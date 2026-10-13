import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { IconTile } from "@/components/reui/icon-tile";
import {
    Stepper,
    StepperContent,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperNav,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from "@/components/reui/stepper";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { Link, useForm } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    BookOpen,
    Calendar,
    Camera,
    Check,
    CheckCircle2,
    Clock,
    GraduationCap,
    Mail,
    Phone,
    Save,
    Sparkles,
    Trash2,
    Upload,
    User,
    UserPlus,
} from "lucide-react";
import type { FormEventHandler } from "react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

type Option = { value: string; label: string };

export type FacultyFormPayload = {
    id?: string;
    faculty_id_number: string | null;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    email: string;
    phone_number: string | null;
    department: string | null;
    position: string | null;
    status: string | null;
    gender: string | null;
    birth_date: string | null;
    date_employed: string | null;
    age: number | null;
    office_hours: string | null;
    address_line1: string | null;
    biography: string | null;
    education: string | null;
    courses_taught: string | null;
    photo_url: string | null;
};

type FacultyFormProps = {
    mode: "create" | "edit";
    defaults?: Partial<FacultyFormPayload>;
    faculty?: FacultyFormPayload;
    options: {
        departments: string[];
        statuses: Option[];
        genders: Option[];
    };
};

function blankToString(value: string | number | null | undefined): string {
    return value === null || value === undefined ? "" : String(value);
}

function calculateAgeFromBirthDate(birthDate: string): number | "" {
    if (!birthDate) return "";
    const birth = new Date(birthDate);
    if (isNaN(birth.getTime())) return "";

    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }

    return age > 0 ? age : "";
}

export function FacultyForm({ mode, defaults, faculty, options }: FacultyFormProps) {
    const isEdit = mode === "edit";
    const profile = faculty ?? defaults;

    const [currentStep, setCurrentStep] = useState(1);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { data, setData, post, put, processing, errors, progress } = useForm({
        faculty_id_number: blankToString(profile?.faculty_id_number),
        first_name: blankToString(profile?.first_name),
        last_name: blankToString(profile?.last_name),
        middle_name: blankToString(profile?.middle_name),
        email: blankToString(profile?.email),
        department: blankToString(profile?.department),
        position: blankToString(profile?.position),
        status: blankToString(profile?.status) || "active",
        gender: blankToString(profile?.gender),
        birth_date: blankToString(profile?.birth_date),
        date_employed: blankToString(profile?.date_employed),
        age: blankToString(profile?.age),
        phone_number: blankToString(profile?.phone_number),
        office_hours: blankToString(profile?.office_hours),
        address_line1: blankToString(profile?.address_line1),
        biography: blankToString(profile?.biography),
        education: blankToString(profile?.education),
        courses_taught: blankToString(profile?.courses_taught),
        photo: null as File | null,
    });

    const [photoPreview, setPhotoPreview] = useState<string | null>(profile?.photo_url ?? null);

    const departmentSuggestions = useMemo(() => options.departments.filter((dept) => dept && dept.trim().length > 0), [options.departments]);

    const displayName = useMemo(() => {
        const parts = [data.first_name, data.middle_name, data.last_name].map((p) => p.trim()).filter(Boolean);
        return parts.length ? parts.join(" ") : isEdit ? "Faculty Member" : "New Faculty Member";
    }, [data.first_name, data.middle_name, data.last_name, isEdit]);

    const handleBirthDateChange = (val: string) => {
        setData((prev) => {
            const calculatedAge = calculateAgeFromBirthDate(val);
            return {
                ...prev,
                birth_date: val,
                age: calculatedAge !== "" ? String(calculatedAge) : prev.age,
            };
        });
    };

    const insertOfficeHoursPreset = (preset: string) => {
        setData("office_hours", data.office_hours ? `${data.office_hours}; ${preset}` : preset);
    };

    const checklist = useMemo(() => {
        return [
            { label: "Faculty ID", done: data.faculty_id_number.trim().length > 0, required: true },
            { label: "Full Name", done: data.first_name.trim().length > 0 && data.last_name.trim().length > 0, required: true },
            { label: "Email Address", done: data.email.trim().length > 0, required: true },
            { label: "Department", done: data.department.trim().length > 0, required: false },
            { label: "Academic Title / Position", done: data.position.trim().length > 0, required: false },
            { label: "Contact Phone", done: data.phone_number.trim().length > 0, required: false },
            { label: "Office Hours", done: data.office_hours.trim().length > 0, required: false },
            { label: "Education & Credentials", done: data.education.trim().length > 0, required: false },
            { label: "Courses Taught", done: data.courses_taught.trim().length > 0, required: false },
            { label: "Academic Bio", done: data.biography.trim().length > 0, required: false },
        ];
    }, [data]);

    const completion = useMemo(() => {
        const completed = checklist.filter((item) => item.done).length;
        return Math.round((completed / checklist.length) * 100);
    }, [checklist]);

    const handlePhotoSelect = (file: File | null) => {
        if (!file) {
            setData("photo", null);
            setPhotoPreview(profile?.photo_url ?? null);
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            toast.error("Photo size exceeds 2MB limit. Please choose a smaller file.");
            return;
        }

        setData("photo", file);
        setPhotoPreview(URL.createObjectURL(file));
    };

    const removePhoto = () => {
        setData("photo", null);
        setPhotoPreview(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = "";
        }
    };

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key === "s") {
                event.preventDefault();
                submit(event as unknown as React.FormEvent);
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [data, isEdit, faculty]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        if (isEdit && faculty?.id) {
            put(route("administrators.faculties.update", faculty.id), {
                forceFormData: true,
                onSuccess: () => toast.success("Faculty profile updated successfully"),
            });
            return;
        }

        post(route("administrators.faculties.store"), {
            forceFormData: true,
            onSuccess: () => toast.success("Faculty profile created successfully"),
        });
    };

    const backHref = isEdit && faculty?.id ? route("administrators.faculties.show", faculty.id) : route("administrators.faculties.index");

    const statusBadgeVariant =
        data.status === "active" ? ("success-light" as const) : data.status === "on_leave" ? ("warning-light" as const) : ("invert-light" as const);

    return (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            {/* Left Column: Form with ReUI Stepper */}
            <form onSubmit={submit} className="flex flex-col gap-6">
                <Stepper value={currentStep} onValueChange={setCurrentStep} className="w-full">
                    {/* ReUI Stepper Navigation */}
                    <div className="bg-card rounded-lg border p-3 shadow-xs">
                        <StepperNav className="gap-2">
                            <StepperItem step={1}>
                                <StepperTrigger className="text-xs">
                                    <StepperIndicator>1</StepperIndicator>
                                    <div className="hidden flex-col text-left sm:flex">
                                        <StepperTitle className="text-xs">Identity</StepperTitle>
                                        <StepperDescription className="text-[11px]">Credentials</StepperDescription>
                                    </div>
                                </StepperTrigger>
                                <StepperSeparator />
                            </StepperItem>

                            <StepperItem step={2}>
                                <StepperTrigger className="text-xs">
                                    <StepperIndicator>2</StepperIndicator>
                                    <div className="hidden flex-col text-left sm:flex">
                                        <StepperTitle className="text-xs">Academic</StepperTitle>
                                        <StepperDescription className="text-[11px]">Teaching</StepperDescription>
                                    </div>
                                </StepperTrigger>
                                <StepperSeparator />
                            </StepperItem>

                            <StepperItem step={3}>
                                <StepperTrigger className="text-xs">
                                    <StepperIndicator>3</StepperIndicator>
                                    <div className="hidden flex-col text-left sm:flex">
                                        <StepperTitle className="text-xs">Personal</StepperTitle>
                                        <StepperDescription className="text-[11px]">Records</StepperDescription>
                                    </div>
                                </StepperTrigger>
                                <StepperSeparator />
                            </StepperItem>

                            <StepperItem step={4}>
                                <StepperTrigger className="text-xs">
                                    <StepperIndicator>4</StepperIndicator>
                                    <div className="hidden flex-col text-left sm:flex">
                                        <StepperTitle className="text-xs">Review</StepperTitle>
                                        <StepperDescription className="text-[11px]">Verification</StepperDescription>
                                    </div>
                                </StepperTrigger>
                            </StepperItem>
                        </StepperNav>
                    </div>

                    {/* Step 1: Identity & Employment */}
                    <StepperContent value={1} className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <IconTile variant="soft" size="xs" className="text-primary">
                                        <User />
                                    </IconTile>
                                    Identity & Employment Status
                                </CardTitle>
                                <CardDescription>
                                    Primary credentials used for institution records, class scheduling, and portal authentication.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="faculty_id_number" className="flex items-center justify-between">
                                        <span>Faculty ID Number</span>
                                        <span className="text-destructive text-[11px] font-medium">* Required</span>
                                    </Label>
                                    <Input
                                        id="faculty_id_number"
                                        placeholder="e.g. FAC-2026-001"
                                        value={data.faculty_id_number}
                                        onChange={(e) => setData("faculty_id_number", e.target.value)}
                                        required
                                        className={cn(errors.faculty_id_number && "border-destructive")}
                                    />
                                    {errors.faculty_id_number ? (
                                        <p className="text-destructive text-xs">{errors.faculty_id_number}</p>
                                    ) : (
                                        <p className="text-muted-foreground text-xs">Unique identifier used in system records and ID cards.</p>
                                    )}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="email" className="flex items-center justify-between">
                                        <span>Institutional Email</span>
                                        <span className="text-destructive text-[11px] font-medium">* Required</span>
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        placeholder="faculty@koakademy.edu"
                                        value={data.email}
                                        onChange={(e) => setData("email", e.target.value)}
                                        required
                                        className={cn(errors.email && "border-destructive")}
                                    />
                                    {errors.email ? (
                                        <p className="text-destructive text-xs">{errors.email}</p>
                                    ) : (
                                        <p className="text-muted-foreground text-xs">Used for portal login notifications and communication.</p>
                                    )}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="first_name" className="flex items-center justify-between">
                                        <span>First Name</span>
                                        <span className="text-destructive text-[11px] font-medium">* Required</span>
                                    </Label>
                                    <Input
                                        id="first_name"
                                        placeholder="First name"
                                        value={data.first_name}
                                        onChange={(e) => setData("first_name", e.target.value)}
                                        required
                                        className={cn(errors.first_name && "border-destructive")}
                                    />
                                    {errors.first_name ? <p className="text-destructive text-xs">{errors.first_name}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="last_name" className="flex items-center justify-between">
                                        <span>Last Name</span>
                                        <span className="text-destructive text-[11px] font-medium">* Required</span>
                                    </Label>
                                    <Input
                                        id="last_name"
                                        placeholder="Last name"
                                        value={data.last_name}
                                        onChange={(e) => setData("last_name", e.target.value)}
                                        required
                                        className={cn(errors.last_name && "border-destructive")}
                                    />
                                    {errors.last_name ? <p className="text-destructive text-xs">{errors.last_name}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5 sm:col-span-2">
                                    <Label htmlFor="middle_name">Middle Name / Suffix</Label>
                                    <Input
                                        id="middle_name"
                                        placeholder="Middle name or extension (optional)"
                                        value={data.middle_name}
                                        onChange={(e) => setData("middle_name", e.target.value)}
                                    />
                                    {errors.middle_name ? <p className="text-destructive text-xs">{errors.middle_name}</p> : null}
                                </div>

                                <Separator className="my-1 sm:col-span-2" />

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="department">Department</Label>
                                    <Input
                                        id="department"
                                        list="faculty-department-list"
                                        placeholder="Select or type department"
                                        value={data.department}
                                        onChange={(e) => setData("department", e.target.value)}
                                        className={cn(errors.department && "border-destructive")}
                                    />
                                    <datalist id="faculty-department-list">
                                        {departmentSuggestions.map((dept) => (
                                            <option key={dept} value={dept} />
                                        ))}
                                    </datalist>
                                    {errors.department ? (
                                        <p className="text-destructive text-xs">{errors.department}</p>
                                    ) : (
                                        <p className="text-muted-foreground text-xs">e.g. College of Computer Studies, Engineering</p>
                                    )}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="position">Job Title / Academic Rank</Label>
                                    <Input
                                        id="position"
                                        placeholder="e.g. Professor, Assistant Professor, Instructor"
                                        value={data.position}
                                        onChange={(e) => setData("position", e.target.value)}
                                        className={cn(errors.position && "border-destructive")}
                                    />
                                    {errors.position ? <p className="text-destructive text-xs">{errors.position}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5 sm:col-span-2">
                                    <Label>Employment Status</Label>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        {[
                                            {
                                                val: "active",
                                                label: "Active",
                                                desc: "Available for class assignments & active portal",
                                                badgeVariant: "success-light" as const,
                                            },
                                            {
                                                val: "on_leave",
                                                label: "On Leave",
                                                desc: "Temporarily unavailable for teaching load",
                                                badgeVariant: "warning-light" as const,
                                            },
                                            {
                                                val: "inactive",
                                                label: "Inactive",
                                                desc: "No longer active or archived profile",
                                                badgeVariant: "invert-light" as const,
                                            },
                                        ].map((item) => (
                                            <button
                                                key={item.val}
                                                type="button"
                                                onClick={() => setData("status", item.val)}
                                                className={cn(
                                                    "flex cursor-pointer flex-col rounded-lg border p-3 text-left text-xs transition-colors",
                                                    data.status === item.val
                                                        ? "border-primary ring-primary bg-primary/[0.03] ring-1"
                                                        : "hover:bg-muted/50 border-border",
                                                )}
                                            >
                                                <div className="mb-1 flex w-full items-center justify-between">
                                                    <span className="text-sm font-semibold">{item.label}</span>
                                                    {data.status === item.val ? <Check className="text-primary h-4 w-4 shrink-0" /> : null}
                                                </div>
                                                <span className="text-muted-foreground">{item.desc}</span>
                                            </button>
                                        ))}
                                    </div>
                                    {errors.status ? <p className="text-destructive text-xs">{errors.status}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="date_employed">Date Employed</Label>
                                    <Input
                                        id="date_employed"
                                        type="date"
                                        value={data.date_employed}
                                        onChange={(e) => setData("date_employed", e.target.value)}
                                        className={cn(errors.date_employed && "border-destructive")}
                                    />
                                    {errors.date_employed ? <p className="text-destructive text-xs">{errors.date_employed}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="phone_number">Contact Phone</Label>
                                    <Input
                                        id="phone_number"
                                        placeholder="+63 912 345 6789"
                                        value={data.phone_number}
                                        onChange={(e) => setData("phone_number", e.target.value)}
                                        className={cn(errors.phone_number && "border-destructive")}
                                    />
                                    {errors.phone_number ? <p className="text-destructive text-xs">{errors.phone_number}</p> : null}
                                </div>
                            </CardContent>
                            <CardFooter className="bg-muted/10 flex justify-end border-t p-3">
                                <Button type="button" size="sm" onClick={() => setCurrentStep(2)}>
                                    Next: Academic Profile &rarr;
                                </Button>
                            </CardFooter>
                        </Card>
                    </StepperContent>

                    {/* Step 2: Academic Profile & Teaching */}
                    <StepperContent value={2} className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <IconTile variant="soft" size="xs" className="text-primary">
                                        <GraduationCap />
                                    </IconTile>
                                    Academic Credentials & Teaching Profile
                                </CardTitle>
                                <CardDescription>
                                    Information used by department heads to evaluate curriculum alignment and student consultation availability.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div className="flex flex-col gap-1.5">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="office_hours" className="flex items-center gap-1.5">
                                            <Clock className="text-muted-foreground h-3.5 w-3.5" />
                                            Office Hours & Consultation Policy
                                        </Label>
                                        <div className="flex flex-wrap gap-1 text-[11px]">
                                            <span className="text-muted-foreground mr-1">Quick insert:</span>
                                            <button
                                                type="button"
                                                onClick={() => insertOfficeHoursPreset("MWF 9:00 AM - 11:00 AM")}
                                                className="text-primary cursor-pointer hover:underline"
                                            >
                                                MWF 9-11 AM
                                            </button>
                                            <span>&bull;</span>
                                            <button
                                                type="button"
                                                onClick={() => insertOfficeHoursPreset("TTh 1:00 PM - 3:00 PM")}
                                                className="text-primary cursor-pointer hover:underline"
                                            >
                                                TTh 1-3 PM
                                            </button>
                                            <span>&bull;</span>
                                            <button
                                                type="button"
                                                onClick={() => insertOfficeHoursPreset("By Appointment (Rm 301)")}
                                                className="text-primary cursor-pointer hover:underline"
                                            >
                                                By Appt
                                            </button>
                                        </div>
                                    </div>
                                    <Textarea
                                        id="office_hours"
                                        placeholder="e.g. MWF 9:00 AM - 11:00 AM, Rm 204 Computer Science Dept."
                                        rows={3}
                                        value={data.office_hours}
                                        onChange={(e) => setData("office_hours", e.target.value)}
                                        className={cn(errors.office_hours && "border-destructive")}
                                    />
                                    {errors.office_hours ? <p className="text-destructive text-xs">{errors.office_hours}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="education" className="flex items-center gap-1.5">
                                        <GraduationCap className="text-muted-foreground h-3.5 w-3.5" />
                                        Highest Educational Attainment & Honors
                                    </Label>
                                    <Textarea
                                        id="education"
                                        placeholder="e.g. Ph.D. in Computer Science - University of the Philippines Diliman&#10;M.S. Information Technology - De La Salle University"
                                        rows={3}
                                        value={data.education}
                                        onChange={(e) => setData("education", e.target.value)}
                                        className={cn(errors.education && "border-destructive")}
                                    />
                                    {errors.education ? <p className="text-destructive text-xs">{errors.education}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="courses_taught" className="flex items-center gap-1.5">
                                        <BookOpen className="text-muted-foreground h-3.5 w-3.5" />
                                        Courses Taught & Areas of Specialization
                                    </Label>
                                    <Textarea
                                        id="courses_taught"
                                        placeholder="e.g. Data Structures & Algorithms, Software Engineering, Database Systems, Artificial Intelligence"
                                        rows={3}
                                        value={data.courses_taught}
                                        onChange={(e) => setData("courses_taught", e.target.value)}
                                        className={cn(errors.courses_taught && "border-destructive")}
                                    />
                                    {errors.courses_taught ? <p className="text-destructive text-xs">{errors.courses_taught}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="biography">Faculty Biography & Research Statement</Label>
                                    <Textarea
                                        id="biography"
                                        placeholder="Brief background on academic focus, research publications, and professional experience."
                                        rows={4}
                                        value={data.biography}
                                        onChange={(e) => setData("biography", e.target.value)}
                                        className={cn(errors.biography && "border-destructive")}
                                    />
                                    {errors.biography ? <p className="text-destructive text-xs">{errors.biography}</p> : null}
                                </div>
                            </CardContent>
                            <CardFooter className="bg-muted/10 flex justify-between border-t p-3">
                                <Button type="button" variant="outline" size="sm" onClick={() => setCurrentStep(1)}>
                                    &larr; Back to Identity
                                </Button>
                                <Button type="button" size="sm" onClick={() => setCurrentStep(3)}>
                                    Next: Personal Details &rarr;
                                </Button>
                            </CardFooter>
                        </Card>
                    </StepperContent>

                    {/* Step 3: Personal & Demographic */}
                    <StepperContent value={3} className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <IconTile variant="soft" size="xs" className="text-primary">
                                        <Calendar />
                                    </IconTile>
                                    Personal & Demographic Information
                                </CardTitle>
                                <CardDescription>Confidential demographic records used for HR and institutional reporting.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-3">
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="birth_date">Birth Date</Label>
                                    <Input
                                        id="birth_date"
                                        type="date"
                                        value={data.birth_date}
                                        onChange={(e) => handleBirthDateChange(e.target.value)}
                                        className={cn(errors.birth_date && "border-destructive")}
                                    />
                                    {errors.birth_date ? <p className="text-destructive text-xs">{errors.birth_date}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="age">Age</Label>
                                    <Input
                                        id="age"
                                        type="number"
                                        placeholder="Auto-calculated"
                                        value={data.age}
                                        onChange={(e) => setData("age", e.target.value)}
                                        className={cn("tabular-nums", errors.age && "border-destructive")}
                                    />
                                    {errors.age ? <p className="text-destructive text-xs">{errors.age}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="gender">Gender</Label>
                                    <Select value={data.gender || "none"} onValueChange={(val) => setData("gender", val === "none" ? "" : val)}>
                                        <SelectTrigger id="gender">
                                            <SelectValue placeholder="Select gender" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Not specified</SelectItem>
                                            {options.genders.map((g) => (
                                                <SelectItem key={g.value} value={g.value}>
                                                    {g.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.gender ? <p className="text-destructive text-xs">{errors.gender}</p> : null}
                                </div>

                                <div className="flex flex-col gap-1.5 sm:col-span-3">
                                    <Label htmlFor="address_line1">Mailing / Residential Address</Label>
                                    <Textarea
                                        id="address_line1"
                                        placeholder="Street address, City, Province, Postal Code"
                                        rows={3}
                                        value={data.address_line1}
                                        onChange={(e) => setData("address_line1", e.target.value)}
                                        className={cn(errors.address_line1 && "border-destructive")}
                                    />
                                    {errors.address_line1 ? <p className="text-destructive text-xs">{errors.address_line1}</p> : null}
                                </div>
                            </CardContent>
                            <CardFooter className="bg-muted/10 flex justify-between border-t p-3">
                                <Button type="button" variant="outline" size="sm" onClick={() => setCurrentStep(2)}>
                                    &larr; Back to Academic
                                </Button>
                                <Button type="button" size="sm" onClick={() => setCurrentStep(4)}>
                                    Next: Review & Save &rarr;
                                </Button>
                            </CardFooter>
                        </Card>
                    </StepperContent>

                    {/* Step 4: Review & Verification */}
                    <StepperContent value={4} className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <IconTile variant="soft" size="xs" className="text-success">
                                        <CheckCircle2 />
                                    </IconTile>
                                    Review & Verification Summary
                                </CardTitle>
                                <CardDescription>Verify all required details before finalizing this faculty record.</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="bg-muted/10 flex flex-col gap-2 rounded-lg border p-3 text-xs">
                                        <div className="text-foreground text-sm font-semibold">Identity & Contact</div>
                                        <div className="flex justify-between border-b py-1">
                                            <span className="text-muted-foreground">Full Name:</span>
                                            <span className="text-right font-medium">{displayName}</span>
                                        </div>
                                        <div className="flex justify-between border-b py-1">
                                            <span className="text-muted-foreground">Faculty ID:</span>
                                            <span className="font-mono font-medium">{data.faculty_id_number || "Missing"}</span>
                                        </div>
                                        <div className="flex justify-between border-b py-1">
                                            <span className="text-muted-foreground">Institutional Email:</span>
                                            <span className="font-medium">{data.email || "Missing"}</span>
                                        </div>
                                        <div className="flex justify-between py-1">
                                            <span className="text-muted-foreground">Phone:</span>
                                            <span>{data.phone_number || "None listed"}</span>
                                        </div>
                                    </div>

                                    <div className="bg-muted/10 flex flex-col gap-2 rounded-lg border p-3 text-xs">
                                        <div className="text-foreground text-sm font-semibold">Academic Placement</div>
                                        <div className="flex justify-between border-b py-1">
                                            <span className="text-muted-foreground">Department:</span>
                                            <span className="font-medium">{data.department || "Unassigned"}</span>
                                        </div>
                                        <div className="flex justify-between border-b py-1">
                                            <span className="text-muted-foreground">Position / Title:</span>
                                            <span>{data.position || "Faculty Member"}</span>
                                        </div>
                                        <div className="flex justify-between border-b py-1">
                                            <span className="text-muted-foreground">Status:</span>
                                            <Badge variant={statusBadgeVariant} size="xs" radius="full">
                                                {data.status}
                                            </Badge>
                                        </div>
                                        <div className="flex justify-between py-1">
                                            <span className="text-muted-foreground">Date Employed:</span>
                                            <span>{data.date_employed || "Not specified"}</span>
                                        </div>
                                    </div>
                                </div>

                                {/* ReUI Alert for Portal Readiness */}
                                {data.email && data.faculty_id_number ? (
                                    <Alert variant="success">
                                        <CheckCircle2 />
                                        <AlertTitle>Portal Account Synchronization Ready</AlertTitle>
                                        <AlertDescription>
                                            Faculty user credentials will be automatically linked via institutional email and faculty ID number.
                                        </AlertDescription>
                                    </Alert>
                                ) : (
                                    <Alert variant="warning">
                                        <AlertTriangle />
                                        <AlertTitle>Incomplete Portal Credentials</AlertTitle>
                                        <AlertDescription>
                                            Both an institutional email and faculty ID number are required before a portal account can be
                                            synchronized.
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </CardContent>
                            <CardFooter className="bg-muted/10 flex justify-between border-t p-3">
                                <Button type="button" variant="outline" size="sm" onClick={() => setCurrentStep(3)}>
                                    &larr; Back to Personal
                                </Button>
                                <Button type="submit" size="sm" disabled={processing} className="gap-1.5">
                                    {isEdit ? <Save className="h-4 w-4" /> : <UserPlus className="h-4 w-4" />}
                                    {processing ? "Saving..." : isEdit ? "Save Faculty Profile" : "Create Faculty"}
                                </Button>
                            </CardFooter>
                        </Card>
                    </StepperContent>
                </Stepper>

                {/* Form Bottom Action Bar */}
                <div className="flex items-center justify-between border-t pt-4">
                    <Link href={backHref} className={buttonVariants({ variant: "outline" })}>
                        <ArrowLeft className="mr-1.5 h-4 w-4" /> Cancel
                    </Link>

                    <div className="flex items-center gap-3">
                        <span className="text-muted-foreground hidden text-xs sm:inline-block">
                            Tip: Press <kbd className="bg-muted rounded border px-1.5 py-0.5 font-mono text-[10px]">Ctrl+S</kbd> to save
                        </span>
                        <Button type="submit" disabled={processing} className="min-w-36">
                            {isEdit ? <Save className="mr-1.5 h-4 w-4" /> : <UserPlus className="mr-1.5 h-4 w-4" />}
                            {processing ? "Saving Profile..." : isEdit ? "Save Changes" : "Create Faculty"}
                        </Button>
                    </div>
                </div>
            </form>

            {/* Right Sticky Sidebar */}
            <div className="flex flex-col gap-4">
                {/* Live Card Preview */}
                <Card className="overflow-hidden shadow-xs">
                    <CardHeader className="bg-muted/30 border-b pb-3">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase">
                                <Sparkles className="text-primary h-3.5 w-3.5" /> Live Directory Preview
                            </CardTitle>
                            <Badge variant={statusBadgeVariant} size="xs" radius="full">
                                {data.status}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 pt-4">
                        <div className="flex flex-col items-center text-center">
                            <div className="relative mb-2">
                                <Avatar className="border-background ring-border h-20 w-20 border-2 shadow-xs ring-1">
                                    <AvatarImage src={photoPreview ?? undefined} alt={displayName} />
                                    <AvatarFallback className="text-lg font-bold">{(displayName || "?").slice(0, 2).toUpperCase()}</AvatarFallback>
                                </Avatar>
                            </div>

                            <div className="line-clamp-1 text-sm font-semibold">{displayName}</div>
                            <div className="text-muted-foreground line-clamp-1 text-xs">{data.position || "Faculty Member"}</div>

                            <div className="mt-2 flex flex-wrap items-center justify-center gap-1.5">
                                {data.faculty_id_number ? (
                                    <span className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 font-mono text-[11px]">
                                        #{data.faculty_id_number}
                                    </span>
                                ) : null}
                                {data.department ? (
                                    <Badge variant="secondary" size="xs" radius="full">
                                        {data.department}
                                    </Badge>
                                ) : null}
                            </div>
                        </div>

                        <Separator />

                        <div className="flex flex-col gap-2 text-xs">
                            <div className="text-muted-foreground flex items-center gap-2">
                                <Mail className="h-3.5 w-3.5 shrink-0" />
                                <span className="truncate">{data.email || "No email entered"}</span>
                            </div>
                            {data.phone_number ? (
                                <div className="text-muted-foreground flex items-center gap-2">
                                    <Phone className="h-3.5 w-3.5 shrink-0" />
                                    <span className="truncate">{data.phone_number}</span>
                                </div>
                            ) : null}
                            {data.office_hours ? (
                                <div className="text-muted-foreground flex items-center gap-2">
                                    <Clock className="h-3.5 w-3.5 shrink-0" />
                                    <span className="truncate">{data.office_hours}</span>
                                </div>
                            ) : null}
                        </div>
                    </CardContent>
                </Card>

                {/* Photo Upload Card */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase">
                            <Camera className="text-primary h-3.5 w-3.5" /> Profile Photo
                        </CardTitle>
                        <CardDescription className="text-xs">Upload an institutional faculty portrait (JPG, PNG up to 2MB).</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => handlePhotoSelect(e.target.files?.[0] ?? null)}
                        />

                        {photoPreview ? (
                            <div className="flex items-center justify-between rounded-lg border p-2.5">
                                <div className="flex items-center gap-2.5">
                                    <Avatar className="h-10 w-10">
                                        <AvatarImage src={photoPreview} />
                                        <AvatarFallback>IMG</AvatarFallback>
                                    </Avatar>
                                    <div className="text-xs">
                                        <div className="text-foreground font-medium">Photo Selected</div>
                                        <div className="text-muted-foreground text-[11px]">Ready to upload on save</div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-xs"
                                        onClick={() => fileInputRef.current?.click()}
                                        title="Change photo"
                                    >
                                        <Upload className="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-xs"
                                        className="text-destructive hover:text-destructive"
                                        onClick={removePhoto}
                                        title="Remove photo"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                className="border-border/80 hover:border-primary/50 hover:bg-muted/30 flex w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-4 transition-colors"
                            >
                                <Upload className="text-muted-foreground mb-2 h-5 w-5" />
                                <span className="text-xs font-semibold">Upload Photo</span>
                                <span className="text-muted-foreground mt-0.5 text-[11px]">Drag & drop or click to browse</span>
                            </button>
                        )}

                        {progress ? (
                            <div className="flex flex-col gap-1">
                                <div className="flex justify-between text-[11px]">
                                    <span>Uploading photo...</span>
                                    <span className="tabular-nums">{progress.percentage}%</span>
                                </div>
                                <Progress value={progress.percentage} className="h-1.5" />
                            </div>
                        ) : null}
                        {errors.photo ? <p className="text-destructive text-xs">{errors.photo}</p> : null}
                    </CardContent>
                </Card>

                {/* Profile Completeness Checklist */}
                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Completeness Score</CardTitle>
                            <span className="text-sm font-bold tabular-nums">{completion}%</span>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        <Progress
                            value={completion}
                            className={cn(
                                "h-2",
                                completion >= 80 ? "[&>div]:bg-success" : completion >= 50 ? "[&>div]:bg-warning" : "[&>div]:bg-destructive",
                            )}
                        />

                        <div className="flex flex-col gap-1.5 text-xs">
                            {checklist.map((item) => (
                                <div key={item.label} className="text-muted-foreground flex items-center justify-between">
                                    <span className="flex items-center gap-1.5">
                                        {item.done ? (
                                            <CheckCircle2 className="text-success h-3.5 w-3.5 shrink-0" />
                                        ) : (
                                            <div className="border-muted-foreground/40 h-3.5 w-3.5 shrink-0 rounded-full border" />
                                        )}
                                        <span className={cn(item.done && "text-foreground font-medium")}>{item.label}</span>
                                    </span>
                                    {item.required ? <span className="text-destructive text-[10px] tracking-wider uppercase">Req</span> : null}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
