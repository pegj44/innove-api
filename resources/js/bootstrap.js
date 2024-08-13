/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss']
});

function startSimple(params)
{
    console.log(params);
    UiPathRobot.init(10);
console.log(UiPathRobot.getProcesses());
    UiPathRobot.getProcesses().then(processes =>
    {
        console.log(processes);
        let process = processes.find(p => p.name.includes('BrowserJsTest'));
        let processArgs = {
            unit1: params.arguments.unit1,
            unit2: params.arguments.unit2,
            unitCommand: params.action
        };

        console.log(processArgs);
        process.start(processArgs).then(result =>
        {
            console.log('ok');
        }, err => {
            console.log(err);
        })
    }, err => {
        console.log(err);
    });
    //alert('startx');
}

window.Echo.private('unit.3').listen('UnitsEvent', response => {
    startSimple(response);
});
