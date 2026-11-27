import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
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
    Save,
    ShieldAlert,
    UploadCloud,
    XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { getInitials, getStatusColor, type Student } from "../columns";
import {
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

    // Lazy dossier loading for Documents tab
    const [dossier, setDossier] = useState<DocumentDossier | null>(null);
    const [dossierLoading, setDossierLoading] = useState(false);
    const [busySlot, setBusySlot] = useState<string | null>(null);
    const [previewSlot, setPreviewSlot] = useState<DocumentSlot | null>(null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [uploadTargetKey, setUploadTargetKey] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setActiveTab(initialTab);
        }
    }, [open, initialTab]);

    useEffect(() => {
        if (!student) return;
        setStatus(student.status ?? "enrolled");
        setYearLevel(String(student.year_level ?? 1));
        setDossier(null);
        setPreviewSlot(null);
    }, [student]);

    const loadDossier = useCallback(async (studentId: number) => {
        setDossierLoading(true);
        try {
            const response = await axios.get(`/administrators/students/${studentId}/documents/dossier`, {
                headers: { Accept: "application/json" },
            });
            setDossier(response.data?.dossier ?? null);
        } catch {
            toast.error("Unable to load student documents.");
        } finally {
            setDossierLoading(false);
        }
    }, []);

    useEffect(() => {
        if (open && activeTab === "documents" && student && !dossier && !dossierLoading) {
            void loadDossier(student.id);
        }
    }, [open, activeTab, student, dossier, dossierLoading, loadDossier]);

    useEffect(() => {
        if (!open) return;
        const onKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            if (target && ["INPUT", "TEXTAREA", "SELECT"].includes(target.tagName)) return;
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
    }, [open, hasPrev, hasNext, onPrev, onNext]);

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

    const handleInlineReview = async (slot: DocumentSlot, action: "verify" | "physical" | "reject") => {
        setBusySlot(slot.key);
        try {
            const response = await axios.post(
                `/administrators/students/${student.id}/documents/fixed/${slot.key}/review`,
                {
                    action,
                    reason: action === "reject" ? "Blurry or unreadable scan — please upload a clearer copy." : null,
                    notify_student: true,
                },
                { headers: { Accept: "application/json" } },
            );
            setDossier(response.data?.dossier ?? null);
            toast.success(response.data?.message || "Document updated.");
            router.reload({ only: ["students", "triage_counts"] });
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to update document.");
        } finally {
            setBusySlot(null);
        }
    };

    const handleInlineUpload = async (key: string, file: File) => {
        setBusySlot(key);
        try {
            const formData = new FormData();
            formData.append("document_type", key);
            formData.append("file", file);
            formData.append("auto_verify", "1");
            const response = await axios.post(`/administrators/students/${student.id}/documents/fixed`, formData, {
                headers: { Accept: "application/json", "Content-Type": "multipart/form-data" },
            });
            setDossier(response.data?.dossier ?? null);
            toast.success("Document uploaded and verified.");
            router.reload({ only: ["students", "triage_counts"] });
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to upload file.");
        } finally {
            setBusySlot(null);
        }
    };

    return (
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
                                    if (file && uploadTargetKey) {
                                        void handleInlineUpload(uploadTargetKey, file);
                                    }
                                    e.currentTarget.value = "";
                                }}
                            />

                            {dossierLoading ? (
                                <div className="flex items-center justify-center py-12">
                                    <Loader2 className="text-muted-foreground size-5 animate-spin" />
                                </div>
                            ) : dossier ? (
                                <>
                                    <div className="space-y-1.5">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="font-semibold">
                                                Checklist Readiness ({dossier.summary.compliant_count}/
                                                {dossier.summary.total_required ?? dossier.summary.required_total ?? 0})
                                            </span>
                                            <Link
                                                href={`/administrators/students/${student.id}/documents`}
                                                className="text-primary font-medium hover:underline"
                                            >
                                                Open Full Studio →
                                            </Link>
                                        </div>
                                        <Progress value={dossier.summary.compliance_percent} className="h-1.5" />
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
                                                    onClick={() => setPreviewSlot(null)}
                                                >
                                                    Close Preview
                                                </Button>
                                            </div>
                                            <DocumentPreviewViewer
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
                                        {dossier.slots.map((slot) => {
                                            const meta = documentStatusMeta(slot.status);
                                            const isBusy = busySlot === slot.key;

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
                                                                onClick={() => setPreviewSlot(slot)}
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
                                                                onClick={() => void handleInlineReview(slot, "reject")}
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
                                                                setUploadTargetKey(slot.key);
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
                                <p className="text-muted-foreground py-8 text-center text-xs">Unable to load document dossier.</p>
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
    );
}
