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
import { useForm } from "@inertiajs/react";
import { Calculator, CircleCheck, GraduationCap, Loader2, Plus, Save, Search, Settings2, Trash2, X } from "lucide-react";
import { useMemo, useState } from "react";

import { submitSystemForm } from "./form-submit";
import SystemManagementLayout from "./layout";
import type { CourseWithSubjects, GradingBandPayload, GradingComponentPayload, GradingConfigPayload, SystemManagementPageProps } from "./types";

type GradingFormData = Omit<GradingConfigPayload, "policy_version_id" | "policy_version">;

const outcomes: Array<{ value: GradingBandPayload["outcome"]; label: string }> = [
    { value: "pass", label: "Pass" },
    { value: "fail", label: "Fail" },
    { value: "incomplete", label: "Incomplete" },
    { value: "withdrawn", label: "Withdrawn" },
    { value: "non_credit", label: "Non-credit" },
];

function createBand(index: number, inputType: GradingFormData["input_type"]): GradingBandPayload {
    return {
        id: `band-${crypto.randomUUID()}`,
        symbol: inputType === "symbol" ? "" : null,
        label: inputType === "symbol" ? "New symbol" : "New range",
        min: inputType === "numeric" ? 0 : null,
        max: inputType === "numeric" ? 0 : null,
        outcome: "pass",
        quality_points: null,
        color: "success",
        sort_order: index,
    };
}

function createComponent(index: number): GradingComponentPayload {
    const key = `component_${index + 1}`;

    return {
        id: key,
        key,
        label: `Component ${index + 1}`,
        weight: 0,
        required: true,
        sort_order: index,
    };
}

export default function SystemManagementGradingPage({
    user,
    grading_config,
    courses_with_subjects,
    active_school,
    access,
}: SystemManagementPageProps) {
    const courses: CourseWithSubjects[] = courses_with_subjects ?? [];
    const [keywordDraft, setKeywordDraft] = useState("");
    const [courseSearch, setCourseSearch] = useState("");

    const gradingForm = useForm<GradingFormData>({
        name: grading_config.name,
        input_type: grading_config.input_type,
        numeric_min: grading_config.numeric_min,
        numeric_max: grading_config.numeric_max,
        direction: grading_config.direction,
        decimal_places: grading_config.decimal_places,
        include_failed_in_gwa: grading_config.include_failed_in_gwa,
        gwa_formula: grading_config.gwa_formula ?? "weighted_units",
        gwa_subject_divisor_basis: grading_config.gwa_subject_divisor_basis ?? "enrolled_subjects",
        gwa_calculation_metric: grading_config.gwa_calculation_metric ?? "numeric_grade",
        retake_strategy: grading_config.retake_strategy ?? "latest",
        include_credited_in_gwa: grading_config.include_credited_in_gwa ?? true,
        zero_is_dropped: grading_config.zero_is_dropped ?? false,
        treat_incomplete_as: grading_config.treat_incomplete_as ?? "exclude",
        exclude_zero_unit_subjects: grading_config.exclude_zero_unit_subjects ?? true,
        excluded_keywords: grading_config.excluded_keywords ?? [],
        excluded_subject_ids: grading_config.excluded_subject_ids ?? [],
        bands: grading_config.bands ?? [],
        components: grading_config.components ?? [],
    });

    const excludedSubjectIds = useMemo(() => new Set(gradingForm.data.excluded_subject_ids), [gradingForm.data.excluded_subject_ids]);
    const componentWeight = useMemo(
        () => gradingForm.data.components.reduce((total, component) => total + Number(component.weight || 0), 0),
        [gradingForm.data.components],
    );
    const componentWeightIsValid = Math.abs(componentWeight - 100) < 0.001;

    const filteredCourses = useMemo(() => {
        const query = courseSearch.trim().toLowerCase();
        if (!query) return courses;

        return courses
            .map((course) => {
                const courseMatches = `${course.code} ${course.title}`.toLowerCase().includes(query);
                const subjects = course.subjects.filter((subject) => `${subject.code} ${subject.title}`.toLowerCase().includes(query));
                return courseMatches ? course : subjects.length > 0 ? { ...course, subjects } : null;
            })
            .filter((course): course is CourseWithSubjects => course !== null);
    }, [courseSearch, courses]);

    const updateBand = (index: number, patch: Partial<GradingBandPayload>) => {
        gradingForm.setData(
            "bands",
            gradingForm.data.bands.map((band, currentIndex) => (currentIndex === index ? { ...band, ...patch } : band)),
        );
    };

    const updateComponent = (index: number, patch: Partial<GradingComponentPayload>) => {
        gradingForm.setData(
            "components",
            gradingForm.data.components.map((component, currentIndex) => (currentIndex === index ? { ...component, ...patch } : component)),
        );
    };

    const addKeyword = () => {
        const keyword = keywordDraft.trim();
        if (!keyword || gradingForm.data.excluded_keywords.some((entry) => entry.toLowerCase() === keyword.toLowerCase())) return;
        gradingForm.setData("excluded_keywords", [...gradingForm.data.excluded_keywords, keyword]);
        setKeywordDraft("");
    };

    const toggleSubject = (subjectId: number, checked: boolean) => {
        const selected = new Set(gradingForm.data.excluded_subject_ids);
        if (checked) {
            selected.add(subjectId);
        } else {
            selected.delete(subjectId);
        }
        gradingForm.setData("excluded_subject_ids", [...selected]);
    };

    const save = () => {
        submitSystemForm({
            form: gradingForm,
            routeName: "administrators.system-management.grading.update",
            successMessage: "A new grading policy version was published.",
            errorMessage: "The grading policy could not be published.",
        });
    };

    const gradeInputPlaceholder = gradingForm.data.input_type === "symbol" ? "e.g. A, B+, Pass" : "Numeric grade range";

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="grading"
            heading="Grading Policy"
            description="Define this school's grade scale, achievement bands, and assessment calculation. Publishing creates a new version; finalized historical grades keep their original rules."
        >
            {!active_school && (
                <Alert variant="destructive">
                    <AlertTitle>No active school selected</AlertTitle>
                    <AlertDescription>Select a school in System Management before publishing a school-specific grading policy.</AlertDescription>
                </Alert>
            )}

            <Card className="overflow-hidden">
                <CardHeader className="bg-muted/25 border-b">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <Settings2 className="text-primary size-5" />
                                <CardTitle>Policy Identity</CardTitle>
                                {grading_config.policy_version && <Badge variant="outline">Version {grading_config.policy_version}</Badge>}
                            </div>
                            <CardDescription>
                                {active_school
                                    ? `Editing the default policy for ${active_school.name}.`
                                    : "This policy is used as the system fallback until a school is selected."}
                            </CardDescription>
                        </div>
                        <Button onClick={save} disabled={gradingForm.processing || !componentWeightIsValid || !active_school} className="gap-2">
                            {gradingForm.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                            Publish New Version
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="grid gap-6 pt-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <div className="space-y-2">
                        <Label htmlFor="policy-name">Policy name</Label>
                        <Input id="policy-name" value={gradingForm.data.name} onChange={(event) => gradingForm.setData("name", event.target.value)} />
                        {gradingForm.errors.name && <p className="text-destructive text-xs">{gradingForm.errors.name}</p>}
                    </div>
                    <div className="bg-background rounded-lg border p-3 text-sm">
                        <div className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Versioned history</div>
                        <p className="text-muted-foreground mt-1">
                            New grade entries use the published policy. Finalized records retain the version used when they were calculated.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <GraduationCap className="size-5" /> Grade Scale
                    </CardTitle>
                    <CardDescription>
                        Use a numeric range from any country or a letter/custom-symbol scheme. Passing is determined only by the bands you define
                        below.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <RadioGroup
                        value={gradingForm.data.input_type}
                        onValueChange={(value) => gradingForm.setData("input_type", value as GradingFormData["input_type"])}
                        className="grid gap-3 md:grid-cols-2"
                    >
                        <label className="hover:bg-muted/40 flex cursor-pointer gap-3 rounded-lg border p-4" htmlFor="numeric-scale">
                            <RadioGroupItem value="numeric" id="numeric-scale" />
                            <div>
                                <div className="font-medium">Numeric scale</div>
                                <p className="text-muted-foreground text-xs">Supports 0–20, 0–100, 1–5, 0–4, or another institution-defined range.</p>
                            </div>
                        </label>
                        <label className="hover:bg-muted/40 flex cursor-pointer gap-3 rounded-lg border p-4" htmlFor="symbol-scale">
                            <RadioGroupItem value="symbol" id="symbol-scale" />
                            <div>
                                <div className="font-medium">Letter or custom symbols</div>
                                <p className="text-muted-foreground text-xs">
                                    Supports A–F, distinctions, competency labels, Pass/Fail, and localized symbols.
                                </p>
                            </div>
                        </label>
                    </RadioGroup>

                    {gradingForm.data.input_type === "numeric" && (
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="space-y-2">
                                <Label htmlFor="numeric-min">Minimum value</Label>
                                <Input
                                    id="numeric-min"
                                    type="number"
                                    value={gradingForm.data.numeric_min}
                                    onChange={(event) => gradingForm.setData("numeric_min", Number(event.target.value))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="numeric-max">Maximum value</Label>
                                <Input
                                    id="numeric-max"
                                    type="number"
                                    value={gradingForm.data.numeric_max}
                                    onChange={(event) => gradingForm.setData("numeric_max", Number(event.target.value))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Grade direction</Label>
                                <Select
                                    value={gradingForm.data.direction}
                                    onValueChange={(value) => gradingForm.setData("direction", value as GradingFormData["direction"])}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="higher_is_better">Higher values are better</SelectItem>
                                        <SelectItem value="lower_is_better">Lower values are better</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="decimal-places">Display decimals</Label>
                                <Input
                                    id="decimal-places"
                                    type="number"
                                    min="0"
                                    max="6"
                                    value={gradingForm.data.decimal_places}
                                    onChange={(event) => gradingForm.setData("decimal_places", Number(event.target.value))}
                                />
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle>Achievement Bands</CardTitle>
                            <CardDescription>
                                Map a score or symbol to its academic outcome, display label, and optional quality points.
                            </CardDescription>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="gap-2"
                            onClick={() =>
                                gradingForm.setData("bands", [
                                    ...gradingForm.data.bands,
                                    createBand(gradingForm.data.bands.length, gradingForm.data.input_type),
                                ])
                            }
                        >
                            <Plus className="size-4" /> Add band
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-3">
                    {gradingForm.data.bands.map((band, index) => (
                        <div
                            key={band.id}
                            className="bg-muted/20 grid items-end gap-3 rounded-xl border p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_8rem_8rem_9rem_8rem_auto]"
                        >
                            <div className="space-y-2">
                                <Label>Label</Label>
                                <Input
                                    value={band.label}
                                    placeholder="Passing"
                                    onChange={(event) => updateBand(index, { label: event.target.value })}
                                />
                            </div>
                            {gradingForm.data.input_type === "symbol" ? (
                                <div className="space-y-2">
                                    <Label>Symbol</Label>
                                    <Input
                                        value={band.symbol ?? ""}
                                        placeholder={gradeInputPlaceholder}
                                        onChange={(event) => updateBand(index, { symbol: event.target.value })}
                                    />
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-2">
                                        <Label>Min</Label>
                                        <Input
                                            type="number"
                                            value={band.min ?? ""}
                                            onChange={(event) => updateBand(index, { min: Number(event.target.value) })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Max</Label>
                                        <Input
                                            type="number"
                                            value={band.max ?? ""}
                                            onChange={(event) => updateBand(index, { max: Number(event.target.value) })}
                                        />
                                    </div>
                                </div>
                            )}
                            <div className="space-y-2">
                                <Label>Outcome</Label>
                                <Select
                                    value={band.outcome}
                                    onValueChange={(value) => updateBand(index, { outcome: value as GradingBandPayload["outcome"] })}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {outcomes.map((outcome) => (
                                            <SelectItem key={outcome.value} value={outcome.value}>
                                                {outcome.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Quality pts.</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={band.quality_points ?? ""}
                                    placeholder="Optional"
                                    onChange={(event) =>
                                        updateBand(index, { quality_points: event.target.value === "" ? null : Number(event.target.value) })
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Color</Label>
                                <Select value={band.color} onValueChange={(value) => updateBand(index, { color: value })}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="success">Success</SelectItem>
                                        <SelectItem value="destructive">Destructive</SelectItem>
                                        <SelectItem value="warning">Warning</SelectItem>
                                        <SelectItem value="muted">Muted</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-destructive"
                                onClick={() =>
                                    gradingForm.setData(
                                        "bands",
                                        gradingForm.data.bands.filter((_, currentIndex) => currentIndex !== index),
                                    )
                                }
                                aria-label={`Remove ${band.label}`}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}
                    {gradingForm.errors.bands && <p className="text-destructive text-sm">{gradingForm.errors.bands}</p>}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Calculator className="size-5" /> Assessment Calculation
                            </CardTitle>
                            <CardDescription>
                                The default Prelim / Midterm / Final calculation is 30% / 30% / 40%. Rename, reweight, add, or remove components to
                                match your institution.
                            </CardDescription>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="gap-2"
                            onClick={() =>
                                gradingForm.setData("components", [
                                    ...gradingForm.data.components,
                                    createComponent(gradingForm.data.components.length),
                                ])
                            }
                        >
                            <Plus className="size-4" /> Add component
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="space-y-3">
                    {gradingForm.data.components.map((component, index) => (
                        <div
                            key={component.id}
                            className="bg-muted/20 grid items-end gap-3 rounded-xl border p-4 md:grid-cols-[minmax(0,1fr)_10rem_8rem_auto_auto]"
                        >
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Label</Label>
                                    <Input value={component.label} onChange={(event) => updateComponent(index, { label: event.target.value })} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Internal key</Label>
                                    <Input
                                        value={component.key}
                                        onChange={(event) =>
                                            updateComponent(index, { key: event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, "_") })
                                        }
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Weight %</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value={component.weight}
                                    onChange={(event) => updateComponent(index, { weight: Number(event.target.value) })}
                                />
                            </div>
                            <label className="bg-background flex h-10 items-center gap-2 rounded-md border px-3 text-sm">
                                <Checkbox
                                    checked={component.required}
                                    onCheckedChange={(checked) => updateComponent(index, { required: checked === true })}
                                />{" "}
                                Required
                            </label>
                            <Badge variant={componentWeightIsValid ? "default" : "destructive"} className="h-10 justify-center">
                                {componentWeight.toFixed(2)}%
                            </Badge>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-destructive"
                                onClick={() =>
                                    gradingForm.setData(
                                        "components",
                                        gradingForm.data.components.filter((_, currentIndex) => currentIndex !== index),
                                    )
                                }
                                aria-label={`Remove ${component.label}`}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}
                    {!componentWeightIsValid && (
                        <Alert variant="destructive">
                            <AlertTitle>Weights must total 100%</AlertTitle>
                            <AlertDescription>Adjust the components before publishing this policy.</AlertDescription>
                        </Alert>
                    )}
                    {gradingForm.errors.components && <p className="text-destructive text-sm">{gradingForm.errors.components}</p>}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>GWA Rules and Exemptions</CardTitle>
                    <CardDescription>
                        Configure how the General Weighted Average is calculated across student records, terms, and retakes.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-3">
                        <Label className="text-base font-semibold">Calculation Formula</Label>
                        <p className="text-muted-foreground text-xs">
                            Choose how grades and subject weights combine to compute semester and cumulative averages.
                        </p>
                        <RadioGroup
                            value={gradingForm.data.gwa_formula ?? "weighted_units"}
                            onValueChange={(value) => gradingForm.setData("gwa_formula", value as GradingFormData["gwa_formula"])}
                            className="grid gap-3 md:grid-cols-3"
                        >
                            <label className="hover:bg-muted/40 flex cursor-pointer gap-3 rounded-lg border p-3.5" htmlFor="formula-units">
                                <RadioGroupItem value="weighted_units" id="formula-units" />
                                <div>
                                    <div className="text-sm font-medium">Standard Weighted (Units)</div>
                                    <p className="text-muted-foreground text-xs">
                                        Sum(Grade × Units) divided by total units. Standard collegiate GWA.
                                    </p>
                                </div>
                            </label>
                            <label className="hover:bg-muted/40 flex cursor-pointer gap-3 rounded-lg border p-3.5" htmlFor="formula-subjects">
                                <RadioGroupItem value="weighted_subjects" id="formula-subjects" />
                                <div>
                                    <div className="text-sm font-medium">Weighted by Units / Subjects</div>
                                    <p className="text-muted-foreground text-xs">
                                        Sum(Grade × Units) divided by the student's enrolled subject count.
                                    </p>
                                </div>
                            </label>
                            <label className="hover:bg-muted/40 flex cursor-pointer gap-3 rounded-lg border p-3.5" htmlFor="formula-unweighted">
                                <RadioGroupItem value="unweighted" id="formula-unweighted" />
                                <div>
                                    <div className="text-sm font-medium">Unweighted Mean</div>
                                    <p className="text-muted-foreground text-xs">
                                        Sum(Grade) divided by total subjects, ignoring individual subject units.
                                    </p>
                                </div>
                            </label>
                        </RadioGroup>
                    </div>

                    {(gradingForm.data.gwa_formula === "weighted_subjects" || gradingForm.data.gwa_formula === "unweighted") && (
                        <div className="bg-muted/20 space-y-2 rounded-xl border p-4">
                            <Label className="text-sm font-medium">Subject Divisor Basis</Label>
                            <p className="text-muted-foreground text-xs">
                                Select which count to divide the semester total by when calculating per-term and cumulative averages.
                            </p>
                            <Select
                                value={gradingForm.data.gwa_subject_divisor_basis ?? "enrolled_subjects"}
                                onValueChange={(value) =>
                                    gradingForm.setData("gwa_subject_divisor_basis", value as GradingFormData["gwa_subject_divisor_basis"])
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="enrolled_subjects">
                                        Total subjects enrolled in the semester (Based on student enrollment)
                                    </SelectItem>
                                    <SelectItem value="graded_subjects">
                                        Graded subjects only (Avoids penalizing pending or in-progress grades)
                                    </SelectItem>
                                    <SelectItem value="curriculum_subjects">All curriculum prospectus subjects in that semester block</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label>Grade Metric for GWA</Label>
                            <Select
                                value={gradingForm.data.gwa_calculation_metric ?? "numeric_grade"}
                                onValueChange={(value) =>
                                    gradingForm.setData("gwa_calculation_metric", value as GradingFormData["gwa_calculation_metric"])
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="numeric_grade">Raw Numeric Grade (1.0–5.0 or 0–100)</SelectItem>
                                    <SelectItem value="quality_points">Band Quality Points (GPA Equivalents)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label>Retake / Repeat Rule</Label>
                            <Select
                                value={gradingForm.data.retake_strategy ?? "latest"}
                                onValueChange={(value) => gradingForm.setData("retake_strategy", value as GradingFormData["retake_strategy"])}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="latest">Latest attempt replaces prior attempts</SelectItem>
                                    <SelectItem value="highest">Highest / best grade achieved is used</SelectItem>
                                    <SelectItem value="first">First attempt with a grade is kept</SelectItem>
                                    <SelectItem value="all">All attempts count in cumulative GWA</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label>Incomplete (INC) Treatment</Label>
                            <Select
                                value={gradingForm.data.treat_incomplete_as ?? "exclude"}
                                onValueChange={(value) => gradingForm.setData("treat_incomplete_as", value as GradingFormData["treat_incomplete_as"])}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="exclude">Exclude from GWA until completed</SelectItem>
                                    <SelectItem value="fail">Treat as failing grade in GWA</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid gap-3 md:grid-cols-2">
                        <div className="bg-muted/20 flex flex-wrap items-start justify-between gap-4 rounded-xl border p-4">
                            <div>
                                <Label className="text-sm font-medium">Include failed grades in GWA</Label>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    When disabled, only bands marked as passing contribute to the weighted average.
                                </p>
                            </div>
                            <Switch
                                checked={gradingForm.data.include_failed_in_gwa}
                                onCheckedChange={(checked) => gradingForm.setData("include_failed_in_gwa", checked)}
                            />
                        </div>

                        <div className="bg-muted/20 flex flex-wrap items-start justify-between gap-4 rounded-xl border p-4">
                            <div>
                                <Label className="text-sm font-medium">Include credited / transfer subjects</Label>
                                <p className="text-muted-foreground mt-1 text-xs">Factor transferred subjects with grades into institutional GWA.</p>
                            </div>
                            <Switch
                                checked={gradingForm.data.include_credited_in_gwa ?? true}
                                onCheckedChange={(checked) => gradingForm.setData("include_credited_in_gwa", checked)}
                            />
                        </div>

                        <div className="bg-muted/20 flex flex-wrap items-start justify-between gap-4 rounded-xl border p-4">
                            <div>
                                <Label className="text-sm font-medium">Treat Grade 0 as Dropped</Label>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    When disabled, numeric grade 0.0 is treated as a failing grade instead of dropped.
                                </p>
                            </div>
                            <Switch
                                checked={gradingForm.data.zero_is_dropped ?? false}
                                onCheckedChange={(checked) => gradingForm.setData("zero_is_dropped", checked)}
                            />
                        </div>

                        <div className="bg-muted/20 flex flex-wrap items-start justify-between gap-4 rounded-xl border p-4">
                            <div>
                                <Label className="text-sm font-medium">Exclude 0-Unit Subjects</Label>
                                <p className="text-muted-foreground mt-1 text-xs">Automatically exclude zero-unit subjects from GWA calculations.</p>
                            </div>
                            <Switch
                                checked={gradingForm.data.exclude_zero_unit_subjects ?? true}
                                onCheckedChange={(checked) => gradingForm.setData("exclude_zero_unit_subjects", checked)}
                            />
                        </div>
                    </div>
                    <div className="space-y-3">
                        <Label>Keyword exclusions</Label>
                        <div className="flex flex-wrap gap-2">
                            {gradingForm.data.excluded_keywords.map((keyword) => (
                                <Badge key={keyword} variant="secondary" className="gap-1 pr-1">
                                    {keyword}
                                    <button
                                        type="button"
                                        className="hover:bg-muted rounded-full p-0.5"
                                        onClick={() =>
                                            gradingForm.setData(
                                                "excluded_keywords",
                                                gradingForm.data.excluded_keywords.filter((entry) => entry !== keyword),
                                            )
                                        }
                                    >
                                        <X className="size-3" />
                                    </button>
                                </Badge>
                            ))}
                        </div>
                        <div className="flex gap-2">
                            <Input
                                value={keywordDraft}
                                placeholder="Add a subject code or title keyword"
                                onChange={(event) => setKeywordDraft(event.target.value)}
                                onKeyDown={(event) => {
                                    if (event.key === "Enter") {
                                        event.preventDefault();
                                        addKeyword();
                                    }
                                }}
                            />
                            <Button type="button" variant="outline" onClick={addKeyword}>
                                Add
                            </Button>
                        </div>
                    </div>
                    <Separator />
                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <Label>Subject exclusions</Label>
                                <p className="text-muted-foreground text-sm">Selected subjects remain on records but do not count toward GWA.</p>
                            </div>
                            <Badge variant="outline">{gradingForm.data.excluded_subject_ids.length} selected</Badge>
                        </div>
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                className="pl-9"
                                value={courseSearch}
                                onChange={(event) => setCourseSearch(event.target.value)}
                                placeholder="Search courses or subjects"
                            />
                        </div>
                        <ScrollArea className="h-72 rounded-lg border">
                            <div className="space-y-4 p-3">
                                {filteredCourses.map((course) => (
                                    <div key={course.id} className="space-y-2">
                                        <div className="font-medium">
                                            {course.code} <span className="text-muted-foreground font-normal">{course.title}</span>
                                        </div>
                                        {course.subjects.map((subject) => (
                                            <label
                                                key={subject.id}
                                                className="hover:bg-muted/60 flex cursor-pointer items-center gap-3 rounded-md border p-2.5"
                                            >
                                                <Checkbox
                                                    checked={excludedSubjectIds.has(subject.id)}
                                                    onCheckedChange={(checked) => toggleSubject(subject.id, checked === true)}
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="font-mono text-sm">{subject.code}</span>
                                                    <span className="text-muted-foreground ml-2 text-xs">{subject.title}</span>
                                                </span>
                                                <Badge variant="outline">{subject.units}u</Badge>
                                            </label>
                                        ))}
                                    </div>
                                ))}
                            </div>
                        </ScrollArea>
                    </div>
                </CardContent>
            </Card>

            <Card className="border-primary/25 bg-primary/3">
                <CardContent className="flex gap-3 pt-6">
                    <CircleCheck className="text-primary mt-0.5 size-5 shrink-0" />
                    <div>
                        <div className="font-medium">Policy publication is deliberate</div>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Publishing does not reinterpret finalized historical grades. It creates the policy version that future grades and
                            calculations will use.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </SystemManagementLayout>
    );
}
