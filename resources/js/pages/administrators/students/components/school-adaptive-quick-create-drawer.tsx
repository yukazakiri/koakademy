import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";
import { Link, router } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, ClipboardPlus, ExternalLink, Loader2, Sparkles, UserPlus } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import type { IdentifierPolicy, SchoolProfile, StudentConfiguration } from "../shared-types";

export interface QuickCreateCourseOption {
    id: number;
    code: string;
    title: string;
    curriculum_kind?: string | null;
}

export interface QuickCreateStrandOption {
    id: number;
    value: string;
    label: string;
}

interface DuplicateCandidate {
    id: number;
    student_id: string | null;
    full_name: string;
    email: string | null;
    course_code: string | null;
    formatted_academic_year: string | null;
}

interface SchoolAdaptiveQuickCreateDrawerProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    configuration?: StudentConfiguration;
    identifierPolicy?: IdentifierPolicy;
    schoolProfile?: SchoolProfile;
    courses: QuickCreateCourseOption[];
    shsStrands: QuickCreateStrandOption[];
    onCreated?: () => void;
}

export function SchoolAdaptiveQuickCreateDrawer({
    open,
    onOpenChange,
    configuration,
    identifierPolicy,
    schoolProfile,
    courses,
    shsStrands,
    onCreated,
}: SchoolAdaptiveQuickCreateDrawerProps) {
    const enabledTypes = configuration?.enabled_types ?? ["college"];
    const defaultType = configuration?.default_type ?? enabledTypes[0] ?? "college";

    const [studentType, setStudentType] = useState<string>(defaultType);
    const [status, setStatus] = useState<string>("enrolled");
    const [studentId, setStudentId] = useState("");
    const [idMode, setIdMode] = useState<"automatic" | "manual">(
        identifierPolicy?.assignment_mode === "manual" ? "manual" : "automatic",
    );
    const [lrn, setLrn] = useState("");
    const [firstName, setFirstName] = useState("");
    const [middleName, setMiddleName] = useState("");
    const [lastName, setLastName] = useState("");
    const [gender, setGender] = useState<string>(configuration?.defaults?.gender || "male");
    const [birthDate, setBirthDate] = useState("");
    const [email, setEmail] = useState("");
    const [phone, setPhone] = useState("");
    const [courseId, setCourseId] = useState<string>("");
    const [shsStrandId, setShsStrandId] = useState<string>("");
    const [academicYear, setAcademicYear] = useState<string>("1");
    const [provisionPortal, setProvisionPortal] = useState(true);
    const [sendActivation, setSendActivation] = useState(true);
    const [duplicateCheckState, setDuplicateCheckState] = useState<{
        identityKey: string;
        candidates: DuplicateCandidate[];
        confirmed: boolean;
    } | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const isShs = studentType === "shs";
    const isTesda = studentType === "tesda";

    const externalEnabled = Boolean(
        configuration?.external_identifier?.enabled &&
            configuration.external_identifier.student_types.includes(studentType),
    );
    const usesExternalAsId = Boolean(externalEnabled && configuration?.external_identifier?.use_as_student_id);
    const externalLabel = configuration?.external_identifier?.label || "External ID";
    const idLabel = identifierPolicy?.display_label || "Student ID";
    const emailVisible = configuration?.fields?.email?.visible ?? true;
    const emailRequired = Boolean(configuration?.fields?.email?.visible && configuration?.fields?.email?.required);
    const phoneVisible = configuration?.fields?.phone?.visible ?? true;
    const phoneRequired = Boolean(configuration?.fields?.phone?.visible && configuration?.fields?.phone?.required);

    const yearLevels = useMemo(() => {
        const list = configuration?.year_levels?.[studentType];
        if (list && list.length > 0) return list;
        return isShs
            ? [
                  { value: 11, label: "Grade 11" },
                  { value: 12, label: "Grade 12" },
              ]
            : [
                  { value: 1, label: "1st Year" },
                  { value: 2, label: "2nd Year" },
                  { value: 3, label: "3rd Year" },
                  { value: 4, label: "4th Year" },
              ];
    }, [configuration, studentType, isShs]);

    const filteredCourses = useMemo(() => {
        if (isTesda) {
            const tesda = courses.filter((c) => c.curriculum_kind === "tesda_qualification");
            return tesda.length > 0 ? tesda : courses;
        }
        const nonTesda = courses.filter((c) => c.curriculum_kind !== "tesda_qualification");
        return nonTesda.length > 0 ? nonTesda : courses;
    }, [courses, isTesda]);

    useEffect(() => {
        if (!open) return;
        setStudentType(defaultType);
        setIdMode(identifierPolicy?.assignment_mode === "manual" ? "manual" : "automatic");
    }, [open, defaultType, identifierPolicy?.assignment_mode]);

    useEffect(() => {
        if (!yearLevels.some((lvl) => String(lvl.value) === academicYear)) {
            setAcademicYear(String(yearLevels[0]?.value ?? (isShs ? 11 : 1)));
        }
    }, [yearLevels, academicYear, isShs]);

    useEffect(() => {
        if (isShs) {
            if (!shsStrandId && shsStrands[0]) {
                setShsStrandId(String(shsStrands[0].id));
            }
        } else if (!filteredCourses.some((c) => String(c.id) === courseId)) {
            setCourseId(filteredCourses[0] ? String(filteredCourses[0].id) : "");
        }
    }, [isShs, filteredCourses, shsStrands, courseId, shsStrandId]);

    const currentIdentityKey = useMemo(
        () =>
            JSON.stringify([
                firstName.trim().toLowerCase(),
                lastName.trim().toLowerCase(),
                birthDate.trim(),
                emailVisible ? email.trim().toLowerCase() : "",
                externalEnabled ? lrn.trim().toLowerCase() : "",
            ]),
        [firstName, lastName, birthDate, emailVisible, email, externalEnabled, lrn],
    );
    const currentIdentityKeyRef = useRef(currentIdentityKey);
    useEffect(() => {
        currentIdentityKeyRef.current = currentIdentityKey;
    }, [currentIdentityKey]);

    const activeDuplicateState =
        duplicateCheckState && duplicateCheckState.identityKey === currentIdentityKey
            ? duplicateCheckState
            : null;
    const duplicates = activeDuplicateState?.candidates ?? [];
    const allowDuplicate = activeDuplicateState?.confirmed ?? false;

    useEffect(() => {
        setDuplicateCheckState((prev) =>
            prev && prev.identityKey !== currentIdentityKey ? null : prev,
        );
    }, [currentIdentityKey]);

    const resetForm = () => {
        const defaultIsShs = defaultType === "shs";
        const defaultIsTesda = defaultType === "tesda";
        const defaultYearLevels = configuration?.year_levels?.[defaultType];
        const defaultAcademicYear = String(
            defaultYearLevels && defaultYearLevels.length > 0
                ? defaultYearLevels[0].value
                : defaultIsShs
                  ? 11
                  : 1,
        );
        const defaultCourseList = defaultIsTesda
            ? courses.filter((c) => c.curriculum_kind === "tesda_qualification")
            : courses.filter((c) => c.curriculum_kind !== "tesda_qualification");
        const resolvedDefaultCourses = defaultCourseList.length > 0 ? defaultCourseList : courses;

        setStudentType(defaultType);
        setStatus("enrolled");
        setIdMode(identifierPolicy?.assignment_mode === "manual" ? "manual" : "automatic");
        setStudentId("");
        setLrn("");
        setFirstName("");
        setMiddleName("");
        setLastName("");
        setGender(configuration?.defaults?.gender || "male");
        setBirthDate("");
        setEmail("");
        setPhone("");
        setCourseId(defaultIsShs ? "" : resolvedDefaultCourses[0] ? String(resolvedDefaultCourses[0].id) : "");
        setShsStrandId(defaultIsShs && shsStrands[0] ? String(shsStrands[0].id) : "");
        setAcademicYear(defaultAcademicYear);
        setProvisionPortal(true);
        setSendActivation(true);
        setDuplicateCheckState(null);
        setErrors({});
    };

    const submitQuickCreate = async (redirectToEnrollment: boolean) => {
        if (submitting) return;
        const submittedIdentityKey = currentIdentityKey;
        const allowDuplicateForRequest = Boolean(
            duplicateCheckState &&
                duplicateCheckState.identityKey === submittedIdentityKey &&
                duplicateCheckState.confirmed,
        );

        setSubmitting(true);
        setErrors({});

        try {
            const response = await axios.post(
                "/administrators/students/quick-create",
                {
                    student_type: studentType,
                    identifier_assignment: idMode,
                    student_id: !usesExternalAsId && idMode === "manual" ? studentId.trim() : undefined,
                    lrn: externalEnabled ? lrn.trim() || null : null,
                    first_name: firstName.trim(),
                    middle_name: middleName.trim() || null,
                    last_name: lastName.trim(),
                    gender,
                    birth_date: birthDate,
                    email: emailVisible ? email.trim() || null : null,
                    phone: phoneVisible ? phone.trim() || null : null,
                    course_id: isShs ? null : courseId ? Number(courseId) : null,
                    shs_strand_id: isShs ? (shsStrandId ? Number(shsStrandId) : null) : null,
                    academic_year: Number(academicYear),
                    status,
                    provision_portal_account: provisionPortal && email.trim() !== "",
                    send_activation_link: sendActivation,
                    allow_duplicate: allowDuplicateForRequest,
                },
                { headers: { Accept: "application/json" } },
            );

            const created = response.data?.student;
            toast.success(`Created ${created?.full_name || "student"} (${created?.student_id || "assigned"})`);
            resetForm();
            onOpenChange(false);
            onCreated?.();

            if (redirectToEnrollment && created?.id) {
                router.visit(`/administrators/enrollments/create?student_id=${created.id}`);
            } else {
                router.reload();
            }
        } catch (error: unknown) {
            const err = error as {
                response?: {
                    status?: number;
                    data?: {
                        message?: string;
                        duplicates?: DuplicateCandidate[];
                        errors?: Record<string, string[]>;
                    };
                };
            };

            if (err.response?.status === 409 && Array.isArray(err.response.data?.duplicates)) {
                if (currentIdentityKeyRef.current === submittedIdentityKey) {
                    const matchedDuplicates = err.response.data.duplicates;
                    setDuplicateCheckState((prev) => ({
                        identityKey: submittedIdentityKey,
                        candidates: matchedDuplicates,
                        confirmed: prev?.identityKey === submittedIdentityKey ? prev.confirmed : false,
                    }));
                    toast.warning("Possible duplicate record found — review below.");
                }
            } else if (err.response?.data?.errors) {
                const flat: Record<string, string> = {};
                for (const [k, msgs] of Object.entries(err.response.data.errors)) {
                    flat[k] = msgs[0] ?? "Invalid value";
                }
                setErrors(flat);
                toast.error(Object.values(flat)[0] || "Check required fields.");
            } else {
                toast.error(err.response?.data?.message || "Failed to create student.");
            }
        } finally {
            setSubmitting(false);
        }
    };

    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen && submitting) return;
        onOpenChange(nextOpen);
    };

    return (
        <Sheet open={open} onOpenChange={handleOpenChange}>
            <SheetContent
                side="right"
                className="flex w-full flex-col gap-0 p-0 sm:max-w-[520px]"
                onEscapeKeyDown={(event) => {
                    if (submitting) event.preventDefault();
                }}
                onPointerDownOutside={(event) => {
                    if (submitting) event.preventDefault();
                }}
                onInteractOutside={(event) => {
                    if (submitting) event.preventDefault();
                }}
            >
                <SheetHeader className="border-border/70 bg-muted/20 border-b px-5 py-4">
                    <div className="flex items-center justify-between gap-2 pr-6">
                        <SheetTitle className="flex items-center gap-2 text-base font-semibold">
                            <UserPlus className="text-primary size-4" />
                            Quick Add Student
                        </SheetTitle>
                        {schoolProfile && (
                            <Badge variant="outline" className="text-[10px]">
                                {schoolProfile.school_level_label}
                            </Badge>
                        )}
                    </div>
                    <SheetDescription className="text-xs">
                        Fast walk-in intake adapted to your school configuration. Duplicate checks run automatically.
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const submitter = (e.nativeEvent as SubmitEvent).submitter as HTMLButtonElement | null;
                        const redirectToEnrollment = submitter?.value === "create_enrollment";
                        void submitQuickCreate(redirectToEnrollment);
                    }}
                    className="flex min-h-0 flex-1 flex-col overflow-hidden"
                >
                    <div className="flex-1 overflow-y-auto px-5 py-4">
                        <fieldset disabled={submitting} className="space-y-4">
                        {duplicates.length > 0 && (
                            <Alert className="border-amber-500/40 bg-amber-500/10">
                                <AlertTriangle className="size-4 text-amber-600 dark:text-amber-400" />
                                <AlertTitle className="text-xs font-semibold">Potential duplicate detected</AlertTitle>
                                <AlertDescription className="mt-2 space-y-2 text-xs">
                                    <div className="space-y-1.5">
                                        {duplicates.map((dup) => (
                                            <div
                                                key={dup.id}
                                                className="border-border/70 bg-background flex items-center justify-between gap-2 rounded-lg border px-2.5 py-1.5"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">{dup.full_name}</p>
                                                    <p className="text-muted-foreground text-[11px]">
                                                        {dup.student_id} · {dup.course_code || "No program"} ·{" "}
                                                        {dup.formatted_academic_year}
                                                    </p>
                                                </div>
                                                <Button
                                                    render={
                                                        <Link
                                                            href={`/administrators/students/${dup.id}`}
                                                            aria-disabled={submitting}
                                                            onClick={(e) => {
                                                                if (submitting) e.preventDefault();
                                                            }}
                                                        />
                                                    }
                                                    nativeButton={false}
                                                    size="sm"
                                                    variant="outline"
                                                    className={cn(
                                                        "h-7 gap-1 text-[11px]",
                                                        submitting && "pointer-events-none opacity-50",
                                                    )}
                                                >
                                                    Open
                                                    <ExternalLink className="size-3" />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                    <label className="flex items-center gap-2 pt-1 text-[11px]">
                                        <Checkbox
                                            checked={allowDuplicate}
                                            disabled={submitting}
                                            onCheckedChange={(checked) =>
                                                setDuplicateCheckState((prev) =>
                                                    prev && prev.identityKey === currentIdentityKey
                                                        ? { ...prev, confirmed: Boolean(checked) }
                                                        : prev,
                                                )
                                            }
                                        />
                                        <span>Confirm creating a separate student record anyway</span>
                                    </label>
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Category & Status */}
                        <div className="grid grid-cols-2 gap-3">
                            {enabledTypes.length > 1 && (
                                <div className="space-y-1.5">
                                    <Label className="text-xs">Student Category *</Label>
                                    <Select value={studentType} onValueChange={setStudentType} disabled={submitting}>
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {enabledTypes.map((t) => (
                                                <SelectItem key={t} value={t}>
                                                    {configuration?.type_labels?.[t] || t.toUpperCase()}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            <div className={enabledTypes.length > 1 ? "space-y-1.5" : "col-span-2 space-y-1.5"}>
                                <Label className="text-xs">Initial Status *</Label>
                                <Select value={status} onValueChange={setStatus} disabled={submitting}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="enrolled">Enrolled</SelectItem>
                                        <SelectItem value="applicant">Applicant</SelectItem>
                                        <SelectItem value="on_leave">On Leave</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Identifier Policy */}
                        {!usesExternalAsId && identifierPolicy?.assignment_mode !== "automatic" && (
                            <div className="border-border/70 bg-muted/20 space-y-2 rounded-lg border p-3">
                                <div className="flex items-center justify-between">
                                    <Label className="text-xs font-medium">{idLabel}</Label>
                                    {identifierPolicy?.assignment_mode === "hybrid" && (
                                        <div className="flex items-center gap-1 text-[11px]">
                                            <button
                                                type="button"
                                                onClick={() => setIdMode("automatic")}
                                                className={`rounded px-2 py-0.5 ${idMode === "automatic" ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted"}`}
                                            >
                                                Auto
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setIdMode("manual")}
                                                className={`rounded px-2 py-0.5 ${idMode === "manual" ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted"}`}
                                            >
                                                Manual
                                            </button>
                                        </div>
                                    )}
                                </div>
                                {idMode === "manual" ? (
                                    <Input
                                        value={studentId}
                                        onChange={(e) => setStudentId(e.target.value)}
                                        placeholder={`Enter ${idLabel}`}
                                        className="h-9 font-mono text-xs"
                                        required
                                    />
                                ) : (
                                    <p className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
                                        <Sparkles className="text-primary size-3" />
                                        Auto-generated using school sequence upon save.
                                    </p>
                                )}
                                {errors.student_id && <p className="text-destructive text-[11px]">{errors.student_id}</p>}
                            </div>
                        )}

                        {externalEnabled && (
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-lrn" className="text-xs">
                                    {externalLabel}
                                    {(configuration?.external_identifier?.required || usesExternalAsId) && " *"}
                                </Label>
                                <Input
                                    id="qc-lrn"
                                    value={lrn}
                                    onChange={(e) => setLrn(e.target.value)}
                                    placeholder={`Enter ${externalLabel}`}
                                    className="h-9 font-mono text-xs"
                                    required={Boolean(configuration?.external_identifier?.required || usesExternalAsId)}
                                />
                                {errors.lrn && <p className="text-destructive text-[11px]">{errors.lrn}</p>}
                            </div>
                        )}

                        {/* Identity */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-first-name" className="text-xs">
                                    First Name *
                                </Label>
                                <Input
                                    id="qc-first-name"
                                    value={firstName}
                                    onChange={(e) => setFirstName(e.target.value)}
                                    placeholder="Juan"
                                    className="h-9 text-xs"
                                    required
                                />
                                {errors.first_name && <p className="text-destructive text-[11px]">{errors.first_name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-last-name" className="text-xs">
                                    Last Name *
                                </Label>
                                <Input
                                    id="qc-last-name"
                                    value={lastName}
                                    onChange={(e) => setLastName(e.target.value)}
                                    placeholder="Dela Cruz"
                                    className="h-9 text-xs"
                                    required
                                />
                                {errors.last_name && <p className="text-destructive text-[11px]">{errors.last_name}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-middle-name" className="text-xs">
                                    Middle Name
                                </Label>
                                <Input
                                    id="qc-middle-name"
                                    value={middleName}
                                    onChange={(e) => setMiddleName(e.target.value)}
                                    placeholder="Optional"
                                    className="h-9 text-xs"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">Gender *</Label>
                                <Select value={gender} onValueChange={setGender} disabled={submitting}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="male">Male</SelectItem>
                                        <SelectItem value="female">Female</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                        <SelectItem value="prefer_not_to_say">Prefer not to say</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-birth-date" className="text-xs">
                                    Birth Date *
                                </Label>
                                <Input
                                    id="qc-birth-date"
                                    type="date"
                                    value={birthDate}
                                    onChange={(e) => setBirthDate(e.target.value)}
                                    className="h-9 text-xs"
                                    required
                                />
                                {errors.birth_date && <p className="text-destructive text-[11px]">{errors.birth_date}</p>}
                            </div>
                        </div>

                        {/* Academic Placement */}
                        <div className="grid grid-cols-3 gap-3">
                            <div className="col-span-2 space-y-1.5">
                                <Label className="text-xs">
                                    {isShs ? "SHS Strand *" : isTesda ? "TESDA Qualification *" : "Program / Course *"}
                                </Label>
                                {isShs ? (
                                    <Select value={shsStrandId} onValueChange={setShsStrandId} disabled={submitting}>
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue placeholder="Select strand" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {shsStrands.map((s) => (
                                                <SelectItem key={s.id} value={String(s.id)}>
                                                    {s.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <Select value={courseId} onValueChange={setCourseId} disabled={submitting}>
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue placeholder="Select program" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filteredCourses.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>
                                                    {c.code} — {c.title}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                {(errors.course_id || errors.shs_strand_id) && (
                                    <p className="text-destructive text-[11px]">{errors.course_id || errors.shs_strand_id}</p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">{isShs ? "Grade *" : "Level *"}</Label>
                                <Select value={academicYear} onValueChange={setAcademicYear} disabled={submitting}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {yearLevels.map((lvl) => (
                                            <SelectItem key={lvl.value} value={String(lvl.value)}>
                                                {lvl.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Contact */}
                        {(emailVisible || phoneVisible) && (
                            <div className="grid grid-cols-2 gap-3">
                                {emailVisible && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="qc-email" className="text-xs">
                                            {configuration?.fields?.email?.label || "Email"}
                                            {emailRequired ? " *" : ""}
                                        </Label>
                                        <Input
                                            id="qc-email"
                                            type="email"
                                            value={email}
                                            onChange={(e) => setEmail(e.target.value)}
                                            placeholder="student@school.edu"
                                            className="h-9 text-xs"
                                            required={emailRequired}
                                        />
                                        {errors.email && <p className="text-destructive text-[11px]">{errors.email}</p>}
                                    </div>
                                )}
                                {phoneVisible && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="qc-phone" className="text-xs">
                                            {configuration?.fields?.phone?.label || "Phone"}
                                            {phoneRequired ? " *" : ""}
                                        </Label>
                                        <Input
                                            id="qc-phone"
                                            value={phone}
                                            onChange={(e) => setPhone(e.target.value)}
                                            placeholder="+63 9XX XXX XXXX"
                                            className="h-9 text-xs"
                                            required={phoneRequired}
                                        />
                                        {errors.phone && <p className="text-destructive text-[11px]">{errors.phone}</p>}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Portal Provisioning */}
                        {emailVisible && (
                            <div className="border-border/70 bg-muted/20 space-y-2 rounded-lg border p-3">
                                <label className="flex items-center gap-2 text-xs font-medium">
                                    <Checkbox
                                        checked={provisionPortal}
                                        disabled={submitting}
                                        onCheckedChange={(checked) => setProvisionPortal(Boolean(checked))}
                                    />
                                    <span>Auto-provision Student Portal account (requires email)</span>
                                </label>
                                {provisionPortal && (
                                    <label className="text-muted-foreground flex items-center gap-2 pl-6 text-[11px]">
                                        <Checkbox
                                            checked={sendActivation}
                                            disabled={submitting}
                                            onCheckedChange={(checked) => setSendActivation(Boolean(checked))}
                                        />
                                        <span>Send activation / password setup email immediately</span>
                                    </label>
                                )}
                            </div>
                        )}
                        </fieldset>
                    </div>

                    <SheetFooter className="border-border/70 bg-muted/20 flex-row items-center justify-between gap-2 border-t px-5 py-3.5">
                        <Button
                            render={
                                <Link
                                    href="/administrators/students/create"
                                    aria-disabled={submitting}
                                    onClick={(e) => {
                                        if (submitting) e.preventDefault();
                                    }}
                                />
                            }
                            nativeButton={false}
                            variant="ghost"
                            size="sm"
                            className={cn("text-muted-foreground text-xs", submitting && "pointer-events-none opacity-50")}
                        >
                            Full Intake Form
                        </Button>
                        <div className="flex flex-row-reverse items-center gap-2">
                            <Button
                                type="submit"
                                name="submit_action"
                                value="create"
                                size="sm"
                                disabled={submitting}
                                className="gap-1.5 text-xs"
                            >
                                {submitting ? <Loader2 className="size-3.5 animate-spin" /> : <UserPlus className="size-3.5" />}
                                Create Student
                            </Button>
                            <Button
                                type="submit"
                                name="submit_action"
                                value="create_enrollment"
                                variant="outline"
                                size="sm"
                                disabled={submitting}
                                className="gap-1.5 text-xs"
                            >
                                <ClipboardPlus className="size-3.5" />
                                Create &amp; Enroll
                            </Button>
                        </div>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
