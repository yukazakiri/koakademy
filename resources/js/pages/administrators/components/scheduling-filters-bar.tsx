import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useState } from "react";
import type { SchedulingAnalyticsProps, StudentSearchResult } from "./scheduling-types";

export type PlannerFilters = { course: string; year: string; section: string; room: string; faculty: string };
export const emptyPlannerFilters: PlannerFilters = { course: "all", year: "all", section: "all", room: "all", faculty: "all" };

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
}) {
    const id = `planner-${label.toLowerCase().replaceAll(" ", "-")}`;
    return (
        <div className="min-w-0 space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

export default function SchedulingFiltersBar({
    value,
    onChange,
    options,
    sections,
    studentQuery,
    onStudentQueryChange,
    students,
    onSelectStudent,
    loadingStudent,
    activeStudentName,
    onClearStudent,
}: {
    value: PlannerFilters;
    onChange: (value: PlannerFilters) => void;
    options: SchedulingAnalyticsProps["filters"];
    sections: string[];
    studentQuery: string;
    onStudentQueryChange: (value: string) => void;
    students: StudentSearchResult[];
    onSelectStudent: (student: StudentSearchResult) => void;
    loadingStudent: boolean;
    activeStudentName?: string;
    onClearStudent: () => void;
}) {
    const [more, setMore] = useState(false);
    const extraCount = Number(value.room !== "all") + Number(value.faculty !== "all") + Number(Boolean(activeStudentName));
    const hasFilters = Object.values(value).some((item) => item !== "all") || activeStudentName;
    const change = (key: keyof PlannerFilters, next: string) =>
        onChange({ ...value, [key]: next, ...(key === "course" || key === "year" ? { section: "all" } : {}) });
    return (
        <section aria-label="Choose schedules to view" className="space-y-4">
            <div className="grid items-end gap-4 sm:grid-cols-3 lg:grid-cols-[1fr_1fr_1fr_auto]">
                <FilterSelect
                    label="Program"
                    value={value.course}
                    onChange={(next) => change("course", next)}
                    options={[
                        { value: "all", label: "All programs" },
                        ...options.available_courses.map((course) => ({ value: String(course.id), label: course.code })),
                    ]}
                />
                <FilterSelect
                    label="Year level"
                    value={value.year}
                    onChange={(next) => change("year", next)}
                    options={[{ value: "all", label: "All years" }, ...options.available_year_levels.map((year) => ({ value: year, label: year }))]}
                />
                <FilterSelect
                    label="Section"
                    value={value.section}
                    onChange={(next) => change("section", next)}
                    options={[
                        { value: "all", label: "All sections" },
                        ...sections.map((section) => ({ value: section, label: `Section ${section}` })),
                    ]}
                />
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        aria-expanded={more}
                        aria-controls="planner-extra-filters"
                        onClick={() => setMore((previous) => !previous)}
                    >
                        More filters{extraCount ? ` (${extraCount})` : ""}
                    </Button>
                    {hasFilters && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                onChange(emptyPlannerFilters);
                                onClearStudent();
                                onStudentQueryChange("");
                            }}
                        >
                            Reset
                        </Button>
                    )}
                </div>
            </div>
            {more && (
                <div id="planner-extra-filters" className="bg-muted/30 grid gap-4 rounded-lg border p-4 sm:grid-cols-3">
                    <FilterSelect
                        label="Room"
                        value={value.room}
                        onChange={(next) => change("room", next)}
                        options={[
                            { value: "all", label: "All rooms" },
                            ...options.available_rooms.map((room) => ({ value: String(room.id), label: room.name })),
                        ]}
                    />
                    <FilterSelect
                        label="Instructor"
                        value={value.faculty}
                        onChange={(next) => change("faculty", next)}
                        options={[
                            { value: "all", label: "All instructors" },
                            ...options.available_faculty.map((faculty) => ({ value: faculty.id, label: faculty.name })),
                        ]}
                    />
                    <div className="relative min-w-0 space-y-2">
                        <Label htmlFor="planner-student">Student timetable</Label>
                        <Input
                            id="planner-student"
                            placeholder="Find a student by name or ID"
                            value={studentQuery}
                            onChange={(event) => onStudentQueryChange(event.target.value)}
                        />
                        {loadingStudent && (
                            <p role="status" className="text-muted-foreground text-xs">
                                Finding student schedules…
                            </p>
                        )}
                        {students.length > 0 && (
                            <ul
                                aria-label="Matching students"
                                className="bg-popover absolute z-20 max-h-64 w-full overflow-auto rounded-lg border p-1 shadow-md"
                            >
                                {students.map((student) => (
                                    <li key={student.id}>
                                        <button
                                            type="button"
                                            className="hover:bg-muted focus-visible:bg-muted w-full rounded-md p-3 text-left text-sm"
                                            onClick={() => onSelectStudent(student)}
                                        >
                                            {student.name} · {student.student_id}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            )}
            {activeStudentName && (
                <p className="flex flex-wrap items-center gap-2 text-sm">
                    Student: {activeStudentName}
                    <Button variant="ghost" size="sm" onClick={onClearStudent}>
                        Clear student
                    </Button>
                </p>
            )}
        </section>
    );
}
