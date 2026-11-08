// Drives headless Chromium over the DevTools protocol: sign in, act on pages, capture them.
import { spawn } from 'node:child_process';

const BASE = 'http://nginx';
const OUT = '/var/www/html/docs/screenshots';
const chrome = spawn('chromium-browser', ['--headless=new', '--no-sandbox', '--disable-gpu', '--hide-scrollbars', '--remote-debugging-port=9222', `--user-data-dir=/tmp/shots-profile-${Date.now()}`, 'about:blank'], { stdio: 'ignore' });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

let target;
for (let i = 0; i < 50 && ! target; i++) {
    try { target = (await (await fetch('http://127.0.0.1:9222/json/list')).json()).find((t) => t.type === 'page'); } catch {}
    if (! target) await sleep(200);
}

const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((r) => ws.addEventListener('open', r));
let id = 0;
const pending = new Map();
const listeners = [];
ws.addEventListener('message', (event) => {
    const msg = JSON.parse(event.data);
    if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
    if (msg.method) listeners.forEach((l) => l(msg));
});
const send = (method, params = {}) => new Promise((resolve, reject) => {
    const n = ++id;
    pending.set(n, (msg) => msg.error ? reject(new Error(method + ': ' + msg.error.message)) : resolve(msg.result));
    ws.send(JSON.stringify({ id: n, method, params }));
});
const evaluate = async (expression) => (await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })).result.value;

await send('Page.enable');
await send('Network.enable');
// Headless Chromium names itself HeadlessChrome, which IgnoreCrawlers rightly refuses.
await send('Network.setUserAgentOverride', { userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', acceptLanguage: 'nl-NL,nl;q=0.9,en;q=0.8' });
await send('Network.setExtraHTTPHeaders', { headers: { 'Accept-Language': 'nl-NL,nl;q=0.9,en;q=0.8' } });

export async function open(path, settle = 1500) {
    const loaded = new Promise((r) => listeners.push(function once(msg) { if (msg.method === 'Page.loadEventFired') { listeners.splice(listeners.indexOf(once), 1); r(); } }));
    await send('Page.navigate', { url: BASE + path });
    await Promise.race([loaded, sleep(30000).then(() => console.log(`no load event for ${path} after 30s`))]);
    await sleep(settle);
}

// Debugbar's toolbar and footer note are development noise in a screenshot.
const tidy = `(() => {
    document.querySelectorAll('.phpdebugbar, .phpdebugbar-openhandler, .phpdebugbar-openhandler-overlay').forEach((el) => el.remove());
    document.querySelectorAll('footer p').forEach((p) => p.textContent = p.textContent.split(', with Debugbar')[0]);
    document.querySelectorAll('details[open]').forEach((d) => d.open = false);
    return true;
})()`;

export async function shot(name, { width = 1440, height = null, maxHeight = 2600, from = 0 } = {}) {
    await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 600 });
    await sleep(400);
    await evaluate(tidy);
    const full = await evaluate('document.documentElement.scrollHeight');
    const h = Math.min(height ?? full, maxHeight, full - from);
    const { data } = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: from, width, height: h, scale: 1 } });
    const { writeFile } = await import('node:fs/promises');
    await writeFile(`${OUT}/${name}.png`, Buffer.from(data, 'base64'));
    console.log(`${name}.png ${width}x${h} (page ${full}px)`);
}

export { evaluate, sleep, send };
export const close = () => { ws.close(); chrome.kill(); };
