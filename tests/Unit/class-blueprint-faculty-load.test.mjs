import assert from "node:assert/strict";
import { test } from "node:test";
import { facultyLoadDeltas } from "../../resources/js/pages/administrators/classes/blueprint/faculty-load.ts";

const threeHours = [{ start_time: "08:00", end_time: "11:00" }];
const twoHours = [{ start_time: "13:00", end_time: "15:00" }];

test("existing unchanged and removed rows do not inflate the instructor's term load", () => {
    const original = [{ class_id: 1, faculty_id: "a", schedules: threeHours }, { class_id: 2, faculty_id: "a", schedules: twoHours }];
    assert.equal(facultyLoadDeltas([original[0]], original).get("a"), 0);
});

test("reassignments subtract old hours and new sections add their staged hours", () => {
    const original = [{ class_id: 1, faculty_id: "a", schedules: threeHours }];
    const staged = [{ class_id: 1, faculty_id: "b", schedules: twoHours }, { class_id: null, faculty_id: "a", schedules: twoHours }];
    const deltas = facultyLoadDeltas(staged, original);
    assert.equal(deltas.get("a"), -1);
    assert.equal(deltas.get("b"), 2);
});
