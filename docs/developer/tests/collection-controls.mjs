/** @brief Exercise mounted collection controls, safe browser cleanup and active-share visibility. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/collections.js', import.meta.url), 'utf8');
class Element
{
    children = [];
    events = {};
    hidden = false;
    classList = {toggle() {}};
    setAttribute() {}
    append(...nodes)
    {
        for (const node of nodes)
        {
            node.remove();
            node.parent = this;
            this.children.push(node);
        }
    }
    prepend(node)
    {
        node.parent = this;
        this.children.unshift(node);
    }
    remove()
    {
        if (this.parent)
        {
            this.parent.children = this.parent.children.filter((node) => node !== this);
            this.parent = null;
        }
    }
    replaceChildren(...nodes)
    {
        this.children = [];
        this.append(...nodes);
    }
    addEventListener(type, handler)
    {
        this.events[type] = handler;
    }
    querySelectorAll()
    {
        return [];
    }
}
const descendants = (root) => root.children.flatMap((child) => [child, ...descendants(child)]);
let scenario = 0;
async function setup(kind, browser, mode = 'server')
{
    const key = `com_audioarchive.${kind === 'playlist' ? 'playlists' : 'soundboard'}.v1`;
    const storage = new Map([[key, JSON.stringify(browser)]]);
    let cleanupFailure = false;
    let denied = false;
    const timers = [];
    globalThis.window = {location: {href: 'https://example.test/archive', origin: 'https://example.test'}, addEventListener() {}, setTimeout: (callback) => timers.push(callback)};
    globalThis.document = {querySelector: () => null, body: new Element(), addEventListener() {}, createElement: () => new Element()};
    globalThis.Option = class extends Element
    {
        constructor(text, value)
        {
            super();
            this.textContent = text;
            this.value = value;
        }
    };
    globalThis.confirm = () => true;
    globalThis.sessionStorage = {getItem: () => null, setItem() {}};
    globalThis.localStorage = {
        getItem: (name) => storage.get(name) ?? null,
        setItem(name, value)
        {
            if (cleanupFailure) throw new Error('Storage blocked');
            storage.set(name, value);
        },
        removeItem(name)
        {
            if (cleanupFailure) throw new Error('Storage blocked');
            storage.delete(name);
        },
    };
    const state = {backend: mode, userId: 7, revision: 0, boards: [], playlists: [], token: 'csrf', sharing: true, labels: {import: 'Move', revoke: 'Revoke', import_retained: 'Retained', import_done: 'Moved'}};
    const imports = [];
    globalThis.fetch = async (url, options) =>
    {
        let data = state;
        if (options.body)
        {
            const input = JSON.parse(options.body.get('payload'));
            if (denied) return {ok: false, json: async () => ({success: false, message: 'Denied'})};
            const action = url.searchParams.get('task');
            let id = input.id;
            let skipped = 0;
            if (action.includes('import'))
            {
                imports.push(input.collection);
                skipped = input.collection.name === 'Bad' ? 1 : 0;
                id = input.collection.id || 'board';
                if (!skipped) state[kind === 'playlist' ? 'playlists' : 'boards'].push({...input.collection, id, shared: false});
            }
            else if (action.endsWith('share') || action.endsWith('revoke'))
            {
                state[kind === 'playlist' ? 'playlists' : 'boards'].find((item) => item.id === id).shared = action.endsWith('share');
            }
            state.revision++;
            data = {id, skipped, token: 'share-token', state};
        }
        return {ok: true, json: async () => ({success: true, data: structuredClone(data)})};
    };
    const {Collections} = await import('data:text/javascript;base64,' + Buffer.from(source + '\n//' + scenario++).toString('base64'));
    await Collections.ready();
    const root = new Element();
    let selected = '';
    Collections.mount(root, kind, () => selected, () => {});
    const flush = () =>
    {
        while (timers.length) timers.shift()();
    };
    return {Collections, root, key, storage, state, imports, flush, select: (id) => {selected = id; root.events.change(); flush();}, failCleanup: () => {cleanupFailure = true;}, deny: () => {denied = true;}};
}
const click = async (test, label) =>
{
    const button = descendants(test.root).find((node) => node.textContent === label);
    assert.ok(button, label);
    await button.events.click();
    test.flush();
};
const good = {id: 'good', name: 'Good', items: [{uuid: 'clip'}]};
const bad = {id: 'bad', name: 'Bad', items: [{uuid: 'missing'}]};
let test = await setup('playlist', {selectedId: 'good', playlists: [good, bad]});
assert.equal(descendants(test.root).some((node) => node.textContent === 'later'), false);
assert.equal(descendants(test.root).find((node) => node.textContent === 'Revoke').hidden, true);
await click(test, 'Move');
assert.deepEqual(JSON.parse(test.storage.get(test.key)).playlists, [bad], 'remove only acknowledged complete imports');
assert.equal(test.state.playlists.length, 1);
test.select('good');
assert.equal(descendants(test.root).find((node) => node.textContent === 'Revoke').hidden, true);
await test.Collections.shareUrl('good', '/playlist');
test.flush();
assert.equal(descendants(test.root).find((node) => node.textContent === 'Revoke').hidden, false);
await click(test, 'Revoke');
assert.equal(descendants(test.root).find((node) => node.textContent === 'Revoke').hidden, true);
test = await setup('playlist', {selectedId: 'good', playlists: [good]});
await click(test, 'Move');
assert.deepEqual(JSON.parse(test.storage.get(test.key)).playlists, []);
assert.equal(descendants(test.root).some((node) => node.textContent === 'Move'), false);
test = await setup('playlist', JSON.parse(test.storage.get(test.key)));
assert.equal(descendants(test.root).some((node) => node.textContent === 'Move'), false, 'reload does not offer completed imports');
test = await setup('soundboard', [null, {uuid: 'clip'}]);
await click(test, 'Move');
assert.equal(test.storage.has(test.key), false);
assert.deepEqual(test.state.boards[0].items, [null, {uuid: 'clip'}]);
test = await setup('playlist', {playlists: [good]});
test.deny();
await click(test, 'Move');
assert.deepEqual(JSON.parse(test.storage.get(test.key)).playlists, [good], 'server failure retains source');
test = await setup('playlist', {playlists: [good]});
test.failCleanup();
await click(test, 'Move');
assert.deepEqual(JSON.parse(test.storage.get(test.key)).playlists, [good], 'storage failure retains source and retry control');
console.log('Collection control/import cleanup assertions passed.');
