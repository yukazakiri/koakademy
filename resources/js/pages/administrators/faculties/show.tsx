import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { IconTile } from "@/components/reui/icon-tile";
import {
    Timeline,
    TimelineContent,
    TimelineDate,
    TimelineHeader,
    TimelineIndicator,
    TimelineItem,
    TimelineSeparator,
    TimelineTitle,
} from "@/components/reui/timeline";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    BookOpen,
    Building2,
    Calendar,
    CalendarClock,
    CheckCircle2,
    Clock,
    Copy,
    ExternalLink,
    FileText,
    GraduationCap,
    History,
    IdCard,
    KeyRound,
    Mail,
    Megaphone,
    Phone,
    Plus,
    Save,
    Search,
    Send,
    Trash2,
    UserCheck,
    UserCog,
    UserRound,
} from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

type Option = { value: string; label: string };

type ScheduleSlot = {
    start_time?: string;
    end_time?: string;
    time_range: string;
    room: { id: number; name: string };
};

type WeeklySchedule = Record<string, ScheduleSlot[]>;

type ClassRow = {
    id: number;
    subject_code: string;
    subject_title: string | null;
    section: string;
    school_year: string;
    semester: number;
    classification: string | null;
    student_count: number | null;
    schedule?: WeeklySchedule | null;
};

type AssignmentWarning = {
    type: "reassignment" | "schedule_conflict" | "heavy_load";
    message: string;
};

type AssignmentClass = ClassRow & {
    label: string;
    assigned_faculty: { id: string; name: string } | null;
    assignment_status: "unassigned" | "assigned_here" | "assigned_elsewhere";
    warnings: AssignmentWarning[];
};

type FacultyDetail = {
    id: string;
    faculty_id_number: string | null;
    name: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    email: string;
    phone_number: string | null;
    department: string | null;
    position: string | null;
    office_hours: string | null;
    birth_date: string | null;
    date_employed: string | null;
    address_line1: string | null;
    biography: string | null;
    education: string | null;
    courses_taught: string | null;
    avatar_url: string | null;
    status: string | null;
    gender: string | null;
    age: number | null;
    created_at: string;
    updated_at: string;
    classes: ClassRow[];
    current_classes: ClassRow[];
    profile_completion: {
        completed: number;
        total: number;
        percent: number;
        missing: string[];
    };
    portal_account: {
        status: "linked" | "needs_repair" | "not_linked";
        label: string;
        user_id: number | null;
        role: string | null;
        role_label: string | null;
        email_verified_at: string | null;
        last_login_at: string | null;
        needs_repair: boolean;
    };
    workload_summary: {
        current_classes_count: number;
        level: "needs_classes" | "light" | "balanced" | "heavy";
        label: string;
    };
    recommended_actions: {
        type: string;
        title: string;
        description: string;
    }[];
    recent_notifications: {
        id: string;
        title: string;
        message: string;
        priority: string;
        read_at: string | null;
        created_at: string | null;
    }[];
    deadlines: {
        id: number;
        title: string;
        description: string | null;
        due_date: string | null;
        priority: string;
        type: string | null;
        class_label: string | null;
    }[];
    filament: {
        view_url: string;
        edit_url: string;
    };
};

interface FacultyShowProps {
    user: User;
    faculty: FacultyDetail;
    assignment_planner: {
        classes: AssignmentClass[];
    };
    options: {
        statuses: Option[];
        faculty_roles: Option[];
        deadline_priorities: Option[];
        notice_priorities: Option[];
    };
}

export default function AdministratorFacultyShow({ user, faculty, assignment_planner, options }: FacultyShowProps) {
    const [activeTab, setActiveTab] = useState("overview");
    const [assignmentSearch, setAssignmentSearch] = useState("");
    const [deleteModalOpen, setDeleteModalOpen] = useState(false);
    const [unassignModal, setUnassignModal] = useState<{ open: boolean; classId: number; title: string }>({
        open: false,
        classId: 0,
        title: "",
    });

    const idForm = useForm({ faculty_id_number: faculty.faculty_id_number ?? "" });

    const assignmentForm = useForm({
        class_ids: [] as number[],
        allow_reassignment: false,
        notify_faculty: true,
    });

    const portalForm = useForm({
        mode: faculty.portal_account.status === "not_linked" ? "create" : "repair",
        role: faculty.portal_account.role ?? options.faculty_roles[0]?.value ?? "instructor",
        send_reset_link: true,
    });

    const noticeForm = useForm({
        title: "",
        message: "",
        priority: "normal",
        action_url: "",
    });

    const deadlineForm = useForm({
        title: "",
        description: "",
        due_date: "",
        priority: "medium",
        type: "administrative",
        class_id: "none",
    });

    const copyToClipboard = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        toast.success(`${label} copied to clipboard`);
    };

    const totalEnrolledStudents = useMemo(() => {
        return faculty.current_classes.reduce((sum, item) => sum + (item.student_count || 0), 0);
    }, [faculty.current_classes]);

    const weeklyScheduleByDay = useMemo(() => {
        const days = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday"];
        const dayLabels: Record<string, string> = {
            monday: "Monday",
            tuesday: "Tuesday",
            wednesday: "Wednesday",
            thursday: "Thursday",
            friday: "Friday",
            saturday: "Saturday",
        };

        const result: Record<string, { class: ClassRow; slot: ScheduleSlot }[]> = {
            monday: [],
            tuesday: [],
            wednesday: [],
            thursday: [],
            friday: [],
            saturday: [],
        };

        faculty.current_classes.forEach((cls) => {
            if (cls.schedule) {
                Object.entries(cls.schedule).forEach(([day, slots]) => {
                    const normalizedDay = day.toLowerCase();
                    if (result[normalizedDay] && Array.isArray(slots)) {
                        slots.forEach((slot) => {
                            result[normalizedDay].push({ class: cls, slot });
                        });
                    }
                });
            }
        });

        return { days, dayLabels, schedule: result };
    }, [faculty.current_classes]);

    const hasAnySchedule = useMemo(() => {
        return Object.values(weeklyScheduleByDay.schedule).some((slots) => slots.length > 0);
    }, [weeklyScheduleByDay]);

    const filteredPlannerClasses = useMemo(() => {
        const query = assignmentSearch.trim().toLowerCase();
        if (!query) return assignment_planner.classes;

        return assignment_planner.classes.filter((item) => {
            return (
                item.label.toLowerCase().includes(query) ||
                item.subject_code.toLowerCase().includes(query) ||
                item.section.toLowerCase().includes(query) ||
                (item.subject_title && item.subject_title.toLowerCase().includes(query))
            );
        });
    }, [assignmentSearch, assignment_planner.classes]);

    const selectedPlannerClasses = useMemo(() => {
        return assignment_planner.classes.filter((item) => assignmentForm.data.class_ids.includes(item.id));
    }, [assignmentForm.data.class_ids, assignment_planner.classes]);

    const selectedHasReassignment = selectedPlannerClasses.some((item) => item.assignment_status === "assigned_elsewhere");
    const selectedWarnings = selectedPlannerClasses.flatMap((item) => item.warnings.map((warning) => ({ ...warning, classLabel: item.label })));

    const toggleAssignmentClass = (classId: number) => {
        const classIds = assignmentForm.data.class_ids.includes(classId)
            ? assignmentForm.data.class_ids.filter((id) => id !== classId)
            : [...assignmentForm.data.class_ids, classId];

        assignmentForm.setData("class_ids", classIds);
    };

    const submitAssignments = () => {
        assignmentForm.post(route("administrators.faculties.assign-classes", faculty.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Classes assigned successfully");
                assignmentForm.reset("class_ids", "allow_reassignment");
                setAssignmentSearch("");
            },
        });
    };

    const submitPortal = () => {
        portalForm.post(route("administrators.faculties.portal-account", faculty.id), {
            preserveScroll: true,
            onSuccess: () => toast.success("Faculty portal access configured"),
        });
    };

    const submitNotice = () => {
        noticeForm.post(route("administrators.faculties.notice", faculty.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Notice sent to faculty portal");
                noticeForm.reset();
            },
        });
    };

    const submitDeadline = () => {
        deadlineForm.transform((d) => ({
            ...d,
            class_id: d.class_id === "none" ? null : Number(d.class_id),
        }));

        deadlineForm.post(route("administrators.faculties.deadlines.store", faculty.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Faculty deadline created");
                deadlineForm.reset();
            },
        });
    };

    const submitFacultyId = () => {
        idForm.put(route("administrators.faculties.update-id-number", faculty.id), {
            preserveScroll: true,
            onSuccess: () => toast.success("Faculty ID number updated"),
        });
    };

    const confirmUnassign = () => {
        if (!unassignModal.classId) return;

        router.delete(route("administrators.faculties.classes.unassign", { faculty: faculty.id, class: unassignModal.classId }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Class unassigned from faculty");
                setUnassignModal({ open: false, classId: 0, title: "" });
            },
        });
    };

    const confirmDeleteFaculty = () => {
        router.delete(route("administrators.faculties.destroy", faculty.id), {
            onSuccess: () => toast.success(`Faculty profile ${faculty.name} deleted`),
        });
    };

    const statusBadge =
        faculty.status === "active"
            ? {
                  variant: "success-light" as const,
                  dot: "bg-success",
                  label: "Active",
              }
            : faculty.status === "on_leave"
              ? {
                    variant: "warning-light" as const,
                    dot: "bg-warning",
                    label: "On Leave",
                }
              : {
                    variant: "invert-light" as const,
                    dot: "bg-muted-foreground",
                    label: "Inactive",
                };

    return (
        <AdminLayout user={user} title={`Faculty - ${faculty.name}`}>
            <Head title={`Administrators - ${faculty.name} (Faculty Details)`} />

            <div className="flex flex-col gap-6">
                {/* Profile Hero Header Card */}
                <Card className="border-border/80 overflow-hidden shadow-xs">
                    <div className="from-primary/10 via-primary/5 to-muted h-24 bg-gradient-to-r" />

                    <CardContent className="relative px-6 pt-0 pb-6">
                        <div className="-mt-12 mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div className="flex items-end gap-4">
                                <div className="relative">
                                    <Avatar className="border-background ring-border h-24 w-24 border-4 shadow-xs ring-1">
                                        <AvatarImage src={faculty.avatar_url ?? undefined} alt={faculty.name} />
                                        <AvatarFallback className="text-2xl font-bold">
                                            {(faculty.name || "?").slice(0, 2).toUpperCase()}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span
                                        className={cn("ring-background absolute right-1 bottom-1 h-4 w-4 rounded-full ring-2", statusBadge.dot)}
                                        title={`Status: ${statusBadge.label}`}
                                    />
                                </div>

                                <div className="mb-1 flex flex-col gap-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h1 className="text-2xl font-bold tracking-tight">{faculty.name}</h1>
                                        <Badge variant={statusBadge.variant} size="xs" radius="full" className="gap-1">
                                            <span className={cn("h-1.5 w-1.5 rounded-full", statusBadge.dot)} />
                                            {statusBadge.label}
                                        </Badge>
                                    </div>
                                    <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                                        <span>{faculty.position || "Faculty Member"}</span>
                                        <span>&bull;</span>
                                        <span className="text-foreground flex items-center gap-1 font-medium">
                                            <Building2 className="text-muted-foreground h-3.5 w-3.5" />
                                            {faculty.department || "Unassigned Department"}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Header Buttons */}
                            <div className="flex flex-wrap items-center gap-2">
                                <Link
                                    href={route("administrators.faculties.edit", faculty.id)}
                                    className={buttonVariants({ variant: "outline", size: "sm" })}
                                >
                                    <UserCog className="mr-1.5 h-4 w-4" /> Edit Profile
                                </Link>

                                <a
                                    href={faculty.filament.view_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className={buttonVariants({ variant: "outline", size: "sm" })}
                                >
                                    <ExternalLink className="mr-1.5 h-4 w-4" /> Filament
                                </a>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    onClick={() => setDeleteModalOpen(true)}
                                >
                                    <Trash2 className="mr-1.5 h-4 w-4" /> Delete
                                </Button>
                            </div>
                        </div>

                        <Separator className="my-4" />

                        {/* Quick Contact & Credentials Strip */}
                        <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-4 text-xs">
                            <div className="flex flex-wrap items-center gap-4">
                                {faculty.faculty_id_number ? (
                                    <div className="flex items-center gap-1.5">
                                        <IdCard className="text-muted-foreground h-3.5 w-3.5" />
                                        <span className="text-foreground font-mono font-medium">#{faculty.faculty_id_number}</span>
                                        <button
                                            type="button"
                                            onClick={() => copyToClipboard(faculty.faculty_id_number ?? "", "Faculty ID")}
                                            className="hover:text-foreground cursor-pointer p-0.5"
                                            title="Copy ID"
                                        >
                                            <Copy className="h-3 w-3" />
                                        </button>
                                    </div>
                                ) : (
                                    <span className="text-warning font-medium">No ID Assigned</span>
                                )}

                                <div className="flex items-center gap-1.5">
                                    <Mail className="text-muted-foreground h-3.5 w-3.5" />
                                    <a href={`mailto:${faculty.email}`} className="text-foreground hover:underline">
                                        {faculty.email}
                                    </a>
                                    <button
                                        type="button"
                                        onClick={() => copyToClipboard(faculty.email, "Email")}
                                        className="hover:text-foreground cursor-pointer p-0.5"
                                        title="Copy email"
                                    >
                                        <Copy className="h-3 w-3" />
                                    </button>
                                </div>

                                {faculty.phone_number ? (
                                    <div className="flex items-center gap-1.5">
                                        <Phone className="text-muted-foreground h-3.5 w-3.5" />
                                        <a href={`tel:${faculty.phone_number}`} className="text-foreground hover:underline">
                                            {faculty.phone_number}
                                        </a>
                                    </div>
                                ) : null}

                                {faculty.office_hours ? (
                                    <div className="flex items-center gap-1.5">
                                        <Clock className="text-muted-foreground h-3.5 w-3.5" />
                                        <span className="text-foreground max-w-64 truncate">{faculty.office_hours}</span>
                                    </div>
                                ) : null}
                            </div>

                            <div className="flex items-center gap-2">
                                <Link
                                    href={route("administrators.faculties.index")}
                                    className="text-muted-foreground hover:text-foreground flex items-center gap-1 transition-colors"
                                >
                                    <ArrowLeft className="h-3.5 w-3.5" /> Back to Directory
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* KPI Metrics Row with ReUI IconTile */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Active Teaching Load</CardTitle>
                            <IconTile variant="soft" size="sm" className="text-primary">
                                <BookOpen />
                            </IconTile>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold tracking-tight tabular-nums">{faculty.current_classes.length}</span>
                                <Badge variant="secondary" size="xs" radius="full" className="capitalize">
                                    {faculty.workload_summary.label}
                                </Badge>
                            </div>
                            <div className="text-muted-foreground text-xs">
                                <span className="text-foreground font-medium tabular-nums">{totalEnrolledStudents}</span> total student(s) enrolled
                                across classes
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Portal Account</CardTitle>
                            <IconTile variant="soft" size="sm" className="text-success">
                                <KeyRound />
                            </IconTile>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <span className="truncate text-base font-bold tracking-tight capitalize">{faculty.portal_account.label}</span>
                            </div>
                            <div className="text-muted-foreground text-xs">Role: {faculty.portal_account.role_label || "No role specified"}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Profile Quality</CardTitle>
                            <IconTile variant="soft" size="sm" className="text-info">
                                <CheckCircle2 />
                            </IconTile>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1.5">
                            <div className="flex items-center justify-between">
                                <span className="text-2xl font-bold tracking-tight tabular-nums">{faculty.profile_completion.percent}%</span>
                                <span className="text-muted-foreground text-xs tabular-nums">
                                    {faculty.profile_completion.completed}/{faculty.profile_completion.total} fields
                                </span>
                            </div>
                            <Progress value={faculty.profile_completion.percent} className="h-1.5" />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Tasks & Notices</CardTitle>
                            <IconTile variant="soft" size="sm" className="text-warning">
                                <CalendarClock />
                            </IconTile>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold tracking-tight tabular-nums">{faculty.deadlines.length}</span>
                                <span className="text-muted-foreground text-xs">active deadlines</span>
                            </div>
                            <div className="text-muted-foreground text-xs">
                                <span className="text-foreground font-medium tabular-nums">{faculty.recent_notifications.length}</span> recent portal
                                notices
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* ReUI Alert Banners for Recommended Actions */}
                {faculty.recommended_actions.length > 0 ? (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {faculty.recommended_actions.map((act) => (
                            <Alert key={act.title} variant="warning">
                                <AlertTriangle />
                                <AlertTitle>{act.title}</AlertTitle>
                                <AlertDescription>{act.description}</AlertDescription>
                            </Alert>
                        ))}
                    </div>
                ) : null}

                {/* Interactive Workspace Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                    <TabsList className="bg-muted/60 grid h-auto w-full grid-cols-2 p-1 sm:grid-cols-6">
                        <TabsTrigger value="overview" className="gap-1.5 py-2 text-xs sm:text-sm">
                            <UserRound className="h-4 w-4 shrink-0" />
                            <span>Overview</span>
                        </TabsTrigger>

                        <TabsTrigger value="load" className="gap-1.5 py-2 text-xs sm:text-sm">
                            <BookOpen className="h-4 w-4 shrink-0" />
                            <span>Teaching Load</span>
                            <Badge variant="secondary" size="xs" radius="full" className="ml-1 tabular-nums">
                                {faculty.current_classes.length}
                            </Badge>
                        </TabsTrigger>

                        <TabsTrigger value="portal" className="gap-1.5 py-2 text-xs sm:text-sm">
                            <KeyRound className="h-4 w-4 shrink-0" />
                            <span>Portal Access</span>
                        </TabsTrigger>

                        <TabsTrigger value="notices" className="gap-1.5 py-2 text-xs sm:text-sm">
                            <Megaphone className="h-4 w-4 shrink-0" />
                            <span>Notices</span>
                            {faculty.recent_notifications.length > 0 ? (
                                <Badge variant="secondary" size="xs" radius="full" className="ml-1 tabular-nums">
                                    {faculty.recent_notifications.length}
                                </Badge>
                            ) : null}
                        </TabsTrigger>

                        <TabsTrigger value="deadlines" className="gap-1.5 py-2 text-xs sm:text-sm">
                            <CalendarClock className="h-4 w-4 shrink-0" />
                            <span>Deadlines</span>
                            {faculty.deadlines.length > 0 ? (
                                <Badge variant="secondary" size="xs" radius="full" className="ml-1 tabular-nums">
                                    {faculty.deadlines.length}
                                </Badge>
                            ) : null}
                        </TabsTrigger>

                        <TabsTrigger value="dossier" className="gap-1.5 py-2 text-xs sm:text-sm">
                            <FileText className="h-4 w-4 shrink-0" />
                            <span>Dossier</span>
                        </TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Overview & Schedule Timetable */}
                    <TabsContent value="overview" className="mt-4 flex flex-col gap-4">
                        {/* Weekly Schedule Visualizer */}
                        <Card>
                            <CardHeader className="bg-muted/10 border-b pb-3">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <IconTile variant="soft" size="xs" className="text-primary">
                                                <Calendar />
                                            </IconTile>
                                            Weekly Teaching Schedule
                                        </CardTitle>
                                        <CardDescription>Scheduled class meeting times and room allocations for the active term.</CardDescription>
                                    </div>
                                    <Button variant="outline" size="sm" onClick={() => setActiveTab("load")} className="h-8 text-xs">
                                        <Plus className="mr-1 h-3.5 w-3.5" /> Manage Teaching Load
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-4">
                                {hasAnySchedule ? (
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                                        {weeklyScheduleByDay.days.map((dayKey) => {
                                            const daySlots = weeklyScheduleByDay.schedule[dayKey] || [];
                                            const dayLabel = weeklyScheduleByDay.dayLabels[dayKey];

                                            return (
                                                <div
                                                    key={dayKey}
                                                    className={cn(
                                                        "flex flex-col gap-2 rounded-lg border p-3 text-xs",
                                                        daySlots.length > 0
                                                            ? "border-border/80 bg-background"
                                                            : "border-border/50 bg-muted/10 border-dashed opacity-70",
                                                    )}
                                                >
                                                    <div className="flex items-center justify-between border-b pb-1.5">
                                                        <span className="text-sm font-semibold">{dayLabel}</span>
                                                        <Badge
                                                            variant={daySlots.length > 0 ? "secondary" : "outline"}
                                                            size="xs"
                                                            radius="full"
                                                            className="tabular-nums"
                                                        >
                                                            {daySlots.length} {daySlots.length === 1 ? "class" : "classes"}
                                                        </Badge>
                                                    </div>

                                                    {daySlots.length === 0 ? (
                                                        <span className="text-muted-foreground py-3 text-center italic">No classes scheduled</span>
                                                    ) : (
                                                        <div className="flex flex-col gap-2 pt-1">
                                                            {daySlots.map(({ class: cls, slot }, idx) => (
                                                                <div
                                                                    key={`${cls.id}-${idx}`}
                                                                    className="border-primary/20 bg-primary/[0.03] flex flex-col gap-1 rounded-md border p-2"
                                                                >
                                                                    <div className="text-foreground flex items-center justify-between font-semibold">
                                                                        <span>{cls.subject_code}</span>
                                                                        <Badge variant="outline" size="xs" radius="full" className="font-mono">
                                                                            Sec {cls.section}
                                                                        </Badge>
                                                                    </div>
                                                                    <div className="text-muted-foreground truncate text-[11px]">
                                                                        {cls.subject_title || "No subject title"}
                                                                    </div>
                                                                    <div className="text-muted-foreground flex items-center justify-between pt-0.5 text-[11px]">
                                                                        <span className="text-primary font-mono font-medium">{slot.time_range}</span>
                                                                        <span className="bg-muted rounded px-1.5 py-0.5 text-[10px]">
                                                                            {slot.room?.name || "TBA Room"}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center">
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <Calendar className="text-muted-foreground h-6 w-6" />
                                                </EmptyMedia>
                                                <EmptyTitle>No classes scheduled this term</EmptyTitle>
                                                <EmptyDescription>
                                                    Assign classes in the Teaching Load tab to populate this faculty member's weekly timetable.
                                                </EmptyDescription>
                                            </EmptyHeader>
                                            <EmptyContent>
                                                <Button size="sm" onClick={() => setActiveTab("load")}>
                                                    <BookOpen className="mr-1.5 h-4 w-4" /> Go to Class Assignment Planner
                                                </Button>
                                            </EmptyContent>
                                        </Empty>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Side by side: ID quick update & profile snapshot */}
                        <div className="grid gap-4 lg:grid-cols-2">
                            {/* Quick ID Correction */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <IdCard />
                                        </IconTile>
                                        Faculty ID Number
                                    </CardTitle>
                                    <CardDescription>
                                        Update the administrative employee identification number without editing full profile.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <div className="flex gap-2">
                                        <Input
                                            value={idForm.data.faculty_id_number}
                                            onChange={(e) => idForm.setData("faculty_id_number", e.target.value)}
                                            placeholder="e.g. FAC-2026-001"
                                            className={cn(idForm.errors.faculty_id_number && "border-destructive")}
                                        />
                                        <Button
                                            onClick={submitFacultyId}
                                            disabled={idForm.processing || idForm.data.faculty_id_number === faculty.faculty_id_number}
                                        >
                                            <Save className="mr-1.5 h-4 w-4" /> Save
                                        </Button>
                                    </div>
                                    {idForm.errors.faculty_id_number ? (
                                        <p className="text-destructive text-xs">{idForm.errors.faculty_id_number}</p>
                                    ) : null}
                                </CardContent>
                            </Card>

                            {/* Office Hours & Consultation */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Clock />
                                        </IconTile>
                                        Office Hours & Consultation
                                    </CardTitle>
                                    <CardDescription>Published times students and staff can consult with this instructor.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-muted-foreground text-xs whitespace-pre-line">
                                        {faculty.office_hours || "No consultation hours currently listed for this faculty member."}
                                    </p>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Tab 2: Teaching Load & Assignments */}
                    <TabsContent value="load" className="mt-4 flex flex-col gap-4">
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_380px]">
                            {/* Current Classes Table */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <IconTile variant="soft" size="xs" className="text-primary">
                                                    <BookOpen />
                                                </IconTile>
                                                Active Term Teaching Load
                                            </CardTitle>
                                            <CardDescription>Classes assigned to this faculty member for the active period.</CardDescription>
                                        </div>
                                        <Badge variant="outline" size="sm" radius="full" className="font-mono tabular-nums">
                                            {faculty.current_classes.length} class(es)
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {faculty.current_classes.length === 0 ? (
                                        <div className="text-muted-foreground py-8 text-center text-xs">
                                            No classes assigned in the active term. Use the Assignment Planner on the right to add classes.
                                        </div>
                                    ) : (
                                        <div className="rounded-md border">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>Code</TableHead>
                                                        <TableHead>Subject Title</TableHead>
                                                        <TableHead>Sec</TableHead>
                                                        <TableHead>Schedule</TableHead>
                                                        <TableHead className="text-right">Enrolled</TableHead>
                                                        <TableHead className="text-right">Actions</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {faculty.current_classes.map((c) => (
                                                        <TableRow key={c.id}>
                                                            <TableCell className="text-xs font-semibold">{c.subject_code}</TableCell>
                                                            <TableCell className="max-w-48 truncate text-xs">{c.subject_title || "-"}</TableCell>
                                                            <TableCell className="font-mono text-xs">{c.section}</TableCell>
                                                            <TableCell className="text-xs">
                                                                {c.schedule ? (
                                                                    <div className="flex flex-col gap-0.5">
                                                                        {Object.entries(c.schedule).flatMap(([d, slots]) =>
                                                                            slots.map((s, idx) => (
                                                                                <div
                                                                                    key={`${d}-${idx}`}
                                                                                    className="text-muted-foreground text-[11px]"
                                                                                >
                                                                                    <span className="text-foreground font-medium uppercase">
                                                                                        {d.slice(0, 3)}:
                                                                                    </span>{" "}
                                                                                    {s.time_range} ({s.room?.name || "TBA"})
                                                                                </div>
                                                                            )),
                                                                        )}
                                                                    </div>
                                                                ) : (
                                                                    <span className="text-muted-foreground text-xs">TBA</span>
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="text-right text-xs font-medium tabular-nums">
                                                                {c.student_count ?? "-"}
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon-xs"
                                                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive h-7 w-7"
                                                                    onClick={() =>
                                                                        setUnassignModal({
                                                                            open: true,
                                                                            classId: c.id,
                                                                            title: `${c.subject_code} - Sec ${c.section}`,
                                                                        })
                                                                    }
                                                                    title="Unassign Class"
                                                                >
                                                                    <Trash2 className="h-3.5 w-3.5" />
                                                                </Button>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Class Assignment Planner */}
                            <Card className="h-fit">
                                <CardHeader className="bg-muted/10 border-b pb-3">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Plus />
                                        </IconTile>
                                        Assignment Planner
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        Select and assign open academic period classes to this faculty.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3 pt-3">
                                    <div className="relative">
                                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-3.5 w-3.5" />
                                        <Input
                                            placeholder="Search classes by code/section..."
                                            value={assignmentSearch}
                                            onChange={(e) => setAssignmentSearch(e.target.value)}
                                            className="h-8 pl-8 text-xs"
                                        />
                                    </div>

                                    <div className="flex max-h-[360px] flex-col gap-2 overflow-y-auto pr-1">
                                        {filteredPlannerClasses.length === 0 ? (
                                            <div className="text-muted-foreground py-6 text-center text-xs">
                                                No available classes matching search.
                                            </div>
                                        ) : (
                                            filteredPlannerClasses.map((item) => {
                                                const isSelected = assignmentForm.data.class_ids.includes(item.id);
                                                const isAssignedHere = item.assignment_status === "assigned_here";

                                                return (
                                                    <div
                                                        key={item.id}
                                                        onClick={() => !isAssignedHere && toggleAssignmentClass(item.id)}
                                                        className={cn(
                                                            "flex cursor-pointer flex-col gap-1.5 rounded-md border p-2.5 text-xs transition-colors",
                                                            isSelected ? "border-primary bg-primary/5 ring-primary/40 ring-1" : "hover:bg-muted/40",
                                                            isAssignedHere && "bg-muted/20 cursor-default opacity-50",
                                                        )}
                                                    >
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div>
                                                                <div className="font-semibold">{item.label}</div>
                                                                <div className="text-muted-foreground line-clamp-1 text-[11px]">
                                                                    {item.subject_title || "No title"}
                                                                </div>
                                                            </div>
                                                            <Checkbox checked={isSelected || isAssignedHere} disabled={isAssignedHere} />
                                                        </div>

                                                        {item.assigned_faculty && item.assignment_status === "assigned_elsewhere" ? (
                                                            <Badge variant="warning-light" size="xs" radius="full">
                                                                Assigned to {item.assigned_faculty.name}
                                                            </Badge>
                                                        ) : null}

                                                        {item.warnings.length > 0 ? (
                                                            <div className="flex flex-col gap-0.5">
                                                                {item.warnings.map((w, idx) => (
                                                                    <div key={idx} className="text-destructive flex items-center gap-1 text-[10px]">
                                                                        <AlertTriangle className="h-2.5 w-2.5 shrink-0" />
                                                                        <span>{w.message}</span>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                );
                                            })
                                        )}
                                    </div>

                                    {/* Warnings summary */}
                                    {selectedWarnings.length > 0 ? (
                                        <Alert variant="warning" className="p-2.5 text-xs">
                                            <AlertTriangle />
                                            <AlertTitle className="text-xs">Warnings on selection</AlertTitle>
                                            <AlertDescription className="text-[11px]">
                                                {selectedWarnings.slice(0, 3).map((w, idx) => (
                                                    <div key={idx}>
                                                        &bull; {w.classLabel}: {w.message}
                                                    </div>
                                                ))}
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}

                                    {selectedHasReassignment ? (
                                        <div className="text-warning-foreground bg-warning/10 border-warning/20 flex items-center gap-2 rounded border p-2 text-xs">
                                            <Checkbox
                                                checked={assignmentForm.data.allow_reassignment}
                                                onCheckedChange={(c) => assignmentForm.setData("allow_reassignment", c === true)}
                                                id="confirm-reassign"
                                            />
                                            <label htmlFor="confirm-reassign" className="cursor-pointer font-medium">
                                                Confirm reassignment from another instructor
                                            </label>
                                        </div>
                                    ) : null}

                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                        <Checkbox
                                            checked={assignmentForm.data.notify_faculty}
                                            onCheckedChange={(c) => assignmentForm.setData("notify_faculty", c === true)}
                                            id="notify-faculty-check"
                                        />
                                        <label htmlFor="notify-faculty-check" className="cursor-pointer">
                                            Notify faculty member via portal
                                        </label>
                                    </div>

                                    <Button
                                        className="w-full text-xs"
                                        onClick={submitAssignments}
                                        disabled={
                                            assignmentForm.processing ||
                                            assignmentForm.data.class_ids.length === 0 ||
                                            (selectedHasReassignment && !assignmentForm.data.allow_reassignment)
                                        }
                                    >
                                        Assign {assignmentForm.data.class_ids.length} Selected Class(es)
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Historical Classes Record */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <IconTile variant="soft" size="xs" className="text-primary">
                                        <History />
                                    </IconTile>
                                    Complete Teaching History
                                </CardTitle>
                                <CardDescription>All historical class records assigned to this faculty across academic terms.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {faculty.classes.length === 0 ? (
                                    <div className="text-muted-foreground py-6 text-center text-xs">No historical class records found.</div>
                                ) : (
                                    <div className="rounded-md border">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>School Year</TableHead>
                                                    <TableHead>Semester</TableHead>
                                                    <TableHead>Subject Code</TableHead>
                                                    <TableHead>Title</TableHead>
                                                    <TableHead>Section</TableHead>
                                                    <TableHead className="text-right">Students</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {faculty.classes.map((cls) => (
                                                    <TableRow key={cls.id}>
                                                        <TableCell className="font-mono text-xs">{cls.school_year}</TableCell>
                                                        <TableCell className="text-xs">
                                                            {cls.semester === 1 ? "1st" : cls.semester === 2 ? "2nd" : "Summer"}
                                                        </TableCell>
                                                        <TableCell className="text-xs font-semibold">{cls.subject_code}</TableCell>
                                                        <TableCell className="max-w-56 truncate text-xs">{cls.subject_title || "-"}</TableCell>
                                                        <TableCell className="font-mono text-xs">{cls.section}</TableCell>
                                                        <TableCell className="text-right text-xs font-medium tabular-nums">
                                                            {cls.student_count ?? "-"}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 3: Portal Account & Security */}
                    <TabsContent value="portal" className="mt-4 flex flex-col gap-4">
                        <div className="grid gap-4 lg:grid-cols-2">
                            {/* Current Portal Account Status */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <KeyRound />
                                        </IconTile>
                                        Linked User Account
                                    </CardTitle>
                                    <CardDescription>Authentication record used to log in to the faculty portal.</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-4">
                                    <div className="bg-muted/10 flex flex-col gap-3 rounded-lg border p-4 text-xs">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-semibold">{faculty.portal_account.label}</span>
                                            <Badge
                                                variant={faculty.portal_account.status === "linked" ? "success-light" : "warning-light"}
                                                size="xs"
                                                radius="full"
                                                className="capitalize"
                                            >
                                                {faculty.portal_account.status}
                                            </Badge>
                                        </div>

                                        <Separator />

                                        <div className="grid gap-2">
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Assigned Role:</span>
                                                <span className="font-semibold">{faculty.portal_account.role_label || "No role"}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Email Verified:</span>
                                                <span>{faculty.portal_account.email_verified_at || "Not verified"}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Last Login:</span>
                                                <span>{faculty.portal_account.last_login_at || "Never logged in"}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="bg-primary/[0.02] text-muted-foreground flex flex-col gap-1 rounded-lg border p-3 text-xs">
                                        <div className="text-foreground flex items-center gap-1.5 font-semibold">
                                            <CheckCircle2 className="text-primary h-3.5 w-3.5" />
                                            Portal Permissions & Access
                                        </div>
                                        <p>
                                            Linked faculty accounts can access class rosters, input preliminary/midterm/final grades, record student
                                            attendance, and view administrative notices.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Provisioning / Repair Form */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <UserCheck />
                                        </IconTile>
                                        Configure Portal Credentials
                                    </CardTitle>
                                    <CardDescription>Provision a new user account or repair link to existing faculty user.</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-4">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="flex flex-col gap-1.5">
                                            <Label>Operation Mode</Label>
                                            <Select
                                                value={portalForm.data.mode}
                                                onValueChange={(val) => portalForm.setData("mode", val as "create" | "repair")}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="create">Create New Account</SelectItem>
                                                    <SelectItem value="repair">Repair Link</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="flex flex-col gap-1.5">
                                            <Label>Faculty Role</Label>
                                            <Select value={portalForm.data.role} onValueChange={(val) => portalForm.setData("role", val)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.faculty_roles.map((r) => (
                                                        <SelectItem key={r.value} value={r.value}>
                                                            {r.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 text-xs">
                                        <Checkbox
                                            checked={portalForm.data.send_reset_link}
                                            onCheckedChange={(c) => portalForm.setData("send_reset_link", c === true)}
                                            id="send-reset-check"
                                        />
                                        <label htmlFor="send-reset-check" className="cursor-pointer">
                                            Send password setup / reset email to {faculty.email}
                                        </label>
                                    </div>

                                    {portalForm.errors.mode ? <p className="text-destructive text-xs">{portalForm.errors.mode}</p> : null}

                                    <Button onClick={submitPortal} disabled={portalForm.processing} className="w-full">
                                        <KeyRound className="mr-1.5 h-4 w-4" /> Save Portal Access
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Tab 4: Communications & Notices with ReUI Timeline */}
                    <TabsContent value="notices" className="mt-4 flex flex-col gap-4">
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_380px]">
                            {/* Sent Notices Stream using ReUI Timeline */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <IconTile variant="soft" size="xs" className="text-primary">
                                                    <Megaphone />
                                                </IconTile>
                                                Recent Portal Notices
                                            </CardTitle>
                                            <CardDescription>Communications sent to this faculty member's portal dashboard.</CardDescription>
                                        </div>
                                        <Badge variant="outline" size="xs" radius="full" className="font-mono tabular-nums">
                                            {faculty.recent_notifications.length} notice(s)
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {faculty.recent_notifications.length === 0 ? (
                                        <div className="text-muted-foreground py-8 text-center text-xs">
                                            No notices sent to this faculty member yet.
                                        </div>
                                    ) : (
                                        <Timeline defaultValue={1} orientation="vertical" className="px-3">
                                            {faculty.recent_notifications.map((n, idx) => (
                                                <TimelineItem key={n.id} step={idx + 1}>
                                                    <TimelineIndicator />
                                                    <TimelineHeader className="flex flex-col gap-1">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <TimelineTitle className="text-sm font-semibold">{n.title}</TimelineTitle>
                                                            <Badge
                                                                variant={
                                                                    n.priority === "urgent" || n.priority === "high"
                                                                        ? "destructive-light"
                                                                        : "invert-light"
                                                                }
                                                                size="xs"
                                                                radius="full"
                                                                className="capitalize"
                                                            >
                                                                {n.priority}
                                                            </Badge>
                                                        </div>
                                                        <TimelineDate className="text-muted-foreground text-[11px]">
                                                            Sent: {n.created_at || "Recently"} &bull; {n.read_at ? "Read" : "Unread"}
                                                        </TimelineDate>
                                                    </TimelineHeader>
                                                    <TimelineContent className="text-muted-foreground mt-1 text-xs whitespace-pre-line">
                                                        {n.message}
                                                    </TimelineContent>
                                                    <TimelineSeparator />
                                                </TimelineItem>
                                            ))}
                                        </Timeline>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Compose Notice Card */}
                            <Card className="h-fit">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Send />
                                        </IconTile>
                                        Send Admin Notice
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        Broadcasts an in-app portal notification to this instructor.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="notice-title">Notice Title</Label>
                                        <Input
                                            id="notice-title"
                                            placeholder="e.g. Grade Submission Deadline Reminder"
                                            value={noticeForm.data.title}
                                            onChange={(e) => noticeForm.setData("title", e.target.value)}
                                            className={cn(noticeForm.errors.title && "border-destructive")}
                                        />
                                        {noticeForm.errors.title ? <p className="text-destructive text-xs">{noticeForm.errors.title}</p> : null}
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="notice-msg">Message Body</Label>
                                        <Textarea
                                            id="notice-msg"
                                            placeholder="Write message content..."
                                            rows={4}
                                            value={noticeForm.data.message}
                                            onChange={(e) => noticeForm.setData("message", e.target.value)}
                                            className={cn(noticeForm.errors.message && "border-destructive")}
                                        />
                                        {noticeForm.errors.message ? <p className="text-destructive text-xs">{noticeForm.errors.message}</p> : null}
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="flex flex-col gap-1.5">
                                            <Label>Priority</Label>
                                            <Select value={noticeForm.data.priority} onValueChange={(val) => noticeForm.setData("priority", val)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.notice_priorities.map((p) => (
                                                        <SelectItem key={p.value} value={p.value}>
                                                            {p.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor="notice-url">Action URL</Label>
                                            <Input
                                                id="notice-url"
                                                placeholder="/grades"
                                                value={noticeForm.data.action_url}
                                                onChange={(e) => noticeForm.setData("action_url", e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <Button onClick={submitNotice} disabled={noticeForm.processing} className="w-full text-xs">
                                        <Send className="mr-1.5 h-3.5 w-3.5" /> Dispatch Notice
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Tab 5: Deadlines & Tasks */}
                    <TabsContent value="deadlines" className="mt-4 flex flex-col gap-4">
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_380px]">
                            {/* Active Deadlines Grid */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <IconTile variant="soft" size="xs" className="text-primary">
                                                    <CalendarClock />
                                                </IconTile>
                                                Active Deadlines & Milestones
                                            </CardTitle>
                                            <CardDescription>Academic submissions and departmental responsibilities.</CardDescription>
                                        </div>
                                        <Badge variant="outline" size="xs" radius="full" className="font-mono tabular-nums">
                                            {faculty.deadlines.length} task(s)
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {faculty.deadlines.length === 0 ? (
                                        <div className="text-muted-foreground py-8 text-center text-xs">
                                            No active deadlines registered for this faculty member.
                                        </div>
                                    ) : (
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {faculty.deadlines.map((d) => (
                                                <div key={d.id} className="bg-muted/10 flex flex-col gap-2 rounded-lg border p-3.5 text-xs">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <span className="text-foreground text-sm font-semibold">{d.title}</span>
                                                        <Badge
                                                            variant={
                                                                d.priority === "high"
                                                                    ? "destructive-light"
                                                                    : d.priority === "medium"
                                                                      ? "warning-light"
                                                                      : "invert-light"
                                                            }
                                                            size="xs"
                                                            radius="full"
                                                            className="capitalize"
                                                        >
                                                            {d.priority}
                                                        </Badge>
                                                    </div>

                                                    <p className="text-muted-foreground">{d.description || "No additional description."}</p>

                                                    <div className="text-muted-foreground flex items-center justify-between border-t pt-2 text-[11px]">
                                                        <span className="flex items-center gap-1">
                                                            <Calendar className="h-3 w-3" />
                                                            {d.due_date || "No due date"}
                                                        </span>
                                                        {d.class_label ? (
                                                            <span className="bg-muted rounded px-1.5 py-0.5 font-mono text-[10px]">
                                                                {d.class_label}
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Create Deadline Form */}
                            <Card className="h-fit">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Plus />
                                        </IconTile>
                                        Assign Deadline
                                    </CardTitle>
                                    <CardDescription className="text-xs">Record a milestone or due date for this instructor.</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="deadline-title">Deadline Title</Label>
                                        <Input
                                            id="deadline-title"
                                            placeholder="e.g. Midterm Grades Submission"
                                            value={deadlineForm.data.title}
                                            onChange={(e) => deadlineForm.setData("title", e.target.value)}
                                            className={cn(deadlineForm.errors.title && "border-destructive")}
                                        />
                                        {deadlineForm.errors.title ? <p className="text-destructive text-xs">{deadlineForm.errors.title}</p> : null}
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="deadline-desc">Description</Label>
                                        <Textarea
                                            id="deadline-desc"
                                            placeholder="Specific instructions or requirements..."
                                            rows={3}
                                            value={deadlineForm.data.description}
                                            onChange={(e) => deadlineForm.setData("description", e.target.value)}
                                        />
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor="deadline-due">Due Date</Label>
                                            <Input
                                                id="deadline-due"
                                                type="datetime-local"
                                                value={deadlineForm.data.due_date}
                                                onChange={(e) => deadlineForm.setData("due_date", e.target.value)}
                                                className={cn(deadlineForm.errors.due_date && "border-destructive")}
                                            />
                                            {deadlineForm.errors.due_date ? (
                                                <p className="text-destructive text-xs">{deadlineForm.errors.due_date}</p>
                                            ) : null}
                                        </div>

                                        <div className="flex flex-col gap-1.5">
                                            <Label>Priority</Label>
                                            <Select value={deadlineForm.data.priority} onValueChange={(val) => deadlineForm.setData("priority", val)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.deadline_priorities.map((p) => (
                                                        <SelectItem key={p.value} value={p.value}>
                                                            {p.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label>Related Class (Optional)</Label>
                                        <Select value={deadlineForm.data.class_id} onValueChange={(val) => deadlineForm.setData("class_id", val)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">No Class Association</SelectItem>
                                                {faculty.current_classes.map((cls) => (
                                                    <SelectItem key={cls.id} value={String(cls.id)}>
                                                        {cls.subject_code} - Sec {cls.section}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <Button onClick={submitDeadline} disabled={deadlineForm.processing} className="w-full text-xs">
                                        <CalendarClock className="mr-1.5 h-3.5 w-3.5" /> Save Deadline
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Tab 6: Complete Dossier */}
                    <TabsContent value="dossier" className="mt-4 flex flex-col gap-4">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <GraduationCap />
                                        </IconTile>
                                        Educational Background
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-muted-foreground text-xs whitespace-pre-line">
                                        {faculty.education || "No educational degrees recorded."}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <BookOpen />
                                        </IconTile>
                                        Courses & Specializations
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-muted-foreground text-xs whitespace-pre-line">
                                        {faculty.courses_taught || "No courses or specializations specified."}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Clock />
                                        </IconTile>
                                        Office Hours Policy
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-muted-foreground text-xs whitespace-pre-line">
                                        {faculty.office_hours || "No office hours specified."}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card className="sm:col-span-2">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <FileText />
                                        </IconTile>
                                        Faculty Biography
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-muted-foreground text-xs whitespace-pre-line">
                                        {faculty.biography || "No biography provided for this faculty member."}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Calendar />
                                        </IconTile>
                                        Personal Records
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-2 text-xs">
                                    <div className="flex justify-between border-b py-1">
                                        <span className="text-muted-foreground">Birth Date:</span>
                                        <span className="font-medium">{faculty.birth_date || "Not recorded"}</span>
                                    </div>
                                    <div className="flex justify-between border-b py-1">
                                        <span className="text-muted-foreground">Age:</span>
                                        <span className="font-medium tabular-nums">{faculty.age ? `${faculty.age} years old` : "Not recorded"}</span>
                                    </div>
                                    <div className="flex justify-between border-b py-1">
                                        <span className="text-muted-foreground">Gender:</span>
                                        <span className="font-medium capitalize">{faculty.gender || "Not specified"}</span>
                                    </div>
                                    <div className="flex justify-between border-b py-1">
                                        <span className="text-muted-foreground">Date Employed:</span>
                                        <span className="font-medium">{faculty.date_employed || "Not recorded"}</span>
                                    </div>
                                    <div className="pt-1">
                                        <span className="text-muted-foreground mb-0.5 block">Address:</span>
                                        <span className="text-foreground">{faculty.address_line1 || "No address on file"}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Accessible Radix Alert Dialog for Delete Faculty */}
            <AlertDialog open={deleteModalOpen} onOpenChange={setDeleteModalOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Faculty Member</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to permanently delete <strong>{faculty.name}</strong>? This action will remove their faculty
                            profile, unassign any assigned teaching classes, and revoke portal access. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDeleteFaculty}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            <Trash2 className="mr-1.5 h-4 w-4" /> Delete Faculty
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Accessible Radix Alert Dialog for Unassign Class */}
            <AlertDialog open={unassignModal.open} onOpenChange={(open) => !open && setUnassignModal({ open: false, classId: 0, title: "" })}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Unassign Class</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to unassign <strong>{unassignModal.title}</strong> from {faculty.name}? The class will become
                            unassigned and available for reassignment to another faculty member.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmUnassign}>Unassign Class</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
