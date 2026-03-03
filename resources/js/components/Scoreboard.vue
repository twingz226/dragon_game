<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const scores = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const response = await axios.get('/api/leaderboard');
        scores.value = response.data;
    } catch (e) {
        console.error("Failed to fetch leaderboard", e);
    } finally {
        loading.value = false;
    }
});

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString();
}
</script>

<template>
    <div class="scoreboard">
        <h3>GLOBAL TOP 10</h3>
        
        <div v-if="loading" class="loading">LOADING...</div>
        
        <div v-else class="score-list">
            <div v-for="(score, index) in scores" :key="index" class="score-item">
                <span class="rank">#{{ index + 1 }}</span>
                <span class="name">{{ score.player_name }}</span>
                <span class="val">{{ score.score.toLocaleString() }}</span>
            </div>
            
            <div v-if="scores.length === 0" class="empty">
                NO SCORES YET. BE THE FIRST!
            </div>
        </div>
    </div>
</template>

<style scoped>
.scoreboard {
    background: rgba(15, 23, 42, 0.9);
    border: 1px solid rgba(14, 165, 233, 0.2);
    border-radius: 0.5rem;
    padding: 1.5rem;
    width: 300px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
}

h3 {
    font-family: 'Press Start 2P', cursive;
    font-size: 0.8rem;
    color: #0ea5e9;
    margin-bottom: 1.5rem;
    text-align: center;
}

.score-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.score-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: #f8fafc;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.rank {
    width: 30px;
    color: #475569;
    font-family: 'Press Start 2P', cursive;
    font-size: 0.6rem;
}

.name {
    flex: 1;
    margin-left: 0.5rem;
}

.val {
    font-family: 'Press Start 2P', cursive;
    font-size: 0.7rem;
    color: #38bdf8;
}

.loading, .empty {
    text-align: center;
    font-size: 0.7rem;
    color: #475569;
    padding: 1rem;
}
</style>
