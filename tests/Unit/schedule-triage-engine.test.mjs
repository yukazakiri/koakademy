import assert from "node:assert/strict";
import { test } from "node:test";
import { buildTriageQueue } from "../../resources/js/pages/administrators/schedule-triage/lib/triage-engine.ts";

const classes = [
    {
        id: 1,
        subject_code: "IT111",
        subject_title: "Intro to Computing",
        section: "A",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: {},
        courses: "BSIT",
        course_ids: [1],
        faculty_id: "f1",
        faculty_name: "Roberto Cruz",
        room_id: 1,
        room_name: "Lab 1",
        student_count: 35,
        required_hours: 2,
        schedules: [
            { id: 10, day_of_week: "Monday", start_time: "08:00 AM", end_time: "10:00 AM", time_range: "08:00 AM - 10:00 AM", room: "Lab 1", room_id: 1 },
        ],
    },
    {
        id: 2,
        subject_code: "GE102",
        subject_title: "Readings",
        section: "A",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: {},
        courses: "BSIT",
        course_ids: [1],
        faculty_id: "f2",
        faculty_name: "Pat V",
        room_id: 1,
        room_name: "Lab 1",
        student_count: 35,
        required_hours: 1.5,
        schedules: [
            { id: 11, day_of_week: "Monday", start_time: "08:00 AM", end_time: "09:30 AM", time_range: "08:00 AM - 09:30 AM", room: "Lab 1", room_id: 1 },
        ],
    },
    {
        id: 3,
        subject_code: "CS105",
        subject_title: "Algorithms",
        section: "B",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: {},
        courses: "BSCS",
        course_ids: [2],
        faculty_id: "f3",
        faculty_name: "Grace H",
        room_id: 1,
        room_name: "Lab 1",
        student_count: 30,
        required_hours: 1.5,
        schedules: [
            { id: 12, day_of_week: "Monday", start_time: "08:30 AM", end_time: "10:00 AM", time_range: "08:30 AM - 10:00 AM", room: "Lab 1", room_id: 1 },
        ],
    },
    {
        id: 4,
        subject_code: "PE101",
        subject_title: "Fitness",
        section: "A",
        grade_level: "1st Year",
        year_levels: ["1st Year"],
        course_year_levels: {},
        courses: "BSIT",
        course_ids: [1],
        faculty_id: "f4",
        faculty_name: "Coach Ed",
        room_id: 2,
        room_name: "Gym",
        student_count: 30,
        required_hours: 2,
        schedules: [],
    },
];

const conflicts = [
    {
        day: "Monday",
        time: "08:00 AM - 10:00 AM vs 08:00 AM - 09:30 AM",
        schedule_1_id: 10,
        schedule_2_id: 11,
        room_id: 1,
        class_1: { id: 1, subject_code: "IT111", section: "A", room: "Lab 1", faculty: "Roberto Cruz" },
        class_2: { id: 2, subject_code: "GE102", section: "A", room: "Lab 1", faculty: "Pat V" },
        conflict_type: "room",
        conflict_types: ["room", "section"],
    },
    {
        day: "Monday",
        time: "08:00 AM - 10:00 AM vs 08:30 AM - 10:00 AM",
        schedule_1_id: 10,
        schedule_2_id: 12,
        room_id: 1,
        class_1: { id: 1, subject_code: "IT111", section: "A", room: "Lab 1", faculty: "Roberto Cruz" },
        class_2: { id: 3, subject_code: "CS105", section: "B", room: "Lab 1", faculty: "Grace H" },
        conflict_type: "room",
        conflict_types: ["room"],
    },
    {
        day: "Monday",
        time: "08:00 AM - 09:30 AM vs 08:30 AM - 10:00 AM",
        schedule_1_id: 11,
        schedule_2_id: 12,
        room_id: 1,
        class_1: { id: 2, subject_code: "GE102", section: "A", room: "Lab 1", faculty: "Pat V" },
        class_2: { id: 3, subject_code: "CS105", section: "B", room: "Lab 1", faculty: "Grace H" },
        conflict_type: "room",
        conflict_types: ["room"],
    },
];

test("buildTriageQueue collapses 3 pairwise room clashes into 1 root-cause issue + 1 missing-time issue", () => {
    const result = buildTriageQueue(classes, conflicts);

    assert.equal(result.rawConflictsCount, 3);
    assert.equal(result.rootIssuesCount, 2);
    assert.equal(result.issues[0].category, "room");
    assert.equal(result.issues[0].impactCount, 3);
    assert.equal(result.issues[0].sessions.length, 3);
    assert.equal(result.issues[1].category, "missing_time");
    assert.equal(result.issues[1].missingClass?.subject_code, "PE101");
});
