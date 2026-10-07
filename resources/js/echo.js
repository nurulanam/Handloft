import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// The CSRF token is read when a private channel is authorized, not once at
// startup: the app loads on the sign-in page (no token yet) and the session
// is regenerated at login, so a token captured up front would be stale.
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? window.livewireScriptConfig?.csrf ?? '';

/**
 * The live-notification settings for this page (Settings → Live notifications), from the
 * <meta name="live-config"> tag the app layout renders. No tag (e.g. the sign-in page) means off.
 */
export function liveConfig() {
    try {
        return JSON.parse(document.querySelector('meta[name="live-config"]')?.content || 'null');
    } catch {
        return null;
    }
}

let current = null;

/**
 * Connect to Reverb with the given settings, reusing the open connection when they haven't changed;
 * disconnect when live notifications are off. Returns the Echo instance, or null.
 */
export function syncEcho(config) {
    const signature = config?.enabled && config.key ? JSON.stringify([config.key, config.host, config.port, config.scheme]) : null;

    if (signature === current) {
        return window.Echo ?? null;
    }

    window.Echo?.disconnect();
    window.Echo = null;
    current = signature;

    if (! signature) {
        return null;
    }

    // Blank host = the site's own host; blank scheme = match the page (wss on https, ws on http,
    // since a browser blocks a plain ws:// socket from an https page).
    const tls = config.scheme ? config.scheme === 'https' : window.location.protocol === 'https:';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host || window.location.hostname,
        wsPort: config.port ?? 80,
        wssPort: config.port ?? 443,
        forceTLS: tls,
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

    return window.Echo;
}

/** The live connection's state: 'connected', 'connecting', 'unavailable', 'failed', … or 'off'. */
export function liveState() {
    return window.Echo?.connector?.pusher?.connection?.state ?? 'off';
}
