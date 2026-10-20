import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import assert from "node:assert/strict";
import { existsSync } from "node:fs";
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
            resolve: {
                alias: [
                    { find: "@/components/administrators/admin-layout", replacement: path.join(root, "tests/Fixtures/user-directory-layout.tsx") },
                    { find: "@", replacement: path.join(root, "resources/js") },
                ],
                dedupe: ["react", "react-dom"],
            },
            server: { host: "127.0.0.1", port: 0 },
        });
        server.middlewares.use(async (request, response, next) => {
            if (
                !request.url?.startsWith("/user-directory-fixture") &&
                !request.url?.startsWith("/user-details-fixture") &&
                !request.url?.startsWith("/user-edit-fixture")
            )
                return next();
            response.setHeader("Content-Type", "text/html");
            const fixture = request.url.includes("user-details-fixture")
                ? "user-details"
                : request.url.includes("user-edit-fixture")
                  ? "user-edit"
                  : "user-directory";
            response.end(
                await server.transformIndexHtml(
                    request.url,
                    `<!doctype html><html><head><title>User directory</title><meta name="viewport" content="width=device-width, initial-scale=1" /></head><body><div id="root"></div><script type="module" src="/tests/Fixtures/${fixture}.tsx"></script></body></html>`,
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

async function directory(run, width = 1440) {
    const page = await browser.newPage();
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.setViewport({ width, height: 1050 });
    try {
        await page.goto(`${baseUrl}/user-directory-fixture`, { waitUntil: "networkidle0" });
        await page.waitForSelector('input[aria-label="Search accounts by name or email"]', { timeout: 15000 }).catch(() => {
            throw new Error(JSON.stringify(errors));
        });
        if (process.env.DIRECTORY_SCREENSHOT_DIR) {
            await page.screenshot({ path: path.join(process.env.DIRECTORY_SCREENSHOT_DIR, `initial-${width}.png`), fullPage: true });
        }
        await run(page);
        if (process.env.DIRECTORY_SCREENSHOT_DIR) {
            await page.screenshot({ path: path.join(process.env.DIRECTORY_SCREENSHOT_DIR, `directory-${width}.png`), fullPage: true });
        }
        assert.deepEqual(errors, []);
    } finally {
        await page.close();
    }
}
test("directory lists accounts, opens details, and filters by queue", { timeout: 120000 }, async () =>
    directory(async (page) => {
        assert.equal(await page.$eval('a[href$="/users/1"]', (el) => el.textContent), "Mika Santos");
        assert.ok(await page.$('a[href$="/users/1"]'));
        assert.deepEqual(await page.$$eval('a[href$="/users/1"]', (els) => els.map((el) => el.textContent.trim())), ["Mika Santos", "View"]);
        assert.match(await page.$eval("table", (el) => el.textContent), /Arun Reyes/);
        await page.click('[aria-label="Actions for Mika Santos"]');
        await page.waitForSelector('[role="menu"]');
        const suspend = await page.waitForFunction(() =>
            [...document.querySelectorAll('[role="menuitem"]')].find((el) => el.textContent.includes("Suspend account")),
        );
        await suspend.click();
        await page.waitForSelector('[role="alertdialog"]');
        assert.equal(await page.$eval('[role="alertdialog"] button:last-child', (el) => el.disabled), true);
        await page.keyboard.press("Escape");
        await page.waitForSelector('[role="alertdialog"]', { hidden: true });
        const suspended = await page.waitForFunction(() =>
            [...document.querySelectorAll('nav[aria-label="Account review queues"] button')].find((el) => el.textContent.startsWith("Suspended")),
        );
        await suspended.evaluate((el) => el.click());
        await page.waitForFunction(() => !document.querySelector("table").textContent.includes("Mika Santos"));
        assert.match(await page.$eval("table", (el) => el.textContent), /Arun Reyes/);
    }),
);
test("mobile directory contains the grid without page overflow and supports empty search", { timeout: 120000 }, async () =>
    directory(async (page) => {
        await page.evaluate(() => document.documentElement.classList.add("dark"));
        await page.type('input[aria-label="Search accounts by name or email"]', "no-matching-account");
        await page.waitForFunction(() => document.querySelector("table").textContent.includes("No accounts match"));
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    }, 390),
);

test("account details render signals, timeline, sessions and tokens", { timeout: 120000 }, async () => {
    const page = await browser.newPage();
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.setViewport({ width: 1440, height: 1200 });
    try {
        await page.goto(`${baseUrl}/user-details-fixture`, { waitUntil: "networkidle0" });
        await page.waitForSelector("h1");
        if (process.env.DIRECTORY_SCREENSHOT_DIR) {
            await page.screenshot({ path: path.join(process.env.DIRECTORY_SCREENSHOT_DIR, "details-1440.png"), fullPage: true });
        }
        const heading = await page.$eval("h1", (el) => el.textContent);
        assert.match(heading, /Mika Santos/);
        const body = await page.$eval("body", (el) => el.textContent);
        assert.match(body, /Account review/);
        assert.match(body, /signed in/);
        assert.match(body, /sign in failed/);
        assert.match(body, /account suspended/);
        assert.match(body, /Chrome/);
        assert.match(body, /Phone/);
        assert.deepEqual(errors, []);
    } finally {
        await page.close();
    }
});

test("account form shows sections, live summary and save actions", { timeout: 120000 }, async () => {
    const page = await browser.newPage();
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.setViewport({ width: 1440, height: 1200 });
    try {
        await page.goto(`${baseUrl}/user-edit-fixture`, { waitUntil: "networkidle0" });
        await page.waitForSelector("#name");
        if (process.env.DIRECTORY_SCREENSHOT_DIR) {
            await page.screenshot({ path: path.join(process.env.DIRECTORY_SCREENSHOT_DIR, "edit-1440.png"), fullPage: true });
        }
        const body = await page.$eval("body", (el) => el.textContent);
        assert.match(body, /Identity/);
        assert.match(body, /Credentials/);
        assert.match(body, /Placement/);
        assert.match(body, /Additional access/);
        assert.match(body, /Library support bundle/);
        assert.match(body, /School of Computing/);
        assert.equal(await page.$eval("#name", (el) => el.value), "Mika Santos");
        assert.deepEqual(errors, []);
    } finally {
        await page.close();
    }
});
