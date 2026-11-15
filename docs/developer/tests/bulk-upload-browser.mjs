/** @brief Exercise the production bulk queue in Chromium with mocked upload/analysis responses. */
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES ? `${process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES}/playwright` : 'playwright');
const base = new URL('../../../pkg_audioarchive/com_audioarchive/', import.meta.url);
const template = await readFile(new URL('administrator/tmpl/upload/default.php', base), 'utf8');
const strings = await readFile(new URL('administrator/language/en-GB/com_audioarchive.ini', base), 'utf8');
const translations = Object.fromEntries([...strings.matchAll(/^([A-Z0-9_]+)="(.*)"$/gm)].map((match) => [match[1], match[2]]));
let html = template.slice(template.indexOf('<form'));
html = html.replace(/<\?php echo Text::_\('([^']+)'\); \?>/g, (_, key) => translations[key] || key).replace(/<\?php[\s\S]*?\?>/g, '');
html = html.replace('data-upload-endpoint=""', 'data-upload-endpoint="https://audio.test/upload"').replace('data-analysis-endpoint=""', 'data-analysis-endpoint="https://audio.test/analysis"').replace('data-token-name=""', 'data-token-name="csrf"');
const css = await readFile(new URL('media/css/admin.css', base), 'utf8');
const browser = await chromium.launch({headless: true, ...(process.env.CHROMIUM_PATH ? {executablePath: process.env.CHROMIUM_PATH} : {})});
try
{
    const page = await browser.newPage({viewport: {width: 1200, height: 900}});
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    let calls = [];
    await page.route('https://audio.test/**', async (route) =>
    {
        if (route.request().url().endsWith('/analysis'))
        {
            const body = new URLSearchParams(route.request().postData());
            assert.equal(body.get('csrf'), '1');
            const id = body.get('clip_id');
            calls.push(id);
            await route.fulfill({json: id === '2' ? {success: false, message: 'Analysis unavailable'} : {success: true, data: {processed: true, success: true, remaining: calls.length === 1 ? 1 : 0, message: 'Analysis created'}}});
        }
        else
        {
            await route.fulfill({contentType: 'text/html', body: `<style>[hidden],.d-none{display:none!important}table{width:100%}td,th{padding:8px}.progress{background:#eee}.progress-bar{background:#2985d0}.table-success{background:#e1f1e6}${css}</style>${html}`});
        }
    });
    await page.goto('https://audio.test/');
    await page.evaluate((translations) =>
    {
        window.Joomla = {Text: {_: (key, fallback) => translations[key] || fallback}};
        window.fileChooserClicks = 0;
        document.getElementById('audioarchive-bulk-upload-files').click = () => { window.fileChooserClicks++; };
        window.XMLHttpRequest = class
        {
            constructor()
            {
                this.handlers = {};
                this.upload = {addEventListener: (name, callback) => { this.progress = callback; }};
            }
            open() {}
            addEventListener(name, callback) { this.handlers[name] = callback; }
            send(data)
            {
                const filename = data.get('jform[audio_file]').name;
                this.progress({lengthComputable: true, loaded: 100, total: 100});
                this.response = filename === 'failed.wav' ? {success: false, message: 'Upload rejected'} : {success: true, data: {clip_id: filename === 'first.wav' ? 1 : 2, title: filename, edit_url: '#edit'}};
                this.handlers.load();
            }
        };
    }, translations);
    await page.addScriptTag({content: await readFile(new URL('media/js/bulk-upload.js', base), 'utf8')});
    assert.equal(await page.locator('#audioarchive-bulk-upload-clear').isVisible(), false);
    await page.locator('#audioarchive-bulk-upload-files').setInputFiles(['first.wav', 'second.wav', 'failed.wav'].map((name) => ({name, mimeType: 'audio/wav', buffer: Buffer.from('fixture')})));
    assert.equal(await page.locator('#audioarchive-bulk-upload-dropzone tbody tr').count(), 3);
    const controls = await page.locator('#audioarchive-bulk-upload-select').boundingBox();
    const drop = await page.locator('#audioarchive-bulk-upload-dropzone').boundingBox();
    assert.ok(controls.y < drop.y, 'controls precede combined queue');
    await page.locator('#audioarchive-bulk-upload-start').click();
    await page.waitForFunction(() => !document.getElementById('audioarchive-bulk-upload-select').disabled);
    assert.deepEqual(calls, ['1', '1', '2']);
    const rows = page.locator('#audioarchive-bulk-upload-queue tr');
    assert.match(await rows.nth(0).innerText(), /2 processed, 0 failed/);
    assert.match(await rows.nth(1).innerText(), /Analysis unavailable/);
    assert.match(await rows.nth(2).innerText(), /Upload rejected/);
    await rows.nth(0).locator('a').click();
    assert.equal(await page.evaluate(() => window.fileChooserClicks), 0, 'row actions must not open file chooser');
    await page.locator('#audioarchive-bulk-upload-clear').click();
    assert.equal(await rows.count(), 2, 'failed analyses/uploads survive clear finished');
    assert.equal(await page.locator('#audioarchive-bulk-upload-clear').isVisible(), false);
    assert.deepEqual(errors, []);
    console.log('Bulk queue: combined drop target, progress, per-file analysis, errors, clear and row actions passed.');
}
finally
{
    await browser.close();
}
