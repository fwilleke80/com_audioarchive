/** @brief Verify ticket POST, duplicate-click prevention, error recovery and origin checks. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source = fs.readFileSync(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/download.js', import.meta.url), 'utf8');
let listener;
let resolveFetch;
let request;
let destination;
const status = {textContent: ''};
const button =
{
    disabled: false,
    dataset: {downloadEndpoint: '/ticket', downloadProof: 'proof', downloadBusy: 'Preparing', downloadError: 'Failed'},
    closest: () => ({querySelector: () => status}),
    setAttribute() {},
    removeAttribute() {},
};
vm.runInNewContext(source,
{
    document: {addEventListener: (name, callback) => { listener = callback; }},
    URL, URLSearchParams,
    window: {location: {href: 'https://example.test/clip', origin: 'https://example.test', assign: (url) => { destination = url; }}},
    fetch: (url, options) =>
    {
        request = {url, options};
        return new Promise((resolve) => { resolveFetch = resolve; });
    },
});
const event = {target: {closest: () => button}, preventDefault() {}};
let pending = listener(event);
assert.equal(button.disabled, true);
assert.equal(status.textContent, 'Preparing');
assert.equal(request.options.method, 'POST');
assert.equal(request.options.body.get('download_proof'), 'proof');
const firstRequest = request;
await listener(event);
assert.equal(request, firstRequest);
resolveFetch({ok: true, json: async () => ({success: true, url: 'https://example.test/download?ticket=123'})});
await pending;
assert.equal(destination, 'https://example.test/download?ticket=123');
assert.equal(button.disabled, false);
assert.equal(status.textContent, '');
for (const response of [
    {ok: false, json: async () => ({success: false})},
    {ok: true, json: async () => ({success: true, url: 'https://other.test/download'})},
])
{
    destination = undefined;
    pending = listener(event);
    resolveFetch(response);
    await pending;
    assert.equal(destination, undefined);
    assert.equal(status.textContent, 'Failed');
    assert.equal(button.disabled, false);
}
console.log('Download button tests passed.');
