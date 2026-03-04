<script setup>
import { ref } from 'vue';
import axios from 'axios';
import Scoreboard from './Scoreboard.vue';

const props = defineProps(['playerId']);
const emit = defineEmits(['joined']);

const playerName = ref(localStorage.getItem('dino_player_name') || '');
const roomCodeInput = ref('');
const loading = ref(false);
const error = ref('');

async function joinRoom(code = null) {
    if (!playerName.value) {
        error.value = "Enter your name first!";
        return;
    }
    
    loading.value = true;
    error.value = '';
    
    try {
        const response = await axios.post('/api/rooms/join', {
            player_id: props.playerId,
            player_name: playerName.value,
            room_code: code
        });
        
        emit('joined', {
            ...response.data,
            playerName: playerName.value
        });
    } catch (e) {
        error.value = e.response?.data?.error || "Failed to join room.";
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="lobby-card">
        <div class="card-header">
            <h2>JOIN THE RACE</h2>
            <p>Enter your nickname to start</p>
        </div>

        <div class="form-group">
            <input 
                v-model="playerName" 
                type="text" 
                placeholder="NICKNAME" 
                maxlength="15"
                @keyup.enter="joinRoom()"
            >
        </div>

        <div class="actions">
            <button class="btn-primary" :disabled="loading" @click="joinRoom()">
                {{ loading ? 'CREATING...' : 'CREATE NEW ROOM' }}
            </button>
            
            <div class="separator">
                <span>OR</span>
            </div>

            <div class="form-group join-group">
                <input 
                    v-model="roomCodeInput" 
                    type="text" 
                    placeholder="ROOM CODE" 
                    maxlength="6"
                >
                <button class="btn-secondary" :disabled="loading" @click="joinRoom(roomCodeInput)">
                    JOIN
                </button>
            </div>
        </div>

        <p v-if="error" class="error-msg">{{ error }}</p>

        <div class="leaderboard-section">
            <Scoreboard />
        </div>
    </div>
</template>

<style scoped>
.lobby-card {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1rem;
    padding: 1.5rem;
    max-width: 400px;
    width: 100%;
    margin: 0 auto;
    backdrop-filter: blur(10px);
}

@media (max-width: 768px) {
    .lobby-card {
        padding: 1.5rem;
        margin: 0 1rem;
        max-width: none;
    }
}

.card-header h2 {
    font-family: 'Press Start 2P', cursive;
    font-size: 1.2rem;
    color: #f8fafc;
    margin-bottom: 0.5rem;
    text-align: center;
}

@media (max-width: 768px) {
    .card-header h2 {
        font-size: 1rem;
    }
}

.card-header p {
    color: #94a3b8;
    font-size: 0.75rem;
    margin-bottom: 0.75rem;
}

.form-group {
    margin-bottom: 0.75rem;
}

input {
    width: 100%;
    background: rgba(15, 23, 42, 0.9);
    border: 1px solid #334155;
    padding: 0.6rem 0.75rem;
    border-radius: 0.5rem;
    color: #f8fafc;
    font-family: 'Orbitron', sans-serif;
    outline: none;
    transition: all 0.2s;
    box-sizing: border-box;
    font-size: 0.9rem;
}

input:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 10px rgba(56, 189, 248, 0.2);
}

.actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

button {
    cursor: pointer;
    font-family: 'Orbitron', sans-serif;
    font-weight: 700;
    padding: 0.6rem 0.75rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    border: none;
    font-size: 0.85rem;
}

.btn-primary {
    background: #0ea5e9;
    color: white;
    width: 100%;
}

.btn-primary:hover {
    background: #38bdf8;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(14, 165, 233, 0.4);
}

.btn-secondary {
    background: #334155;
    color: #f8fafc;
    padding: 0 1.5rem;
}

.btn-secondary:hover {
    background: #475569;
}

.separator {
    display: flex;
    align-items: center;
    text-align: center;
    color: #475569;
    font-size: 0.7rem;
    margin: 0.5rem 0;
}

.separator::before,
.separator::after {
    content: '';
    flex: 1;
    border-bottom: 1px solid #334155;
}

.separator span {
    padding: 0 1rem;
}

.join-group {
    display: flex;
    gap: 0.5rem;
}

.error-msg {
    color: #f43f5e;
    font-size: 0.8rem;
    margin-top: 1.5rem;
}

.leaderboard-section {
    margin-top: 1rem;
    display: flex;
    justify-content: center;
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}
</style>
