<?php
//ssh xpotapov@147.175.98.188
//sO03JWtGGtuObBg
//
//cd var/www/site188.webte.fei.stuba.sk/z3
//php server.php start
use Workerman\Worker;
use Workerman\Lib\Timer;
use Workerman\WebServer;
use Workerman\Protocols\Websocket;

require_once __DIR__ . '/vendor/autoload.php';

init_globals();

$context = [
    'ssl' => [
        'local_cert' => '/home/xpotapov/webte_fei_stuba_sk.pem',
        'local_pk' => '/home/xpotapov/webte.fei.stuba.sk.key',
        'verify_peer' => false,
    ]
];

$ws_worker = new Worker('websocket://0.0.0.0:9000', $context);
$ws_worker->transport = 'ssl';
$ws_worker->count = 1;

$ws_worker->onWorkerStart = function ($ws_worker) {

    $ws_worker->onConnect = function ($connection) {
        $connection->onWebSocketConnect = function ($connection) {
            echo "New connection " . $connection->id . "\n";
            $connection->send(json_encode($GLOBALS['game_started']));
            $connection->send(json_encode($GLOBALS['panels_data']));
            $connection->send(json_encode($GLOBALS['ball_data']));
            $connection->send(json_encode($GLOBALS['players_count']));
            $connection->send(json_encode($GLOBALS['players_info']));
        };
    };

    $ws_worker->onMessage = function ($connection, $data) use ($ws_worker) {
        $data = json_decode($data);
        if ($data->type === "player_name") {
            $GLOBALS['players_count']->players_count++;
            addPlayer($connection->id, $data->player_name, $GLOBALS['players_count']->players_count);
            broadcastMessage(json_encode($GLOBALS['players_count']), $ws_worker);
            broadcastMessage(json_encode($GLOBALS['players_info']), $ws_worker);
            echo "Player " . $data->player_name . " joined (conID: " . $connection->id . ")\n";
        } elseif ($data->type === "player_left") {
            if (!$GLOBALS["game_started"]->game_started) {
                echo "Player " . $data->player_name . " left (conID: " . $connection->id . ")\n";
                removePlayer($data->player_name, $ws_worker);
            }
        } elseif ($data->type === "start_game") {
            $GLOBALS['game_started']->game_started = true;
            broadcastMessage(json_encode($GLOBALS['game_started']), $ws_worker);
            for ($i = $GLOBALS["players_count"]->players_count; $i < 4; $i++) {
                $player = new stdClass();
                $player->connectionId = -1;
                $player->id = $i;
                $player->name = "";
                $player->lives = 0;
                $GLOBALS['players_info']->players_info[] = $player;
            }
            startGame($ws_worker);
        } elseif ($data->type === "panels_data") {
            $GLOBALS['panels_data'] = $data;
            broadcastMessage(json_encode($GLOBALS['panels_data']), $ws_worker);
        }
    };

    $ws_worker->onClose = function ($connection) use ($ws_worker) {
        for ($i = 0; $i < $GLOBALS["players_count"]->players_count; $i++) {
            if ($GLOBALS['players_info']->players_info[$i]->connectionId === $connection->id) {
                echo "Player " . $GLOBALS['players_info']->players_info[$i]->name . " left (conID: " . $connection->id . ")\n";
                if (!$GLOBALS["game_started"]->game_started) {
                    removePlayer($GLOBALS['players_info']->players_info[$i]->name, $ws_worker);
                } else {
                    $GLOBALS['players_info']->players_info[$i]->lives = 0;
                    broadcastMessage(json_encode($GLOBALS['players_info']), $ws_worker);
                }
                break;
            }
        }
        echo "Connection closed " . $connection->id . "\n";
    };
};
Worker::runAll();


function broadcastMessage($message, $ws_worker)
{
    foreach ($ws_worker->connections as $connection) {
        $connection->send($message);
    }
}

function addPlayer($connectionId, $name, $id)
{
    $player = new stdClass();
    $player->connectionId = $connectionId;
    $player->id = $id;
    $player->name = $name;
    $player->lives = 3;
    $GLOBALS['players_info']->players_info[] = $player;
}

function removePlayer($name, $ws_worker)
{
    for ($i = 0; $i < $GLOBALS['players_count']->players_count; $i++) {
        if ($GLOBALS['players_info']->players_info[$i]->name === $name) {
            for ($j = $i + 1; $j < $GLOBALS['players_count']->players_count; $j++) {
                $GLOBALS['players_info']->players_info[$j]->id--;
            }
//            unset($GLOBALS['players_info']->players_info[$i]);
            array_splice($GLOBALS['players_info']->players_info, $i, 1);
            $GLOBALS['players_count']->players_count--;
            broadcastMessage(json_encode($GLOBALS['players_count']), $ws_worker);
            broadcastMessage(json_encode($GLOBALS['players_info']), $ws_worker);
            break;
        }
    }
}

function startGame($ws_worker)
{
    $timer_id = Timer::add(0.04, function () use ($ws_worker, &$timer_id) {
        if (isAllPlayersDied()) {
            Timer::del($timer_id);
            $msg = new stdClass();
            $msg->type = "game_ended";
            broadcastMessage(json_encode($msg), $ws_worker);
            init_globals();
            broadcastMessage(json_encode($GLOBALS['game_started']), $ws_worker);
            broadcastMessage(json_encode($GLOBALS['panels_data']), $ws_worker);
            broadcastMessage(json_encode($GLOBALS['ball_data']), $ws_worker);
            broadcastMessage(json_encode($GLOBALS['players_count']), $ws_worker);
            broadcastMessage(json_encode($GLOBALS['players_info']), $ws_worker);
            broadcastMessage(json_encode($GLOBALS['rebound']), $ws_worker);
            return;
        }
        if (updateBallPosition($ws_worker)) {
            $ball = $GLOBALS['ball_data'];
            $ball->x += $ball->dx;
            $ball->y += $ball->dy;
            $GLOBALS['ball_data'] = $ball;
            initBall();
            initPanels();
            broadcastMessage(json_encode($GLOBALS['players_info']), $ws_worker);
            broadcastMessage(json_encode($GLOBALS['ball_data']), $ws_worker);
            broadcastMessage(json_encode($GLOBALS['panels_data']), $ws_worker);
        }
        broadcastMessage(json_encode($GLOBALS['ball_data']), $ws_worker);
    });

}

function isAllPlayersDied()
{
    foreach ($GLOBALS['players_info']->players_info as $player) {
        if ($player->lives > 0) {
            return false;
        }
    }
    return true;
}

function updateBallPosition($ws_worker)
{
    $ball = $GLOBALS['ball_data'];
    if ($ball->x + $ball->dx - $ball->radius < 15) {
        if ($ball->y - $ball->radius < 40 or
            $ball->y + $ball->radius > $GLOBALS['canvas']->height - 40 or
            checkCollisionWithPanel($GLOBALS['panels_data']->panels[0]) or
            $GLOBALS['players_info']->players_info[0]->lives < 1) {
            $ball->dx = -($ball->dx);
            $GLOBALS['rebound']->rebound++;
            if ($GLOBALS['rebound']->rebound % 5 === 0) {
                $ball->dx += ($ball->dx > 0) ? 1 : -1;
                $ball->dy += ($ball->dy > 0) ? 1 : -1;
            }
        } else {
            if ($ball->x + $ball->dx - $ball->radius < -5) {
                $GLOBALS['players_info']->players_info[0]->lives--;
                return true;
            }
        }
    } else if ($ball->x + $ball->dx + $ball->radius > $GLOBALS['canvas']->width - 15) {
        if ($ball->y - $ball->radius < 40 or
            $ball->y + $ball->radius > $GLOBALS['canvas']->height - 40 or
            checkCollisionWithPanel($GLOBALS['panels_data']->panels[1]) or
            $GLOBALS['players_info']->players_info[1]->lives < 1) {
            $ball->dx = -($ball->dx);
            $GLOBALS['rebound']->rebound++;
            if ($GLOBALS['rebound']->rebound % 5 === 0) {
                $ball->dx += ($ball->dx > 0) ? 1 : -1;
                $ball->dy += ($ball->dy > 0) ? 1 : -1;
            }
        } else {
            if ($ball->x + $ball->dx + $ball->radius > $GLOBALS['canvas']->width + 5) {
                $GLOBALS['players_info']->players_info[1]->lives--;
                return true;
            }
        }
    }

    if ($ball->y + $ball->dy - $ball->radius < 15) {
        if ($ball->x - $ball->radius < 40 or
            $ball->x + $ball->radius > $GLOBALS['canvas']->width - 40 or
            checkCollisionWithPanel($GLOBALS['panels_data']->panels[2]) or
            $GLOBALS['players_info']->players_info[2]->lives < 1) {
            $ball->dy = -($ball->dy);
            $GLOBALS['rebound']->rebound++;
            if ($GLOBALS['rebound']->rebound % 5 === 0) {
                $ball->dx += ($ball->dx > 0) ? 1 : -1;
                $ball->dy += ($ball->dy > 0) ? 1 : -1;
            }
        } else {
            if ($ball->y + $ball->dy - $ball->radius < -5) {
                $GLOBALS['players_info']->players_info[2]->lives--;
                return true;
            }
        }
    } else if ($ball->y + $ball->dy + $ball->radius > $GLOBALS['canvas']->height - 15) {
        if ($ball->x - $ball->radius < 40 or
            $ball->x + $ball->radius > $GLOBALS['canvas']->width - 40 or
            checkCollisionWithPanel($GLOBALS['panels_data']->panels[3]) or
            $GLOBALS['players_info']->players_info[3]->lives < 1) {
            $ball->dy = -($ball->dy);
            $GLOBALS['rebound']->rebound++;
            if ($GLOBALS['rebound']->rebound % 5 === 0) {
                $ball->dx += ($ball->dx > 0) ? 1 : -1;
                $ball->dy += ($ball->dy > 0) ? 1 : -1;
            }
        } else {
            if ($ball->y + $ball->dy + $ball->radius > $GLOBALS['canvas']->height + 5) {
                $GLOBALS['players_info']->players_info[3]->lives--;
                return true;
            }
        }
    }

    $ball->x += $ball->dx;
    $ball->y += $ball->dy;

    $GLOBALS['ball_data'] = $ball;
    broadcastMessage(json_encode($GLOBALS['rebound']), $ws_worker);
    return false;
}

function checkCollisionWithPanel($panel)
{
//    echo $GLOBALS['ball_data']->y + $GLOBALS['ball_data']->radius . " " . $panel->y . (" | " . ($GLOBALS['ball_data']->y - $GLOBALS['ball_data']->radius)) . (" " . ($panel->y + $panel->height)) . (" | " . ($GLOBALS['ball_data']->x + $GLOBALS['ball_data']->radius)) . " " . $panel->x . (" | " . ($GLOBALS['ball_data']->x - $GLOBALS['ball_data']->radius)) . (" " . ($panel->x + $panel->width)) . "\n";

    if ($GLOBALS['ball_data']->y + $GLOBALS['ball_data']->radius >= $panel->y &&
        $GLOBALS['ball_data']->y - $GLOBALS['ball_data']->radius <= $panel->y + $panel->height &&
        $GLOBALS['ball_data']->x + $GLOBALS['ball_data']->radius >= $panel->x &&
        $GLOBALS['ball_data']->x - $GLOBALS['ball_data']->radius <= $panel->x + $panel->width) {
        return true;
    } else return false;
}


function init_globals()
{
    $GLOBALS['players_count'] = new stdClass();
    $GLOBALS['players_count']->type = "players_count";
    $GLOBALS['players_count']->players_count = 0;

    $GLOBALS['players_info'] = new stdClass();
    $GLOBALS['players_info']->type = "players_info";
    $GLOBALS['players_info']->players_info = array();

    $GLOBALS['game_started'] = new stdClass();
    $GLOBALS['game_started']->type = "game_started";
    $GLOBALS['game_started']->game_started = false;

    $GLOBALS['canvas'] = new stdClass();
    $GLOBALS['canvas']->width = 400;
    $GLOBALS['canvas']->height = 400;

    $GLOBALS['rebound'] = new stdClass();
    $GLOBALS['rebound']->type = "rebound";
    $GLOBALS['rebound']->rebound = 0;

    initBall();
    initPanels();
}

function initBall()
{
    $GLOBALS['ball_data'] = new stdClass();
    $GLOBALS['ball_data']->type = "ball_data";
    $GLOBALS['ball_data']->x = $GLOBALS['canvas']->width / 2;
    $GLOBALS['ball_data']->y = $GLOBALS['canvas']->height / 2;
    $GLOBALS['ball_data']->radius = 10;
    $GLOBALS['ball_data']->speed = 5;
    $GLOBALS['ball_data']->dx = rand(-4, 4);
    if ($GLOBALS['ball_data']->dx < 0) {
        $GLOBALS['ball_data']->dy = $GLOBALS['ball_data']->speed + $GLOBALS['ball_data']->dx;
    } elseif ($GLOBALS['ball_data']->dx > 0) {
        $GLOBALS['ball_data']->dy = -($GLOBALS['ball_data']->speed - $GLOBALS['ball_data']->dx);
    } else {
        $GLOBALS['ball_data']->dx = 2;
        $GLOBALS['ball_data']->dy = -3;
    }
}

function initPanels()
{
    $GLOBALS['panels_data'] = new stdClass();
    $GLOBALS['panels_data']->type = "panels_data";
    $panel1 = new stdClass();
    $panel1->x = 5;
    $panel1->y = ($GLOBALS['canvas']->height - 50) / 2;
    $panel1->width = 15;
    $panel1->height = 70;
    $panel1->player_id = 1;
    $panel1->speed = 5;
    $panel2 = new stdClass();
    $panel2->x = $GLOBALS['canvas']->width - 20;
    $panel2->y = ($GLOBALS['canvas']->height - 50) / 2;
    $panel2->width = 15;
    $panel2->height = 70;
    $panel2->player_id = 2;
    $panel2->speed = 5;
    $panel3 = new stdClass();
    $panel3->x = ($GLOBALS['canvas']->width - 50) / 2;
    $panel3->y = 5;
    $panel3->width = 70;
    $panel3->height = 15;
    $panel3->player_id = 3;
    $panel3->speed = 5;
    $panel4 = new stdClass();
    $panel4->x = ($GLOBALS['canvas']->width - 50) / 2;
    $panel4->y = $GLOBALS['canvas']->height - 20;
    $panel4->width = 70;
    $panel4->height = 15;
    $panel4->player_id = 4;
    $panel4->speed = 5;
    $GLOBALS['panels_data']->panels = array($panel1, $panel2, $panel3, $panel4);
}