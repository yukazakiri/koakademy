import test from "node:test";
import assert from "node:assert/strict";
import {
    buildCohortWeekModel,
    buildRoomWeekModel,
    buildTeacherWeekModel,
    extractCohortOptions,
    getCanvasDays,
} from "../../resources/js/pages/administrators/schedule-canvas/lib/canvas-engine.ts";

const sampleRooms = [
    { id: 1, name: "Computer Lab 1", class_code: "CL1", capacity: 35, type: "laboratory", building: "IT" },
    { id: 2, name: "Room 201", class_code: "R201", capacity: 40, type: "lecture", building: "Main" },
];

const sampleFaculty = [
    { id: "fac-1", name: "Ana Rodriguez", department: "Computing" },
];

const sampleClasses = [
    {
        id: 101,
        subject_code: "IT111",
        subject_title: "Intro to Computing",
        section: "A",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: { BSIT: ["1st Year"], BSCS: ["1st Year"] },
        faculty_id: "fac-1",
        faculty_name: "Ana Rodriguez",
        room_id: 1,
        room_name: "Computer Lab 1",
        courses: "BSIT, BSCS",
        course_ids: [1, 2],
        student_count: 38,
        schedules: [
            { id: 1, day_of_week: "Monday", start_time: "08:00 AM", end_time: "09:30 AM", time_range: "08:00 AM - 09:30 AM", room: "Computer Lab 1", room_id: 1, delivery_mode: "face_to_face" },
        ],
    },
    {
        id: 102,
        subject_code: "GE102",
        subject_title: "Readings in History",
        section: "A",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: { BSIT: ["1st Year"] },
        faculty_id: "fac-1",
        faculty_name: "Ana Rodriguez",
        room_id: 2,
        room_name: "Room 201",
        courses: "BSIT",
        course_ids: [1],
        student_count: 30,
        schedules: [
            { id: 2, day_of_week: "Monday", start_time: "09:30 AM", end_time: "11:00 AM", time_range: "09:30 AM - 11:00 AM", room: "Room 201", room_id: 2, delivery_mode: "face_to_face" },
        ],
    },
    {
        id: 103,
        subject_code: "PE101",
        subject_title: "Physical Fitness",
        section: "A",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: { BSIT: ["1st Year"] },
        faculty_id: "fac-2",
        faculty_name: "Other Teacher",
        room_id: 1,
        room_name: "Computer Lab 1",
        courses: "BSIT",
        course_ids: [1],
        student_count: 25,
        schedules: [
            { id: 3, day_of_week: "Monday", start_time: "03:00 PM", end_time: "04:30 PM", time_range: "03:00 PM - 04:30 PM", room: "Computer Lab 1", room_id: 1, delivery_mode: "face_to_face" },
        ],
    },
];

test("Room tab detects overcrowded class and computes open room gaps cleanly", () => {
    const days = getCanvasDays(false);
    const model = buildRoomWeekModel({
        room: sampleRooms[0],
        days,
        classes: sampleClasses,
        conflicts: [],
    });

    assert.equal(model.sessionCount, 2);
    assert.equal(model.weeklyHours, 3);
    assert.equal(model.blocksByDay.Monday[0].overcrowded, true); // 38 > 35 seats
    // Open gap between 09:30 AM and 03:00 PM (5.5h)
    assert.equal(model.openGapsByDay.Monday.some((g) => g.startMin === 9 * 60 + 30 && g.endMin === 15 * 60), true);
});

test("Students & Sections tab splits multi-course offerings and spots long student idle gaps", () => {
    const opts = extractCohortOptions(sampleClasses);
    assert.deepEqual(opts.programs, ["BSCS", "BSIT"]);

    const model = buildCohortWeekModel({
        program: "BSIT",
        yearLevel: "1st Year",
        section: "A",
        days: getCanvasDays(false),
        classes: sampleClasses,
        conflicts: [],
    });

    assert.equal(model.subjectCount, 3);
    assert.equal(model.hoursByDay.Monday, 4.5);
    // 11:00 AM -> 3:00 PM = 4h idle gap
    assert.equal(model.idleGapsByDay.Monday.length, 1);
    assert.equal(model.idleGapsByDay.Monday[0].durationMin, 240);
});

test("Teachers tab detects zero-minute back-to-back room change handoffs", () => {
    const model = buildTeacherWeekModel({
        faculty: sampleFaculty[0],
        days: getCanvasDays(false),
        classes: sampleClasses,
        conflicts: [],
    });

    assert.equal(model.weeklyHours, 3);
    assert.equal(model.handoffCount, 1);
    assert.equal(model.handoffsByDay.Monday[0].fromRoom, "Computer Lab 1");
    assert.equal(model.handoffsByDay.Monday[0].toRoom, "Room 201");
    assert.equal(model.handoffsByDay.Monday[0].gapMinutes, 0);
});
