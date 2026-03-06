<script setup>
import Scoreboard from './Scoreboard.vue';

const props = defineProps({
    playerId: String,
    playerName: String
});
const emit = defineEmits(['started']);

function startGame() {
    if (!props.playerName) return;
    emit('started');
}
</script>

<template>
    <div class="lobby-card">
        <div class="card-header">
            <h2>START THE RACE</h2>
            <p>Ready to run?</p>
        </div>

        <div class="form-group">
            <input 
                :value="playerName" 
                type="text" 
                placeholder="NICKNAME" 
                disabled
            >
        </div>

        <div class="actions">
            <button class="btn-primary" @click="startGame()">
                PLAY
            </button>
        </div>

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
