/**
 * Luany Dev Engine — Watcher helpers
 *
 * Pure, dependency-free helpers used by watcher.js. Kept in a separate
 * module so they can be unit-tested without chokidar / ws installed
 * (see tests/js/watcher-lib.test.js).
 */

'use strict';

const path = require('path');

/** Directories (relative to the project root) that are watched. */
const WATCH_GLOBS = [
    'views/**/*.lte',
    'app/**/*.php',
    'routes/**/*.php',
    'config/**/*.php',
    'public/assets/**/*',
];

/** Hosts a browser page may be served from to connect to the WebSocket. */
const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]', '::1'];

/**
 * Convert a path to forward slashes. chokidar 3 / picomatch treat "\" as an
 * escape character, so Windows paths must be converted before globbing.
 *
 * @param  {string} p
 * @returns {string}
 */
function toPosix(p) {
    return String(p).replace(/\\/g, '/');
}

/**
 * Build the chokidar glob list for a project root (always forward slashes).
 *
 * @param  {string} projectRoot
 * @returns {string[]}
 */
function buildWatchPaths(projectRoot) {
    const root = toPosix(projectRoot).replace(/\/+$/, '');
    return WATCH_GLOBS.map((glob) => `${root}/${glob}`);
}

/**
 * Path of filePath relative to projectRoot, with forward slashes.
 * Returns null when filePath is outside projectRoot.
 *
 * @param  {string} projectRoot
 * @param  {string} filePath
 * @param  {object} [pathImpl]  path / path.win32 / path.posix (for tests)
 * @returns {string|null}
 */
function relativeTo(projectRoot, filePath, pathImpl = path) {
    const rel = toPosix(pathImpl.relative(projectRoot, filePath));

    if (rel === '') {
        return '';
    }
    if (rel === '..' || rel.startsWith('../') || pathImpl.isAbsolute(rel)) {
        return null;
    }

    return rel;
}

/**
 * Build the chokidar `ignored` predicate.
 *
 * Rules are evaluated on the path RELATIVE to the project root, so a
 * project living under e.g. ~/.projects/app or /srv/vendor/app still works:
 *   - any hidden segment (".git", ".env", ".cache/…")
 *   - node_modules anywhere
 *   - the top-level vendor/ directory only (public/assets/vendor is watched)
 *   - storage/cache and storage/logs
 *
 * @param  {string} projectRoot
 * @param  {object} [pathImpl]
 * @returns {(filePath: string) => boolean}
 */
function createIgnored(projectRoot, pathImpl = path) {
    return function ignored(filePath) {
        const rel = relativeTo(projectRoot, filePath, pathImpl);

        if (rel === null || rel === '') {
            return false;
        }

        const segments = rel.split('/');

        if (segments.some((s) => s.startsWith('.') && s !== '.' && s !== '..')) {
            return true;
        }
        if (segments.includes('node_modules')) {
            return true;
        }
        if (segments[0] === 'vendor') {
            return true;
        }
        if (segments[0] === 'storage' && (segments[1] === 'cache' || segments[1] === 'logs')) {
            return true;
        }

        return false;
    };
}

/**
 * Classify a watcher event into a reload action.
 *
 *   change/add *.css → inject-css (no page reload)
 *   unlink (any)     → reload (a removed stylesheet cannot be injected)
 *   anything else    → reload
 *
 * @param  {string} event     chokidar event name (add|change|unlink)
 * @param  {string} filePath
 * @returns {{ action: string, file?: string }}
 */
function classify(event, filePath) {
    const ext = path.extname(toPosix(filePath)).toLowerCase();

    if (event !== 'unlink' && ext === '.css') {
        return { action: 'inject-css', file: path.posix.basename(toPosix(filePath)) };
    }

    return { action: 'reload' };
}

/**
 * Collapse burst events into one flush per debounce window.
 *
 * Within a window:
 *   - any reload wins → a single { action: 'reload' }
 *   - otherwise every distinct CSS file changed is injected
 *     (previously only the first one was)
 *
 * @param  {(actions: Array<{action: string, file?: string}>) => void} onFlush
 * @param  {number} debounceMs
 * @param  {{ setTimeout: Function, clearTimeout: Function }} [timers]
 * @returns {{ schedule: (action: {action: string, file?: string}) => void, flush: () => void }}
 */
function createBatcher(onFlush, debounceMs, timers = { setTimeout, clearTimeout }) {
    let timer      = null;
    let reload     = false;
    let cssFiles   = new Set();

    function flush() {
        timer = null;

        const actions = reload
            ? [{ action: 'reload' }]
            : Array.from(cssFiles, (file) => ({ action: 'inject-css', file }));

        reload   = false;
        cssFiles = new Set();

        if (actions.length > 0) {
            onFlush(actions);
        }
    }

    function schedule(action) {
        if (action.action === 'reload') {
            reload = true;
        } else if (action.action === 'inject-css' && action.file) {
            cssFiles.add(action.file);
        }

        if (timer !== null) {
            timers.clearTimeout(timer);
        }
        timer = timers.setTimeout(flush, debounceMs);
    }

    return { schedule, flush };
}

/**
 * Whether a WebSocket handshake Origin is allowed.
 *
 * Only pages served from a loopback host (or the configured dev host) may
 * connect. A missing Origin (non-browser client) is allowed — browsers
 * always send one, which is what the check protects against.
 *
 * @param  {string|undefined} origin
 * @param  {string[]} [extraHosts]  e.g. the host passed to `luany dev`
 * @returns {boolean}
 */
function isAllowedOrigin(origin, extraHosts = []) {
    if (origin === undefined || origin === null || origin === '') {
        return true;
    }

    let url;
    try {
        url = new URL(origin);
    } catch (e) {
        return false;
    }

    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return false;
    }

    const host    = url.hostname.toLowerCase();
    const allowed = LOOPBACK_HOSTS.concat(
        extraHosts.filter(Boolean).map((h) => String(h).toLowerCase())
    );

    return allowed.includes(host) || host.endsWith('.localhost');
}

module.exports = {
    WATCH_GLOBS,
    toPosix,
    buildWatchPaths,
    relativeTo,
    createIgnored,
    classify,
    createBatcher,
    isAllowedOrigin,
};
