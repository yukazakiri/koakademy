import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import axios from "axios";
import { AlertTriangle, CheckCircle2, Loader2, UserPlus } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import type { StudentOption } from "./types";

interface CourseOption {
    id: number;
    code: string;
    title: string;
}

interface QuickCreateStudentDrawerProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initialSearch?: string;
    courses: CourseOption[];
    onStudentCreated: (student: StudentOption) => void;
}

export function QuickCreateStudentDrawer({
    open,
    onOpenChange,
    initialSearch = "",
    courses,
    onStudentCreated,
}: QuickCreateStudentDrawerProps) {
    const [studentType, setStudentType] = useState<"college" | "tesda" | "dhrt">("college");
    const [firstName, setFirstName] = useState("");
    const [middleName, setMiddleName] = useState("");
    const [lastName, setLastName] = useState("");
    const [gender, setGender] = useState("male");
    const [birthDate, setBirthDate] = useState("");
    const [email, setEmail] = useState("");
    const [phone, setPhone] = useState("");
    const [courseId, setCourseId] = useState(courses[0] ? String(courses[0].id) : "");
    const [academicYear, setAcademicYear] = useState("1");
    const [provisionPortal, setProvisionPortal] = useState(true);
    const [sendActivation, setSendActivation] = useState(true);
    const [allowDuplicate, setAllowDuplicate] = useState(false);
    const [duplicates, setDuplicates] = useState<StudentOption[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const wasOpenRef = useRef(false);

    useEffect(() => {
        const justOpened = open && !wasOpenRef.current;
        wasOpenRef.current = open;
        if (!justOpened) return;

        const trimmed = initialSearch.trim();
        if (trimmed && !/^\d+$/.test(trimmed)) {
            if (trimmed.includes("@")) {
                setEmail((prev) => prev || trimmed);
            } else {
                const parts = trimmed.split(/\s+/);
                setFirstName((prev) => prev || parts[0] || "");
                if (parts.length > 1) {
                    setLastName((prev) => prev || parts.slice(1).join(" "));
                }
            }
        }
        if (!courseId && courses[0]) {
            setCourseId(String(courses[0].id));
        }
    }, [open, initialSearch, courses, courseId]);

    const resetForm = () => {
        setFirstName("");
        setMiddleName("");
        setLastName("");
        setBirthDate("");
        setEmail("");
        setPhone("");
        setAllowDuplicate(false);
        setDuplicates([]);
        setErrors({});
    };

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setErrors({});

        try {
            const response = await axios.post(
                "/administrators/students/quick-create",
                {
                    student_type: studentType,
                    first_name: firstName.trim(),
                    middle_name: middleName.trim() || null,
                    last_name: lastName.trim(),
                    gender,
                    birth_date: birthDate,
                    email: email.trim() || null,
                    phone: phone.trim() || null,
                    course_id: courseId ? Number(courseId) : null,
                    academic_year: Number(academicYear),
                    status: "applicant",
                    provision_portal_account: provisionPortal && email.trim() !== "",
                    send_activation_link: sendActivation,
                    allow_duplicate: allowDuplicate,
                },
                { headers: { Accept: "application/json" } },
            );

            const createdStudent = response.data?.student as StudentOption | undefined;
            if (createdStudent) {
                toast.success(`Created ${createdStudent.full_name} (${createdStudent.student_id})`);
                onStudentCreated(createdStudent);
                resetForm();
                onOpenChange(false);
            }
        } catch (error: unknown) {
            const err = error as {
                response?: {
                    status?: number;
                    data?: {
                        message?: string;
                        duplicates?: StudentOption[];
                        errors?: Record<string, string[]>;
                    };
                };
            };

            if (err.response?.status === 409 && Array.isArray(err.response.data?.duplicates)) {
                setDuplicates(err.response.data.duplicates);
                toast.warning("Possible duplicate student detected — review below.");
            } else if (err.response?.data?.errors) {
                const flatErrors: Record<string, string> = {};
                for (const [key, messages] of Object.entries(err.response.data.errors)) {
                    flatErrors[key] = messages[0] ?? "Invalid value";
                }
                setErrors(flatErrors);
                toast.error(Object.values(flatErrors)[0] || "Please check the required fields.");
            } else {
                toast.error(err.response?.data?.message || "Failed to create student.");
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-[500px]">
                <SheetHeader className="border-border/70 bg-muted/20 border-b px-5 py-4">
                    <SheetTitle className="flex items-center gap-2 text-base font-semibold">
                        <UserPlus className="text-primary size-4" />
                        Quick-Create Walk-In Student
                    </SheetTitle>
                    <SheetDescription className="text-xs">
                        Create a new enrollee record without leaving the enrollment workbench. Duplicate checks run automatically.
                    </SheetDescription>
                </SheetHeader>

                <form onSubmit={handleSubmit} className="flex flex-1 flex-col overflow-hidden">
                    <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                        {duplicates.length > 0 && (
                            <Alert className="border-amber-500/40 bg-amber-500/10">
                                <AlertTriangle className="size-4 text-amber-600 dark:text-amber-400" />
                                <AlertTitle className="text-xs font-semibold text-amber-900 dark:text-amber-100">
                                    Matching student record found
                                </AlertTitle>
                                <AlertDescription className="mt-2 space-y-2 text-xs">
                                    <p className="text-amber-800 dark:text-amber-200">
                                        Select the existing student below to avoid creating a duplicate:
                                    </p>
                                    <div className="space-y-1.5">
                                        {duplicates.map((dup) => (
                                            <button
                                                key={dup.id}
                                                type="button"
                                                onClick={() => {
                                                    onStudentCreated(dup);
                                                    resetForm();
                                                    onOpenChange(false);
                                                }}
                                                className="border-amber-500/30 bg-background hover:bg-accent flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left transition-colors"
                                            >
                                                <div className="min-w-0">
                                                    <p className="text-foreground truncate font-medium">{dup.full_name}</p>
                                                    <p className="text-muted-foreground truncate font-mono text-[11px]">
                                                        {dup.student_id} · {dup.course_code || "No course"}
                                                    </p>
                                                </div>
                                                <Badge variant="secondary" className="shrink-0 text-[10px]">
                                                    <CheckCircle2 className="mr-1 size-3" />
                                                    Use existing
                                                </Badge>
                                            </button>
                                        ))}
                                    </div>
                                    <div className="flex items-center space-x-2 pt-1">
                                        <Checkbox
                                            id="quick-allow-duplicate"
                                            checked={allowDuplicate}
                                            onCheckedChange={(checked) => setAllowDuplicate(Boolean(checked))}
                                        />
                                        <Label htmlFor="quick-allow-duplicate" className="cursor-pointer text-xs font-medium">
                                            Create as a separate student record anyway
                                        </Label>
                                    </div>
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-type" className="text-xs">
                                    Student Type <span className="text-destructive">*</span>
                                </Label>
                                <Select value={studentType} onValueChange={(val) => setStudentType(val as "college" | "tesda" | "dhrt")}>
                                    <SelectTrigger id="qc-type" className="h-9 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="college">College</SelectItem>
                                        <SelectItem value="tesda">TESDA</SelectItem>
                                        <SelectItem value="dhrt">DHRT</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-year" className="text-xs">
                                    Year Level <span className="text-destructive">*</span>
                                </Label>
                                <Select value={academicYear} onValueChange={setAcademicYear}>
                                    <SelectTrigger id="qc-year" className="h-9 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">1st Year</SelectItem>
                                        <SelectItem value="2">2nd Year</SelectItem>
                                        <SelectItem value="3">3rd Year</SelectItem>
                                        <SelectItem value="4">4th Year</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="qc-course" className="text-xs">
                                Program / Course <span className="text-destructive">*</span>
                            </Label>
                            <Select value={courseId} onValueChange={setCourseId}>
                                <SelectTrigger id="qc-course" className="h-9 text-xs">
                                    <SelectValue placeholder="Select program" />
                                </SelectTrigger>
                                <SelectContent>
                                    {courses.map((course) => (
                                        <SelectItem key={course.id} value={String(course.id)}>
                                            {course.code} — {course.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.course_id && <p className="text-destructive text-xs">{errors.course_id}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-first-name" className="text-xs">
                                    First Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="qc-first-name"
                                    value={firstName}
                                    onChange={(e) => setFirstName(e.target.value)}
                                    required
                                    className="h-9 text-xs"
                                    placeholder="Juan"
                                />
                                {errors.first_name && <p className="text-destructive text-xs">{errors.first_name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-last-name" className="text-xs">
                                    Last Name <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="qc-last-name"
                                    value={lastName}
                                    onChange={(e) => setLastName(e.target.value)}
                                    required
                                    className="h-9 text-xs"
                                    placeholder="Dela Cruz"
                                />
                                {errors.last_name && <p className="text-destructive text-xs">{errors.last_name}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-middle" className="text-xs">
                                    Middle Name
                                </Label>
                                <Input
                                    id="qc-middle"
                                    value={middleName}
                                    onChange={(e) => setMiddleName(e.target.value)}
                                    className="h-9 text-xs"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-gender" className="text-xs">
                                    Gender <span className="text-destructive">*</span>
                                </Label>
                                <Select value={gender} onValueChange={setGender}>
                                    <SelectTrigger id="qc-gender" className="h-9 text-xs">
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
                                    Birth Date <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="qc-birth-date"
                                    type="date"
                                    value={birthDate}
                                    onChange={(e) => setBirthDate(e.target.value)}
                                    required
                                    className="h-9 font-mono text-xs tabular-nums"
                                />
                                {errors.birth_date && <p className="text-destructive text-xs">{errors.birth_date}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-email" className="text-xs">
                                    School / Personal Email
                                </Label>
                                <Input
                                    id="qc-email"
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    className="h-9 text-xs"
                                    placeholder="student@school.edu"
                                />
                                {errors.email && <p className="text-destructive text-xs">{errors.email}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="qc-phone" className="text-xs">
                                    Mobile Number
                                </Label>
                                <Input
                                    id="qc-phone"
                                    value={phone}
                                    onChange={(e) => setPhone(e.target.value)}
                                    className="h-9 font-mono text-xs tabular-nums"
                                    placeholder="09171234567"
                                />
                            </div>
                        </div>

                        <div className="border-border/70 bg-muted/20 space-y-2 rounded-xl border p-3.5">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="qc-provision-portal"
                                    checked={provisionPortal}
                                    onCheckedChange={(checked) => setProvisionPortal(Boolean(checked))}
                                    disabled={!email.trim()}
                                />
                                <Label htmlFor="qc-provision-portal" className="cursor-pointer text-xs font-medium">
                                    Auto-provision Student Portal account
                                </Label>
                            </div>
                            {provisionPortal && email.trim() && (
                                <div className="flex items-center space-x-2 pl-6">
                                    <Checkbox
                                        id="qc-send-activation"
                                        checked={sendActivation}
                                        onCheckedChange={(checked) => setSendActivation(Boolean(checked))}
                                    />
                                    <Label htmlFor="qc-send-activation" className="text-muted-foreground cursor-pointer text-xs font-normal">
                                        Email password-setup link immediately
                                    </Label>
                                </div>
                            )}
                        </div>
                    </div>

                    <SheetFooter className="border-border/70 bg-muted/20 border-t px-5 py-3">
                        <Button type="button" variant="outline" size="sm" onClick={() => onOpenChange(false)} disabled={submitting}>
                            Cancel
                        </Button>
                        <Button type="submit" size="sm" disabled={submitting} className="gap-1.5 active:scale-[0.97]">
                            {submitting ? <Loader2 className="size-3.5 animate-spin" /> : <UserPlus className="size-3.5" />}
                            Create &amp; Select for Enrollment
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
