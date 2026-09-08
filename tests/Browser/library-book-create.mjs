import assert from "node:assert/strict";
import puppeteer from "puppeteer";

const baseUrl = (process.env.LIBRARY_E2E_BASE_URL ?? "http://localhost:8000").replace(/\/$/, "");
const email = process.env.LIBRARY_E2E_EMAIL;
const password = process.env.LIBRARY_E2E_PASSWORD;

if (!email || !password) {
    throw new Error("Set LIBRARY_E2E_EMAIL and LIBRARY_E2E_PASSWORD before running this smoke test.");
}

async function clickVisibleCommandItem(page) {
    await page.waitForFunction(() =>
        [...document.querySelectorAll("[cmdk-item]")].some((element) => {
            const rect = element.getBoundingClientRect();
            return rect.width > 0 && rect.height > 0 && getComputedStyle(element).visibility !== "hidden";
        }),
    );

    for (const item of await page.$$("[cmdk-item]")) {
        if (await item.boundingBox()) {
            await item.click();
            return;
        }
    }

    throw new Error("No visible catalog option was available.");
}

async function waitForCommandItemsToClose(page) {
    await page.waitForFunction(() =>
        [...document.querySelectorAll("[cmdk-item]")].every((element) => {
            const rect = element.getBoundingClientRect();
            return rect.width === 0 || rect.height === 0 || getComputedStyle(element).visibility === "hidden";
        }),
    );
}

const browser = await puppeteer.launch({
    headless: true,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
});

try {
    const page = await browser.newPage();
    await page.setBypassServiceWorker(true);
    let bookRequestCount = 0;
    page.on("request", (request) => {
        const requestUrl = new URL(request.url());

        if (request.method() === "POST" && requestUrl.pathname === "/administrators/library/books") {
            bookRequestCount += 1;
        }
    });

    const pageErrors = [];
    page.on("pageerror", (error) => pageErrors.push(error));

    await page.goto(`${baseUrl}/login`, { waitUntil: "networkidle0" });
    await page.waitForSelector('input[type="email"]');
    await page.type('input[type="email"]', email);
    await page.type('input[type="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForFunction(() => window.location.pathname === "/administrators/dashboard", { timeout: 15000 });

    await page.goto(`${baseUrl}/administrators/library/books/create`, { waitUntil: "networkidle0" });
    await page.waitForFunction(() => window.location.pathname.endsWith("/administrators/library/books/create"), { timeout: 15000 });
    await page.waitForSelector('[data-testid="book-form"]');

    const requestCountBeforeQuickCreate = bookRequestCount;
    await page.click('button[aria-label="Create a new author"]');
    await page.waitForSelector('[role="dialog"]');
    assert.equal(bookRequestCount, requestCountBeforeQuickCreate, "quick-create controls must not submit the book form");
    await page.click('[role="dialog"] button[type="button"]');
    await page.waitForSelector('[role="dialog"]', { hidden: true });

    await page.click('[data-testid="book-submit"]');
    await page.waitForSelector('[data-testid="book-form-errors"]');
    assert.ok(bookRequestCount > requestCountBeforeQuickCreate, "blank submit must send the book POST request");
    assert.match(await page.$eval('[data-testid="book-form-errors"]', (element) => element.textContent ?? ""), /Book title is required/i);
    assert.equal(await page.$eval('[data-testid="book-submit"]', (button) => button.disabled), false);

    await page.goto(`${baseUrl}/administrators/library/books/create`, { waitUntil: "networkidle0" });
    await page.waitForSelector('[data-testid="book-form"]');

    const title = `Browser Book ${Date.now()}`;
    await page.locator("#title").fill(title);

    for (const fieldId of ["author_id", "category_id"]) {
        await page.click(`#${fieldId}`);
        await page.waitForSelector("[cmdk-item]");
        await clickVisibleCommandItem(page);
        await waitForCommandItemsToClose(page);
        assert.doesNotMatch(await page.$eval(`#${fieldId}`, (element) => element.textContent ?? ""), /Search (authors|categories)/i);
    }

    await page.locator('[data-testid="book-submit"]').click();
    await page.waitForFunction((expectedTitle) => document.body.innerText.includes(expectedTitle), { timeout: 15000 }, title);
    assert.match(await page.evaluate(() => document.body.innerText), /Book (?:created successfully|added to the catalog)\./);

    await page.goto(`${baseUrl}/administrators/library/books/create`, { waitUntil: "networkidle0" });
    await page.waitForSelector('[data-testid="book-form"]');
    await page.locator("#title").fill(`Network Failure Book ${Date.now()}`);

    for (const fieldId of ["author_id", "category_id"]) {
        await page.click(`#${fieldId}`);
        await page.waitForSelector("[cmdk-item]");
        await clickVisibleCommandItem(page);
        await waitForCommandItemsToClose(page);
    }

    await page.setOfflineMode(true);
    await page.locator('[data-testid="book-submit"]').click();
    await page.waitForFunction(() => document.body.innerText.includes("connection was interrupted"), { timeout: 15000 });
    await page.setOfflineMode(false);

    assert.equal(pageErrors.length, 0, `browser page errors: ${pageErrors.map((error) => error.message).join("; ")}`);
    console.log("Library book create browser smoke test passed.");
} finally {
    await browser.close();
}
