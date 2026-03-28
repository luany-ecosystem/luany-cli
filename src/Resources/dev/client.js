/**
 * Luany Dev Engine — Browser Client
 *
 * Injected automatically into HTML responses by DevMiddleware when
 * APP_ENV=development. Served at /__luany_dev/client.js.
 *
 * Connects to the LDE WebSocket server and applies changes:
 *   reload      → location.reload()
 *   inject-css  → updates matching <link> href with cache buster
 *                 (no page reload — instant visual feedback)
 *
 * Reconnect strategy: exponential back-off capped at 5 s.
 * Zero dependencies. Zero globals. IIFE-isolated.
 */
(function () {
    'use strict';

    var WS_PORT       = (window.__LDE_WS_PORT__ || 35729);
    var WS_URL        = 'ws://127.0.0.1:' + WS_PORT;
    var RECONNECT_MIN = 500;   // ms — initial reconnect delay
    var RECONNECT_MAX = 5000;  // ms — cap
    var RECONNECT_MUL = 1.5;   // back-off multiplier

    var reconnectDelay = RECONNECT_MIN;
    var ws             = null;
    var connecting     = false;

    // ── CSS inject ────────────────────────────────────────────────────────────

    /**
     * Update the href of any <link rel="stylesheet"> whose href ends with
     * the given filename, appending a timestamp cache-buster.
     * Falls back to a full reload if no matching link is found.
     *
     * @param {string} file  Basename of the changed CSS file (e.g. "app.css")
     */
    function injectCss(file) {
        var links   = document.querySelectorAll('link[rel="stylesheet"]');
        var matched = false;

        links.forEach(function (link) {
            var href = link.getAttribute('href') || '';
            // Match on basename — strip query string before comparing
            var base = href.split('?')[0].split('/').pop();
            if (base === file) {
                link.href = href.split('?')[0] + '?lde=' + Date.now();
                matched = true;
            }
        });

        if (!matched) {
            // Unknown file — safe fallback
            window.location.reload();
        }
    }

    // ── Message handler ───────────────────────────────────────────────────────

    function onMessage(event) {
        var payload;
        try {
            payload = JSON.parse(event.data);
        } catch (e) {
            return;
        }

        switch (payload.action) {
            case 'reload':
                window.location.reload();
                break;
            case 'inject-css':
                injectCss(payload.file);
                break;
        }
    }

    // ── Connection management ─────────────────────────────────────────────────

    function connect() {
        if (connecting || (ws && ws.readyState === WebSocket.OPEN)) {
            return;
        }

        connecting = true;
        ws         = new WebSocket(WS_URL);

        ws.addEventListener('open', function () {
            connecting     = false;
            reconnectDelay = RECONNECT_MIN;
            console.debug('[LDE] Connected to dev server');
        });

        ws.addEventListener('message', onMessage);

        ws.addEventListener('close', function () {
            connecting = false;
            ws         = null;
            console.debug('[LDE] Disconnected — reconnecting in ' + reconnectDelay + 'ms');
            setTimeout(connect, reconnectDelay);
            reconnectDelay = Math.min(reconnectDelay * RECONNECT_MUL, RECONNECT_MAX);
        });

        ws.addEventListener('error', function () {
            // error is always followed by close — close handler retries
            connecting = false;
        });
    }

    // ── Boot ──────────────────────────────────────────────────────────────────

    if (typeof WebSocket === 'undefined') {
        console.warn('[LDE] WebSocket not supported — live reload disabled');
        return;
    }

    connect();
}());