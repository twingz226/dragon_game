<script setup>
import { ref, onMounted, onUnmounted, reactive, watch } from 'vue';
import axios from 'axios';
import Scoreboard from './Scoreboard.vue';


const props = defineProps(['roomCode', 'playerId', 'playerName', 'isHost', 'obstacleSeed']);
const emit = defineEmits(['game-over', 'back']);

// --- Game Constants ---
const CANVAS_WIDTH = 900;
const CANVAS_HEIGHT = 300;
const MOBILE_CANVAS_WIDTH = 350;
const MOBILE_CANVAS_HEIGHT = 200;
const GRAVITY = 1800;
const JUMP_FORCE = -650;
const GROUND_Y_DESKTOP = 250;
const GROUND_Y_MOBILE = 150;
const groundY = ref(window.innerWidth <= 768 ? GROUND_Y_MOBILE : GROUND_Y_DESKTOP);
const DINO_WIDTH = 66; // Increased from 44
const DINO_HEIGHT = 66; // Increased from 44
const INITIAL_SPEED = 400;
const SPEED_INCREMENT = 0.1;
const BIRD_COLORS = [
    { main: '#fbbf24', wing: '#f59e0b', beak: '#f97316' }, // Yellow
    { main: '#f43f5e', wing: '#e11d48', beak: '#fb7185' }, // Rose/Red
    { main: '#8b5cf6', wing: '#7c3aed', beak: '#a78bfa' }, // violet
    { main: '#06b6d4', wing: '#0891b2', beak: '#22d3ee' }, // Cyan
    { main: '#10b981', wing: '#059669', beak: '#34d399' }, // Emerald/Green
    { main: '#f97316', wing: '#ea580c', beak: '#fdba74' }, // Orange
];


// --- State ---
const canvasRef = ref(null);
const ctx = ref(null);
const isMobile = ref(window.innerWidth <= 768);
const canvasWidth = ref(isMobile.value ? MOBILE_CANVAS_WIDTH : CANVAS_WIDTH);
const canvasHeight = ref(isMobile.value ? MOBILE_CANVAS_HEIGHT : CANVAS_HEIGHT);
const gameState = reactive({
    isRunning: false,
    isDead: false,
    score: 0,
    speed: INITIAL_SPEED,
    lastTime: 0,
});

const localPlayer = reactive({
    y: groundY.value - DINO_HEIGHT,
    vy: 0,
    onGround: true,
    isDead: false,
    wingPhase: 0,
});

const remotePlayers = ref({}); // { playerId: { y, isDead, name, score } }
const obstacles = ref([]);
const particles = ref([]);
const gameEnded = ref(false);
const champion = ref(null);
const scoreboardKey = ref(0);
const showRestartConfirm = ref(false);
const playerHighScores = ref({}); // { playerId: score }




// PRNG for synced obstacles
let randomSeed = props.obstacleSeed;
function mulberry32(a) {
    return function() {
      let t = a += 0x6D2B79F5;
      t = Math.imul(t ^ t >>> 15, t | 1);
      t ^= t + Math.imul(t ^ t >>> 7, t | 61);
      return ((t ^ t >>> 14) >>> 0) / 4294967296;
    }
}
let nextRandom = mulberry32(randomSeed);

// --- WebSocket Setup ---
let channel = null;

onMounted(() => {
    ctx.value = canvasRef.value.getContext('2d');
    setupEcho();
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('resize', handleResize);
    window.addEventListener('touchstart', handleTouchStart);
    requestAnimationFrame(gameLoop);
    
    // Start game automatically after a small delay for sync
    setTimeout(() => {
        gameState.isRunning = true;
    }, 1000);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('touchstart', handleTouchStart);
    if (channel) {
        window.Echo.leave(`game.room.${props.roomCode}`);
    }
});

function setupEcho() {
    channel = window.Echo.join(`game.room.${props.roomCode}`)
        .here((users) => {
            users.forEach(user => {
                if (user.id !== props.playerId) {
                    remotePlayers.value[user.id] = { 
                        y: groundY.value - DINO_HEIGHT, 
                        vy: 0, 
                        onGround: true, 
                        isDead: false, 
                        name: user.name, 
                        wingPhase: 0 
                    };
                }
            });
            fetchHighScores();
        })
        .joining((user) => {
            remotePlayers.value[user.id] = { 
                y: groundY.value - DINO_HEIGHT, 
                vy: 0, 
                onGround: true, 
                isDead: false, 
                name: user.name, 
                wingPhase: 0 
            };
            fetchHighScores();
        })

        .leaving((user) => {
            delete remotePlayers.value[user.id];
        })
        .listenForWhisper('jump', (e) => {
            if (remotePlayers.value[e.playerId]) {
                const p = remotePlayers.value[e.playerId];
                p.vy = JUMP_FORCE;
                p.onGround = false;
                // Sync position to avoid drift
                p.y = e.y;
            }
        })
        .listenForWhisper('player-died', (e) => {
            if (remotePlayers.value[e.playerId]) {
                remotePlayers.value[e.playerId].isDead = true;
                remotePlayers.value[e.playerId].finalScore = e.score;
            }
            checkAllPlayersDead();
        })
        .listenForWhisper('player-respawned', (e) => {
            if (remotePlayers.value[e.playerId]) {
                remotePlayers.value[e.playerId].isDead = false;
                remotePlayers.value[e.playerId].y = groundY.value - DINO_HEIGHT;
            }
        });
    
    fetchHighScores();
}


async function fetchHighScores() {
    const ids = [props.playerId, ...Object.keys(remotePlayers.value)];
    try {
        const response = await axios.post('/api/personal-high-scores', { player_ids: ids });
        playerHighScores.value = response.data;
    } catch (e) {
        console.error("Failed to fetch high scores", e);
    }
}


function handleKeyDown(e) {
    console.log('Key pressed:', e.code, 'gameEnded:', gameEnded.value, 'localPlayer.isDead:', localPlayer.isDead, 'localPlayer.onGround:', localPlayer.onGround);
    
    if (e.code === 'Space') {
        if (gameEnded.value) {
            // Game has ended, allow restart with confirmation
            console.log('Game ended, checking restart confirmation');
            if (!showRestartConfirm.value) {
                showRestartConfirm.value = true;
            } else {
                restartGame();
            }
        } else if (localPlayer.onGround && !localPlayer.isDead) {
            console.log('Jumping');
            jump();
        } else if (localPlayer.isDead) {
            // Player is dead but game hasn't ended for everyone yet
            console.log('Waiting for all players to finish...');
        }
    } else if (e.key === 'Escape') {
        if (showRestartConfirm.value) {
            showRestartConfirm.value = false;
        } else {
            emit('back');
        }
    }

}

function restartGame() {
    console.log('=== RESTART GAME CALLED ===');
    showRestartConfirm.value = false;
    gameState.score = 0;

    gameState.speed = INITIAL_SPEED;
    gameState.isRunning = true;
    gameState.lastTime = 0;
    
    localPlayer.isDead = false;
    localPlayer.y = groundY.value - DINO_HEIGHT;
    localPlayer.vy = 0;
    localPlayer.onGround = true;
    
    obstacles.value = [];
    nextRandom = mulberry32(props.obstacleSeed);
    
    // Reset game state
    gameEnded.value = false;
    champion.value = null;
    
    // Reset remote players
    Object.keys(remotePlayers.value).forEach(playerId => {
        const p = remotePlayers.value[playerId];
        p.isDead = false;
        p.y = groundY.value - DINO_HEIGHT;
        p.vy = 0;
        p.onGround = true;
        delete p.finalScore;
    });
    
    // Broadcast respawn
    channel.whisper('player-respawned', {
        playerId: props.playerId
    });

    // Refresh scoreboard
    scoreboardKey.value++;
    
    console.log('=== RESTART GAME COMPLETED ===');

}

function jump() {
    localPlayer.vy = JUMP_FORCE;
    localPlayer.onGround = false;
    
    // Whisper to others
    channel.whisper('jump', {
        playerId: props.playerId,
        y: localPlayer.y
    });
}

function handleResize() {
    const wasMobile = isMobile.value;
    isMobile.value = window.innerWidth <= 768;
    
    if (wasMobile !== isMobile.value) {
        canvasWidth.value = isMobile.value ? MOBILE_CANVAS_WIDTH : CANVAS_WIDTH;
        canvasHeight.value = isMobile.value ? MOBILE_CANVAS_HEIGHT : CANVAS_HEIGHT;
        groundY.value = isMobile.value ? GROUND_Y_MOBILE : GROUND_Y_DESKTOP;
    }
}

function handleTouchStart(e) {
    e.preventDefault();
    if (gameEnded.value) {
        // Game has ended, allow restart with confirmation
        if (!showRestartConfirm.value) {
            showRestartConfirm.value = true;
        } else {
            restartGame();
        }
    } else if (localPlayer.onGround && !localPlayer.isDead) {
        jump();
    } else if (localPlayer.isDead) {
        console.log('Waiting for all players to finish...');
    }
}


// --- Game Engine ---

function gameLoop(timestamp) {
    if (!gameState.lastTime) gameState.lastTime = timestamp;
    const deltaTime = (timestamp - gameState.lastTime) / 1000;
    gameState.lastTime = timestamp;

    if (gameState.isRunning && !localPlayer.isDead) {
        update(deltaTime);
    }
    
    draw();
    requestAnimationFrame(gameLoop);
}

function update(dt) {
    // 1. Update Physics
    localPlayer.vy += GRAVITY * dt;
    localPlayer.y += localPlayer.vy * dt;
    
    // Animate wings and limbs (faster when in air or when game speed increases)
    // Scale animation speed relative to the current game speed vs the starting speed
    const speedMultiplier = gameState.speed / INITIAL_SPEED;
    const flapSpeed = localPlayer.onGround ? (10 * speedMultiplier) : (25 * speedMultiplier);
    localPlayer.wingPhase = (localPlayer.wingPhase + dt * flapSpeed) % (Math.PI * 2);

    // Update remote player wing animations and physics
    Object.values(remotePlayers.value).forEach(p => {
        if (!p.isDead) {
            // Apply gravity to remote players if they are in the air
            if (!p.onGround) {
                p.vy += GRAVITY * dt;
                p.y += p.vy * dt;

                if (p.y > groundY.value - DINO_HEIGHT) {
                    p.y = groundY.value - DINO_HEIGHT;
                    p.vy = 0;
                    p.onGround = true;
                }
            }

            p.wingPhase = (p.wingPhase || 0) + dt * 15;
            p.wingPhase %= (Math.PI * 2);
        }
    });
    
    if (isNaN(localPlayer.y)) {
        console.error("localPlayer.y is NaN!", { vy: localPlayer.vy, dt, GRAVITY });
    }

    if (localPlayer.y > groundY.value - DINO_HEIGHT) {
        localPlayer.y = groundY.value - DINO_HEIGHT;
        localPlayer.vy = 0;
        localPlayer.onGround = true;
    }

    // 2. Obstacles
    gameState.speed += SPEED_INCREMENT;
    // Accumulate score as float to avoid precision loss every frame
    gameState.score += gameState.speed * dt * 0.1;

    // Spawn obstacles based on seeded random
    const lastObstacle = obstacles.value[obstacles.value.length - 1];
    if (!lastObstacle || (canvasWidth.value - lastObstacle.x > 400 + nextRandom() * 600)) {
        spawnObstacle();
    }

    obstacles.value.forEach((obs, index) => {
        obs.x -= gameState.speed * dt;
        
        // Animate bird wings
        if (obs.type === 'bird') {
            obs.wingPhase = (obs.wingPhase + dt * 10) % (Math.PI * 2);
        }
        
        // Collision detection for local player
        if (checkCollision(localPlayer, obs, canvasWidth.value/4)) {
            console.log("Collision detected with obstacle:", obs);
            die();
        }
        
        // Collision detection for remote players
        const playerSpacing = 60;
        let playerIndex = 0;
        Object.values(remotePlayers.value).forEach(p => {
            const offsetX = canvasWidth.value/4 + ((playerIndex + 1) * playerSpacing);
            if (!p.isDead && checkCollision(p, obs, offsetX)) {
                console.log(`Remote player ${p.name} collided with obstacle:`, obs);
                // Remote player collision is handled by their own game instance
            }
            playerIndex++;
        });
    });

    // Remove off-screen obstacles
    obstacles.value = obstacles.value.filter(obs => obs.x + obs.width > 0);
    
    // Update particles
    particles.value.forEach(p => {
        p.x += p.vx * dt;
        p.y += p.vy * dt;
        p.life -= dt;
    });
    particles.value = particles.value.filter(p => p.life > 0);
}

function spawnObstacle() {
    const typeRand = nextRandom();
    let type;
    
    if (typeRand > 0.7) {
        type = 'bird';
    } else if (typeRand > 0.35) {
        type = 'large';
    } else {
        type = 'small';
    }
    
    if (type === 'bird') {
        // Flying bird obstacle
        const birdHeight = 120 + nextRandom() * 80; // Random height between 120-200
        const colorIdx = Math.floor(nextRandom() * BIRD_COLORS.length);
        obstacles.value.push({
            x: canvasWidth.value,
            width: 35,
            height: 25,
            y: birdHeight,
            type: 'bird',
            wingPhase: 0,
            colors: BIRD_COLORS[colorIdx]
        });

    } else {
        // Ground cacti obstacles
        obstacles.value.push({
            x: canvasWidth.value,
            width: type === 'large' ? 30 : 20,
            height: type === 'large' ? 60 : 40,
            y: groundY.value - (type === 'large' ? 60 : 40),
            type: type
        });
    }
}

function checkCollision(player, obs, playerX = canvasWidth.value/4) {
    // Basic AABB
    const padding = 5; // A bit of leniency
    const playerRight = playerX + DINO_WIDTH;
    const obsRight = obs.x + obs.width;
    
    const collided = (
        playerX < obsRight - padding &&
        playerRight > obs.x + padding &&
        player.y < obs.y + obs.height - padding &&
        player.y + DINO_HEIGHT > obs.y + padding
    );
    
    if (collided) {
        console.log("Collision Details:", {
            player: { x: playerX, y: player.y, w: DINO_WIDTH, h: DINO_HEIGHT },
            obs: { x: obs.x, y: obs.y, w: obs.width, h: obs.height },
            padding
        });
    }
    
    return collided;
}

function die() {
    console.warn("die() called. Current score:", gameState.score);
    localPlayer.isDead = true;
    gameState.isRunning = false;
    
    // Visual effect: particles
    for(let i=0; i<15; i++) {
        particles.value.push({
            x: canvasWidth.value/4 + 20,
            y: localPlayer.y + 20,
            vx: (Math.random() - 0.5) * 400,
            vy: (Math.random() - 0.5) * 400,
            life: 1,
            color: '#f43f5e'
        });
    }

    // Broadcast death with score
    channel.whisper('player-died', {
        playerId: props.playerId,
        score: Math.floor(gameState.score)
    });

    // Save score to DB
    axios.post('/api/scores', {
        player_name: props.playerName,
        player_id: props.playerId,
        room_code: props.roomCode,
        score: Math.floor(gameState.score)
    });
    
    // Check if all players are dead (including this one)
    checkAllPlayersDead();
}

function checkAllPlayersDead() {
    const allPlayers = {
        [props.playerId]: { isDead: localPlayer.isDead, name: props.playerName, score: Math.floor(gameState.score) }
    };
    
    Object.assign(allPlayers, remotePlayers.value);
    
    const allDead = Object.values(allPlayers).every(player => player.isDead);
    
    console.log('checkAllPlayersDead - allDead:', allDead, 'gameEnded:', gameEnded.value, 'players:', allPlayers);
    
    if (allDead && !gameEnded.value) {
        gameEnded.value = true;
        console.log('Setting gameEnded to true');
        declareChampion(allPlayers);
    }
}

function declareChampion(players) {
    const alivePlayers = Object.values(players).filter(player => !player.isDead);
    
    if (alivePlayers.length === 1) {
        champion.value = alivePlayers[0];
    } else {
        // All players are dead, find highest score
        const sortedPlayers = Object.values(players).sort((a, b) => b.score - a.score);
        champion.value = sortedPlayers[0];
    }
    
    console.log('Champion:', champion.value);
}

// --- Rendering ---

function draw() {
    const c = ctx.value;
    if (!c) return;

    // Clear
    c.fillStyle = '#0f172a';
    c.fillRect(0, 0, canvasWidth.value, canvasHeight.value);

    // Grid lines for "speed" feel
    c.strokeStyle = 'rgba(56, 189, 248, 0.05)';
    c.lineWidth = 1;
    for(let i=0; i<canvasWidth.value; i += 50) {
        const offset = (gameState.speed * (gameState.lastTime / 1000)) % 50;
        c.beginPath();
        c.moveTo(i - offset, 0);
        c.lineTo(i - offset, canvasHeight.value);
        c.stroke();
    }

    // Ground
    c.fillStyle = '#334155';
    c.fillRect(0, groundY.value, canvasWidth.value, 5);
    
    // Obstacles
    obstacles.value.forEach(obs => {
        if (obs.type === 'bird') {
            // Draw flying bird
            drawBird(obs.x, obs.y, obs.wingPhase, obs.colors);
        } else {

            // Draw ground cacti
            c.fillStyle = '#e2e8f0';
            c.beginPath();
            if (c.roundRect) {
                c.roundRect(obs.x, obs.y, obs.width, obs.height, 5);
            } else {
                c.rect(obs.x, obs.y, obs.width, obs.height);
            }
            c.fill();
            // Shadow
            c.fillStyle = 'rgba(56, 189, 248, 0.3)';
            c.fillRect(obs.x + 2, obs.y + obs.height, obs.width - 4, 3);
        }
    });

    // Local Player
    drawDino(canvasWidth.value/4, localPlayer.y, localPlayer.isDead ? '#64748b' : '#38bdf8', props.playerName, true, localPlayer.wingPhase);

    // Remote Players with spacing
    const playerSpacing = 60; // Horizontal spacing between players
    let playerIndex = 0;
    
    Object.values(remotePlayers.value).forEach(p => {
        const offsetX = canvasWidth.value/4 + ((playerIndex + 1) * playerSpacing);
        drawDino(offsetX, p.y, p.isDead ? '#475569' : 'rgba(14, 165, 233, 0.5)', p.name, false, p.wingPhase || 0);
        playerIndex++;
    });
    
    // Particles
    particles.value.forEach(p => {
        c.globalAlpha = p.life;
        c.fillStyle = p.color;
        c.fillRect(p.x, p.y, 4, 4);
    });
    c.globalAlpha = 1;

    // HUD
    c.fillStyle = '#f8fafc';
    c.font = '16px "Press Start 2P", monospace';
    c.textAlign = 'right';
    const displayScore = Math.floor(gameState.score).toString().padStart(5, '0');
    // Shift score left to avoid clipping on small viewports
    const scoreX = isMobile.value ? canvasWidth.value - 80 : canvasWidth.value - 150;
    c.fillText(`SCORE: ${displayScore}`, scoreX, 40);
    
    if (localPlayer.isDead || gameEnded.value) {
        drawGameOver();
    }

    if (showRestartConfirm.value) {
        drawRestartConfirmation();
    }
}


function drawBird(x, y, wingPhase, colors = BIRD_COLORS[0]) {
    const c = ctx.value;
    
    // Bird body
    c.fillStyle = colors.main;
    c.fillRect(x + 10, y + 8, 15, 10);
    
    // Bird head
    c.fillRect(x + 22, y + 6, 8, 8);
    
    // Beak
    c.fillStyle = colors.beak;
    c.fillRect(x + 28, y + 8, 4, 2);
    
    // Eye
    c.fillStyle = '#000000';
    c.fillRect(x + 24, y + 7, 1, 1);
    
    // Animated wings
    const wingOffset = Math.sin(wingPhase) * 8;
    c.fillStyle = colors.wing;

    
    // Upper wing
    c.beginPath();
    c.moveTo(x + 12, y + 10);
    c.lineTo(x + 5, y + 5 - wingOffset);
    c.lineTo(x + 8, y + 12);
    c.closePath();
    c.fill();
    
    // Lower wing
    c.beginPath();
    c.moveTo(x + 12, y + 12);
    c.lineTo(x + 5, y + 17 + wingOffset);
    c.lineTo(x + 8, y + 14);
    c.closePath();
    c.fill();
    
    // Tail
    c.fillStyle = '#f59e0b';
    c.beginPath();
    c.moveTo(x + 10, y + 13);
    c.lineTo(x + 5, y + 11);
    c.lineTo(x + 5, y + 15);
    c.closePath();
    c.fill();
}

function drawDino(x, y, color, name, isLocal, wingPhase = 0) {
    const c = ctx.value;
    
    c.save();
    
    // Scale drawing proportionally based on original 44x44 size
    const scaleX = DINO_WIDTH / 44;
    const scaleY = DINO_HEIGHT / 44;
    
    c.translate(x, y);
    c.scale(scaleX, scaleY);
    c.translate(-x, -y);
    
    // T-Rex body parts
    c.fillStyle = color;
    
    // Main body (larger rectangle)
    c.beginPath();
    if (c.roundRect) {
        c.roundRect(x + 8, y + 12, 24, 20, 3);
    } else {
        c.rect(x + 8, y + 12, 24, 20);
    }
    c.fill();
    
    // Head (rectangular with snout)
    c.beginPath();
    if (c.roundRect) {
        c.roundRect(x + 24, y + 4, 16, 14, 2);
    } else {
        c.rect(x + 24, y + 4, 16, 14);
    }
    c.fill();
    
    // Snout extension
    c.fillRect(x + 36, y + 8, 6, 6);
    
    // Dragon Tail Animation
    const tailSwing = Math.sin(wingPhase) * 6; // swish horizontally/vertically
    
    // Dragon Tail (longer, spiky)
    c.beginPath();
    c.moveTo(x + 8, y + 22);
    c.lineTo(x - 4, y + 16 + tailSwing/2);
    c.lineTo(x - 12 - Math.abs(tailSwing), y + 10 + tailSwing);
    c.lineTo(x - 8, y + 22 + tailSwing/2);
    c.lineTo(x + 4, y + 28);
    c.closePath();
    c.fill();

    // Wing Animation
    const flapOffset = Math.sin(wingPhase) * 12; // vertical flap
    const spreadOffset = Math.cos(wingPhase) * 4; // horizontal spread

    // Dragon Wings (Enhanced)
    // Draw the far wing (darker)
    c.fillStyle = isLocal ? '#0284c7' : '#334155'; // darker shade of the color
    c.beginPath();
    c.moveTo(x + 16, y + 14); // base
    c.lineTo(x + 8, y + 2 - flapOffset/2); // joint 1
    c.lineTo(x - spreadOffset, y - 10 - flapOffset); // tip
    c.lineTo(x + 12, y - 6 - flapOffset*0.8); // middle outer
    c.lineTo(x + 20 + spreadOffset, y - 14 - flapOffset*1.2); // second tip
    c.lineTo(x + 24, y - 2 - flapOffset/2); // lower inner
    c.lineTo(x + 32 + spreadOffset, y - 6 - flapOffset); // third tip
    c.lineTo(x + 26, y + 12); // back to body
    c.closePath();
    c.fill();
    
    // Draw the near wing (lighter)
    c.fillStyle = color;
    c.beginPath();
    c.moveTo(x + 12, y + 16); // base
    c.lineTo(x + 2, y + 4 - flapOffset/2); // joint 1
    c.lineTo(x - 8 - spreadOffset, y - 8 - flapOffset); // tip
    c.lineTo(x + 6, y - 4 - flapOffset*0.8); // middle outer
    c.lineTo(x + 14 + spreadOffset/2, y - 12 - flapOffset*1.2); // second tip
    c.lineTo(x + 18, y - flapOffset/2); // lower inner
    c.lineTo(x + 26 + spreadOffset, y - 4 - flapOffset); // third tip
    c.lineTo(x + 20, y + 14); // back to body
    c.closePath();
    c.fill();
    
    // Add wing bones/veins for detail
    c.strokeStyle = '#0f172a';
    c.lineWidth = 1;
    // Main bone
    c.beginPath();
    c.moveTo(x + 12, y + 16);
    c.lineTo(x + 2, y + 4 - flapOffset/2);
    c.lineTo(x - 8 - spreadOffset, y - 8 - flapOffset);
    c.stroke();
    // Inner bones
    c.beginPath();
    c.moveTo(x + 2, y + 4 - flapOffset/2);
    c.lineTo(x + 14 + spreadOffset/2, y - 12 - flapOffset*1.2);
    c.stroke();
    c.beginPath();
    c.moveTo(x + 2, y + 4 - flapOffset/2);
    c.lineTo(x + 26 + spreadOffset, y - 4 - flapOffset);
    c.stroke();
    // Limb Animation
    // We can use the wingPhase for walking as well, but scale it differently
    const walkSwing1 = Math.sin(wingPhase) * 4; // Right leg/arm
    const walkSwing2 = Math.cos(wingPhase) * 4; // Left leg/arm
    
    // Legs (thick)
    // Draw far leg first (darker)
    c.fillStyle = isLocal ? '#0284c7' : '#334155';
    c.fillRect(x + 22 + walkSwing2, y + 28, 6, 8);
    // Draw near leg
    c.fillStyle = color;
    c.fillRect(x + 12 + walkSwing1, y + 28, 6, 8);
    
    // Small arms
    // Draw far arm
    c.fillStyle = isLocal ? '#0284c7' : '#334155';
    c.fillRect(x + 20 + walkSwing2, y + 18, 4, 4);
    // Draw near arm
    c.fillStyle = color;
    c.fillRect(x + 16 + walkSwing1, y + 18, 4, 4);
    
    // Eye (more menacing)
    c.fillStyle = '#ffffff';
    c.fillRect(x + 32, y + 8, 3, 3);
    c.fillStyle = '#000000';
    c.fillRect(x + 33, y + 9, 1, 1);
    
    // Nostril
    c.fillStyle = '#000000';
    c.fillRect(x + 38, y + 10, 1, 1);
    
    // Teeth (small triangles)
    c.fillStyle = '#ffffff';
    c.beginPath();
    c.moveTo(x + 36, y + 12);
    c.lineTo(x + 37, y + 14);
    c.lineTo(x + 35, y + 14);
    c.closePath();
    c.fill();
    
    c.beginPath();
    c.moveTo(x + 38, y + 12);
    c.lineTo(x + 39, y + 14);
    c.lineTo(x + 37, y + 14);
    c.closePath();
    c.fill();
    
    c.restore(); // Restore scaling before drawing the name tag
    
    // Name tag
    c.fillStyle = color;
    c.font = '10px "Orbitron"';
    c.textAlign = 'center';
    c.fillText(name + (isLocal ? ' (YOU)' : ''), x + DINO_WIDTH/2, y - 10);
}

function drawGameOver() {
    const c = ctx.value;
    c.fillStyle = 'rgba(15, 23, 42, 0.85)';
    c.fillRect(0, 0, canvasWidth.value, canvasHeight.value);
    
    const isMobileView = isMobile.value;
    const titleSize = isMobileView ? '20px' : '30px';
    const nameSize = isMobileView ? '16px' : '20px';
    const scoreSize = isMobileView ? '12px' : '14px';
    const textSize = isMobileView ? '10px' : '12px';
    
    if (gameEnded.value && champion.value) {
        // Show champion
        c.fillStyle = '#10b981';
        c.font = `${titleSize} "Press Start 2P"`;
        c.textAlign = 'center';
        c.fillText('CHAMPION!', canvasWidth.value/2, canvasHeight.value/2 - 50);
        
        c.fillStyle = '#fbbf24';
        c.font = `${nameSize} "Press Start 2P"`;
        c.fillText(champion.value.name, canvasWidth.value/2, canvasHeight.value/2 - 10);
        
        c.fillStyle = '#f8fafc';
        c.font = `${scoreSize} "Press Start 2P"`;
        c.fillText(`SCORE: ${champion.value.score}`, canvasWidth.value/2, canvasHeight.value/2 + 30);
        
        c.font = `${textSize} "Orbitron"`;
        c.fillStyle = '#94a3b8';
        c.fillText('SPACE TO PLAY AGAIN • ESC TO LOBBY', canvasWidth.value/2, canvasHeight.value/2 + 70);
    } else if (localPlayer.isDead && !gameEnded.value) {
        // Waiting for other players
        c.fillStyle = '#f59e0b';
        c.font = `${isMobileView ? '18px' : '24px'} "Press Start 2P"`;
        c.textAlign = 'center';
        c.fillText('ELIMINATED!', canvasWidth.value/2, canvasHeight.value/2 - 30);
        
        c.fillStyle = '#f8fafc';
        c.font = `${scoreSize} "Press Start 2P"`;
        c.fillText(`FINAL SCORE: ${Math.floor(gameState.score)}`, canvasWidth.value/2, canvasHeight.value/2 + 10);
        
        c.font = `${textSize} "Orbitron"`;
        c.fillStyle = '#94a3b8';
        c.fillText('WAITING FOR OTHER PLAYERS...', canvasWidth.value/2, canvasHeight.value/2 + 50);
    } else {
        // Regular game over for single player
        c.fillStyle = '#f43f5e';
        c.font = `${titleSize} "Press Start 2P"`;
        c.textAlign = 'center';
        c.fillText('GAME OVER', canvasWidth.value/2, canvasHeight.value/2 - 20);
        
        c.fillStyle = '#f8fafc';
        c.font = `${scoreSize} "Press Start 2P"`;
        c.fillText(`FINAL SCORE: ${Math.floor(gameState.score)}`, canvasWidth.value/2, canvasHeight.value/2 + 30);
        
        c.font = `${textSize} "Orbitron"`;
        c.fillStyle = '#94a3b8';
        c.fillText('SPACE TO RESTART • ESC TO LOBBY', canvasWidth.value/2, canvasHeight.value/2 + 70);
    }
}

function drawRestartConfirmation() {
    const c = ctx.value;
    const isMobileView = isMobile.value;
    
    // Dim background further
    c.fillStyle = 'rgba(0, 0, 0, 0.7)';
    c.fillRect(0, 0, canvasWidth.value, canvasHeight.value);
    
    // Warning Box
    const boxWidth = isMobileView ? 240 : 400;
    const boxHeight = isMobileView ? 100 : 150;
    const x = (canvasWidth.value - boxWidth) / 2;
    const y = (canvasHeight.value - boxHeight) / 2;
    
    c.fillStyle = '#1e293b';
    c.strokeStyle = '#f59e0b';
    c.lineWidth = 2;
    if (c.roundRect) {
        c.beginPath();
        c.roundRect(x, y, boxWidth, boxHeight, 10);
        c.fill();
        c.stroke();
    } else {
        c.fillRect(x, y, boxWidth, boxHeight);
        c.strokeRect(x, y, boxWidth, boxHeight);
    }
    
    // Icon (Warning Triangle)
    c.fillStyle = '#f59e0b';
    c.beginPath();
    c.moveTo(canvasWidth.value / 2, y + 20);
    c.lineTo(canvasWidth.value / 2 - 20, y + 55);
    c.lineTo(canvasWidth.value / 2 + 20, y + 55);
    c.closePath();
    c.fill();
    
    c.fillStyle = '#1e293b';
    c.font = 'bold 20px Arial';
    c.fillText('!', canvasWidth.value / 2, y + 50);
    
    // Text
    c.fillStyle = '#f8fafc';
    c.font = `${isMobileView ? '10px' : '14px'} "Press Start 2P"`;
    c.textAlign = 'center';
    c.fillText('RESTART GAME?', canvasWidth.value / 2, y + 85);
    
    c.fillStyle = '#94a3b8';
    c.font = `${isMobileView ? '8px' : '11px'} "Orbitron"`;
    c.fillText(isMobileView ? 'TAP AGAIN TO CONFIRM' : 'PRESS SPACE TO CONFIRM • ESC TO CANCEL', canvasWidth.value / 2, y + 115);
}

</script>

<template>
    <div class="game-container">
        <div class="player-list">
            <h3>PLAYERS</h3>
            <div v-for="(p, id) in remotePlayers" :key="id" class="player-item" :class="{ dead: p.isDead }">
                <div class="player-info">
                    <span class="status-dot"></span>
                    <span class="name">{{ p.name }}</span>
                </div>
                <div class="high-score" v-if="playerHighScores[id]">HI: {{ playerHighScores[id].toLocaleString() }}</div>
            </div>
            <div class="player-item self">
                <div class="player-info">
                    <span class="status-dot"></span>
                    <span class="name">{{ playerName }} (YOU)</span>
                </div>
                <div class="high-score" v-if="playerHighScores[playerId]">HI: {{ playerHighScores[playerId].toLocaleString() }}</div>
            </div>

            <div class="scoreboard-wrapper">
                <Scoreboard :key="scoreboardKey" />
            </div>
        </div>


        
        <div class="canvas-wrapper">
            <canvas 
                ref="canvasRef" 
                :width="canvasWidth" 
                :height="canvasHeight"
            ></canvas>
            <div class="instructions">
                <span v-if="!isMobile">SPACEBAR TO JUMP • ESC TO QUIT</span>
                <span v-else>TAP TO JUMP • BACK TO QUIT</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.game-container {
    display: flex;
    gap: 2rem;
    align-items: flex-start;
    width: 100%;
    flex-direction: column;
}

@media (min-width: 769px) {
    .game-container {
        flex-direction: row;
    }
}

.player-list {
    width: 100%;
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    padding: 1rem;
}

@media (min-width: 769px) {
    .player-list {
        width: 180px;
    }
}

.player-list h3 {
    font-size: 0.7rem;
    color: #475569;
    margin-bottom: 1rem;
    letter-spacing: 2px;
}

.player-item {
    font-size: 0.8rem;
    padding: 0.6rem 0;
    color: #cbd5e1;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.player-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.high-score {
    font-size: 0.6rem;
    font-family: 'Press Start 2P', cursive;
    color: #fbbf24;
    margin-left: 1.1rem;
}


.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 5px #22c55e;
}

.player-item.dead {
    color: #475569;
    text-decoration: line-through;
}

.player-item.dead .status-dot {
    background: #ef4444;
    box-shadow: none;
}

.player-item.self {
    color: #38bdf8;
    font-weight: 700;
}

.canvas-wrapper {
    position: relative;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    border: 2px solid #1e293b;
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
}

@media (min-width: 769px) {
    .canvas-wrapper {
        max-width: 900px;
    }
}

canvas {
    display: block;
    image-rendering: pixelated;
    width: 100%;
    height: auto;
    max-width: 100%;
}

.instructions {
    position: absolute;
    bottom: 1rem;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 0.6rem;
    color: #475569;
    letter-spacing: 1px;
    padding: 0 1rem;
}

@media (max-width: 768px) {
    .instructions {
        font-size: 0.5rem;
        bottom: 0.5rem;
    }
}

.scoreboard-wrapper {
    margin-top: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 1rem;
}

:deep(.scoreboard) {
    width: 100% !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
}
</style>

