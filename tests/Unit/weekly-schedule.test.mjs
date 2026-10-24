import assert from "node:assert/strict";
import { test } from "node:test";
import {
    buildScheduleDays,
    scheduleMinutes,
    scheduleTimeRange,
    scheduleWindow,
} from "../../resources/js/components/administrators/classes/weekly-schedule-utils.ts";
import { matchesPlannerGroup } from "../../resources/js/pages/administrators/components/scheduling-view.ts";

test("program filters support SHS classes without college program IDs", () => {
    const shs = { course_ids: null, year_levels: ["Grade 11"], course_year_levels: {} };
    assert.equal(matchesPlannerGroup(shs, "1", "all"), false);
    assert.equal(matchesPlannerGroup(shs, "all", "Grade 11"), true);
    const college = { course_ids: [1, 2], year_levels: ["1", "2"], course_year_levels: { 1: ["1"], 2: ["2"] } };
    assert.equal(matchesPlannerGroup(college, "1", "2"), false);
    assert.equal(matchesPlannerGroup(college, "2", "2"), true);
});

const event = (id, start, end) => ({ id, day: "Monday", start, end, title: id });

test("planner and record time formats render the same teaching hours", () => {
    assert.equal(scheduleMinutes("12:00 AM"), 0);
    assert.equal(scheduleMinutes("12:00 PM"), 720);
    assert.equal(scheduleMinutes("1:15 PM"), scheduleMinutes("13:15:00"));
    assert.equal(scheduleMinutes("24:01"), NaN);
    assert.equal(scheduleMinutes("13:00 PM"), NaN);
    assert.equal(scheduleTimeRange(480, 540), "8–9 AM");
    assert.equal(scheduleTimeRange(690, 780), "11:30 AM–1 PM");
    const days = buildScheduleDays([event("online", "08:00 AM", "09:00 AM"), event("campus", "13:00:00", "14:00:00")]);
    assert.equal(days[0].blocks.length, 2);
    assert.equal(days[0].blocks[1].startMinutes, 780);
});

test("overlapping sessions get separate lanes while adjacent sessions share a lane", () => {
    const [day] = buildScheduleDays([
        event("a", "08:00", "10:00"),
        event("b", "09:00", "11:00"),
        event("c", "10:00", "12:00"),
        event("d", "12:00", "13:00"),
    ]);
    const [a, b, c, d] = day.blocks;
    assert.notEqual(a.lane, b.lane);
    assert.notEqual(b.lane, c.lane);
    assert.equal(a.lane, c.lane);
    assert.deepEqual(
        day.blocks.map((block) => block.laneCount),
        [2, 2, 2, 1],
    );
    assert.equal(d.lane, 0);
});

test("calendar window contains late sessions and ignores malformed times", () => {
    const blocks = buildScheduleDays([
        event("late", "23:00", "24:00"),
        event("invalid", "bad", "10:00"),
        event("backward", "11:00", "10:00"),
    ]).flatMap((day) => day.blocks);
    assert.equal(blocks.length, 1);
    assert.deepEqual(scheduleWindow(blocks), { start: 1200, end: 1440 });
    assert.equal(scheduleWindow([]), null);
});

test("online classes do not need room assignments during schedule review", async () => {
    const { getSchedulingReadiness } = await import("../../resources/js/pages/administrators/components/scheduling-readiness.ts");
    const base = {
        id: 1,
        subject_code: "IT101",
        section: "A",
        faculty_name: "Mika Santos",
        required_hours: 3,
        schedules: [{ start_time: "08:00", end_time: "11:00", room_id: null }],
    };
    assert.deepEqual(getSchedulingReadiness([{ ...base, delivery_mode: "online" }])[0].warnings, []);
    assert.deepEqual(getSchedulingReadiness([{ ...base, delivery_mode: "face_to_face" }])[0].warnings, ["Room not assigned to every session"]);
});
