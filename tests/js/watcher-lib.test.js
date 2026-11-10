'use strict';

/**
 * Unit tests for src/Resources/dev/watcher-lib.js (Node's built-in runner).
 * Run with: node --test tests/js/
 * Also executed from PHPUnit by tests/Dev/WatcherScriptTest.php.
 */

const test   = require('node:test');
const assert = require('node:assert/strict');
const path   = require('path');
const lib    = require('../../src/Resources/dev/watcher-lib');

test('watch globs use forward slashes for Windows roots', () => {
    const globs = lib.buildWatchPaths('C:\\Users\\dev\\my-app\\');

    assert.ok(globs.length > 0);
    globs.forEach((g) => {
        assert.ok(!g.includes('\\'), `backslash in ${g}`);
        assert.ok(g.startsWith('C:/Users/dev/my-app/'), g);
    });
    assert.ok(globs.includes('C:/Users/dev/my-app/views/**/*.lte'));
});

test('watch globs for POSIX roots', () => {
    assert.ok(lib.buildWatchPaths('/srv/app').includes('/srv/app/app/**/*.php'));
});

test('hidden-file ignore is relative to project root (dotted parent dirs)', () => {
    const ignored = lib.createIgnored('/home/dev/.projects/app', path.posix);

    assert.equal(ignored('/home/dev/.projects/app/views/home.lte'), false);
    assert.equal(ignored('/home/dev/.projects/app/views/.home.lte.swp'), true);
    assert.equal(ignored('/home/dev/.projects/app/.git/HEAD'), true);
    assert.equal(ignored('/home/dev/.projects/app'), false);
});

test('vendor ignore only applies to the top-level vendor directory', () => {
    const ignored = lib.createIgnored('/srv/vendor/app', path.posix);

    assert.equal(ignored('/srv/vendor/app/app/Models/Post.php'), false);
    assert.equal(ignored('/srv/vendor/app/public/assets/vendor/lib.css'), false);
    assert.equal(ignored('/srv/vendor/app/app/Services/VendorService.php'), false);
    assert.equal(ignored('/srv/vendor/app/vendor/luany/core/src/X.php'), true);
});

test('node_modules and storage cache/logs are ignored', () => {
    const ignored = lib.createIgnored('/srv/app', path.posix);

    assert.equal(ignored('/srv/app/public/assets/node_modules/x.js'), true);
    assert.equal(ignored('/srv/app/storage/cache/views/a.php'), true);
    assert.equal(ignored('/srv/app/storage/logs/app.log'), true);
    assert.equal(ignored('/srv/app/storage/app/file.txt'), false);
});

test('ignore rules work with Windows paths', () => {
    const ignored = lib.createIgnored('C:\\Users\\dev\\.config\\app', path.win32);

    assert.equal(ignored('C:\\Users\\dev\\.config\\app\\views\\home.lte'), false);
    assert.equal(ignored('C:\\Users\\dev\\.config\\app\\vendor\\autoload.php'), true);
    assert.equal(ignored('C:/Users/dev/.config/app/views/home.lte'), false);
});

test('classify: css change injects, other files and unlink reload', () => {
    assert.deepEqual(lib.classify('change', '/srv/app/public/assets/css/app.css'), { action: 'inject-css', file: 'app.css' });
    assert.deepEqual(lib.classify('add', 'C:\\app\\public\\assets\\x.CSS'), { action: 'inject-css', file: 'x.CSS' });
    assert.deepEqual(lib.classify('change', '/srv/app/views/home.lte'), { action: 'reload' });
    assert.deepEqual(lib.classify('unlink', '/srv/app/public/assets/css/app.css'), { action: 'reload' });
    assert.deepEqual(lib.classify('unlink', '/srv/app/app/Models/Post.php'), { action: 'reload' });
});

/** Fake timers so the batcher can be driven synchronously. */
function fakeTimers() {
    let pending = null;
    return {
        setTimeout: (fn) => { pending = fn; return 1; },
        clearTimeout: () => { pending = null; },
        run: () => { const fn = pending; pending = null; if (fn) fn(); },
    };
}

test('batcher injects every CSS file changed within the window', () => {
    const timers  = fakeTimers();
    const flushed = [];
    const b = lib.createBatcher((a) => flushed.push(a), 40, timers);

    b.schedule({ action: 'inject-css', file: 'app.css' });
    b.schedule({ action: 'inject-css', file: 'admin.css' });
    b.schedule({ action: 'inject-css', file: 'app.css' });
    timers.run();

    assert.deepEqual(flushed, [[
        { action: 'inject-css', file: 'app.css' },
        { action: 'inject-css', file: 'admin.css' },
    ]]);
});

test('batcher: reload beats css regardless of order', () => {
    const timers  = fakeTimers();
    const flushed = [];
    const b = lib.createBatcher((a) => flushed.push(a), 40, timers);

    b.schedule({ action: 'reload' });
    b.schedule({ action: 'inject-css', file: 'app.css' });
    timers.run();

    assert.deepEqual(flushed, [[{ action: 'reload' }]]);
});

test('batcher resets between windows', () => {
    const timers  = fakeTimers();
    const flushed = [];
    const b = lib.createBatcher((a) => flushed.push(a), 40, timers);

    b.schedule({ action: 'reload' });
    timers.run();
    b.schedule({ action: 'inject-css', file: 'a.css' });
    timers.run();

    assert.deepEqual(flushed, [[{ action: 'reload' }], [{ action: 'inject-css', file: 'a.css' }]]);
});

test('origin check allows localhost pages only', () => {
    assert.equal(lib.isAllowedOrigin('http://localhost:8000'), true);
    assert.equal(lib.isAllowedOrigin('http://127.0.0.1:8080'), true);
    assert.equal(lib.isAllowedOrigin('http://[::1]:8000'), true);
    assert.equal(lib.isAllowedOrigin('https://app.localhost'), true);
    assert.equal(lib.isAllowedOrigin(undefined), true);

    assert.equal(lib.isAllowedOrigin('https://evil.example'), false);
    assert.equal(lib.isAllowedOrigin('http://localhost.evil.example'), false);
    assert.equal(lib.isAllowedOrigin('null'), false);
    assert.equal(lib.isAllowedOrigin('file://'), false);
});

test('origin check allows the configured dev host', () => {
    assert.equal(lib.isAllowedOrigin('http://my-app.test:8000', ['my-app.test']), true);
    assert.equal(lib.isAllowedOrigin('http://other.test:8000', ['my-app.test']), false);
});
