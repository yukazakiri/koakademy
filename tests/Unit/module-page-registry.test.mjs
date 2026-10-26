import assert from "node:assert/strict";
import test from "node:test";
import { modulePageExists } from "../../resources/js/lib/module-page-registry.ts";

const pages = [
    "../../../vendor/school-tools/campus-surveys/resources/assets/js/Pages/Surveys/Index.tsx",
    "../../../Modules/Announcement/resources/assets/js/Pages/administrators/announcements/index.tsx",
];

test("a package directory can differ from its module name", () => {
    assert.equal(modulePageExists(pages, "Surveys", "Surveys/Index", "vendor/school-tools/campus-surveys"), true);
});

test("another module cannot satisfy the requested page path", () => {
    assert.equal(modulePageExists(pages, "Different", "Surveys/Index", "vendor/another/surveys"), false);
});

test("bundled modules retain their directory fallback", () => {
    assert.equal(modulePageExists(pages, "Announcement", "administrators/announcements/index"), true);
});
