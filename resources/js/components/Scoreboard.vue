<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const rawScores = ref([]);
const loading = ref(true);

const scores = computed(() => {
    const list = [...rawScores.value];
    // Pad to 10 slots
    while (list.length < 10) {
        list.push({ player_name: '---', score: 0, isEmpty: true });
    }
    return list;
});

onMounted(async () => {
    try {
        const response = await axios.get('/api/leaderboard');
        rawScores.value = response.data;
    } catch (e) {
        console.error("Failed to fetch leaderboard", e);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="scoreboard">
        <div class="header-glow"></div>
        <h3>GLOBAL TOP 10</h3>
        
        <div v-if="loading" class="loading">
            <div class="spinner"></div>
            <span>SYNCING...</span>
        </div>
        
        <div v-else class="score-list">
            <div 
                v-for="(score, index) in scores" 
                :key="index" 
                class="score-item"
                :class="{ 'empty-slot': score.isEmpty, 'top-3': index < 3 && !score.isEmpty }"
            >
                <span class="rank" :class="'rank-' + (index + 1)">#{{ index + 1 }}</span>
                <span class="name">{{ score.player_name }}</span>
                <span class="val">{{ score.isEmpty ? '---' : score.score.toLocaleString() }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scoreboard {
    background: rgba(15, 23, 42, 0.95);
    border: 2px solid rgba(14, 165, 233, 0.3);
    border-radius: 0.75rem;
    padding: 1rem;
    width: 100%;
    max-width: 320px;
    box-shadow: 0 0 20px rgba(14, 165, 233, 0.15), 0 10px 40px rgba(0, 0, 0, 0.6);
    box-sizing: border-box;
    position: relative;
    overflow: hidden;
}

.header-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, #38bdf8, transparent);
    box-shadow: 0 0 15px #38bdf8;
}

h3 {
    font-family: 'Press Start 2P', cursive;
    font-size: 0.7rem;
    color: #f8fafc;
    margin-bottom: 1rem;
    text-align: center;
    letter-spacing: 1px;
    text-shadow: 0 0 8px rgba(56, 189, 248, 0.6);
}

.score-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.score-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.75rem;
    color: #f8fafc;
    padding: 0.4rem 0.6rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.4rem;
    transition: all 0.2s;
    border: 1px solid transparent;
}

.score-item:hover:not(.empty-slot) {
    background: rgba(56, 189, 248, 0.1);
    border-color: rgba(56, 189, 248, 0.2);
    transform: translateX(4px);
}

.top-3 {
    background: rgba(14, 165, 233, 0.1);
    border-color: rgba(14, 165, 233, 0.2);
}

.rank {
    width: 35px;
    color: #94a3b8;
    font-family: 'Press Start 2P', cursive;
    font-size: 0.6rem;
}

.rank-1 { color: #fbbf24; text-shadow: 0 0 5px rgba(251, 191, 36, 0.5); }
.rank-2 { color: #e2e8f0; }
.rank-3 { color: #cd7f32; }

.name {
    flex: 1;
    margin-left: 0.5rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 500;
}

.val {
    font-family: 'Press Start 2P', cursive;
    font-size: 0.65rem;
    color: #38bdf8;
    text-align: right;
    min-width: 60px;
}

.empty-slot {
    opacity: 0.35;
    background: transparent;
}

.empty-slot .name, .empty-slot .val {
    color: #475569;
}

.loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 2rem 0;
    color: #38bdf8;
    font-family: 'Press Start 2P', cursive;
    font-size: 0.6rem;
}

.spinner {
    width: 24px;
    height: 24px;
    border: 3px solid rgba(56, 189, 248, 0.2);
    border-top: 3px solid #38bdf8;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
