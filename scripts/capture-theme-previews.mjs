#!/usr/bin/env node
import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

// Optional source checkout lets maintainers document pending palettes without editing it.
const root = process.cwd();
const source = path.resolve(process.argv[2] ?? root);
const require = createRequire(pathToFileURL(path.join(source, 'package.json')));
const puppeteer = require('puppeteer');
const output = path.join(root, 'docs/public/images/themes');
await mkdir(output, { recursive: true });
const themes = [
  ['default', 'Default'], ['tangerine', 'Tangerine'], ['zen-inspired-theme', 'Zen Inspired Theme'],
  ['claude-blu2', 'Claude Blu 2'], ['meridian', 'Meridian'], ['bamboo-breeze', 'Bamboo Breeze'],
  ['kodama-grove', 'Kodama Grove'], ['caffeine', 'Caffeine'], ['claude', 'Claude'],
];
const appCss = await readFile(path.join(source, 'resources/css/app.css'), 'utf8');
// Only base variables: no app layout rules or Tailwind compilation in this isolated preview.
const base = [...appCss.matchAll(/(?:^|\n)(:root|\.dark)\s*\{([^}]+)\}/g)].map((match) => `${match[1]} {${match[2]}}`).join('\n');
const manifest = { viewport: { width: 1120, height: 720 }, sourceFiles: {} };
manifest.sourceFiles['resources/css/app.css'] = createHash('sha256').update(appCss).digest('hex');
const browser = await puppeteer.launch({
  headless: true,
  ...(process.env.PUPPETEER_EXECUTABLE_PATH ? { executablePath: process.env.PUPPETEER_EXECUTABLE_PATH } : {}),
});
try {
  const page = await browser.newPage();
  await page.setViewport({ ...manifest.viewport, deviceScaleFactor: 1 });
  // Reproducible offline captures. Named fonts use system fallbacks; no remote font requests.
  await page.setRequestInterception(true);
  page.on('request', (request) => request.url().startsWith('data:') ? request.continue() : request.abort());
  for (const [id, name] of themes) {
    const file = `resources/css/themes/${id}.css`;
    const raw = id === 'default' ? '' : await readFile(path.join(source, file), 'utf8');
    if (raw) manifest.sourceFiles[file] = createHash('sha256').update(raw).digest('hex');
    const css = raw.replace(/@import[^;]+;/g, '');
    for (const mode of ['light', 'dark']) {
      await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: mode }]);
      await page.setContent(`<!doctype html><html class="theme-${id} ${mode}"><head><meta charset="utf-8"><style>
${base}\n${css}
*{box-sizing:border-box}body{margin:0;background:var(--background);color:var(--foreground);font:16px/24px var(--font-sans, sans-serif)}
main{padding:40px}header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px}h1{font-size:30px;line-height:36px;margin:8px 0;font-weight:600}h2{font-size:20px;line-height:28px;margin:0 0 8px;font-weight:600}p{margin:0;color:var(--muted-foreground)}small{font-size:12px;line-height:16px}section{display:grid;grid-template-columns:1fr 1fr;gap:24px}.card{background:var(--card);color:var(--card-foreground);border:1px solid var(--border);border-radius:var(--radius,8px);padding:24px;box-shadow:var(--shadow-sm)}.tag{background:var(--secondary);color:var(--secondary-foreground);padding:8px 12px;border-radius:var(--radius,8px)}.row{display:flex;gap:12px;margin-top:24px;align-items:center}button{font:600 16px/24px var(--font-sans,sans-serif);padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius,8px);background:var(--primary);color:var(--primary-foreground)}.outline{background:var(--background);color:var(--foreground)}input{display:block;width:100%;background:var(--background);color:var(--foreground);border:1px solid var(--input);border-radius:var(--radius,8px);font:inherit;padding:8px 12px;margin-top:8px}.swatches{display:flex;gap:12px;margin-top:24px}.swatch{flex:1;height:64px;border:1px solid var(--border);border-radius:var(--radius,8px)}.bars{height:128px;display:flex;align-items:end;gap:16px;margin-top:24px}.bar{flex:1;border-radius:var(--radius,8px) var(--radius,8px) 0 0}footer{margin-top:32px;color:var(--muted-foreground);font-size:12px;line-height:16px}.accent{background:var(--accent);color:var(--accent-foreground);padding:16px;margin-top:24px;border-radius:var(--radius,8px)}
</style></head><body><main><header><div><small>KOAKADEMY / THEME STUDY</small><h1>${name}</h1><p>Color, surfaces, controls and chart tokens</p></div><span class="tag">${mode === 'light' ? 'Light' : 'Dark'} mode</span></header><section><article class="card"><h2>Personalization</h2><p>Component preview using theme CSS variables.</p><div class="swatches">${['primary','secondary','accent'].map((token) => `<div class="swatch" style="background:var(--${token})" title="${token}"></div>`).join('')}</div><div class="row"><button>Save preference</button><button class="outline">Cancel</button></div><div class="accent">Accent surface and foreground</div></article><article class="card"><h2>Learning overview</h2><p>Illustrative data, not student records.</p><div class="bars">${[48,83,64,112,96].map((height,index) => `<div class="bar" style="height:${height}px;background:var(--chart-${index+1},var(--primary))"></div>`).join('')}</div><label class="row" for="sample">Workspace name</label><input id="sample" value="Academic services" readonly></article></section><footer>Isolated palette preview · Not a live portal screenshot · System font fallbacks · 1120 × 720</footer></main></body></html>`, { waitUntil: 'load' });
      await page.screenshot({ path: path.join(output, `${id}-${mode}.png`) });
      console.log(`Captured ${id}-${mode}.png`);
    }
  }
} finally {
  await browser.close();
}
await writeFile(path.join(output, 'sources.json'), JSON.stringify(manifest, null, 2) + '\n');
