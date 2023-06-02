let ws = new WebSocket("wss://site188.webte.fei.stuba.sk:9000");
let canvas = document.getElementById("canvas");
const ctx = canvas.getContext("2d");
let player_name = "";
let players_count = 0;
let player_id = -1;
let players = [];
let panels = {};
let ball = {};
let game_started = false;

ws.onopen = function () {
    console.log("Connection established");
};
ws.onerror = function (error) {
    console.log("Unknown WebSocket Error " + JSON.stringify(error));
};
ws.onmessage = function (e) {
    let message = JSON.parse(e.data);
    if (message.type === "players_count") {
        players_count = message.players_count;
        document.getElementById("player-count").innerText = players_count + " / 4";
    } else if (message.type === "game_started") {
        game_started = message.game_started;
        if (game_started) {
            document.getElementById("login").style.display = "none";
            document.getElementById("end-game").style.display = "none";
            document.getElementById("game").style.display = "block";
            document.getElementById("back").disabled = true;
            document.getElementById("start_game_btn").disabled = true;
        }
    } else if (message.type === "players_info") {
        players = message.players_info;
        if (players_count>0)
        console.log(players[0].name);
        if (!game_started && players_count > 0 && players[0].name === player_name) {
            document.getElementById("start_game_btn").disabled = false;
        } else document.getElementById("start_game_btn").disabled = true;
        updatePlayersStats();
    } else if (message.type === "panels_data") {
        panels = message.panels;
    } else if (message.type === "ball_data") {
        ball = message;
    } else if (message.type === "rebound") {
        document.getElementById("rebound").innerText = message.rebound;
    } else if (message.type === "game_ended") {
        player_name = "";
        players_count = 0;
        player_id = -1;
        players = [];
        panels = {};
        game_started = false;
        document.getElementById("back").disabled = false;
        document.getElementById("game").style.display = "none";
        document.getElementById("end-game").style.display = "block";
    }

    if (players[0] != null) {
        try {
            drawField();
            if (!game_started && players_count === 4 && player_id === 4) {
                startGame();
            }
        } catch (e) {
        }
    }
};


function send(msg) {
    try {
        // console.log(">> "+msg);
        ws.send(msg);
    } catch (exception) {
        console.log(exception);
    }
}

function join() {
    let name = document.getElementById("player-name").value;
    if (name.trim().length === 0) {
        alert("Enter name!");
        return;
    }
    for (let i = 0; i < players_count; i++) {
        if (name === players[i].name) {
            alert("Enter another name! This one already in the game.")
            return;
        }
    }

    player_name = name;
    let msg = {type: "player_name", player_name: player_name};
    send(JSON.stringify(msg));
    document.getElementById("login").style.display = "none";
    document.getElementById("game").style.display = "block";
}

function rejoin() {
    document.getElementById("end-game").style.display = "none";
    document.getElementById("login").style.display = "block";
}

function back() {
    if (!game_started) {
        document.getElementById("game").style.display = "none";
        document.getElementById("login").style.display = "block";
        let msg = {type: "player_left", player_name: player_name};
        send(JSON.stringify(msg));
    }
}

function startGame() {
    let msg = {type: "start_game"};
    send(JSON.stringify(msg));
}

function updatePlayersStats() {
    for (let i = 0; i < players_count; i++) {
        document.getElementById("player" + (i + 1) + "-lives").innerText = players[i].lives;
        document.getElementById("player" + (i + 1) + "-card-body").innerText = players[i].name;
        if (players[i].name === player_name) {
            player_id = players[i].id;
        }
    }

    for (let i = players_count; i < 4; i++) {
        document.getElementById("player" + (i + 1) + "-lives").innerText = "0";
        document.getElementById("player" + (i + 1) + "-card-body").innerText = "";
    }
}

function updatePanels() {

    for (let i = 0; i < players_count; i++) {
        if (players[i].lives > 0) {
            if (i === player_id - 1)
                ctx.fillStyle = "#1c6bef";//3fbd20
            else ctx.fillStyle = "rgba(28,107,239,0.4)";//1c6bef
            ctx.fillRect(panels[i].x, panels[i].y, panels[i].width, panels[i].height);
        }
    }
    for (let i = players_count; i < 4; i++) {
        ctx.fillStyle = "#fff";
        ctx.fillRect(panels[i].x, panels[i].y, panels[i].width, panels[i].height);
    }
}

function updateBall() {
    ctx.beginPath();
    ctx.arc(ball.x, ball.y, ball.radius, 0, Math.PI * 2);
    ctx.fillStyle = "#fff";
    ctx.fill();
    ctx.closePath();
}

function drawField() {
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = "#444";
    ctx.fillRect(20, 20, canvas.width - 40, canvas.height - 40);
    if (players_count > 0 && players[0].lives > 0) {
        ctx.fillRect(0, 40, 20, canvas.height - 80);
    }
    if (players_count > 1 && players[1].lives > 0) {
        ctx.fillRect(canvas.width - 20, 40, 20, canvas.height - 80);
    }
    if (players_count > 2 && players[2].lives > 0) {
        ctx.fillRect(40, 0, canvas.width - 80, 20);
    }
    if (players_count > 3 && players[3].lives > 0) {
        ctx.fillRect(40, canvas.height - 20, canvas.width - 80, 20);
    }
    updatePanels();
    updateBall();
}

let leftKeyPressed = false;
let rightKeyPressed = false;
let upKeyPressed = false;
let downKeyPressed = false;

document.addEventListener('keydown', function (event) {
    if (event.target.tagName === 'INPUT') {
        return;
    }
    event.preventDefault();
    if (event.code === 'ArrowLeft') {
        leftKeyPressed = true;
    } else if (event.code === 'ArrowRight') {
        rightKeyPressed = true;
    } else if (event.code === 'ArrowUp') {
        upKeyPressed = true;
    } else if (event.code === 'ArrowDown') {
        downKeyPressed = true;
    }
});

document.addEventListener('keyup', function (event) {
    event.preventDefault();
    if (event.code === 'ArrowLeft') {
        leftKeyPressed = false;
    } else if (event.code === 'ArrowRight') {
        rightKeyPressed = false;
    } else if (event.code === 'ArrowUp') {
        upKeyPressed = false;
    } else if (event.code === 'ArrowDown') {
        downKeyPressed = false;
    }
});

function movePlatform() {
    // if (!game_started && players[player_id - 1].lives > 0) {
    //     return;
    // }

    if (upKeyPressed && (player_id === 1 || player_id === 2)) {
        if (panels[player_id - 1].y - panels[player_id - 1].speed > 40) {
            panels[player_id - 1].y -= panels[player_id - 1].speed;
            let panels_data = {type: "panels_data", panels};
            send(JSON.stringify(panels_data));
        } else {
            panels[player_id - 1].y = 40;
            let panels_data = {type: "panels_data", panels};
            send(JSON.stringify(panels_data));
        }
    }
    if (downKeyPressed && (player_id === 1 || player_id === 2)) {
        if (panels[player_id - 1].y + panels[player_id - 1].speed < canvas.height - 40 - panels[player_id - 1].height) {
            panels[player_id - 1].y += panels[player_id - 1].speed;
            let panels_data = {type: "panels_data", panels};
            send(JSON.stringify(panels_data));
        } else {
            panels[player_id - 1].y = canvas.height - 40 - panels[player_id - 1].height;
            let panels_data = {type: "panels_data", panels};
            send(JSON.stringify(panels_data));
        }
    }
    if (leftKeyPressed && (player_id === 3 || player_id === 4)) {
        if (panels[player_id - 1].x - panels[player_id - 1].speed > 40) {
            panels[player_id - 1].x -= panels[player_id - 1].speed;
            let panels_data = {type: "panels_data", panels};
            send(JSON.stringify(panels_data));
        } else {
            panels[player_id - 1].x = 40;
            let panels_data = {type: "panels_data", panels};
            send(JSON.stringify(panels_data));
        }
    }
    if (rightKeyPressed && (player_id === 3 || player_id === 4)) {
        if (panels[player_id - 1].x + panels[player_id - 1].speed < canvas.width - 40 - panels[player_id - 1].width) {
            panels[player_id - 1].x += panels[player_id - 1].speed;
            let panels_data = {type: "panels_data", panels};
            send(JSON.stringify(panels_data));
        } else {
            panels[player_id - 1].x = canvas.width - 40 - panels[player_id - 1].width;
            let panels_data = {type: "panels_data", panels};
            send(JSON.stringify(panels_data));
        }
    }
}

setInterval(movePlatform, 10); // Call the movePlatform function every 10 milliseconds

