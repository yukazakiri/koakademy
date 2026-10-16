import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import assert from "node:assert/strict";
import { existsSync, mkdirSync } from "node:fs";
import path from "node:path";
import process from "node:process";
import { after, before, test } from "node:test";
import { fileURLToPath } from "node:url";
import puppeteer from "puppeteer";
import { createServer } from "vite";

const root = fileURLToPath(new URL("../../", import.meta.url));
const executablePath =
    process.env.PUPPETEER_EXECUTABLE_PATH ??
    ["/usr/bin/chromium", "/usr/bin/chromium-browser", "/usr/bin/google-chrome", puppeteer.executablePath()].find(existsSync);
let server, browser, baseUrl;
before(
    async () => {
        assert.ok(executablePath, "Installed Chromium required.");
        server = await createServer({
            configFile: false,
            root,
            appType: "custom",
            plugins: [tailwindcss(), react()],
            resolve: { alias: { "@": path.join(root, "resources/js") }, dedupe: ["react", "react-dom"] },
            server: { host: "127.0.0.1", port: 0 },
        });
        server.middlewares.use(async (request, response, next) => {
            if (!request.url?.startsWith("/enrollment-workflow-fixture")) return next();
            response.setHeader("Content-Type", "text/html");
            response.end(
                await server.transformIndexHtml(
                    request.url,
                    '<!doctype html><html><head><title>Enrollment workflow</title><meta name="viewport" content="width=device-width, initial-scale=1" /></head><body><div id="root"></div><script type="module" src="/tests/Fixtures/enrollment-workflow.tsx"></script></body></html>',
                ),
            );
        });
        await server.listen();
        baseUrl = `http://127.0.0.1:${server.httpServer.address().port}`;
        browser = await puppeteer.launch({ executablePath, headless: true, args: ["--no-sandbox", "--disable-dev-shm-usage"] });
    },
    { timeout: 120_000 },
);
after(async () => {
    await browser?.close();
    await server?.close();
});

async function withCanvas(run, { width = 1500, dark = false, query = "" } = {}) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.setViewport({ width, height: 1100 });
    await page.setRequestInterception(true);
    page.on("request", (request) => {
        if (request.url().startsWith(baseUrl) || request.url().startsWith("data:") || request.url().startsWith("about:")) request.continue();
        else request.abort();
    });
    mkdirSync(path.join(root, "tests/Browser/Screenshots/.enrollment"), { recursive: true });
    try {
        await page.goto(`${baseUrl}/enrollment-workflow-fixture${query}`, { waitUntil: "networkidle0" });
        await page.waitForSelector("[data-workflow-trigger]");
        if (dark) {
            await page.evaluate(() => document.documentElement.classList.add("dark"));
            await page.waitForFunction(() => getComputedStyle(document.body).backgroundColor === "oklch(0.1913 0 0)");
        }
        await run(page);
        assert.deepEqual(errors, []);
    } finally {
        await context.close();
    }
}

test("shows submission trigger, embedded action order, staff gate and reachable outcome", { timeout: 120_000 }, async () =>
    withCanvas(async (page) => {
        const text = await page.$eval("[data-workflow-canvas]", (element) => element.textContent);
        assert.match(text, /Enrollment submitted/);
        assert.match(text, /Wait for staff action/);
        assert.match(text, /After every action succeeds/);
        await page.screenshot({ path: path.join(root, "tests/Browser/Screenshots/.enrollment/desktop.png") });
        await page.click('[aria-label="Move tuition up"]');
        await page.waitForFunction(() => JSON.parse(document.querySelector("[data-workflow-json]").textContent)[0].actions[0].key === "tuition");
        mkdirSync(path.join(root, "tests/Browser/Screenshots/.enrollment"), { recursive: true });
        await page.click('[aria-label="Configure Application received"]');
        await page.waitForSelector('[role="dialog"]');
        assert.equal(await page.$eval('input[value="submitted"]', (input) => input.readOnly), true);
    }),
);

test("supports dark mobile layout without page overflow", { timeout: 60_000 }, async () =>
    withCanvas(
        async (page) => {
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            const trigger = await page.$eval("[data-workflow-trigger]", (element) => ({
                top: element.getBoundingClientRect().top,
                bottom: element.getBoundingClientRect().bottom,
            }));
            assert.ok(trigger.top > 210 && trigger.bottom < 950, "Mobile starts at the submission trigger, not a clipped middle stage");
            await page.screenshot({ path: path.join(root, "tests/Browser/Screenshots/.enrollment/mobile-dark.png") });
        },
        { width: 390, dark: true },
    ),
);

test("read-only canvas cannot edit stage actions", { timeout: 60_000 }, async () =>
    withCanvas(
        async (page) => {
            assert.equal(await page.$eval('[aria-label="Configure Application received"]', (button) => button.disabled), true);
            assert.equal(await page.$('[aria-label="Move tuition up"]'), null);
            assert.equal(await page.$eval('[aria-label="Edit route Verify academics"]', (button) => button.disabled), true);
        },
        { query: "?readonly" },
    ),
);
