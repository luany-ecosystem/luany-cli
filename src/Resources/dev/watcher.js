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
 *   *.css               → inject-css  (no page reload)
 *   *.lte / *.php / *.js → reload     (full page reload)
 *
 * Message protocol (JSON):
 *   { "action": "reload" }
 *   { "action": "inject-css", "file": "app.css" }
 *
 * Usage (called by NodeRunner.php):
 *   node watcher.js <projectRoot> [wsPort]
 */

'use strict';

const chokidar = require('chokidar');
const { WebSocketServer } = require('ws');
const path = require('path');

// ── Config ────────────────────────────────────────────────────────────────────

const projectRoot = process.argv[2] || process.cwd();
const wsPort      = parseInt(process.argv[3] || '35729', 10);

/** Debounce window in ms — prevents burst events from the same save */
const DEBOUNCE_MS = 40;

// ── WebSocket server ──────────────────────────────────────────────────────────

const wss = new WebSocketServer({ host: '127.0.0.1', port: wsPort });

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

// ── Change classifier ─────────────────────────────────────────────────────────

/**
 * Classify a file path into a reload action.
 *
 * @param  {string} filePath
 * @returns {{ action: string, file?: string }}
 */
function classify(filePath) {
    const ext = path.extname(filePath).toLowerCase();

    if (ext === '.css') {
        return { action: 'inject-css', file: path.basename(filePath) };
    }

    // .lte, .php, .js — full reload
    return { action: 'reload' };
}

// ── Debounce ──────────────────────────────────────────────────────────────────

/**
 * Collapse burst events (e.g. editor writes temp file then final file)
 * into a single action within the debounce window.
 *
 * Strategy: within the window, if any file triggers a full reload, that
 * takes priority over inject-css from another file.
 */
let debounceTimer = null;
let pendingAction = null;

function scheduleAction(action) {
    // Full reload always beats inject-css within the same window
    if (!pendingAction || (pendingAction.action !== 'reload' && action.action === 'reload')) {
        pendingAction = action;
    }

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const toSend = pendingAction;
        pendingAction = null;

        const connected = wss.clients.size;
        const label     = toSend.action === 'inject-css'
            ? `inject-css → ${toSend.file}`
            : 'reload';

        process.stdout.write(`[LDE] ${label} (${connected} client${connected !== 1 ? 's' : ''})\n`);
        broadcast(toSend);
    }, DEBOUNCE_MS);
}

// ── File watcher ──────────────────────────────────────────────────────────────

const watchPaths = [
    path.join(projectRoot, 'views/**/*.lte'),
    path.join(projectRoot, 'app/**/*.php'),
    path.join(projectRoot, 'routes/**/*.php'),
    path.join(projectRoot, 'config/**/*.php'),
    path.join(projectRoot, 'public/assets/**/*'),
];

const watcher = chokidar.watch(watchPaths, {
    // Ignore hidden files, node_modules, vendor, storage cache
    ignored: [
        /(^|[/\\])\../,
        /node_modules/,
        /vendor/,
        /storage[/\\]cache/,
        /storage[/\\]logs/,
    ],
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

watcher.on('change', (filePath) => {
    const relative = path.relative(projectRoot, filePath);
    process.stdout.write(`[LDE] Changed: ${relative}\n`);
    scheduleAction(classify(filePath));
});

watcher.on('add', (filePath) => {
    const relative = path.relative(projectRoot, filePath);
    process.stdout.write(`[LDE] Added: ${relative}\n`);
    scheduleAction(classify(filePath));
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