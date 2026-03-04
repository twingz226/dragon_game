import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Get player data from localStorage or generate defaults
const getPlayerData = () => {
    const playerId = localStorage.getItem('dino_player_id') || 'guest-' + Math.random().toString(36).substr(2, 9);
    const playerName = localStorage.getItem('dino_player_name') || 'Anonymous Player';

    return { playerId, playerName };
};

// Resolve config: prefer runtime-injected window.reverbConfig (set by Blade),
// fall back to Vite build-time env vars only when the runtime value is null/undefined.
// Use ?? (nullish) NOT || so empty strings from Blade don't falsely fall through.
const reverbKey = (window.reverbConfig?.key ?? import.meta.env.VITE_REVERB_APP_KEY) || '';
const reverbHost = (window.reverbConfig?.wsHost ?? import.meta.env.VITE_REVERB_HOST) || '';
const reverbPort = window.reverbConfig?.wsPort ?? +(import.meta.env.VITE_REVERB_PORT ?? 80);
const reverbWssPort = window.reverbConfig?.wssPort ?? +(import.meta.env.VITE_REVERB_PORT ?? 443);
const reverbScheme = (window.reverbConfig?.scheme ?? import.meta.env.VITE_REVERB_SCHEME) || 'https';

if (!reverbKey) {
    console.error('[Echo] REVERB_APP_KEY is not set! WebSocket connection will fail. Check Railway env vars.');
}

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverbKey,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbWssPort,
    forceTLS: reverbScheme === 'https',
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
