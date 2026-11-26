import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire, Module } from "node:module";
import { test } from "node:test";
import { fileURLToPath } from "node:url";
import ts from "typescript";

function loadTsModule(relativeUrl, overrides = {}) {
    const url = new URL(relativeUrl, import.meta.url);
    const filename = fileURLToPath(url);
    const { outputText } = ts.transpileModule(readFileSync(url, "utf8"), {
        fileName: filename,
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    });
    const mod = new Module(filename);
    const require = createRequire(url);
    mod.require = (id) => (id in overrides ? overrides[id] : require(id));
    mod._compile(outputText, filename);
    return mod.exports;
}

const classDefaults = loadTsModule(
    "../../resources/js/pages/administrators/classes/lib/class-defaults.ts",
);
const conflicts = loadTsModule(
    "../../resources/js/pages/administrators/enrollments/workbench/schedule-conflicts.ts",
    {
        "../../classes/lib/class-defaults": classDefaults,
    },
);

const {
    DEFAULT_ENROLLMENT_SCHEDULING_POLICY,
    checksStudentScheduleConflicts,
    detectDraftScheduleConflicts,
    findCandidateSectionConflicts,
    schedulesOverlap,
} = conflicts;

function makeDraftSubject({ id, code, sectionId, sectionName = "A", deliveryMode = "face_to_face", schedules }) {
    return {
        subject_id: id,
        subject_code: code,
        subject_title: `${code} Title`,
        class_id: sectionId,
        is_modular: false,
        exclude_from_tuition: false,
        lecture_units: 3,
        laboratory_units: 0,
        lecture_fee: 0,
        laboratory_fee: 0,
        lec_per_unit: 0,
        lab_per_unit: 0,
        sections: [
            {
                id: sectionId,
                section: sectionName,
                faculty: "Prof. Test",
                enrolled_count: 5,
                max_slots: 40,
                available_slots: 35,
                is_full: false,
                delivery_mode: deliveryMode,
                schedule: "",
                schedules,
                label: sectionName,
            },
        ],
    };
}

test("default policy exempts asynchronous online schedules from conflicts while checking face-to-face and synchronous online", () => {
    assert.equal(checksStudentScheduleConflicts("face_to_face", DEFAULT_ENROLLMENT_SCHEDULING_POLICY), true);
    assert.equal(checksStudentScheduleConflicts("online_synchronous", DEFAULT_ENROLLMENT_SCHEDULING_POLICY), true);
    assert.equal(checksStudentScheduleConflicts("online", DEFAULT_ENROLLMENT_SCHEDULING_POLICY), false);
    assert.equal(checksStudentScheduleConflicts("online_asynchronous", DEFAULT_ENROLLMENT_SCHEDULING_POLICY), false);

    const f2f = { day: "Monday", start_time: "08:00", end_time: "10:00", room: "Room 101", delivery_mode: "face_to_face" };
    const asyncOnline = { day: "Monday", start_time: "08:00", end_time: "10:00", room: "Online (Async)", delivery_mode: "online" };
    const asyncAlias = { day: "Monday", start_time: "08:00", end_time: "10:00", room: "Online (Async)", delivery_mode: "online_asynchronous" };
    const syncOnline = { day: "Monday", start_time: "08:00", end_time: "10:00", room: "Online (Sync)", delivery_mode: "online_synchronous" };

    // F2F vs Async Online -> NO conflict
    assert.equal(schedulesOverlap(f2f, asyncOnline, DEFAULT_ENROLLMENT_SCHEDULING_POLICY), null);
    assert.equal(schedulesOverlap(f2f, asyncAlias, DEFAULT_ENROLLMENT_SCHEDULING_POLICY), null);

    // Async Online vs Async Online -> NO conflict
    assert.equal(schedulesOverlap(asyncOnline, asyncAlias, DEFAULT_ENROLLMENT_SCHEDULING_POLICY), null);

    // Async Online vs Sync Online -> NO conflict
    assert.equal(schedulesOverlap(asyncOnline, syncOnline, DEFAULT_ENROLLMENT_SCHEDULING_POLICY), null);

    // F2F vs F2F -> CONFLICT
    assert.match(schedulesOverlap(f2f, f2f, DEFAULT_ENROLLMENT_SCHEDULING_POLICY), /Mon 8:00 AM–10:00 AM/);

    // F2F vs Sync Online -> CONFLICT
    assert.match(schedulesOverlap(f2f, syncOnline, DEFAULT_ENROLLMENT_SCHEDULING_POLICY), /Mon 8:00 AM–10:00 AM/);
});

test("respects check_asynchronous_online_conflicts and check_synchronous_online_conflicts policy toggles", () => {
    const flippedPolicy = {
        ...DEFAULT_ENROLLMENT_SCHEDULING_POLICY,
        check_synchronous_online_conflicts: false,
        check_asynchronous_online_conflicts: true,
    };

    const f2f = { day: "Tuesday", start_time: "09:00", end_time: "11:00", room: "Room 102", delivery_mode: "face_to_face" };
    const asyncOnline = { day: "Tuesday", start_time: "10:00", end_time: "12:00", room: "Online (Async)", delivery_mode: "online" };
    const syncOnline = { day: "Tuesday", start_time: "10:00", end_time: "12:00", room: "Online (Sync)", delivery_mode: "online_synchronous" };

    // Now async online clashes with F2F
    assert.match(schedulesOverlap(f2f, asyncOnline, flippedPolicy), /Tue 10:00 AM–11:00 AM/);
    // And sync online is exempt
    assert.equal(schedulesOverlap(f2f, syncOnline, flippedPolicy), null);
});

test("disables all student enrollment conflict checks when check_student_cohort_conflicts is false", () => {
    const disabledPolicy = {
        ...DEFAULT_ENROLLMENT_SCHEDULING_POLICY,
        check_student_cohort_conflicts: false,
        check_synchronous_online_conflicts: true,
        check_asynchronous_online_conflicts: true,
    };

    const subA = makeDraftSubject({
        id: 1,
        code: "CS101",
        sectionId: 10,
        schedules: [{ day: "Monday", start_time: "08:00", end_time: "10:00", room: "R1", delivery_mode: "face_to_face" }],
    });
    const subB = makeDraftSubject({
        id: 2,
        code: "CS102",
        sectionId: 20,
        schedules: [{ day: "Monday", start_time: "08:00", end_time: "10:00", room: "R2", delivery_mode: "face_to_face" }],
    });

    assert.deepEqual(detectDraftScheduleConflicts([subA, subB], disabledPolicy), []);
    assert.deepEqual(findCandidateSectionConflicts(2, subB.sections[0], [subA, subB], disabledPolicy), []);
});

test("handles mixed-delivery classes per meeting block and falls back to section delivery_mode", () => {
    // Subject A has two Monday sessions: [0] F2F 08:00-09:00, [1] Async online 10:00-11:00
    const mixedA = makeDraftSubject({
        id: 1,
        code: "IT101",
        sectionId: 101,
        deliveryMode: "face_to_face",
        schedules: [
            { day: "Monday", start_time: "08:00", end_time: "09:00", room: "Lab 1", delivery_mode: "face_to_face" },
            { day: "Monday", start_time: "10:00", end_time: "11:00", room: "Online (Async)", delivery_mode: "online" },
        ],
    });
    // Subject B overlaps ONLY the 10:00-11:00 async block
    const f2fAt10 = makeDraftSubject({
        id: 2,
        code: "IT102",
        sectionId: 102,
        deliveryMode: "face_to_face",
        schedules: [
            { day: "Monday", start_time: "10:00", end_time: "11:00", room: "Room 2", delivery_mode: "face_to_face" },
        ],
    });
    // Subject C overlaps the 08:00-09:00 F2F block
    const f2fAt8 = makeDraftSubject({
        id: 3,
        code: "IT103",
        sectionId: 103,
        deliveryMode: "face_to_face",
        schedules: [
            { day: "Monday", start_time: "08:00", end_time: "09:00", room: "Room 3", delivery_mode: "face_to_face" },
        ],
    });
    // Subject D omits schedule-level delivery_mode and relies on section-level delivery_mode="online"
    const sectionLevelAsync = makeDraftSubject({
        id: 4,
        code: "IT104",
        sectionId: 104,
        deliveryMode: "online",
        schedules: [
            { day: "Monday", start_time: "08:00", end_time: "09:00", room: null },
        ],
    });

    const detected = detectDraftScheduleConflicts(
        [mixedA, f2fAt10, f2fAt8, sectionLevelAsync],
        DEFAULT_ENROLLMENT_SCHEDULING_POLICY,
    );

    assert.equal(detected.length, 1);
    assert.equal(detected[0].subjectACode, "IT101");
    assert.equal(detected[0].subjectBCode, "IT103");
    assert.equal(detected[0].scheduleAIndex, 0);
    assert.equal(detected[0].scheduleBIndex, 0);

    // Candidate section check for sectionLevelAsync against mixedA should report zero warnings
    assert.deepEqual(
        findCandidateSectionConflicts(4, sectionLevelAsync.sections[0], [mixedA, f2fAt10, f2fAt8], DEFAULT_ENROLLMENT_SCHEDULING_POLICY),
        [],
    );
});
