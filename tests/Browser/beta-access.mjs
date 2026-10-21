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

// Real React/Inertia controls with deterministic HTTP responses. PHP feature
// tests cover authentication, persistence, rollout decisions and route gates.
const root = fileURLToPath(new URL("../../", import.meta.url));
const executablePath =
    process.env.PUPPETEER_EXECUTABLE_PATH ??
    ["/usr/bin/chromium", "/usr/bin/chromium-browser", "/usr/bin/google-chrome", puppeteer.executablePath()].find(existsSync);
let server, browser, baseUrl, state;

function reset() {
    state = { enrolled: false, preference: true, requests: [], errors: {}, audience: null, rejected: false };
}

function pageData(url) {
    return {
        component: "BetaAccessFixture",
        url,
        version: null,
        clearHistory: false,
        encryptHistory: false,
        props: {
            errors: state.errors,
            inspector: url.includes("inspector"),
            beta: {
                enrolled: state.enrolled,
                joined_at: state.enrolled ? "2026-10-21T12:00:00Z" : null,
                features: [
                    {
                        key: "student-grades-preview",
                        name: "Grade preview",
                        summary: "Preview grades before general release.",
                        audience: "beta",
                        enabled: state.preference,
                        available: state.enrolled && state.preference,
                        reason: !state.enrolled ? "Beta Access enrollment required" : !state.preference ? "Beta opt-out" : "Included in Beta Access",
                    },
                ],
            },
            feature: {
                key: "student-grades-preview",
                name: "Grade preview",
                summary: "Preview grades before general release.",
                audience: "student",
                category: "Student",
                class: "StudentGradesPreview",
                catalog: { roles: ["student"], default_enabled: false, tier: "beta", opt_in: true, public: false },
                policy: {
                    enabled: true,
                    mode: "enforced",
                    release_audience: state.audience,
                    strategy: "defaults",
                    percentage: 0,
                    allow_opt_in: false,
                    activate_at: null,
                    retire_at: null,
                    revision: 1,
                },
                assignment_count: 0,
                release_status: "released",
            },
        },
    };
}

before(
    async () => {
        assert.ok(executablePath, "Installed Chromium required; no browser dependency is downloaded.");
        reset();
        server = await createServer({
            configFile: false,
            root,
            appType: "custom",
            plugins: [tailwindcss(), react()],
            resolve: { alias: { "@": path.join(root, "resources/js") }, dedupe: ["react", "react-dom"] },
            server: { host: "127.0.0.1", port: 0 },
        });
        server.middlewares.use(async (request, response, next) => {
            if (request.url?.endsWith("/details")) {
                response.setHeader("Content-Type", "application/json");
                response.end(JSON.stringify({ assignments: [], audit: [] }));
                return;
            }
            if (
                ["PUT", "POST"].includes(request.method) &&
                (request.url?.startsWith("/profile/beta-access") || request.url?.startsWith("/administrators/feature-toggles"))
            ) {
                let body = "";
                for await (const chunk of request) body += chunk;
                const data = JSON.parse(body);
                state.requests.push({ url: request.url, method: request.method, data });
                state.errors = {};
                if (request.headers.referer?.includes("reject=1") && !state.rejected) {
                    state.errors = { enabled: "Beta enrollment could not be saved. Please retry." };
                    state.rejected = true;
                } else if (request.url === "/profile/beta-access/features") state.preference = data.enabled;
                else if (request.url === "/profile/beta-access") state.enrolled = data.enabled;
                else state.audience = data.release_audience;
                response.writeHead(303, { Location: request.headers.referer ?? "/beta-access-fixture" });
                response.end();
                return;
            }
            if (!request.url?.startsWith("/beta-access-fixture")) return next();
            const page = pageData(request.url);
            if (request.headers["x-inertia"]) {
                response.setHeader("X-Inertia", "true");
                response.setHeader("Content-Type", "application/json");
                response.end(JSON.stringify(page));
                return;
            }
            response.setHeader("Content-Type", "text/html");
            const dark = request.url.includes("dark");
            response.end(
                await server.transformIndexHtml(
                    request.url,
                    `<!doctype html><html${dark ? ' class="dark"' : ""}><head><title>Beta access browser test</title><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app"></div><script id="page-data" type="application/json">${JSON.stringify(page).replaceAll("<", "\\u003c")}</script><script type="module" src="/tests/Fixtures/beta-access.tsx"></script></body></html>`,
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

async function withPage(run, { width = 1280, query = "" } = {}) {
    reset();
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.setViewport({ width, height: 1000 });
    await page.setRequestInterception(true);
    page.on("request", (request) =>
        request.url().startsWith(baseUrl) || request.url().startsWith("data:") || request.url().startsWith("about:")
            ? request.continue()
            : request.abort(),
    );
    try {
        await page.goto(`${baseUrl}/beta-access-fixture${query}`, { waitUntil: "networkidle0" });
        await run(page);
        assert.deepEqual(errors, [], "React and Inertia must render without uncaught errors");
    } finally {
        await context.close();
    }
}

test("joins leaves and saves per-feature beta preferences through real Inertia requests", { timeout: 120_000 }, async () =>
    withPage(async (page) => {
        await page.waitForSelector("#join-beta-access");
        assert.equal(await page.$eval("#beta-student-grades-preview", (element) => element.disabled), true);
        await page.click("#join-beta-access");
        await page.waitForFunction(() => document.querySelector("#join-beta-access").getAttribute("aria-checked") === "true");
        assert.deepEqual(state.requests[0].data, { enabled: true });
        await page.waitForFunction(() => !document.querySelector("#beta-student-grades-preview").disabled);
        await page.click("#beta-student-grades-preview");
        await page.waitForFunction(() => document.querySelector("#beta-student-grades-preview").getAttribute("aria-checked") === "false");
        assert.deepEqual(state.requests[1].data, { feature_key: "student-grades-preview", enabled: false });
        await page.click("#join-beta-access");
        await page.waitForFunction(() => document.querySelector("#join-beta-access").getAttribute("aria-checked") === "false");
        assert.equal(state.requests[2].data.enabled, false);
    }),
);

test("shows readable beta enrollment on narrow dark screens without page overflow", { timeout: 60_000 }, async () =>
    withPage(
        async (page) => {
            await page.waitForSelector("#join-beta-access");
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            mkdirSync(path.join(root, "tests/Browser/Screenshots/.beta"), { recursive: true });
            await page.screenshot({ path: path.join(root, "tests/Browser/Screenshots/.beta/mobile-dark.png") });
        },
        { width: 390, query: "?dark=1" },
    ),
);

test("keeps consent unchanged after a rejected save and lets users retry", { timeout: 60_000 }, async () =>
    withPage(
        async (page) => {
            await page.waitForSelector("#join-beta-access");
            await page.click("#join-beta-access");
            await page.waitForFunction(() => document.body.textContent.includes("Beta enrollment could not be saved. Please retry."));
            assert.equal(await page.$eval("#join-beta-access", (element) => element.getAttribute("aria-checked")), "false");
            await page.click("#join-beta-access");
            await page.waitForFunction(() => document.querySelector("#join-beta-access").getAttribute("aria-checked") === "true");
            assert.equal(state.enrolled, true);
        },
        { query: "?reject=1" },
    ),
);

test("saves a per-feature beta release audience using typed policy endpoint", { timeout: 60_000 }, async () =>
    withPage(
        async (page) => {
            await page.waitForSelector("#release-audience");
            await page.click("#release-audience");
            await page.locator('[role="option"] ::-p-text(Beta: enrolled participants)').click();
            await page.locator("button ::-p-text(Save Policy Changes)").click();
            await page.waitForFunction(() => document.body.textContent.includes("Feature policy saved."));
            assert.equal(state.requests.at(-1).data.release_audience, "beta");
            assert.equal(state.requests.at(-1).method, "PUT");
            mkdirSync(path.join(root, "tests/Browser/Screenshots/.beta"), { recursive: true });
            await page.screenshot({ path: path.join(root, "tests/Browser/Screenshots/.beta/admin-desktop.png") });
        },
        { query: "?inspector=1" },
    ),
);

test("keeps admin beta controls reachable on mobile", { timeout: 60_000 }, async () =>
    withPage(
        async (page) => {
            await page.waitForSelector("#release-audience");
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            await page.click("#release-audience");
            await page.locator('[role="option"] ::-p-text(Private beta: selected participants)').click();
            await page.waitForFunction(() =>
                document.body.textContent.includes("Requires Beta Access enrollment and a matching administrator Allow rule."),
            );
            mkdirSync(path.join(root, "tests/Browser/Screenshots/.beta"), { recursive: true });
            await page.screenshot({ path: path.join(root, "tests/Browser/Screenshots/.beta/admin-mobile.png"), fullPage: true });
        },
        { width: 390, query: "?inspector=1&dark=1" },
    ),
);
