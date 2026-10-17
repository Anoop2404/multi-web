// Prints raw and gzipped sizes of the built JS/CSS (run after `npm run build`).
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { gzipSync } from 'node:zlib';

const dir = new URL('../dist/assets/', import.meta.url).pathname;
let jsTotal = 0;
let jsGzip = 0;
const rows = [];
for (const name of readdirSync(dir)) {
    if (!/\.(js|css)$/.test(name)) continue;
    const buf = readFileSync(join(dir, name));
    const gz = gzipSync(buf, { level: 9 }).length;
    rows.push([name, statSync(join(dir, name)).size, gz]);
    if (name.endsWith('.js')) {
        jsTotal += buf.length;
        jsGzip += gz;
    }
}
const kb = (n) => (n / 1024).toFixed(1).padStart(7) + ' KB';
for (const [name, raw, gz] of rows.sort((a, b) => b[2] - a[2])) console.log(`${name.padEnd(36)} ${kb(raw)}  gzip ${kb(gz)}`);
console.log(`${'ALL JS'.padEnd(36)} ${kb(jsTotal)}  gzip ${kb(jsGzip)}  (budget 150 KB gzip)`);
if (jsGzip > 150 * 1024) process.exitCode = 1;
