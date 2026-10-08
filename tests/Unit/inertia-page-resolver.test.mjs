import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire, Module } from "node:module";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import ts from "typescript";

function AppRootLayout({ children }) {
    return children;
}

const resolverUrl = new URL("../../resources/js/lib/inertia-page-resolver.tsx", import.meta.url);
const resolverFilename = fileURLToPath(resolverUrl);
const { outputText } = ts.transpileModule(readFileSync(resolverUrl, "utf8"), {
    fileName: resolverFilename,
    compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        jsx: ts.JsxEmit.ReactJSX,
        target: ts.ScriptTarget.ES2022,
    },
});
const resolverRequire = createRequire(resolverUrl);
const resolverModule = new Module(resolverFilename);
resolverModule.require = (specifier) => (specifier === "@/components/app-root-layout" ? { default: AppRootLayout } : resolverRequire(specifier));
resolverModule._compile(outputText, resolverFilename);
const { resolveInertiaPage } = resolverModule.exports;

test("reuses app page wrapper and default layout across repeat resolution", async () => {
    const Original = () => null;
    const appPages = { "./pages/example.tsx": async () => ({ default: Original }) };

    const first = await resolveInertiaPage("example", appPages, {});
    const second = await resolveInertiaPage("example", appPages, {});

    assert.strictEqual(second, first);
    assert.strictEqual(second.layout, first.layout);
    assert.strictEqual(first({}).type, Original);
    assert.strictEqual(first.layout(null).type, AppRootLayout);
    assert.deepStrictEqual(first({ search: "first" }).props, { search: "first" });
    assert.deepStrictEqual(second({ search: "second" }).props, { search: "second" });
    assert.strictEqual(Object.hasOwn(Original, "layout"), false);
});

test("reuses module page wrapper and default layout across repeat resolution", async () => {
    const Original = () => null;
    const modulePages = { "../../Modules/Example/resources/assets/js/Pages/example.tsx": async () => ({ default: Original }) };

    const first = await resolveInertiaPage("example", {}, modulePages);
    const second = await resolveInertiaPage("example", {}, modulePages);

    assert.strictEqual(second, first);
    assert.strictEqual(second.layout, first.layout);
    assert.strictEqual(first({}).type, Original);
});

test("preserves explicit layout without mutating original component", async () => {
    const layout = (children) => children;
    const Original = Object.freeze(Object.assign(() => null, { layout }));
    const appPages = { "./pages/example.tsx": async () => ({ default: Original }) };

    const resolved = await resolveInertiaPage("example", appPages, {});

    assert.notStrictEqual(resolved, Original);
    assert.strictEqual(resolved.layout, layout);
    assert.strictEqual(Original.layout, layout);
});

test("creates new wrapper when original component identity changes", async () => {
    const Original = () => null;
    let component = Original;
    const appPages = { "./pages/example.tsx": async () => ({ default: component }) };
    const first = await resolveInertiaPage("example", appPages, {});

    component = () => null;
    const second = await resolveInertiaPage("example", appPages, {});
    const repeated = await resolveInertiaPage("example", appPages, {});

    assert.notStrictEqual(second, first);
    assert.notStrictEqual(second.layout, first.layout);
    assert.strictEqual(first({}).type, Original);
    assert.strictEqual(second({}).type, component);
    assert.strictEqual(repeated, second);
});
