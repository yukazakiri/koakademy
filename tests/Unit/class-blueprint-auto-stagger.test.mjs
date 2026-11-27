import assert from "node:assert/strict";
import { test } from "node:test";
import { autoStagger } from "../../resources/js/pages/administrators/classes/blueprint/auto-stagger.ts";

const slot = (day_of_week, start_time, end_time, room_id = 1) => ({ day_of_week, start_time, end_time, room_id, delivery_mode: "face_to_face", component_type: "lecture" });
const row = (client_key, overrides = {}) => ({ client_key, class_id: null, section: client_key, faculty_id: null, maximum_slots: 40, room_id: 1, schedules: [], ...overrides });
const rooms = [{ id: 1, label: "Room 10", capacity: 40 }];

test("a skipped section keeps its original reservations for later sections", () => {
    const original = [slot("Tuesday", "08:00", "09:30"), slot("Thursday", "08:00", "09:30")];
    const rows = [
        row("A", { class_id: 1, faculty_id: "F1", schedules: original }),
        row("B", { faculty_id: "F2" }),
        row("C", { class_id: 3, faculty_id: "F1", schedules: [slot("Friday", "08:00", "10:00", null)] }),
    ];
    const result = autoStagger(rows, new Set(["A"]), rooms, "face_to_face", true,
        [{ days: ["Tuesday", "Thursday"], start_time: "08:00", end_time: "09:30" }],
        [{ day: "Friday", start_time: "08:00", end_time: "10:00" }]);
    assert.deepEqual(result.rows[0].schedules, original);
    assert.deepEqual(result.rows[1].schedules, []);
    assert.deepEqual(result.skipped, ["A", "B"]);
});

test("a later lecture band is tried when the first conflicts with the only lab band", () => {
    const result = autoStagger([row("A")], new Set(), rooms, "face_to_face", true,
        [{ days: ["Friday"], start_time: "08:00", end_time: "11:00" }, { days: ["Monday"], start_time: "08:00", end_time: "11:00" }],
        [{ day: "Friday", start_time: "08:00", end_time: "10:00" }]);
    assert.deepEqual(result.rows[0].schedules.map((item) => [item.day_of_week, item.component_type]), [["Monday", "lecture"], ["Friday", "lab"]]);
    assert.equal(result.shifted, 1);
});
