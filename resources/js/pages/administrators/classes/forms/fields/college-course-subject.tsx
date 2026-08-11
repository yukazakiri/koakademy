import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect as SearchableMultiSelect } from "@/components/ui/multi-select";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { ReactNode } from "react";
import { buildSubjectCodeFromSubjectOptions, type EntityOption, type SubjectOption, type ValueOption } from "../../lib/class-defaults";

type CollegeCourseSubjectProps = {
    courses: EntityOption[];
    subjects: SubjectOption[];
    academicYears: ValueOption[];
    selectedCourseIds: number[];
    selectedSubjectIds: number[];
    subjectCode: string;
    academicYear: number;
    subjectCodeTouched: boolean;
    subjectsLoading?: boolean;
    onCoursesChange: (courseIds: number[]) => void;
    onSubjectsChange: (subjectIds: number[], subjectCode?: string) => void;
    onSubjectCodeChange: (subjectCode: string) => void;
    onAcademicYearChange: (academicYear: number) => void;
    onSubjectCodeTouched: () => void;
};

function toOptionValue(id: number | string): string {
    return String(id);
}

function toNumbers(values: string[]): number[] {
    return values.map((value) => Number(value)).filter((value) => Number.isFinite(value));
}

export function CollegeCourseSubject({
    courses,
    subjects,
    academicYears,
    selectedCourseIds,
    selectedSubjectIds,
    subjectCode,
    academicYear,
    subjectCodeTouched,
    subjectsLoading = false,
    onCoursesChange,
    onSubjectsChange,
    onSubjectCodeChange,
    onAcademicYearChange,
    onSubjectCodeTouched,
}: CollegeCourseSubjectProps): ReactNode {
    const courseOptions = courses.map((course) => ({ label: course.label, value: toOptionValue(course.id) }));
    const subjectOptions = subjects.map((subject) => ({
        label: subject.label,
        value: String(subject.id),
        description: `${subject.course_code ?? "Program"} · ${subject.academic_year ? `Year ${subject.academic_year}` : "Year unresolved"}${
            subject.semester ? ` · Semester ${subject.semester}` : ""
        }`,
        searchText: `${subject.code} ${subject.label}`,
    }));

    const selectedSubjects = subjects.filter((subject) => selectedSubjectIds.includes(subject.id));
    const curriculumYears = Array.from(
        new Set(selectedSubjects.map((subject) => subject.academic_year).filter((year): year is number => year !== null)),
    ).sort((a, b) => a - b);
    const allSubjectsResolved =
        selectedSubjects.length === selectedSubjectIds.length && selectedSubjects.every((subject) => subject.academic_year !== null);
    const unanimousYear = allSubjectsResolved && curriculumYears.length === 1 ? curriculumYears[0] : null;
    const spansYears = curriculumYears.length > 1;

    const handleSubjectChange = (values: string[]): void => {
        const nextSubjectIds = toNumbers(values);
        onSubjectsChange(nextSubjectIds, subjectCodeTouched ? undefined : buildSubjectCodeFromSubjectOptions(nextSubjectIds, subjects));
    };

    return (
        <div className="grid gap-5 lg:grid-cols-2">
            <div className="space-y-2">
                <Label>
                    Courses <span className="text-muted-foreground text-xs font-normal">(pick one or more)</span>
                </Label>
                <SearchableMultiSelect
                    options={courseOptions}
                    selected={selectedCourseIds.map(String)}
                    onChange={(values) => onCoursesChange(toNumbers(values))}
                    placeholder="Choose the course programs"
                    searchPlaceholder="Search courses"
                    emptyText="No courses match your search."
                />
                <p className="text-muted-foreground text-sm">
                    Pick every course program that will share this class. Subjects will load for the combined list.
                </p>
            </div>

            <div className="space-y-2">
                <Label>
                    Subjects <span className="text-muted-foreground text-xs font-normal">(pick one or more)</span>
                </Label>
                <SearchableMultiSelect
                    options={subjectOptions}
                    selected={selectedSubjectIds.map(String)}
                    onChange={handleSubjectChange}
                    disabled={selectedCourseIds.length === 0 || subjectsLoading}
                    placeholder={subjectsLoading ? "Loading subjects" : "Choose subjects"}
                    searchPlaceholder="Search subjects"
                    emptyText="No subjects are available for the selected courses."
                />
                <p className="text-muted-foreground text-sm">
                    Subjects are pooled from every selected course. Pick as many as this class will teach.
                </p>
            </div>

            <div className="space-y-2">
                <Label htmlFor="subject_code">Subject code</Label>
                <Input
                    id="subject_code"
                    value={subjectCode}
                    onChange={(event) => {
                        onSubjectCodeTouched();
                        onSubjectCodeChange(event.target.value);
                    }}
                    placeholder="Example: IT101"
                />
                <p className="text-muted-foreground text-sm">This is filled from selected subjects, but you can edit it.</p>
            </div>

            <div className="space-y-2">
                <Label>Academic year</Label>
                <Select value={String(academicYear)} onValueChange={(value) => onAcademicYearChange(Number(value))} disabled={unanimousYear !== null}>
                    <SelectTrigger className="w-full">
                        <SelectValue placeholder="Choose academic year" />
                    </SelectTrigger>
                    <SelectContent>
                        {academicYears.map((year) => (
                            <SelectItem
                                key={year.value}
                                value={year.value}
                                disabled={unanimousYear !== null || (spansYears && !curriculumYears.includes(Number(year.value)))}
                            >
                                {year.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {unanimousYear !== null ? (
                    <p className="text-muted-foreground text-sm">Set automatically from the selected subjects’ curriculum placement.</p>
                ) : spansYears ? (
                    <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-100">
                        This shared class spans curriculum years {curriculumYears.join(" and ")}. Program schedules use each subject’s year; this
                        selection is only the legacy fallback.
                    </div>
                ) : null}
            </div>
        </div>
    );
}
