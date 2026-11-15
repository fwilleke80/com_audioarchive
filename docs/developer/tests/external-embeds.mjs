/** @brief Exercise iframe players across origins, preview exclusion and copy analytics in Chromium. */
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFile} from 'node:fs/promises';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES ? `${process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES}/playwright` : 'playwright');
const base = new URL('../../../pkg_audioarchive/com_audioarchive/', import.meta.url);
const docs = JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', [new URL('external-embed-render.php', import.meta.url).pathname], {encoding: 'utf8'}));
const social = await readFile(new URL('media/js/social.js', base), 'utf8');
const start = social.indexOf('function initialiseShareButtons()');
const end = social.indexOf('\n/**', start);
const css = await readFile(new URL('media/css/site.css', base), 'utf8');
const wav = Buffer.alloc(44 + 16000);
wav.write('RIFF', 0); wav.writeUInt32LE(wav.length - 8, 4); wav.write('WAVEfmt ', 8);
wav.writeUInt32LE(16, 16); wav.writeUInt16LE(1, 20); wav.writeUInt16LE(1, 22);
wav.writeUInt32LE(8000, 24); wav.writeUInt32LE(16000, 28); wav.writeUInt16LE(2, 32); wav.writeUInt16LE(16, 34);
wav.write('data', 36); wav.writeUInt32LE(16000, 40);
const browser = await chromium.launch({headless: true, ...(process.env.CHROMIUM_PATH ? {executablePath: process.env.CHROMIUM_PATH} : {})});
try
{
	const context = await browser.newContext({viewport: {width: 1050, height: 850}});
	const events = [];
	const errors = [];
	await context.route('https://audio.test/**', async route =>
	{
		const url = new URL(route.request().url());
		if (url.pathname.startsWith('/media/com_audioarchive/'))
		{
			const path = url.pathname.replace('/media/com_audioarchive/', 'media/');
			await route.fulfill({body: await readFile(new URL(path, base)), contentType: path.endsWith('.js') ? 'text/javascript' : 'text/css'});
		}
		else if (url.pathname === '/embed')
		{
			await route.fulfill({body: docs[url.searchParams.get('presentation') || 'default'], contentType: 'text/html'});
		}
		else if (route.request().method() === 'POST')
		{
			events.push(url.searchParams.get('task'));
			await route.fulfill({json: {success: true}});
		}
		else if (url.pathname.endsWith('.wav'))
		{
			await route.fulfill({body: wav, contentType: 'audio/wav'});
		}
		else
		{
			await route.fulfill({json: {peaks: [0.2, 0.5, 1, 0.3], duration: 1, channels: 1}});
		}
	});
	await context.route('https://other.test/**', route => route.fulfill({contentType: 'text/html', body: `<iframe title="External player" width="600" height="500" src="https://audio.test/embed?presentation=default"></iframe>`}));
	const page = await context.newPage();
	page.on('pageerror', error => errors.push(error.message));
	await page.goto('https://other.test/');
	for (const mode of ['minimal', 'compact', 'default', 'featured'])
	{
		await page.locator('iframe').evaluate((frame, mode) => frame.src = `https://audio.test/embed?presentation=${mode}`, mode);
		const frame = page.frameLocator('iframe');
		await frame.locator(`[data-player-presentation="${mode}"] [data-audioarchive-custom-ui]`).waitFor({state: 'visible'});
		await frame.locator('[data-audioarchive-custom-toggle]').click();
		await page.waitForTimeout(120);
		assert.equal(events.filter(value => value === 'embed.countPlay').length, ['minimal', 'compact', 'default', 'featured'].indexOf(mode) + 1);
		await frame.locator('[data-audioarchive-custom-toggle]').click();
		await frame.locator('[data-audioarchive-custom-toggle]').click();
		await page.waitForTimeout(80);
		assert.equal(events.filter(value => value === 'embed.countPlay').length, ['minimal', 'compact', 'default', 'featured'].indexOf(mode) + 1);
	}
	await page.goto('https://audio.test/');
	await page.setContent(`<style>${css}</style>${docs.share}`);
	await page.addScriptTag({content: `
	window.copySucceeds = true;
	const copyText = async value => {window.copied = value; return window.copySucceeds;};
	const setShareMenuOpen = () => {};
	const closeShareMenus = () => {};
	const initialiseShareMenuKeyboard = () => {};
	${social.slice(start, end)}
	initialiseShareButtons();`});
	await page.locator('[data-audioarchive-share-popover]').evaluate(element => element.hidden = false);
	await page.locator('[data-embed-open]').click();
	await page.locator('[data-embed-presentation]').selectOption('featured');
	const code = await page.locator('[data-embed-code]').inputValue();
	assert.match(code, /presentation=featured/);
	assert.doesNotMatch(code, /preview=/);
	assert.match(code, /&quot;quoted&quot;/);
	const preview = page.frameLocator('[data-embed-preview]');
	await preview.locator('[data-audioarchive-custom-toggle]').click();
	await page.waitForTimeout(120);
	assert.equal(events.filter(value => value === 'embed.countPlay').length, 4);
	assert.equal(events.includes('embed.created'), false);
	await page.locator('[data-embed-copy]').click();
	await page.waitForTimeout(120);
	assert.equal(events.filter(value => value === 'embed.created').length, 1);
	await page.evaluate(() => window.copySucceeds = false);
	await page.locator('[data-embed-copy]').click();
	await page.waitForTimeout(80);
	assert.equal(events.filter(value => value === 'embed.created').length, 1);
	if (process.env.EMBED_SCREENSHOT)
	{
		await page.screenshot({path: process.env.EMBED_SCREENSHOT});
	}
	await page.locator('[data-embed-close]').click();
	assert.equal(await page.locator('[data-embed-preview]').getAttribute('src'), null);
	assert.deepEqual(errors, []);
	console.log('All four cross-origin player layouts, playback deduplication, preview exclusion, escaped code and copy-success analytics passed.');
}
finally
{
	await browser.close();
}
