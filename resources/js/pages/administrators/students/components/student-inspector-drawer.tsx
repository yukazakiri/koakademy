import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { Link, router } from "@inertiajs/react";
import axios from "axios";
import {
    BookOpen,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ClipboardPlus,
    ExternalLink,
    Eye,
    FileCheck2,
    KeyRound,
    Loader2,
    Mail,
    Pencil,
    Phone,
    RotateCcw,
    Save,
    ShieldAlert,
    Trash2,
    UploadCloud,
    XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { getInitials, getStatusColor, type Student } from "../columns";
import {
    DOCUMENT_REJECTION_PRESETS,
    documentStatusMeta,
    type DocumentDossier,
    type DocumentSlot,
    type StudentConfiguration,
} from "../shared-types";
import { DocumentPreviewViewer } from "./document-preview-viewer";
import { StudentPortalAccountCard, type StudentPortalAccountSummary } from "./student-portal-account-card";

interface StudentInspectorDrawerProps {
    student: (Student & { email?: string | null; phone?: string | null; portal_account?: StudentPortalAccountSummary | null }) | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initialTab?: "overview" | "documents" | "holds" | "portal";
    statusOptions: Array<{ value: string; label: string }>;
    studentConfiguration?: StudentConfiguration;
    onPrev?: () => void;
    onNext?: () => void;
    hasPrev?: boolean;
    hasNext?: boolean;
}

export function StudentInspectorDrawer({
    student,
    open,
    onOpenChange,
    initialTab = "overview",
    statusOptions,
    studentConfiguration,
    onPrev,
    onNext,
    hasPrev = false,
    hasNext = false,
}: StudentInspectorDrawerProps) {
    const [activeTab, setActiveTab] = useState<"overview" | "documents" | "holds" | "portal">(initialTab);
    const [status, setStatus] = useState(student?.status ?? "enrolled");
    const [yearLevel, setYearLevel] = useState(String(student?.year_level ?? 1));
    const [saving, setSaving] = useState(false);

    // Lazy dossier loading for Documents tab (scoped per student to avoid cross-student races)
    const activeStudentId = student?.id ?? null;
    const activeStudentIdRef = useRef<number | null>(activeStudentId);
    activeStudentIdRef.current = activeStudentId;
    const isTrashed = Boolean(student?.deleted_at);

    const [dossierState, setDossierState] = useState<{ studentId: number; dossier: DocumentDossier } | null>(null);
    const [loadingDossierStudentId, setLoadingDossierStudentId] = useState<number | null>(null);
    const [failedDossierStudentId, setFailedDossierStudentId] = useState<number | null>(null);
    const dossierRequestSeqRef = useRef(0);
    const dossierAbortControllerRef = useRef<AbortController | null>(null);

    const [busySlots, setBusySlots] = useState<Record<string, number>>({});
    const [previewSlotKey, setPreviewSlotKey] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [uploadTarget, setUploadTarget] = useState<{ studentId: number; slotKey: string } | null>(null);

    const [rejectTarget, setRejectTarget] = useState<{ studentId: number; slot: DocumentSlot } | null>(null);
    const [rejectReason, setRejectReason] = useState("");
    const [notifyStudentOnReject, setNotifyStudentOnReject] = useState(true);

    const activeDossier =
        activeStudentId !== null && dossierState?.studentId === activeStudentId ? dossierState.dossier : null;
    const isDossierLoading = activeStudentId !== null && loadingDossierStudentId === activeStudentId;
    const hasDossierError = activeStudentId !== null && failedDossierStudentId === activeStudentId;

    const previewSlot = useMemo(() => {
        if (!previewSlotKey || !activeDossier) return null;
        return activeDossier.slots.find((slot) => slot.key === previewSlotKey && slot.has_file) ?? null;
    }, [activeDossier, previewSlotKey]);

    const markSlotBusy = useCallback((studentId: number, slotKey: string) => {
        const compositeKey = `${studentId}:${slotKey}`;
        setBusySlots((prev) => ({ ...prev, [compositeKey]: (prev[compositeKey] ?? 0) + 1 }));
    }, []);

    const unmarkSlotBusy = useCallback((studentId: number, slotKey: string) => {
        const compositeKey = `${studentId}:${slotKey}`;
        setBusySlots((prev) => {
            const nextCount = (prev[compositeKey] ?? 0) - 1;
            if (nextCount <= 0) {
                const rest = { ...prev };
                delete rest[compositeKey];
                return rest;
            }
            return { ...prev, [compositeKey]: nextCount };
        });
    }, []);

    useEffect(() => {
        if (open) {
            setActiveTab(initialTab);
        } else {
            dossierAbortControllerRef.current?.abort();
            dossierAbortControllerRef.current = null;
        }
    }, [open, initialTab]);

    useEffect(() => {
        if (!student) return;
        setStatus(student.status ?? "enrolled");
        setYearLevel(String(student.year_level ?? 1));
    }, [student?.id, student?.status, student?.year_level]);

    useEffect(() => {
        dossierAbortControllerRef.current?.abort();
        dossierAbortControllerRef.current = null;
        setPreviewSlotKey(null);
        setRejectTarget(null);
        setRejectReason("");
        setUploadTarget(null);
    }, [student?.id]);

    useEffect(() => {
        return () => {
            dossierAbortControllerRef.current?.abort();
            dossierAbortControllerRef.current = null;
        };
    }, []);

    const loadDossier = useCallback(async (studentId: number) => {
        dossierAbortControllerRef.current?.abort();
        const controller = new AbortController();
        dossierAbortControllerRef.current = controller;
        const requestSeq = ++dossierRequestSeqRef.current;

        setLoadingDossierStudentId(studentId);
        setFailedDossierStudentId((prev) => (prev === studentId ? null : prev));

        try {
            const response = await axios.get(`/administrators/students/${studentId}/documents/dossier`, {
                headers: { Accept: "application/json" },
                signal: controller.signal,
            });
            if (activeStudentIdRef.current !== studentId || dossierRequestSeqRef.current !== requestSeq) {
                return;
            }
            const nextDossier = response.data?.dossier as DocumentDossier | undefined;
            if (nextDossier) {
                setDossierState({ studentId, dossier: nextDossier });
            } else {
                setFailedDossierStudentId(studentId);
            }
        } catch (error: unknown) {
            const errName = (error as { name?: string })?.name;
            if (axios.isCancel(error) || errName === "CanceledError" || errName === "AbortError") {
                return;
            }
            if (activeStudentIdRef.current !== studentId || dossierRequestSeqRef.current !== requestSeq) {
                return;
            }
            setFailedDossierStudentId(studentId);
            toast.error("Unable to load student documents.");
        } finally {
            if (dossierRequestSeqRef.current === requestSeq) {
                setLoadingDossierStudentId((prev) => (prev === studentId ? null : prev));
            }
        }
    }, []);

    useEffect(() => {
        if (
            open &&
            activeTab === "documents" &&
            student &&
            !isTrashed &&
            !activeDossier &&
            !isDossierLoading &&
            !hasDossierError
        ) {
            void loadDossier(student.id);
        }
    }, [open, activeTab, student, isTrashed, activeDossier, isDossierLoading, hasDossierError, loadDossier]);

    useEffect(() => {
        if (!open) return;
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.metaKey || event.ctrlKey || event.altKey) return;
            if (rejectTarget !== null) return;
            const target = event.target as HTMLElement | null;
            if (
                target &&
                (target.isContentEditable ||
                    ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName) ||
                    target.closest(
                        '[role="combobox"], [role="listbox"], [role="option"], [aria-haspopup="listbox"], [data-slot="select-trigger"], [data-slot="select-content"]',
                    ))
            ) {
                return;
            }
            if ((event.key === "[" || event.key === "k") && hasPrev) {
                event.preventDefault();
                onPrev?.();
            } else if ((event.key === "]" || event.key === "j") && hasNext) {
                event.preventDefault();
                onNext?.();
            }
        };
        window.addEventListener("keydown", onKeyDown);
        return () => window.removeEventListener("keydown", onKeyDown);
    }, [open, rejectTarget, hasPrev, hasNext, onPrev, onNext]);

    const yearChoices = useMemo(() => {
        const typeKey = student?.type ?? "college";
        const configured = studentConfiguration?.year_levels?.[typeKey];
        if (configured && configured.length > 0) {
            return configured.map((item) => ({ value: String(item.value), label: item.label }));
        }
        return typeKey === "shs"
            ? [
                  { value: "11", label: "Grade 11" },
                  { value: "12", label: "Grade 12" },
              ]
            : [
                  { value: "1", label: "1st Year" },
                  { value: "2", label: "2nd Year" },
                  { value: "3", label: "3rd Year" },
                  { value: "4", label: "4th Year" },
                  { value: "5", label: "5th Year" },
              ];
    }, [student?.type, studentConfiguration]);

    if (!student) return null;

    const isDirty = status !== (student.status ?? "enrolled") || Number(yearLevel) !== Number(student.year_level ?? 1);

    const handleQuickSave = async () => {
        if (!isDirty || saving) return;
        setSaving(true);
        try {
            await axios.patch(
                `/administrators/students/${student.id}/quick-update`,
                { status, academic_year: Number(yearLevel) },
                { headers: { Accept: "application/json" } },
            );
            toast.success(`Updated ${student.name}`);
            router.reload();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to quick-update student.");
        } finally {
            setSaving(false);
        }
    };

    const handleInlineReview = async (
        slot: DocumentSlot,
        action: "verify" | "physical" | "reject",
        options?: { reason?: string; notifyStudent?: boolean; studentId?: number },
    ) => {
        const targetStudentId = options?.studentId ?? student.id;
        const trimmedReason = options?.reason?.trim() || null;
        const notifyStudent = action === "reject" ? (options?.notifyStudent ?? true) : true;

        markSlotBusy(targetStudentId, slot.key);
        try {
            const response = await axios.post(
                `/administrators/students/${targetStudentId}/documents/fixed/${slot.key}/review`,
                {
                    action,
                    reason: action === "reject" ? trimmedReason : null,
                    notify_student: notifyStudent,
                    ...(action === "verify" ? { expected_file_revision: slot.file_revision ?? undefined } : {}),
                },
                { headers: { Accept: "application/json" } },
            );
            const nextDossier = response.data?.dossier as DocumentDossier | undefined;
            if (nextDossier && activeStudentIdRef.current === targetStudentId) {
                setDossierState({ studentId: targetStudentId, dossier: nextDossier });
            }
            if (action === "reject" && trimmedReason) {
                toast.success(response.data?.message || `Rejected ${slot.label}: ${trimmedReason}`);
            } else {
                toast.success(response.data?.message || "Document updated.");
            }
            router.reload({ only: ["students", "triage_counts"] });
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
                if (err.response.data.dossier && activeStudentIdRef.current === targetStudentId) {
                    setDossierState({ studentId: targetStudentId, dossier: err.response.data.dossier });
                } else if (activeStudentIdRef.current === targetStudentId) {
                    void loadDossier(targetStudentId);
                }
            }
        } finally {
            unmarkSlotBusy(targetStudentId, slot.key);
        }
    };

    const handleInlineUpload = async (targetStudentId: number, key: string, file: File) => {
        markSlotBusy(targetStudentId, key);
        try {
            const formData = new FormData();
            formData.append("document_type", key);
            formData.append("file", file);
            formData.append("auto_verify", "1");
            const response = await axios.post(`/administrators/students/${targetStudentId}/documents/fixed`, formData, {
                headers: { Accept: "application/json", "Content-Type": "multipart/form-data" },
            });
            const nextDossier = response.data?.dossier as DocumentDossier | undefined;
            if (nextDossier && activeStudentIdRef.current === targetStudentId) {
                setDossierState({ studentId: targetStudentId, dossier: nextDossier });
            }
            toast.success("Document uploaded and verified.");
            router.reload({ only: ["students", "triage_counts"] });
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to upload file.");
        } finally {
            unmarkSlotBusy(targetStudentId, key);
        }
    };

    return (
        <>
            <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-[560px]">
                <SheetHeader className="border-border/70 bg-muted/20 border-b px-5 py-4">
                    <div className="flex items-start justify-between gap-3 pr-6">
                        <div className="flex min-w-0 items-center gap-3">
                            <Avatar className="border-border size-11 border">
                                <AvatarImage src={student.avatar_url ?? undefined} alt={student.name} />
                                <AvatarFallback className="bg-primary/10 text-primary text-xs font-semibold">
                                    {getInitials(student.name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0">
                                <SheetTitle className="truncate text-base font-semibold">{student.name}</SheetTitle>
                                <SheetDescription className="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
                                    <span className="font-mono font-medium tabular-nums">{student.student_id || `#${student.id}`}</span>
                                    <span>·</span>
                                    <span>{student.academic_year}</span>
                                    {student.course && (
                                        <>
                                            <span>·</span>
                                            <span>{student.course}</span>
                                        </>
                                    )}
                                </SheetDescription>
                            </div>
                        </div>

                        <div className="flex shrink-0 items-center gap-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="size-7"
                                disabled={!hasPrev}
                                onClick={onPrev}
                                title="Previous student ([ or k)"
                            >
                                <ChevronLeft className="size-3.5" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="size-7"
                                disabled={!hasNext}
                                onClick={onNext}
                                title="Next student (] or j)"
                            >
                                <ChevronRight className="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </SheetHeader>

                <Tabs
                    value={activeTab}
                    onValueChange={(v) => setActiveTab(v as "overview" | "documents" | "holds" | "portal")}
                    className="flex flex-1 flex-col overflow-hidden"
                >
                    <div className="border-border/70 border-b px-5 pt-2.5">
                        <TabsList className="grid w-full grid-cols-4">
                            <TabsTrigger value="overview" className="text-xs">
                                Overview
                            </TabsTrigger>
                            <TabsTrigger value="documents" className="gap-1 text-xs">
                                Docs
                                {student.docs_summary && student.docs_summary.required_total > 0 && (
                                    <span className="font-mono text-[10px] opacity-80">
                                        {student.docs_summary.compliant_count}/{student.docs_summary.required_total}
                                    </span>
                                )}
                            </TabsTrigger>
                            <TabsTrigger value="holds" className="gap-1 text-xs">
                                Holds
                                {(student.active_holds_count ?? 0) > 0 && (
                                    <span className="rounded-full bg-rose-500/20 px-1.5 font-mono text-[10px] text-rose-700 dark:text-rose-300">
                                        {student.active_holds_count}
                                    </span>
                                )}
                            </TabsTrigger>
                            <TabsTrigger value="portal" className="text-xs">
                                Portal
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <div className="flex-1 overflow-y-auto px-5 py-4">
                        <TabsContent value="overview" className="mt-0 space-y-4">
                            {/* Contact & Placement */}
                            <div className="grid grid-cols-2 gap-2.5">
                                <div className="border-border/70 bg-card rounded-xl border p-3">
                                    <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Placement</p>
                                    <p className="text-foreground mt-1 flex items-center gap-1.5 text-sm font-semibold">
                                        <BookOpen className="text-primary size-3.5 shrink-0" />
                                        <span className="truncate">{student.course || "Unassigned"}</span>
                                    </p>
                                    {student.course_title && (
                                        <p className="text-muted-foreground mt-0.5 truncate text-[11px]">{student.course_title}</p>
                                    )}
                                </div>
                                <div className="border-border/70 bg-card rounded-xl border p-3">
                                    <p className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Contact</p>
                                    <p className="mt-1 flex items-center gap-1.5 truncate text-xs">
                                        <Mail className="text-muted-foreground size-3 shrink-0" />
                                        <span className="truncate">{student.email || "No email"}</span>
                                    </p>
                                    <p className="text-muted-foreground mt-1 flex items-center gap-1.5 truncate text-xs">
                                        <Phone className="size-3 shrink-0" />
                                        <span className="truncate">{student.phone || "No phone"}</span>
                                    </p>
                                </div>
                            </div>

                            {/* Inline Quick Edit */}
                            <div className="border-border/70 bg-card space-y-3 rounded-xl border p-4">
                                <div className="flex items-center justify-between">
                                    <p className="text-foreground text-xs font-semibold">Inline Quick Edit</p>
                                    <span className="text-muted-foreground font-mono text-[10px]">j / k cycle rows</span>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="inspector-status" className="text-xs">
                                            Status
                                        </Label>
                                        <Select value={status} onValueChange={setStatus}>
                                            <SelectTrigger id="inspector-status" className="h-9 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {statusOptions.map((opt) => (
                                                    <SelectItem key={opt.value} value={opt.value}>
                                                        {opt.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="inspector-year" className="text-xs">
                                            Academic Level
                                        </Label>
                                        <Select value={yearLevel} onValueChange={setYearLevel}>
                                            <SelectTrigger id="inspector-year" className="h-9 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {yearChoices.map((choice) => (
                                                    <SelectItem key={choice.value} value={choice.value}>
                                                        {choice.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div className="flex justify-end">
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={!isDirty || saving}
                                        onClick={() => void handleQuickSave()}
                                        className="h-8 gap-1.5 text-xs"
                                    >
                                        {saving ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                                        Save Quick Edit
                                    </Button>
                                </div>
                            </div>

                            <Separator />

                            {/* Quick Navigation Actions */}
                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    render={<Link href={`/administrators/students/${student.id}`} />}
                                    nativeButton={false}
                                    variant="outline"
                                    size="sm"
                                    className="h-9 gap-1.5 text-xs"
                                >
                                    <ExternalLink className="size-3.5" />
                                    Full Profile
                                </Button>
                                <Button
                                    render={<Link href={`/administrators/students/${student.id}/edit`} />}
                                    nativeButton={false}
                                    variant="outline"
                                    size="sm"
                                    className="h-9 gap-1.5 text-xs"
                                >
                                    <Pencil className="size-3.5" />
                                    Full Edit
                                </Button>
                                <Button
                                    render={<Link href={`/administrators/students/${student.id}/documents`} />}
                                    nativeButton={false}
                                    variant="outline"
                                    size="sm"
                                    className="h-9 gap-1.5 text-xs"
                                >
                                    <FileCheck2 className="size-3.5" />
                                    Document Studio
                                </Button>
                                <Button
                                    render={<Link href={`/administrators/enrollments/create?student_id=${student.id}`} />}
                                    nativeButton={false}
                                    size="sm"
                                    className="h-9 gap-1.5 text-xs"
                                >
                                    <ClipboardPlus className="size-3.5" />
                                    Enroll Student
                                </Button>
                            </div>
                        </TabsContent>

                        <TabsContent value="documents" className="mt-0 space-y-3">
                            <input
                                ref={fileInputRef}
                                type="file"
                                className="hidden"
                                onChange={(e) => {
                                    const file = e.target.files?.[0];
                                    const target = uploadTarget;
                                    setUploadTarget(null);
                                    if (file && target) {
                                        void handleInlineUpload(target.studentId, target.slotKey, file);
                                    }
                                    e.currentTarget.value = "";
                                }}
                            />

                            {isTrashed ? (
                                <div className="border-border/70 bg-muted/30 flex flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-10 text-center">
                                    <Trash2 className="text-muted-foreground size-5" />
                                    <p className="text-foreground text-xs font-semibold">Student record is in the trash</p>
                                    <p className="text-muted-foreground max-w-xs text-xs">
                                        Document viewing, verification, and uploads are unavailable for trashed records. Restore this
                                        student from the directory first to manage their documents.
                                    </p>
                                </div>
                            ) : isDossierLoading ? (
                                <div className="flex items-center justify-center py-12">
                                    <Loader2 className="text-muted-foreground size-5 animate-spin" />
                                </div>
                            ) : activeDossier ? (
                                <>
                                    <div className="space-y-1.5">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="font-semibold">
                                                Checklist Readiness ({activeDossier.summary.compliant_count}/
                                                {activeDossier.summary.total_required ?? activeDossier.summary.required_total ?? 0})
                                            </span>
                                            <Link
                                                href={`/administrators/students/${student.id}/documents`}
                                                className="text-primary font-medium hover:underline"
                                            >
                                                Open Full Studio →
                                            </Link>
                                        </div>
                                        <Progress value={activeDossier.summary.compliance_percent} className="h-1.5" />
                                    </div>

                                    {previewSlot && (
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs font-semibold">{previewSlot.label}</span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 text-xs"
                                                    onClick={() => setPreviewSlotKey(null)}
                                                >
                                                    Close Preview
                                                </Button>
                                            </div>
                                            <DocumentPreviewViewer
                                                key={`${student.id}:${previewSlot.key}:${previewSlot.preview_url ?? ""}:${previewSlot.file_name ?? ""}:${previewSlot.uploaded_at ?? ""}`}
                                                title={previewSlot.label}
                                                fileName={previewSlot.file_name}
                                                mimeType={previewSlot.mime_type}
                                                previewUrl={previewSlot.preview_url}
                                                downloadUrl={previewSlot.download_url}
                                                className="h-[300px]"
                                            />
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        {activeDossier.slots.map((slot) => {
                                            const meta = documentStatusMeta(slot.status);
                                            const isBusy = (busySlots[`${student.id}:${slot.key}`] ?? 0) > 0;

                                            return (
                                                <div
                                                    key={slot.key}
                                                    className="border-border/70 bg-card flex items-center justify-between gap-2 rounded-xl border p-3"
                                                >
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-1.5">
                                                            <p className="truncate text-xs font-semibold">{slot.label}</p>
                                                            <Badge variant="outline" className={cn("text-[10px]", meta.className)}>
                                                                {meta.shortLabel}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-muted-foreground mt-0.5 truncate text-[11px]">
                                                            {slot.file_name || (slot.required ? "Required document" : "Optional")}
                                                        </p>
                                                    </div>

                                                    <div className="flex shrink-0 items-center gap-1">
                                                        {slot.has_file && (
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-7"
                                                                title="Preview"
                                                                onClick={() => setPreviewSlotKey(slot.key)}
                                                            >
                                                                <Eye className="size-3.5" />
                                                            </Button>
                                                        )}
                                                        {slot.status !== "verified" && slot.has_file && (
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-7 text-emerald-600 hover:text-emerald-700"
                                                                title="Verify"
                                                                disabled={isBusy}
                                                                onClick={() => void handleInlineReview(slot, "verify")}
                                                            >
                                                                <CheckCircle2 className="size-3.5" />
                                                            </Button>
                                                        )}
                                                        {slot.has_file && slot.status !== "rejected" && (
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-7 text-rose-600 hover:text-rose-700"
                                                                title="Reject"
                                                                disabled={isBusy}
                                                                onClick={() => {
                                                                    setRejectReason("");
                                                                    setNotifyStudentOnReject(true);
                                                                    setRejectTarget({ studentId: student.id, slot });
                                                                }}
                                                            >
                                                                <XCircle className="size-3.5" />
                                                            </Button>
                                                        )}
                                                        {!slot.has_file && slot.status !== "physical_on_file" && (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="ghost"
                                                                className="h-7 px-2 text-[11px]"
                                                                disabled={isBusy}
                                                                onClick={() => void handleInlineReview(slot, "physical")}
                                                            >
                                                                Physical
                                                            </Button>
                                                        )}
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="outline"
                                                            className="size-7"
                                                            title={slot.has_file ? "Replace file" : "Upload file"}
                                                            disabled={isBusy}
                                                            onClick={() => {
                                                                setUploadTarget({ studentId: student.id, slotKey: slot.key });
                                                                fileInputRef.current?.click();
                                                            }}
                                                        >
                                                            {isBusy ? (
                                                                <Loader2 className="size-3.5 animate-spin" />
                                                            ) : (
                                                                <UploadCloud className="size-3.5" />
                                                            )}
                                                        </Button>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </>
                            ) : (
                                <div className="flex flex-col items-center gap-3 py-8 text-center">
                                    <p className="text-muted-foreground text-xs">Unable to load document dossier.</p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-8 gap-1.5 text-xs"
                                        onClick={() => {
                                            setFailedDossierStudentId(null);
                                            void loadDossier(student.id);
                                        }}
                                    >
                                        <RotateCcw className="size-3.5" />
                                        Retry
                                    </Button>
                                </div>
                            )}
                        </TabsContent>

                        <TabsContent value="holds" className="mt-0 space-y-3">
                            <div className="border-border/70 bg-card space-y-3 rounded-xl border p-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <ShieldAlert className="text-primary size-4" />
                                        <span className="text-xs font-semibold">Clearance &amp; Administrative Holds</span>
                                    </div>
                                    <Badge variant="outline" className={`text-[11px] capitalize ${getStatusColor(student.status)}`}>
                                        {student.previous_sem_clearance.replace("_", " ")}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    {(student.active_holds_count ?? 0) > 0
                                        ? `This student currently has ${student.active_holds_count} active cross-office hold(s) that may block enrollment or grade release.`
                                        : "No active multi-office administrative holds on this student record."}
                                </p>
                                <div className="flex flex-wrap gap-2 pt-1">
                                    <Button
                                        render={<Link href="/administrators/enrollments/holds" />}
                                        nativeButton={false}
                                        size="sm"
                                        variant="outline"
                                        className="h-8 gap-1.5 text-xs"
                                    >
                                        Manage Holds Console
                                    </Button>
                                    <Button
                                        render={<Link href={`/administrators/students/${student.id}`} />}
                                        nativeButton={false}
                                        size="sm"
                                        className="h-8 gap-1.5 text-xs"
                                    >
                                        Open Grade Clearance
                                    </Button>
                                </div>
                            </div>
                        </TabsContent>

                        <TabsContent value="portal" className="mt-0 space-y-3">
                            <StudentPortalAccountCard
                                key={student.id}
                                studentId={student.id}
                                studentEmail={student.email}
                                portalAccount={student.portal_account}
                                compact
                                onUpdated={() => router.reload()}
                            />
                            {!student.email && (
                                <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                                    <KeyRound className="size-3.5" />
                                    Add an email address on the student record to provision a portal login.
                                </p>
                            )}
                        </TabsContent>
                    </div>
                </Tabs>
            </SheetContent>
        </Sheet>

            <Dialog open={rejectTarget !== null} onOpenChange={(nextOpen) => !nextOpen && setRejectTarget(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reject {rejectTarget?.slot.label}</DialogTitle>
                        <DialogDescription>
                            Choose or enter a clear rejection reason so the student knows what needs to be re-uploaded.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3 py-2">
                        <div className="flex flex-wrap gap-1.5">
                            {DOCUMENT_REJECTION_PRESETS.map((preset) => (
                                <button
                                    key={preset}
                                    type="button"
                                    onClick={() => setRejectReason(preset)}
                                    className={cn(
                                        "border-border hover:bg-muted rounded-md border px-2 py-1 text-left text-[11px] transition-colors",
                                        rejectReason === preset && "border-primary bg-primary/10 text-primary font-medium",
                                    )}
                                >
                                    {preset}
                                </button>
                            ))}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="inspector-reject-reason" className="text-xs">
                                Rejection Reason
                            </Label>
                            <Textarea
                                id="inspector-reject-reason"
                                value={rejectReason}
                                onChange={(e) => setRejectReason(e.target.value)}
                                placeholder="Explain what needs to be corrected..."
                                rows={3}
                            />
                        </div>

                        <label className="flex items-center gap-2 text-xs">
                            <Checkbox
                                checked={notifyStudentOnReject}
                                onCheckedChange={(checked) => setNotifyStudentOnReject(Boolean(checked))}
                            />
                            <span>Email &amp; notify student with re-upload link</span>
                        </label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setRejectTarget(null)}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={rejectReason.trim() === ""}
                            onClick={() => {
                                if (!rejectTarget) return;
                                const target = rejectTarget;
                                const reason = rejectReason.trim();
                                const notify = notifyStudentOnReject;
                                setRejectTarget(null);
                                setRejectReason("");
                                void handleInlineReview(target.slot, "reject", {
                                    reason,
                                    notifyStudent: notify,
                                    studentId: target.studentId,
                                });
                            }}
                        >
                            Reject Document
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
