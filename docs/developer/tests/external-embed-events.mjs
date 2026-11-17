/** @brief Execute production embed handlers with deterministic DOM/event adapters. */
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const base = new URL('../../../pkg_audioarchive/com_audioarchive/media/js/', import.meta.url);
const playerEvents = await readFile(new URL('embed.js', base), 'utf8');
for (const preview of [false, true])
{
	const handlers = {};
	const calls = [];
	const root = {dataset: {embedEventUrl: '/countPlay', embedToken: 'signed-play'}, querySelector: () => ({addEventListener: (name, fn) => handlers[name] = fn})};
	vm.runInNewContext(playerEvents, {document: {querySelector: () => root}, URL, URLSearchParams,
		window: {location: {href: `https://audio.test/?preview=${preview ? '1' : '0'}`}},
		fetch: async (...args) => calls.push(args)});
	assert.equal(calls.length, 0, 'Loading never counts as playback');
	handlers.playing();
	handlers.playing();
	assert.equal(calls.length, preview ? 0 : 1, 'Count once, excluding previews');
	if (!preview)
	{
		assert.equal(calls[0][1].body.get('embed_token'), 'signed-play');
	}
}
/** @brief Create an event adapter with observable values. */
function element()
{
	return {dataset: {}, events: {}, value: '', textContent: '', style: {},
		addEventListener(name, callback)
		{
			this.events[name] = callback;
		},
		focus() {},
		removeAttribute(name)
		{
			delete this[name];
		},
		showModal()
		{
			this.open = true;
		},
		close()
		{
			this.open = false;
			this.events.close();
		},
	};
}
const fields = Object.fromEntries(['presentation', 'code', 'preview', 'feedback', 'close', 'copy', 'title', 'links', 'link-title'].map(key => [`[data-embed-${key}]`, element()]));
fields['[data-embed-presentation]'].value = 'default';
fields['[data-embed-link-title]'].checked = true;
fields['[data-embed-title]'].checked = true;
fields['[data-embed-links]'].checked = true;
const dialog = element();
dialog.dataset = {embedUrl: 'https://audio.test/embed?id=42', eventUrl: '/created', eventToken: 'signed-copy', copiedLabel: 'Copied'};
dialog.querySelector = key => fields[key];
const open = element();
const toggle = element();
const menu = {dataset: {shareTitle: 'Title', copyFailedLabel: 'Failed'}, querySelector: key => ({'[data-embed-dialog]': dialog, '[data-embed-open]': open})[key] || null};
const source = await readFile(new URL('social.js', base), 'utf8');
const begin = source.indexOf('    const initialiseEmbedDialog =');
const finish = source.indexOf("\n\tdocument.querySelectorAll('[data-audioarchive-share-menu]')", begin);
const calls = [];
let copied = '';
let succeed = true;
vm.runInNewContext(source.slice(begin, finish) + '\ninitialiseEmbedDialog(menu, toggle);', {
	menu, toggle, URL, URLSearchParams, Object, window: {location: {href: 'https://audio.test/'}},
	document: {createElement: () => ({style: {}, get outerHTML()
	{
		return JSON.stringify({src: this.src, height: this.height, title: this.title});
	}})},
	setShareMenuOpen() {},
	copyText: async text =>
	{
		copied = text;
		return succeed;
	},
	fetch: async (...args) => calls.push(args),
});
open.events.click();
assert.equal(dialog.open, true);
assert.equal(calls.length, 0);
for (const mode of ['minimal', 'compact', 'default', 'featured'])
{
	fields['[data-embed-presentation]'].value = mode;
	fields['[data-embed-presentation]'].events.change();
	const code = JSON.parse(fields['[data-embed-code]'].value);
	assert.equal(new URL(code.src).searchParams.get('presentation'), mode);
	assert.equal(new URL(code.src).searchParams.has('preview'), false);
	assert.equal(new URL(fields['[data-embed-preview]'].src).searchParams.get('preview'), '1');
}
fields['[data-embed-link-title]'].checked = false;
fields['[data-embed-link-title]'].events.change();
assert.equal(new URL(JSON.parse(fields['[data-embed-code]'].value).src).searchParams.get('link_title'), '0');
fields['[data-embed-title]'].checked = false;
fields['[data-embed-links]'].checked = false;
fields['[data-embed-links]'].events.change();
let codeUrl = new URL(JSON.parse(fields['[data-embed-code]'].value).src);
assert.equal(fields['[data-embed-link-title]'].disabled, true);
assert.equal(codeUrl.searchParams.get('title'), '0');
assert.equal(codeUrl.searchParams.get('links'), '0');
assert.equal(new URL(fields['[data-embed-preview]'].src).searchParams.get('links'), '0');
fields['[data-embed-title]'].checked = true;
fields['[data-embed-title]'].events.change();
codeUrl = new URL(JSON.parse(fields['[data-embed-code]'].value).src);
assert.equal(codeUrl.searchParams.has('title'), false);
assert.equal(fields['[data-embed-link-title]'].disabled, false);
assert.equal(codeUrl.searchParams.get('link_title'), '0');
assert.equal(codeUrl.searchParams.get('links'), '0');
await fields['[data-embed-copy]'].events.click();
assert.equal(calls.length, 1);
assert.equal(calls[0][1].body.get('embed_token'), 'signed-copy');
assert.equal(copied, fields['[data-embed-code]'].value);
succeed = false;
await fields['[data-embed-copy]'].events.click();
assert.equal(calls.length, 1, 'Failed clipboard writes never emit creation');
assert.equal(fields['[data-embed-feedback]'].textContent, 'Failed');
fields['[data-embed-close]'].events.click();
assert.equal(fields['[data-embed-preview]'].src, undefined, 'Closing unloads preview audio');
console.log('Embed presentation URLs, preview exclusion, once-per-frame playback and successful-copy events passed.');
