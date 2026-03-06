<script setup>
import { ref, onMounted, onUnmounted, reactive, watch } from 'vue';
import axios from 'axios';
import Scoreboard from './Scoreboard.vue';

const props = defineProps(['playerId', 'playerName', 'obstacleSeed']);
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

const SNAKE_COLORS = [
    { main: '#4ade80', belly: '#22c55e', eye: '#111827', pattern: '#166534', tongue: '#ef4444' }, // Bright Green
    { main: '#ca8a04', belly: '#facc15', eye: '#111827', pattern: '#854d0e', tongue: '#ef4444' }, // Brown/Yellow
    { main: '#57534e', belly: '#a8a29e', eye: '#f59e0b', pattern: '#1c1917', tongue: '#ef4444' }, // Gray/Black
    { main: '#d97706', belly: '#fbbf24', eye: '#111827', pattern: '#9a3412', tongue: '#ef4444' }, // Orange
    { main: '#65a30d', belly: '#a3e635', eye: '#111827', pattern: '#3f6212', tongue: '#ef4444' }, // Olive
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
    gameTime: 0,
});

const localPlayer = reactive({
    y: groundY.value - DINO_HEIGHT,
    vy: 0,
    onGround: true,
    isDead: false,
    wingPhase: 0,
});

const obstacles = ref([]);
const particles = ref([]);
const gameEnded = ref(false);
const scoreboardKey = ref(0);
const showRestartConfirm = ref(false);
const playerHighScore = ref(0);



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


onMounted(() => {
    ctx.value = canvasRef.value.getContext('2d');
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('resize', handleResize);
    window.addEventListener('touchstart', handleTouchStart);
    window.addEventListener('touchmove', handleTouchMove, { passive: false });
    requestAnimationFrame(gameLoop);
    
    fetchHighScore();
    
    // Start game automatically after a small delay
    setTimeout(() => {
        gameState.isRunning = true;
    }, 500);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('touchstart', handleTouchStart);
    window.removeEventListener('touchmove', handleTouchMove);
});

async function fetchHighScore() {
    try {
        const response = await axios.post('/api/personal-high-scores', { player_ids: [props.playerId] });
        playerHighScore.value = response.data[props.playerId] || 0;
    } catch (e) {
        console.error("Failed to fetch high score", e);
    }
}


function handleKeyDown(e) {
    console.log('Key pressed:', e.code, 'gameEnded:', gameEnded.value, 'localPlayer.isDead:', localPlayer.isDead, 'localPlayer.onGround:', localPlayer.onGround);
    
    if (e.code === 'Space') {
        e.preventDefault();
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
    } else if (e.code === 'ArrowDown' || e.code === 'KeyS') {
        e.preventDefault();
        if (!localPlayer.onGround && !localPlayer.isDead) {
            console.log('Fast falling');
            fastFall();
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
    gameState.gameTime = 0;

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
    
    // Refresh scoreboard
    scoreboardKey.value++;
    
    console.log('=== RESTART GAME COMPLETED ===');

}

function jump() {
    localPlayer.vy = JUMP_FORCE;
    localPlayer.onGround = false;
}

function fastFall() {
    localPlayer.vy = 1200;
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

let touchStartY = 0;

function handleTouchStart(e) {
    if (e.touches && e.touches.length > 0) {
        touchStartY = e.touches[0].clientY;
    }

    if (gameEnded.value) {
        // Game has ended, allow restart with confirmation
        if (!showRestartConfirm.value) {
            showRestartConfirm.value = true;
        } else {
            restartGame();
        }
    } else if (localPlayer.onGround && !localPlayer.isDead) {
        jump();
        // Prevent default only when jumping to avoid blocking normal scrolling if needed elsewhere, 
        // though standard is to prevent default on the game canvas.
        e.preventDefault(); 
    } else if (localPlayer.isDead) {
        console.log('Waiting for all players to finish...');
    }
}

function handleTouchMove(e) {
    if (localPlayer.onGround || localPlayer.isDead || gameEnded.value) return;
    
    if (e.touches && e.touches.length > 0) {
        const touchEndY = e.touches[0].clientY;
        const swipeDistance = touchEndY - touchStartY;
        
        // If swiped down more than 30 pixels
        if (swipeDistance > 30) {
            e.preventDefault(); // Prevent scrolling while fast falling
            fastFall();
            // Reset touchStartY so it doesn't trigger multiple times in one swipe
            touchStartY = touchEndY; 
        }
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
    gameState.gameTime += dt;

    // Spawn obstacles based on seeded random
    if (gameState.gameTime > 3) {
        const lastObstacle = obstacles.value[obstacles.value.length - 1];
        
        // Increase distance slightly if score/speed threshold is reached
        let minGap = 400;
        let maxGapRange = 600;
        if (gameState.score >= 2000 || gameState.speed >= 2000) {
            minGap = 500;
            maxGapRange = 750;
        }

        if (!lastObstacle || (canvasWidth.value - lastObstacle.x > minGap + nextRandom() * maxGapRange)) {
            spawnObstacle();
        }
    }

    obstacles.value.forEach((obs, index) => {
        obs.x -= gameState.speed * dt;
        
        // Animate bird wings
        if (obs.type === 'bird') {
            obs.wingPhase = (obs.wingPhase + dt * 10) % (Math.PI * 2);
        } else if (obs.type === 'snake') {
            obs.snakePhase = (obs.snakePhase + dt * 4) % (Math.PI * 2); // Slow wave-like animation
        }
        
        // Collision detection for local player
        if (checkCollision(localPlayer, obs, canvasWidth.value/4)) {
            console.log("Collision detected with obstacle:", obs);
            die();
        }
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
    
    if (typeRand > 0.8 && gameState.score >= 500) {
        type = 'bird';
    } else if (typeRand > 0.6 && gameState.score >= 200) {
        type = 'snake';
    } else if (typeRand > 0.3) {
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
            width: 38,
            height: 30,
            y: birdHeight,
            type: 'bird',
            wingPhase: 0,
            colors: BIRD_COLORS[colorIdx]
        });


    } else if (type === 'snake') {
        const c1 = Math.floor(nextRandom() * SNAKE_COLORS.length);
        const c2 = Math.floor(nextRandom() * SNAKE_COLORS.length);
        const c3 = Math.floor(nextRandom() * SNAKE_COLORS.length);
        obstacles.value.push({
            x: canvasWidth.value,
            width: 30, // Total width of 3 snakes standing upright
            height: 50,
            y: groundY.value - 50, // On the ground
            type: 'snake',
            snakePhase: 0,
            colors: [SNAKE_COLORS[c1], SNAKE_COLORS[c2], SNAKE_COLORS[c3]]
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

function rectIntersect(r1, r2) {
    return (
        r1.x < r2.x + r2.w &&
        r1.x + r1.w > r2.x &&
        r1.y < r2.y + r2.h &&
        r1.y + r1.h > r2.y
    );
}

function checkCollision(player, obs, playerX = canvasWidth.value/4) {
    // 1. Define Player Hitboxes (Head and Body)
    // Internal shifts: ox=12, oy=14. Scaling: scaleX=DINO_WIDTH/54, scaleY=DINO_HEIGHT/50
    const ox = 12;
    const oy = 14;
    const sx = DINO_WIDTH / 54;
    const sy = DINO_HEIGHT / 50;

    const playerHitboxes = [
        // Main Body: x + 8 + ox, y + 12 + oy, width 24, height 20
        { 
            x: playerX + (8 + ox) * sx, 
            y: player.y + (12 + oy) * sy, 
            w: 24 * sx, 
            h: 20 * sy 
        },
        // Head: modified to match new neck extended position
        { 
            x: playerX + (33 + ox) * sx, 
            y: player.y + (-5 + oy) * sy, 
            w: 24 * sx, 
            h: 14 * sy 
        }
    ];

    // 2. Define Obstacle Hitboxes
    let obsHitboxes = [];
    if (obs.type === 'bird') {
        const scale = 38 / 27;
        // Bird body: x + 0, y + 8, w 15, h 10
        // Bird head: x + 12, y + 6, w 8, h 8
        // Beak: x + 18, y + 8, w 4, h 2
        obsHitboxes = [
            { 
                x: obs.x + 0 * scale, 
                y: obs.y + 6 * scale, // Combined height for body/head
                w: 22 * scale, 
                h: 12 * scale 
            }
        ];
        
        // Add wing hitbox (dynamic or simplified)
        const wingOffset = Math.sin(obs.wingPhase) * 8 * scale;
        if (wingOffset > 0) {
            // Lower wing extended
            obsHitboxes.push({
                x: obs.x - 2 * scale,
                y: obs.y + 12 * scale,
                w: 5 * scale,
                h: wingOffset
            });
        } else {
            // Upper wing extended
            obsHitboxes.push({
                x: obs.x - 2 * scale,
                y: obs.y + 10 * scale + wingOffset,
                w: 5 * scale,
                h: Math.abs(wingOffset)
            });
        }
    } else {
        // Ground cacti - single box is fine as they are rectangular
        obsHitboxes = [{ x: obs.x, y: obs.y, w: obs.width, h: obs.height }];
    }

    // 3. Check for intersection between any player hitbox and any obstacle hitbox
    for (const ph of playerHitboxes) {
        for (const oh of obsHitboxes) {
            if (rectIntersect(ph, oh)) return true;
        }
    }

    return false;
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

    // Save score to DB
    axios.post('/api/scores', {
        player_name: props.playerName,
        player_id: props.playerId,
        score: Math.floor(gameState.score)
    });
    
    gameEnded.value = true;
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
        } else if (obs.type === 'snake') {
            // Draw 3 slithering snakes
            drawSnake(obs.x, obs.y, obs.snakePhase, obs.colors);
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

    // Calculate constant slow tail phase based on absolute time
    const tailPhase = (gameState.lastTime / 1000) * 8; // Decreased constant speed for tail animation

    // Local Player - Neon Blue Gradient Base
    drawDino(canvasWidth.value/4, localPlayer.y, localPlayer.isDead ? '#64748b' : '#0ea5e9', props.playerName, true, localPlayer.wingPhase, localPlayer.onGround, tailPhase);
    
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
    
    if (gameEnded.value) {
        drawGameOver();
    }

    if (showRestartConfirm.value) {
        drawRestartConfirmation();
    }
}


function drawBird(x, y, wingPhase, colors = BIRD_COLORS[0]) {
    const c = ctx.value;
    
    c.save();
    
    // Scale bird to fit precisely in 38x30 bounding box
    // Base visual width is 27 (from tail 0 to beak 27)
    // Scale = 38 / 27 ≈ 1.4
    const scale = 38 / 27;
    c.translate(x, y);
    c.scale(scale, scale);
    c.translate(-x, -y);
    
    // Bird body (shifted left by 10)
    c.fillStyle = colors.main;
    c.fillRect(x + 0, y + 8, 15, 10);
    
    // Bird head (shifted left by 10)
    c.fillRect(x + 12, y + 6, 8, 8);
    
    // Beak (shifted left by 10)
    c.fillStyle = colors.beak;
    c.fillRect(x + 18, y + 8, 4, 2);
    
    // Eye (shifted left by 10)
    c.fillStyle = '#000000';
    c.fillRect(x + 14, y + 7, 1, 1);
    
    // Animated wings
    const wingOffset = Math.sin(wingPhase) * 8;
    c.fillStyle = colors.wing;

    // Upper wing (shifted left by 10)
    c.beginPath();
    c.moveTo(x + 2, y + 10);
    c.lineTo(x - 5, y + 5 - wingOffset);
    c.lineTo(x - 2, y + 12);
    c.closePath();
    c.fill();
    
    // Lower wing (shifted left by 10)
    c.beginPath();
    c.moveTo(x + 2, y + 12);
    c.lineTo(x - 5, y + 17 + wingOffset);
    c.lineTo(x - 2, y + 14);
    c.closePath();
    c.fill();
    
    // Tail (shifted left by 10, relative to tail-start at x)
    c.fillStyle = colors.wing; 
    c.beginPath();
    c.moveTo(x + 0, y + 13);
    c.lineTo(x - 5, y + 11);
    c.lineTo(x - 5, y + 15);
    c.closePath();
    c.fill();
    
    c.restore();
}

function drawSnake(x, y, snakePhase, colors) {
    const c = ctx.value;
    c.save();
    
    const segments = 12;
    const segmentHeight = 4;

    // Draw 3 snakes (from back to front for z-ordering)
    for (let s = 2; s >= 0; s--) {
        const snakeColor = colors[s];
        
        // Offset snakes horizontally
        const sx = x + s * 8 + 8; // x + 8, x + 16, x + 24
        const sy = y + 2; // slightly below bounding box top
        
        // Slightly different slither phase offsets
        const phase = snakePhase + s * 1.5;
        
        c.fillStyle = snakeColor.main;
        c.beginPath();
        
        // Left edge of snake (going from top to bottom)
        for (let i = 0; i < segments; i++) {
            const waveX = Math.sin(phase - i * 0.6) * 4;
            const px = sx + waveX;
            const py = sy + i * segmentHeight;
            
            if (i === 0) {
                c.moveTo(px - 2, py); // Snout left
            } else {
                c.lineTo(px - 3, py);
            }
        }
        
        // Tail (bottom)
        const lastWaveX = Math.sin(phase - (segments - 1) * 0.6) * 4;
        c.lineTo(sx + lastWaveX, sy + segments * segmentHeight + 2);

        // Right edge (going from bottom to top)
        for (let i = segments - 1; i >= 0; i--) {
            const waveX = Math.sin(phase - i * 0.6) * 4;
            const px = sx + waveX;
            const py = sy + i * segmentHeight;
            
            if (i === 0) {
                c.lineTo(px + 2, py); // Snout right
            } else {
                c.lineTo(px + 3, py);
            }
        }
        
        c.closePath();
        c.fill();
        
        // Belly pattern (right part of the snake)
        c.fillStyle = snakeColor.belly || snakeColor.main;
        c.beginPath();
        for (let i = 0; i < segments; i++) {
            const waveX = Math.sin(phase - i * 0.6) * 4;
            const px = sx + waveX;
            const py = sy + i * segmentHeight;
            if (i === 0) c.moveTo(px + 1, py);
            else c.lineTo(px + 1, py);
        }
        for (let i = segments - 1; i >= 0; i--) {
            const waveX = Math.sin(phase - i * 0.6) * 4;
            const px = sx + waveX;
            const py = sy + i * segmentHeight;
            c.lineTo(px + 3, py);
        }
        c.closePath();
        c.fill();
        
        // Add pattern/scale spots
        c.fillStyle = snakeColor.pattern || '#000000';
        for (let i = 2; i < segments - 2; i += 2) {
            const waveX = Math.sin(phase - i * 0.6) * 4;
            c.fillRect(sx + waveX - 2, sy + i * segmentHeight, 2, 2);
        }

        // Draw Eye
        const headWaveX = Math.sin(phase) * 4;
        c.fillStyle = snakeColor.eye || '#000000';
        c.fillRect(sx + headWaveX - 1, sy + 2, 2, 2);
        
        // Draw Tongue (flicks up and slightly out)
        if (Math.sin(phase * 4) > 0) {
            c.fillStyle = snakeColor.tongue || '#ef4444';
            c.beginPath();
            c.moveTo(sx + headWaveX, sy - 1);
            c.lineTo(sx + headWaveX - 3, sy - 4);
            c.lineTo(sx + headWaveX - 2, sy - 5); 
            c.lineTo(sx + headWaveX, sy - 3);
            c.lineTo(sx + headWaveX + 2, sy - 5);
            c.lineTo(sx + headWaveX + 3, sy - 4);
            c.lineTo(sx + headWaveX, sy - 1);
            c.fill();
        }
    }
    
    c.restore();
}


function drawDino(x, y, color, name, isLocal, wingPhase = 0, onGround = true, tailPhase = 0) {
    const c = ctx.value;
    
    c.save();
    
    // Total visual width: 54 units (from tail tip -12 to snout tip 42)
    // Total visual height: 50 units (from wing tip -14 to leg bottom 36)
    // We scale to fill the DINO_WIDTH x DINO_HEIGHT box (66x66)
    const scaleX = DINO_WIDTH / 54;
    const scaleY = DINO_HEIGHT / 50;
    
    c.translate(x, y);
    c.scale(scaleX, scaleY);
    c.translate(-x, -y);
    
    // Apply internal shift (+12, +14) to all components to make the tail start at 0
    const ox = 12;
    const oy = 14;
    
    // Dragon body gradient for depth
    const bodyGradient = c.createLinearGradient(x + ox, y + oy, x + ox + 36, y + oy + 20);
    bodyGradient.addColorStop(0, color);
    bodyGradient.addColorStop(1, isLocal ? '#075985' : '#1e293b'); // Darker depth
    c.fillStyle = bodyGradient;

    // Main body
    c.beginPath();
    if (c.roundRect) {
        c.roundRect(x + 8 + ox, y + 12 + oy, 24, 20, 3);
    } else {
        c.rect(x + 8 + ox, y + 12 + oy, 24, 20);
    }
    c.fill();
    
    // Neck
    c.fillStyle = color;
    c.beginPath();
    c.moveTo(x + 30 + ox, y + 14 + oy); // Top front of body
    c.lineTo(x + 22 + ox, y + 14 + oy); // Top middle of body
    c.lineTo(x + 33 + ox, y + 5 + oy); // Back of neck base
    c.lineTo(x + 37 + ox, y + 9 + oy); // Front of neck base
    c.closePath();
    c.fill();

    // Realistic Head
    c.fillStyle = color;
    c.beginPath();
    c.moveTo(x + 33 + ox, y + 5 + oy); // Back of neck
    c.lineTo(x + 33 + ox, y - 5 + oy); // Back of head
    c.quadraticCurveTo(x + 41 + ox, y - 11 + oy, x + 49 + ox, y - 5 + oy); // Top of head
    c.quadraticCurveTo(x + 55 + ox, y - 3 + oy, x + 57 + oy, y + 1 + oy); // Snout top
    c.quadraticCurveTo(x + 57 + oy, y + 5 + oy, x + 55 + ox, y + 6 + oy); // Snout front
    c.quadraticCurveTo(x + 47 + ox, y + 9 + oy, x + 39 + ox, y + 9 + oy); // Jaw lower
    c.quadraticCurveTo(x + 35 + ox, y + 5 + oy, x + 33 + ox, y + 5 + oy); // Neck front
    c.fill();
    
    // Brow ridge
    c.strokeStyle = isLocal ? '#075985' : '#1e293b';
    c.lineWidth = 1.5;
    c.beginPath();
    c.moveTo(x + 43 + ox, y - 3 + oy);
    c.quadraticCurveTo(x + 47 + ox, y - 5 + oy, x + 51 + ox, y - 2 + oy);
    c.stroke();

    // Animated Hair Strands
    c.lineWidth = 1.5;
    for (let i = 0; i < 2; i++) {
        c.strokeStyle = i === 0 ? '#fbbf24' : '#f59e0b';
        c.beginPath();
        let hx = x + 34 + ox;
        let hy = y - 5 + oy + i * 2;
        c.moveTo(hx, hy);
        
        let hairPhase = tailPhase * 0.8 + i;
        let cp1x = hx - 12;
        let cp1y = hy - 4 + Math.sin(hairPhase) * 4;
        let cp2x = hx - 24;
        let cp2y = hy - 4 + Math.cos(hairPhase) * 5;
        let endx = hx - 36;
        let endy = hy - 2 + Math.sin(hairPhase * 1.2) * 6;
        
        c.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, endx, endy);
        c.stroke();
    }


    // Enhanced Procedural Dragon Tail
    const tailSegments = 8;
    const points = [];
    let currentX = x + 8 + ox;
    let currentY = y + 20 + oy; // Centered on body back

    for (let i = 0; i < tailSegments; i++) {
        // Sine wave offset for the "swing"
        const wave = Math.sin(tailPhase - i * 0.5) * (2 + i * 1.5);
        currentX -= 4.5; // Move left
        const targetY = (y + 20 + oy) + wave;
        
        points.push({
            x: currentX,
            y: targetY,
            width: 12 - (i * 1.4) // Tapering
        });
    }

    // Draw Tail Shape
    c.beginPath();
    c.moveTo(x + 8 + ox, y + 12 + oy); // Top-back of body
    
    // Top curve of the tail
    points.forEach(p => {
        c.lineTo(p.x, p.y - p.width / 2);
    });

    // Tip of the tail
    const tip = points[tailSegments - 1];
    c.lineTo(tip.x - 6, tip.y);

    // Bottom curve of the tail
    for (let i = tailSegments - 1; i >= 0; i--) {
        const p = points[i];
        c.lineTo(p.x, p.y + p.width / 2);
    }

    c.lineTo(x + 8 + ox, y + 28 + oy); // Bottom-back of body
    c.closePath();
    c.fill();

    // Add Decorative Spikes/Scales along the top
    c.fillStyle = isLocal ? '#075985' : '#1e293b'; // Use depth color
    points.forEach((p, i) => {
        if (i < tailSegments - 1) {
            c.beginPath();
            c.moveTo(p.x, p.y - p.width / 2);
            c.lineTo(p.x - 2, p.y - p.width / 2 - 4); // Spike tip
            c.lineTo(p.x - 4, p.y - p.width / 2);
            c.fill();
        }
    });

    // Wing Animation
    const flapOffset = Math.sin(wingPhase) * 12; // vertical flap
    const spreadOffset = Math.cos(wingPhase) * 4; // horizontal spread

    // Dragon Wings (Enhanced "Ibon Adarna" Style)
    // Define Adarna Gradient for wings
    const adarnaGradient = c.createLinearGradient(x + ox - 10, y + oy - 15, x + ox + 30, y + oy + 15);
    adarnaGradient.addColorStop(0, '#ffd700'); // Gold
    adarnaGradient.addColorStop(0.2, '#ff4500'); // OrangeRed
    adarnaGradient.addColorStop(0.4, '#dc143c'); // Crimson
    adarnaGradient.addColorStop(0.6, '#0000ff'); // Blue
    adarnaGradient.addColorStop(0.8, '#008000'); // Green
    adarnaGradient.addColorStop(1, '#8b00ff'); // Violet

    // Draw the far wing (darker overlay)
    c.fillStyle = adarnaGradient;
    c.save();
    c.globalAlpha = 0.7; // Make the far wing slightly more transparent/darker
    c.beginPath();
    c.moveTo(x + 16 + ox, y + 14 + oy); // base
    c.lineTo(x + 8 + ox, y + 2 + oy - flapOffset/2); // joint 1
    c.lineTo(x + ox - spreadOffset, y + oy - 10 - flapOffset); // tip
    c.lineTo(x + 12 + ox, y + oy - 6 - flapOffset*0.8); // middle outer
    c.lineTo(x + 20 + ox + spreadOffset, y + oy - 14 - flapOffset*1.2); // second tip
    c.lineTo(x + 24 + ox, y + oy - 2 - flapOffset/2); // lower inner
    c.lineTo(x + 32 + ox + spreadOffset, y + oy - 6 - flapOffset); // third tip
    c.lineTo(x + 26 + ox, y + 12 + oy); // back to body
    c.closePath();
    c.fill();
    c.restore();
    
    // Draw the near wing (vibrant Adarna colors)
    c.fillStyle = adarnaGradient;
    c.beginPath();
    c.moveTo(x + 12 + ox, y + 16 + oy); // base
    c.lineTo(x + 2 + ox, y + 4 + oy - flapOffset/2); // joint 1
    c.lineTo(x + ox - 8 - spreadOffset, y + oy - 8 - flapOffset); // tip
    c.lineTo(x + 6 + ox, y + oy - 4 - flapOffset*0.8); // middle outer
    c.lineTo(x + 14 + ox + spreadOffset/2, y + oy - 12 - flapOffset*1.2); // second tip
    c.lineTo(x + 18 + ox, y + oy - flapOffset/2); // lower inner
    c.lineTo(x + 26 + ox + spreadOffset, y + oy - 4 - flapOffset); // third tip
    c.lineTo(x + 20 + ox, y + 14 + oy); // back to body
    c.closePath();
    c.fill();
    
    // Add wing bones/veins for detail
    c.strokeStyle = '#0f172a';
    c.lineWidth = 1;
    // Main bone
    c.beginPath();
    c.moveTo(x + 12 + ox, y + 16 + oy);
    c.lineTo(x + 2 + ox, y + 4 + oy - flapOffset/2);
    c.lineTo(x + ox - 8 - spreadOffset, y + oy - 8 - flapOffset);
    c.stroke();
    // Inner bones
    c.beginPath();
    c.moveTo(x + 2 + ox, y + 4 + oy - flapOffset/2);
    c.lineTo(x + 14 + ox + spreadOffset/2, y + oy - 12 - flapOffset*1.2);
    c.stroke();
    c.beginPath();
    c.moveTo(x + 2 + ox, y + 4 + oy - flapOffset/2);
    c.lineTo(x + 26 + ox + spreadOffset, y + oy - 4 - flapOffset);
    c.stroke();
    // Limb Animation
    // We can use the wingPhase for walking as well, but scale it differently
    const walkSwing1 = onGround ? Math.sin(wingPhase) * 4 : 0; // Right leg/arm
    const walkSwing2 = onGround ? Math.cos(wingPhase) * 4 : 0; // Left leg/arm
    
    // Enhanced Dragon Legs
    const drawLeg = (lx, ly, isFar, swing) => {
        const legColor = isFar ? (isLocal ? '#0284c7' : '#334155') : color;
        c.fillStyle = legColor;
        
        // Thigh
        c.fillRect(lx + swing/2, ly, 8, 5);
        
        // Shin (angled more by swing)
        c.fillRect(lx + swing, ly + 5, 6, 6);
        
        // Foot
        c.fillRect(lx + swing - 2, ly + 10, 10, 3);
        
        // Claws (small white details)
        c.fillStyle = '#ffffff';
        c.fillRect(lx + swing + 4, ly + 11, 2, 1);
        c.fillRect(lx + swing + 7, ly + 11, 2, 1);
    };

    // Draw far leg first
    drawLeg(x + 22 + ox, y + 26 + oy, true, walkSwing2);
    // Draw near leg
    drawLeg(x + 12 + ox, y + 26 + oy, false, walkSwing1);

    
    // Small arms
    // Draw far arm
    c.fillStyle = isLocal ? '#0284c7' : '#334155';
    c.fillRect(x + 20 + ox + walkSwing2, y + 18 + oy, 4, 4);
    // Draw near arm
    c.fillStyle = color;
    c.fillRect(x + 16 + ox + walkSwing1, y + 18 + oy, 4, 4);
    
    // Realistic Eye
    c.fillStyle = '#ffffff';
    c.beginPath();
    c.ellipse(x + 47 + ox, y - 0.5 + oy, 2.5, 1.5, Math.PI / 8, 0, Math.PI * 2);
    c.fill();
    
    c.fillStyle = '#000000'; // Pupil
    c.beginPath();
    c.arc(x + 47.5 + ox, y - 0.5 + oy, 1, 0, Math.PI * 2);
    c.fill();

    // Eye highlight
    c.fillStyle = '#ffffff';
    c.fillRect(x + 47.5 + ox, y - 1 + oy, 0.5, 0.5);

    // Nostril
    c.fillStyle = '#0f172a';
    c.beginPath();
    c.ellipse(x + 54 + ox, y + 1 + oy, 1.5, 0.8, -Math.PI / 6, 0, Math.PI * 2);
    c.fill();

    // Jaw line separation for realism
    c.strokeStyle = isLocal ? '#075985' : '#1e293b';
    c.lineWidth = 1;
    c.beginPath();
    c.moveTo(x + 57 + ox, y + 4 + oy);
    c.lineTo(x + 46 + ox, y + 6 + oy);
    c.stroke();
    
    // Realistic Sharp Teeth
    c.fillStyle = '#ffffff';
    c.beginPath();
    c.moveTo(x + 49 + ox, y + 5 + oy);
    c.lineTo(x + 50 + ox, y + 7 + oy);
    c.lineTo(x + 51 + ox, y + 5 + oy);
    c.fill();

    c.beginPath();
    c.moveTo(x + 52 + ox, y + 4.5 + oy);
    c.lineTo(x + 53 + ox, y + 6.5 + oy);
    c.lineTo(x + 54 + ox, y + 4.5 + oy);
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
    const scoreSize = isMobileView ? '12px' : '14px';
    const textSize = isMobileView ? '10px' : '12px';

    // Regular game over for single player
    c.fillStyle = '#f43f5e';
    c.font = `${titleSize} "Press Start 2P"`;
    c.textAlign = 'center';
    c.fillText('GAME OVER', canvasWidth.value/2, canvasHeight.value/2 - 20);
    
    c.fillStyle = '#f8fafc';
    c.font = `${scoreSize} "Press Start 2P"`;
    c.fillText(`FINAL SCORE: ${Math.floor(gameState.score)}`, canvasWidth.value/2, canvasHeight.value/2 + 30);
    
    c.font = `${textSize} "Orbitron"`;
    c.fillStyle = '#cbd5e1';
    c.fillText('SPACE TO RESTART • ESC TO LOBBY', canvasWidth.value/2, canvasHeight.value/2 + 70);
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
    
    c.fillStyle = '#cbd5e1';
    c.font = `${isMobileView ? '8px' : '11px'} "Orbitron"`;
    c.fillText(isMobileView ? 'TAP AGAIN TO CONFIRM' : 'PRESS SPACE TO CONFIRM • ESC TO CANCEL', canvasWidth.value / 2, y + 115);
}

</script>

<template>
    <div class="game-container">
        <div class="canvas-wrapper">
            <canvas 
                ref="canvasRef" 
                :width="canvasWidth" 
                :height="canvasHeight"
            ></canvas>
            <div class="instructions">
                <span v-if="!isMobile">SPACEBAR JUMP • DOWN ARROW DESCEND • ESC QUIT</span>
                <span v-else>TAP TO JUMP • BACK TO QUIT</span>
            </div>
            
            <div class="player-hud">
                <span class="player-name">{{ playerName }}</span>
                <span v-if="playerHighScore > 0" class="high-score">HI: {{ playerHighScore.toLocaleString() }}</span>
            </div>
        </div>

        <div class="side-panel">
            <div class="scoreboard-wrapper">
                <h3>GLOBAL LEADERBOARD</h3>
                <Scoreboard :key="scoreboardKey" />
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

.canvas-wrapper {
    position: relative;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    border: 2px solid #1e293b;
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
    flex: 1;
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
    color: #f8fafc;
    letter-spacing: 1px;
    padding: 0 1rem;
}

@media (max-width: 768px) {
    .instructions {
        font-size: 0.5rem;
        bottom: 0.5rem;
    }
}

.player-hud {
    position: absolute;
    top: 10px;
    left: 10px;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    text-shadow: 1px 1px 0 #000;
}

.player-name {
    color: #38bdf8;
    font-family: 'Press Start 2P', monospace;
    font-size: 0.6rem;
}

.high-score {
    color: #fbbf24;
    font-family: 'Press Start 2P', monospace;
    font-size: 0.5rem;
}

.side-panel {
    width: 100%;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(14, 165, 233, 0.2);
    border-radius: 0.75rem;
    padding: 0.75rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

@media (min-width: 769px) {
    .side-panel {
        width: 280px;
    }
}

.side-panel h3 {
    font-size: 0.7rem;
    color: #475569;
    margin-bottom: 0.5rem;
    letter-spacing: 2px;
    text-align: center;
}

.scoreboard-wrapper {
    margin-top: 0.5rem;
}

:deep(.scoreboard) {
    width: 100% !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
}
</style>

