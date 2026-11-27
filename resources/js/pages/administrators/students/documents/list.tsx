import AdminLayout from "@/components/administrators/admin-layout";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { AdminLink } from "@/lib/admin-navigation";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    ExternalLink,
    FileCheck2,
    FileWarning,
    FolderOpen,
    Grid3X3,
    Loader2,
    Mail,
    PanelLeft,
    RotateCcw,
    Search,
    ShieldOff,
    UploadCloud,
    Users,
    X,
    XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { getInitials } from "../columns";
import { DocumentPreviewViewer } from "../components/document-preview-viewer";
import {
    DOCUMENT_REJECTION_PRESETS,
    documentStatusMeta,
    type DocumentDossier,
    type DocumentMatrixStudent,
    type DocumentSlot,
    type IdentifierPolicy,
    type MatrixColumn,
    type MatrixColumnStat,
    type MatrixSlotCell,
    type SchoolProfile,
    type StudentConfiguration,
} from "../shared-types";

interface DocumentsListPageProps {
    students: {
        data: DocumentMatrixStudent[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    matrix_columns: MatrixColumn[];
    column_stats: MatrixColumnStat[];
    kpis: {
        total_students: number;
        fully_compliant: number;
        pending_review: number;
        missing_required: number;
        rejected_count: number;
    };
    filters: {
        search: string | null;
        compliance: "all" | "pending_review" | "missing" | "rejected" | "complete";
        missing_doc: string | null;
        student_type: string | null;
        course_id: number | null;
        year_level: number | null;
        sort: string;
        direction: "asc" | "desc";
        per_page: number;
        view: "matrix" | "workbench";
    };
    options: {
        student_configuration: StudentConfiguration;
        identifier_policy: IdentifierPolicy;
        school_profile: SchoolProfile;
        types: Array<{ value: string; label: string }>;
        courses: Array<{ value: string; label: string }>;
        year_levels: Array<{ value: string; label: string }>;
    };
}

export default function AdministratorStudentDocumentsHub({
    students,
    matrix_columns,
    column_stats,
    kpis,
    filters: serverFilters,
    options,
}: DocumentsListPageProps) {
    const { auth } = usePage<{ auth: { user: User } }>().props;
    const schoolProfile = options.school_profile;
    const idLabel = options.identifier_policy?.display_label || "ID";

    const [viewMode, setViewMode] = useState<"matrix" | "workbench">(serverFilters.view || "matrix");
    const [search, setSearch] = useState(serverFilters.search ?? "");
    const [compliance, setCompliance] = useState(serverFilters.compliance || "all");
    const [missingDoc, setMissingDoc] = useState(serverFilters.missing_doc ?? "");
    const [studentType, setStudentType] = useState(serverFilters.student_type ?? "");
    const [courseId, setCourseId] = useState(serverFilters.course_id ? String(serverFilters.course_id) : "");
    const [yearLevel, setYearLevel] = useState(serverFilters.year_level ? String(serverFilters.year_level) : "");
    const [sort, setSort] = useState(serverFilters.sort || "name");
    const [direction, setDirection] = useState<"asc" | "desc">(serverFilters.direction || "asc");
    const [perPage, setPerPage] = useState(serverFilters.per_page || 15);
    const [loading, setLoading] = useState(false);

    // Matrix bulk selection
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [reminding, setReminding] = useState(false);

    // Anchored cell quick-action dialog (Matrix Mode)
    const [activeCell, setActiveCell] = useState<{
        student: DocumentMatrixStudent;
        cell: MatrixSlotCell;
    } | null>(null);
    const [cellBusy, setCellBusy] = useState(false);
    const [rejectReason, setRejectReason] = useState<string>(DOCUMENT_REJECTION_PRESETS[0]);
    const [rejectMode, setRejectMode] = useState(false);
    const [waiveMode, setWaiveMode] = useState(false);
    const [waiveReason, setWaiveReason] = useState("");
    const cellFileInputRef = useRef<HTMLInputElement | null>(null);

    // Split-Pane Workbench state
    const [selectedStudentId, setSelectedStudentId] = useState<number | null>(students.data[0]?.id ?? null);
    const [workbenchDossierState, setWorkbenchDossierState] = useState<{
        studentId: number;
        dossier: DocumentDossier;
    } | null>(null);
    const [workbenchLoading, setWorkbenchLoading] = useState(false);
    const [activeSlotKey, setActiveSlotKey] = useState<string | null>(null);
    const [workbenchRejectOpen, setWorkbenchRejectOpen] = useState(false);
    const [workbenchRejectReason, setWorkbenchRejectReason] = useState<string>(DOCUMENT_REJECTION_PRESETS[0]);
    const workbenchFileRef = useRef<HTMLInputElement | null>(null);

    const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const selectedStudentIdRef = useRef<number | null>(selectedStudentId);
    selectedStudentIdRef.current = selectedStudentId;
    const dossierRequestSeqRef = useRef(0);
    const dossierAbortRef = useRef<AbortController | null>(null);

    const clearSearchDebounce = useCallback(() => {
        if (searchDebounceRef.current) {
            clearTimeout(searchDebounceRef.current);
            searchDebounceRef.current = null;
        }
    }, []);

    useEffect(() => {
        return () => clearSearchDebounce();
    }, [clearSearchDebounce]);

    useEffect(() => {
        setSelectedIds([]);
    }, [students.data]);

    useEffect(() => {
        if (students.data.length === 0) {
            if (selectedStudentId !== null) {
                setSelectedStudentId(null);
            }
            return;
        }
        if (!students.data.some((s) => s.id === selectedStudentId)) {
            setSelectedStudentId(students.data[0].id);
        }
    }, [students.data, selectedStudentId]);

    const navigateHub = useCallback(
        (overrides?: {
            search?: string;
            compliance?: string;
            missing_doc?: string;
            student_type?: string;
            course_id?: string;
            year_level?: string;
            sort?: string;
            direction?: "asc" | "desc";
            per_page?: number;
            page?: number;
            view?: "matrix" | "workbench";
        }) => {
            clearSearchDebounce();

            const nextSearch = overrides?.search ?? search;
            const nextCompliance = overrides?.compliance ?? compliance;
            const nextMissingDoc = overrides?.missing_doc ?? missingDoc;
            const nextType = overrides?.student_type ?? studentType;
            const nextCourse = overrides?.course_id ?? courseId;
            const nextLevel = overrides?.year_level ?? yearLevel;
            const nextSort = overrides?.sort ?? sort;
            const nextDir = overrides?.direction ?? direction;
            const nextPerPage = overrides?.per_page ?? perPage;
            const nextPage = overrides?.page ?? 1;
            const nextView = overrides?.view ?? viewMode;

            const params: Record<string, string | number> = {
                compliance: nextCompliance,
                sort: nextSort,
                direction: nextDir,
                per_page: nextPerPage,
                page: nextPage,
                view: nextView,
            };

            if (nextSearch.trim() !== "") params.search = nextSearch.trim();
            if (nextMissingDoc !== "") params.missing_doc = nextMissingDoc;
            if (nextType !== "") params.student_type = nextType;
            if (nextCourse !== "") params.course_id = nextCourse;
            if (nextLevel !== "") params.year_level = nextLevel;

            setLoading(true);
            router.get("/administrators/students/documents", params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onFinish: () => setLoading(false),
            });
        },
        [clearSearchDebounce, search, compliance, missingDoc, studentType, courseId, yearLevel, sort, direction, perPage, viewMode],
    );

    const navigateHubRef = useRef(navigateHub);
    useEffect(() => {
        navigateHubRef.current = navigateHub;
    }, [navigateHub]);

    // Load dossier for active Split-Pane Workbench student
    const fetchWorkbenchDossier = useCallback(async (studentId: number) => {
        dossierAbortRef.current?.abort();
        const controller = new AbortController();
        dossierAbortRef.current = controller;
        const requestId = ++dossierRequestSeqRef.current;

        setWorkbenchDossierState(null);
        setActiveSlotKey(null);
        setWorkbenchRejectOpen(false);
        setWorkbenchRejectReason(DOCUMENT_REJECTION_PRESETS[0]);
        setWorkbenchLoading(true);

        try {
            const res = await axios.get(`/administrators/students/${studentId}/documents/dossier`, {
                headers: { Accept: "application/json" },
                signal: controller.signal,
            });
            if (
                controller.signal.aborted ||
                requestId !== dossierRequestSeqRef.current ||
                selectedStudentIdRef.current !== studentId
            ) {
                return;
            }
            const nextDossier = res.data?.dossier as DocumentDossier | null;
            if (nextDossier) {
                setWorkbenchDossierState({ studentId, dossier: nextDossier });
                if (nextDossier.slots.length > 0) {
                    const prioritySlot =
                        nextDossier.slots.find((s) => s.status === "pending_review") ??
                        nextDossier.slots.find((s) => s.has_file) ??
                        nextDossier.slots[0];
                    setActiveSlotKey(prioritySlot.key);
                } else {
                    setActiveSlotKey(null);
                }
            } else {
                setWorkbenchDossierState(null);
                setActiveSlotKey(null);
            }
        } catch (error: unknown) {
            if (
                axios.isCancel(error) ||
                controller.signal.aborted ||
                requestId !== dossierRequestSeqRef.current ||
                selectedStudentIdRef.current !== studentId
            ) {
                return;
            }
            setWorkbenchDossierState(null);
            setActiveSlotKey(null);
            toast.error("Failed to load student document dossier.");
        } finally {
            if (requestId === dossierRequestSeqRef.current) {
                setWorkbenchLoading(false);
            }
        }
    }, []);

    useEffect(() => {
        if (viewMode === "workbench" && selectedStudentId !== null) {
            void fetchWorkbenchDossier(selectedStudentId);
        } else {
            dossierAbortRef.current?.abort();
            dossierRequestSeqRef.current += 1;
            setWorkbenchDossierState(null);
            setActiveSlotKey(null);
            setWorkbenchRejectOpen(false);
            setWorkbenchRejectReason(DOCUMENT_REJECTION_PRESETS[0]);
            setWorkbenchLoading(false);
        }

        return () => {
            dossierAbortRef.current?.abort();
        };
    }, [viewMode, selectedStudentId, fetchWorkbenchDossier]);

    const openMatrixCell = (student: DocumentMatrixStudent, cell: MatrixSlotCell) => {
        const isSameDocument = activeCell?.student.id === student.id && activeCell?.cell.key === cell.key;
        if (!isSameDocument) {
            setRejectMode(false);
            setWaiveMode(false);
            setRejectReason(DOCUMENT_REJECTION_PRESETS[0]);
            setWaiveReason("");
        }
        setActiveCell({ student, cell });
    };

    const closeMatrixCell = () => {
        setActiveCell(null);
        setRejectMode(false);
        setWaiveMode(false);
        setRejectReason(DOCUMENT_REJECTION_PRESETS[0]);
        setWaiveReason("");
    };

    const selectWorkbenchSlot = (slotKey: string) => {
        if (slotKey !== activeSlotKey) {
            setWorkbenchRejectOpen(false);
            setWorkbenchRejectReason(DOCUMENT_REJECTION_PRESETS[0]);
        }
        setActiveSlotKey(slotKey);
    };

    // Keyboard stepping in Workbench mode (Alt+ArrowRight / Alt+ArrowLeft)
    const selectedStudentIdx = useMemo(
        () => students.data.findIndex((s) => s.id === selectedStudentId),
        [students.data, selectedStudentId],
    );

    useEffect(() => {
        if (viewMode !== "workbench") return;
        const onKeyDown = (e: KeyboardEvent) => {
            if (!e.altKey) return;
            if (e.key === "ArrowRight" && selectedStudentIdx >= 0 && selectedStudentIdx < students.data.length - 1) {
                e.preventDefault();
                setSelectedStudentId(students.data[selectedStudentIdx + 1].id);
            } else if (e.key === "ArrowLeft" && selectedStudentIdx > 0) {
                e.preventDefault();
                setSelectedStudentId(students.data[selectedStudentIdx - 1].id);
            }
        };
        window.addEventListener("keydown", onKeyDown);
        return () => window.removeEventListener("keydown", onKeyDown);
    }, [viewMode, selectedStudentIdx, students.data]);

    const handleReviewSlot = async (
        studentId: number,
        docKey: string,
        action: "verify" | "reject" | "physical" | "waive" | "reset",
        reason?: string,
        expectedFileRevision?: string | null,
    ) => {
        setCellBusy(true);
        try {
            const res = await axios.post(
                `/administrators/students/${studentId}/documents/fixed/${docKey}/review`,
                {
                    action,
                    reason: reason || null,
                    notify_student: true,
                    ...(action === "verify" ? { expected_file_revision: expectedFileRevision ?? undefined } : {}),
                },
                { headers: { Accept: "application/json" } },
            );
            toast.success(res.data?.message || "Document updated.");
            if (res.data?.dossier && selectedStudentIdRef.current === studentId) {
                dossierAbortRef.current?.abort();
                dossierRequestSeqRef.current += 1;
                setWorkbenchLoading(false);
                setWorkbenchDossierState({ studentId, dossier: res.data.dossier });
            }
            closeMatrixCell();
            setWorkbenchRejectOpen(false);
            setWorkbenchRejectReason(DOCUMENT_REJECTION_PRESETS[0]);
            router.reload({ only: ["students", "column_stats", "kpis"] });
        } catch (error: unknown) {
            const err = error as {
                response?: {
                    status?: number;
                    data?: { message?: string; errors?: Record<string, string[]>; dossier?: DocumentDossier };
                };
            };
            const fieldError =
                err.response?.data?.errors?.expected_file_revision?.[0] ??
                (err.response?.data?.errors ? Object.values(err.response.data.errors)[0]?.[0] : null);
            toast.error(fieldError || err.response?.data?.message || "Failed to update document.");
            if (err.response?.status === 422 && err.response?.data?.errors?.expected_file_revision) {
                if (err.response.data.dossier && selectedStudentIdRef.current === studentId) {
                    setWorkbenchDossierState({ studentId, dossier: err.response.data.dossier });
                } else if (viewMode === "workbench" && selectedStudentIdRef.current === studentId) {
                    void fetchWorkbenchDossier(studentId);
                }
                router.reload({ only: ["students", "column_stats", "kpis"] });
            }
        } finally {
            setCellBusy(false);
        }
    };

    const handleUploadSlot = async (studentId: number, docKey: string, file: File) => {
        setCellBusy(true);
        try {
            const formData = new FormData();
            formData.append("document_type", docKey);
            formData.append("file", file);
            formData.append("auto_verify", "1");
            const res = await axios.post(`/administrators/students/${studentId}/documents/fixed`, formData, {
                headers: { Accept: "application/json", "Content-Type": "multipart/form-data" },
            });
            toast.success("Document uploaded and verified.");
            if (res.data?.dossier && selectedStudentIdRef.current === studentId) {
                dossierAbortRef.current?.abort();
                dossierRequestSeqRef.current += 1;
                setWorkbenchLoading(false);
                setWorkbenchDossierState({ studentId, dossier: res.data.dossier });
            }
            closeMatrixCell();
            router.reload({ only: ["students", "column_stats", "kpis"] });
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Upload failed.");
        } finally {
            setCellBusy(false);
        }
    };

    const handleBulkRemind = async (opts?: { studentIds?: number[]; documentKey?: string }) => {
        const batchStudentIds = opts?.studentIds ? [...opts.studentIds] : null;
        const isExplicitSelection = Boolean(batchStudentIds && batchStudentIds.length > 0);
        const isColumnReminder = !isExplicitSelection && Boolean(opts?.documentKey);

        const basePayload: Record<string, unknown> = {};
        if (opts?.documentKey) {
            basePayload.document_key = opts.documentKey;
        }

        if (isExplicitSelection && batchStudentIds) {
            basePayload.student_ids = batchStudentIds;
        } else if (isColumnReminder) {
            if (serverFilters.search) basePayload.search = serverFilters.search;
            if (serverFilters.student_type) basePayload.student_type = serverFilters.student_type;
            if (serverFilters.course_id) basePayload.course_id = serverFilters.course_id;
            if (serverFilters.year_level) basePayload.year_level = serverFilters.year_level;
        }

        setReminding(true);
        let totalSent = 0;
        try {
            if (isColumnReminder) {
                let afterStudentId: number | null = null;
                let lastMessage: string | undefined;
                let pages = 0;
                const maxPages = 100;

                while (pages < maxPages) {
                    pages += 1;
                    const pagePayload: Record<string, unknown> =
                        afterStudentId !== null
                            ? { ...basePayload, after_student_id: afterStudentId }
                            : basePayload;
                    const res = await axios.post("/administrators/students/documents/bulk-remind", pagePayload, {
                        headers: { Accept: "application/json" },
                    });
                    const data = res.data as {
                        message?: string;
                        sent_count?: number;
                        has_more?: boolean;
                        next_after_student_id?: number | null;
                    };
                    totalSent += Number(data?.sent_count ?? 0);
                    lastMessage = data?.message;

                    if (data?.has_more === true && typeof data.next_after_student_id === "number") {
                        if (data.next_after_student_id === afterStudentId) {
                            break;
                        }
                        afterStudentId = data.next_after_student_id;
                    } else {
                        break;
                    }
                }

                if (pages > 1) {
                    toast.success(
                        totalSent > 0
                            ? `Sent missing-document reminders to ${totalSent} student(s).`
                            : "No students required reminders or had reachable contact details.",
                    );
                } else {
                    toast.success(lastMessage || "Reminders sent.");
                }
            } else {
                const res = await axios.post("/administrators/students/documents/bulk-remind", basePayload, {
                    headers: { Accept: "application/json" },
                });
                toast.success(res.data?.message || "Reminders sent.");
                if (isExplicitSelection && batchStudentIds) {
                    const completedSet = new Set(batchStudentIds);
                    setSelectedIds((prev) => prev.filter((id) => !completedSet.has(id)));
                }
            }
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            const baseErr = err.response?.data?.message || "Could not send reminders.";
            toast.error(totalSent > 0 ? `${baseErr} (${totalSent} reminder(s) were already sent.)` : baseErr);
        } finally {
            setReminding(false);
        }
    };

    const columnStatsMap = useMemo(() => {
        const map: Record<string, MatrixColumnStat> = {};
        for (const stat of column_stats) {
            map[stat.key] = stat;
        }
        return map;
    }, [column_stats]);

    const activeWorkbenchStudent = students.data.find((s) => s.id === selectedStudentId) ?? null;
    const workbenchDossier =
        activeWorkbenchStudent !== null && workbenchDossierState?.studentId === activeWorkbenchStudent.id
            ? workbenchDossierState.dossier
            : null;
    const activeWorkbenchSlot: DocumentSlot | null =
        workbenchDossier?.slots.find((s) => s.key === activeSlotKey) ?? workbenchDossier?.slots[0] ?? null;

    const allPageSelected = students.data.length > 0 && selectedIds.length === students.data.length;

    const paginationFooter = (
        <div className="border-border/70 flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-xs">
            <span className="text-muted-foreground">
                {students.total === 0
                    ? "No students"
                    : `Showing ${students.from ?? 1}–${students.to ?? students.data.length} of ${students.total} students`}
            </span>
            <div className="flex items-center gap-3">
                <div className="flex items-center gap-1.5">
                    <span className="text-muted-foreground">Rows:</span>
                    <Select
                        value={String(perPage)}
                        onValueChange={(v) => {
                            const n = Number(v);
                            setPerPage(n);
                            navigateHub({ per_page: n, page: 1 });
                        }}
                    >
                        <SelectTrigger className="h-8 w-16 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[15, 25, 50, 100].map((n) => (
                                <SelectItem key={n} value={String(n)}>
                                    {n}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-8"
                        disabled={students.current_page <= 1}
                        onClick={() => navigateHub({ page: students.current_page - 1 })}
                        aria-label="Previous page"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <span className="px-2 font-mono text-xs tabular-nums">
                        {students.current_page} / {students.last_page}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-8"
                        disabled={students.current_page >= students.last_page}
                        onClick={() => navigateHub({ page: students.current_page + 1 })}
                        aria-label="Next page"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );

    return (
        <AdminLayout user={auth.user} title="Student Documents">
            <Head title="Administrators • Student Documents Hub" />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-5">
                {/* Header */}
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-end">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                Student Records · Documents Hub
                            </span>
                            <Badge variant="secondary" className="text-[10px]">
                                {schoolProfile.school_level_label}
                            </Badge>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Document Compliance &amp; Verification</h1>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Tabs
                            value={viewMode}
                            onValueChange={(v) => {
                                const next = v as "matrix" | "workbench";
                                setViewMode(next);
                                navigateHub({ view: next, page: students.current_page });
                            }}
                        >
                            <TabsList className="h-9">
                                <TabsTrigger value="matrix" className="gap-1.5 text-xs">
                                    <Grid3X3 className="size-3.5" />
                                    Compliance Matrix
                                </TabsTrigger>
                                <TabsTrigger value="workbench" className="gap-1.5 text-xs">
                                    <PanelLeft className="size-3.5" />
                                    Split Reviewer
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>

                        <Button
                            render={<AdminLink href="/administrators/students" />}
                            nativeButton={false}
                            variant="outline"
                            size="sm"
                            className="h-9 gap-1.5 text-xs"
                        >
                            <Users className="size-3.5" />
                            Directory
                        </Button>
                    </div>
                </div>

                {/* Live Compliance KPI Strip (clickable triage filters) */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {[
                        {
                            key: "all",
                            label: "Scoped Students",
                            value: kpis.total_students,
                            icon: <Users className="text-primary size-4" />,
                            accent: "border-primary/40 bg-primary/5",
                        },
                        {
                            key: "complete",
                            label: "Fully Compliant",
                            value: kpis.fully_compliant,
                            icon: <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />,
                            accent: "border-emerald-500/40 bg-emerald-500/5",
                        },
                        {
                            key: "pending_review",
                            label: "Pending Review",
                            value: kpis.pending_review,
                            icon: <Clock className="size-4 text-amber-600 dark:text-amber-400" />,
                            accent: "border-amber-500/40 bg-amber-500/5",
                        },
                        {
                            key: "missing",
                            label: "Missing / Pending / Rejected",
                            value: kpis.missing_required,
                            icon: <FileWarning className="size-4 text-rose-600 dark:text-rose-400" />,
                            accent: "border-rose-500/40 bg-rose-500/5",
                        },
                    ].map((card) => {
                        const isActive = compliance === card.key;
                        return (
                            <button
                                key={card.key}
                                type="button"
                                onClick={() => {
                                    setCompliance(card.key as typeof compliance);
                                    navigateHub({ compliance: card.key, page: 1 });
                                }}
                                className={cn(
                                    "border-border/70 bg-card hover:border-primary/40 flex items-center justify-between rounded-xl border p-3.5 text-left transition-all",
                                    isActive && card.accent,
                                )}
                            >
                                <div>
                                    <p className="text-muted-foreground text-[11px] font-medium">{card.label}</p>
                                    <p className="mt-1 font-mono text-2xl font-semibold tabular-nums">{card.value}</p>
                                </div>
                                <div className="bg-muted/50 flex size-9 items-center justify-center rounded-lg">{card.icon}</div>
                            </button>
                        );
                    })}
                </div>

                {/* Filter & Search Toolbar */}
                <Card className="border-border/70">
                    <CardContent className="flex flex-wrap items-center gap-2.5 p-3.5">
                        <div className="relative min-w-[220px] flex-1">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2" />
                            <Input
                                value={search}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    setSearch(val);
                                    clearSearchDebounce();
                                    searchDebounceRef.current = setTimeout(() => {
                                        searchDebounceRef.current = null;
                                        navigateHubRef.current({ search: val, page: 1 });
                                    }, 280);
                                }}
                                placeholder={`Search student name, ${idLabel}, LRN, email...`}
                                className="h-9 pr-8 pl-8 text-xs"
                            />
                            {search && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        clearSearchDebounce();
                                        setSearch("");
                                        navigateHub({ search: "", page: 1 });
                                    }}
                                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                                    aria-label="Clear search"
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </div>

                        {options.types.length > 1 && (
                            <Select
                                value={studentType || "__all__"}
                                onValueChange={(v) => {
                                    const next = v === "__all__" ? "" : v;
                                    setStudentType(next);
                                    navigateHub({ student_type: next, page: 1 });
                                }}
                            >
                                <SelectTrigger className="h-9 w-[150px] text-xs">
                                    <SelectValue placeholder="All Categories" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all__">All Categories</SelectItem>
                                    {options.types.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}

                        <Select
                            value={courseId || "__all__"}
                            onValueChange={(v) => {
                                const next = v === "__all__" ? "" : v;
                                setCourseId(next);
                                navigateHub({ course_id: next, page: 1 });
                            }}
                        >
                            <SelectTrigger className="h-9 w-[170px] text-xs">
                                <SelectValue placeholder="All Programs" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">All Programs</SelectItem>
                                {options.courses.map((c) => (
                                    <SelectItem key={c.value} value={c.value}>
                                        {c.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={missingDoc || "__all__"}
                            onValueChange={(v) => {
                                const next = v === "__all__" ? "" : v;
                                setMissingDoc(next);
                                navigateHub({ missing_doc: next, page: 1 });
                            }}
                        >
                            <SelectTrigger className="h-9 w-[175px] text-xs">
                                <SelectValue placeholder="Missing specific doc" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">Any Document Slot</SelectItem>
                                {matrix_columns.map((col) => (
                                    <SelectItem key={col.key} value={col.key}>
                                        Missing: {col.short_label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={`${sort}:${direction}`}
                            onValueChange={(v) => {
                                const [s, d] = v.split(":");
                                const nextDir = d === "desc" ? "desc" : "asc";
                                setSort(s);
                                setDirection(nextDir);
                                navigateHub({ sort: s, direction: nextDir, page: 1 });
                            }}
                        >
                            <SelectTrigger className="h-9 w-[145px] text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="name:asc">Name (A–Z)</SelectItem>
                                <SelectItem value="name:desc">Name (Z–A)</SelectItem>
                                <SelectItem value="student_id:asc">{idLabel} Asc</SelectItem>
                                <SelectItem value="created_at:desc">Newest Added</SelectItem>
                            </SelectContent>
                        </Select>

                        {(search || compliance !== "all" || missingDoc || studentType || courseId || yearLevel) && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-9 gap-1 text-xs"
                                onClick={() => {
                                    clearSearchDebounce();
                                    setSearch("");
                                    setCompliance("all");
                                    setMissingDoc("");
                                    setStudentType("");
                                    setCourseId("");
                                    setYearLevel("");
                                    navigateHub({
                                        search: "",
                                        compliance: "all",
                                        missing_doc: "",
                                        student_type: "",
                                        course_id: "",
                                        year_level: "",
                                        page: 1,
                                    });
                                }}
                            >
                                <RotateCcw className="size-3.5" />
                                Reset
                            </Button>
                        )}
                    </CardContent>
                </Card>

                {/* Bulk Selection Action Bar */}
                {selectedIds.length > 0 && viewMode === "matrix" && (
                    <div className="border-primary/25 bg-card sticky top-3 z-20 flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3 shadow-md">
                        <div className="flex items-center gap-2">
                            <Badge>{selectedIds.length} selected</Badge>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => setSelectedIds([])}
                            >
                                Clear
                            </Button>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            className="h-8 gap-1.5 text-xs"
                            disabled={reminding}
                            onClick={() => void handleBulkRemind({ studentIds: selectedIds })}
                        >
                            {reminding ? <Loader2 className="size-3.5 animate-spin" /> : <Mail className="size-3.5" />}
                            Send Missing-Document Reminders
                        </Button>
                    </div>
                )}

                {/* VIEW 1: DYNAMIC COMPLIANCE MATRIX */}
                {viewMode === "matrix" && (
                    <Card className="border-border/70 relative overflow-hidden">
                        {loading && (
                            <div className="bg-background/50 absolute inset-0 z-20 flex items-center justify-center backdrop-blur-[1px]">
                                <Loader2 className="text-primary size-6 animate-spin" />
                            </div>
                        )}
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-muted/40 hover:bg-muted/40">
                                        <TableHead className="w-10">
                                            <Checkbox
                                                checked={allPageSelected}
                                                onCheckedChange={(checked) =>
                                                    setSelectedIds(checked ? students.data.map((s) => s.id) : [])
                                                }
                                                aria-label="Select all students on page"
                                            />
                                        </TableHead>
                                        <TableHead className="min-w-[230px] text-xs font-semibold">Student</TableHead>
                                        {matrix_columns.map((col) => {
                                            const stat = columnStatsMap[col.key];
                                            const isFilteringThis = missingDoc === col.key;
                                            return (
                                                <TableHead key={col.key} className="min-w-[120px] py-2.5 text-center">
                                                    <div className="flex flex-col items-center gap-1">
                                                        <span className="text-foreground text-xs font-semibold">{col.short_label}</span>
                                                        {stat && (
                                                            <div className="flex items-center gap-1">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        const next = isFilteringThis ? "" : col.key;
                                                                        setMissingDoc(next);
                                                                        navigateHub({ missing_doc: next, page: 1 });
                                                                    }}
                                                                    className={cn(
                                                                        "rounded px-1.5 py-0.5 font-mono text-[10px] tabular-nums transition-colors",
                                                                        isFilteringThis
                                                                            ? "bg-primary text-primary-foreground"
                                                                            : "bg-muted text-muted-foreground hover:text-foreground",
                                                                    )}
                                                                    title={`Filter students missing ${col.label}`}
                                                                >
                                                                    {stat.compliance_percent}%{stat.missing > 0 ? ` · ${stat.missing} miss` : ""}
                                                                </button>
                                                                {stat.missing > 0 && (
                                                                    <button
                                                                        type="button"
                                                                        disabled={reminding}
                                                                        onClick={() => void handleBulkRemind({ documentKey: col.key })}
                                                                        className="text-muted-foreground hover:text-primary"
                                                                        title={`Email reminder to students missing ${col.label}`}
                                                                    >
                                                                        <Mail className="size-3" />
                                                                    </button>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableHead>
                                            );
                                        })}
                                        <TableHead className="min-w-[100px] text-center text-xs font-semibold">Readiness</TableHead>
                                        <TableHead className="w-[90px] text-right text-xs font-semibold">Studio</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {students.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={matrix_columns.length + 4} className="h-44 text-center">
                                                <div className="text-muted-foreground flex flex-col items-center justify-center gap-2">
                                                    <FileCheck2 className="size-8 opacity-35" />
                                                    <p className="text-sm font-medium">No students match the current document filter</p>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        students.data.map((student) => {
                                            const checked = selectedIds.includes(student.id);
                                            const reqTotal =
                                                student.summary.required_total ?? student.summary.total_required ?? 0;

                                            return (
                                                <TableRow key={student.id} data-state={checked && "selected"}>
                                                    <TableCell>
                                                        <Checkbox
                                                            checked={checked}
                                                            onCheckedChange={(val) =>
                                                                setSelectedIds((prev) =>
                                                                    val ? [...prev, student.id] : prev.filter((id) => id !== student.id),
                                                                )
                                                            }
                                                            aria-label={`Select ${student.full_name}`}
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2.5">
                                                            <Avatar className="border-border/60 size-8 border">
                                                                <AvatarImage src={student.avatar_url ?? undefined} alt={student.full_name} />
                                                                <AvatarFallback className="bg-primary/10 text-primary text-[11px] font-semibold">
                                                                    {getInitials(student.full_name)}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <div className="min-w-0">
                                                                <Link
                                                                    href={`/administrators/students/${student.id}/documents`}
                                                                    className="hover:text-primary block truncate text-xs font-semibold"
                                                                >
                                                                    {student.full_name}
                                                                </Link>
                                                                <p className="text-muted-foreground truncate font-mono text-[10px]">
                                                                    {student.student_id || `#${student.id}`} · {student.course_code || "Unassigned"} ·{" "}
                                                                    {student.academic_year}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    {matrix_columns.map((col) => {
                                                        const cell = student.slots[col.key];
                                                        if (!cell) {
                                                            return (
                                                                <TableCell key={col.key} className="text-center">
                                                                    <span className="text-muted-foreground text-[11px]">—</span>
                                                                </TableCell>
                                                            );
                                                        }
                                                        const meta = documentStatusMeta(cell.status);
                                                        return (
                                                            <TableCell key={col.key} className="text-center">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => openMatrixCell(student, cell)}
                                                                    className={cn(
                                                                        "inline-flex items-center justify-center rounded-md border px-2 py-1 text-[11px] font-medium transition-transform hover:scale-[1.03]",
                                                                        meta.className,
                                                                    )}
                                                                    title={`${student.full_name} · ${cell.label}: ${meta.label}`}
                                                                >
                                                                    {meta.shortLabel}
                                                                </button>
                                                            </TableCell>
                                                        );
                                                    })}
                                                    <TableCell className="text-center">
                                                        <Badge
                                                            variant="outline"
                                                            className={cn(
                                                                "font-mono text-[11px] tabular-nums",
                                                                student.summary.is_complete
                                                                    ? "border-emerald-500/35 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                                    : "border-amber-500/35 bg-amber-500/10 text-amber-700 dark:text-amber-300",
                                                            )}
                                                        >
                                                            {student.summary.compliant_count}/{reqTotal}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7"
                                                                title="Inspect in Split Reviewer"
                                                                onClick={() => {
                                                                    setSelectedStudentId(student.id);
                                                                    setViewMode("workbench");
                                                                }}
                                                            >
                                                                <PanelLeft className="size-3.5" />
                                                            </Button>
                                                            <Button
                                                                render={<Link href={`/administrators/students/${student.id}/documents`} />}
                                                                nativeButton={false}
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7"
                                                                title="Open Full Student Document Studio"
                                                            >
                                                                <FolderOpen className="size-3.5" />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Pagination Footer */}
                        {paginationFooter}
                    </Card>
                )}

                {/* VIEW 2: SPLIT-PANE VERIFICATION WORKBENCH */}
                {viewMode === "workbench" && (
                    <Card className="border-border/70 relative overflow-hidden">
                        {loading && (
                            <div className="bg-background/50 absolute inset-0 z-20 flex items-center justify-center backdrop-blur-[1px]">
                                <Loader2 className="text-primary size-6 animate-spin" />
                            </div>
                        )}
                        <div className="grid h-[72vh] min-h-[540px] grid-cols-1 divide-y overflow-hidden lg:grid-cols-[260px_minmax(0,1fr)_320px] lg:divide-x lg:divide-y-0">
                            {/* Left Pane: Student Queue */}
                            <div className="flex min-h-0 flex-col overflow-hidden">
                                <div className="border-border/70 bg-muted/30 flex items-center justify-between border-b px-3 py-2.5">
                                    <span className="text-xs font-semibold">
                                        Queue ({students.data.length}
                                        {students.total > students.data.length ? ` of ${students.total}` : ""})
                                    </span>
                                    <span className="text-muted-foreground font-mono text-[10px]">Alt + ← / →</span>
                                </div>
                                <div className="flex-1 divide-y overflow-y-auto">
                                    {students.data.length === 0 ? (
                                        <div className="text-muted-foreground flex h-full items-center justify-center p-4 text-center text-xs">
                                            No students match the current filter.
                                        </div>
                                    ) : (
                                        students.data.map((student) => {
                                            const active = student.id === selectedStudentId;
                                            const reqTotal = student.summary.required_total ?? 0;
                                            return (
                                                <button
                                                    key={student.id}
                                                    type="button"
                                                    onClick={() => setSelectedStudentId(student.id)}
                                                    className={cn(
                                                        "flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left transition-colors",
                                                        active ? "bg-primary/10" : "hover:bg-muted/50",
                                                    )}
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-xs font-semibold">{student.full_name}</p>
                                                        <p className="text-muted-foreground truncate font-mono text-[10px]">
                                                            {student.student_id || `#${student.id}`} · {student.course_code || "—"}
                                                        </p>
                                                    </div>
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            "shrink-0 font-mono text-[10px] tabular-nums",
                                                            student.summary.is_complete
                                                                ? "border-emerald-500/35 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                                : student.summary.pending_review_count > 0
                                                                  ? "border-amber-500/35 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                                  : "border-border text-muted-foreground",
                                                        )}
                                                    >
                                                        {student.summary.compliant_count}/{reqTotal}
                                                    </Badge>
                                                </button>
                                            );
                                        })
                                    )}
                                </div>
                            </div>

                            {/* Center Pane: Live Document Viewer */}
                            <div className="flex min-h-0 flex-col overflow-hidden p-3">
                                {workbenchLoading ? (
                                    <div className="flex flex-1 items-center justify-center">
                                        <Loader2 className="text-primary size-6 animate-spin" />
                                    </div>
                                ) : workbenchDossier && activeWorkbenchStudent && activeWorkbenchSlot ? (
                                    <DocumentPreviewViewer
                                        title={`${activeWorkbenchStudent.full_name} — ${activeWorkbenchSlot.label}`}
                                        fileName={activeWorkbenchSlot.file_name}
                                        mimeType={activeWorkbenchSlot.mime_type}
                                        previewUrl={activeWorkbenchSlot.preview_url}
                                        downloadUrl={activeWorkbenchSlot.download_url}
                                        className="h-full"
                                    />
                                ) : activeWorkbenchStudent ? (
                                    <div className="text-muted-foreground flex flex-1 flex-col items-center justify-center gap-2 text-center text-xs">
                                        <p>Unable to load document dossier for {activeWorkbenchStudent.full_name}.</p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-8 gap-1.5 text-xs"
                                            onClick={() => void fetchWorkbenchDossier(activeWorkbenchStudent.id)}
                                        >
                                            <RotateCcw className="size-3.5" />
                                            Retry
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="text-muted-foreground flex flex-1 items-center justify-center text-xs">
                                        Select a student from the queue to inspect documents.
                                    </div>
                                )}
                            </div>

                            {/* Right Pane: Checklist & Rapid Decision Rail */}
                            <div className="flex min-h-0 flex-col overflow-hidden">
                                <input
                                    ref={workbenchFileRef}
                                    type="file"
                                    className="hidden"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0];
                                        if (file && workbenchDossier && activeWorkbenchStudent && activeWorkbenchSlot) {
                                            void handleUploadSlot(activeWorkbenchStudent.id, activeWorkbenchSlot.key, file);
                                        }
                                        e.currentTarget.value = "";
                                    }}
                                />

                                <div className="border-border/70 bg-muted/30 flex items-center justify-between gap-2 border-b px-3.5 py-2.5">
                                    <div className="min-w-0">
                                        <p className="truncate text-xs font-semibold">
                                            {activeWorkbenchStudent?.full_name || "Checklist"}
                                        </p>
                                        {workbenchDossier && (
                                            <p className="text-muted-foreground font-mono text-[10px]">
                                                {workbenchDossier.summary.compliant_count}/
                                                {workbenchDossier.summary.total_required ?? 0} Ready (
                                                {workbenchDossier.summary.compliance_percent}%)
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="size-7"
                                            disabled={selectedStudentIdx <= 0}
                                            onClick={() => setSelectedStudentId(students.data[selectedStudentIdx - 1].id)}
                                            title="Previous student"
                                        >
                                            <ArrowLeft className="size-3.5" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="size-7"
                                            disabled={selectedStudentIdx < 0 || selectedStudentIdx >= students.data.length - 1}
                                            onClick={() => setSelectedStudentId(students.data[selectedStudentIdx + 1].id)}
                                            title="Next student"
                                        >
                                            <ArrowRight className="size-3.5" />
                                        </Button>
                                    </div>
                                </div>

                                {workbenchDossier && (
                                    <div className="px-3.5 pt-2.5">
                                        <Progress value={workbenchDossier.summary.compliance_percent} className="h-1.5" />
                                    </div>
                                )}

                                <div className="flex-1 space-y-2 overflow-y-auto p-3.5">
                                    {workbenchLoading ? (
                                        <div className="flex h-full items-center justify-center">
                                            <Loader2 className="text-primary size-5 animate-spin" />
                                        </div>
                                    ) : workbenchDossier && activeWorkbenchStudent ? (
                                        workbenchDossier.slots.map((slot) => {
                                            const meta = documentStatusMeta(slot.status);
                                            const selected = slot.key === activeWorkbenchSlot?.key;
                                            return (
                                                <div
                                                    key={slot.key}
                                                    className={cn(
                                                        "rounded-xl border transition-colors",
                                                        selected ? "border-primary bg-primary/5" : "border-border/70 hover:bg-muted/40",
                                                    )}
                                                >
                                                    <button
                                                        type="button"
                                                        aria-pressed={selected}
                                                        onClick={() => selectWorkbenchSlot(slot.key)}
                                                        className="focus-visible:ring-ring w-full rounded-xl p-3 text-left outline-none focus-visible:ring-2"
                                                    >
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="truncate text-xs font-semibold">{slot.label}</span>
                                                            <Badge variant="outline" className={cn("text-[10px]", meta.className)}>
                                                                {meta.shortLabel}
                                                            </Badge>
                                                        </div>
                                                        {slot.rejection_reason && (
                                                            <p className="mt-1 text-[11px] text-rose-600 dark:text-rose-400">
                                                                {slot.rejection_reason}
                                                            </p>
                                                        )}
                                                    </button>
                                                    {selected && (
                                                        <div className="mx-3 mb-3 space-y-2.5 border-t pt-2.5">
                                                            <div className="flex flex-wrap items-center gap-1.5">
                                                                {slot.status !== "verified" && (
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        className="h-7 gap-1 px-2 text-[11px]"
                                                                        disabled={cellBusy || !slot.has_file}
                                                                        title={
                                                                            !slot.has_file
                                                                                ? "Upload a file first before verifying"
                                                                                : "Verify uploaded document"
                                                                        }
                                                                        onClick={() => {
                                                                            if (!slot.has_file) return;
                                                                            void handleReviewSlot(
                                                                                activeWorkbenchStudent.id,
                                                                                slot.key,
                                                                                "verify",
                                                                                undefined,
                                                                                slot.file_revision,
                                                                            );
                                                                        }}
                                                                    >
                                                                        <CheckCircle2 className="size-3" />
                                                                        Verify
                                                                    </Button>
                                                                )}
                                                                {!slot.has_file && slot.status !== "physical_on_file" && (
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="outline"
                                                                        className="h-7 gap-1 px-2 text-[11px]"
                                                                        disabled={cellBusy}
                                                                        onClick={() =>
                                                                            void handleReviewSlot(
                                                                                activeWorkbenchStudent.id,
                                                                                slot.key,
                                                                                "physical",
                                                                            )
                                                                        }
                                                                    >
                                                                        <FileCheck2 className="size-3" />
                                                                        Physical
                                                                    </Button>
                                                                )}
                                                                {slot.has_file && slot.status !== "rejected" && (
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="outline"
                                                                        className="h-7 gap-1 px-2 text-[11px] text-rose-600"
                                                                        disabled={cellBusy}
                                                                        onClick={() => {
                                                                            setWorkbenchRejectReason(DOCUMENT_REJECTION_PRESETS[0]);
                                                                            setWorkbenchRejectOpen(true);
                                                                        }}
                                                                    >
                                                                        <XCircle className="size-3" />
                                                                        Reject
                                                                    </Button>
                                                                )}
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="h-7 gap-1 px-2 text-[11px]"
                                                                    disabled={cellBusy}
                                                                    onClick={() => workbenchFileRef.current?.click()}
                                                                >
                                                                    <UploadCloud className="size-3" />
                                                                    {slot.has_file ? "Replace" : "Upload"}
                                                                </Button>
                                                            </div>

                                                            {workbenchRejectOpen && slot.has_file && (
                                                                <div className="space-y-2 rounded-lg border border-rose-500/30 bg-rose-500/5 p-2.5">
                                                                    <Label
                                                                        htmlFor="workbench-reject-reason"
                                                                        className="text-xs font-semibold"
                                                                    >
                                                                        Rejection Reason (sent to student)
                                                                    </Label>
                                                                    <div className="flex flex-wrap gap-1">
                                                                        {DOCUMENT_REJECTION_PRESETS.map((preset) => (
                                                                            <button
                                                                                key={preset}
                                                                                type="button"
                                                                                onClick={() => setWorkbenchRejectReason(preset)}
                                                                                className={cn(
                                                                                    "border-border rounded border px-1.5 py-0.5 text-left text-[10px]",
                                                                                    workbenchRejectReason === preset
                                                                                        ? "border-primary bg-primary/10 font-medium"
                                                                                        : "hover:bg-muted",
                                                                                )}
                                                                            >
                                                                                {preset}
                                                                            </button>
                                                                        ))}
                                                                    </div>
                                                                    <Textarea
                                                                        id="workbench-reject-reason"
                                                                        value={workbenchRejectReason}
                                                                        onChange={(e) => setWorkbenchRejectReason(e.target.value)}
                                                                        rows={2}
                                                                        className="text-xs"
                                                                    />
                                                                    <div className="flex justify-end gap-1.5">
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="sm"
                                                                            className="h-7 px-2 text-[11px]"
                                                                            disabled={cellBusy}
                                                                            onClick={() => setWorkbenchRejectOpen(false)}
                                                                        >
                                                                            Cancel
                                                                        </Button>
                                                                        <Button
                                                                            type="button"
                                                                            variant="destructive"
                                                                            size="sm"
                                                                            className="h-7 px-2 text-[11px]"
                                                                            disabled={cellBusy || workbenchRejectReason.trim().length === 0}
                                                                            onClick={() =>
                                                                                void handleReviewSlot(
                                                                                    activeWorkbenchStudent.id,
                                                                                    slot.key,
                                                                                    "reject",
                                                                                    workbenchRejectReason.trim(),
                                                                                )
                                                                            }
                                                                        >
                                                                            Confirm Rejection &amp; Notify
                                                                        </Button>
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })
                                    ) : (
                                        <div className="text-muted-foreground flex h-full items-center justify-center text-center text-xs">
                                            {activeWorkbenchStudent
                                                ? "Checklist unavailable until the dossier loads."
                                                : "Select a student to view checklist."}
                                        </div>
                                    )}
                                </div>

                                {activeWorkbenchStudent && (
                                    <div className="border-border/70 border-t p-3">
                                        <Button
                                            render={<Link href={`/administrators/students/${activeWorkbenchStudent.id}/documents`} />}
                                            nativeButton={false}
                                            variant="outline"
                                            size="sm"
                                            className="h-8 w-full gap-1.5 text-xs"
                                        >
                                            <ExternalLink className="size-3.5" />
                                            Open Full Student Studio
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>
                        {paginationFooter}
                    </Card>
                )}
            </div>

            {/* Matrix Cell Quick-Action Dialog */}
            <Dialog open={activeCell !== null} onOpenChange={(open) => !open && closeMatrixCell()}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex flex-wrap items-center gap-2 text-base">
                            <span>{activeCell?.cell.label}</span>
                            {activeCell && (
                                <Badge variant="outline" className={cn("text-xs", documentStatusMeta(activeCell.cell.status).className)}>
                                    {documentStatusMeta(activeCell.cell.status).label}
                                </Badge>
                            )}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {activeCell?.student.full_name} ({activeCell?.student.student_id || `#${activeCell?.student.id}`})
                        </DialogDescription>
                    </DialogHeader>

                    {activeCell && (
                        <div className="space-y-4">
                            <input
                                ref={cellFileInputRef}
                                type="file"
                                className="hidden"
                                onChange={(e) => {
                                    const file = e.target.files?.[0];
                                    if (file) void handleUploadSlot(activeCell.student.id, activeCell.cell.key, file);
                                    e.currentTarget.value = "";
                                }}
                            />

                            <DocumentPreviewViewer
                                title={activeCell.cell.label}
                                fileName={activeCell.cell.file_name}
                                previewUrl={activeCell.cell.preview_url}
                                downloadUrl={activeCell.cell.download_url}
                                className="h-[340px]"
                            />

                            {activeCell.cell.rejection_reason && (
                                <div className="flex items-start gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 p-2.5 text-xs text-rose-700 dark:text-rose-300">
                                    <AlertCircle className="mt-0.5 size-4 shrink-0" />
                                    <span>Previous rejection note: {activeCell.cell.rejection_reason}</span>
                                </div>
                            )}

                            {rejectMode && (
                                <div className="space-y-2 rounded-lg border border-rose-500/30 bg-rose-500/5 p-3">
                                    <Label htmlFor="matrix-reject-reason" className="text-xs font-semibold">
                                        Rejection Reason (sent to student)
                                    </Label>
                                    <div className="flex flex-wrap gap-1.5">
                                        {DOCUMENT_REJECTION_PRESETS.map((preset) => (
                                            <button
                                                key={preset}
                                                type="button"
                                                onClick={() => setRejectReason(preset)}
                                                className={cn(
                                                    "border-border rounded border px-2 py-0.5 text-[11px]",
                                                    rejectReason === preset ? "border-primary bg-primary/10 font-medium" : "hover:bg-muted",
                                                )}
                                            >
                                                {preset}
                                            </button>
                                        ))}
                                    </div>
                                    <Textarea
                                        id="matrix-reject-reason"
                                        value={rejectReason}
                                        onChange={(e) => setRejectReason(e.target.value)}
                                        rows={2}
                                        className="text-xs"
                                    />
                                    <div className="flex justify-end gap-2">
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setRejectMode(false)}>
                                            Cancel
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            disabled={cellBusy || rejectReason.trim().length === 0}
                                            onClick={() =>
                                                void handleReviewSlot(
                                                    activeCell.student.id,
                                                    activeCell.cell.key,
                                                    "reject",
                                                    rejectReason.trim(),
                                                )
                                            }
                                        >
                                            Confirm Rejection &amp; Notify
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {waiveMode && (
                                <div className="bg-muted/30 space-y-2 rounded-lg border p-3">
                                    <Label htmlFor="matrix-waive-reason" className="text-xs font-semibold">
                                        Waiver Reason
                                    </Label>
                                    <Input
                                        id="matrix-waive-reason"
                                        value={waiveReason}
                                        onChange={(e) => setWaiveReason(e.target.value)}
                                        placeholder="e.g. Not applicable for freshman entrant"
                                        className="h-8 text-xs"
                                    />
                                    <div className="flex justify-end gap-2">
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setWaiveMode(false)}>
                                            Cancel
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={cellBusy}
                                            onClick={() =>
                                                void handleReviewSlot(
                                                    activeCell.student.id,
                                                    activeCell.cell.key,
                                                    "waive",
                                                    waiveReason.trim(),
                                                )
                                            }
                                        >
                                            Confirm Waiver
                                        </Button>
                                    </div>
                                </div>
                            )}

                            <DialogFooter className="flex-wrap justify-between gap-2 sm:justify-between">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="h-8 gap-1.5 text-xs"
                                        disabled={cellBusy}
                                        onClick={() => cellFileInputRef.current?.click()}
                                    >
                                        <UploadCloud className="size-3.5" />
                                        {activeCell.cell.has_file ? "Replace File" : "Upload File"}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="h-8 gap-1.5 text-xs"
                                        disabled={cellBusy}
                                        onClick={() =>
                                            void handleReviewSlot(activeCell.student.id, activeCell.cell.key, "physical")
                                        }
                                    >
                                        <FileCheck2 className="size-3.5" />
                                        Physical Copy
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-8 gap-1.5 text-xs"
                                        disabled={cellBusy}
                                        onClick={() => {
                                            setRejectMode(false);
                                            setWaiveMode(true);
                                        }}
                                    >
                                        <ShieldOff className="size-3.5" />
                                        Waive
                                    </Button>
                                </div>

                                <div className="flex items-center gap-1.5">
                                    {activeCell.cell.has_file && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="h-8 gap-1.5 text-xs text-rose-600"
                                            disabled={cellBusy}
                                            onClick={() => {
                                                setWaiveMode(false);
                                                setRejectMode(true);
                                            }}
                                        >
                                            <XCircle className="size-3.5" />
                                            Reject
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="h-8 gap-1.5 text-xs"
                                        disabled={cellBusy || !activeCell.cell.has_file}
                                        title={
                                            !activeCell.cell.has_file
                                                ? "Upload a file before verifying (or mark Physical Copy)"
                                                : "Verify uploaded document"
                                        }
                                        onClick={() => {
                                            if (!activeCell.cell.has_file) return;
                                            void handleReviewSlot(
                                                activeCell.student.id,
                                                activeCell.cell.key,
                                                "verify",
                                                undefined,
                                                activeCell.cell.file_revision,
                                            );
                                        }}
                                    >
                                        {cellBusy ? <Loader2 className="size-3.5 animate-spin" /> : <CheckCircle2 className="size-3.5" />}
                                        Verify
                                    </Button>
                                </div>
                            </DialogFooter>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
