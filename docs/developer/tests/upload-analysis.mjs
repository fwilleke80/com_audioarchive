/** @brief Verify incremental upload processing, CSRF, resume state and visible failures. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
globalThis.document = {readyState: 'complete', querySelectorAll: () => []};
const navigation = [];
globalThis.window = {setTimeout: (callback) => callback(), location: {assign: (url) => navigation.push(url), reload: () => navigation.push('reload')}};
const source = await readFile(new URL('../../../pkg_audioarchive/com_audioarchive/media/js/upload-analysis.js', import.meta.url), 'utf8');
const {processUploads} = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
function root(ids, destination = '')
{
	const status = {textContent: ''};
	const retry = {hidden: true};
	return {
		dataset: {ids: JSON.stringify(ids), endpoint: '/process', token: 'csrf', return: destination, error: 'Error', failed: 'Failed', complete: 'Done'},
		querySelector: (selector) => selector === '[data-processing-status]' ? status : retry,
		classList: {toggle() {}}, status, retry,
	};
}
let requests = [];
let responses = [];
globalThis.fetch = async (url, options) =>
{
	requests.push(options.body.get('id'));
	assert.equal(options.body.get('csrf'), '1');
	assert.equal(options.method, 'POST');
	const data = responses.shift();
	if (!data)
	{
		throw new Error('Network');
	}
	return {ok: true, json: async () => ({success: true, data})};
};
let element = root([1,2], '/my-clips');
responses = [{done: false, processed: true, failed: false}, {done: true, processed: true, failed: false}, {done: true, processed: true, failed: false}];
await processUploads(element);
assert.deepEqual(requests, ['1','1','2']);
assert.equal(element.dataset.ids, '[]');
assert.deepEqual(navigation, ['/my-clips']);
element = root([3,4]);
requests = [];
responses = [{done: true, processed: true, failed: false}];
await processUploads(element);
assert.equal(element.dataset.ids, '[4]', 'failed request preserves remaining IDs');
assert.equal(element.retry.hidden, false);
responses = [{done: true, processed: true, failed: false}];
await processUploads(element);
assert.deepEqual(requests, ['3','4','4'], 'retry does not restart completed clip');
assert.equal(navigation.at(-1), 'reload');
element = root([5], '/finish');
responses = [{done: true, processed: true, failed: true}];
await processUploads(element);
assert.equal(element.status.textContent, 'Failed');
assert.notEqual(navigation.at(-1), '/finish', 'failure remains visible instead of silently redirecting');
assert.equal(element.dataset.busy, '0');
console.log('Frontend upload processing/resume/error assertions passed.');
