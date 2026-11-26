import { Badge } from "@/components/reui/badge";
import {
    Frame,
    FrameDescription,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Link } from "@inertiajs/react";
import {
    Award,
    ClipboardCheck,
    Download,
    ExternalLink,
    FileBadge,
    FileCheck2,
    FileSpreadsheet,
    FileText,
    FolderOpen,
    GraduationCap,
    Loader2,
    Mail,
    RefreshCw,
} from "lucide-react";
import { formatShortDate } from "../enrollment-utils";
import type {
    DocumentLinks,
    EnrollmentData,
    ShowEnrollmentPageProps,
} from "./types";

export interface DocumentsStudioPanelProps {
    enrollment: EnrollmentData;
    auth: ShowEnrollmentPageProps["auth"];
    documentLinks: DocumentLinks;
    onRegeneratePdf: () => void;
    regeneratingPdf: boolean;
    onResendAssessment: () => void;
    resendingAssessment: boolean;
}

function formatFileSize(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined || Number.isNaN(bytes) || bytes <= 0) {
        return "—";
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export function DocumentsStudioPanel({
    enrollment,
    auth,
    documentLinks,
    onRegeneratePdf,
    regeneratingPdf,
    onResendAssessment,
    resendingAssessment,
}: DocumentsStudioPanelProps) {
    const canDownloadCor =
        Boolean(documentLinks.cor_pdf_url) &&
        enrollment.workflow.is_completed &&
        !enrollment.workflow.is_terminal_locked;

    const assessmentLabel = enrollment.workflow.is_completed
        ? "Official Assessment"
        : "Draft Assessment";

    const hasRegistrationForms = Boolean(
        documentLinks.reg_form_student_url ||
            documentLinks.reg_form_adviser_url ||
            documentLinks.reg_form_receipt_url,
    );

    return (
        <div className="min-w-0 space-y-4">
            {/* Top Frame: Official Registrar & Billing Documents */}
            <Frame>
                <FrameHeader className="flex-row items-center justify-between gap-3">
                    <div className="flex items-center gap-2.5">
                        <IconTile
                            variant="soft"
                            size="sm"
                            className="text-primary"
                        >
                            <FileBadge />
                        </IconTile>
                        <div className="min-w-0">
                            <FrameTitle>
                                Official Registrar & Billing Documents
                            </FrameTitle>
                            <FrameDescription>
                                Print-ready registrar certificates, registration
                                copies, grade slips, and billing statements.
                            </FrameDescription>
                        </div>
                    </div>
                </FrameHeader>

                <FramePanel>
                    <div className="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {/* 1. Certificate of Enrollment (COR) */}
                        <div className="flex min-w-0 flex-col justify-between gap-3 rounded-lg border bg-card p-3.5">
                            <div className="space-y-2">
                                <div className="flex items-start justify-between gap-2">
                                    <IconTile
                                        variant="soft"
                                        size="sm"
                                        className="text-emerald-600 dark:text-emerald-400"
                                    >
                                        <Award />
                                    </IconTile>
                                    <Badge
                                        size="xs"
                                        variant={
                                            canDownloadCor
                                                ? "success-light"
                                                : "secondary"
                                        }
                                    >
                                        {canDownloadCor
                                            ? "Ready"
                                            : enrollment.workflow
                                                    .is_terminal_locked
                                              ? "Locked"
                                              : "Pending Completion"}
                                    </Badge>
                                </div>
                                <div>
                                    <h4 className="text-sm font-semibold text-foreground">
                                        Certificate of Enrollment (COR)
                                    </h4>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {canDownloadCor
                                            ? "Official registrar proof of enrollment with class schedule."
                                            : "Unlocks automatically once enrollment reaches completed status."}
                                    </p>
                                </div>
                            </div>
                            <div>
                                {canDownloadCor && documentLinks.cor_pdf_url ? (
                                    <Button
                                        size="sm"
                                        variant="default"
                                        className="w-full"
                                        nativeButton={false}
                                        render={
                                            <a
                                                href={documentLinks.cor_pdf_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            />
                                        }
                                    >
                                        <Download className="size-3.5" />
                                        Download COR PDF
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="w-full"
                                        disabled
                                    >
                                        <FileCheck2 className="size-3.5" />
                                        COR Unavailable
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* 2. Registration Form (3 Copy Links) */}
                        <div className="flex min-w-0 flex-col justify-between gap-3 rounded-lg border bg-card p-3.5">
                            <div className="space-y-2">
                                <div className="flex items-start justify-between gap-2">
                                    <IconTile
                                        variant="soft"
                                        size="sm"
                                        className="text-blue-600 dark:text-blue-400"
                                    >
                                        <ClipboardCheck />
                                    </IconTile>
                                    <Badge size="xs" variant="info-light">
                                        3 Copies
                                    </Badge>
                                </div>
                                <div>
                                    <h4 className="text-sm font-semibold text-foreground">
                                        Registration Form
                                    </h4>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Print official triplicate copies for
                                        student, adviser, and cashier records.
                                    </p>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {hasRegistrationForms ? (
                                    <>
                                        {documentLinks.reg_form_student_url && (
                                            <Button
                                                size="xs"
                                                variant="outline"
                                                nativeButton={false}
                                                render={
                                                    <a
                                                        href={
                                                            documentLinks.reg_form_student_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    />
                                                }
                                            >
                                                Student Copy
                                            </Button>
                                        )}
                                        {documentLinks.reg_form_adviser_url && (
                                            <Button
                                                size="xs"
                                                variant="outline"
                                                nativeButton={false}
                                                render={
                                                    <a
                                                        href={
                                                            documentLinks.reg_form_adviser_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    />
                                                }
                                            >
                                                Adviser Copy
                                            </Button>
                                        )}
                                        {documentLinks.reg_form_receipt_url && (
                                            <Button
                                                size="xs"
                                                variant="outline"
                                                nativeButton={false}
                                                render={
                                                    <a
                                                        href={
                                                            documentLinks.reg_form_receipt_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    />
                                                }
                                            >
                                                Receipt Copy
                                            </Button>
                                        )}
                                    </>
                                ) : (
                                    <span className="text-xs text-muted-foreground">
                                        Registration form links unavailable.
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* 3. Grade Slip / Report */}
                        <div className="flex min-w-0 flex-col justify-between gap-3 rounded-lg border bg-card p-3.5">
                            <div className="space-y-2">
                                <div className="flex items-start justify-between gap-2">
                                    <IconTile
                                        variant="soft"
                                        size="sm"
                                        className="text-violet-600 dark:text-violet-400"
                                    >
                                        <GraduationCap />
                                    </IconTile>
                                    <Badge size="xs" variant="outline">
                                        Academic
                                    </Badge>
                                </div>
                                <div>
                                    <h4 className="text-sm font-semibold text-foreground">
                                        Grade Slip / Report
                                    </h4>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Term grade report for{" "}
                                        {enrollment.school_year} (
                                        {enrollment.semester_label}).
                                    </p>
                                </div>
                            </div>
                            <div>
                                {documentLinks.grade_report_url ? (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="w-full"
                                        nativeButton={false}
                                        render={
                                            <a
                                                href={
                                                    documentLinks.grade_report_url
                                                }
                                                target="_blank"
                                                rel="noreferrer"
                                            />
                                        }
                                    >
                                        <ExternalLink className="size-3.5" />
                                        Open Grade Report
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="w-full"
                                        disabled
                                    >
                                        Grade Report Unavailable
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* 4. Statement of Account (SOA) */}
                        <div className="flex min-w-0 flex-col justify-between gap-3 rounded-lg border bg-card p-3.5">
                            <div className="space-y-2">
                                <div className="flex items-start justify-between gap-2">
                                    <IconTile
                                        variant="soft"
                                        size="sm"
                                        className="text-amber-600 dark:text-amber-400"
                                    >
                                        <FileSpreadsheet />
                                    </IconTile>
                                    <Badge size="xs" variant="warning-light">
                                        Billing
                                    </Badge>
                                </div>
                                <div>
                                    <h4 className="text-sm font-semibold text-foreground">
                                        Statement of Account (SOA)
                                    </h4>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Detailed ledger of tuition, additional
                                        fees, payments, and remaining balance.
                                    </p>
                                </div>
                            </div>
                            <div>
                                {documentLinks.soa_url ? (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="w-full"
                                        nativeButton={false}
                                        render={
                                            <a
                                                href={documentLinks.soa_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            />
                                        }
                                    >
                                        <Download className="size-3.5" />
                                        Download SOA PDF
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="w-full"
                                        disabled
                                    >
                                        SOA Unavailable
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* 5. Assessment Slip Preview */}
                        <div className="flex min-w-0 flex-col justify-between gap-3 rounded-lg border bg-card p-3.5">
                            <div className="space-y-2">
                                <div className="flex items-start justify-between gap-2">
                                    <IconTile
                                        variant="soft"
                                        size="sm"
                                        className="text-primary"
                                    >
                                        <FileText />
                                    </IconTile>
                                    <Badge
                                        size="xs"
                                        variant={
                                            enrollment.workflow.is_completed
                                                ? "success-light"
                                                : "warning-light"
                                        }
                                    >
                                        {assessmentLabel}
                                    </Badge>
                                </div>
                                <div>
                                    <h4 className="text-sm font-semibold text-foreground">
                                        Assessment Slip Preview
                                    </h4>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {enrollment.workflow.is_completed
                                            ? "Preview the finalized Official Assessment slip."
                                            : "Preview the current Draft Assessment before final enrollment completion."}
                                    </p>
                                </div>
                            </div>
                            <div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="w-full"
                                    nativeButton={false}
                                    render={
                                        <a
                                            href={
                                                documentLinks.assessment_preview_url
                                            }
                                            target="_blank"
                                            rel="noreferrer"
                                        />
                                    }
                                >
                                    <ExternalLink className="size-3.5" />
                                    Preview {assessmentLabel}
                                </Button>
                            </div>
                        </div>
                    </div>
                </FramePanel>
            </Frame>

            {/* Bottom Frame: Generated PDFs & Student Files */}
            <Frame>
                <FrameHeader className="flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2.5">
                        <IconTile
                            variant="soft"
                            size="sm"
                            className="text-primary"
                        >
                            <FolderOpen />
                        </IconTile>
                        <div className="min-w-0">
                            <FrameTitle>
                                Generated PDFs & Student Files
                            </FrameTitle>
                            <FrameDescription>
                                Stored assessment PDFs generated for this
                                enrollment and student repository access.
                            </FrameDescription>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {auth.can_manage_enrollment &&
                            !enrollment.is_trashed && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="default"
                                    onClick={onRegeneratePdf}
                                    disabled={regeneratingPdf}
                                >
                                    {regeneratingPdf ? (
                                        <Loader2 className="size-3.5 animate-spin" />
                                    ) : (
                                        <RefreshCw className="size-3.5" />
                                    )}
                                    Generate Fresh Assessment PDF
                                </Button>
                            )}
                        {!enrollment.is_trashed && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={onResendAssessment}
                                disabled={
                                    resendingAssessment ||
                                    !enrollment.workflow.can_resend_assessment
                                }
                            >
                                {resendingAssessment ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Mail className="size-3.5" />
                                )}
                                Resend Assessment Email
                            </Button>
                        )}
                    </div>
                </FrameHeader>

                <FramePanel className="space-y-4">
                    {enrollment.resources.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed py-8 text-center">
                            <FileText className="size-7 text-muted-foreground/40" />
                            <p className="text-xs font-medium text-foreground">
                                No generated PDF files yet
                            </p>
                            <p className="text-[11px] text-muted-foreground">
                                Click &ldquo;Generate Fresh Assessment
                                PDF&rdquo; to create and archive a downloadable
                                copy.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {enrollment.resources.map((res) => {
                                const typeLabel = res.type.replaceAll("_", " ");
                                return (
                                    <div
                                        key={res.id}
                                        className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex min-w-0 items-center gap-2.5">
                                            <IconTile
                                                variant="outline"
                                                size="xs"
                                            >
                                                <FileText />
                                            </IconTile>
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span className="truncate text-xs font-semibold text-foreground">
                                                        {res.file_name}
                                                    </span>
                                                    <Badge
                                                        size="xs"
                                                        variant="secondary"
                                                        className="capitalize"
                                                    >
                                                        {typeLabel}
                                                    </Badge>
                                                </div>
                                                <p className="text-[11px] text-muted-foreground tabular-nums">
                                                    {formatFileSize(
                                                        res.file_size,
                                                    )}
                                                    {" · "}
                                                    Generated{" "}
                                                    {formatShortDate(
                                                        res.created_at,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            size="xs"
                                            variant="outline"
                                            aria-label={`Download ${res.file_name}`}
                                            nativeButton={false}
                                            render={
                                                <a
                                                    href={res.download_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    aria-label={`Download ${res.file_name}`}
                                                />
                                            }
                                        >
                                            <Download className="size-3" />
                                            Download
                                        </Button>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {/* Student Document Manager Link Card */}
                    {enrollment.student.documents_url && (
                        <div className="flex flex-col gap-3 rounded-lg border bg-muted/30 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 items-center gap-3">
                                <IconTile
                                    variant="soft"
                                    size="sm"
                                    className="text-primary"
                                >
                                    <FolderOpen />
                                </IconTile>
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold text-foreground">
                                        Student Document Manager
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Browse all uploaded credentials,
                                        admission files, and permanent records
                                        for {enrollment.student.full_name}.
                                    </p>
                                </div>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                className="shrink-0"
                                nativeButton={false}
                                render={
                                    <Link
                                        href={enrollment.student.documents_url}
                                    />
                                }
                            >
                                <ExternalLink className="size-3.5" />
                                Open Document Manager
                            </Button>
                        </div>
                    )}
                </FramePanel>
            </Frame>
        </div>
    );
}
