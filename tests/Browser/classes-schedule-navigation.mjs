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
            if (!request.url?.startsWith("/classes-schedule-fixture")) return next();
            response.setHeader("Content-Type", "text/html");
            response.end(
                await server.transformIndexHtml(
                    request.url,
                    `<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1" /></head><body><div id="root"></div><script type="module" src="/tests/Fixtures/classes-schedule-navigation.tsx"></script></body></html>`,
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

async function withPage(query, run, width = 1440) {
    const page = await browser.newPage();
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    try {
        await page.setViewport({ width, height: 1000 });
        await page.goto(`${baseUrl}/classes-schedule-fixture${query}`, { waitUntil: "networkidle0" });
        await page.waitForSelector("h1", { timeout: 10000 }).catch(async () => {
            if (errors.length === 0) {
                await page.reload({ waitUntil: "networkidle0" });
                await page.waitForSelector("h1", { timeout: 10000 });
                return;
            }
            throw new Error(
                `Page did not render: ${JSON.stringify({ errors, url: page.url(), body: await page.$eval("body", (el) => el.innerHTML) })}`,
            );
        });
        await run(page);
        assert.deepEqual(errors, []);
    } finally {
        await page.close();
    }
}

async function clickText(page, selector, text) {
    const element = await page.waitForFunction(
        (selector, text) =>
            [...document.querySelectorAll(selector)].find((item) => item.textContent.trim() === text && item.getBoundingClientRect().width > 0),
        {},
        selector,
        text,
    );
    await element.click();
}

test("schedule actions from table, card and menu open the class edit page", { timeout: 120_000 }, async () => {
    await withPage("", async (page) => {
        const expectScheduleVisit = async () => {
            await page.waitForFunction(() => window.classScheduleVisit === "/administrators/classes/15/edit?step=schedule");
            await page.evaluate(() => {
                window.classScheduleVisit = null;
            });
        };

        await clickText(page, "button", "Schedule");
        await expectScheduleVisit();
        await page.click('[aria-label="Actions for STEM 101 - A"]');
        await clickText(page, '[role="menuitem"]', "Edit schedule");
        await expectScheduleVisit();
        await page.click('[aria-label="Card view"]');
        await clickText(page, "button", "Schedule");
        await expectScheduleVisit();
    });
});

test("manage details sheet opens schedule on the edit page", { timeout: 120_000 }, async () => {
    await withPage("", async (page) => {
        await page.click('[aria-label="Actions for STEM 101 - A"]');
        await clickText(page, '[role="menuitem"]', "Manage details");
        await page.waitForFunction(() => {
            const sheet = document.querySelector('[data-slot="sheet-content"]');
            return sheet && sheet.getBoundingClientRect().left < window.innerWidth - 100;
        });
        await clickText(page, '[data-slot="sheet-content"] [role="tab"]', "Schedule");
        await clickText(page, '[role="dialog"] button', "Edit schedule");
        await page.waitForFunction(() => window.classScheduleVisit === "/administrators/classes/15/edit?step=schedule");
    });
});

test("schedule navigation cancels a pending search", { timeout: 120_000 }, async () => {
    await withPage("", async (page) => {
        await page.type("#class-search", "science");
        await clickText(page, "button", "Schedule");
        await new Promise((resolve) => setTimeout(resolve, 500));
        assert.equal(await page.evaluate(() => window.classListRequests ?? 0), 0);
        assert.equal(await page.evaluate(() => window.classScheduleVisit), "/administrators/classes/15/edit?step=schedule");
    });
});

test("schedule deep link starts on teaching and schedule; normal and unknown links start on details", { timeout: 120_000 }, async () => {
    for (const [query, heading] of [
        ["?mode=edit&step=schedule", "Teaching & schedule"],
        ["?mode=edit", "Class details"],
        ["?mode=edit&step=other", "Class details"],
    ]) {
        await withPage(query, async (page) => {
            assert.equal(await page.$eval("form h2", (element) => element.textContent.trim()), heading);
        });
    }
});

async function setTime(page, selector, value) {
    await page.$eval(
        selector,
        (input, next) => {
            const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value").set;
            setter.call(input, next);
            input.dispatchEvent(new Event("input", { bubbles: true }));
            input.dispatchEvent(new Event("change", { bubbles: true }));
        },
        value,
    );
}

test("record edits a recurring pattern, reviews each change and sends only schedule fields", { timeout: 120_000 }, async () => {
    await withPage("?mode=show", async (page) => {
        await clickText(page, "button", "Edit schedule");
        assert.equal(await page.evaluate(() => document.activeElement?.textContent), "Update weekly meetings");
        if (process.env.CLASS_SCHEDULE_SCREENSHOT_DIR)
            await page.screenshot({ path: path.join(process.env.CLASS_SCHEDULE_SCREENSHOT_DIR, "class-schedule-desktop.png"), fullPage: true });
        assert.equal(await page.$('[role="dialog"]'), null);
        await setTime(page, "#start-21", "10:00");
        await setTime(page, "#end-21", "11:00");
        await page.click('section[aria-label="Meeting 1"] input[type="checkbox"]');
        assert.equal(await page.$eval("#start-22", (input) => input.value), "10:00");
        assert.equal(await page.$eval("#start-22", (input) => input.disabled), true);
        await clickText(page, "button", "Review changes");
        await page.waitForSelector('[role="alertdialog"]');
        const preview = await page.$eval('[role="alertdialog"]', (element) => element.textContent);
        assert.match(preview, /Monday 08:00–09:00/);
        assert.match(preview, /Wednesday 08:00–09:00/);
        assert.match(preview, /10:00–11:00/);
        await clickText(page, '[role="alertdialog"] button', "Save schedule");
        const submitted = await page.evaluate(() => window.classSchedulePatch);
        assert.equal(submitted.url, "/administrators/classes/15/schedule");
        assert.deepEqual(submitted.data, {
            schedule_revision: "a".repeat(64),
            schedules: [
                { id: 21, day_of_week: "Monday", start_time: "10:00", end_time: "11:00", delivery_mode: "face_to_face", room_id: 4 },
                { id: 22, day_of_week: "Wednesday", start_time: "10:00", end_time: "11:00", delivery_mode: "face_to_face", room_id: 4 },
            ],
        });
    });
});

test("editing one recurring meeting leaves other days unchanged", { timeout: 120_000 }, async () => {
    await withPage("?mode=show", async (page) => {
        await clickText(page, "button", "Edit schedule");
        await setTime(page, "#start-21", "10:00");
        await setTime(page, "#end-21", "11:00");
        await clickText(page, "button", "Review changes");
        const preview = await page.$eval('[role="alertdialog"]', (element) => element.textContent);
        assert.match(preview, /Monday/);
        assert.doesNotMatch(preview, /Wednesday/);
        await clickText(page, '[role="alertdialog"] button', "Save schedule");
        const submitted = await page.evaluate(() => window.classSchedulePatch);
        assert.equal(submitted.data.schedules[1].start_time, "08:00");
        assert.equal(submitted.data.schedules[1].end_time, "09:00");
    });
});

test("preview shows an unassigned room after switching online and back", { timeout: 120_000 }, async () => {
    await withPage("?mode=show", async (page) => {
        await clickText(page, "button", "Edit schedule");
        await page.select("#delivery-21", "online");
        await page.select("#delivery-21", "face_to_face");
        await clickText(page, "button", "Review changes");
        const preview = await page.$eval('[role="alertdialog"]', (element) => element.textContent);
        assert.match(preview, /Room 201 to Monday 08:00–09:00 · Room unassigned/);
    });
});

test("record validates overlaps and protects unsaved changes on mobile", { timeout: 120_000 }, async () => {
    await withPage(
        "?mode=show",
        async (page) => {
            await clickText(page, "button", "Edit schedule");
            if (process.env.CLASS_SCHEDULE_SCREENSHOT_DIR)
                await page.screenshot({ path: path.join(process.env.CLASS_SCHEDULE_SCREENSHOT_DIR, "class-schedule-mobile.png"), fullPage: true });
            await page.select("#day-22", "Monday");
            await page.waitForFunction(() => document.body.textContent.includes("overlaps another meeting"));
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
            const review = await page.waitForFunction(() =>
                [...document.querySelectorAll("button")].find((el) => el.textContent.trim() === "Review changes"),
            );
            assert.equal(await review.evaluate((button) => button.disabled), true);
            await clickText(page, "button", "Cancel");
            await page.waitForSelector('[role="alertdialog"]');
            await clickText(page, '[role="alertdialog"] button', "Keep editing");
            assert.equal(await page.$eval("#day-22", (input) => input.value), "Monday");
            await clickText(page, "button", "Cancel");
            await clickText(page, '[role="alertdialog"] button', "Discard changes");
            assert.equal(await page.$('[aria-label="Edit weekly schedule"]'), null);
        },
        390,
    );
});

test("record keeps edits and displays server conflict beside affected meeting", { timeout: 120_000 }, async () => {
    await withPage("?mode=show", async (page) => {
        await page.evaluate(() => {
            window.classScheduleMockErrors = { "schedules.1": "Room 201 is occupied by CS101 on Wednesday." };
        });
        await clickText(page, "button", "Edit schedule");
        await setTime(page, "#start-22", "10:00");
        await setTime(page, "#end-22", "11:00");
        await clickText(page, "button", "Review changes");
        await clickText(page, '[role="alertdialog"] button', "Save schedule");
        await page.waitForFunction(() =>
            document.querySelector('section[aria-label="Meeting 2"]')?.textContent.includes("Room 201 is occupied by CS101"),
        );
        assert.equal(await page.$eval("#start-22", (input) => input.value), "10:00");
        assert.equal(await page.$eval("#start-22", (input) => input.getAttribute("aria-invalid")), "true");
    });
});

test("stale schedule errors survive edits and prevent another stale save", { timeout: 120_000 }, async () => {
    await withPage("?mode=show", async (page) => {
        await page.evaluate(() => {
            window.classScheduleMockErrors = { schedule_revision: "This schedule changed since you opened it. Reload the class before saving." };
        });
        await clickText(page, "button", "Edit schedule");
        await setTime(page, "#start-22", "10:00");
        await setTime(page, "#end-22", "11:00");
        await clickText(page, "button", "Review changes");
        await clickText(page, '[role="alertdialog"] button', "Save schedule");
        await page.waitForFunction(() => document.body.textContent.includes("This schedule changed since you opened it"));
        await setTime(page, "#end-22", "11:30");
        assert.match(await page.$eval('[role="alert"]', (element) => element.textContent), /Reload the class/);
        const review = await page.waitForFunction(() =>
            [...document.querySelectorAll("button")].find((el) => el.textContent.trim() === "Review changes"),
        );
        assert.equal(await review.evaluate((button) => button.disabled), true);
        assert.ok(await page.$('a[href="/administrators/classes/15"]'));
    });
});

test("schedule retry retains the draft revision after newer page props arrive", { timeout: 120_000 }, async () => {
    await withPage("?mode=show", async (page) => {
        await page.evaluate(() => {
            window.classScheduleMockErrors = { "schedules.1": "Room already occupied." };
        });
        await clickText(page, "button", "Edit schedule");
        await setTime(page, "#start-22", "10:00");
        await setTime(page, "#end-22", "11:00");
        await clickText(page, "button", "Review changes");
        await clickText(page, '[role="alertdialog"] button', "Save schedule");
        await page.waitForFunction(() => document.body.textContent.includes("Room already occupied."));
        await page.evaluate(() => {
            window.refreshScheduleRevision("b".repeat(64));
            delete window.classScheduleMockErrors;
        });
        await setTime(page, "#end-22", "11:30");
        await clickText(page, "button", "Review changes");
        await clickText(page, '[role="alertdialog"] button', "Save schedule");
        const submitted = await page.evaluate(() => window.classSchedulePatch);
        assert.equal(submitted.data.schedule_revision, "a".repeat(64));
    });
});

test("adding and removing an unsaved meeting leaves no pending changes", { timeout: 120_000 }, async () => {
    await withPage("?mode=show", async (page) => {
        await clickText(page, "button", "Edit schedule");
        await clickText(page, "button", "Add meeting");
        assert.equal(await page.$eval("#day-new-1", (input) => input.value), "Tuesday");
        await page.click('[aria-label="Remove meeting 3"]');
        assert.equal(await page.$('[aria-label="Remove meeting 3"]'), null);
        const review = await page.waitForFunction(() =>
            [...document.querySelectorAll("button")].find((element) => element.textContent.trim() === "Review changes"),
        );
        assert.equal(await review.evaluate((button) => button.disabled), true);
        await clickText(page, "button", "Cancel");
        assert.equal(await page.$('[role="alertdialog"]'), null);
    });
});

test("edit page changes existing meeting fields without replacing its id", { timeout: 120_000 }, async () => {
    await withPage("?mode=edit&step=schedule", async (page) => {
        await page.select('[aria-label="Day for meeting 1"]', "Tuesday");
        await setTime(page, '[aria-label="Start time for meeting 1"]', "10:00");
        await setTime(page, '[aria-label="End time for meeting 1"]', "11:00");
        await clickText(page, "button", "Continue to Classroom settings");
        await clickText(page, "button", "Continue to Review & save");
        await clickText(page, "button", "Save changes");
        const submitted = await page.evaluate(() => window.classSchedulePatch);
        assert.equal(submitted.url, "/administrators/classes/15");
        assert.equal(submitted.data.schedules[0].id, 21);
        assert.equal(submitted.data.schedules[0].day_of_week, "Tuesday");
        assert.equal(submitted.data.schedules_match_by_id, true);
    });
});
