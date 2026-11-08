// Renders public/favicon.svg to the PNG sizes browsers and iOS ask for, and
// packs the small ones into public/favicon.ico. Needs Chromium in the PHP
// container, see scripts/screenshots/capture.mjs.
//
//   docker exec -w /var/www/html/scripts/favicon evd-php node render.mjs
import { writeFile } from 'node:fs/promises';
import { open, send, sleep, close } from '../screenshots/cdp.mjs';

const PUBLIC = '/var/www/html/public';
const sizes = { 16: null, 32: null, 48: null, 180: 'apple-touch-icon.png' };
const png = {};

await send('Emulation.setDefaultBackgroundColorOverride', { color: { r: 0, g: 0, b: 0, a: 0 } });

for (const [size, file] of Object.entries(sizes)) {
    const n = Number(size);
    await send('Emulation.setDeviceMetricsOverride', { width: n, height: n, deviceScaleFactor: 1, mobile: false });
    await open('/favicon.svg', 300);

    // iOS rounds a touch icon itself and fills transparent corners with black.
    if (file === 'apple-touch-icon.png') {
        await send('Runtime.evaluate', { expression: `document.querySelector('rect').setAttribute('rx', '0')` });
    }

    await sleep(200);
    const { data } = await send('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width: n, height: n, scale: 1 } });
    png[n] = Buffer.from(data, 'base64');

    if (file) {
        await writeFile(`${PUBLIC}/${file}`, png[n]);
        console.log(`${file} ${n}x${n}`);
    }
}

// An ICO is a directory of images; modern ones may hold PNG data as is.
const icons = [16, 32, 48];
const header = Buffer.alloc(6 + 16 * icons.length);
header.writeUInt16LE(0, 0);
header.writeUInt16LE(1, 2);
header.writeUInt16LE(icons.length, 4);

let offset = header.length;
icons.forEach((n, i) => {
    const entry = 6 + 16 * i;
    header.writeUInt8(n, entry);
    header.writeUInt8(n, entry + 1);
    header.writeUInt16LE(1, entry + 4);
    header.writeUInt16LE(32, entry + 6);
    header.writeUInt32LE(png[n].length, entry + 8);
    header.writeUInt32LE(offset, entry + 12);
    offset += png[n].length;
});

await writeFile(`${PUBLIC}/favicon.ico`, Buffer.concat([header, ...icons.map((n) => png[n])]));
console.log(`favicon.ico ${icons.join(', ')}`);

close();
