import { createInertiaApp, router } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import "../../resources/css/app.css";
import ClassesEdit from "../../resources/js/pages/administrators/classes/create";
import ClassesIndex from "../../resources/js/pages/administrators/classes/index";
import ClassesShow from "../../resources/js/pages/administrators/classes/show";

const params = new URLSearchParams(window.location.search);
const mode = params.get("mode") ?? "index";
const requestedStep = params.get("step");
const options = {
    classifications: [
        { value: "college", label: "College" },
        { value: "shs", label: "SHS" },
    ],
    sections: [{ value: "A", label: "Section A" }],
    semesters: [{ value: "1", label: "1st Semester" }],
    grade_levels: [{ value: "Grade 11", label: "Grade 11" }],
    academic_years: [{ value: "1", label: "1st Year" }],
    day_of_week: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"].map((day) => ({ value: day, label: day })),
    courses: [],
    faculties: [],
    rooms: [{ id: 4, label: "Room 201" }],
    shs_tracks: [{ id: 3, label: "STEM" }],
};

const schedule = {
    id: 21,
    day_of_week: "Monday",
    start_time: "08:00",
    end_time: "09:00",
    delivery_mode: "face_to_face",
    room_id: 4,
    room_name: "Room 201",
};
const recordSchedules = [
    { ...schedule, temp_id: "21", pattern_group_id: "weekly-pattern", component_type: "lab", status: "draft" },
    { ...schedule, id: 22, temp_id: "22", day_of_week: "Wednesday", pattern_group_id: "weekly-pattern", component_type: "lab", status: "draft" },
];
const row = {
    id: 15,
    record_title: "STEM 101 - A",
    subject_code: "STEM 101",
    subject_title: "Science",
    section: "A",
    school_year: "2026 - 2027",
    semester: "1",
    classification: "shs",
    delivery_mode: "face_to_face",
    academic_year: null,
    grade_level: "Grade 11",
    course_abbreviations: [],
    shs_track: "STEM",
    shs_strand: "STEM",
    faculty: "TBA",
    students_count: 10,
    maximum_slots: 40,
    deleted_at: null,
    filament: { view_url: "#", edit_url: "#" },
};
const selectedClass = {
    ...row,
    shs_track: { id: 3, label: "STEM" },
    shs_strand: { id: 5, label: "STEM" },
    faculty: null,
    room: { id: 4, label: "Room 201" },
    course_codes: [],
    course_ids: [],
    subject_ids: [],
    subjects: [],
    settings: {},
    schedules: [{ ...schedule, room: { id: 4, label: "Room 201" } }],
    enrollments: [],
    posts: [],
};
const defaults = {
    classification: "shs",
    course_codes: [],
    subject_ids: [],
    subject_code: "",
    academic_year: 1,
    shs_track_id: 3,
    shs_strand_id: 5,
    subject_code_shs: "STEM 101",
    grade_level: "Grade 11",
    faculty_id: null,
    semester: "1",
    school_year: "2026 - 2027",
    section: "A",
    delivery_mode: "face_to_face",
    room_id: 4,
    maximum_slots: 40,
    schedules: [schedule],
    settings: {
        background_color: "#ffffff",
        accent_color: "#3b82f6",
        theme: "default",
        enable_announcements: true,
        enable_grade_visibility: true,
        enable_attendance_tracking: false,
        allow_late_submissions: false,
        enable_discussion_board: false,
        notify_students_on_schedule_changes: true,
        custom: {},
        banner_image: null,
    },
};

router.visit = ((url: string) => {
    (window as unknown as { classScheduleVisit: string }).classScheduleVisit = url;
}) as typeof router.visit;
router.get = ((_url: string, _data: unknown, options?: { onFinish?: () => void }) => {
    (window as unknown as { classListRequests: number }).classListRequests =
        ((window as unknown as { classListRequests?: number }).classListRequests ?? 0) + 1;
    options?.onFinish?.();
}) as typeof router.get;
router.patch = ((
    url: string,
    data: unknown,
    visitOptions?: { onSuccess?: () => void; onError?: (errors: Record<string, string>) => void; onFinish?: () => void },
) => {
    (window as unknown as { classSchedulePatch: unknown }).classSchedulePatch = { url, data };
    const mockErrors = (window as unknown as { classScheduleMockErrors?: Record<string, string> }).classScheduleMockErrors;
    if (mockErrors) {
        visitOptions?.onError?.(mockErrors);
    } else {
        visitOptions?.onSuccess?.();
    }
    visitOptions?.onFinish?.();
}) as typeof router.patch;

createInertiaApp({
    id: "root",
    page: {
        component: mode,
        props: {
            user: { id: 1, name: "Administrator" },
            filament: { classes: { index_url: "#", create_url: "#" } },
            options,
            ...(mode === "show"
                ? {
                      class: {
                          ...row,
                          subjects: ["Science"],
                          associated_courses: "STEM",
                          year_level: "Grade 11",
                          faculty: null,
                          schedule: {
                              Monday: [{ ...schedule, time_range: "8:00 AM - 9:00 AM", room: { id: 4, name: "Room 201" }, has_conflict: false }],
                              Wednesday: [{ ...schedule, time_range: "8:00 AM - 9:00 AM", room: { id: 4, name: "Room 201" }, has_conflict: false }],
                          },
                          settings: { background_color: null, accent_color: null, theme: null, banner_image_url: null, features: {}, custom: {} },
                          is_archived: false,
                      },
                      capabilities: { update: true, delete: true, restore: true, force_delete: true },
                      enrollments: [],
                      schedule_revision: "a".repeat(64),
                      raw_schedules: recordSchedules,
                      rooms: options.rooms,
                      transferable_classes: [],
                  }
                : mode === "index"
                  ? {
                        classes: {
                            data: [row],
                            meta: {
                                current_page: 1,
                                last_page: 1,
                                per_page: 20,
                                total: 1,
                                from: 1,
                                to: 1,
                                total_students: 10,
                                total_capacity: 40,
                                queues: { unassigned_faculty: 1, no_schedule: 0, over_capacity: 0 },
                                classification_counts: { college: 0, shs: 1 },
                            },
                        },
                        capabilities: { create: true, update: true, delete: true, restore: true, force_delete: true },
                        selected_class: selectedClass,
                        filters: {},
                        defaults: { semester: "1", school_year: "2026 - 2027" },
                    }
                  : { defaults, mode: "edit", class_id: 15, class_title: row.record_title, flash: null }),
        },
        url:
            mode === "index"
                ? "/administrators/classes"
                : mode === "show"
                  ? "/administrators/classes/15"
                  : `/administrators/classes/15/edit${requestedStep ? `?step=${requestedStep}` : ""}`,
        version: "fixture",
        clearHistory: false,
        encryptHistory: false,
    },
    resolve: () => (mode === "index" ? ClassesIndex : mode === "show" ? ClassesShow : ClassesEdit),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
