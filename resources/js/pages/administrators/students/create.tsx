import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    Banknote,
    BookOpen,
    Briefcase,
    Calendar,
    Globe,
    GraduationCap,
    Hash,
    Loader2,
    Mail,
    MapPin,
    MapPinned,
    Phone,
    RefreshCw,
    Save,
    Sparkles,
    User as UserIcon,
    Users,
} from "lucide-react";
import { useCallback, useEffect, useState } from "react";

declare const route: (name: string, params?: Record<string, unknown>) => string;

interface Option {
    value: string | number;
    label: string;
    is_active?: boolean;
}

interface CreateStudentProps {
    user: User;
    options: {
        types: Option[];
        statuses: Option[];
        scholarship_types: Option[];
        employment_statuses: Option[];
        attrition_categories: Option[];
        courses: Option[];
        shs_strands: Option[];
        regions: Option[];
    };
}

const CIVIL_STATUS_OPTIONS = [
    { value: "single", label: "Single" },
    { value: "married", label: "Married" },
    { value: "widowed", label: "Widowed" },
    { value: "separated", label: "Separated" },
    { value: "annulled", label: "Annulled" },
];

const NATIONALITY_OPTIONS = [
    { value: "filipino", label: "Filipino" },
    { value: "american", label: "American" },
    { value: "chinese", label: "Chinese" },
    { value: "indian", label: "Indian" },
    { value: "korean", label: "Korean" },
    { value: "japanese", label: "Japanese" },
    { value: "other", label: "Other" },
];

export default function AdministratorStudentCreate({ user, options }: CreateStudentProps) {
    const [previewId, setPreviewId] = useState<number | null>(null);
    const [isGeneratingId, setIsGeneratingId] = useState(false);
    const [idGenerationError, setIdGenerationError] = useState<string | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        // Basic Info
        student_type: "college",
        student_id: "",
        lrn: "",
        status: "enrolled",

        // Personal Details
        first_name: "",
        last_name: "",
        middle_name: "",
        suffix: "",
        gender: "male",
        birth_date: "",
        age: "",
        email: "",
        phone: "",

        // Additional Personal Info
        civil_status: "",
        nationality: "filipino",
        religion: "",

        // Academic Info
        course_id: "",
        shs_strand_id: "",
        academic_year: "1",
        remarks: "",

        // Contact Information
        personal_contact: "",
        emergency_contact_name: "",
        emergency_contact_phone: "",
        emergency_contact_address: "",

        // Parent Information
        fathers_name: "",
        mothers_name: "",

        // Address Information
        current_address: "",
        permanent_address: "",
        birthplace: "",

        // Background & Origin
        ethnicity: "",
        region_of_origin: "",
        province_of_origin: "",
        city_of_origin: "",
        is_indigenous_person: false,
        indigenous_group: "",

        // Scholarship Information
        scholarship_type: "none",
        scholarship_details: "",

        // Employment Information (for graduates)
        employment_status: "not_applicable",
        employer_name: "",
        job_position: "",
        employment_date: "",
        employed_by_institution: false,

        // Withdrawal Information
        withdrawal_date: "",
        withdrawal_reason: "",
        attrition_category: "",
        dropout_date: "",
    });

    const isSHS = data.student_type === "shs";
    const isGraduated = data.status === "graduated";
    const isWithdrawn = data.status === "withdrawn" || data.status === "dropped";
    const showEmployment =
        isGraduated &&
        data.employment_status !== "not_applicable" &&
        data.employment_status !== "unemployed" &&
        data.employment_status !== "further_study";

    // Fetch generated ID when student type changes (for non-SHS only)
    const fetchGeneratedId = useCallback(async () => {
        if (isSHS) {
            setPreviewId(null);
            setIdGenerationError(null);
            return;
        }

        setIsGeneratingId(true);
        setIdGenerationError(null);

        try {
            const url = route("administrators.students.generate-id", { type: data.student_type });
            const response = await fetch(url);
            if (response.ok) {
                const result = await response.json();
                if (result.id) {
                    setPreviewId(result.id);
                } else {
                    setIdGenerationError("No ID returned");
                }
            } else {
                setIdGenerationError("Failed to generate ID");
            }
        } catch (err) {
            console.error("Error generating ID:", err);
            setIdGenerationError("Network error");
        } finally {
            setIsGeneratingId(false);
        }
    }, [data.student_type, isSHS]);

    // Auto-fetch generated ID on mount and when student type changes (non-SHS only)
    useEffect(() => {
        if (!isSHS) {
            fetchGeneratedId();
        }
    }, [data.student_type, isSHS]);

    // Clear type-specific fields when switching student type
    useEffect(() => {
        if (isSHS) {
            if (data.course_id) setData("course_id", "");
            if (data.student_id) setData("student_id", "");
            if (data.academic_year !== "11" && data.academic_year !== "12") {
                setData("academic_year", "11");
            }
        } else {
            if (data.lrn) setData("lrn", "");
            if (data.shs_strand_id) setData("shs_strand_id", "");
            if (data.academic_year === "11" || data.academic_year === "12") {
                setData("academic_year", "1");
            }
        }
    }, [data.student_type]);

    // Auto-calculate age from birth_date
    useEffect(() => {
        if (data.birth_date) {
            const birthDate = new Date(data.birth_date);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            setData("age", age.toString());
        }
    }, [data.birth_date]);

    // Use generated ID
    const handleUseGeneratedId = () => {
        if (previewId && !isSHS) {
            setData("student_id", previewId.toString());
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!isSHS && !data.student_id && previewId) {
            setData("student_id", previewId.toString());
        }

        post(route("administrators.students.store"));
    };

    const getYearLevelOptions = () => {
        if (isSHS) {
            return [
                { value: "11", label: "Grade 11" },
                { value: "12", label: "Grade 12" },
            ];
        }
        return [
            { value: "1", label: "1st Year" },
            { value: "2", label: "2nd Year" },
            { value: "3", label: "3rd Year" },
            { value: "4", label: "4th Year" },
            { value: "5", label: "Graduate" },
        ];
    };

    return (
        <AdminLayout user={user} title="Create Student">
            <Head title="Administrators - Create Student" />

            <form onSubmit={submit} className="mx-auto max-w-6xl space-y-6 pb-10">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary/10 flex h-12 w-12 items-center justify-center rounded-xl">
                            <GraduationCap className="text-primary h-6 w-6" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">Create New Student</h1>
                            <p className="text-muted-foreground text-sm">Add a new student to the system</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={route("administrators.students.index")}>
                                <ArrowLeft className="mr-2 h-4 w-4" /> Back
                            </Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                            {processing ? "Creating..." : "Create Student"}
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Left Column - Main Info */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Student Type & Identity */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <UserIcon className="text-primary h-5 w-5" />
                                    Student Type & Identity
                                </CardTitle>
                                <CardDescription>Select the student type and enter identification details</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                {/* Student Type - Radio-like buttons */}
                                <div className="space-y-2">
                                    <Label className="text-sm font-medium">Student Type *</Label>
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        {options.types.map((type) => (
                                            <button
                                                key={type.value}
                                                type="button"
                                                onClick={() => setData("student_type", type.value.toString())}
                                                className={cn(
                                                    "rounded-lg border px-3 py-2.5 text-sm font-medium transition-all",
                                                    data.student_type === type.value
                                                        ? "border-primary bg-primary text-primary-foreground shadow-sm"
                                                        : "border-border bg-background hover:bg-muted hover:border-muted-foreground/30",
                                                )}
                                            >
                                                {type.label}
                                            </button>
                                        ))}
                                    </div>
                                    {errors.student_type && <p className="text-destructive text-sm">{errors.student_type}</p>}
                                </div>

                                {/* Student ID / LRN based on type */}
                                {!isSHS ? (
                                    <div className="space-y-3">
                                        {/* Generated ID Preview */}
                                        <div className="bg-muted/50 flex items-center gap-3 rounded-lg border p-3">
                                            <div className="flex-1">
                                                <div className="mb-1 flex items-center gap-2">
                                                    <span className="text-muted-foreground text-xs font-medium">Next Available ID</span>
                                                    <Badge variant="secondary" className="h-5 text-xs">
                                                        Auto
                                                    </Badge>
                                                </div>
                                                {isGeneratingId ? (
                                                    <div className="text-muted-foreground flex items-center gap-2">
                                                        <Loader2 className="h-4 w-4 animate-spin" />
                                                        <span className="text-sm">Generating...</span>
                                                    </div>
                                                ) : previewId ? (
                                                    <span className="text-primary font-mono text-xl font-bold">{previewId}</span>
                                                ) : idGenerationError ? (
                                                    <span className="text-destructive text-sm">{idGenerationError}</span>
                                                ) : (
                                                    <span className="text-muted-foreground text-sm">—</span>
                                                )}
                                            </div>
                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={fetchGeneratedId}
                                                    disabled={isGeneratingId}
                                                >
                                                    <RefreshCw className={cn("h-4 w-4", isGeneratingId && "animate-spin")} />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="default"
                                                    size="sm"
                                                    onClick={handleUseGeneratedId}
                                                    disabled={!previewId || isGeneratingId}
                                                    className="gap-1"
                                                >
                                                    <Sparkles className="h-3.5 w-3.5" />
                                                    Use
                                                </Button>
                                            </div>
                                        </div>

                                        {/* Manual ID Entry */}
                                        <div className="space-y-1.5">
                                            <Label htmlFor="student_id" className="flex items-center gap-1.5">
                                                <Hash className="h-3.5 w-3.5" />
                                                Student ID
                                                <span className="text-muted-foreground text-xs font-normal">(or use auto-generated)</span>
                                            </Label>
                                            <Input
                                                id="student_id"
                                                value={data.student_id}
                                                onChange={(e) => setData("student_id", e.target.value)}
                                                placeholder="Enter 6-digit ID"
                                                className="font-mono"
                                            />
                                            {errors.student_id && <p className="text-destructive text-sm">{errors.student_id}</p>}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="lrn" className="flex items-center gap-1.5">
                                            <Hash className="h-3.5 w-3.5" />
                                            Learner Reference Number (LRN) *
                                        </Label>
                                        <Input
                                            id="lrn"
                                            value={data.lrn}
                                            onChange={(e) => setData("lrn", e.target.value)}
                                            placeholder="Enter LRN"
                                            className="font-mono"
                                        />
                                        <p className="text-muted-foreground text-xs">Required for Senior High School students</p>
                                        {errors.lrn && <p className="text-destructive text-sm">{errors.lrn}</p>}
                                    </div>
                                )}

                                {/* Status */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="status">Student Status</Label>
                                    <Select value={data.status} onValueChange={(val) => setData("status", val)}>
                                        <SelectTrigger id="status">
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.statuses.map((status) => (
                                                <SelectItem key={status.value} value={status.value.toString()}>
                                                    {status.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.status && <p className="text-destructive text-sm">{errors.status}</p>}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Personal Details */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <UserIcon className="text-primary h-5 w-5" />
                                    Personal Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Name Row */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="first_name">First Name *</Label>
                                        <Input
                                            id="first_name"
                                            value={data.first_name}
                                            onChange={(e) => setData("first_name", e.target.value)}
                                            placeholder="Juan"
                                        />
                                        {errors.first_name && <p className="text-destructive text-sm">{errors.first_name}</p>}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="middle_name">Middle Name</Label>
                                        <Input
                                            id="middle_name"
                                            value={data.middle_name}
                                            onChange={(e) => setData("middle_name", e.target.value)}
                                            placeholder="Santos"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="last_name">Last Name *</Label>
                                        <Input
                                            id="last_name"
                                            value={data.last_name}
                                            onChange={(e) => setData("last_name", e.target.value)}
                                            placeholder="Dela Cruz"
                                        />
                                        {errors.last_name && <p className="text-destructive text-sm">{errors.last_name}</p>}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="suffix">Suffix</Label>
                                        <Input
                                            id="suffix"
                                            value={data.suffix}
                                            onChange={(e) => setData("suffix", e.target.value)}
                                            placeholder="Jr., Sr., III"
                                        />
                                    </div>
                                </div>

                                {/* Gender, Birth Date, Age, Email */}
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="gender">Gender *</Label>
                                        <Select value={data.gender} onValueChange={(val) => setData("gender", val)}>
                                            <SelectTrigger id="gender">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="male">Male</SelectItem>
                                                <SelectItem value="female">Female</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.gender && <p className="text-destructive text-sm">{errors.gender}</p>}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="birth_date" className="flex items-center gap-1.5">
                                            <Calendar className="h-3.5 w-3.5" />
                                            Birth Date *
                                        </Label>
                                        <Input
                                            id="birth_date"
                                            type="date"
                                            value={data.birth_date}
                                            onChange={(e) => setData("birth_date", e.target.value)}
                                            max={new Date().toISOString().split("T")[0]}
                                        />
                                        {errors.birth_date && <p className="text-destructive text-sm">{errors.birth_date}</p>}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="age">Age</Label>
                                        <Input id="age" value={data.age} readOnly className="bg-muted" placeholder="Auto" />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="email" className="flex items-center gap-1.5">
                                            <Mail className="h-3.5 w-3.5" />
                                            Email
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData("email", e.target.value)}
                                            placeholder="email@example.com"
                                        />
                                        {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                                    </div>
                                </div>

                                {/* Additional Personal Info */}
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="phone" className="flex items-center gap-1.5">
                                            <Phone className="h-3.5 w-3.5" />
                                            Phone
                                        </Label>
                                        <Input
                                            id="phone"
                                            value={data.phone}
                                            onChange={(e) => setData("phone", e.target.value)}
                                            placeholder="09XX XXX XXXX"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="civil_status">Civil Status</Label>
                                        <Select value={data.civil_status} onValueChange={(val) => setData("civil_status", val)}>
                                            <SelectTrigger id="civil_status">
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {CIVIL_STATUS_OPTIONS.map((status) => (
                                                    <SelectItem key={status.value} value={status.value}>
                                                        {status.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="nationality" className="flex items-center gap-1.5">
                                            <Globe className="h-3.5 w-3.5" />
                                            Nationality
                                        </Label>
                                        <Select value={data.nationality} onValueChange={(val) => setData("nationality", val)}>
                                            <SelectTrigger id="nationality">
                                                <SelectValue placeholder="Select nationality" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {NATIONALITY_OPTIONS.map((nat) => (
                                                    <SelectItem key={nat.value} value={nat.value}>
                                                        {nat.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="religion">Religion</Label>
                                        <Input
                                            id="religion"
                                            value={data.religion}
                                            onChange={(e) => setData("religion", e.target.value)}
                                            placeholder="e.g., Roman Catholic"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="birthplace" className="flex items-center gap-1.5">
                                            <MapPinned className="h-3.5 w-3.5" />
                                            Birthplace
                                        </Label>
                                        <Input
                                            id="birthplace"
                                            value={data.birthplace}
                                            onChange={(e) => setData("birthplace", e.target.value)}
                                            placeholder="City, Province"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Academic Information */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <GraduationCap className="text-primary h-5 w-5" />
                                    Academic Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {/* Course or SHS Strand */}
                                    {!isSHS ? (
                                        <div className="space-y-1.5">
                                            <Label htmlFor="course_id" className="flex items-center gap-1.5">
                                                <BookOpen className="h-3.5 w-3.5" />
                                                Course *
                                            </Label>
                                            <Select value={data.course_id} onValueChange={(val) => setData("course_id", val)}>
                                                <SelectTrigger id="course_id">
                                                    <SelectValue placeholder="Select course" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.courses.map((course) => (
                                                        <SelectItem
                                                            key={course.value}
                                                            value={course.value.toString()}
                                                            disabled={course.is_active === false}
                                                        >
                                                            {course.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.course_id && <p className="text-destructive text-sm">{errors.course_id}</p>}
                                        </div>
                                    ) : (
                                        <div className="space-y-1.5">
                                            <Label htmlFor="shs_strand_id" className="flex items-center gap-1.5">
                                                <BookOpen className="h-3.5 w-3.5" />
                                                SHS Strand *
                                            </Label>
                                            <Select value={data.shs_strand_id} onValueChange={(val) => setData("shs_strand_id", val)}>
                                                <SelectTrigger id="shs_strand_id">
                                                    <SelectValue placeholder="Select strand" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.shs_strands.map((strand) => (
                                                        <SelectItem key={strand.value} value={strand.value.toString()}>
                                                            {strand.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.shs_strand_id && <p className="text-destructive text-sm">{errors.shs_strand_id}</p>}
                                        </div>
                                    )}

                                    {/* Year Level */}
                                    <div className="space-y-1.5">
                                        <Label htmlFor="academic_year">{isSHS ? "Grade Level" : "Year Level"} *</Label>
                                        <Select value={data.academic_year} onValueChange={(val) => setData("academic_year", val)}>
                                            <SelectTrigger id="academic_year">
                                                <SelectValue placeholder="Select year" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {getYearLevelOptions().map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.academic_year && <p className="text-destructive text-sm">{errors.academic_year}</p>}
                                    </div>
                                </div>

                                {/* Remarks */}
                                <div className="space-y-1.5">
                                    <Label htmlFor="remarks">Remarks</Label>
                                    <Textarea
                                        id="remarks"
                                        value={data.remarks}
                                        onChange={(e) => setData("remarks", e.target.value)}
                                        placeholder="Any additional notes..."
                                        rows={2}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Background & Origin */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Globe className="text-primary h-5 w-5" />
                                    Background & Origin
                                </CardTitle>
                                <CardDescription>Statistical information for government reporting</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="ethnicity">Ethnicity</Label>
                                        <Input
                                            id="ethnicity"
                                            value={data.ethnicity}
                                            onChange={(e) => setData("ethnicity", e.target.value)}
                                            placeholder="e.g., Tagalog, Visayan, Ilocano"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="region_of_origin">Region of Origin</Label>
                                        <Select value={data.region_of_origin} onValueChange={(val) => setData("region_of_origin", val)}>
                                            <SelectTrigger id="region_of_origin">
                                                <SelectValue placeholder="Select region" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.regions.map((region) => (
                                                    <SelectItem key={region.value} value={region.value.toString()}>
                                                        {region.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="province_of_origin">Province of Origin</Label>
                                        <Input
                                            id="province_of_origin"
                                            value={data.province_of_origin}
                                            onChange={(e) => setData("province_of_origin", e.target.value)}
                                            placeholder="Province"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="city_of_origin">City/Municipality of Origin</Label>
                                        <Input
                                            id="city_of_origin"
                                            value={data.city_of_origin}
                                            onChange={(e) => setData("city_of_origin", e.target.value)}
                                            placeholder="City/Municipality"
                                        />
                                    </div>
                                </div>

                                {/* Indigenous Person */}
                                <div className="flex items-center gap-4 rounded-lg border p-4">
                                    <Checkbox
                                        id="is_indigenous_person"
                                        checked={data.is_indigenous_person}
                                        onCheckedChange={(checked) => setData("is_indigenous_person", checked === true)}
                                    />
                                    <div className="flex-1">
                                        <Label htmlFor="is_indigenous_person" className="cursor-pointer font-medium">
                                            Indigenous Person
                                        </Label>
                                        <p className="text-muted-foreground text-xs">Check if the student belongs to an indigenous group</p>
                                    </div>
                                </div>

                                {data.is_indigenous_person && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="indigenous_group">Indigenous Group</Label>
                                        <Input
                                            id="indigenous_group"
                                            value={data.indigenous_group}
                                            onChange={(e) => setData("indigenous_group", e.target.value)}
                                            placeholder="e.g., Igorot, Mangyan, Lumad"
                                        />
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Scholarship Information */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Banknote className="text-primary h-5 w-5" />
                                    Scholarship Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="scholarship_type">Scholarship Type</Label>
                                    <Select value={data.scholarship_type} onValueChange={(val) => setData("scholarship_type", val)}>
                                        <SelectTrigger id="scholarship_type">
                                            <SelectValue placeholder="Select scholarship type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.scholarship_types.map((type) => (
                                                <SelectItem key={type.value} value={type.value.toString()}>
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {data.scholarship_type !== "none" && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="scholarship_details">Scholarship Details</Label>
                                        <Textarea
                                            id="scholarship_details"
                                            value={data.scholarship_details}
                                            onChange={(e) => setData("scholarship_details", e.target.value)}
                                            placeholder="Additional details about the scholarship..."
                                            rows={2}
                                        />
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Employment Information */}
                        {isGraduated && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <Briefcase className="text-primary h-5 w-5" />
                                        Employment Information
                                    </CardTitle>
                                    <CardDescription>For graduate tracer studies</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="employment_status">Employment Status</Label>
                                        <Select value={data.employment_status} onValueChange={(val) => setData("employment_status", val)}>
                                            <SelectTrigger id="employment_status">
                                                <SelectValue placeholder="Select employment status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.employment_statuses.map((status) => (
                                                    <SelectItem key={status.value} value={status.value.toString()}>
                                                        {status.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {showEmployment && (
                                        <>
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="employer_name">Employer Name</Label>
                                                    <Input
                                                        id="employer_name"
                                                        value={data.employer_name}
                                                        onChange={(e) => setData("employer_name", e.target.value)}
                                                        placeholder="Company/Organization name"
                                                    />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="job_position">Job Position</Label>
                                                    <Input
                                                        id="job_position"
                                                        value={data.job_position}
                                                        onChange={(e) => setData("job_position", e.target.value)}
                                                        placeholder="Job title"
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="employment_date">Employment Date</Label>
                                                    <Input
                                                        id="employment_date"
                                                        type="date"
                                                        value={data.employment_date}
                                                        onChange={(e) => setData("employment_date", e.target.value)}
                                                    />
                                                </div>
                                                <div className="flex items-center gap-4 pt-6">
                                                    <Checkbox
                                                        id="employed_by_institution"
                                                        checked={data.employed_by_institution}
                                                        onCheckedChange={(checked) => setData("employed_by_institution", checked === true)}
                                                    />
                                                    <Label htmlFor="employed_by_institution" className="cursor-pointer">
                                                        Employed by this institution
                                                    </Label>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Withdrawal Information */}
                        {isWithdrawn && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <Users className="text-primary h-5 w-5" />
                                        Withdrawal Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="attrition_category">Attrition Category</Label>
                                            <Select value={data.attrition_category} onValueChange={(val) => setData("attrition_category", val)}>
                                                <SelectTrigger id="attrition_category">
                                                    <SelectValue placeholder="Select category" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.attrition_categories.map((cat) => (
                                                        <SelectItem key={cat.value} value={cat.value.toString()}>
                                                            {cat.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="withdrawal_date">Withdrawal Date</Label>
                                            <Input
                                                id="withdrawal_date"
                                                type="date"
                                                value={data.withdrawal_date}
                                                onChange={(e) => setData("withdrawal_date", e.target.value)}
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="withdrawal_reason">Withdrawal Reason</Label>
                                        <Textarea
                                            id="withdrawal_reason"
                                            value={data.withdrawal_reason}
                                            onChange={(e) => setData("withdrawal_reason", e.target.value)}
                                            placeholder="Reason for withdrawal..."
                                            rows={3}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Right Column - Contact & Additional */}
                    <div className="space-y-6">
                        {/* Contact Information */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Phone className="text-primary h-5 w-5" />
                                    Contact Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="personal_contact">Personal Contact</Label>
                                    <Input
                                        id="personal_contact"
                                        value={data.personal_contact}
                                        onChange={(e) => setData("personal_contact", e.target.value)}
                                        placeholder="09XX XXX XXXX"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="emergency_contact_name">Guardian Name</Label>
                                    <Input
                                        id="emergency_contact_name"
                                        value={data.emergency_contact_name}
                                        onChange={(e) => setData("emergency_contact_name", e.target.value)}
                                        placeholder="Full name"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="emergency_contact_phone">Guardian Phone</Label>
                                    <Input
                                        id="emergency_contact_phone"
                                        value={data.emergency_contact_phone}
                                        onChange={(e) => setData("emergency_contact_phone", e.target.value)}
                                        placeholder="09XX XXX XXXX"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="emergency_contact_address">Guardian Address</Label>
                                    <Textarea
                                        id="emergency_contact_address"
                                        value={data.emergency_contact_address}
                                        onChange={(e) => setData("emergency_contact_address", e.target.value)}
                                        placeholder="Full address"
                                        rows={2}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Parent Information */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Users className="text-primary h-5 w-5" />
                                    Parent Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="fathers_name">Father's Name</Label>
                                    <Input
                                        id="fathers_name"
                                        value={data.fathers_name}
                                        onChange={(e) => setData("fathers_name", e.target.value)}
                                        placeholder="Full name"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="mothers_name">Mother's Name</Label>
                                    <Input
                                        id="mothers_name"
                                        value={data.mothers_name}
                                        onChange={(e) => setData("mothers_name", e.target.value)}
                                        placeholder="Full name"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Address Information */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <MapPin className="text-primary h-5 w-5" />
                                    Address Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="current_address">Current Address</Label>
                                    <Textarea
                                        id="current_address"
                                        value={data.current_address}
                                        onChange={(e) => setData("current_address", e.target.value)}
                                        placeholder="Street, Barangay, City"
                                        rows={2}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="permanent_address">Permanent Address</Label>
                                    <Textarea
                                        id="permanent_address"
                                        value={data.permanent_address}
                                        onChange={(e) => setData("permanent_address", e.target.value)}
                                        placeholder="Street, Barangay, City"
                                        rows={2}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Submit Button (Mobile) */}
                        <div className="lg:hidden">
                            <Button type="submit" disabled={processing} className="w-full">
                                {processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                                {processing ? "Creating..." : "Create Student"}
                            </Button>
                        </div>
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}
