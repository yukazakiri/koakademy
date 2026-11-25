import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire, Module } from "node:module";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import ts from "typescript";

function loadTs(url) {
    const filename = fileURLToPath(url);
    const { outputText } = ts.transpileModule(readFileSync(url, "utf8"), {
        fileName: filename,
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    });
    const mod = new Module(filename);
    mod.require = createRequire(url);
    mod._compile(outputText, filename);
    return mod.exports;
}

const appUpdate = loadTs(new URL("../../resources/js/lib/app-update.ts", import.meta.url));
const {
    canAttemptPreloadRecovery,
    clearAllDirtyGuards,
    hasActiveDomWork,
    hasActiveUserWork,
    hasRegisteredDirtyGuard,
    isBuildUpdateAvailable,
    registerDirtyGuard,
    shouldAutoReloadOnVisit,
} = appUpdate;

test("detects an available update only when both build IDs are present and differ", () => {
    assert.strictEqual(isBuildUpdateAvailable(null, "build-2"), false);
    assert.strictEqual(isBuildUpdateAvailable("build-1", null), false);
    assert.strictEqual(isBuildUpdateAvailable("", "build-2"), false);
    assert.strictEqual(isBuildUpdateAvailable("build-1", "build-1"), false);
    assert.strictEqual(isBuildUpdateAvailable("build-1", "build-2"), true);
});

test("auto-reloads only on clean cross-page GET navigations with no active work", () => {
    assert.strictEqual(
        shouldAutoReloadOnVisit({
            updateAvailable: true,
            method: "get",
            isPartialReload: false,
            isPrefetch: false,
            currentPathname: "/faculty/dashboard",
            targetPathname: "/faculty/classes",
            hasActiveWork: false,
        }),
        true,
    );

    // Same-page filters, sorting, tabs, and pagination must never trigger a hard reload.
    assert.strictEqual(
        shouldAutoReloadOnVisit({
            updateAvailable: true,
            method: "get",
            isPartialReload: false,
            isPrefetch: false,
            currentPathname: "/administrators/students",
            targetPathname: "/administrators/students",
            hasActiveWork: false,
        }),
        false,
    );

    // Dirty forms or open dialogs block auto-reload.
    assert.strictEqual(
        shouldAutoReloadOnVisit({
            updateAvailable: true,
            method: "get",
            isPartialReload: false,
            isPrefetch: false,
            currentPathname: "/faculty/classes/1",
            targetPathname: "/faculty/dashboard",
            hasActiveWork: true,
        }),
        false,
    );

    // Partial reloads, prefetches, and mutations never trigger auto-reload.
    for (const overrides of [
        { updateAvailable: false },
        { method: "post" },
        { method: "put" },
        { method: "delete" },
        { isPartialReload: true },
        { isPrefetch: true },
    ]) {
        assert.strictEqual(
            shouldAutoReloadOnVisit({
                updateAvailable: true,
                method: "get",
                isPartialReload: false,
                isPrefetch: false,
                currentPathname: "/a",
                targetPathname: "/b",
                hasActiveWork: false,
                ...overrides,
            }),
            false,
        );
    }
});

test("tracks registered dirty guards cleanly across mount/unmount cycles", () => {
    clearAllDirtyGuards();
    assert.strictEqual(hasRegisteredDirtyGuard(), false);
    assert.strictEqual(hasActiveUserWork(null), false);

    const releaseFirst = registerDirtyGuard("grade-form");
    const releaseSecond = registerDirtyGuard("profile-form");
    assert.strictEqual(hasRegisteredDirtyGuard(), true);
    assert.strictEqual(hasActiveUserWork(null), true);

    releaseFirst();
    assert.strictEqual(hasRegisteredDirtyGuard(), true);

    releaseSecond();
    assert.strictEqual(hasRegisteredDirtyGuard(), false);
    assert.strictEqual(hasActiveUserWork(null), false);
});

test("detects active DOM work from open dialogs and modified form controls", () => {
    clearAllDirtyGuards();

    const dialogDoc = {
        querySelector: (selector) => (selector.includes("dialog") ? {} : null),
        querySelectorAll: () => [],
        activeElement: null,
    };
    assert.strictEqual(hasActiveDomWork(dialogDoc), true);

    const cleanFormDoc = {
        querySelector: () => null,
        activeElement: null,
        querySelectorAll: () => [
            { tagName: "INPUT", type: "text", value: "Alice", defaultValue: "Alice", disabled: false, readOnly: false, dataset: {} },
            { tagName: "INPUT", type: "search", value: "changed-search", defaultValue: "", disabled: false, readOnly: false, dataset: {} },
            {
                tagName: "INPUT",
                type: "text",
                value: "safe-filter",
                defaultValue: "",
                disabled: false,
                readOnly: false,
                dataset: { updateSafe: "true" },
            },
        ],
    };
    assert.strictEqual(hasActiveDomWork(cleanFormDoc), false);

    const dirtyFormDoc = {
        querySelector: () => null,
        activeElement: null,
        querySelectorAll: () => [
            { tagName: "TEXTAREA", value: "Unsaved grade remark", defaultValue: "", disabled: false, readOnly: false, dataset: {} },
        ],
    };
    assert.strictEqual(hasActiveDomWork(dirtyFormDoc), true);

    const dirtyCheckboxDoc = {
        querySelector: () => null,
        activeElement: null,
        querySelectorAll: () => [
            { tagName: "INPUT", type: "checkbox", checked: true, defaultChecked: false, disabled: false, readOnly: false, dataset: {} },
        ],
    };
    assert.strictEqual(hasActiveDomWork(dirtyCheckboxDoc), true);
});

test("enforces preload recovery cooldown to prevent reload loops", () => {
    assert.strictEqual(canAttemptPreloadRecovery(100_000, null), true);
    assert.strictEqual(canAttemptPreloadRecovery(100_000, undefined), true);
    assert.strictEqual(canAttemptPreloadRecovery(100_000, 90_000, 30_000), false);
    assert.strictEqual(canAttemptPreloadRecovery(120_000, 90_000, 30_000), true);
});

test("service worker bypasses versioned Vite assets, build probes, and Inertia requests", () => {
    const swContent = readFileSync(new URL("../../public/sw.js", import.meta.url), "utf8");

    assert.match(swContent, /X-Inertia/);
    assert.match(swContent, /\/build\//);
    assert.match(swContent, /\/build-version/);
    assert.ok(!swContent.includes("caches.match(event.request)"), "SW must not blindly serve stale cached assets across releases");
});
