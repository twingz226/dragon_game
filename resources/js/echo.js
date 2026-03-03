import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Get player data from localStorage or generate defaults
const getPlayerData = () => {
    const playerId = localStorage.getItem('dino_player_id') || 'guest-' + Math.random().toString(36).substr(2, 9);
    const playerName = localStorage.getItem('dino_player_name') || 'Anonymous Player';
    
    return { playerId, playerName };
};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Add custom authentication configuration
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        params: {
            ...getPlayerData()
        }
    },
    // Configure authorizer for custom authentication
    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                const { playerId, playerName } = getPlayerData();
                
                // Build the auth URL with player data
                const authUrl = options.authEndpoint + 
                    '?socket_id=' + encodeURIComponent(socketId) + 
                    '&channel_name=' + encodeURIComponent(channel.name) +
                    '&player_id=' + encodeURIComponent(playerId) +
                    '&player_name=' + encodeURIComponent(playerName);
                
                // Make the authentication request
                fetch(authUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.auth) {
                        callback(false, data);
                    } else {
                        callback(true, new Error('Authentication failed'));
                    }
                })
                .catch(error => {
                    callback(true, error);
                });
            }
        };
    },
});
