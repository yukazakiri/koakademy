import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire, Module } from "node:module";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import ts from "typescript";

const helperUrl = new URL("../../resources/js/lib/student-directory-return.ts", import.meta.url);
const helperFilename = fileURLToPath(helperUrl);
const { outputText } = ts.transpileModule(readFileSync(helperUrl, "utf8"), {
    fileName: helperFilename,
    compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        target: ts.ScriptTarget.ES2022,
    },
});
const helperModule = new Module(helperFilename);
helperModule.require = createRequire(helperUrl);
helperModule._compile(outputText, helperFilename);
const { studentDirectoryReturnUrl } = helperModule.exports;
const indexUrl = "/administrators/students";
const detailUrl = (returnTo) => `${indexUrl}/42?${new URLSearchParams({ return_to: returnTo })}`;

test("preserves directory search, filters, sort and pagination query", () => {
    const returnTo = `${indexUrl}?search=Jane+Doe&status=enrolled&sort=name&direction=asc&page=3&per_page=20`;

    assert.strictEqual(studentDirectoryReturnUrl(detailUrl(returnTo), indexUrl), returnTo);
});

test("compares directory pathname against generated index URL with an authority", () => {
    const generatedIndexUrl = "//portal.example.test/administrators/students";
    const returnTo = `${indexUrl}?search=Jane%26Doe&page=2`;

    assert.strictEqual(studentDirectoryReturnUrl(detailUrl(returnTo), generatedIndexUrl), returnTo);
    assert.strictEqual(studentDirectoryReturnUrl(`${indexUrl}/42`, generatedIndexUrl), generatedIndexUrl);
});

test("uses generated fallback when return_to is absent or empty", () => {
    assert.strictEqual(studentDirectoryReturnUrl(`${indexUrl}/42`, indexUrl), indexUrl);
    assert.strictEqual(studentDirectoryReturnUrl(detailUrl(""), indexUrl), indexUrl);
});

for (const [name, returnTo] of [
    ["external absolute URL", "https://evil.example/administrators/students?search=Jane"],
    ["external protocol-relative URL", "//evil.example/administrators/students"],
    ["protocol-relative URL even with matching path", "//portal.example.test/administrators/students"],
    ["wrong index pathname", "/administrators/users?search=Jane"],
    ["student detail pathname", "/administrators/students/42"],
    ["index pathname prefix", "/administrators/students-other"],
    ["index pathname suffix", "/administrators/students/"],
    ["JavaScript scheme", "javascript:alert(1)"],
    ["data scheme", "data:text/html,hello"],
    ["malformed URL", "http://["],
    ["malformed percent encoding", "/administrators/students?search=%ZZ"],
    ["relative pathname", "administrators/students"],
    ["backslash authority", "/\\evil.example/administrators/students"],
    ["control character", "/administrators/students\n?search=Jane"],
]) {
    test(`rejects ${name}`, () => {
        assert.strictEqual(studentDirectoryReturnUrl(detailUrl(returnTo), indexUrl), indexUrl);
    });
}
