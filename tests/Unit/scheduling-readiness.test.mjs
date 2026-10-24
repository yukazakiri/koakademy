import assert from "node:assert/strict";
import { test } from "node:test";
import { getSchedulingReadiness, scheduledHours } from "../../resources/js/pages/administrators/components/scheduling-readiness.ts";

test("weekly hours remain accurate across noon and after resizing a meeting", () => {
    assert.equal(
        scheduledHours([
            { start_time: "11:30 AM", end_time: "01:00 PM" },
            { start_time: "08:00:00", end_time: "09:15:00" },
        ]),
        2.75,
    );
    assert.equal(scheduledHours([{ start_time: "11:30 AM", end_time: "02:00 PM" }]), 2.5);
});

test("publish review exposes unscheduled and missing assignments from the complete term", () => {
    const classes = [
        {
            id: 1,
            subject_code: "IT 101",
            section: "A",
            faculty_name: "Mika Santos",
            required_hours: 3,
            schedules: [{ start_time: "08:00", end_time: "11:00", room_id: 1, status: "draft" }],
        },
        { id: 2, subject_code: "PE 101", section: "B", faculty_name: null, required_hours: 2, schedules: [] },
        {
            id: 3,
            subject_code: "GE 101",
            section: "C",
            faculty_name: "Mika Santos",
            required_hours: 3,
            schedules: [{ start_time: "10:00", end_time: "11:00", room_id: null, status: "published" }],
        },
    ];
    const review = getSchedulingReadiness(classes);
    assert.equal(review.length, 3);
    assert.deepEqual(review[0].warnings, []);
    assert.deepEqual(review[1].warnings, ["No sessions scheduled", "Instructor not assigned"]);
    assert.deepEqual(review[2].warnings, ["1 of 3 weekly hours", "Room not assigned to every session"]);
});

test("a roomless draft does not block unrelated class edits", async () => {
    const { step2IsValid, createDefaultSchedule } = await import("../../resources/js/pages/administrators/classes/lib/class-defaults.ts");
    const meeting = { ...createDefaultSchedule(0), id: 17, status: "draft", component_type: "lab" };
    assert.equal(meeting.room_id, null);
    assert.equal(step2IsValid({ schedules: [meeting] }), true);
    assert.notEqual(step2IsValid({ schedules: [{ ...meeting, end_time: "07:30" }] }), true);
    assert.notEqual(step2IsValid({ schedules: [meeting, { ...meeting, id: 18 }] }), true);
});

test("a saved class draft retains sessions awaiting room assignment", async () => {
    const { isClassDraft } = await import("../../resources/js/pages/administrators/classes/lib/class-defaults.ts");
    const draft = {
        classification: "college",
        course_codes: [1],
        subject_ids: [2],
        subject_code: "IT 101",
        subject_code_shs: "",
        settings: {},
        schedules: [{ day_of_week: "Monday", start_time: "08:00", end_time: "09:00", room_id: null }],
    };
    assert.equal(isClassDraft(draft), true);
    assert.equal(isClassDraft({ ...draft, schedules: [{ ...draft.schedules[0], room_id: "invalid" }] }), false);
});

test("class-form online meetings clear their rooms and preserve session metadata for both curricula", async () => {
    const { buildClassDefaults, buildClassStorePayload, createDefaultSchedule } =
        await import("../../resources/js/pages/administrators/classes/lib/class-defaults.ts");
    const defaults = buildClassDefaults({ semester: "1", school_year: "2026-2027", options: { rooms: [{ id: 4 }] } });
    const meeting = { ...createDefaultSchedule(7), delivery_mode: "online", id: 17, component_type: "lab", pattern_group_id: "group-a", status: "draft", subject_id: 2 };

    for (const classification of ["college", "shs"]) {
        const form = { ...defaults, classification, delivery_mode: "online", schedules: [meeting] };
        const payload = buildClassStorePayload(form);
        assert.equal(payload.delivery_mode, "online");
        assert.equal(payload.room_id, null);
        assert.deepEqual(payload.schedules, [{ ...meeting, room_id: null }]);
        assert.equal(form.room_id, 4);
        assert.equal(form.schedules[0].room_id, 7);
    }
});

test("class-form initialization and restored online drafts cannot retain stale room assignments", async () => {
    const { buildClassDefaults, createDefaultSchedule, isClassDraft, normalizeClassDelivery } =
        await import("../../resources/js/pages/administrators/classes/lib/class-defaults.ts");
    const defaults = buildClassDefaults({ semester: "1", school_year: "2026-2027", options: { rooms: [{ id: 4 }] } });
    const stored = JSON.parse(JSON.stringify({ ...defaults, delivery_mode: "online", schedules: [{ ...createDefaultSchedule(7), delivery_mode: "online" }] }));
    assert.equal(isClassDraft(stored), true);
    const restored = normalizeClassDelivery({ ...defaults, ...stored });
    assert.equal(restored.room_id, null);
    assert.equal(restored.schedules[0].room_id, null);
    const faceToFace = normalizeClassDelivery({ ...restored, delivery_mode: "face_to_face" });
    assert.equal(faceToFace.room_id, null);
    assert.equal(faceToFace.schedules[0].room_id, null);
    assert.equal(isClassDraft({ ...stored, delivery_mode: "hybrid" }), false);
});

test("class-form legacy drafts default to face to face and preserve existing room choices", async () => {
    const { buildClassDefaults, buildClassStorePayload, createDefaultSchedule, isClassDraft, normalizeClassDelivery } =
        await import("../../resources/js/pages/administrators/classes/lib/class-defaults.ts");
    const legacy = buildClassDefaults({ semester: "1", school_year: "2026-2027", options: { rooms: [{ id: 4 }] } });
    delete legacy.delivery_mode;
    legacy.schedules = [createDefaultSchedule(7), { ...createDefaultSchedule(null), day_of_week: "Tuesday" }];
    assert.equal(isClassDraft(legacy), true);
    const restored = normalizeClassDelivery(legacy);
    assert.equal(restored.delivery_mode, "face_to_face");
    assert.equal(restored.room_id, 4);
    assert.deepEqual(
        restored.schedules.map((meeting) => meeting.room_id),
        [7, null],
    );
    assert.equal(buildClassStorePayload(restored).delivery_mode, "face_to_face");
});

test("class-form face-to-face payloads allow an unassigned room without replacing it", async () => {
    const { buildClassDefaults, buildClassStorePayload, createDefaultSchedule, normalizeClassDelivery, step2IsValid } =
        await import("../../resources/js/pages/administrators/classes/lib/class-defaults.ts");
    const defaults = buildClassDefaults({ semester: "1", school_year: "2026-2027", options: { rooms: [{ id: 4 }] } });
    const form = { ...defaults, room_id: null, schedules: [createDefaultSchedule(null)] };
    assert.equal(step2IsValid(form), true);
    assert.equal(normalizeClassDelivery(form).room_id, null);
    const payload = buildClassStorePayload(form);
    assert.equal(payload.room_id, null);
    assert.equal(payload.schedules[0].room_id, null);
    assert.deepEqual(buildClassStorePayload({ ...form, schedules: [] }).schedules, []);
});

test("mixed class drafts and submissions retain face-to-face rooms and clear only online rooms", async () => {
    const { buildClassDefaults, buildClassStorePayload, createDefaultSchedule, isClassDraft, normalizeClassDelivery, meetingDeliverySummary } =
        await import("../../resources/js/pages/administrators/classes/lib/class-defaults.ts");
    const defaults = buildClassDefaults({ semester: "1", school_year: "2026-2027", options: { rooms: [{ id: 4 }] } });
    const schedules = [
        { ...createDefaultSchedule(4), id: 1, day_of_week: "Monday" },
        { ...createDefaultSchedule(4), id: 2, day_of_week: "Wednesday" },
        { ...createDefaultSchedule(7), id: 3, day_of_week: "Friday", delivery_mode: "online" },
    ];
    const draft = { ...defaults, schedules };
    assert.equal(isClassDraft(JSON.parse(JSON.stringify(draft))), true);
    const payload = buildClassStorePayload(draft);
    assert.deepEqual(payload.schedules.map((meeting) => [meeting.delivery_mode, meeting.room_id]), [["face_to_face", 4], ["face_to_face", 4], ["online", null]]);
    assert.equal(meetingDeliverySummary(payload.schedules), "mixed");
    const newDefault = normalizeClassDelivery({ ...payload, delivery_mode: "online" });
    assert.deepEqual(newDefault.schedules, payload.schedules);
    assert.equal(isClassDraft({ ...draft, schedules: [{ ...schedules[0], delivery_mode: "hybrid" }] }), false);
});

test("legacy online drafts inherit a meeting mode without losing metadata", async () => {
    const { normalizeClassDelivery, meetingDeliverySummary } = await import("../../resources/js/pages/administrators/classes/lib/class-defaults.ts");
    const legacy = { delivery_mode: "online", room_id: 4, schedules: [{ id: 17, day_of_week: "Monday", start_time: "08:00", end_time: "09:00", room_id: 4, component_type: "lab", pattern_group_id: "mwf" }] };
    const restored = normalizeClassDelivery(legacy);
    assert.deepEqual(restored.schedules[0], { ...legacy.schedules[0], delivery_mode: "online", room_id: null });
    assert.equal(meetingDeliverySummary(restored.schedules), "online");
});

test("mixed schedule review requires rooms only for face-to-face meetings", () => {
    const item = { id: 1, subject_code: "IT101", section: "A", faculty_name: "Mika Santos", required_hours: 3, delivery_mode: "online", schedules: [
        { start_time: "08:00", end_time: "09:00", delivery_mode: "face_to_face", room_id: 4 },
        { start_time: "08:00", end_time: "09:00", delivery_mode: "face_to_face", room_id: 4 },
        { start_time: "08:00", end_time: "09:00", delivery_mode: "online", room_id: null },
    ] };
    assert.deepEqual(getSchedulingReadiness([item])[0].warnings, []);
    item.schedules[0].room_id = null;
    assert.deepEqual(getSchedulingReadiness([item])[0].warnings, ["Room not assigned to every session"]);
});
