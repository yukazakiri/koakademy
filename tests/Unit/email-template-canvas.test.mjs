import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire, Module } from "node:module";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import ts from "typescript";

const helperUrl = new URL("../../resources/js/lib/email-template-canvas.ts", import.meta.url);
const filename = fileURLToPath(helperUrl);
const { outputText } = ts.transpileModule(readFileSync(helperUrl, "utf8"), {
    fileName: filename,
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
});
const helper = new Module(filename);
helper.require = createRequire(helperUrl);
helper._compile(outputText, filename);
const {
    canonicalEmailTemplateKey,
    configurationFromDraft,
    createEmailDraft,
    emailDraftHistoryReducer,
    insertEmailCopyToken,
    interpolateEmailCopy,
    introFromParagraphs,
    isSamePortalEmailPath,
    moveEmailParagraph,
    paragraphsFromIntro,
    replaceEmailParagraphs,
    stripEmailSalutation,
} = helper.exports;

const definition = {
    key: "status_update",
    defaults: {
        notification: "status_update",
        subject: "Status: {{ status }}",
        headline: "Application update",
        custom_body: "Your application has advanced.\n\nPlease review your next steps.",
        button_label: "Visit student portal",
        button_url: "",
        include_details: true,
        include_subjects: false,
        include_tuition: false,
    },
};

test("uses supplied catalog defaults for missing, null, or blank copy without mutating stored configuration", () => {
    const configuration = { subject: "", headline: null, custom_body: "  ", include_details: false, include_subjects: true };
    const draft = createEmailDraft(configuration, definition);
    assert.equal(draft.subject, "Status: {{ status }}");
    assert.equal(draft.headline, "Application update");
    assert.equal(introFromParagraphs(draft.paragraphs), "Your application has advanced.\n\nPlease review your next steps.");
    assert.equal(draft.include_details, false);
    assert.equal(draft.include_subjects, true);
    assert.deepEqual(configuration, { subject: "", headline: null, custom_body: "  ", include_details: false, include_subjects: true });
    assert.equal(configurationFromDraft({ ...draft, subject: "Only the subject changed" }).custom_body, "");
});

test("catalog intro becomes an override only when its effective copy changes", () => {
    const inherited = createEmailDraft({}, definition);
    const addedEmpty = replaceEmailParagraphs(inherited, [...inherited.paragraphs, { id: "empty", text: "" }]);
    assert.equal(configurationFromDraft(addedEmpty).custom_body, "");
    const edited = replaceEmailParagraphs(
        inherited,
        inherited.paragraphs.map((paragraph, index) => (index === 0 ? { ...paragraph, text: "Edited intro." } : paragraph)),
    );
    assert.equal(configurationFromDraft(edited).custom_body, "Edited intro.\n\nPlease review your next steps.");
    const moved = replaceEmailParagraphs(inherited, moveEmailParagraph(inherited.paragraphs, "paragraph-1", 1));
    assert.equal(configurationFromDraft(moved).custom_body, "Please review your next steps.\n\nYour application has advanced.");
    const undone = emailDraftHistoryReducer({ past: [inherited], present: edited, future: [] }, { type: "undo" });
    assert.equal(configurationFromDraft(undone.present).custom_body, "");
});

test("serializes paragraph order into the existing intro string, never HTML or layout configuration", () => {
    const draft = createEmailDraft({ custom_body: "First line.\nStill first paragraph.\n\nSecond paragraph." }, definition);
    const paragraphs = moveEmailParagraph(draft.paragraphs, "paragraph-2", 0);
    const payload = configurationFromDraft({ ...draft, paragraphs });
    assert.equal(payload.custom_body, "Second paragraph.\n\nFirst line.\nStill first paragraph.");
    assert.equal("paragraphs" in payload, false);
    assert.equal("introCustomized" in payload, false);
    assert.equal("blocks" in payload, false);
    assert.equal("html" in payload, false);
    assert.deepEqual(
        draft.paragraphs.map((paragraph) => paragraph.id),
        ["paragraph-1", "paragraph-2"],
    );
});

test("normalizes Windows line endings and removes empty editing blocks from sent intro prose", () => {
    const paragraphs = paragraphsFromIntro("First.\r\nContinued.\r\n\r\nSecond.");
    assert.equal(introFromParagraphs([...paragraphs, { id: "empty", text: " \n " }]), "First.\nContinued.\n\nSecond.");
    assert.deepEqual(paragraphsFromIntro(""), []);
    assert.equal(introFromParagraphs([]), "");
});

for (const salutation of [
    "Hello {{ student_name }},\n\n",
    "dear {{student_name}}, ",
    "HI {{ student_name }} ",
    "Congratulations {{ student_name }}! ",
]) {
    test(`strips the enrollment token salutation ${JSON.stringify(salutation)} on load and save`, () => {
        const paragraphs = paragraphsFromIntro(`${salutation}Your application is ready.`);
        assert.equal(paragraphs[0].text, "Your application is ready.");
        assert.equal(introFromParagraphs([{ id: "new", text: `${salutation}Your application is ready.` }]), "Your application is ready.");
    });
}

test("does not strip literal-name salutations or greetings embedded in a paragraph", () => {
    assert.equal(stripEmailSalutation("Hello Maria,\nThank you."), "Hello Maria,\nThank you.");
    assert.equal(stripEmailSalutation("Update: Hello {{ student_name }}, welcome."), "Update: Hello {{ student_name }}, welcome.");
});

test("supports only real enrollment template keys and canonicalizes the legacy assessment alias", () => {
    assert.equal(canonicalEmailTemplateKey("official_enrollment"), "assessment");
    for (const key of ["status_update", "academic_verified", "assessment", "custom"]) assert.equal(canonicalEmailTemplateKey(key), key);
    for (const key of ["two_factor_code", "password_reset", "magic_login_link", "signup_otp", "class_schedule_changed", null]) {
        assert.equal(canonicalEmailTemplateKey(key), null);
    }
});

test("interpolates only the server-supported token spellings, preserving unknown tokens and avoiding recursive expansion", () => {
    assert.equal(
        interpolateEmailCopy("{{ student_name }} / {{student_name}} / {{ student_name}} / {{ missing }}", { student_name: "Santos, Maria" }),
        "Santos, Maria / Santos, Maria / {{ student_name}} / {{ missing }}",
    );
    assert.equal(interpolateEmailCopy("{{ student_name }}", { student_name: "{{ status }}", status: "Verified" }), "{{ status }}");
});

test("inserts tokens at the caret and replaces a selected range rather than always appending", () => {
    assert.deepEqual(insertEmailCopyToken("Hello world!", "{{ student_name }}", 6, 11), { text: "Hello {{ student_name }}!", caret: 24 });
    assert.deepEqual(insertEmailCopyToken("Your status.", "{{ status }}", 5, 11), { text: "Your {{ status }}.", caret: 17 });
    assert.deepEqual(insertEmailCopyToken("Hello", "{{ student_name }}"), { text: "Hello {{ student_name }}", caret: 24 });
});

test("clamps stale caret ranges to the current text and preserves spacing around tokens", () => {
    assert.deepEqual(insertEmailCopyToken("Hi", "{{ status }}", 999, 999), { text: "Hi {{ status }}", caret: 15 });
    assert.equal(insertEmailCopyToken("world", "{{ student_name }}", -10, -1).text, "{{ student_name }} world");
});

test("ignores invalid paragraph reorder targets and retains stable block identities", () => {
    const paragraphs = paragraphsFromIntro("First.\n\nSecond.");
    assert.equal(moveEmailParagraph(paragraphs, "missing", 0), paragraphs);
    assert.equal(moveEmailParagraph(paragraphs, "paragraph-1", -1), paragraphs);
    assert.equal(moveEmailParagraph(paragraphs, "paragraph-1", 2), paragraphs);
    assert.equal(moveEmailParagraph(paragraphs, "paragraph-1", 0), paragraphs);
    assert.deepEqual(
        moveEmailParagraph(paragraphs, "paragraph-1", 1).map((paragraph) => paragraph.id),
        ["paragraph-2", "paragraph-1"],
    );
});

for (const path of ["", "  ", "/student", " /student/applications ", "/student?tab=application#status"]) {
    test(`accepts same-portal CTA path ${JSON.stringify(path)}`, () => assert.equal(isSamePortalEmailPath(path), true));
}

for (const path of [
    "https://evil.test",
    "//evil.test",
    "javascript:alert(1)",
    "student",
    "/\\evil.test",
    "/student path",
    "/student\npath",
    "/%5cevil.test",
    "/%0aevil.test",
    "/%09evil.test",
    "/%1fevil.test",
    "/%7fevil.test",
]) {
    test(`rejects non-portal or encoded unsafe CTA path ${JSON.stringify(path)}`, () => assert.equal(isSamePortalEmailPath(path), false));
}

test("undo and redo restore copy, paragraph order and section settings together", () => {
    const initial = createEmailDraft({}, definition);
    const history = { past: [], present: initial, future: [] };
    const changed = emailDraftHistoryReducer(history, {
        type: "change",
        update: (draft) => ({
            ...draft,
            subject: "New subject",
            paragraphs: moveEmailParagraph(draft.paragraphs, "paragraph-1", 1),
            include_subjects: true,
        }),
    });
    const undone = emailDraftHistoryReducer(changed, { type: "undo" });
    assert.deepEqual(undone.present, initial);
    const redone = emailDraftHistoryReducer(undone, { type: "redo" });
    assert.equal(redone.present.subject, "New subject");
    assert.equal(redone.present.include_subjects, true);
    assert.equal(redone.present.paragraphs[0].id, "paragraph-2");
    assert.equal(initial.subject, "Status: {{ status }}");
});

test("a new edit clears redo history and no-op edits do not create undo entries", () => {
    const initial = { past: [], present: createEmailDraft({}, definition), future: [] };
    assert.equal(emailDraftHistoryReducer(initial, { type: "undo" }), initial);
    assert.equal(emailDraftHistoryReducer(initial, { type: "redo" }), initial);
    assert.equal(emailDraftHistoryReducer(initial, { type: "change", update: (draft) => ({ ...draft }) }), initial);
    const changed = emailDraftHistoryReducer(initial, { type: "change", update: (draft) => ({ ...draft, subject: "Changed" }) });
    const undone = emailDraftHistoryReducer(changed, { type: "undo" });
    const replacement = emailDraftHistoryReducer(undone, { type: "change", update: (draft) => ({ ...draft, headline: "Different" }) });
    assert.equal(replacement.future.length, 0);
});

test("bounds typing history to fifty previous snapshots", () => {
    let history = { past: [], present: createEmailDraft({}, definition), future: [] };
    for (let index = 0; index < 100; index++)
        history = emailDraftHistoryReducer(history, { type: "change", update: (draft) => ({ ...draft, subject: String(index) }) });
    assert.equal(history.past.length, 50);
    assert.equal(history.present.subject, "99");
});
