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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import axios from "axios";
import {
    AlertCircle,
    Archive,
    CheckCircle2,
    Clock,
    Download,
    ExternalLink,
    Eye,
    FileCheck2,
    FileText,
    FolderOpen,
    History,
    Loader2,
    Mail,
    Paperclip,
    Plus,
    RefreshCw,
    ShieldOff,
    Trash2,
    UploadCloud,
    XCircle,
} from "lucide-react";
import { useRef, useState } from "react";
import { toast } from "sonner";
import {
    DOCUMENT_REJECTION_PRESETS,
    documentStatusMeta,
    type DocumentDossier,
    type DocumentSlot,
    type DynamicDocument,
} from "../shared-types";
import { DocumentPreviewViewer } from "./document-preview-viewer";

interface DocumentStudioCardsProps {
    studentId: number;
    studentName: string;
    dossier: DocumentDossier;
    exportZipUrl?: string | null;
    compact?: boolean;
    onDossierUpdated?: (next: DocumentDossier) => void;
}

const MAX_REVIEW_REASON_LENGTH = 500;

const DYNAMIC_PRESET_TYPES = [
    "Medical Certificate",
    "Scholarship Award Letter",
    "Parental Consent",
    "Affidavit / Undertaking",
    "NBI / Police Clearance",
    "Marriage Certificate",
    "Other",
] as const;

function formatBytes(bytes: number | null | undefined): string {
    if (!bytes) return "";
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export function DocumentStudioCards({
    studentId,
    studentName,
    dossier: initialDossier,
    exportZipUrl,
    compact = false,
    onDossierUpdated,
}: DocumentStudioCardsProps) {
    const [dossier, setDossier] = useState<DocumentDossier>(initialDossier);
    const [busySlot, setBusySlot] = useState<string | null>(null);
    const [dragSlot, setDragSlot] = useState<string | null>(null);
    const [previewTarget, setPreviewTarget] = useState<{
        title: string;
        fileName: string | null;
        mimeType: string | null;
        previewUrl: string | null;
        downloadUrl: string | null;
    } | null>(null);
    const [reviewModal, setReviewModal] = useState<{
        slot: DocumentSlot;
        action: "reject" | "waive";
    } | null>(null);
    const [reviewReason, setReviewReason] = useState("");
    const [reviewError, setReviewError] = useState<string | null>(null);
    const [notifyStudent, setNotifyStudent] = useState(true);
    const [historySlot, setHistorySlot] = useState<DocumentSlot | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<
        | { kind: "fixed"; slot: DocumentSlot }
        | { kind: "dynamic"; doc: DynamicDocument }
        | null
    >(null);
    const [deletingDocument, setDeletingDocument] = useState(false);

    // Additional document upload modal
    const [dynamicOpen, setDynamicOpen] = useState(false);
    const [dynamicType, setDynamicType] = useState<string>(DYNAMIC_PRESET_TYPES[0]);
    const [dynamicCustomType, setDynamicCustomType] = useState("");
    const [dynamicFile, setDynamicFile] = useState<File | null>(null);
    const [dynamicUploading, setDynamicUploading] = useState(false);
    const [sendingReminder, setSendingReminder] = useState(false);

    const fileInputRefs = useRef<Record<string, HTMLInputElement | null>>({});

    const applyNextDossier = (next?: DocumentDossier) => {
        if (!next) return;
        setDossier(next);
        onDossierUpdated?.(next);
    };

    const handleUploadSlot = async (slot: DocumentSlot, file: File) => {
        setBusySlot(slot.key);
        try {
            const formData = new FormData();
            formData.append("document_type", slot.key);
            formData.append("file", file);
            formData.append("auto_verify", "1");

            const response = await axios.post(
                `/administrators/students/${studentId}/documents/fixed`,
                formData,
                { headers: { Accept: "application/json", "Content-Type": "multipart/form-data" } },
            );
            applyNextDossier(response.data?.dossier);
            toast.success(`${slot.label} uploaded and verified.`);
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
            const firstErr = err.response?.data?.errors ? Object.values(err.response.data.errors)[0]?.[0] : null;
            toast.error(firstErr || err.response?.data?.message || `Failed to upload ${slot.label}.`);
        } finally {
            setBusySlot(null);
        }
    };

    const handleReviewAction = async (
        slot: DocumentSlot,
        action: "verify" | "reject" | "physical" | "waive" | "reset",
        reason?: string,
        notify = true,
    ): Promise<{ ok: boolean; error?: string }> => {
        setBusySlot(slot.key);
        try {
            const response = await axios.post(
                `/administrators/students/${studentId}/documents/fixed/${slot.key}/review`,
                {
                    action,
                    reason: reason || null,
                    notify_student: notify,
                    ...(action === "verify" ? { expected_file_revision: slot.file_revision ?? undefined } : {}),
                },
                { headers: { Accept: "application/json" } },
            );
            applyNextDossier(response.data?.dossier);
            toast.success(response.data?.message || "Document status updated.");
            return { ok: true };
        } catch (error: unknown) {
            const err = error as {
                response?: {
                    status?: number;
                    data?: { message?: string; errors?: Record<string, string[]>; dossier?: DocumentDossier };
                };
            };
            if (err.response?.data?.dossier) {
                applyNextDossier(err.response.data.dossier);
            }
            const fieldError =
                err.response?.data?.errors?.expected_file_revision?.[0] ??
                err.response?.data?.errors?.reason?.[0] ??
                (err.response?.data?.errors ? Object.values(err.response.data.errors)[0]?.[0] : null);
            const message = fieldError || err.response?.data?.message || "Failed to update document status.";
            toast.error(message);
            return { ok: false, error: message };
        } finally {
            setBusySlot(null);
        }
    };

    const handleSubmitReviewModal = async () => {
        if (!reviewModal) return;
        const trimmedReason = reviewReason.trim();
        if (trimmedReason.length === 0) {
            setReviewError(
                reviewModal.action === "reject"
                    ? "Please enter a rejection reason."
                    : "Please enter a waiver reason.",
            );
            return;
        }
        if (trimmedReason.length > MAX_REVIEW_REASON_LENGTH) {
            setReviewError(`Reason must be ${MAX_REVIEW_REASON_LENGTH} characters or fewer.`);
            return;
        }

        setReviewError(null);
        const result = await handleReviewAction(
            reviewModal.slot,
            reviewModal.action,
            trimmedReason,
            notifyStudent,
        );
        if (result.ok) {
            setReviewModal(null);
            setReviewReason("");
            setReviewError(null);
        } else {
            setReviewError(result.error || "Failed to update document status.");
        }
    };

    const handleConfirmDelete = async () => {
        if (!deleteTarget || deletingDocument) return;
        setDeletingDocument(true);
        try {
            if (deleteTarget.kind === "fixed") {
                await handleRemoveFixed(deleteTarget.slot);
            } else {
                await handleDeleteDynamic(deleteTarget.doc);
            }
            setDeleteTarget(null);
        } finally {
            setDeletingDocument(false);
        }
    };

    const handleRemoveFixed = async (slot: DocumentSlot) => {
        setBusySlot(slot.key);
        try {
            const response = await axios.delete(
                `/administrators/students/${studentId}/documents/fixed/${slot.key}`,
                { headers: { Accept: "application/json" } },
            );
            applyNextDossier(response.data?.dossier);
            toast.success(`${slot.label} removed.`);
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to remove document.");
        } finally {
            setBusySlot(null);
        }
    };

    const handleUploadDynamic = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!dynamicFile) return;
        const resolvedType = dynamicType === "Other" ? dynamicCustomType.trim() || "Additional Document" : dynamicType;

        setDynamicUploading(true);
        try {
            const formData = new FormData();
            formData.append("type", resolvedType);
            formData.append("file", dynamicFile);

            const response = await axios.post(
                `/administrators/students/${studentId}/documents/dynamic`,
                formData,
                { headers: { Accept: "application/json", "Content-Type": "multipart/form-data" } },
            );
            applyNextDossier(response.data?.dossier);
            toast.success(`${resolvedType} added to student vault.`);
            setDynamicFile(null);
            setDynamicCustomType("");
            setDynamicOpen(false);
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to upload additional document.");
        } finally {
            setDynamicUploading(false);
        }
    };

    const handleDeleteDynamic = async (doc: DynamicDocument) => {
        try {
            const response = await axios.delete(
                `/administrators/students/${studentId}/documents/dynamic/${doc.id}`,
                { headers: { Accept: "application/json" } },
            );
            applyNextDossier(response.data?.dossier);
            toast.success(`Deleted ${doc.file_name}.`);
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to delete document.");
        }
    };

    const handleSendReminder = async () => {
        setSendingReminder(true);
        try {
            const response = await axios.post(
                "/administrators/students/documents/bulk-remind",
                { student_ids: [studentId] },
                { headers: { Accept: "application/json" } },
            );
            toast.success(response.data?.message || "Missing-document reminder sent.");
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Could not send reminder.");
        } finally {
            setSendingReminder(false);
        }
    };

    const totalRequired = dossier.summary.total_required ?? dossier.summary.required_total ?? 0;

    return (
        <div className="space-y-4">
            {/* Compliance Progress Header */}
            <Card className="border-border/70">
                <CardContent className={cn("flex flex-col gap-4", compact ? "p-4" : "p-5")}>
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-semibold">Document Compliance</span>
                                <Badge
                                    variant="outline"
                                    className={cn(
                                        "font-mono text-xs tabular-nums",
                                        dossier.summary.is_complete
                                            ? "border-emerald-500/35 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                            : "border-amber-500/35 bg-amber-500/10 text-amber-700 dark:text-amber-300",
                                    )}
                                >
                                    {dossier.summary.compliant_count} / {totalRequired} Ready ({dossier.summary.compliance_percent}%)
                                </Badge>
                                {dossier.summary.pending_review_count > 0 && (
                                    <Badge variant="outline" className="border-amber-500/35 bg-amber-500/10 text-amber-700 dark:text-amber-300 text-xs">
                                        <Clock className="mr-1 size-3" />
                                        {dossier.summary.pending_review_count} Pending Review
                                    </Badge>
                                )}
                                {dossier.summary.rejected_count > 0 && (
                                    <Badge variant="outline" className="border-rose-500/35 bg-rose-500/10 text-rose-700 dark:text-rose-300 text-xs">
                                        <XCircle className="mr-1 size-3" />
                                        {dossier.summary.rejected_count} Rejected
                                    </Badge>
                                )}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Verifying or waiving slots automatically syncs matching active enrollment requirements.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {!dossier.summary.is_complete && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5 text-xs"
                                    disabled={sendingReminder}
                                    onClick={() => void handleSendReminder()}
                                >
                                    {sendingReminder ? <Loader2 className="size-3.5 animate-spin" /> : <Mail className="size-3.5" />}
                                    Remind Student
                                </Button>
                            )}
                            {exportZipUrl && (
                                <Button
                                    render={<a href={exportZipUrl} />}
                                    nativeButton={false}
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5 text-xs"
                                >
                                    <Archive className="size-3.5" />
                                    ZIP Dossier
                                </Button>
                            )}
                        </div>
                    </div>
                    <Progress value={dossier.summary.compliance_percent} className="h-2" />
                </CardContent>
            </Card>

            {/* Studio Tabs */}
            <Tabs defaultValue="checklist" className="w-full">
                <TabsList className="w-full justify-start overflow-x-auto">
                    <TabsTrigger value="checklist" className="gap-1.5 text-xs">
                        <FileCheck2 className="size-3.5" />
                        Required Checklist ({dossier.slots.length})
                    </TabsTrigger>
                    <TabsTrigger value="additional" className="gap-1.5 text-xs">
                        <FolderOpen className="size-3.5" />
                        Additional Vault ({dossier.dynamic_documents.length})
                    </TabsTrigger>
                    <TabsTrigger value="official" className="gap-1.5 text-xs">
                        <FileText className="size-3.5" />
                        Official Registrar PDFs ({dossier.official_documents.length})
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="checklist" className="mt-4">
                    <div className={cn("grid grid-cols-1 gap-3.5", compact ? "md:grid-cols-2" : "md:grid-cols-2 xl:grid-cols-3")}>
                        {dossier.slots.map((slot) => {
                            const statusMeta = documentStatusMeta(slot.status);
                            const isBusy = busySlot === slot.key;
                            const isDragging = dragSlot === slot.key;

                            return (
                                <div
                                    key={slot.key}
                                    onDragOver={(e) => {
                                        e.preventDefault();
                                        setDragSlot(slot.key);
                                    }}
                                    onDragLeave={() => setDragSlot((prev) => (prev === slot.key ? null : prev))}
                                    onDrop={(e) => {
                                        e.preventDefault();
                                        setDragSlot(null);
                                        const file = e.dataTransfer.files?.[0];
                                        if (file) void handleUploadSlot(slot, file);
                                    }}
                                    className={cn(
                                        "border-border/70 bg-card flex flex-col justify-between rounded-xl border p-4 transition-colors",
                                        isDragging && "border-primary bg-primary/5 border-dashed",
                                    )}
                                >
                                    <div className="space-y-2.5">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <h4 className="text-sm font-semibold">{slot.label}</h4>
                                                    {slot.required ? (
                                                        <span className="text-primary text-[10px] font-semibold uppercase">Required</span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-[10px] uppercase">Optional</span>
                                                    )}
                                                </div>
                                                <p className="text-muted-foreground mt-0.5 text-[11px] leading-snug">{slot.description}</p>
                                            </div>
                                            <Badge variant="outline" className={cn("shrink-0 text-[11px]", statusMeta.className)}>
                                                {statusMeta.shortLabel}
                                            </Badge>
                                        </div>

                                        {/* File summary or Drop Zone */}
                                        <input
                                            ref={(el) => {
                                                fileInputRefs.current[slot.key] = el;
                                            }}
                                            type="file"
                                            className="hidden"
                                            accept={slot.image_only ? ".jpg,.jpeg,.png,.webp" : ".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"}
                                            onChange={(e) => {
                                                const file = e.target.files?.[0];
                                                if (file) void handleUploadSlot(slot, file);
                                                e.currentTarget.value = "";
                                            }}
                                        />

                                        {slot.has_file ? (
                                            <div className="bg-muted/35 border-border/60 flex items-center justify-between gap-2 rounded-lg border px-2.5 py-2">
                                                <div className="min-w-0">
                                                    <p className="truncate text-xs font-medium">{slot.file_name || "Attached file"}</p>
                                                    <p className="text-muted-foreground text-[10px]">
                                                        {[
                                                            formatBytes(slot.file_size),
                                                            slot.verified_at_label
                                                                ? `Verified ${slot.verified_at_label}`
                                                                : slot.uploaded_at_label
                                                                  ? `Uploaded ${slot.uploaded_at_label}`
                                                                  : null,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(" · ") || "Stored privately"}
                                                    </p>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-7"
                                                        title="Preview inline"
                                                        onClick={() =>
                                                            setPreviewTarget({
                                                                title: `${studentName} — ${slot.label}`,
                                                                fileName: slot.file_name,
                                                                mimeType: slot.mime_type,
                                                                previewUrl: slot.preview_url,
                                                                downloadUrl: slot.download_url,
                                                            })
                                                        }
                                                    >
                                                        <Eye className="size-3.5" />
                                                    </Button>
                                                    {slot.download_url && (
                                                        <Button
                                                            render={<a href={slot.download_url} />}
                                                            nativeButton={false}
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7"
                                                            title="Download"
                                                        >
                                                            <Download className="size-3.5" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ) : (
                                            <button
                                                type="button"
                                                disabled={isBusy}
                                                onClick={() => fileInputRefs.current[slot.key]?.click()}
                                                className="border-border hover:border-primary/60 hover:bg-muted/40 flex w-full flex-col items-center justify-center rounded-lg border border-dashed px-3 py-4 text-center transition-colors"
                                            >
                                                {isBusy ? (
                                                    <Loader2 className="text-primary size-4 animate-spin" />
                                                ) : (
                                                    <UploadCloud className="text-muted-foreground size-4" />
                                                )}
                                                <span className="mt-1 text-xs font-medium">Drop file or browse</span>
                                                <span className="text-muted-foreground text-[10px]">{slot.accepted}</span>
                                            </button>
                                        )}

                                        {slot.status === "rejected" && slot.rejection_reason && (
                                            <div className="flex items-start gap-1.5 rounded-md border border-rose-500/30 bg-rose-500/10 p-2 text-[11px] text-rose-700 dark:text-rose-300">
                                                <AlertCircle className="mt-0.5 size-3.5 shrink-0" />
                                                <span>{slot.rejection_reason}</span>
                                            </div>
                                        )}

                                        {slot.status === "waived" && slot.waiver_reason && (
                                            <div className="bg-muted/50 text-muted-foreground rounded-md p-2 text-[11px]">
                                                Waived: {slot.waiver_reason}
                                            </div>
                                        )}
                                    </div>

                                    {/* Slot Action Row */}
                                    <div className="border-border/60 mt-3 flex flex-wrap items-center gap-1.5 border-t pt-3">
                                        {slot.status !== "verified" && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant={slot.has_file ? "default" : "outline"}
                                                className="h-7 gap-1 px-2 text-[11px]"
                                                disabled={isBusy || !slot.has_file}
                                                title={!slot.has_file ? "Upload a file first before verifying" : "Verify uploaded document"}
                                                onClick={() => {
                                                    if (!slot.has_file) return;
                                                    void handleReviewAction(slot, "verify");
                                                }}
                                            >
                                                <CheckCircle2 className="size-3" />
                                                Verify
                                            </Button>
                                        )}

                                        {slot.status !== "physical_on_file" && !slot.has_file && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="h-7 gap-1 px-2 text-[11px]"
                                                disabled={isBusy}
                                                onClick={() => void handleReviewAction(slot, "physical")}
                                            >
                                                <FileCheck2 className="size-3" />
                                                Physical Copy
                                            </Button>
                                        )}

                                        {slot.has_file && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="h-7 gap-1 px-2 text-[11px]"
                                                disabled={isBusy}
                                                onClick={() => fileInputRefs.current[slot.key]?.click()}
                                            >
                                                <RefreshCw className="size-3" />
                                                Replace
                                            </Button>
                                        )}

                                        {slot.has_file && slot.status !== "rejected" && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="h-7 gap-1 px-2 text-[11px] text-rose-600 hover:text-rose-700 dark:text-rose-400"
                                                disabled={isBusy}
                                                onClick={() => {
                                                    setReviewReason(DOCUMENT_REJECTION_PRESETS[0]);
                                                    setReviewError(null);
                                                    setNotifyStudent(true);
                                                    setReviewModal({ slot, action: "reject" });
                                                }}
                                            >
                                                <XCircle className="size-3" />
                                                Reject
                                            </Button>
                                        )}

                                        {slot.status !== "waived" && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                className="h-7 gap-1 px-2 text-[11px]"
                                                disabled={isBusy}
                                                onClick={() => {
                                                    setReviewReason("");
                                                    setReviewError(null);
                                                    setReviewModal({ slot, action: "waive" });
                                                }}
                                            >
                                                <ShieldOff className="size-3" />
                                                Waive
                                            </Button>
                                        )}

                                        {slot.has_file && (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="text-muted-foreground hover:text-destructive ml-auto size-7"
                                                title="Remove file"
                                                disabled={isBusy}
                                                onClick={() => setDeleteTarget({ kind: "fixed", slot })}
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        )}

                                        {slot.history.length > 0 && (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className={cn("text-muted-foreground size-7", !slot.has_file && "ml-auto")}
                                                title="Audit history"
                                                onClick={() => setHistorySlot(slot)}
                                            >
                                                <History className="size-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </TabsContent>

                <TabsContent value="additional" className="mt-4 space-y-3">
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-muted-foreground text-xs">
                            Store supplemental records such as medical clearances, scholarship awards, or affidavits.
                        </p>
                        <Button type="button" size="sm" className="h-8 gap-1.5 text-xs" onClick={() => setDynamicOpen(true)}>
                            <Plus className="size-3.5" />
                            Add Document
                        </Button>
                    </div>

                    {dossier.dynamic_documents.length === 0 ? (
                        <Card className="border-border/70 border-dashed">
                            <CardContent className="flex flex-col items-center justify-center py-10 text-center">
                                <Paperclip className="text-muted-foreground/50 mb-2 size-8" />
                                <p className="text-sm font-medium">No additional documents uploaded</p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Attach medical records, transfer letters, or special permits here.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                            {dossier.dynamic_documents.map((doc) => (
                                <Card key={doc.id} className="border-border/70">
                                    <CardContent className="flex items-center justify-between gap-3 p-3.5">
                                        <div className="min-w-0">
                                            <Badge variant="secondary" className="mb-1 text-[10px]">
                                                {doc.type}
                                            </Badge>
                                            <p className="truncate text-sm font-medium">{doc.file_name}</p>
                                            <p className="text-muted-foreground text-[11px]">
                                                {[formatBytes(doc.file_size), doc.created_at_label].filter(Boolean).join(" · ")}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                title="Preview"
                                                onClick={() =>
                                                    setPreviewTarget({
                                                        title: `${studentName} — ${doc.type}`,
                                                        fileName: doc.file_name,
                                                        mimeType: doc.mime_type,
                                                        previewUrl: doc.preview_url,
                                                        downloadUrl: doc.url,
                                                    })
                                                }
                                            >
                                                <Eye className="size-4" />
                                            </Button>
                                            <Button
                                                render={<a href={doc.url} />}
                                                nativeButton={false}
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                title="Download"
                                            >
                                                <Download className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-destructive size-8"
                                                title="Delete"
                                                onClick={() => setDeleteTarget({ kind: "dynamic", doc })}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </TabsContent>

                <TabsContent value="official" className="mt-4">
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                        {dossier.official_documents.map((doc) => (
                            <Card key={doc.key} className="border-border/70">
                                <CardHeader className="p-4 pb-2">
                                    <CardTitle className="text-sm font-semibold">{doc.label}</CardTitle>
                                    <CardDescription className="text-xs">{doc.description}</CardDescription>
                                </CardHeader>
                                <CardContent className="p-4 pt-2">
                                    <Button
                                        render={<a href={doc.url} target="_blank" rel="noreferrer" />}
                                        nativeButton={false}
                                        variant="outline"
                                        size="sm"
                                        className="h-8 w-full gap-1.5 text-xs"
                                    >
                                        <ExternalLink className="size-3.5" />
                                        Open / Generate
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </TabsContent>
            </Tabs>

            {/* Inline Preview Lightbox */}
            <Dialog open={previewTarget !== null} onOpenChange={(open) => !open && setPreviewTarget(null)}>
                <DialogContent className="max-w-4xl p-4">
                    <DialogHeader className="sr-only">
                        <DialogTitle>{previewTarget?.title || "Document Preview"}</DialogTitle>
                        <DialogDescription>Inline student document viewer</DialogDescription>
                    </DialogHeader>
                    {previewTarget && (
                        <DocumentPreviewViewer
                            title={previewTarget.title}
                            fileName={previewTarget.fileName}
                            mimeType={previewTarget.mimeType}
                            previewUrl={previewTarget.previewUrl}
                            downloadUrl={previewTarget.downloadUrl}
                            className="h-[72vh]"
                        />
                    )}
                </DialogContent>
            </Dialog>

            {/* Reject / Waive Reason Dialog */}
            <Dialog
                open={reviewModal !== null}
                onOpenChange={(open) => {
                    if (!open && (!reviewModal || busySlot !== reviewModal.slot.key)) {
                        setReviewModal(null);
                        setReviewError(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {reviewModal?.action === "reject" ? `Reject ${reviewModal.slot.label}` : `Waive ${reviewModal?.slot.label}`}
                        </DialogTitle>
                        <DialogDescription>
                            {reviewModal?.action === "reject"
                                ? "Provide a clear reason so the student knows what to re-upload."
                                : "Record why this requirement is waived or not applicable for this student."}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3 py-2">
                        {reviewModal?.action === "reject" && (
                            <div className="flex flex-wrap gap-1.5">
                                {DOCUMENT_REJECTION_PRESETS.map((preset) => (
                                    <button
                                        key={preset}
                                        type="button"
                                        onClick={() => {
                                            setReviewReason(preset);
                                            setReviewError(null);
                                        }}
                                        className={cn(
                                            "border-border hover:bg-muted rounded-md border px-2 py-1 text-left text-[11px] transition-colors",
                                            reviewReason === preset && "border-primary bg-primary/10 text-primary font-medium",
                                        )}
                                    >
                                        {preset}
                                    </button>
                                ))}
                            </div>
                        )}

                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="doc-review-reason" className="text-xs">
                                    {reviewModal?.action === "reject" ? "Rejection Reason" : "Waiver Reason"}
                                </Label>
                                <span
                                    className={cn(
                                        "font-mono text-[11px] tabular-nums",
                                        reviewReason.trim().length > MAX_REVIEW_REASON_LENGTH
                                            ? "text-destructive font-medium"
                                            : "text-muted-foreground",
                                    )}
                                >
                                    {reviewReason.trim().length} / {MAX_REVIEW_REASON_LENGTH}
                                </span>
                            </div>
                            <Textarea
                                id="doc-review-reason"
                                value={reviewReason}
                                onChange={(e) => {
                                    const nextValue = e.target.value;
                                    setReviewReason(nextValue);
                                    if (nextValue.trim().length > MAX_REVIEW_REASON_LENGTH) {
                                        setReviewError(`Reason must be ${MAX_REVIEW_REASON_LENGTH} characters or fewer.`);
                                    } else {
                                        setReviewError(null);
                                    }
                                }}
                                placeholder={
                                    reviewModal?.action === "reject"
                                        ? "Explain what needs to be corrected..."
                                        : "e.g., Not a transferee student, or approved by Registrar"
                                }
                                rows={3}
                                aria-invalid={Boolean(reviewError)}
                                aria-describedby={reviewError ? "doc-review-reason-error" : undefined}
                            />
                            {reviewError && (
                                <p id="doc-review-reason-error" role="alert" className="text-destructive text-xs">
                                    {reviewError}
                                </p>
                            )}
                        </div>

                        {reviewModal?.action === "reject" && (
                            <label className="flex items-center gap-2 text-xs">
                                <Checkbox checked={notifyStudent} onCheckedChange={(checked) => setNotifyStudent(Boolean(checked))} />
                                <span>Email &amp; notify student with re-upload link</span>
                            </label>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={Boolean(reviewModal && busySlot === reviewModal.slot.key)}
                            onClick={() => {
                                setReviewModal(null);
                                setReviewError(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant={reviewModal?.action === "reject" ? "destructive" : "default"}
                            disabled={
                                !reviewModal ||
                                busySlot === reviewModal.slot.key ||
                                reviewReason.trim().length === 0 ||
                                reviewReason.trim().length > MAX_REVIEW_REASON_LENGTH
                            }
                            onClick={() => void handleSubmitReviewModal()}
                        >
                            {reviewModal && busySlot === reviewModal.slot.key && (
                                <Loader2 className="mr-1.5 size-3.5 animate-spin" />
                            )}
                            {reviewModal?.action === "reject" ? "Reject Document" : "Confirm Waiver"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Permanent Delete Confirmation Dialog */}
            <AlertDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open && !deletingDocument) {
                        setDeleteTarget(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Permanently delete student document?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {deleteTarget?.kind === "fixed" ? (
                                <>
                                    This will permanently delete{" "}
                                    <span className="text-foreground font-medium">
                                        {deleteTarget.slot.file_name || deleteTarget.slot.label}
                                    </span>{" "}
                                    from the <span className="text-foreground font-medium">{deleteTarget.slot.label}</span>{" "}
                                    slot for {studentName}. This action cannot be undone.
                                </>
                            ) : deleteTarget?.kind === "dynamic" ? (
                                <>
                                    This will permanently delete{" "}
                                    <span className="text-foreground font-medium">{deleteTarget.doc.file_name}</span> (
                                    {deleteTarget.doc.type}) from {studentName}&apos;s vault. This action cannot be undone.
                                </>
                            ) : null}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deletingDocument}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={deletingDocument}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={(e) => {
                                e.preventDefault();
                                void handleConfirmDelete();
                            }}
                        >
                            {deletingDocument && <Loader2 className="mr-1.5 size-3.5 animate-spin" />}
                            Delete Document
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Audit History Dialog */}
            <Dialog open={historySlot !== null} onOpenChange={(open) => !open && setHistorySlot(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{historySlot?.label} — Audit History</DialogTitle>
                        <DialogDescription>Recent upload, verification, and status events for this document slot.</DialogDescription>
                    </DialogHeader>
                    <div className="max-h-72 space-y-2.5 overflow-y-auto py-2">
                        {(historySlot?.history ?? [])
                            .slice()
                            .reverse()
                            .map((entry, idx) => (
                                <div key={idx} className="border-border/70 rounded-lg border p-2.5 text-xs">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-semibold capitalize">{entry.event.replace(/_/g, " ")}</span>
                                        <span className="text-muted-foreground text-[11px]">
                                            {entry.at ? new Date(entry.at).toLocaleString() : ""}
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground mt-0.5 text-[11px]">By {entry.actor || "System"}</p>
                                    {entry.reason && <p className="mt-1 text-[11px]">{entry.reason}</p>}
                                    {entry.previous_file_name && (
                                        <p className="text-muted-foreground mt-1 text-[11px]">Previous: {entry.previous_file_name}</p>
                                    )}
                                </div>
                            ))}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Additional Document Upload Modal */}
            <Dialog open={dynamicOpen} onOpenChange={setDynamicOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add Document to Student Vault</DialogTitle>
                        <DialogDescription>Upload an additional file for {studentName}.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleUploadDynamic} className="space-y-4">
                        <div className="space-y-1.5">
                            <Label className="text-xs">Document Category</Label>
                            <div className="flex flex-wrap gap-1.5">
                                {DYNAMIC_PRESET_TYPES.map((preset) => (
                                    <button
                                        key={preset}
                                        type="button"
                                        onClick={() => setDynamicType(preset)}
                                        className={cn(
                                            "border-border rounded-md border px-2.5 py-1 text-xs transition-colors",
                                            dynamicType === preset
                                                ? "border-primary bg-primary/10 text-primary font-medium"
                                                : "hover:bg-muted",
                                        )}
                                    >
                                        {preset}
                                    </button>
                                ))}
                            </div>
                            {dynamicType === "Other" && (
                                <Input
                                    value={dynamicCustomType}
                                    onChange={(e) => setDynamicCustomType(e.target.value)}
                                    placeholder="Enter custom category..."
                                    className="mt-2 h-9 text-xs"
                                    required
                                />
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="dynamic-file-input" className="text-xs">
                                File (max 5 MB)
                            </Label>
                            <Input
                                id="dynamic-file-input"
                                type="file"
                                required
                                onChange={(e) => setDynamicFile(e.target.files?.[0] ?? null)}
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDynamicOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={!dynamicFile || dynamicUploading}>
                                {dynamicUploading && <Loader2 className="mr-1.5 size-3.5 animate-spin" />}
                                Upload
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
