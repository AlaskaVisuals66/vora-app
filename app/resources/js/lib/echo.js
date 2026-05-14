import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

export function initEcho() {
    if (echoInstance) return echoInstance;

    window.Pusher = Pusher;

    const cfg = window.__pusher__ || {};

    echoInstance = new Echo({
        broadcaster: 'pusher',
        key: cfg.key,
        wsHost: cfg.host,
        wsPort: cfg.port,
        wssPort: cfg.port,
        forceTLS: cfg.scheme === 'https',
        encrypted: cfg.scheme === 'https',
        cluster: cfg.cluster || 'mt1',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                Authorization: `Bearer ${localStorage.getItem('helpdesk.jwt') || ''}`,
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });

    window.Echo = echoInstance;
    return echoInstance;
}

export function getEcho() {
    return echoInstance || initEcho();
}

export function leaveAll(channels = []) {
    const echo = getEcho();
    channels.forEach((c) => echo.leave(c));
}
