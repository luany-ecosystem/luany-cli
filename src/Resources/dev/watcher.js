/**
 * Luany Dev Engine — File Watcher
 *
 * Watches the project file system and notifies connected browsers via
 * WebSocket. Replaces BrowserSync entirely — no proxy, no port conflicts,
 * no request loops.
 *
 * Topology:
 *   Browser ←── WebSocket (35729) ←── watcher.js ←── file changes
 *   Browser ────────────────────────→ PHP (8000)   [direct, no proxy]
 *
 * Reload strategy (v1):
 *   *.css               → inject-css  (no page reload, one per changed file)
 *   *.lte / *.php / *.js → reload     (full page reload)
 *   deleted files        → reload
 *
 * Message protocol (JSON):
 *   { "action": "reload" }
 *   { "action": "inject-css", "file": "app.css" }
 *
 * Usage (called by NodeRunner.php):
 *   node watcher.js <projectRoot> [wsPort] [devHost]
 *
 * Pure helpers (globs, ignore rules, batching, Origin check) live in
 * watcher-lib.js so they can be unit-tested without chokidar / ws.
 */

'use strict';

const chokidar = require('chokidar');
const { WebSocketServer } = require('ws');
const path = require('path');
const {
    buildWatchPaths,
    createIgnored,
    classify,
    createBatcher,
    isAllowedOrigin,
} = require('./watcher-lib');

// ── Config ────────────────────────────────────────────────────────────────────

const projectRoot = path.resolve(process.argv[2] || process.cwd());
const wsPort      = parseInt(process.argv[3] || '35729', 10);
const devHost     = process.argv[4] || 'localhost';

/** Debounce window in ms — prevents burst events from the same save */
const DEBOUNCE_MS = 40;

// ── WebSocket server ──────────────────────────────────────────────────────────

const wss = new WebSocketServer({
    host: '127.0.0.1',
    port: wsPort,
    // Only pages served from localhost (or the dev host) may subscribe.
    verifyClient: (info) => {
        const ok = isAllowedOrigin(info.origin, [devHost]);
        if (!ok) {
            process.stderr.write(`[LDE] Rejected WebSocket connection from origin ${info.origin}\n`);
        }
        return ok;
    },
});

wss.on('listening', () => {
    process.stdout.write(`[LDE] WebSocket server ready on ws://localhost:${wsPort}\n`);
});

wss.on('error', (err) => {
    process.stderr.write(`[LDE] WebSocket error: ${err.message}\n`);
    process.exit(1);
});

/**
 * Broadcast a JSON message to all connected browser clients.
 *
 * @param {object} payload
 */
function broadcast(payload) {
    const data = JSON.stringify(payload);
    wss.clients.forEach((client) => {
        // readyState 1 = OPEN
        if (client.readyState === 1) {
            client.send(data);
        }
    });
}

// ── Debounce ──────────────────────────────────────────────────────────────────

/**
 * Collapse burst events (e.g. editor writes temp file then final file)
 * into a single flush per debounce window. A full reload beats CSS
 * injection; otherwise every changed stylesheet is injected.
 */
const batcher = createBatcher((actions) => {
    const connected = wss.clients.size;
    const clients   = `${connected} client${connected !== 1 ? 's' : ''}`;

    actions.forEach((action) => {
        const label = action.action === 'inject-css'
            ? `inject-css → ${action.file}`
            : 'reload';

        process.stdout.write(`[LDE] ${label} (${clients})\n`);
        broadcast(action);
    });
}, DEBOUNCE_MS);

// ── File watcher ──────────────────────────────────────────────────────────────

const watcher = chokidar.watch(buildWatchPaths(projectRoot), {
    // Hidden files, node_modules, top-level vendor/, storage cache/logs —
    // evaluated relative to the project root.
    ignored:           createIgnored(projectRoot),
    persistent:        true,
    ignoreInitial:     true,   // don't fire for existing files on startup
    awaitWriteFinish: {
        stabilityThreshold: 80,   // wait 80ms after last write event
        pollInterval:       10,
    },
});

watcher.on('ready', () => {
    process.stdout.write(`[LDE] Watching for changes in ${projectRoot}\n`);
});

const labels = { change: 'Changed', add: 'Added', unlink: 'Removed' };

Object.keys(labels).forEach((event) => {
    watcher.on(event, (filePath) => {
        const relative = path.relative(projectRoot, filePath);
        process.stdout.write(`[LDE] ${labels[event]}: ${relative}\n`);
        batcher.schedule(classify(event, filePath));
    });
});

watcher.on('error', (err) => {
    process.stderr.write(`[LDE] Watcher error: ${err.message}\n`);
});

// ── Clean shutdown ────────────────────────────────────────────────────────────

function shutdown() {
    process.stdout.write('\n[LDE] Shutting down watcher...\n');
    watcher.close().then(() => {
        wss.close(() => process.exit(0));
    });
}

process.on('SIGINT',  shutdown);
process.on('SIGTERM', shutdown);
