import PortalLayout from "@/components/portal-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { cn } from "@/lib/utils";
import { type User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    ArrowLeft,
    CheckCircle2,
    CircleAlert,
    Download,
    Eye,
    FileCheck2,
    FileText,
    Loader2,
    ShieldCheck,
    Upload,
} from "lucide-react";
import { useRef, useState } from "react";
import { toast } from "sonner";

interface StudentDocumentSlot {
    key: string;
    label: string;
    description: string;
    accepted: string;
    status: "on_file" | "missing";
    file_name: string | null;
    download_url: string | null;
    preview_url: string | null;
}

interface StudentDocumentsPageProps {
    user: User;
    student: {
        id: number;
        student_id: string | number | null;
        full_name: string;
        course: string | null;
    } | null;
    documents: StudentDocumentSlot[];
    summary: {
        uploaded: number;
        total: number;
    };
}

const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024;

export default function StudentDocumentsIndex({ user, student, documents, summary }: StudentDocumentsPageProps) {
    const { props } = usePage<{ flash?: { success?: string; error?: string }; success?: string; error?: string }>();
    const [selectedFiles, setSelectedFiles] = useState<Record<string, File | null>>({});
    const [uploadingKey, setUploadingKey] = useState<string | null>(null);
    const fileInputRefs = useRef<Record<string, HTMLInputElement | null>>({});

    const completionPercent = summary.total > 0 ? Math.round((summary.uploaded / summary.total) * 100) : 0;
    const flashSuccess = props.flash?.success ?? props.success;
    const flashError = props.flash?.error ?? props.error;

    const handleFileSelect = (key: string, file: File | null) => {
        if (!file) {
            setSelectedFiles((prev) => ({ ...prev, [key]: null }));
            return;
        }

        if (file.size > MAX_FILE_SIZE_BYTES) {
            toast.error("File must be 5 MB or smaller.");
            if (fileInputRefs.current[key]) {
                fileInputRefs.current[key]!.value = "";
            }
            return;
        }

        setSelectedFiles((prev) => ({ ...prev, [key]: file }));
    };

    const handleUpload = (slot: StudentDocumentSlot) => {
        const file = selectedFiles[slot.key];
        if (!file) {
            toast.error(`Select a file for ${slot.label} first.`);
            return;
        }

        const formData = new FormData();
        formData.append("document_type", slot.key);
        formData.append("file", file);

        setUploadingKey(slot.key);
        router.post("/student/documents", formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${slot.label} uploaded to your registrar vault.`);
                setSelectedFiles((prev) => ({ ...prev, [slot.key]: null }));
                if (fileInputRefs.current[slot.key]) {
                    fileInputRefs.current[slot.key]!.value = "";
                }
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(typeof firstError === "string" ? firstError : `Could not upload ${slot.label}.`);
            },
            onFinish: () => {
                setUploadingKey(null);
            },
        });
    };

    return (
        <PortalLayout user={user}>
            <Head title="Registrar Document Vault" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 pb-10">
                {/* Guided Campus Canvas Header */}
                <div className="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card/85 p-5 shadow-xs sm:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="secondary" className="gap-1 rounded-full text-xs font-semibold">
                                    <FileCheck2 className="size-3.5 text-primary" />
                                    Registrar Document Vault
                                </Badge>
                                <Badge
                                    variant={summary.uploaded >= summary.total ? "default" : "outline"}
                                    className="rounded-full font-mono text-xs tabular-nums"
                                >
                                    {summary.uploaded} / {summary.total} on file
                                </Badge>
                            </div>
                            <h1 className="text-foreground text-2xl font-bold tracking-tight">My Registrar Documents</h1>
                            <p className="text-muted-foreground max-w-2xl text-sm leading-relaxed">
                                Upload and review your core admission and registrar credentials. Files are stored securely in private school storage and
                                linked directly to your student record.
                            </p>
                        </div>

                        <Button render={<Link href="/student/dashboard" />} nativeButton={false} variant="outline" size="sm" className="gap-1.5">
                            <ArrowLeft className="size-4" />
                            Back to Dashboard
                        </Button>
                    </div>

                    <div className="space-y-2 rounded-xl border border-border/60 bg-background/70 p-3.5">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-foreground">Document Completeness</span>
                            <span className="font-mono font-semibold text-primary tabular-nums">{completionPercent}%</span>
                        </div>
                        <Progress value={completionPercent} className="h-2" />
                        <div className="flex flex-wrap items-center justify-between gap-2 pt-0.5 text-[11px] text-muted-foreground">
                            <span className="inline-flex items-center gap-1.5">
                                <ShieldCheck className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                Encrypted private storage · accessible only by you and authorized registrar staff
                            </span>
                            {student?.student_id && (
                                <span className="font-mono">
                                    Student ID: <strong className="text-foreground">{student.student_id}</strong>
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {flashSuccess && (
                    <div className="flex items-center gap-2.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
                        <CheckCircle2 className="size-4 shrink-0" />
                        <span>{flashSuccess}</span>
                    </div>
                )}

                {flashError && (
                    <div className="flex items-center gap-2.5 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        <CircleAlert className="size-4 shrink-0" />
                        <span>{flashError}</span>
                    </div>
                )}

                {!student ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center">
                            <CircleAlert className="size-8 text-muted-foreground" />
                            <div className="space-y-1">
                                <h2 className="text-base font-semibold">No linked student profile yet</h2>
                                <p className="text-muted-foreground max-w-md text-sm">
                                    Your account is not yet linked to a registrar student profile. Please contact the registrar&apos;s office or submit a
                                    support ticket for assistance.
                                </p>
                            </div>
                            <Button render={<Link href="/student/help" />} nativeButton={false} variant="outline" size="sm">
                                Contact Support
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {documents.map((slot) => {
                            const isOnFile = slot.status === "on_file";
                            const selectedFile = selectedFiles[slot.key] ?? null;
                            const isUploading = uploadingKey === slot.key;

                            return (
                                <Card
                                    key={slot.key}
                                    className={cn(
                                        "flex flex-col justify-between border-border/70 bg-card/85 shadow-2xs transition-colors",
                                        isOnFile && "border-emerald-500/25",
                                    )}
                                >
                                    <CardHeader className="space-y-2 pb-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-2.5">
                                                <div
                                                    className={cn(
                                                        "flex size-9 shrink-0 items-center justify-center rounded-xl",
                                                        isOnFile
                                                            ? "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300"
                                                            : "bg-amber-500/15 text-amber-700 dark:text-amber-300",
                                                    )}
                                                >
                                                    <FileText className="size-4" />
                                                </div>
                                                <div>
                                                    <CardTitle className="text-base leading-tight">{slot.label}</CardTitle>
                                                    <CardDescription className="mt-0.5 text-xs">{slot.description}</CardDescription>
                                                </div>
                                            </div>
                                            <Badge
                                                variant={isOnFile ? "secondary" : "outline"}
                                                className={cn(
                                                    "shrink-0 rounded-full text-[11px] font-semibold",
                                                    isOnFile
                                                        ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                        : "border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300",
                                                )}
                                            >
                                                {isOnFile ? "On File" : "Missing"}
                                            </Badge>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="space-y-4">
                                        {isOnFile && slot.download_url && (
                                            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border/70 bg-background/70 px-3 py-2.5">
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-[11px] font-medium text-muted-foreground">Current file on record</p>
                                                    <p className="truncate font-mono text-xs font-medium text-foreground" title={slot.file_name ?? undefined}>
                                                        {slot.file_name}
                                                    </p>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1.5">
                                                    {slot.preview_url && (
                                                        <Button
                                                            render={<a href={slot.preview_url} target="_blank" rel="noreferrer" />}
                                                            nativeButton={false}
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 gap-1 px-2.5 text-xs"
                                                        >
                                                            <Eye className="size-3.5" />
                                                            Preview
                                                        </Button>
                                                    )}
                                                    <Button
                                                        render={<a href={slot.download_url} />}
                                                        nativeButton={false}
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-8 gap-1 px-2.5 text-xs"
                                                    >
                                                        <Download className="size-3.5" />
                                                        Download
                                                    </Button>
                                                </div>
                                            </div>
                                        )}

                                        <div className="space-y-2.5 rounded-xl border border-dashed border-border/80 bg-muted/20 p-3.5">
                                            <div className="space-y-1">
                                                <label htmlFor={`doc-upload-${slot.key}`} className="text-xs font-semibold text-foreground">
                                                    {isOnFile ? "Replace document" : "Upload document"}
                                                </label>
                                                <p className="text-[11px] text-muted-foreground">{slot.accepted}</p>
                                            </div>

                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                                <input
                                                    ref={(el) => {
                                                        fileInputRefs.current[slot.key] = el;
                                                    }}
                                                    id={`doc-upload-${slot.key}`}
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                                                    disabled={isUploading}
                                                    onChange={(e) => handleFileSelect(slot.key, e.target.files?.[0] ?? null)}
                                                    className="block w-full cursor-pointer text-xs text-muted-foreground file:mr-3 file:cursor-pointer file:rounded-lg file:border file:border-border file:bg-background file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-foreground hover:file:bg-muted"
                                                />
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={!selectedFile || isUploading}
                                                    onClick={() => handleUpload(slot)}
                                                    className="shrink-0 gap-1.5"
                                                >
                                                    {isUploading ? (
                                                        <>
                                                            <Loader2 className="size-3.5 animate-spin" />
                                                            Uploading...
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Upload className="size-3.5" />
                                                            {isOnFile ? "Replace" : "Upload"}
                                                        </>
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
