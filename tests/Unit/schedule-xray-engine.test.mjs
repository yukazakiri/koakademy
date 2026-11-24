import assert from "node:assert/strict";
import { test } from "node:test";
import { evaluatePatternFit, evaluateXRayGrid } from "../../resources/js/pages/administrators/schedule-xray/lib/xray-engine.ts";

const policy = {
    default_online_delivery_mode: "online",
    check_room_conflicts: true,
    check_faculty_conflicts: true,
    check_student_cohort_conflicts: true,
    check_synchronous_online_conflicts: true,
    check_asynchronous_online_conflicts: false,
    allow_sunday_scheduling: false,
};

const rooms = [
    { id: 1, name: "Room 101", class_code: "R101", capacity: 40, type: "lecture", building: "Main" },
    { id: 2, name: "Room 102", class_code: "R102", capacity: 45, type: "lecture", building: "Main" },
];

const classes = [
    {
        id: 1,
        subject_code: "BA111",
        subject_title: "Fundamentals of Business",
        section: "A",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: {},
        classification: "college",
        delivery_mode: "face_to_face",
        faculty_id: "fac-ana",
        faculty_name: "Ana Rodriguez",
        room_id: 1,
        room_name: "Room 101",
        courses: "BSBA-FM",
        course_ids: [10],
        student_count: 35,
        maximum_slots: 40,
        schedules: [
            {
                id: 501,
                day_of_week: "Monday",
                start_time: "09:00 AM",
                end_time: "10:30 AM",
                time_range: "09:00 AM - 10:30 AM",
                room: "Room 101",
                room_id: 1,
                delivery_mode: "face_to_face",
            },
        ],
    },
    {
        id: 2,
        subject_code: "HRM312",
        subject_title: "HR Management",
        section: "B",
        grade_level: "3rd Year",
        year_levels: ["3rd Year"],
        course_year_levels: {},
        classification: "college",
        delivery_mode: "face_to_face",
        faculty_id: "fac-ana",
        faculty_name: "Ana Rodriguez",
        room_id: 2,
        room_name: "Room 102",
        courses: "BSBA-HRM",
        course_ids: [11],
        student_count: 30,
        maximum_slots: 40,
        schedules: [
            {
                id: 502,
                day_of_week: "Tuesday",
                start_time: "10:00 AM",
                end_time: "11:30 AM",
                time_range: "10:00 AM - 11:30 AM",
                room: "Room 102",
                room_id: 2,
                delivery_mode: "face_to_face",
            },
        ],
    },
    {
        id: 3,
        subject_code: "MATH111",
        subject_title: "College Algebra",
        section: "A",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: {},
        classification: "college",
        delivery_mode: "face_to_face",
        faculty_id: "fac-pat",
        faculty_name: "Patricia Villanueva",
        room_id: 2,
        room_name: "Room 102",
        courses: "BSBA-FM",
        course_ids: [10],
        student_count: 35,
        maximum_slots: 40,
        schedules: [
            {
                id: 503,
                day_of_week: "Wednesday",
                start_time: "01:00 PM",
                end_time: "02:30 PM",
                time_range: "01:00 PM - 02:30 PM",
                room: "Room 102",
                room_id: 2,
                delivery_mode: "face_to_face",
            },
        ],
    },
];

test("X-Ray marks current session, faculty blockers, section blockers, and open rooms accurately", () => {
    const grid = evaluateXRayGrid({
        targetClass: classes[0],
        allClasses: classes,
        rooms,
        policy,
        days: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
        startHours: [8, 9, 10, 13],
        durationMinutes: 90,
        deliveryMode: "face_to_face",
    });

    // Monday 9 AM is current session of BA111
    assert.equal(grid.cells.get("Monday:9")?.status, "current");

    // Tuesday 10 AM is blocked by Ana teaching HRM312
    const tue10 = grid.cells.get("Tuesday:10");
    assert.equal(tue10?.status, "blocked");
    assert.equal(tue10?.blockerKind, "faculty");
    assert.equal(tue10?.shortReason, "Ana busy");

    // Wednesday 1 PM is blocked by Section A already in MATH111
    const wed13 = grid.cells.get("Wednesday:13");
    assert.equal(wed13?.status, "blocked");
    assert.equal(wed13?.blockerKind, "section");
    assert.equal(wed13?.shortReason, "Sec A in MATH111");

    // Thursday 8 AM is completely open with both rooms free
    const thu8 = grid.cells.get("Thursday:8");
    assert.equal(thu8?.status, "open");
    assert.equal(thu8?.openRooms.length, 2);

    // TTh 8 AM pattern is 100% open with common rooms
    const tth = evaluatePatternFit(grid, ["Tuesday", "Thursday"], 8, false);
    assert.equal(tth.allOpen, true);
    assert.equal(tth.commonRooms.length, 2);
});

test("X-Ray opens asynchronous online slots even when teacher & section are busy under default policy", () => {
    const grid = evaluateXRayGrid({
        targetClass: classes[0],
        allClasses: classes,
        rooms,
        policy,
        days: ["Tuesday", "Wednesday"],
        startHours: [10, 13],
        durationMinutes: 90,
        deliveryMode: "online",
    });

    assert.equal(grid.cells.get("Tuesday:10")?.status, "open");
    assert.equal(grid.cells.get("Wednesday:13")?.status, "open");
});
