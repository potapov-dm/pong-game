# Pong Online Game

This is an online multiplayer version of the classic Pong game. Up to 4 players can join and compete against each other.

###Features
Homepage: The homepage displays the game board with an ongoing match or offers the option to sign in with a chosen username. If the game hasn't started yet, it provides information about the number of currently logged-in players.

Game Start: The first player who signs in has the option to start the game. If any player doesn't want to wait for the game to start, they can leave the room. If the first player leaves, the game start privilege passes to the next logged-in player. The game can also start automatically when the fourth player joins.
![image](https://github.com/d-potapov/pong-game/assets/49323039/2f75d53f-b832-4970-92ce-a5e9f78d47da)

Adaptive Game Board: When the game starts, the game board adapts dynamically based on the number of players (see image below).

![image](https://github.com/d-potapov/pong-game/assets/49323039/cd84f090-f81c-4df6-9dd3-677531c7daf9)
![image](https://github.com/d-potapov/pong-game/assets/49323039/cfd0b6e2-4980-49fc-a483-880a1b8f74b0)
![image](https://github.com/d-potapov/pong-game/assets/49323039/a9c5dcbf-1e32-4d5e-818f-0f423d9ee472)


Gameplay: The objective is to move your paddle, represented by a moving rectangle, to prevent the ball from going past your side. The ball's speed increases as the game progresses.

Player Elimination: If a player fails to hit the ball 3 times, they are eliminated, and the side they were defending gets replaced with a solid line. The ball will bounce off this line.

Player Information: Each player can see how many times the ball has passed them.

Game End: The game ends when the last player fails to hit the ball 3 times.

Statistics: Keep track of and display the number of successful ball bounces (regardless of whether the player bounced it off an opponent or the wall).

###Technologies Used
HTML, CSS, JavaScript for the frontend
PHP for server-side logic
WebSocket for real-time communication
