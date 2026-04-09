import AdminLayout from "@/components/administrators/admin-layout";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    Banknote,
    CalendarDays,
    CheckCircle2,
    Clock,
    Download,
    FileText,
    GraduationCap,
    MoreVertical,
    Pencil,
    Printer,
    RefreshCcw,
    School,
    ShieldAlert,
    Undo2,
    UserCheck,
} from "lucide-react";
import React, { useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

// Define types based on Controller output
interface Branding {
    currency: string;
}

interface EnrollmentData {
    id: number;
    student_id: string | number;
    status: string;
    school_year: string;
    semester: number;
    academic_year: number;
    signature: any;
    student: {
        id: number;
        full_name: string;
        email: string | null;
        student_id: string | number;
        course_code: string | null;
    };
    subjects_enrolled: Array<{
        id: number;
        subject_code: string;
        subject_title: string;
        units: number;
        lecture_fee: number;
        lab_fee: number;
    }>;
    class_enrollments: Array<{
        id: number;
        class_id: number;
        subject_code: string;
        subject_title: string;
        section: string;
        faculty: string;
        schedule: string;
        room: string;
        grades: {
            prelim: number | null;
            midterm: number | null;
            finals: number | null;
            average: number | null;
        };
        status: boolean;
    }>;
    missing_classes: Array<{
        class_id: number | null;
        subject_code: string;
        subject_title: string;
        section: string;
        faculty: string;
        available_slots: number | null;
        max_slots: number;
        is_full: boolean;
        enrollment_status?: string;
    }>;
    tuition: {
        total_tuition: number;
        total_lectures: number;
        total_laboratory: number;
        total_miscelaneous_fees: number;
        additional_fees_total: number;
        discount: number;
        downpayment: number;
        overall_tuition: number;
        total_balance: number;
        total_paid?: number;
    } | null;
    additional_fees: Array<{
        id: number;
        fee_name: string;
        amount: number;
    }>;
    transactions: Array<{
        id: number;
        transaction_number: string;
        invoicenumber: string;
        description: string;
        status: string;
        total_amount: number | null;
        amount: number;
        transaction_date: string;
        created_at: string;
    }>;
    resources: Array<{
        id: number;
        type: string;
        file_name: string;
        file_size: number;
        created_at: string;
        download_url: string;
    }>;
}

interface RecentDeletion {
    id: number;
    log_name: string;
    description: string;
    subject_code: string;
    subject_title: string;
    subject_id: number | null;
    class_id: number | null;
    grade: number | null;
    created_at: string;
    created_at_human: string;
    causer: string;
}

interface PageProps {
    user: User;
    enrollment: EnrollmentData;
    auth: {
        user: User;
        can_verify_head: boolean;
        can_verify_cashier: boolean;
        is_super_admin: boolean;
        can_advance_pipeline: boolean;
    };
    recent_deletions?: RecentDeletion[];
    enrollment_pipeline: {
        submitted_label: string;
        pending_status: string;
        pending_label: string;
        pending_color: string;
        department_verified_status: string;
        department_verified_label: string;
        department_verified_color: string;
        cashier_verified_status: string;
        cashier_verified_label: string;
        cashier_verified_color: string;
        steps: Array<{
            status: string;
            label: string;
            color: string;
            allowed_roles: string[];
            is_core: boolean;
            key: string;
            action_type?: "standard" | "department_verification" | "cashier_verification";
            is_completion?: boolean;
        }>;
        status_options: Array<{ value: string; label: string }>;
        status_classes: Record<string, string>;
        next_step?: {
            status: string;
            label: string;
            color: string;
            allowed_roles: string[];
            is_core: boolean;
            key: string;
        } | null;
    };
}

export default function ShowEnrollment({ user, enrollment, auth, recent_deletions = [], enrollment_pipeline }: PageProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const formatMoney = (amount: number | null | undefined) => {
        if (amount === null || amount === undefined) return currency === "USD" ? "$0.00" : "₱0.00";
        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency: currency,
        }).format(amount);
    };

    const [activeTab, setActiveTab] = useState("classes");
    const [showUndoDialog, setShowUndoDialog] = useState(false);
    const [selectedDeletions, setSelectedDeletions] = useState<number[]>([]);
    const [restoringSubjects, setRestoringSubjects] = useState(false);

    const pendingStatus = enrollment_pipeline.pending_status;
    const departmentStep = enrollment_pipeline.steps.find((step) => step.action_type === "department_verification") ?? null;
    const cashierStep = enrollment_pipeline.steps.find((step) => step.action_type === "cashier_verification") ?? null;
    const completionStep = enrollment_pipeline.steps.find((step) => step.is_completion) ?? enrollment_pipeline.steps[enrollment_pipeline.steps.length - 1];
    const departmentVerifiedStatus = departmentStep?.status ?? enrollment_pipeline.department_verified_status;
    const cashierVerifiedStatus = cashierStep?.status ?? enrollment_pipeline.cashier_verified_status;
    const completionStatus = completionStep?.status ?? enrollment_pipeline.cashier_verified_status;
    const statusClasses = enrollment_pipeline.status_classes ?? {};
    const nextStep = enrollment_pipeline.next_step ?? null;

    const getStatusColor = (status: string) => {
        return statusClasses[status] ?? "bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400 border-gray-200";
    };

    // --- Actions ---

    const handleVerifyHeadDept = () => {
        router.post(
            route("administrators.enrollments.verify-head-dept", enrollment.id),
            {},
            {
                onSuccess: () => toast.success("Verified as Department Head"),
                onError: () => toast.error("Verification failed"),
            },
        );
    };

    const handleUndoHeadDept = () => {
        router.post(
            route("administrators.enrollments.undo-head-dept", enrollment.id),
            {},
            {
                onSuccess: () => toast.success("Department Head verification undone"),
                onError: () => toast.error("Action failed"),
            },
        );
    };

    const handleUndoCashier = () => {
        if (!confirm("Are you sure? This will revert status but NOT reverse financial transactions automatically.")) return;
        router.post(
            route("administrators.enrollments.undo-cashier", enrollment.id),
            {},
            {
                onSuccess: () => toast.success("Cashier verification undone"),
                onError: () => toast.error("Action failed"),
            },
        );
    };

    const handleResendAssessment = () => {
        router.post(
            route("administrators.enrollments.resend-assessment", enrollment.id),
            {},
            {
                onSuccess: () => toast.success("Assessment queued for resending"),
                onError: () => toast.error("Action failed"),
            },
        );
    };

    const handleRetryEnrollment = () => {
        router.post(
            route("administrators.enrollments.retry-enrollment", enrollment.id),
            { force_enrollment: true },
            {
                onSuccess: () => toast.success("Class enrollment retried"),
                onError: () => toast.error("Action failed"),
            },
        );
    };

    const handleAdvancePipelineStep = () => {
        router.post(
            route("administrators.enrollments.advance-pipeline-step", enrollment.id),
            {},
            {
                onSuccess: () => toast.success("Advanced to the next pipeline step"),
                onError: () => toast.error("Failed to advance pipeline step"),
            },
        );
    };

    const handleRestoreSubjects = () => {
        if (selectedDeletions.length === 0) return;
        setRestoringSubjects(true);
        router.post(
            route("administrators.enrollments.restore-subjects", enrollment.id),
            { activity_ids: selectedDeletions },
            {
                onSuccess: () => {
                    toast.success("Subjects restored successfully");
                    setShowUndoDialog(false);
                    setSelectedDeletions([]);
                },
                onError: () => toast.error("Failed to restore subjects"),
                onFinish: () => setRestoringSubjects(false),
            },
        );
    };

    const toggleDeletionSelection = (id: number) => {
        setSelectedDeletions((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
    };

    const selectAllDeletions = () => {
        if (selectedDeletions.length === recent_deletions.length) {
            setSelectedDeletions([]);
        } else {
            setSelectedDeletions(recent_deletions.map((d) => d.id));
        }
    };

    // Enrollment Steps Calculation
    const pipelineSteps = enrollment_pipeline.steps ?? [];
    const currentStepIndex = pipelineSteps.findIndex((step) => step.status === enrollment.status);
    const steps = [
        { label: enrollment_pipeline.submitted_label, status: "completed" },
        ...pipelineSteps.map((step, index) => ({
            label: step.label,
            status: currentStepIndex === -1 ? "pending" : index < currentStepIndex ? "completed" : index === currentStepIndex ? "current" : "pending",
        })),
    ];

    return (
        <AdminLayout user={user} title="Enrollment Management">
            <Head title={`Enrollment • ${enrollment.student.full_name}`} />

            <div className="space-y-6 pb-20">
                {/* Minimal Header */}
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild className="h-8 w-8">
                            <Link href={route("administrators.enrollments.index")}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">Enrollment Details</h1>
                            <p className="text-muted-foreground text-sm">Manage student enrollment and verification</p>
                        </div>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={route("administrators.enrollments.edit", enrollment.id)}>
                            <Pencil className="mr-2 h-4 w-4" />
                            Edit Enrollment
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-12">
                    {/* Left Sidebar - Context & Status */}
                    <div className="space-y-6 lg:col-span-4">
                        {/* Student Profile Card */}
                        <Card className="overflow-hidden">
                            <div className="from-primary/10 to-primary/5 h-24 bg-gradient-to-r"></div>
                            <CardContent className="relative pt-0">
                                <div className="absolute -top-12 left-6">
                                    <Avatar className="border-background h-24 w-24 border-4 shadow-sm">
                                        <AvatarFallback className="bg-primary/10 text-primary text-2xl">
                                            {enrollment.student.full_name.charAt(0)}
                                        </AvatarFallback>
                                    </Avatar>
                                </div>
                                <div className="mt-14 mb-4">
                                    <h2 className="text-2xl leading-tight font-bold">{enrollment.student.full_name}</h2>
                                    <div className="text-muted-foreground mt-1 flex items-center gap-2">
                                        <Badge variant="secondary" className="font-normal">
                                            {enrollment.student.student_id}
                                        </Badge>
                                        <span className="text-sm">{enrollment.student.email}</span>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4 border-t border-b py-4">
                                    <div>
                                        <p className="text-muted-foreground text-xs font-medium uppercase">Course</p>
                                        <p className="mt-0.5 flex items-center gap-1.5 font-semibold">
                                            <GraduationCap className="text-primary h-3.5 w-3.5" />
                                            {enrollment.student.course_code}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs font-medium uppercase">Year Level</p>
                                        <p className="mt-0.5 flex items-center gap-1.5 font-semibold">
                                            <span className="bg-primary/20 text-primary flex h-3.5 w-3.5 items-center justify-center rounded-full text-[9px] font-bold">
                                                {enrollment.academic_year}
                                            </span>
                                            Year {enrollment.academic_year}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs font-medium uppercase">Semester</p>
                                        <p className="mt-0.5 flex items-center gap-1.5 font-semibold">
                                            <Clock className="text-primary h-3.5 w-3.5" />
                                            {enrollment.semester === 1 ? "1st" : "2nd"} Sem
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs font-medium uppercase">School Year</p>
                                        <p className="mt-0.5 flex items-center gap-1.5 font-semibold">
                                            <CalendarDays className="text-primary h-3.5 w-3.5" />
                                            {enrollment.school_year}
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-4 flex flex-col gap-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Enrolled Units</span>
                                        <span className="font-medium">
                                            {enrollment.subjects_enrolled.reduce((acc, sub) => acc + sub.units, 0)} Units
                                        </span>
                                    </div>
                                    <Progress
                                        value={(enrollment.subjects_enrolled.reduce((acc, sub) => acc + sub.units, 0) / 24) * 100}
                                        className="h-2"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Status & Actions Card */}
                        <Card className="border-l-primary border-l-4 shadow-sm">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center justify-between text-base">
                                    Enrollment Status
                                    <Badge variant="outline" className={getStatusColor(enrollment.status)}>
                                        {enrollment.status}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Stepper Visual */}
                                <div className="relative space-y-4 pl-2">
                                    <div className="bg-muted absolute top-2 bottom-2 left-[7px] w-[2px]"></div>
                                    {steps.map((step, idx) => (
                                        <div key={idx} className="relative flex items-center gap-3">
                                            <div
                                                className={cn(
                                                    "z-10 h-4 w-4 rounded-full border-2",
                                                    step.status === "completed"
                                                        ? "bg-primary border-primary"
                                                        : step.status === "current"
                                                          ? "bg-background border-primary animate-pulse"
                                                          : "bg-background border-muted",
                                                )}
                                            >
                                                {step.status === "completed" && <div className="bg-primary h-full w-full rounded-full" />}
                                            </div>
                                            <span
                                                className={cn(
                                                    "text-sm font-medium",
                                                    step.status === "completed"
                                                        ? "text-foreground"
                                                        : step.status === "current"
                                                          ? "text-primary font-bold"
                                                          : "text-muted-foreground",
                                                )}
                                            >
                                                {step.label}
                                            </span>
                                        </div>
                                    ))}
                                </div>

                                <Separator />

                                {/* Primary Action Button */}
                                <div className="pt-2">
                                    {nextStep?.action_type === "department_verification" && auth.can_verify_head && (
                                        <Button onClick={handleVerifyHeadDept} className="h-10 w-full bg-blue-600 shadow-sm hover:bg-blue-700">
                                            <UserCheck className="mr-2 h-4 w-4" />
                                            Verify Step
                                        </Button>
                                    )}

                                    {nextStep?.action_type === "cashier_verification" && auth.can_verify_cashier && (
                                        <VerifyCashierDialog
                                            enrollmentId={enrollment.id}
                                            tuition={enrollment.tuition}
                                            additionalFees={enrollment.additional_fees}
                                        />
                                    )}

                                    {auth.can_advance_pipeline &&
                                        nextStep &&
                                        nextStep.action_type !== "cashier_verification" &&
                                        enrollment.status !== pendingStatus && (
                                            <Button onClick={handleAdvancePipelineStep} className="h-10 w-full">
                                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                                Advance: {nextStep.label}
                                            </Button>
                                        )}

                                    {enrollment.status === completionStatus && (
                                        <Button
                                            className="w-full"
                                            variant="outline"
                                            onClick={() =>
                                                window.open(route("administrators.enrollments.assessment-preview", enrollment.id), "_blank")
                                            }
                                        >
                                            <Printer className="mr-2 h-4 w-4" /> Print Assessment
                                        </Button>
                                    )}

                                    {/* More Actions Dropdown */}
                                    <div className="mt-3 flex justify-center">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-foreground">
                                                    More Options <MoreVertical className="ml-1 h-3 w-3" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="center" className="w-56">
                                                <DropdownMenuLabel>Administrative Actions</DropdownMenuLabel>
                                                <DropdownMenuSeparator />
                                                {enrollment.status === completionStatus && (
                                                    <DropdownMenuItem onClick={handleResendAssessment}>
                                                        <FileText className="mr-2 h-4 w-4" /> Resend Assessment Email
                                                    </DropdownMenuItem>
                                                )}
                                                {departmentStep && enrollment.status === departmentVerifiedStatus && auth.can_verify_head && (
                                                    <DropdownMenuItem onClick={handleUndoHeadDept} className="text-red-600 focus:text-red-600">
                                                        <Undo2 className="mr-2 h-4 w-4" /> Undo Step Verification
                                                    </DropdownMenuItem>
                                                )}
                                                {cashierStep && enrollment.status === cashierVerifiedStatus && auth.can_verify_cashier && (
                                                    <DropdownMenuItem onClick={handleUndoCashier} className="text-red-600 focus:text-red-600">
                                                        <Undo2 className="mr-2 h-4 w-4" /> Undo Payment Verification
                                                    </DropdownMenuItem>
                                                )}
                                                {auth.is_super_admin && enrollment.status === pendingStatus && (
                                                    <QuickEnrollDialog enrollmentId={enrollment.id} />
                                                )}
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem onClick={handleRetryEnrollment}>
                                                    <RefreshCcw className="mr-2 h-4 w-4" /> Retry Class Enrollment
                                                </DropdownMenuItem>
                                                {recent_deletions.length > 0 && (
                                                    <DropdownMenuItem onClick={() => setShowUndoDialog(true)}>
                                                        <Undo2 className="mr-2 h-4 w-4" /> Restore Deleted Subjects ({recent_deletions.length})
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Financial Summary Card */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                    <Banknote className="text-muted-foreground h-4 w-4" />
                                    Tuition Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="mb-4 space-y-1">
                                    <p className="text-3xl font-bold tracking-tight">{formatMoney(enrollment.tuition?.total_balance)}</p>
                                    <p className="text-muted-foreground text-xs">Remaining Balance</p>
                                </div>

                                <div className="space-y-3">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">Total Fees</span>
                                        <span className="font-medium">{formatMoney(enrollment.tuition?.overall_tuition)}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">Total Paid</span>
                                        <span className="font-medium text-green-600">- {formatMoney(enrollment.tuition?.total_paid)}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Main Content */}
                    <div className="lg:col-span-8">
                        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                            <TabsList className="bg-muted/60 h-auto w-full justify-start p-1">
                                <TabsTrigger value="classes" className="data-[state=active]:bg-background flex-1 py-2 md:flex-none">
                                    Classes
                                </TabsTrigger>
                                <TabsTrigger value="financial" className="data-[state=active]:bg-background flex-1 py-2 md:flex-none">
                                    Financial
                                </TabsTrigger>
                                <TabsTrigger value="resources" className="data-[state=active]:bg-background flex-1 py-2 md:flex-none">
                                    Documents
                                </TabsTrigger>
                            </TabsList>

                            {/* CLASSES TAB */}
                            <TabsContent value="classes" className="mt-6 space-y-6">
                                {/* Active Enrollments */}
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <CardTitle>Class Schedule</CardTitle>
                                                <CardDescription>Enrolled subjects and class assignments</CardDescription>
                                            </div>
                                            <Badge variant="secondary">{enrollment.class_enrollments.length} Classes</Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="pl-6">Subject</TableHead>
                                                    <TableHead>Schedule & Room</TableHead>
                                                    <TableHead>Faculty</TableHead>
                                                    <TableHead className="pr-6 text-center">Grades</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {enrollment.class_enrollments.length === 0 ? (
                                                    <TableRow>
                                                        <TableCell colSpan={4} className="text-muted-foreground py-12 text-center">
                                                            No classes enrolled yet.
                                                        </TableCell>
                                                    </TableRow>
                                                ) : (
                                                    enrollment.class_enrollments.map((cls) => (
                                                        <TableRow key={cls.id}>
                                                            <TableCell className="pl-6 align-top">
                                                                <div className="font-semibold">{cls.subject_code}</div>
                                                                <div className="text-muted-foreground line-clamp-1 text-xs">{cls.subject_title}</div>
                                                                <Badge variant="outline" className="mt-1 h-5 text-[10px]">
                                                                    {cls.section}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="align-top">
                                                                <div className="text-sm font-medium">{cls.schedule}</div>
                                                                <div className="text-muted-foreground flex items-center gap-1 text-xs">
                                                                    <School className="h-3 w-3" /> {cls.room}
                                                                </div>
                                                            </TableCell>
                                                            <TableCell className="align-top">
                                                                <div className="flex items-center gap-2">
                                                                    <Avatar className="h-6 w-6">
                                                                        <AvatarFallback className="text-[10px]">
                                                                            {cls.faculty.charAt(0)}
                                                                        </AvatarFallback>
                                                                    </Avatar>
                                                                    <span className="text-sm">{cls.faculty}</span>
                                                                </div>
                                                            </TableCell>
                                                            <TableCell className="pr-6 text-center align-top">
                                                                <div className="flex justify-center gap-1 font-mono text-xs">
                                                                    <span
                                                                        title="Prelim"
                                                                        className={cn(
                                                                            "bg-muted rounded px-1.5 py-0.5",
                                                                            cls.grades.prelim ? "text-foreground" : "text-muted-foreground/50",
                                                                        )}
                                                                    >
                                                                        {cls.grades.prelim ?? "-"}
                                                                    </span>
                                                                    <span
                                                                        title="Midterm"
                                                                        className={cn(
                                                                            "bg-muted rounded px-1.5 py-0.5",
                                                                            cls.grades.midterm ? "text-foreground" : "text-muted-foreground/50",
                                                                        )}
                                                                    >
                                                                        {cls.grades.midterm ?? "-"}
                                                                    </span>
                                                                    <span
                                                                        title="Finals"
                                                                        className={cn(
                                                                            "bg-muted rounded px-1.5 py-0.5",
                                                                            cls.grades.finals ? "text-foreground" : "text-muted-foreground/50",
                                                                        )}
                                                                    >
                                                                        {cls.grades.finals ?? "-"}
                                                                    </span>
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))
                                                )}
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>

                                {/* Missing Classes - Enhanced UI */}
                                {enrollment.missing_classes.length > 0 && (
                                    <div className="overflow-hidden rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/20">
                                        <div className="flex items-center gap-3 border-b border-amber-200 bg-amber-100/50 p-4 dark:border-amber-900 dark:bg-amber-900/40">
                                            <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-500" />
                                            <div>
                                                <h3 className="font-semibold text-amber-900 dark:text-amber-200">
                                                    Action Required: Missing Class Assignments
                                                </h3>
                                                <p className="text-xs text-amber-700 dark:text-amber-400">
                                                    The student is enrolled in these subjects but not assigned to any class section.
                                                </p>
                                            </div>
                                        </div>
                                        <div className="p-0">
                                            <Table>
                                                <TableBody>
                                                    {enrollment.missing_classes.map((cls, idx) => (
                                                        <TableRow key={idx} className="hover:bg-amber-100/20">
                                                            <TableCell className="pl-6">
                                                                <div className="font-medium text-amber-900 dark:text-amber-200">
                                                                    {cls.subject_code}
                                                                </div>
                                                                <div className="text-xs text-amber-700 dark:text-amber-400">{cls.subject_title}</div>
                                                            </TableCell>
                                                            <TableCell>
                                                                {cls.class_id ? (
                                                                    <div className="flex flex-col">
                                                                        <span className="text-muted-foreground text-xs">Available:</span>
                                                                        <Badge variant="outline" className="bg-background w-fit">
                                                                            {cls.section}
                                                                        </Badge>
                                                                    </div>
                                                                ) : (
                                                                    <span className="text-muted-foreground text-xs italic">
                                                                        No class offering available
                                                                    </span>
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {cls.class_id && (
                                                                    <div className="flex flex-col">
                                                                        <span className="text-muted-foreground text-xs">Slots:</span>
                                                                        <span
                                                                            className={cn(
                                                                                "text-sm font-medium",
                                                                                cls.is_full ? "text-red-600" : "text-green-600",
                                                                            )}
                                                                        >
                                                                            {cls.available_slots} / {cls.max_slots}
                                                                        </span>
                                                                    </div>
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="pr-6 text-right">
                                                                {cls.class_id && (
                                                                    <EnrollClassButton
                                                                        enrollmentId={enrollment.id}
                                                                        classId={cls.class_id}
                                                                        isFull={cls.is_full}
                                                                        subjectCode={cls.subject_code}
                                                                    />
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </div>
                                )}
                            </TabsContent>

                            {/* FINANCIAL TAB */}
                            <TabsContent value="financial" className="mt-6 space-y-6">
                                <div className="grid gap-6 md:grid-cols-2">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Fees Breakdown</CardTitle>
                                            <CardDescription>Detailed assessment of fees</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="space-y-2">
                                                <div className="mb-2 flex items-center justify-between">
                                                    <h4 className="text-sm font-semibold">Breakdown</h4>
                                                    {auth.is_super_admin && (
                                                        <EditTuitionDialog enrollmentId={enrollment.id} tuition={enrollment.tuition} />
                                                    )}
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-muted-foreground">Tuition Fee (Lectures)</span>
                                                    <span>{formatMoney(enrollment.tuition?.total_lectures)}</span>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-muted-foreground">Laboratory Fee</span>
                                                    <span>{formatMoney(enrollment.tuition?.total_laboratory)}</span>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-muted-foreground">Miscellaneous Fee</span>
                                                    <span>{formatMoney(enrollment.tuition?.total_miscelaneous_fees)}</span>
                                                </div>
                                                {enrollment.additional_fees.map((fee) => (
                                                    <div key={fee.id} className="flex justify-between text-sm">
                                                        <span className="text-muted-foreground">{fee.fee_name}</span>
                                                        <span>{formatMoney(fee.amount)}</span>
                                                    </div>
                                                ))}
                                            </div>
                                            <Separator />
                                            <div className="space-y-2">
                                                <div className="flex justify-between text-sm font-medium">
                                                    <span>Subtotal</span>
                                                    <span>
                                                        {formatMoney(
                                                            (enrollment.tuition?.overall_tuition || 0) + (enrollment.tuition?.discount || 0),
                                                        )}
                                                    </span>
                                                </div>
                                                {enrollment.tuition?.discount ? (
                                                    <div className="flex justify-between text-sm text-green-600">
                                                        <span>Discount</span>
                                                        <span>- {formatMoney(enrollment.tuition.discount)}</span>
                                                    </div>
                                                ) : null}
                                                <div className="flex justify-between pt-2 text-base font-bold">
                                                    <span>Total Assessment</span>
                                                    <span>{formatMoney(enrollment.tuition?.overall_tuition)}</span>
                                                </div>
                                                <div className="flex justify-between text-sm text-green-600">
                                                    <span>Total Paid</span>
                                                    <span>- {formatMoney(enrollment.tuition?.total_paid)}</span>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Transaction History</CardTitle>
                                            <CardDescription>Payments and adjustments</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="border-muted relative ml-3 space-y-6 border-l">
                                                {enrollment.transactions.length === 0 ? (
                                                    <p className="text-muted-foreground pl-6 text-sm">No transactions recorded yet.</p>
                                                ) : (
                                                    enrollment.transactions.map((tx) => (
                                                        <div key={tx.id} className="relative pl-6">
                                                            <div
                                                                className={cn(
                                                                    "border-background absolute top-1.5 -left-1.5 h-3 w-3 rounded-full border-2",
                                                                    tx.status === "Paid" || tx.status === "Completed" ? "bg-green-500" : "bg-muted",
                                                                )}
                                                            />
                                                            <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                                <div>
                                                                    <p className="text-sm font-medium">{tx.description}</p>
                                                                    <div className="mt-1 flex items-center gap-2">
                                                                        <Badge variant="outline" className="h-5 text-[10px]">
                                                                            {tx.invoicenumber}
                                                                        </Badge>
                                                                        <span className="text-muted-foreground text-xs">
                                                                            {new Date(tx.created_at).toLocaleDateString()}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div className="flex flex-col items-end">
                                                                    <div className="flex items-center gap-2">
                                                                        <span className="text-sm font-bold">
                                                                            {formatMoney(tx.amount || tx.total_amount)}
                                                                        </span>
                                                                        {auth.can_verify_cashier && (
                                                                            <EditTransactionDialog enrollmentId={enrollment.id} transaction={tx} />
                                                                        )}
                                                                    </div>
                                                                    <Badge
                                                                        variant={
                                                                            tx.status === "Paid" || tx.status === "Completed"
                                                                                ? "default"
                                                                                : "secondary"
                                                                        }
                                                                        className={cn(
                                                                            "mt-1 h-5 text-[10px]",
                                                                            (tx.status === "Paid" || tx.status === "Completed") &&
                                                                                "border-green-200 bg-green-100 text-green-800 hover:bg-green-100",
                                                                        )}
                                                                    >
                                                                        {tx.status}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </TabsContent>

                            {/* RESOURCES TAB */}
                            <TabsContent value="resources" className="mt-6 space-y-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Generated Documents</CardTitle>
                                        <CardDescription>Official enrollment forms and assessments</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                            {enrollment.resources.map((res) => (
                                                <div
                                                    key={res.id}
                                                    className="bg-card flex items-start gap-4 rounded-lg border p-4 transition-shadow hover:shadow-sm"
                                                >
                                                    <div className="bg-primary/10 text-primary rounded-md p-2">
                                                        <FileText className="h-6 w-6" />
                                                    </div>
                                                    <div className="flex-1 space-y-1">
                                                        <p className="text-sm font-medium capitalize">{res.type.replace("_", " ")}</p>
                                                        <p className="text-muted-foreground max-w-[150px] truncate font-mono text-xs">
                                                            {res.file_name}
                                                        </p>
                                                        <p className="text-muted-foreground text-[10px]">
                                                            {new Date(res.created_at).toLocaleString()}
                                                        </p>
                                                    </div>
                                                    <Button variant="ghost" size="icon" asChild className="text-muted-foreground h-8 w-8">
                                                        <a href={res.download_url} target="_blank" rel="noreferrer">
                                                            <Download className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                </div>
                                            ))}
                                            {enrollment.resources.length === 0 && (
                                                <div className="text-muted-foreground col-span-full py-12 text-center">No documents available.</div>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>

            {/* Restore Deleted Subjects Dialog */}
            <RestoreSubjectsDialog
                open={showUndoDialog}
                onOpenChange={setShowUndoDialog}
                recentDeletions={recent_deletions}
                selectedDeletions={selectedDeletions}
                onToggleSelection={toggleDeletionSelection}
                onSelectAll={selectAllDeletions}
                onRestore={handleRestoreSubjects}
                restoring={restoringSubjects}
            />
        </AdminLayout>
    );
}

// --- Subcomponents ---

function EditTuitionDialog({ enrollmentId, tuition }: { enrollmentId: number; tuition: any }) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        total_lectures: tuition?.total_lectures || 0,
        total_laboratory: tuition?.total_laboratory || 0,
        total_miscelaneous_fees: tuition?.total_miscelaneous_fees || 0,
        discount: tuition?.discount || 0,
        paid: tuition?.total_paid || 0,
    });
    const [open, setOpen] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // @ts-ignore
        patch(route("administrators.enrollments.tuition.update", enrollmentId), {
            onSuccess: () => {
                setOpen(false);
                toast.success("Tuition updated");
            },
            onError: () => toast.error("Failed to update tuition"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-foreground h-6 w-6">
                    <Pencil className="h-3 w-3" />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Edit Tuition Details</DialogTitle>
                    <DialogDescription>Modify tuition fees, discounts, and total paid amount.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4 py-4">
                    <div className="grid gap-2">
                        <Label htmlFor="lectures">Tuition Fee (Lectures)</Label>
                        <Input
                            id="lectures"
                            type="number"
                            step="0.01"
                            value={data.total_lectures}
                            onChange={(e) => setData("total_lectures", parseFloat(e.target.value))}
                            required
                        />
                        {errors.total_lectures && <p className="text-xs text-red-500">{errors.total_lectures}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="laboratory">Laboratory Fee</Label>
                        <Input
                            id="laboratory"
                            type="number"
                            step="0.01"
                            value={data.total_laboratory}
                            onChange={(e) => setData("total_laboratory", parseFloat(e.target.value))}
                            required
                        />
                        {errors.total_laboratory && <p className="text-xs text-red-500">{errors.total_laboratory}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="misc">Miscellaneous Fee</Label>
                        <Input
                            id="misc"
                            type="number"
                            step="0.01"
                            value={data.total_miscelaneous_fees}
                            onChange={(e) => setData("total_miscelaneous_fees", parseFloat(e.target.value))}
                            required
                        />
                        {errors.total_miscelaneous_fees && <p className="text-xs text-red-500">{errors.total_miscelaneous_fees}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="discount">Discount (%)</Label>
                            <Input
                                id="discount"
                                type="number"
                                min="0"
                                max="100"
                                value={data.discount}
                                onChange={(e) => setData("discount", parseInt(e.target.value))}
                                required
                            />
                            {errors.discount && <p className="text-xs text-red-500">{errors.discount}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="paid">Total Paid Override</Label>
                            <Input
                                id="paid"
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.paid}
                                onChange={(e) => setData("paid", parseFloat(e.target.value))}
                            />
                            {errors.paid && <p className="text-xs text-red-500">{errors.paid}</p>}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditTransactionDialog({ enrollmentId, transaction }: { enrollmentId: number; transaction: any }) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        amount: transaction.amount || transaction.total_amount || 0,
        invoicenumber: transaction.invoicenumber || "",
    });
    const [open, setOpen] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // @ts-ignore
        patch(route("administrators.enrollments.transactions.update", [enrollmentId, transaction.id]), {
            onSuccess: () => {
                setOpen(false);
                toast.success("Transaction updated");
            },
            onError: () => toast.error("Failed to update transaction"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-foreground h-6 w-6">
                    <Pencil className="h-3 w-3" />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Edit Transaction</DialogTitle>
                    <DialogDescription>Modify amount and invoice number. Use with caution.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4 py-4">
                    <div className="grid gap-2">
                        <Label htmlFor="tx-invoice">Invoice Number</Label>
                        <Input id="tx-invoice" value={data.invoicenumber} onChange={(e) => setData("invoicenumber", e.target.value)} required />
                        {errors.invoicenumber && <p className="text-xs text-red-500">{errors.invoicenumber}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tx-amount">Amount</Label>
                        <Input
                            id="tx-amount"
                            type="number"
                            step="0.01"
                            value={data.amount}
                            onChange={(e) => setData("amount", parseFloat(e.target.value))}
                            required
                        />
                        {errors.amount && <p className="text-xs text-red-500">{errors.amount}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function VerifyCashierDialog({ enrollmentId, tuition, additionalFees }: { enrollmentId: number; tuition: any; additionalFees: any[] }) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";
    const { data, setData, post, processing, errors, reset } = useForm({
        invoicenumber: "",
        payment_method: "Cash",
        settlements: {
            registration_fee: 0,
            tuition_fee: tuition?.downpayment || 5000,
            miscelanous_fee: 0,
            others: 0,
        },
    });
    const [open, setOpen] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route("administrators.enrollments.verify-cashier", enrollmentId), {
            onSuccess: () => {
                setOpen(false);
                toast.success("Successfully enrolled student");
                reset();
            },
            onError: () => toast.error("Failed to process enrollment"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="h-10 w-full bg-emerald-600 shadow-sm hover:bg-emerald-700">
                    <CheckCircle2 className="mr-2 h-4 w-4" />
                    Verify & Enroll
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Cashier Verification</DialogTitle>
                    <DialogDescription>Confirm payment details to finalize enrollment.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4 py-4">
                    <div className="grid gap-2">
                        <Label htmlFor="invoice">Invoice / OR Number</Label>
                        <Input
                            id="invoice"
                            required
                            placeholder="Enter Invoice No."
                            value={data.invoicenumber}
                            onChange={(e) => setData("invoicenumber", e.target.value)}
                        />
                        {errors.invoicenumber && <p className="text-xs text-red-500">{errors.invoicenumber}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="payment_method">Payment Method</Label>
                        <Select value={data.payment_method} onValueChange={(val) => setData("payment_method", val)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Cash">Cash</SelectItem>
                                <SelectItem value="Check">Check</SelectItem>
                                <SelectItem value="Bank Transfer">Bank Transfer</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label>Downpayment Amount</Label>
                        <div className="relative">
                            <span className="text-muted-foreground absolute top-2.5 left-3">{currency === "USD" ? "$" : "₱"}</span>
                            <Input
                                type="number"
                                className="pl-7"
                                value={data.settlements.tuition_fee}
                                onChange={(e) => setData("settlements", { ...data.settlements, tuition_fee: parseFloat(e.target.value) || 0 })}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Confirm Enrollment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EnrollClassButton({
    enrollmentId,
    classId,
    isFull,
    subjectCode,
}: {
    enrollmentId: number;
    classId: number;
    isFull: boolean;
    subjectCode: string;
}) {
    const [processing, setProcessing] = useState(false);

    const handleEnroll = () => {
        if (isFull && !confirm("Class is full. Force enroll?")) return;

        router.post(
            route("administrators.enrollments.enroll-class", enrollmentId),
            {
                class_id: classId,
                force_enrollment: isFull, // Force if full
            },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => toast.success(`Enrolled in ${subjectCode}`),
                onError: () => toast.error("Failed to enroll"),
            },
        );
    };

    return (
        <Button size="sm" variant={isFull ? "destructive" : "secondary"} onClick={handleEnroll} disabled={processing} className="shadow-sm">
            {isFull ? "Force Enroll" : "Enroll"}
        </Button>
    );
}

function QuickEnrollDialog({ enrollmentId }: { enrollmentId: number }) {
    const { data, setData, post, processing, reset } = useForm({
        remarks: "",
        confirm_emergency: false,
        confirm_payment: false,
    });
    const [open, setOpen] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route("administrators.enrollments.quick-enroll", enrollmentId), {
            onSuccess: () => {
                setOpen(false);
                toast.success("Quick enrollment successful");
                reset();
            },
            onError: () => toast.error("Quick enrollment failed"),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <DropdownMenuItem onSelect={(e) => e.preventDefault()}>
                    <ShieldAlert className="mr-2 h-4 w-4" /> Quick Enroll (Super Admin)
                </DropdownMenuItem>
            </DialogTrigger>
            <DialogContent className="border-red-200 sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-red-600">
                        <ShieldAlert className="h-5 w-5" /> Emergency Quick Enroll
                    </DialogTitle>
                    <DialogDescription>Bypass all verification steps. Use only in exceptional circumstances.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4 py-4">
                    <div className="grid gap-2">
                        <Label htmlFor="remarks">Justification / Remarks</Label>
                        <Textarea
                            id="remarks"
                            required
                            placeholder="Explain why verification is being bypassed..."
                            value={data.remarks}
                            onChange={(e) => setData("remarks", e.target.value)}
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="confirm_emergency"
                            className="rounded border-gray-300"
                            checked={data.confirm_emergency}
                            onChange={(e) => setData("confirm_emergency", e.target.checked)}
                            required
                        />
                        <Label htmlFor="confirm_emergency" className="text-xs">
                            I confirm this is an emergency enrollment
                        </Label>
                    </div>

                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="confirm_payment"
                            className="rounded border-gray-300"
                            checked={data.confirm_payment}
                            onChange={(e) => setData("confirm_payment", e.target.checked)}
                            required
                        />
                        <Label htmlFor="confirm_payment" className="text-xs">
                            I confirm payment has been verified manually
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            Quick Enroll
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RestoreSubjectsDialog({
    open,
    onOpenChange,
    recentDeletions,
    selectedDeletions,
    onToggleSelection,
    onSelectAll,
    onRestore,
    restoring,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    recentDeletions: RecentDeletion[];
    selectedDeletions: number[];
    onToggleSelection: (id: number) => void;
    onSelectAll: () => void;
    onRestore: () => void;
    restoring: boolean;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[550px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Undo2 className="h-5 w-5" /> Restore Deleted Subjects
                    </DialogTitle>
                    <DialogDescription>
                        Select subjects to restore. This will recreate the subject enrollments and attempt to re-enroll in their original classes.
                    </DialogDescription>
                </DialogHeader>
                <div className="max-h-[300px] space-y-2 overflow-y-auto py-4">
                    <div className="flex items-center gap-2 pb-2">
                        <Checkbox
                            id="select-all"
                            checked={selectedDeletions.length === recentDeletions.length && recentDeletions.length > 0}
                            onCheckedChange={onSelectAll}
                        />
                        <label htmlFor="select-all" className="text-sm font-medium">
                            Select All ({recentDeletions.length} items)
                        </label>
                    </div>
                    <Separator />
                    {recentDeletions.map((deletion) => (
                        <div key={deletion.id} className="hover:bg-muted/50 flex items-start gap-3 rounded-lg border p-3 transition-colors">
                            <Checkbox
                                id={`deletion-${deletion.id}`}
                                checked={selectedDeletions.includes(deletion.id)}
                                onCheckedChange={() => onToggleSelection(deletion.id)}
                                className="mt-0.5"
                            />
                            <div className="flex-1">
                                <div className="flex items-center justify-between">
                                    <label htmlFor={`deletion-${deletion.id}`} className="cursor-pointer font-medium">
                                        {deletion.subject_code}
                                    </label>
                                    <span className="text-muted-foreground text-xs">{deletion.created_at_human}</span>
                                </div>
                                <p className="text-muted-foreground text-sm">{deletion.subject_title}</p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Deleted by: {deletion.causer}
                                    {deletion.class_id && ` | Class ID: ${deletion.class_id}`}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button onClick={onRestore} disabled={restoring || selectedDeletions.length === 0}>
                        <Undo2 className="mr-2 h-4 w-4" />
                        Restore {selectedDeletions.length} Subject{selectedDeletions.length !== 1 ? "s" : ""}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
