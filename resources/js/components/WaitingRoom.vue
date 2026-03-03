<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps(['roomCode', 'playerId', 'playerName', 'isHost']);
const emit = defineEmits(['gameStarted', 'back']);

const players = ref([]);
const loading = ref(false);
const error = ref('');
const refreshInterval = ref(null);
const gameStartTimeout = ref(null);

// Fetch players from API
async function fetchPlayers() {
    try {
        const response = await axios.get(`/api/rooms/${props.roomCode}/players`);
        players.value = response.data;
    } catch (e) {
        console.error('Failed to fetch players:', e);
    }
}

async function startGame() {
    if (!props.isHost) return;
    
    loading.value = true;
    error.value = '';
    
    try {
        console.log('Host starting game for room:', props.roomCode);
        
        // Update room status to playing - this will broadcast the event
        await axios.patch(`/api/rooms/${props.roomCode}/status`, {
            status: 'playing'
        });
        
        console.log('Game status updated, waiting for broadcast event...');
        
        // Set a fallback timeout in case broadcast doesn't work
        gameStartTimeout.value = setTimeout(() => {
            console.log('Fallback timeout reached, proceeding with game start...');
            loading.value = false;
            emit('gameStarted', {
                room_code: props.roomCode,
                is_host: props.isHost,
                obstacle_seed: Math.floor(Math.random() * 999999999)
            });
        }, 3000); // 3 second fallback
        
    } catch (e) {
        console.error('Failed to start game:', e);
        error.value = 'Failed to start game';
        loading.value = false;
    }
}

function backToLobby() {
    emit('back');
}

onMounted(() => {
    // Initial fetch
    fetchPlayers();
    
    // Refresh players list every 2 seconds
    refreshInterval.value = setInterval(fetchPlayers, 2000);
    
    console.log(`Player ${props.playerName} (${props.playerId}) joining room ${props.roomCode}`);
    
    // Listen for game started broadcast
    window.Echo.join(`game.room.${props.roomCode}`)
        .here((users) => {
            console.log('Successfully joined presence channel. Current users:', users);
        })
        .joining((user) => {
            console.log('Player joining:', user);
        })
        .leaving((user) => {
            console.log('Player leaving:', user);
        })
        .listen('game.started', (e) => {
            console.log('=== GAME STARTED EVENT RECEIVED ===');
            console.log('Full event data:', e);
            console.log('Event type:', typeof e);
            console.log('Event keys:', Object.keys(e || {}));
            console.log('Room code from event:', e?.roomCode);
            console.log('Obstacle seed from event:', e?.obstacleSeed);
            console.log('Player transitioning to game screen...');
            
            // Clear the fallback timeout since we received the event
            if (gameStartTimeout.value) {
                clearTimeout(gameStartTimeout.value);
                gameStartTimeout.value = null;
            }
            
            // Reset loading state for host
            loading.value = false;
            
            emit('gameStarted', {
                room_code: props.roomCode,
                is_host: props.isHost,
                obstacle_seed: e.obstacleSeed
            });
        })
        .error((error) => {
            console.error('Presence channel error:', error);
        });
});

onUnmounted(() => {
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
    }
    
    // Clear the game start timeout if it exists
    if (gameStartTimeout.value) {
        clearTimeout(gameStartTimeout.value);
    }
    
    // Leave the Echo channel
    window.Echo.leave(`game.room.${props.roomCode}`);
});
</script>

<template>
    <div class="waiting-room">
        <div class="room-header">
            <h2>WAITING ROOM</h2>
            <div class="room-code-display">
                <span class="code-label">ROOM CODE:</span>
                <span class="code-value">{{ roomCode }}</span>
                <button class="copy-btn" @click="navigator.clipboard.writeText(roomCode)">
                    COPY
                </button>
            </div>
        </div>

        <div class="players-section">
            <h3>PLAYERS IN ROOM</h3>
            <div class="players-list">
                <div 
                    v-for="player in players" 
                    :key="player.id" 
                    class="player-item"
                    :class="{ host: player.isHost }"
                >
                    <div class="player-info">
                        <span class="player-name">{{ player.name }}</span>
                        <span v-if="player.isHost" class="host-badge">HOST</span>
                    </div>
                    <div class="player-status">
                        <div class="status-indicator ready"></div>
                        <span>READY</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="waiting-message" v-if="players.length === 1">
            <p>🦖 Ready to play solo!</p>
            <p class="share-hint">Start the game or share the room code with friends!</p>
        </div>

        <div class="actions">
            <button 
                v-if="isHost" 
                class="btn-start" 
                :disabled="loading"
                @click="startGame"
            >
                {{ loading ? 'STARTING...' : 'START GAME' }}
            </button>
            
            <button class="btn-leave" @click="backToLobby">
                LEAVE ROOM
            </button>
        </div>

        <p v-if="error" class="error-msg">{{ error }}</p>
    </div>
</template>

<style scoped>
.waiting-room {
    background: rgba(30, 41, 59, 0.7);
    padding: 1.5rem;
    border-radius: 1rem;
    border: 1px solid rgba(56, 189, 248, 0.3);
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(56, 189, 248, 0.05);
    width: 100%;
    max-width: 400px;
    text-align: center;
    backdrop-filter: blur(8px);
    margin: 0 auto;
}

@media (max-width: 768px) {
    .waiting-room {
        padding: 1rem;
        margin: 0 1rem;
        max-width: none;
    }
}

.room-header {
    margin-bottom: 1rem;
}

.room-header h2 {
    font-family: 'Press Start 2P', cursive;
    font-size: 1rem;
    color: #f8fafc;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .room-header h2 {
        font-size: 0.8rem;
    }
}

.room-code-display {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.code-label {
    color: #94a3b8;
    font-size: 0.8rem;
}

.code-value {
    font-family: 'Press Start 2P', cursive;
    font-size: 1.2rem;
    color: #38bdf8;
    background: rgba(15, 23, 42, 0.9);
    padding: 0.3rem 0.8rem;
    border-radius: 0.5rem;
    border: 1px solid #334155;
    letter-spacing: 0.1em;
}

@media (max-width: 768px) {
    .code-value {
        font-size: 1rem;
        padding: 0.2rem 0.6rem;
    }
}

.copy-btn {
    background: #334155;
    color: #f8fafc;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    cursor: pointer;
    font-family: 'Orbitron', sans-serif;
    font-size: 0.7rem;
    transition: all 0.2s;
}

.copy-btn:hover {
    background: #475569;
}

.players-section {
    margin-bottom: 1rem;
}

.players-section h3 {
    color: #f8fafc;
    font-size: 1rem;
    margin-bottom: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.players-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.player-item {
    background: rgba(15, 23, 42, 0.9);
    border: 1px solid #334155;
    border-radius: 0.5rem;
    padding: 0.7rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s;
}

.player-item.host {
    border-color: #38bdf8;
    background: rgba(56, 189, 248, 0.1);
}

.player-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.player-name {
    color: #f8fafc;
    font-weight: 600;
}

.host-badge {
    background: #38bdf8;
    color: #0f172a;
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.6rem;
    font-weight: 700;
}

.player-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #94a3b8;
    font-size: 0.8rem;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.waiting-message {
    margin-bottom: 1rem;
    color: #94a3b8;
}

.waiting-message p {
    margin: 0.5rem 0;
}

.share-hint {
    font-size: 0.8rem;
    color: #64748b;
}

.actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

button {
    cursor: pointer;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    padding: 1rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    border: none;
}

.btn-start {
    background: #10b981;
    color: white;
    font-size: 1.1rem;
}

.btn-start:hover:not(:disabled) {
    background: #34d399;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

.btn-start:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

.btn-leave {
    background: #334155;
    color: #f8fafc;
}

.btn-leave:hover {
    background: #ef4444;
}

.error-msg {
    color: #f43f5e;
    font-size: 0.8rem;
    margin-top: 1rem;
}
</style>
