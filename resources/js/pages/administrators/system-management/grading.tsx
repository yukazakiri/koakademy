import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { computeGwa, formatGwa, gradeScaleLabel, gwaToneClass, type GradingConfig } from "@/lib/gwa";
import { useForm } from "@inertiajs/react";
import { Calculator, Info, Loader2, Plus, Save, Search, X } from "lucide-react";
import { useMemo, useState } from "react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { CourseWithSubjects, GradingConfigPayload, SystemManagementPageProps } from "./types";

interface GradingFormData {
    scale: GradingConfigPayload["scale"];
    point_passing_grade: number;
    percent_passing_grade: number;
    point_decimal_places: number;
    percent_decimal_places: number;
    include_failed_in_gwa: boolean;
    excluded_keywords: string[];
    excluded_subject_ids: number[];
}

const SAMPLE_SUBJECTS_POINT = [
    { id: -101, code: "MATH101", title: "College Algebra", units: 3, grade: 1.75 },
    { id: -102, code: "ENG101", title: "English Composition", units: 3, grade: 2.0 },
    { id: -103, code: "NSTP1", title: "NSTP Civic Welfare Training", units: 3, grade: 1.5 },
    { id: -104, code: "OJT400", title: "On-the-Job Training", units: 6, grade: 1.25 },
    { id: -105, code: "HIST101", title: "Philippine History", units: 3, grade: 2.25 },
];

const SAMPLE_SUBJECTS_PERCENT = SAMPLE_SUBJECTS_POINT.map((s) => ({
    ...s,
    grade: Math.round(100 - (s.grade - 1) * 10),
}));

export default function SystemManagementGradingPage({
    user,
    grading_config,
    courses_with_subjects,
    access,
}: SystemManagementPageProps) {
    const courses: CourseWithSubjects[] = courses_with_subjects ?? [];

    const gradingForm = useForm<GradingFormData>({
        scale: grading_config.scale,
        point_passing_grade: grading_config.point_passing_grade,
        percent_passing_grade: grading_config.percent_passing_grade,
        point_decimal_places: grading_config.point_decimal_places,
        percent_decimal_places: grading_config.percent_decimal_places,
        include_failed_in_gwa: grading_config.include_failed_in_gwa,
        excluded_keywords: grading_config.excluded_keywords ?? [],
        excluded_subject_ids: grading_config.excluded_subject_ids ?? [],
    });

    const [keywordDraft, setKeywordDraft] = useState("");
    const [courseSearch, setCourseSearch] = useState("");

    const excludedSubjectIds = useMemo(
        () => new Set(gradingForm.data.excluded_subject_ids),
        [gradingForm.data.excluded_subject_ids],
    );

    const filteredCourses = useMemo(() => {
        const q = courseSearch.trim().toLowerCase();
        if (!q) {
            return courses;
        }
        return courses
            .map((course) => {
                const courseMatches = course.code.toLowerCase().includes(q) || course.title.toLowerCase().includes(q);
                const matchingSubjects = course.subjects.filter(
                    (s) => s.code.toLowerCase().includes(q) || s.title.toLowerCase().includes(q),
                );
                if (courseMatches) {
                    return course;
                }
                if (matchingSubjects.length > 0) {
                    return { ...course, subjects: matchingSubjects };
                }
                return null;
            })
            .filter((course): course is CourseWithSubjects => course !== null);
    }, [courses, courseSearch]);

    const previewConfig: GradingConfig = useMemo(
        () => ({
            scale: gradingForm.data.scale,
            point_passing_grade: gradingForm.data.point_passing_grade,
            percent_passing_grade: gradingForm.data.percent_passing_grade,
            point_decimal_places: gradingForm.data.point_decimal_places,
            percent_decimal_places: gradingForm.data.percent_decimal_places,
            include_failed_in_gwa: gradingForm.data.include_failed_in_gwa,
            excluded_keywords: gradingForm.data.excluded_keywords,
            excluded_subject_ids: gradingForm.data.excluded_subject_ids,
        }),
        [gradingForm.data],
    );

    const samplePoint = useMemo(() => computeGwa(SAMPLE_SUBJECTS_POINT, { config: previewConfig }), [previewConfig]);
    const samplePercent = useMemo(() => computeGwa(SAMPLE_SUBJECTS_PERCENT, { config: previewConfig }), [previewConfig]);

    const addKeyword = () => {
        const value = keywordDraft.trim();
        if (!value) return;
        if (gradingForm.data.excluded_keywords.some((k) => k.toLowerCase() === value.toLowerCase())) {
            setKeywordDraft("");
            return;
        }
        gradingForm.setData("excluded_keywords", [...gradingForm.data.excluded_keywords, value]);
        setKeywordDraft("");
    };

    const removeKeyword = (keyword: string) => {
        gradingForm.setData(
            "excluded_keywords",
            gradingForm.data.excluded_keywords.filter((k) => k !== keyword),
        );
    };

    const toggleSubject = (subjectId: number, checked: boolean) => {
        const current = new Set(gradingForm.data.excluded_subject_ids);
        if (checked) {
            current.add(subjectId);
        } else {
            current.delete(subjectId);
        }
        gradingForm.setData("excluded_subject_ids", Array.from(current));
    };

    const toggleAllSubjectsOfCourse = (course: CourseWithSubjects, checked: boolean) => {
        const current = new Set(gradingForm.data.excluded_subject_ids);
        for (const subject of course.subjects) {
            if (checked) {
                current.add(subject.id);
            } else {
                current.delete(subject.id);
            }
        }
        gradingForm.setData("excluded_subject_ids", Array.from(current));
    };

    const countExcludedInCourse = (course: CourseWithSubjects): number =>
        course.subjects.reduce((count, subject) => count + (excludedSubjectIds.has(subject.id) ? 1 : 0), 0);

    const handleSave = () => {
        submitSystemForm({
            form: gradingForm,
            routeName: "administrators.system-management.grading.update",
            successMessage: "Grading system updated successfully.",
            errorMessage: "Failed to update grading system.",
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="grading"
            heading="Grading System"
            description="Configure how GWAs are computed across the platform and exempt subjects like OJT and NSTP from the calculation."
        >
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <CardTitle className="flex items-center gap-2">
                                <Calculator className="h-5 w-5" />
                                Grade Scale & Thresholds
                            </CardTitle>
                            <CardDescription>
                                Choose the grading scale used in transcripts and define the passing thresholds.
                            </CardDescription>
                        </div>
                        <Button onClick={handleSave} disabled={gradingForm.processing}>
                            {gradingForm.processing ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Save className="mr-2 h-4 w-4" />
                            )}
                            Save Changes
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-2">
                        <Label>Default grading scale</Label>
                        <RadioGroup
                            value={gradingForm.data.scale}
                            onValueChange={(value) => gradingForm.setData("scale", value as GradingConfigPayload["scale"])}
                            className="grid gap-3 md:grid-cols-3"
                        >
                            <label
                                htmlFor="scale-auto"
                                className="hover:bg-muted/40 flex cursor-pointer items-start gap-3 rounded-md border p-3"
                            >
                                <RadioGroupItem id="scale-auto" value="auto" className="mt-1" />
                                <div className="space-y-0.5">
                                    <div className="font-medium">Auto detect</div>
                                    <p className="text-muted-foreground text-xs">
                                        Infer per record. Recommended when legacy records mix scales.
                                    </p>
                                </div>
                            </label>
                            <label
                                htmlFor="scale-point"
                                className="hover:bg-muted/40 flex cursor-pointer items-start gap-3 rounded-md border p-3"
                            >
                                <RadioGroupItem id="scale-point" value="point" className="mt-1" />
                                <div className="space-y-0.5">
                                    <div className="font-medium">Point (1.0 – 5.0)</div>
                                    <p className="text-muted-foreground text-xs">
                                        Lower is better. Common in Philippine tertiary institutions.
                                    </p>
                                </div>
                            </label>
                            <label
                                htmlFor="scale-percent"
                                className="hover:bg-muted/40 flex cursor-pointer items-start gap-3 rounded-md border p-3"
                            >
                                <RadioGroupItem id="scale-percent" value="percent" className="mt-1" />
                                <div className="space-y-0.5">
                                    <div className="font-medium">Percent (0 – 100)</div>
                                    <p className="text-muted-foreground text-xs">Higher is better. Common in secondary education.</p>
                                </div>
                            </label>
                        </RadioGroup>
                    </div>

                    <Separator />

                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="point_passing_grade">Passing grade (Point scale)</Label>
                            <Input
                                id="point_passing_grade"
                                type="number"
                                step="0.01"
                                min={1}
                                max={5}
                                value={gradingForm.data.point_passing_grade}
                                onChange={(e) => gradingForm.setData("point_passing_grade", Number(e.target.value))}
                            />
                            <p className="text-muted-foreground text-xs">Grades at or below this value are considered passing.</p>
                            {gradingForm.errors.point_passing_grade && (
                                <p className="text-destructive text-xs">{gradingForm.errors.point_passing_grade}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="percent_passing_grade">Passing grade (Percent scale)</Label>
                            <Input
                                id="percent_passing_grade"
                                type="number"
                                step="1"
                                min={0}
                                max={100}
                                value={gradingForm.data.percent_passing_grade}
                                onChange={(e) => gradingForm.setData("percent_passing_grade", Number(e.target.value))}
                            />
                            <p className="text-muted-foreground text-xs">Grades at or above this value are considered passing.</p>
                            {gradingForm.errors.percent_passing_grade && (
                                <p className="text-destructive text-xs">{gradingForm.errors.percent_passing_grade}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="point_decimal_places">GWA decimals (Point scale)</Label>
                            <Select
                                value={String(gradingForm.data.point_decimal_places)}
                                onValueChange={(v) => gradingForm.setData("point_decimal_places", Number(v))}
                            >
                                <SelectTrigger id="point_decimal_places">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {[0, 1, 2, 3, 4, 5, 6].map((v) => (
                                        <SelectItem key={v} value={String(v)}>
                                            {v} decimal{v === 1 ? "" : "s"}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="percent_decimal_places">GWA decimals (Percent scale)</Label>
                            <Select
                                value={String(gradingForm.data.percent_decimal_places)}
                                onValueChange={(v) => gradingForm.setData("percent_decimal_places", Number(v))}
                            >
                                <SelectTrigger id="percent_decimal_places">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {[0, 1, 2, 3, 4, 5, 6].map((v) => (
                                        <SelectItem key={v} value={String(v)}>
                                            {v} decimal{v === 1 ? "" : "s"}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <Separator />

                    <div className="flex flex-wrap items-start justify-between gap-4 rounded-md border p-4">
                        <div className="space-y-1">
                            <Label className="text-base">Include failed grades in GWA</Label>
                            <p className="text-muted-foreground text-sm">
                                When enabled, subjects with a failing grade still contribute their weighted units to the GWA. Turn off
                                to compute GWA from passed subjects only.
                            </p>
                        </div>
                        <Switch
                            checked={gradingForm.data.include_failed_in_gwa}
                            onCheckedChange={(checked) => gradingForm.setData("include_failed_in_gwa", checked)}
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>GWA Exemptions</CardTitle>
                    <CardDescription>
                        Subjects selected here — and subjects whose code or title matches any of the configured keywords — are
                        excluded from GWA calculations but will still appear on the student's checklist.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-3">
                        <Label>Keyword exclusions</Label>
                        <p className="text-muted-foreground text-sm">
                            A subject is excluded if its code or title contains any of these keywords (case-insensitive). Useful for
                            blanket-excluding OJT, NSTP, PE, and similar programs.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {gradingForm.data.excluded_keywords.length === 0 && (
                                <span className="text-muted-foreground text-sm italic">No keywords configured.</span>
                            )}
                            {gradingForm.data.excluded_keywords.map((keyword) => (
                                <Badge key={keyword} variant="secondary" className="gap-1 pr-1">
                                    {keyword}
                                    <button
                                        type="button"
                                        onClick={() => removeKeyword(keyword)}
                                        className="hover:bg-muted rounded-full p-0.5"
                                        aria-label={`Remove ${keyword}`}
                                    >
                                        <X className="h-3 w-3" />
                                    </button>
                                </Badge>
                            ))}
                        </div>
                        <div className="flex gap-2">
                            <Input
                                value={keywordDraft}
                                placeholder="Add a keyword (e.g. OJT, NSTP, PE)"
                                onChange={(e) => setKeywordDraft(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === "Enter") {
                                        e.preventDefault();
                                        addKeyword();
                                    }
                                }}
                            />
                            <Button type="button" variant="secondary" onClick={addKeyword}>
                                <Plus className="mr-1 h-4 w-4" />
                                Add
                            </Button>
                        </div>
                    </div>

                    <Separator />

                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="space-y-1">
                                <Label>Per-course subject exclusions</Label>
                                <p className="text-muted-foreground text-sm">
                                    Expand a course and tick individual subjects to exempt them. This takes precedence over grade/units.
                                </p>
                            </div>
                            <Badge variant="outline">
                                {gradingForm.data.excluded_subject_ids.length} excluded subject
                                {gradingForm.data.excluded_subject_ids.length === 1 ? "" : "s"}
                            </Badge>
                        </div>

                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input
                                placeholder="Search courses or subjects..."
                                value={courseSearch}
                                onChange={(e) => setCourseSearch(e.target.value)}
                                className="pl-9"
                            />
                        </div>

                        {filteredCourses.length === 0 ? (
                            <Alert>
                                <Info className="h-4 w-4" />
                                <AlertTitle>No results</AlertTitle>
                                <AlertDescription>
                                    {courses.length === 0
                                        ? "No courses are available yet."
                                        : "No courses match your search. Try a different term."}
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <ScrollArea className="h-[420px] rounded-md border">
                                <Accordion type="multiple" className="w-full">
                                    {filteredCourses.map((course) => {
                                        const excludedCount = countExcludedInCourse(course);
                                        const total = course.subjects.length;
                                        const allExcluded = total > 0 && excludedCount === total;
                                        return (
                                            <AccordionItem key={course.id} value={`course-${course.id}`} className="px-3">
                                                <AccordionTrigger className="py-3 hover:no-underline">
                                                    <div className="flex w-full items-center justify-between gap-3 pr-2">
                                                        <div className="text-left">
                                                            <div className="font-semibold">{course.code}</div>
                                                            <div className="text-muted-foreground text-xs">{course.title}</div>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-muted-foreground text-xs">
                                                                {excludedCount}/{total} excluded
                                                            </span>
                                                        </div>
                                                    </div>
                                                </AccordionTrigger>
                                                <AccordionContent>
                                                    <div className="bg-muted/30 mb-3 flex items-center justify-between rounded-md border px-3 py-2">
                                                        <span className="text-muted-foreground text-xs">
                                                            {total} subject{total === 1 ? "" : "s"} in this course
                                                        </span>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => toggleAllSubjectsOfCourse(course, !allExcluded)}
                                                            disabled={total === 0}
                                                        >
                                                            {allExcluded ? "Unselect all" : "Select all"}
                                                        </Button>
                                                    </div>
                                                    {total === 0 ? (
                                                        <p className="text-muted-foreground py-4 text-center text-sm italic">
                                                            No subjects registered for this course yet.
                                                        </p>
                                                    ) : (
                                                        <div className="grid gap-1">
                                                            {course.subjects.map((subject) => {
                                                                const checked = excludedSubjectIds.has(subject.id);
                                                                return (
                                                                    <label
                                                                        key={subject.id}
                                                                        htmlFor={`subject-${subject.id}`}
                                                                        className="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2"
                                                                    >
                                                                        <Checkbox
                                                                            id={`subject-${subject.id}`}
                                                                            checked={checked}
                                                                            onCheckedChange={(value) =>
                                                                                toggleSubject(subject.id, value === true)
                                                                            }
                                                                        />
                                                                        <div className="flex-1">
                                                                            <div className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                                                                <span className="font-mono">{subject.code}</span>
                                                                                <span className="text-muted-foreground text-xs">
                                                                                    Year {subject.year_level || "—"} • Sem{" "}
                                                                                    {subject.semester || "—"}
                                                                                </span>
                                                                            </div>
                                                                            <div className="text-muted-foreground text-xs">
                                                                                {subject.title}
                                                                            </div>
                                                                        </div>
                                                                        <Badge variant="outline" className="font-mono text-xs">
                                                                            {subject.units}u
                                                                        </Badge>
                                                                    </label>
                                                                );
                                                            })}
                                                        </div>
                                                    )}
                                                </AccordionContent>
                                            </AccordionItem>
                                        );
                                    })}
                                </Accordion>
                            </ScrollArea>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Live Preview</CardTitle>
                    <CardDescription>
                        Sample GWAs using the current configuration. Change the settings above and watch the preview update in real
                        time — remember to click <strong>Save Changes</strong> to persist the configuration.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="rounded-md border p-4">
                            <div className="text-muted-foreground text-xs tracking-wider uppercase">Point scale sample</div>
                            <div className={`mt-2 font-mono text-3xl font-bold ${gwaToneClass(samplePoint, previewConfig)}`}>
                                {formatGwa(samplePoint, previewConfig)}
                            </div>
                            <div className="text-muted-foreground mt-1 text-xs">
                                {gradeScaleLabel(samplePoint.scale) && `(${gradeScaleLabel(samplePoint.scale)}) `}
                                {samplePoint.gradedCount}/{samplePoint.itemCount} subjects •{" "}
                                {samplePoint.excludedCount} excluded
                            </div>
                        </div>
                        <div className="rounded-md border p-4">
                            <div className="text-muted-foreground text-xs tracking-wider uppercase">Percent scale sample</div>
                            <div className={`mt-2 font-mono text-3xl font-bold ${gwaToneClass(samplePercent, previewConfig)}`}>
                                {formatGwa(samplePercent, previewConfig)}
                            </div>
                            <div className="text-muted-foreground mt-1 text-xs">
                                {gradeScaleLabel(samplePercent.scale) && `(${gradeScaleLabel(samplePercent.scale)}) `}
                                {samplePercent.gradedCount}/{samplePercent.itemCount} subjects •{" "}
                                {samplePercent.excludedCount} excluded
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40">
                                <tr>
                                    <th className="px-3 py-2 text-left">Code</th>
                                    <th className="px-3 py-2 text-left">Title</th>
                                    <th className="px-3 py-2 text-right">Units</th>
                                    <th className="px-3 py-2 text-right">Grade (pt)</th>
                                    <th className="px-3 py-2 text-right">Grade (%)</th>
                                    <th className="px-3 py-2 text-center">Effect</th>
                                </tr>
                            </thead>
                            <tbody>
                                {SAMPLE_SUBJECTS_POINT.map((subject, idx) => {
                                    const keywordHit = previewConfig.excluded_keywords.some(
                                        (k) =>
                                            k.trim() !== "" &&
                                            (subject.code.toLowerCase().includes(k.toLowerCase()) ||
                                                subject.title.toLowerCase().includes(k.toLowerCase())),
                                    );
                                    const excluded = keywordHit;
                                    return (
                                        <tr key={subject.id} className="border-t">
                                            <td className="px-3 py-2 font-mono">{subject.code}</td>
                                            <td className="px-3 py-2">{subject.title}</td>
                                            <td className="px-3 py-2 text-right">{subject.units}</td>
                                            <td className="px-3 py-2 text-right font-mono">{subject.grade}</td>
                                            <td className="px-3 py-2 text-right font-mono">
                                                {SAMPLE_SUBJECTS_PERCENT[idx].grade}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                {excluded ? (
                                                    <Badge variant="outline" className="text-amber-600">
                                                        Excluded (keyword)
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-emerald-600">
                                                        Counted
                                                    </Badge>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </SystemManagementLayout>
    );
}
