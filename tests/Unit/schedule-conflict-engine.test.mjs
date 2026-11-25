import assert from "node:assert/strict";
import { test } from "node:test";
import { simulatePlacementConflicts } from "../../resources/js/pages/administrators/scheduling/lib/conflict-engine.ts";
import {
    getVisibleDays,
    packOverlappingBlocks,
    parseTimeToMinutes,
    snapMinutes,
} from "../../resources/js/pages/administrators/scheduling/lib/time-geometry.ts";

const defaultPolicy = {
    default_online_delivery_mode: "online",
    check_room_conflicts: true,
    check_faculty_conflicts: true,
    check_student_cohort_conflicts: true,
    check_synchronous_online_conflicts: true,
    check_asynchronous_online_conflicts: false,
    allow_sunday_scheduling: false,
};

const baseClasses = [
    {
        id: 1,
        subject_code: "CS101",
        subject_title: "Intro to CS",
        section: "A",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: {},
        classification: "college",
        delivery_mode: "face_to_face",
        faculty_id: "fac-1",
        faculty_name: "Dr. Ada",
        room_id: 10,
        room_name: "Room 101",
        courses: "BSCS",
        course_ids: [1],
        student_count: 35,
        schedules: [
            {
                id: 100,
                day_of_week: "Monday",
                start_time: "08:00 AM",
                end_time: "09:30 AM",
                time_range: "08:00 AM - 09:30 AM",
                room: "Room 101",
                room_id: 10,
                delivery_mode: "face_to_face",
            },
        ],
    },
    {
        id: 2,
        subject_code: "MATH101",
        subject_title: "Calculus",
        section: "A",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: {},
        classification: "college",
        delivery_mode: "face_to_face",
        faculty_id: "fac-2",
        faculty_name: "Prof. Euler",
        room_id: 20,
        room_name: "Room 102",
        courses: "BSCS",
        course_ids: [1],
        student_count: 35,
        schedules: [],
    },
];

test("detects simultaneous room, faculty, and cohort conflicts", () => {
    const candidateClass = { ...baseClasses[1], faculty_id: "fac-1" };
    const result = simulatePlacementConflicts(
        {
            classItem: candidateClass,
            day_of_week: "Monday",
            startMin: 8 * 60 + 30,
            endMin: 10 * 60,
            room_id: 10,
            delivery_mode: "face_to_face",
        },
        baseClasses,
        defaultPolicy,
    );

    assert.equal(result.hasConflict, true);
    assert.equal(result.hits.length, 1);
    assert.deepEqual(result.hits[0].reasons, ["room", "faculty", "section"]);
    assert.equal(result.hits[0].overlapMinutes, 60);
});

test("skips conflicts for asynchronous online by default but checks synchronous online", () => {
    const asyncResult = simulatePlacementConflicts(
        {
            classItem: baseClasses[1],
            day_of_week: "Monday",
            startMin: 8 * 60 + 30,
            endMin: 10 * 60,
            room_id: null,
            delivery_mode: "online",
        },
        baseClasses,
        defaultPolicy,
    );
    assert.equal(asyncResult.hasConflict, false);

    const syncResult = simulatePlacementConflicts(
        {
            classItem: baseClasses[1],
            day_of_week: "Monday",
            startMin: 8 * 60 + 30,
            endMin: 10 * 60,
            room_id: null,
            delivery_mode: "online_synchronous",
        },
        baseClasses,
        defaultPolicy,
    );
    assert.equal(syncResult.hasConflict, true);
    assert.deepEqual(syncResult.hits[0].reasons, ["section"]);
});

test("time geometry parses 12h/24h, snaps 15m, packs overlapping lanes, and handles Sunday visibility", () => {
    assert.equal(parseTimeToMinutes("08:30 AM"), 510);
    assert.equal(parseTimeToMinutes("1:15 PM"), 795);
    assert.equal(parseTimeToMinutes("14:45"), 885);
    assert.equal(snapMinutes(518, 15), 525);

    assert.equal(getVisibleDays(false, false).length, 6);
    assert.equal(getVisibleDays(true, false).length, 7);
    assert.equal(getVisibleDays(false, true).length, 7);

    const packed = packOverlappingBlocks([
        { key: "a", startMin: 480, endMin: 600 },
        { key: "b", startMin: 540, endMin: 660 },
        { key: "c", startMin: 600, endMin: 720 },
    ]);
    assert.equal(packed.get("a").laneCount, 2);
    assert.equal(packed.get("b").lane, 1);
    assert.equal(packed.get("c").lane, 0);
});
