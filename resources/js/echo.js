import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// The CSRF token is read when a private channel is authorized, not once at
// startup: the app loads on the sign-in page (no token yet) and the session
// is regenerated at login, so a token captured up front would be stale.
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? window.livewireScriptConfig?.csrf ?? '';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    // Connect to the site's own host and match the page's scheme (wss on an https
    // page, ws on http) — a browser blocks a plain ws:// socket from an https page.
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: window.location.protocol === 'https:',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            fetch('/broadcasting/auth', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
            })
                .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`Auth failed: ${response.status}`))))
                .then((data) => callback(null, data))
                .catch((error) => callback(error, null));
        },
    }),
});
