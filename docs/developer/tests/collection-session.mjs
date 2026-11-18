/** @brief Verify that login changes cannot silently redirect saves or discard drafts. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/collections.js', import.meta.url), 'utf8');
const boardKey = 'com_audioarchive.soundboard.v1';
const recordingKey = 'com_audioarchive.soundboard.recordings.v1';
const playlistKey = 'com_audioarchive.playlists.v1';
let serial = 0;
/** @brief Create an isolated adapter with independently changing server identity. */
async function setup(backend, userId)
{
    let remote = {backend, userId, token: 'csrf', revision: 4, boards: [{id: 'board', name: 'Saved', items: []}], defaultBoard: 'board', playlists: [], recordings: []};
    const posts = [], writes = [], handlers = {};
    let offline = false, race = false;
    globalThis.localStorage = {getItem: () => null, setItem: (...args) => writes.push(args)};
    globalThis.sessionStorage = {getItem: () => null, setItem() {}};
    globalThis.window = {location: {origin: 'https://example.test', href: 'https://example.test/soundboard'}, addEventListener: (name, fn) => {handlers[name] = fn;}};
    globalThis.document = {visibilityState: 'visible', querySelector: () => null, body: {prepend() {}}, addEventListener() {}, createElement: () => ({setAttribute() {}, classList: {toggle() {}}})};
    globalThis.fetch = async (url, options) =>
    {
        if (offline) throw new Error('Offline');
        if (!options.body) return {ok: true, json: async () => ({success: true, data: remote})};
        const payload = JSON.parse(options.body.get('payload'));
        posts.push(payload);
        if (race) return {ok: false, json: async () => ({success: false, sessionChanged: true, message: 'Login changed'})};
        assert.equal(payload.userId, remote.userId);
        assert.equal(payload.revision, 4, 'identity checks must not adopt a newer collection revision');
        return {ok: true, json: async () => ({success: true, data: {id: 'board', state: {...remote, revision: 5, boards: [{id: 'board', items: payload.board.items}]}}})};
    };
    const {Collections} = await import('data:text/javascript;base64,' + Buffer.from(source + '\n// session test ' + serial++).toString('base64'));
    await Collections.ready();
    return {api: Collections, posts, writes, handlers, change: (changes) => {remote = {...remote, ...changes};}, offline: () => {offline = true;}, race: () => {race = true;}};
}
for (const [backend, userId, nextBackend, nextUser] of [['browser', 0, 'server', 7], ['server', 7, 'browser', 0], ['server', 7, 'server', 8]])
{
    const env = await setup(backend, userId);
    env.change({backend: nextBackend, userId: nextUser});
    for (const [key, draft] of [[boardKey, [{id: 1}]], [recordingKey, [{id: 'song', events: []}]], [playlistKey, {selectedId: 'list', playlists: [{id: 'list', name: 'Draft', items: []}]}]])
    {
        const before = JSON.stringify(draft);
        assert.equal(await env.api.write(key, draft), false);
        assert.equal(JSON.stringify(draft), before, 'identity mismatch preserves exportable drafts');
    }
    assert.equal(env.posts.length, 0);
    assert.equal(env.writes.length, 0, 'login changes never silently create browser copies');
    let warned = false;
    env.handlers.beforeunload({preventDefault: () => {warned = true;}});
    assert.equal(warned, true);
}
let env = await setup('browser', 0);
assert.equal(await env.api.write(boardKey, [{id: 1}]), true);
assert.equal(env.writes.length, 1, 'unchanged guest retains browser storage');
env = await setup('server', 7);
env.change({revision: 99});
assert.equal(await env.api.write(boardKey, [{id: 1}]), true);
assert.equal(env.posts.length, 1);
env = await setup('server', 7);
env.offline();
const draft = [{id: 2}];
assert.equal(await env.api.write(boardKey, draft), false);
assert.deepEqual(draft, [{id: 2}]);
assert.equal(env.posts.length + env.writes.length, 0);
env = await setup('browser', 0);
env.change({backend: 'server', userId: 7});
assert.equal(await env.api.refresh(), false, 'refresh checks guest identity too');
assert.equal(env.api.backend, 'browser', 'refresh does not change the owner of an existing draft');
env = await setup('server', 7);
env.race();
assert.equal(await env.api.write(boardKey, draft), false);
assert.deepEqual(draft, [{id: 2}], 'server-side race rejection preserves drafts');
assert.equal(await env.api.write(boardKey, draft), false);
assert.equal(env.posts.length, 1, 'detected identity change blocks further writes until reload');
console.log('Guest/login/logout/account-switch, unchanged identity, offline checks, refresh and server-race draft preservation passed.');
