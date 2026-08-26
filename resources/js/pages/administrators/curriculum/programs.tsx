import {
    index as curriculumIndex,
    destroyProgram,
    destroyPrograms,
    programDeletionImpact,
    showProgram,
    storeProgram,
    toggleProgramStatus,
    updateProgram,
} from "@/actions/App/Http/Controllers/AdministratorCurriculumManagementController";
import AdminLayout from "@/components/administrators/admin-layout";
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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTab } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    type ColumnDef,
    type PaginationState,
    type SortingState,
    type VisibilityState,
    flexRender,
    getCoreRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from "@tanstack/react-table";
import {
    ArrowDownUp,
    ArrowRight,
    ArrowUpRight,
    BookOpen,
    CalendarDays,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Columns3,
    Ellipsis,
    Filter,
    GraduationCap,
    Layers3,
    ListFilter,
    Pencil,
    Plus,
    Search,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Trash2,
    Waypoints,
    X,
} from "lucide-react";
import { type FormEvent, useEffect, useMemo, useState } from "react";

interface DepartmentOption {
    id: number;
    name: string;
    code: string;
}

interface CurriculumProgramsProps {
    user: User;
    stats: {
        programs: number;
        active_programs: number;
        subjects: number;
        subjects_with_requisites: number;
        curriculum_versions: number;
    };
    programs: ProgramSummary[];
    departments: DepartmentOption[];
    course_types: { id: number; name: string }[];
    versions: CurriculumVersion[];
    school: { id: number; name: string; school_level: string | null } | null;
    capabilities: CurriculumCapability[];
    catalog_templates: CatalogTemplate[];
    shs_pathways: ShsPathway[];
}

type CurriculumCapability = {
    id: string;
    school_level: string;
    school_level_label: string;
    curriculum_framework: string;
    framework_label: string;
    reference: string;
    is_derived: boolean;
};

type CatalogTemplate = {
    framework: string;
    label: string;
    kind: string;
    code: string;
    title: string;
    stage: string | null;
    qualification_level: string | null;
    duration_hours: number | null;
    tesda_program_type: string | null;
    duration_years: number | null;
    internship_hours: number | null;
    bundled_qualifications: string[] | null;
    advanced_topics: string | null;
    reference: string;
};

type ShsPathway = { id: number; title: string; strands_count: number; subjects_count: number };

type ProgramSummary = {
    id: number;
    code: string;
    title: string;
    department: string | null;
    department_id: number | null;
    department_name: string | null;
    course_type_id: number | null;
    course_type_name: string | null;
    curriculum_year: string | null;
    subjects_count: number;
    total_units: number;
    prerequisites_count: number;
    is_active: boolean;
    updated_at: string | null;
    curriculum_kind: string;
    curriculum_stage: string | null;
    curriculum_framework: string | null;
    qualification_level: string | null;
    duration_hours: number | null;
    tesda_program_type: string | null;
    duration_years: number | null;
    internship_hours: number | null;
    bundled_qualifications: string[] | null;
    advanced_topics: string | null;
};

type CurriculumVersion = {
    curriculum_year: string;
    program_count: number;
    active_program_count: number;
    subject_count: number;
};

type StatusFilter = "all" | "active" | "inactive";
type CreateStep = "identity" | "details";
type DeletionSeverity = "safe" | "warning" | "destructive" | "blocked";

type ProgramDeletionRecord = {
    key: string;
    label: string;
    count: number;
    severity: DeletionSeverity;
    blocks: boolean;
    effect: string;
};

type ProgramDeletionImpact = {
    id: number;
    code: string;
    title: string;
    can_delete: boolean;
    has_blockers: boolean;
    has_destructive_changes: boolean;
    records: ProgramDeletionRecord[];
    totals: {
        subjects: number;
        subject_enrollments: number;
        classes: number;
        students: number;
        enrollments: number;
        pending_enrollments: number;
        policies: number;
        research_papers: number;
    };
};

type ProgramDeletionResponse = {
    programs: ProgramDeletionImpact[];
    can_delete: boolean;
    requires_confirmation: boolean;
    totals: ProgramDeletionImpact["totals"];
};

type ProgramEditData = {
    code: string;
    title: string;
    department_id: string;
    course_type_id: string;
    curriculum_year: string;
    curriculum_kind: string;
    curriculum_stage: string;
    duration_hours: string;
    qualification_level: string;
    catalog_reference: string;
    tesda_program_type: string;
    duration_years: string;
    internship_hours: string;
    bundled_qualifications: string;
    advanced_topics: string;
};

const FieldError = ({ message }: { message?: string }) => (message ? <p className="text-destructive mt-1 text-xs font-medium">{message}</p> : null);

function capabilityKind(schoolLevel?: string): string {
    switch (schoolLevel) {
        case "technical_vocational":
            return "tesda_qualification";
        case "elementary":
        case "junior_high":
            return "grade_pathway";
        case "senior_high":
            return "senior_high_pathway";
        default:
            return "program";
    }
}

function kindLabel(program: Pick<ProgramSummary, "curriculum_kind" | "curriculum_stage" | "qualification_level" | "tesda_program_type">): string {
    if (program.curriculum_kind === "tesda_qualification") {
        return program.tesda_program_type === "diploma" ? "Institutional diploma" : "TESDA qualification";
    }

    if (program.curriculum_kind === "grade_pathway") return program.curriculum_stage || "Grade pathway";
    if (program.curriculum_kind === "senior_high_pathway") return program.curriculum_stage || "Senior High pathway";
    if (program.curriculum_kind === "program") return "Academic program";

    return program.qualification_level || "Legacy curriculum";
}

function frameworkLabel(value: string | null): string {
    if (!value) return "Local curriculum";

    return value
        .split("_")
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
}

function departmentColor(code: string | null): string {
    const palette = [
        "bg-sky-500/10 text-sky-700 dark:text-sky-300",
        "bg-violet-500/10 text-violet-700 dark:text-violet-300",
        "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
        "bg-amber-500/10 text-amber-700 dark:text-amber-300",
        "bg-rose-500/10 text-rose-700 dark:text-rose-300",
        "bg-cyan-500/10 text-cyan-700 dark:text-cyan-300",
    ];

    if (!code) return "bg-muted text-muted-foreground";

    return palette[code.charCodeAt(0) % palette.length];
}

export default function CurriculumPrograms({
    user,
    stats,
    programs,
    departments,
    course_types,
    versions,
    school,
    capabilities,
    catalog_templates,
    shs_pathways,
}: CurriculumProgramsProps) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [createStep, setCreateStep] = useState<CreateStep>("identity");
    const [search, setSearch] = useState("");
    const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
    const [activeDepartment, setActiveDepartment] = useState<string>(() => {
        if (typeof window === "undefined") return "all";

        return new URLSearchParams(window.location.search).get("department") ?? "all";
    });
    const [activeYear, setActiveYear] = useState<string>(() => {
        if (typeof window === "undefined") return "all";

        return new URLSearchParams(window.location.search).get("year") ?? "all";
    });
    const [activeFramework, setActiveFramework] = useState("all");
    const [sorting, setSorting] = useState<SortingState>([]);
    const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 10 });
    const [rowSelection, setRowSelection] = useState<Record<string, boolean>>({});
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({});
    const [editingProgram, setEditingProgram] = useState<ProgramSummary | null>(null);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [deletionImpact, setDeletionImpact] = useState<ProgramDeletionResponse | null>(null);
    const [deletionLoading, setDeletionLoading] = useState(false);
    const [deletionSubmitting, setDeletionSubmitting] = useState(false);
    const [deletionError, setDeletionError] = useState<string | null>(null);
    const [deletionConfirmation, setDeletionConfirmation] = useState("");
    const isCurriculumRoot =
        typeof window !== "undefined" && window.location.pathname.replace(/\/$/, "") === curriculumIndex().url.replace(/\/$/, "");

    const createForm = useForm({
        code: "",
        title: "",
        description: "",
        department_id: "",
        course_type_id: "",
        curriculum_year: "",
        lec_per_unit: "",
        lab_per_unit: "",
        miscelaneous: "",
        remarks: "",
        capability_id: capabilities[0]?.id ?? "",
        curriculum_kind: capabilityKind(capabilities[0]?.school_level),
        curriculum_stage: "",
        duration_hours: "",
        qualification_level: "",
        catalog_reference: "",
        tesda_program_type: "national_certificate",
        duration_years: "",
        internship_hours: "",
        bundled_qualifications: "",
        advanced_topics: "",
    });

    const editForm = useForm<ProgramEditData>({
        code: "",
        title: "",
        department_id: "",
        course_type_id: "",
        curriculum_year: "",
        curriculum_kind: "legacy",
        curriculum_stage: "",
        duration_hours: "",
        qualification_level: "",
        catalog_reference: "",
        tesda_program_type: "national_certificate",
        duration_years: "",
        internship_hours: "",
        bundled_qualifications: "",
        advanced_topics: "",
    });

    const selectedCapability = capabilities.find((capability) => capability.id === createForm.data.capability_id) ?? null;
    const compatibleTemplates = catalog_templates.filter((template) => template.framework === selectedCapability?.curriculum_framework);

    const departmentsWithPrograms = useMemo(() => {
        const ids = new Set(programs.map((program) => program.department_id).filter((id): id is number => id !== null));

        return departments.filter((department) => ids.has(department.id));
    }, [departments, programs]);

    const frameworkOptions = useMemo(() => {
        const labels = new Map(capabilities.map((capability) => [capability.curriculum_framework, capability.framework_label]));
        const values = programs.reduce<string[]>((result, program) => {
            if (program.curriculum_framework && !result.includes(program.curriculum_framework)) result.push(program.curriculum_framework);

            return result;
        }, []);

        return values.map((value) => ({ value, label: labels.get(value) ?? frameworkLabel(value) }));
    }, [capabilities, programs]);

    const filteredPrograms = useMemo(() => {
        const query = search.trim().toLowerCase();

        return programs.filter((program) => {
            const matchesQuery =
                query === "" ||
                [program.code, program.title, program.department, program.department_name, program.curriculum_year, program.course_type_name]
                    .filter(Boolean)
                    .join(" ")
                    .toLowerCase()
                    .includes(query);
            const matchesStatus = statusFilter === "all" || (statusFilter === "active" ? program.is_active : !program.is_active);
            const matchesDepartment = activeDepartment === "all" || String(program.department_id) === activeDepartment;
            const matchesYear = activeYear === "all" || program.curriculum_year === activeYear;
            const matchesFramework = activeFramework === "all" || program.curriculum_framework === activeFramework;

            return matchesQuery && matchesStatus && matchesDepartment && matchesYear && matchesFramework;
        });
    }, [activeDepartment, activeFramework, activeYear, programs, search, statusFilter]);

    const filteredStats = useMemo(() => {
        const isUnfiltered =
            search.trim() === "" && statusFilter === "all" && activeDepartment === "all" && activeYear === "all" && activeFramework === "all";

        if (isUnfiltered) return stats;

        return {
            programs: filteredPrograms.length,
            active_programs: filteredPrograms.filter((program) => program.is_active).length,
            subjects: filteredPrograms.reduce((sum, program) => sum + program.subjects_count, 0),
            subjects_with_requisites: filteredPrograms.reduce((sum, program) => sum + program.prerequisites_count, 0),
            curriculum_versions: new Set(filteredPrograms.map((program) => program.curriculum_year).filter(Boolean)).size,
        };
    }, [activeDepartment, activeFramework, activeYear, filteredPrograms, search, stats, statusFilter]);

    const selectCapability = (capabilityId: string): void => {
        const capability = capabilities.find((item) => item.id === capabilityId);

        createForm.setData({
            ...createForm.data,
            capability_id: capabilityId,
            curriculum_kind: capabilityKind(capability?.school_level),
            curriculum_stage: "",
            duration_hours: "",
            qualification_level: "",
            catalog_reference: capability?.reference ?? "",
            tesda_program_type: "national_certificate",
            duration_years: "",
            internship_hours: "",
            bundled_qualifications: "",
            advanced_topics: "",
        });
    };

    const applyTemplate = (code: string): void => {
        const template = compatibleTemplates.find((item) => item.code === code);
        if (!template) return;

        createForm.setData({
            ...createForm.data,
            code: template.code,
            title: template.title,
            curriculum_kind: template.kind,
            curriculum_stage: template.stage ?? "",
            qualification_level: template.qualification_level ?? "",
            duration_hours: template.duration_hours?.toString() ?? "",
            catalog_reference: template.reference,
            tesda_program_type: template.tesda_program_type ?? "national_certificate",
            duration_years: template.duration_years?.toString() ?? "",
            internship_hours: template.internship_hours?.toString() ?? "",
            bundled_qualifications: template.bundled_qualifications?.join(", ") ?? "",
            advanced_topics: template.advanced_topics ?? "",
        });
    };

    useEffect(() => {
        if (activeDepartment !== "all" && isCreateOpen) {
            createForm.setData("department_id", activeDepartment);
        }
    }, [activeDepartment, isCreateOpen]);

    const resetCreateDialog = (): void => {
        createForm.reset();
        createForm.clearErrors();
        setCreateStep("identity");
    };

    const handleCreateProgram = (event: FormEvent): void => {
        event.preventDefault();

        createForm.post(storeProgram().url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                resetCreateDialog();
            },
            onError: (errors) => {
                const identityFields = [
                    "capability_id",
                    "code",
                    "course_type_id",
                    "curriculum_kind",
                    "curriculum_stage",
                    "curriculum_year",
                    "department_id",
                    "title",
                ];

                if (identityFields.some((field) => errors[field])) {
                    setCreateStep("identity");
                }
            },
        });
    };

    const toggleStatus = (program: ProgramSummary): void => {
        router.put(toggleProgramStatus(program.id).url, {}, { preserveScroll: true });
    };

    const openEditProgram = (program: ProgramSummary): void => {
        setEditingProgram(program);
        editForm.clearErrors();
        editForm.setData({
            code: program.code,
            title: program.title,
            department_id: program.department_id?.toString() ?? "",
            course_type_id: program.course_type_id?.toString() ?? "",
            curriculum_year: program.curriculum_year ?? "",
            curriculum_kind: program.curriculum_kind,
            curriculum_stage: program.curriculum_stage ?? "",
            duration_hours: program.duration_hours?.toString() ?? "",
            qualification_level: program.qualification_level ?? "",
            catalog_reference: "",
            tesda_program_type: program.tesda_program_type ?? "national_certificate",
            duration_years: program.duration_years?.toString() ?? "",
            internship_hours: program.internship_hours?.toString() ?? "",
            bundled_qualifications: program.bundled_qualifications?.join(", ") ?? "",
            advanced_topics: program.advanced_topics ?? "",
        });
    };

    const handleEditProgram = (event: FormEvent): void => {
        event.preventDefault();
        if (!editingProgram) return;

        const isDiploma = editForm.data.curriculum_kind === "tesda_qualification" && editForm.data.tesda_program_type === "diploma";
        const payload = {
            code: editForm.data.code,
            title: editForm.data.title,
            department_id: editForm.data.curriculum_kind === "program" ? editForm.data.department_id : null,
            course_type_id: editForm.data.curriculum_kind === "program" ? editForm.data.course_type_id : null,
            curriculum_year: editForm.data.curriculum_year,
            curriculum_kind: editForm.data.curriculum_kind,
            ...(editForm.data.curriculum_kind === "grade_pathway" || editForm.data.curriculum_kind === "senior_high_pathway"
                ? { curriculum_stage: editForm.data.curriculum_stage }
                : {}),
            ...(isDiploma
                ? {
                      duration_hours: editForm.data.duration_hours,
                      qualification_level: editForm.data.qualification_level,
                      tesda_program_type: editForm.data.tesda_program_type,
                      duration_years: editForm.data.duration_years,
                      internship_hours: editForm.data.internship_hours,
                      bundled_qualifications: editForm.data.bundled_qualifications,
                      advanced_topics: editForm.data.advanced_topics,
                  }
                : {}),
        };

        editForm.transform(() => payload);
        editForm.put(updateProgram(editingProgram.id).url, {
            preserveScroll: true,
            onSuccess: () => setEditingProgram(null),
        });
    };

    const openDeletionDialog = async (ids: number[]): Promise<void> => {
        setIsDeleteOpen(true);
        setDeletionLoading(true);
        setDeletionImpact(null);
        setDeletionError(null);
        setDeletionConfirmation("");

        try {
            const query = new URLSearchParams();
            ids.forEach((id) => query.append("ids[]", String(id)));

            const response = await fetch(`${programDeletionImpact().url}?${query.toString()}`, {
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            const payload = (await response.json()) as ProgramDeletionResponse | { message?: string };

            if (!response.ok || !("programs" in payload)) {
                throw new Error("message" in payload && payload.message ? payload.message : "The deletion review could not be completed.");
            }

            setDeletionImpact(payload);
        } catch (error) {
            setDeletionError(error instanceof Error ? error.message : "The deletion review could not be completed.");
        } finally {
            setDeletionLoading(false);
        }
    };

    const confirmDeletion = (): void => {
        if (!deletionImpact || !deletionImpact.can_delete) return;

        const ids = deletionImpact.programs.map((program) => program.id);
        const expectedConfirmation = ids.length === 1 ? deletionImpact.programs[0].code : "DELETE";

        if (deletionConfirmation.trim().toUpperCase() !== expectedConfirmation.toUpperCase()) return;

        setDeletionSubmitting(true);
        const onSuccess = (): void => {
            setIsDeleteOpen(false);
            setDeletionImpact(null);
            setDeletionConfirmation("");
            setRowSelection({});
        };
        const onError = (errors: Record<string, string>): void => {
            setDeletionError(errors.programs ?? errors.confirmation ?? "The deletion was rejected after the final safety check.");
        };
        const options = {
            data: ids.length === 1 ? { confirmation: deletionConfirmation.trim() } : { ids, confirmation: deletionConfirmation.trim() },
            preserveScroll: true,
            onSuccess,
            onError,
            onFinish: () => setDeletionSubmitting(false),
        };

        if (ids.length === 1) {
            router.delete(destroyProgram(ids[0]).url, options);
        } else {
            router.delete(destroyPrograms().url, options);
        }
    };

    const clearFilters = (): void => {
        setSearch("");
        setStatusFilter("all");
        setActiveDepartment("all");
        setActiveYear("all");
        setActiveFramework("all");
    };

    const columns = useMemo<ColumnDef<ProgramSummary>[]>(
        () => [
            {
                id: "select",
                enableHiding: false,
                header: ({ table }) => (
                    <Checkbox
                        aria-label="Select all visible programs"
                        checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate")}
                        onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                    />
                ),
                cell: ({ row }) => (
                    <Checkbox
                        aria-label={`Select ${row.original.code}`}
                        checked={row.getIsSelected()}
                        onCheckedChange={(value) => row.toggleSelected(!!value)}
                    />
                ),
            },
            {
                id: "program",
                accessorFn: (row) => `${row.code} ${row.title}`,
                header: ({ column }) => (
                    <button
                        type="button"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        className="hover:text-foreground inline-flex items-center gap-1.5 transition-colors"
                    >
                        Program <ArrowDownUp className="size-3" />
                    </button>
                ),
                cell: ({ row }) => {
                    const program = row.original;

                    return (
                        <div className="max-w-[340px] min-w-[240px]">
                            <div className="flex flex-wrap items-center gap-2">
                                <Link
                                    href={showProgram(program.id).url}
                                    prefetch
                                    className="hover:text-primary focus-visible:ring-ring/60 truncate rounded-sm font-semibold tracking-[-0.015em] transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    {program.code}
                                </Link>
                                <Badge
                                    variant="secondary"
                                    className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                                        program.is_active
                                            ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                            : "bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                    }`}
                                >
                                    {program.is_active ? "Active" : "Needs review"}
                                </Badge>
                            </div>
                            <Link
                                href={showProgram(program.id).url}
                                className="text-muted-foreground hover:text-foreground focus-visible:ring-ring/60 mt-1 block truncate rounded-sm text-xs transition-colors focus-visible:ring-2 focus-visible:outline-none"
                            >
                                {program.title}
                            </Link>
                        </div>
                    );
                },
            },
            {
                id: "department",
                accessorFn: (row) => row.department ?? "Unassigned",
                header: "Department",
                cell: ({ row }) => (
                    <span className={`rounded-full px-2 py-1 text-xs font-semibold ${departmentColor(row.original.department)}`}>
                        {row.original.department ?? "Unassigned"}
                    </span>
                ),
            },
            {
                id: "curriculum_year",
                accessorKey: "curriculum_year",
                header: ({ column }) => (
                    <button
                        type="button"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        className="hover:text-foreground inline-flex items-center gap-1.5 transition-colors"
                    >
                        Curriculum <ArrowDownUp className="size-3" />
                    </button>
                ),
                cell: ({ row }) => <span className="text-muted-foreground text-xs">{row.original.curriculum_year ?? "Unassigned"}</span>,
            },
            {
                id: "footprint",
                accessorFn: (row) => row.subjects_count,
                header: "Footprint",
                cell: ({ row }) => (
                    <div className="text-muted-foreground grid grid-cols-3 gap-3 text-xs">
                        <span title="Subjects">
                            <strong className="text-foreground block font-semibold">{row.original.subjects_count}</strong> subjects
                        </span>
                        <span title="Units">
                            <strong className="text-foreground block font-semibold">{row.original.total_units}</strong> units
                        </span>
                        <span title="Prerequisites">
                            <strong className="text-foreground block font-semibold">{row.original.prerequisites_count}</strong> prereqs
                        </span>
                    </div>
                ),
            },
            {
                id: "actions",
                enableHiding: false,
                header: () => <span className="sr-only">Actions</span>,
                cell: ({ row }) => {
                    const program = row.original;

                    return (
                        <div className="flex items-center justify-end gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-muted-foreground hover:text-foreground rounded-lg"
                                title={`Edit ${program.code}`}
                                onClick={() => openEditProgram(program)}
                            >
                                <Pencil className="size-4" />
                                <span className="sr-only">Edit {program.code}</span>
                            </Button>
                            <Button asChild variant="secondary" size="sm" className="hidden rounded-lg px-3 xl:inline-flex">
                                <Link href={showProgram(program.id).url}>
                                    Manage <ArrowUpRight className="size-3.5" />
                                </Link>
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="icon" className="text-muted-foreground rounded-lg">
                                        <Ellipsis className="size-4" />
                                        <span className="sr-only">Open actions for {program.code}</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onClick={() => toggleStatus(program)}>
                                        {program.is_active ? "Deactivate program" : "Activate program"}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        className="text-destructive focus:text-destructive"
                                        onClick={() => void openDeletionDialog([program.id])}
                                    >
                                        <Trash2 className="size-4" /> Delete program
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    );
                },
            },
        ],
        [],
    );

    const table = useReactTable({
        data: filteredPrograms,
        columns,
        state: { sorting, pagination, rowSelection, columnVisibility },
        enableRowSelection: true,
        getRowId: (row) => String(row.id),
        onSortingChange: setSorting,
        onPaginationChange: setPagination,
        onRowSelectionChange: setRowSelection,
        onColumnVisibilityChange: setColumnVisibility,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
    });

    const selectedProgramIds = useMemo(() => table.getSelectedRowModel().rows.map((row) => row.original.id), [rowSelection, table]);

    useEffect(() => {
        setPagination((current) => ({ ...current, pageIndex: 0 }));
    }, [activeDepartment, activeFramework, activeYear, search, statusFilter]);

    const activeFilterCount = [statusFilter !== "all", activeDepartment !== "all", activeYear !== "all", activeFramework !== "all"].filter(
        Boolean,
    ).length;
    const readiness = filteredStats.programs > 0 ? Math.round((filteredStats.active_programs / filteredStats.programs) * 100) : 0;
    const deletionRecords = deletionImpact
        ? (deletionImpact.programs[0]?.records ?? []).map((record) => ({
              ...record,
              count: deletionImpact.totals[record.key as keyof ProgramDeletionImpact["totals"]],
          }))
        : [];
    const deletionConfirmationTarget = deletionImpact?.programs.length === 1 ? deletionImpact.programs[0].code : "DELETE";

    return (
        <AdminLayout user={user} title={isCurriculumRoot ? "Curriculum" : "Programs"}>
            <Head title={isCurriculumRoot ? "Curriculum · Programs" : "Programs · Curriculum"} />
            <div className="relative isolate flex flex-col gap-6 pb-4">
                <div className="pointer-events-none absolute -top-28 right-[-11rem] -z-10 size-[27rem] rounded-full bg-sky-400/10 blur-3xl dark:bg-sky-300/5" />

                <header className="border-border/70 relative flex flex-col gap-6 border-b pb-7 lg:flex-row lg:items-end lg:justify-between">
                    <div className="min-w-0 space-y-4">
                        <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs font-medium">
                            {isCurriculumRoot ? (
                                <span>Curriculum</span>
                            ) : (
                                <Link
                                    href={curriculumIndex().url}
                                    className="hover:text-foreground focus-visible:ring-ring/60 rounded-sm transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    Curriculum
                                </Link>
                            )}
                            <span aria-hidden="true">/</span>
                            <span className="text-foreground">Program catalog</span>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="bg-primary/8 text-primary inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.14em] uppercase">
                                <Sparkles className="size-3.5" /> Curriculum workspace
                            </span>
                            {school?.name && <span className="text-muted-foreground text-xs">{school.name}</span>}
                        </div>
                        <div className="space-y-2">
                            <h1 className="text-3xl font-semibold tracking-[-0.04em] text-balance sm:text-4xl">Program catalog</h1>
                            <p className="text-muted-foreground max-w-2xl text-sm leading-6 sm:text-[15px]">
                                Keep every academic pathway, grade progression, and technical qualification ready for curriculum planning and
                                enrollment.
                            </p>
                        </div>
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        {!isCurriculumRoot && (
                            <Button asChild variant="ghost" className="rounded-lg">
                                <Link href={curriculumIndex().url}>
                                    <ChevronLeft className="size-4" /> Curriculum workspace
                                </Link>
                            </Button>
                        )}
                        <Button className="rounded-lg px-4 shadow-sm" onClick={() => setIsCreateOpen(true)}>
                            <Plus className="size-4" /> Add program
                        </Button>
                    </div>
                </header>

                <section className="border-border/80 bg-card overflow-hidden rounded-2xl border shadow-sm" aria-label="Catalog overview">
                    <div className="grid divide-y sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                        <div className="flex items-center justify-between gap-4 px-5 py-4 sm:p-5">
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.12em] uppercase">Programs in view</p>
                                <p className="mt-2 text-2xl font-semibold tracking-[-0.03em]">{filteredStats.programs}</p>
                            </div>
                            <div className="bg-primary/8 text-primary flex size-9 items-center justify-center rounded-xl">
                                <Waypoints className="size-4" />
                            </div>
                        </div>
                        <div className="flex items-center justify-between gap-4 px-5 py-4 sm:p-5">
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.12em] uppercase">Active pathways</p>
                                <p className="mt-2 text-2xl font-semibold tracking-[-0.03em]">{filteredStats.active_programs}</p>
                            </div>
                            <CheckCircle2 className="size-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div className="flex items-center justify-between gap-4 px-5 py-4 sm:p-5">
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.12em] uppercase">Subjects mapped</p>
                                <p className="mt-2 text-2xl font-semibold tracking-[-0.03em]">{filteredStats.subjects}</p>
                            </div>
                            <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                <BookOpen className="size-4" />
                                <span>{filteredStats.subjects_with_requisites} prereq-aware</span>
                            </div>
                        </div>
                        <div className="px-5 py-4 sm:p-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.12em] uppercase">Catalog readiness</p>
                                    <p className="mt-2 text-2xl font-semibold tracking-[-0.03em]">{readiness}%</p>
                                </div>
                                <span className="text-muted-foreground text-xs">
                                    {filteredStats.programs - filteredStats.active_programs} to review
                                </span>
                            </div>
                            <div
                                className="bg-muted mt-3 h-1.5 overflow-hidden rounded-full"
                                role="progressbar"
                                aria-label="Catalog readiness"
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-valuenow={readiness}
                            >
                                <div
                                    className="h-full rounded-full bg-emerald-500 transition-[width] duration-500 ease-out"
                                    style={{ width: `${readiness}%` }}
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_290px]">
                    <section
                        className="border-border/80 bg-card min-w-0 overflow-hidden rounded-2xl border shadow-sm"
                        aria-labelledby="program-catalog-heading"
                    >
                        <div className="border-border/70 border-b p-5 sm:p-6">
                            <div className="flex flex-col gap-5">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h2 id="program-catalog-heading" className="text-xl font-semibold tracking-[-0.025em]">
                                                Program catalog
                                            </h2>
                                            <Badge variant="secondary" className="rounded-full px-2.5" aria-live="polite">
                                                {filteredPrograms.length}
                                            </Badge>
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Search by identity, then open a program to manage its curriculum.
                                        </p>
                                    </div>
                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                        <ListFilter className="size-4" />
                                        <span>{activeFilterCount > 0 ? `${activeFilterCount} filters active` : "All programs"}</span>
                                    </div>
                                </div>

                                <div className="bg-muted/30 border-border/60 grid gap-3 rounded-xl border p-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                                    <div className="relative min-w-0">
                                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2" />
                                        <Input
                                            aria-label="Search programs"
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            placeholder="Search code, name, department, or year…"
                                            className="bg-background h-10 rounded-lg pr-16 pl-10 shadow-none"
                                        />
                                        {search ? (
                                            <button
                                                type="button"
                                                aria-label="Clear search"
                                                onClick={() => setSearch("")}
                                                className="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/60 absolute top-1/2 right-2 flex size-8 -translate-y-1/2 items-center justify-center rounded-md transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                            >
                                                <X className="size-3.5" />
                                            </button>
                                        ) : (
                                            <span className="bg-muted text-muted-foreground pointer-events-none absolute top-1/2 right-2 hidden -translate-y-1/2 rounded border px-1.5 py-0.5 text-[10px] font-medium sm:inline">
                                                ⌘ K
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Select value={activeDepartment} onValueChange={setActiveDepartment}>
                                            <SelectTrigger className="bg-background h-10 min-w-[150px] rounded-lg shadow-none">
                                                <Filter className="text-muted-foreground size-3.5" />
                                                <SelectValue placeholder="Department" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All departments</SelectItem>
                                                {departmentsWithPrograms.map((department) => (
                                                    <SelectItem key={department.id} value={String(department.id)}>
                                                        {department.code} · {department.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Select value={activeYear} onValueChange={setActiveYear}>
                                            <SelectTrigger className="bg-background h-10 min-w-[145px] rounded-lg shadow-none">
                                                <CalendarDays className="text-muted-foreground size-3.5" />
                                                <SelectValue placeholder="Curriculum year" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All curriculum years</SelectItem>
                                                {versions.map((version) => (
                                                    <SelectItem key={version.curriculum_year} value={version.curriculum_year}>
                                                        {version.curriculum_year}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {frameworkOptions.length > 0 && (
                                            <Select value={activeFramework} onValueChange={setActiveFramework}>
                                                <SelectTrigger className="bg-background h-10 min-w-[150px] rounded-lg shadow-none">
                                                    <Layers3 className="text-muted-foreground size-3.5" />
                                                    <SelectValue placeholder="Framework" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All frameworks</SelectItem>
                                                    {frameworkOptions.map((option) => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <div
                                        className="border-border/70 bg-muted/50 inline-flex rounded-lg border p-1"
                                        role="group"
                                        aria-label="Filter by program status"
                                    >
                                        {(["all", "active", "inactive"] as StatusFilter[]).map((status) => {
                                            const label = status === "all" ? "All" : status === "active" ? "Active" : "Needs review";
                                            const count =
                                                status === "all"
                                                    ? programs.length
                                                    : status === "active"
                                                      ? programs.filter((program) => program.is_active).length
                                                      : programs.filter((program) => !program.is_active).length;

                                            return (
                                                <button
                                                    type="button"
                                                    key={status}
                                                    aria-pressed={statusFilter === status}
                                                    onClick={() => setStatusFilter(status)}
                                                    className={`focus-visible:ring-ring/60 cursor-pointer rounded-md px-3 py-1.5 text-xs font-semibold transition-[background-color,color,box-shadow] duration-200 focus-visible:ring-2 focus-visible:outline-none ${
                                                        statusFilter === status
                                                            ? "bg-background text-foreground shadow-sm"
                                                            : "text-muted-foreground hover:text-foreground"
                                                    }`}
                                                >
                                                    {label} <span className="text-muted-foreground ml-1 text-[10px]">{count}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {(search || activeFilterCount > 0) && (
                                        <button
                                            type="button"
                                            onClick={clearFilters}
                                            className="text-muted-foreground hover:text-foreground focus-visible:ring-ring/60 ml-auto cursor-pointer rounded-md px-2 py-1 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                        >
                                            Clear filters
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>

                        {selectedProgramIds.length > 0 && (
                            <div className="border-border/70 bg-primary/[0.04] flex flex-col gap-3 border-b px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div className="flex items-center gap-2 text-sm">
                                    <Badge className="rounded-full">{selectedProgramIds.length}</Badge>
                                    <span className="font-medium">program{selectedProgramIds.length === 1 ? "" : "s"} selected</span>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="rounded-lg"
                                        onClick={() =>
                                            programs
                                                .filter((program) => selectedProgramIds.includes(program.id) && !program.is_active)
                                                .forEach((program) => router.put(toggleProgramStatus(program.id).url, {}, { preserveScroll: true }))
                                        }
                                    >
                                        <CheckCircle2 className="size-3.5" /> Activate
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="rounded-lg"
                                        onClick={() =>
                                            programs
                                                .filter((program) => selectedProgramIds.includes(program.id) && program.is_active)
                                                .forEach((program) => router.put(toggleProgramStatus(program.id).url, {}, { preserveScroll: true }))
                                        }
                                    >
                                        <Clock3 className="size-3.5" /> Deactivate
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        className="rounded-lg"
                                        onClick={() => void openDeletionDialog(selectedProgramIds)}
                                    >
                                        <Trash2 className="size-3.5" /> Delete selected
                                    </Button>
                                </div>
                            </div>
                        )}

                        <div className="flex items-center justify-between gap-3 border-b px-5 py-3 sm:px-6">
                            <p className="text-muted-foreground text-xs">
                                Showing {table.getRowModel().rows.length} of {filteredPrograms.length} programs
                            </p>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button type="button" variant="outline" size="sm" className="rounded-lg">
                                        <Columns3 className="size-3.5" /> Columns
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {table
                                        .getAllLeafColumns()
                                        .filter((column) => column.getCanHide())
                                        .map((column) => (
                                            <DropdownMenuCheckboxItem
                                                key={column.id}
                                                checked={column.getIsVisible()}
                                                onCheckedChange={(value) => column.toggleVisibility(!!value)}
                                            >
                                                {column.id === "curriculum_year"
                                                    ? "Curriculum year"
                                                    : column.id === "footprint"
                                                      ? "Footprint"
                                                      : "Department"}
                                            </DropdownMenuCheckboxItem>
                                        ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <div className="overflow-hidden">
                            {table.getRowModel().rows.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        {table.getHeaderGroups().map((headerGroup) => (
                                            <TableRow key={headerGroup.id}>
                                                {headerGroup.headers.map((header) => (
                                                    <TableHead
                                                        key={header.id}
                                                        className="text-muted-foreground px-4 text-[10px] font-semibold tracking-[0.13em] uppercase"
                                                    >
                                                        {header.isPlaceholder
                                                            ? null
                                                            : flexRender(header.column.columnDef.header, header.getContext())}
                                                    </TableHead>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </TableHeader>
                                    <TableBody>
                                        {table.getRowModel().rows.map((row) => (
                                            <TableRow key={row.id} data-state={row.getIsSelected() ? "selected" : undefined}>
                                                {row.getVisibleCells().map((cell) => (
                                                    <TableCell key={cell.id} className="px-4 py-4">
                                                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <div className="flex flex-col items-center justify-center px-6 py-20 text-center">
                                    <div className="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-2xl">
                                        <Search className="size-5" />
                                    </div>
                                    <h3 className="mt-4 text-sm font-semibold">No programs match these filters</h3>
                                    <p className="text-muted-foreground mt-1 max-w-sm text-sm">Try another search or reset the catalog view.</p>
                                    <Button variant="outline" size="sm" className="mt-5 rounded-lg" onClick={clearFilters}>
                                        Reset catalog view
                                    </Button>
                                </div>
                            )}
                        </div>

                        <div className="border-border/70 flex flex-col gap-3 border-t px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <p className="text-muted-foreground text-xs">
                                Page {table.getState().pagination.pageIndex + 1} of {Math.max(table.getPageCount(), 1)}
                            </p>
                            <div className="flex items-center gap-2">
                                <Select
                                    value={String(table.getState().pagination.pageSize)}
                                    onValueChange={(value) => table.setPageSize(Number(value))}
                                >
                                    <SelectTrigger className="h-9 w-[115px] rounded-lg text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="10">10 / page</SelectItem>
                                        <SelectItem value="25">25 / page</SelectItem>
                                        <SelectItem value="50">50 / page</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="size-9 rounded-lg"
                                    disabled={!table.getCanPreviousPage()}
                                    onClick={() => table.previousPage()}
                                    aria-label="Previous page"
                                >
                                    <ChevronLeft className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="size-9 rounded-lg"
                                    disabled={!table.getCanNextPage()}
                                    onClick={() => table.nextPage()}
                                    aria-label="Next page"
                                >
                                    <ChevronRight className="size-4" />
                                </Button>
                            </div>
                        </div>
                    </section>

                    <aside className="grid gap-4">
                        <section
                            className="border-border/80 bg-card overflow-hidden rounded-2xl border shadow-sm"
                            aria-labelledby="curriculum-versions-heading"
                        >
                            <div className="border-border/70 border-b p-5">
                                <div className="flex items-center justify-between gap-3">
                                    <h2 id="curriculum-versions-heading" className="text-base font-semibold tracking-[-0.015em]">
                                        Curriculum versions
                                    </h2>
                                    <Clock3 className="text-muted-foreground size-4" />
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">Select a year to narrow the catalog.</p>
                            </div>
                            <div className="grid gap-2 p-4">
                                {versions.length > 0 ? (
                                    versions.slice(0, 5).map((version) => (
                                        <button
                                            key={version.curriculum_year}
                                            type="button"
                                            aria-pressed={activeYear === version.curriculum_year}
                                            onClick={() => setActiveYear(activeYear === version.curriculum_year ? "all" : version.curriculum_year)}
                                            className={`border-border/60 bg-background hover:bg-muted/50 focus-visible:ring-ring/60 w-full cursor-pointer rounded-xl border p-3.5 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none ${
                                                activeYear === version.curriculum_year ? "border-primary/40 bg-primary/5" : ""
                                            }`}
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="text-sm font-semibold">{version.curriculum_year}</span>
                                                <span className="text-muted-foreground text-xs">
                                                    {version.active_program_count}/{version.program_count} active
                                                </span>
                                            </div>
                                            <div className="bg-muted mt-2 h-1.5 overflow-hidden rounded-full">
                                                <div
                                                    className="h-full rounded-full bg-sky-600 transition-[width] duration-500 ease-out dark:bg-sky-400"
                                                    style={{
                                                        width: `${version.program_count ? (version.active_program_count / version.program_count) * 100 : 0}%`,
                                                    }}
                                                />
                                            </div>
                                            <p className="text-muted-foreground mt-2 text-xs">{version.subject_count} subjects mapped</p>
                                        </button>
                                    ))
                                ) : (
                                    <p className="bg-muted/50 text-muted-foreground rounded-xl p-4 text-sm">No curriculum versions yet.</p>
                                )}
                            </div>
                        </section>

                        <section className="border-border/80 bg-card overflow-hidden rounded-2xl border shadow-sm" aria-labelledby="workflow-heading">
                            <div className="border-border/70 border-b p-5">
                                <h2 id="workflow-heading" className="text-base font-semibold tracking-[-0.015em]">
                                    Curriculum workflow
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">A simple path from definition to enrollment.</p>
                            </div>
                            <div className="grid gap-4 p-5">
                                {[
                                    ["01", "Define", "Name the pathway and align it to a framework."],
                                    ["02", "Build", "Add subjects, units, sequencing, and prerequisites."],
                                    ["03", "Activate", "Publish only when the curriculum is ready."],
                                ].map(([number, title, description]) => (
                                    <div key={number} className="flex gap-3">
                                        <span className="bg-primary/8 text-primary flex size-7 shrink-0 items-center justify-center rounded-lg text-[10px] font-bold">
                                            {number}
                                        </span>
                                        <div className="space-y-0.5">
                                            <p className="text-sm font-semibold">{title}</p>
                                            <p className="text-muted-foreground text-xs leading-5">{description}</p>
                                        </div>
                                    </div>
                                ))}
                                {shs_pathways.length > 0 && (
                                    <div className="flex items-start gap-2 rounded-xl border border-amber-500/20 bg-amber-500/5 p-3 text-xs leading-5 text-amber-800 dark:text-amber-200">
                                        <BookOpen className="mt-0.5 size-3.5 shrink-0" />
                                        <span>{shs_pathways.length} Senior High pathway records retain their track and strand structure.</span>
                                    </div>
                                )}
                            </div>
                        </section>
                    </aside>
                </div>
            </div>

            <Dialog
                open={isCreateOpen}
                onOpenChange={(open) => {
                    setIsCreateOpen(open);
                    if (!open) resetCreateDialog();
                }}
            >
                <DialogContent className="border-border/80 bg-card/95 max-h-[min(880px,calc(100vh-2rem))] overflow-y-auto rounded-[26px] p-0 shadow-2xl backdrop-blur-2xl sm:max-w-3xl">
                    <div className="border-border/70 bg-muted/25 border-b px-6 py-6 sm:px-8">
                        <DialogHeader className="gap-3 pr-8 text-left">
                            <div className="flex size-11 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-700 dark:text-sky-300">
                                <GraduationCap className="size-5" />
                            </div>
                            <div className="space-y-1">
                                <DialogTitle className="text-2xl tracking-[-0.03em]">Add a program</DialogTitle>
                                <DialogDescription className="max-w-xl leading-5">
                                    Start with the program identity, then add the standards and operational details your registrar needs.
                                </DialogDescription>
                            </div>
                        </DialogHeader>
                    </div>

                    <form className="grid gap-6 px-6 py-6 sm:px-8" onSubmit={handleCreateProgram}>
                        <Tabs value={createStep} onValueChange={(value) => setCreateStep(value as CreateStep)}>
                            <TabsList className="grid w-full grid-cols-2 rounded-xl p-1">
                                <TabsTab value="identity" className="justify-start gap-3 rounded-lg px-3 text-left">
                                    <span className="bg-muted flex size-6 items-center justify-center rounded-full text-[10px] font-bold">1</span>
                                    <span>
                                        <span className="block text-xs font-semibold">Identity</span>
                                        <span className="text-muted-foreground hidden text-[10px] font-normal sm:block">Name and pathway</span>
                                    </span>
                                </TabsTab>
                                <TabsTab value="details" className="justify-start gap-3 rounded-lg px-3 text-left">
                                    <span className="bg-muted flex size-6 items-center justify-center rounded-full text-[10px] font-bold">2</span>
                                    <span>
                                        <span className="block text-xs font-semibold">Standards & details</span>
                                        <span className="text-muted-foreground hidden text-[10px] font-normal sm:block">Complete the record</span>
                                    </span>
                                </TabsTab>
                            </TabsList>

                            <TabsContent value="identity" className="grid gap-5 pt-4">
                                <div className="rounded-2xl border border-sky-500/15 bg-sky-500/[0.04] p-4">
                                    <div className="flex items-start gap-3">
                                        <Sparkles className="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-300" />
                                        <div>
                                            <p className="text-sm font-semibold">Choose the right starting point</p>
                                            <p className="text-muted-foreground mt-1 text-xs leading-5">
                                                A framework keeps program metadata consistent. Use a catalog starter when one is available, or
                                                continue with a custom pathway.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-capability">School capability</Label>
                                        <Select value={createForm.data.capability_id} onValueChange={selectCapability}>
                                            <SelectTrigger id="create-capability" className="rounded-xl">
                                                <SelectValue placeholder="Choose a supported pathway" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {capabilities.map((capability) => (
                                                    <SelectItem key={capability.id} value={capability.id}>
                                                        {capability.school_level_label} · {capability.framework_label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {!capabilities.length && (
                                            <p className="text-muted-foreground text-xs">
                                                No framework is configured yet. You can still create a custom pathway.
                                            </p>
                                        )}
                                        <FieldError message={createForm.errors.capability_id} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-template">
                                            Catalog starter <span className="text-muted-foreground font-normal">(optional)</span>
                                        </Label>
                                        <Select onValueChange={applyTemplate} disabled={!selectedCapability || compatibleTemplates.length === 0}>
                                            <SelectTrigger id="create-template" className="rounded-xl">
                                                <SelectValue placeholder={selectedCapability ? "Choose a template" : "Choose a capability first"} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {compatibleTemplates.map((template) => (
                                                    <SelectItem key={template.code} value={template.code}>
                                                        {template.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                {selectedCapability && (
                                    <div className="flex flex-wrap items-center gap-2 text-xs">
                                        <Badge variant="secondary" className="rounded-full">
                                            {selectedCapability.school_level_label}
                                        </Badge>
                                        <Badge variant="outline" className="rounded-full">
                                            {selectedCapability.framework_label}
                                        </Badge>
                                        {school && <span className="text-muted-foreground">Adding to {school.name}</span>}
                                    </div>
                                )}

                                <div className="grid gap-4 md:grid-cols-[0.65fr_1.35fr]">
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-code">Program code</Label>
                                        <Input
                                            id="create-code"
                                            className="rounded-xl uppercase"
                                            placeholder="e.g. BSIT"
                                            value={createForm.data.code}
                                            onChange={(event) => createForm.setData("code", event.target.value.toUpperCase())}
                                        />
                                        <FieldError message={createForm.errors.code} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-title">Program title</Label>
                                        <Input
                                            id="create-title"
                                            className="rounded-xl"
                                            placeholder="e.g. Bachelor of Science in Information Technology"
                                            value={createForm.data.title}
                                            onChange={(event) => createForm.setData("title", event.target.value)}
                                        />
                                        <FieldError message={createForm.errors.title} />
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    {createForm.data.curriculum_kind === "program" && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-department">Department</Label>
                                            <Select
                                                value={createForm.data.department_id}
                                                onValueChange={(value) => createForm.setData("department_id", value)}
                                            >
                                                <SelectTrigger id="create-department" className="rounded-xl">
                                                    <SelectValue placeholder="Select department" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {departments.map((department) => (
                                                        <SelectItem key={department.id} value={String(department.id)}>
                                                            {department.code} · {department.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <FieldError message={createForm.errors.department_id} />
                                        </div>
                                    )}
                                    {createForm.data.curriculum_kind === "program" && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-course-type">Program type</Label>
                                            <Select
                                                value={createForm.data.course_type_id}
                                                onValueChange={(value) => createForm.setData("course_type_id", value)}
                                            >
                                                <SelectTrigger id="create-course-type" className="rounded-xl">
                                                    <SelectValue placeholder="Select program type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {course_types.map((type) => (
                                                        <SelectItem key={type.id} value={String(type.id)}>
                                                            {type.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <FieldError message={createForm.errors.course_type_id} />
                                        </div>
                                    )}
                                </div>

                                {(createForm.data.curriculum_kind === "grade_pathway" ||
                                    createForm.data.curriculum_kind === "senior_high_pathway") && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-stage">Grade level / stage</Label>
                                        <Input
                                            id="create-stage"
                                            className="rounded-xl"
                                            placeholder="e.g. Grade 1, Grade 7, Grade 11"
                                            value={createForm.data.curriculum_stage}
                                            onChange={(event) => createForm.setData("curriculum_stage", event.target.value)}
                                        />
                                        <FieldError message={createForm.errors.curriculum_stage} />
                                    </div>
                                )}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="create-year">Curriculum year</Label>
                                        <Input
                                            id="create-year"
                                            className="rounded-xl"
                                            placeholder="e.g. 2024-2025"
                                            value={createForm.data.curriculum_year}
                                            onChange={(event) => createForm.setData("curriculum_year", event.target.value)}
                                        />
                                        <FieldError message={createForm.errors.curriculum_year} />
                                    </div>
                                    {createForm.data.curriculum_kind === "program" && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-misc">
                                                Miscellaneous fee <span className="text-muted-foreground font-normal">(₱)</span>
                                            </Label>
                                            <Input
                                                id="create-misc"
                                                type="number"
                                                min="0"
                                                className="rounded-xl"
                                                placeholder="e.g. 3500"
                                                value={createForm.data.miscelaneous}
                                                onChange={(event) => createForm.setData("miscelaneous", event.target.value)}
                                            />
                                            <FieldError message={createForm.errors.miscelaneous} />
                                        </div>
                                    )}
                                </div>
                            </TabsContent>

                            <TabsContent value="details" className="grid gap-5 pt-4">
                                {createForm.data.curriculum_kind === "tesda_qualification" && (
                                    <div className="grid gap-4 rounded-2xl border border-amber-500/20 bg-amber-500/[0.04] p-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-tesda-type">TESDA program type</Label>
                                            <Select
                                                value={createForm.data.tesda_program_type}
                                                onValueChange={(value) => createForm.setData("tesda_program_type", value)}
                                            >
                                                <SelectTrigger id="create-tesda-type" className="rounded-xl">
                                                    <SelectValue placeholder="Select program type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="national_certificate">National Certificate (NC)</SelectItem>
                                                    <SelectItem value="diploma">Institutional Diploma</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <FieldError message={createForm.errors.tesda_program_type} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-qualification">Qualification level</Label>
                                            <Input
                                                id="create-qualification"
                                                className="rounded-xl"
                                                placeholder="e.g. NC II or Diploma"
                                                value={createForm.data.qualification_level}
                                                onChange={(event) => createForm.setData("qualification_level", event.target.value)}
                                            />
                                            <FieldError message={createForm.errors.qualification_level} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-hours">Total program hours</Label>
                                            <Input
                                                id="create-hours"
                                                type="number"
                                                min="1"
                                                className="rounded-xl"
                                                value={createForm.data.duration_hours}
                                                onChange={(event) => createForm.setData("duration_hours", event.target.value)}
                                            />
                                            <FieldError message={createForm.errors.duration_hours} />
                                        </div>
                                        {createForm.data.tesda_program_type === "diploma" && (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="create-duration-years">
                                                        Typical duration <span className="text-muted-foreground font-normal">(years)</span>
                                                    </Label>
                                                    <Input
                                                        id="create-duration-years"
                                                        type="number"
                                                        min="0.5"
                                                        max="10"
                                                        step="0.5"
                                                        className="rounded-xl"
                                                        placeholder="e.g. 1.5"
                                                        value={createForm.data.duration_years}
                                                        onChange={(event) => createForm.setData("duration_years", event.target.value)}
                                                    />
                                                    <FieldError message={createForm.errors.duration_years} />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="create-internship-hours">OJT / internship hours</Label>
                                                    <Input
                                                        id="create-internship-hours"
                                                        type="number"
                                                        min="0"
                                                        max="65535"
                                                        className="rounded-xl"
                                                        placeholder="e.g. 600"
                                                        value={createForm.data.internship_hours}
                                                        onChange={(event) => createForm.setData("internship_hours", event.target.value)}
                                                    />
                                                    <FieldError message={createForm.errors.internship_hours} />
                                                </div>
                                                <div className="grid gap-2 md:col-span-2">
                                                    <Label htmlFor="create-bundled">Bundled qualifications</Label>
                                                    <Input
                                                        id="create-bundled"
                                                        className="rounded-xl"
                                                        placeholder="Separate each qualification with a comma"
                                                        value={createForm.data.bundled_qualifications}
                                                        onChange={(event) => createForm.setData("bundled_qualifications", event.target.value)}
                                                    />
                                                    <FieldError message={createForm.errors.bundled_qualifications} />
                                                </div>
                                                <div className="grid gap-2 md:col-span-2">
                                                    <Label htmlFor="create-advanced">Advanced topics and internship context</Label>
                                                    <Textarea
                                                        id="create-advanced"
                                                        className="min-h-20 resize-none rounded-xl"
                                                        placeholder="Describe the advanced topics, operations, or internship context."
                                                        value={createForm.data.advanced_topics}
                                                        onChange={(event) => createForm.setData("advanced_topics", event.target.value)}
                                                    />
                                                    <FieldError message={createForm.errors.advanced_topics} />
                                                </div>
                                            </>
                                        )}
                                    </div>
                                )}

                                {createForm.data.curriculum_kind === "program" && (
                                    <div className="border-border/70 bg-muted/20 grid gap-4 rounded-2xl border p-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-lec">Lecture rate / unit</Label>
                                            <Input
                                                id="create-lec"
                                                type="number"
                                                min="0"
                                                className="rounded-xl"
                                                value={createForm.data.lec_per_unit}
                                                onChange={(event) => createForm.setData("lec_per_unit", event.target.value)}
                                            />
                                            <FieldError message={createForm.errors.lec_per_unit} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-lab">Lab rate / unit</Label>
                                            <Input
                                                id="create-lab"
                                                type="number"
                                                min="0"
                                                className="rounded-xl"
                                                value={createForm.data.lab_per_unit}
                                                onChange={(event) => createForm.setData("lab_per_unit", event.target.value)}
                                            />
                                            <FieldError message={createForm.errors.lab_per_unit} />
                                        </div>
                                    </div>
                                )}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="create-description">Description</Label>
                                        <Textarea
                                            id="create-description"
                                            className="min-h-24 resize-none rounded-xl"
                                            placeholder="Give staff a concise description of this program and its intended outcome."
                                            value={createForm.data.description}
                                            onChange={(event) => createForm.setData("description", event.target.value)}
                                        />
                                        <FieldError message={createForm.errors.description} />
                                    </div>
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="create-remarks">
                                            Internal remarks <span className="text-muted-foreground font-normal">(optional)</span>
                                        </Label>
                                        <Textarea
                                            id="create-remarks"
                                            className="min-h-20 resize-none rounded-xl"
                                            placeholder="Add internal notes for curriculum administrators."
                                            value={createForm.data.remarks}
                                            onChange={(event) => createForm.setData("remarks", event.target.value)}
                                        />
                                        <FieldError message={createForm.errors.remarks} />
                                    </div>
                                </div>
                            </TabsContent>
                        </Tabs>

                        <DialogFooter className="border-border/70 border-t pt-5 sm:justify-between">
                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                <span className="flex size-5 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-300">
                                    <Check className="size-3" />
                                </span>
                                New programs are active by default; deactivate them when they need review.
                            </div>
                            <div className="flex flex-col-reverse gap-2 sm:flex-row">
                                {createStep === "details" && (
                                    <Button type="button" variant="ghost" className="rounded-full" onClick={() => setCreateStep("identity")}>
                                        <ChevronLeft className="size-4" /> Back
                                    </Button>
                                )}
                                {createStep === "identity" ? (
                                    <Button type="button" className="rounded-full px-4" onClick={() => setCreateStep("details")}>
                                        Continue <ArrowRight className="size-4" />
                                    </Button>
                                ) : (
                                    <Button type="submit" className="rounded-full px-4" disabled={createForm.processing}>
                                        {createForm.processing ? "Creating…" : "Create program"} <ArrowUpRight className="size-4" />
                                    </Button>
                                )}
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingProgram !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingProgram(null);
                        editForm.clearErrors();
                    }
                }}
            >
                <DialogContent className="border-border/80 bg-card/95 max-h-[min(720px,calc(100vh-2rem))] overflow-y-auto rounded-[26px] p-0 shadow-2xl backdrop-blur-2xl sm:max-w-2xl">
                    <div className="border-border/70 bg-muted/25 border-b px-6 py-6 sm:px-8">
                        <DialogHeader className="gap-3 pr-8 text-left">
                            <div className="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-2xl">
                                <Pencil className="size-5" />
                            </div>
                            <div className="space-y-1">
                                <DialogTitle className="text-2xl tracking-[-0.03em]">Quick edit program</DialogTitle>
                                <DialogDescription>
                                    Update the catalog identity here. Use Manage for subjects, prerequisites, fees, and complete pathway metadata.
                                </DialogDescription>
                            </div>
                        </DialogHeader>
                    </div>

                    <form className="grid gap-5 px-6 py-6 sm:px-8" onSubmit={handleEditProgram}>
                        <div className="grid gap-4 md:grid-cols-[0.65fr_1.35fr]">
                            <div className="grid gap-2">
                                <Label htmlFor="edit-code">Program code</Label>
                                <Input
                                    id="edit-code"
                                    className="rounded-xl uppercase"
                                    value={editForm.data.code}
                                    onChange={(event) => editForm.setData("code", event.target.value.toUpperCase())}
                                />
                                <FieldError message={editForm.errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-title">Program title</Label>
                                <Input
                                    id="edit-title"
                                    className="rounded-xl"
                                    value={editForm.data.title}
                                    onChange={(event) => editForm.setData("title", event.target.value)}
                                />
                                <FieldError message={editForm.errors.title} />
                            </div>
                        </div>

                        {editForm.data.curriculum_kind === "program" && (
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-department">Department</Label>
                                    <Select value={editForm.data.department_id} onValueChange={(value) => editForm.setData("department_id", value)}>
                                        <SelectTrigger id="edit-department" className="rounded-xl">
                                            <SelectValue placeholder="Select department" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {departments.map((department) => (
                                                <SelectItem key={department.id} value={String(department.id)}>
                                                    {department.code} · {department.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FieldError message={editForm.errors.department_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-course-type">Program type</Label>
                                    <Select value={editForm.data.course_type_id} onValueChange={(value) => editForm.setData("course_type_id", value)}>
                                        <SelectTrigger id="edit-course-type" className="rounded-xl">
                                            <SelectValue placeholder="Select program type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {course_types.map((type) => (
                                                <SelectItem key={type.id} value={String(type.id)}>
                                                    {type.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FieldError message={editForm.errors.course_type_id} />
                                </div>
                            </div>
                        )}

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="edit-year">Curriculum year</Label>
                                <Input
                                    id="edit-year"
                                    className="rounded-xl"
                                    value={editForm.data.curriculum_year}
                                    onChange={(event) => editForm.setData("curriculum_year", event.target.value)}
                                />
                                <FieldError message={editForm.errors.curriculum_year} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Curriculum profile</Label>
                                <div className="bg-muted/40 flex min-h-10 items-center rounded-xl border px-3">
                                    <Badge variant="secondary" className="rounded-full">
                                        {editingProgram ? kindLabel(editingProgram) : "Curriculum profile"}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        {editingProgram?.curriculum_kind === "tesda_qualification" && (
                            <div className="grid gap-3 rounded-2xl border border-amber-500/20 bg-amber-500/[0.04] p-4 text-sm">
                                <div className="flex items-start gap-2">
                                    <GraduationCap className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-300" />
                                    <p className="text-muted-foreground leading-5">
                                        This is a technical qualification. Its hours, bundled qualifications, and advanced topics remain unchanged in
                                        quick edit; open Manage for full metadata editing.
                                    </p>
                                </div>
                            </div>
                        )}

                        <DialogFooter className="border-border/70 border-t pt-5 sm:justify-between">
                            <Button type="button" variant="ghost" className="rounded-full" onClick={() => setEditingProgram(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" className="rounded-full px-4" disabled={editForm.processing}>
                                {editForm.processing ? "Saving…" : "Save changes"} <Check className="size-4" />
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog
                open={isDeleteOpen}
                onOpenChange={(open) => {
                    setIsDeleteOpen(open);
                    if (!open && !deletionSubmitting) {
                        setDeletionImpact(null);
                        setDeletionError(null);
                        setDeletionConfirmation("");
                    }
                }}
            >
                <AlertDialogContent className="border-border/80 max-h-[min(760px,calc(100vh-2rem))] overflow-y-auto rounded-[26px] sm:max-w-2xl">
                    <AlertDialogHeader className="text-left">
                        <div
                            className={`flex size-11 items-center justify-center rounded-2xl ${deletionImpact?.can_delete ? "bg-destructive/10 text-destructive" : "bg-amber-500/10 text-amber-700 dark:text-amber-300"}`}
                        >
                            {deletionImpact?.can_delete ? <Trash2 className="size-5" /> : <ShieldAlert className="size-5" />}
                        </div>
                        <AlertDialogTitle className="text-2xl tracking-[-0.03em]">Review deletion impact</AlertDialogTitle>
                        <AlertDialogDescription>
                            The server analyzes related records before every deletion. This preview is informational; the final check runs again when
                            you confirm.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    {deletionLoading && <div className="bg-muted/40 text-muted-foreground rounded-2xl p-5 text-sm">Analyzing related records…</div>}

                    {deletionError && (
                        <div role="alert" className="border-destructive/30 bg-destructive/5 text-destructive rounded-2xl border p-4 text-sm">
                            {deletionError}
                        </div>
                    )}

                    {deletionImpact && !deletionLoading && (
                        <div className="grid gap-4">
                            <div
                                className={`rounded-2xl border p-4 ${deletionImpact.can_delete ? "border-destructive/20 bg-destructive/[0.04]" : "border-amber-500/25 bg-amber-500/[0.05]"}`}
                            >
                                <div className="flex items-start gap-3">
                                    {deletionImpact.can_delete ? (
                                        <ShieldAlert className="text-destructive mt-0.5 size-5 shrink-0" />
                                    ) : (
                                        <ShieldCheck className="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300" />
                                    )}
                                    <div className="space-y-1">
                                        <p className="font-semibold">
                                            {deletionImpact.can_delete
                                                ? "Deletion is allowed after confirmation"
                                                : "Deletion blocked by related records"}
                                        </p>
                                        <p className="text-muted-foreground text-sm leading-5">
                                            {deletionImpact.programs.length === 1
                                                ? `${deletionImpact.programs[0].code} · ${deletionImpact.programs[0].title}`
                                                : `${deletionImpact.programs.length} programs selected. Review the combined impact below.`}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                {deletionRecords.map((record) => {
                                    const isSafe = record.count === 0;
                                    const isBlocked = record.blocks && record.count > 0;
                                    const isDestructive = record.severity === "destructive" && record.count > 0;

                                    return (
                                        <div key={record.key} className="border-border/70 bg-muted/20 flex items-start gap-3 rounded-xl border p-3">
                                            <div
                                                className={`mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg ${isSafe ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" : isBlocked ? "bg-amber-500/10 text-amber-700 dark:text-amber-300" : "bg-destructive/10 text-destructive"}`}
                                            >
                                                {isSafe ? <ShieldCheck className="size-4" /> : <ShieldAlert className="size-4" />}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <p className="text-sm font-semibold">{record.label}</p>
                                                    <Badge variant="outline" className="rounded-full">
                                                        {record.count}
                                                    </Badge>
                                                </div>
                                                <p className="text-muted-foreground mt-1 text-xs leading-5">{record.effect}</p>
                                                {isDestructive && <p className="text-destructive mt-1 text-xs font-semibold">Permanent deletion</p>}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {deletionImpact.can_delete && (
                                <div className="grid gap-2">
                                    <Label htmlFor="delete-confirmation">Type {deletionConfirmationTarget} to confirm</Label>
                                    <Input
                                        id="delete-confirmation"
                                        value={deletionConfirmation}
                                        onChange={(event) => setDeletionConfirmation(event.target.value)}
                                        placeholder={deletionConfirmationTarget}
                                        autoComplete="off"
                                        className="rounded-xl font-mono uppercase"
                                        aria-describedby="delete-confirmation-help"
                                    />
                                    <p id="delete-confirmation-help" className="text-muted-foreground text-xs leading-5">
                                        This action is permanent. Subjects will be removed with the program; nullable policy and research links will
                                        be cleared.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deletionSubmitting}>Cancel</AlertDialogCancel>
                        {deletionImpact?.can_delete && (
                            <AlertDialogAction
                                disabled={
                                    deletionLoading ||
                                    deletionSubmitting ||
                                    deletionConfirmation.trim().toUpperCase() !== deletionConfirmationTarget.toUpperCase()
                                }
                                onClick={(event) => {
                                    event.preventDefault();
                                    confirmDeletion();
                                }}
                                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            >
                                {deletionSubmitting ? "Deleting…" : "Delete permanently"}
                            </AlertDialogAction>
                        )}
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
