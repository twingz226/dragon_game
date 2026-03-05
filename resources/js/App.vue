<script setup>
import { ref, onMounted } from 'vue';
import LobbyScreen from './components/LobbyScreen.vue';
import WaitingRoom from './components/WaitingRoom.vue';
import GameCanvas from './components/GameCanvas.vue';
import { v4 as uuidv4 } from 'uuid';

const currentScreen = ref('lobby'); // lobby, waiting, playing, game-over
const roomData = ref(null);
const playerId = ref(localStorage.getItem('dino_player_id') || uuidv4());
const playerName = ref(localStorage.getItem('dino_player_name') || '');
const isAuthenticated = ref(false);
const currentUser = ref(null);

onMounted(() => {
    localStorage.setItem('dino_player_id', playerId.value);
    checkAuthentication();
});

async function checkAuthentication() {
    try {
        const response = await fetch('/api/user');
        if (response.ok) {
            const data = await response.json();
            currentUser.value = data.user;
            isAuthenticated.value = true;
        } else {
            // Redirect to login if not authenticated
            window.location.href = '/login';
        }
    } catch (error) {
        console.error('Authentication check failed:', error);
        window.location.href = '/login';
    }
}

async function logout() {
    try {
        await fetch('/logout', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                                document.querySelector('input[name="_token"]')?.value
            }
        });
        window.location.href = '/login';
    } catch (error) {
        console.error('Logout failed:', error);
    }
}

function handleJoined(data) {
    roomData.value = data;
    playerName.value = data.playerName;
    localStorage.setItem('dino_player_name', data.playerName);
    
    // All players go to waiting room first
    currentScreen.value = 'waiting';
}

function handleGameStarted(data) {
    roomData.value = { ...roomData.value, ...data };
    currentScreen.value = 'playing';
}

function handleGameOver(score) {
    // We can handle game over state here if needed
}

function backToLobby() {
    currentScreen.value = 'lobby';
    roomData.value = null;
}
</script>

<template>
    <div class="app-container">
        <header class="game-header">
            <h1 class="glitch" data-text="DINO RACE">DINO RACE</h1>
            <div class="header-info">
                <div v-if="roomData" class="room-info">
                    <span>ROOM: <strong>{{ roomData.room_code }}</strong></span>
                    <span>PLAYER: <strong>{{ playerName }}</strong></span>
                </div>
                <div v-if="currentUser" class="user-info">
                    <span>USER: <strong>{{ currentUser.name }}</strong></span>
                    <button @click="logout" class="logout-btn">Logout</button>
                </div>
            </div>
        </header>

        <main class="game-content">
            <LobbyScreen 
                v-if="currentScreen === 'lobby'" 
                :playerId="playerId"
                @joined="handleJoined" 
            />
            <WaitingRoom 
                v-if="currentScreen === 'waiting'" 
                :roomCode="roomData.room_code"
                :playerId="playerId"
                :playerName="playerName"
                :isHost="roomData.is_host"
                :obstacleSeed="roomData.obstacle_seed"
                @game-started="handleGameStarted"
                @back="backToLobby"
            />
            <GameCanvas 
                v-if="currentScreen === 'playing'" 
                :roomCode="roomData.room_code"
                :playerId="playerId"
                :playerName="playerName"
                :isHost="roomData.is_host"
                :obstacleSeed="roomData.obstacle_seed"
                @game-over="handleGameOver"
                @back="backToLobby"
            />
        </main>

        <footer class="game-footer">
            <p>Built with Laravel Reverb & Vue 3</p>
        </footer>
    </div>
</template>

<style scoped>
.app-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
}

.game-header {
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    flex-wrap: wrap;
    gap: 1rem;
}

.header-info {
    display: flex;
    gap: 1.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.room-info {
    font-size: 0.8rem;
    display: flex;
    gap: 1.5rem;
    color: #94a3b8;
}

.user-info {
    font-size: 0.8rem;
    display: flex;
    gap: 1rem;
    align-items: center;
    color: #94a3b8;
}

.logout-btn {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Orbitron', sans-serif;
    text-transform: uppercase;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.3);
    border-color: rgba(239, 68, 68, 0.5);
}

@media (max-width: 768px) {
    .game-header {
        padding: 0.75rem 1rem;
        flex-direction: column;
        text-align: center;
    }
    
    .header-info {
        flex-direction: column;
        gap: 1rem;
        width: 100%;
    }
    
    .room-info, .user-info {
        flex-direction: column;
        gap: 0.5rem;
        align-items: center;
        font-size: 0.7rem;
    }
}

h1 {
    font-family: 'Press Start 2P', cursive;
    font-size: 1.5rem;
    color: #38bdf8;
    margin: 0;
    text-shadow: 2px 2px #0ea5e9;
}

@media (max-width: 768px) {
    h1 {
        font-size: 1rem;
    }
}

.room-info {
    font-size: 0.8rem;
    display: flex;
    gap: 1.5rem;
    color: #94a3b8;
}

@media (max-width: 768px) {
    .room-info {
        font-size: 0.7rem;
        gap: 1rem;
        flex-direction: column;
        align-items: center;
    }
}

.room-info strong {
    color: #f8fafc;
}

.game-content {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    padding: 2rem;
}

@media (max-width: 768px) {
    .game-content {
        padding: 1rem;
    }
}

.game-footer {
    padding: 0.5rem;
    text-align: center;
    font-size: 0.7rem;
    color: #475569;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

/* Simple glitch effect */
.glitch {
    position: relative;
}
.glitch::before,
.glitch::after {
    content: attr(data-text);
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0.8;
}
.glitch::before {
    color: #ff00ff;
    z-index: -1;
    animation: glitch 3s cubic-bezier(0.25, 0.46, 0.45, 0.94) both infinite;
}
.glitch::after {
    color: #00ffff;
    z-index: -2;
    animation: glitch 3s cubic-bezier(0.25, 0.46, 0.45, 0.94) reverse both infinite;
}

@keyframes glitch {
    0% { transform: translate(0); }
    20% { transform: translate(-2px, 2px); }
    40% { transform: translate(-2px, -2px); }
    60% { transform: translate(2px, 2px); }
    80% { transform: translate(2px, -2px); }
    100% { transform: translate(0); }
}
</style>
