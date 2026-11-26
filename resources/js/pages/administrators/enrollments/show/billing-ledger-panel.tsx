import {
    resendReceipt,
    sendInvoice,
} from "@/actions/App/Http/Controllers/AdministratorFinanceController";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge, type BadgeProps } from "@/components/reui/badge";
import {
    Frame,
    FrameDescription,
    FrameFooter,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from "@/components/reui/frame";
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
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    Banknote,
    CreditCard,
    Download,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    Loader2,
    Lock,
    Mail,
    Pencil,
    Receipt,
    Send,
    SlidersHorizontal,
} from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { formatMoney, formatShortDate } from "../enrollment-utils";
import type {
    DocumentLinks,
    EnrollmentData,
    ShowEnrollmentPageProps,
} from "./types";

export interface BillingLedgerPanelProps {
    enrollment: EnrollmentData;
    auth: ShowEnrollmentPageProps["auth"];
    currency: string;
    documentLinks: DocumentLinks;
    onEditTransaction: (tx: EnrollmentData["transactions"][number]) => void;
}

function resolveTransactionStatusVariant(
    status: string | null | undefined,
): BadgeProps["variant"] {
    const normalized = (status ?? "").toLowerCase();
    if (
        normalized.includes("paid") ||
        normalized.includes("complete") ||
        normalized.includes("verified") ||
        normalized.includes("settled")
    ) {
        return "success-light";
    }
    if (normalized.includes("pending") || normalized.includes("partial")) {
        return "warning-light";
    }
    if (
        normalized.includes("void") ||
        normalized.includes("cancel") ||
        normalized.includes("fail") ||
        normalized.includes("refund")
    ) {
        return "destructive-light";
    }
    return "secondary";
}

function formatDocumentTypeLabel(type: string): string {
    return type
        .replaceAll("_", " ")
        .replaceAll("-", " ")
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function BillingLedgerPanel({
    enrollment,
    auth,
    currency,
    documentLinks,
    onEditTransaction,
}: BillingLedgerPanelProps) {
    const [sendingInvoice, setSendingInvoice] = useState(false);
    const [resendingTxId, setResendingTxId] = useState<number | null>(null);

    const tuition = enrollment.tuition;
    const totalPaid =
        tuition?.total_paid ??
        (tuition
            ? Math.max(
                  0,
                  Number(tuition.overall_tuition || 0) -
                      Number(tuition.balance_due ?? tuition.total_balance ?? 0),
              )
            : 0);
    const balanceDue = tuition
        ? Number(tuition.balance_due ?? tuition.total_balance ?? 0)
        : 0;
    const creditBalance = Number(tuition?.credit ?? 0);
    const assessmentAdjustment = Number(tuition?.assessment_adjustment ?? 0);

    const handleSendInvoice = () => {
        setSendingInvoice(true);
        router.post(
            sendInvoice.url(enrollment.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Official eInvoice sent to student.");
                },
                onError: () => {
                    toast.error("Failed to send official eInvoice.");
                },
                onFinish: () => setSendingInvoice(false),
            },
        );
    };

    const handleResendReceipt = (transactionId: number) => {
        setResendingTxId(transactionId);
        router.post(
            resendReceipt.url(transactionId),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Receipt email queued for delivery.");
                },
                onError: () => {
                    toast.error("Failed to resend payment receipt.");
                },
                onFinish: () => setResendingTxId(null),
            },
        );
    };

    return (
        <div className="grid min-w-0 grid-cols-1 items-start gap-4 lg:grid-cols-2">
            {/* Left Frame: Assessment & Fees Breakdown */}
            <Frame className="min-w-0">
                <FrameHeader className="flex-row items-center justify-between gap-2">
                    <div className="flex items-center gap-2.5">
                        <IconTile
                            variant="soft"
                            size="sm"
                            className="text-primary"
                        >
                            <Banknote />
                        </IconTile>
                        <div className="min-w-0">
                            <FrameTitle>Assessment & Fees Breakdown</FrameTitle>
                            <FrameDescription>
                                Itemized tuition, discounts, protected fees, and
                                settlement balance.
                            </FrameDescription>
                        </div>
                    </div>
                    {tuition?.status && (
                        <Badge
                            variant={resolveTransactionStatusVariant(
                                tuition.status,
                            )}
                            className="capitalize"
                        >
                            {tuition.status.replaceAll("_", " ")}
                        </Badge>
                    )}
                </FrameHeader>

                <FramePanel className="space-y-4">
                    {(tuition?.needs_finance_review ||
                        tuition?.active_revision_id) && (
                        <Alert variant="warning">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>
                                {tuition.needs_finance_review
                                    ? "Finance Review Required"
                                    : "Active Assessment Revision Lock"}
                            </AlertTitle>
                            <AlertDescription>
                                {tuition.needs_finance_review
                                    ? "Recent enrollment or load changes flagged this assessment for finance verification before finalizing billing."
                                    : `Tuition revision #${tuition.active_revision_id} is currently active. Review adjustments before issuing final clearance.`}
                            </AlertDescription>
                        </Alert>
                    )}

                    {!tuition ? (
                        <div className="rounded-lg border border-dashed py-8 text-center text-xs text-muted-foreground">
                            No tuition assessment record found for this
                            enrollment.
                        </div>
                    ) : (
                        <div className="space-y-3 text-sm">
                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Lecture Fees
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatMoney(
                                            tuition.total_lectures,
                                            currency,
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Laboratory Fees
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatMoney(
                                            tuition.total_laboratory,
                                            currency,
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Miscellaneous Fees
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatMoney(
                                            tuition.total_miscelaneous_fees,
                                            currency,
                                        )}
                                    </span>
                                </div>

                                {(tuition.discount > 0 ||
                                    tuition.discount_name) && (
                                    <div className="flex items-center justify-between gap-3 text-emerald-700 dark:text-emerald-400">
                                        <span className="flex min-w-0 items-center gap-1.5">
                                            <span>Discount</span>
                                            {tuition.discount_name && (
                                                <Badge
                                                    size="xs"
                                                    variant="success-light"
                                                    className="max-w-[180px] truncate"
                                                >
                                                    {tuition.discount_name}
                                                </Badge>
                                            )}
                                            {tuition.discount > 0 && (
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    ({tuition.discount}%)
                                                </span>
                                            )}
                                        </span>
                                        <span className="font-medium tabular-nums">
                                            {tuition.discount > 0
                                                ? `-${tuition.discount}%`
                                                : "Applied"}
                                        </span>
                                    </div>
                                )}
                            </div>

                            {enrollment.additional_fees.length > 0 && (
                                <div className="space-y-2 border-t pt-3">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        Additional Fees (
                                        {enrollment.additional_fees.length})
                                    </p>
                                    <div className="space-y-1.5">
                                        {enrollment.additional_fees.map(
                                            (fee) => {
                                                const isProtected =
                                                    fee.is_separate_transaction ||
                                                    Boolean(
                                                        fee.transaction_number,
                                                    );
                                                return (
                                                    <div
                                                        key={fee.id}
                                                        className="flex items-center justify-between gap-2 text-xs"
                                                    >
                                                        <div className="flex min-w-0 flex-wrap items-center gap-1.5">
                                                            <span className="truncate font-medium text-foreground">
                                                                {fee.fee_name}
                                                            </span>
                                                            {isProtected && (
                                                                <Badge
                                                                    size="xs"
                                                                    variant="success-light"
                                                                    className="gap-1"
                                                                >
                                                                    <Lock className="size-2.5" />
                                                                    {fee.transaction_number
                                                                        ? `Receipt #${fee.transaction_number} · Protected`
                                                                        : "Protected"}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <span className="shrink-0 font-medium tabular-nums">
                                                            {formatMoney(
                                                                fee.amount,
                                                                currency,
                                                            )}
                                                        </span>
                                                    </div>
                                                );
                                            },
                                        )}
                                    </div>
                                </div>
                            )}

                            {(assessmentAdjustment !== 0 ||
                                creditBalance > 0) && (
                                <div className="space-y-1.5 border-t pt-3 text-xs">
                                    {assessmentAdjustment !== 0 && (
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-muted-foreground">
                                                Finance Adjustment
                                            </span>
                                            <span
                                                className={cn(
                                                    "font-medium tabular-nums",
                                                    assessmentAdjustment < 0
                                                        ? "text-emerald-600 dark:text-emerald-400"
                                                        : "text-amber-600 dark:text-amber-400",
                                                )}
                                            >
                                                {assessmentAdjustment > 0
                                                    ? "+"
                                                    : ""}
                                                {formatMoney(
                                                    assessmentAdjustment,
                                                    currency,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                    {creditBalance > 0 && (
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-muted-foreground">
                                                Credit Balance
                                            </span>
                                            <span className="font-medium text-emerald-600 tabular-nums dark:text-emerald-400">
                                                {formatMoney(
                                                    creditBalance,
                                                    currency,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            )}

                            <Separator />

                            {/* Single authoritative totals block — no duplicate total rows */}
                            <div className="space-y-2 rounded-lg bg-muted/40 p-3">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="font-medium text-foreground">
                                        Total Assessment
                                    </span>
                                    <span className="text-base font-semibold tabular-nums">
                                        {formatMoney(
                                            tuition.overall_tuition,
                                            currency,
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3 text-xs">
                                    <span className="text-muted-foreground">
                                        Total Paid
                                    </span>
                                    <span className="font-medium text-emerald-600 tabular-nums dark:text-emerald-400">
                                        {formatMoney(totalPaid, currency)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3 border-t pt-2">
                                    <span className="font-semibold text-foreground">
                                        Balance Due
                                    </span>
                                    <span
                                        className={cn(
                                            "text-base font-bold tabular-nums",
                                            balanceDue > 0
                                                ? "text-destructive"
                                                : "text-emerald-600 dark:text-emerald-400",
                                        )}
                                    >
                                        {formatMoney(balanceDue, currency)}
                                    </span>
                                </div>
                                {tuition.required_downpayment > 0 && (
                                    <p className="text-[11px] text-muted-foreground tabular-nums">
                                        Required downpayment:{" "}
                                        {formatMoney(
                                            tuition.required_downpayment,
                                            currency,
                                        )}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </FramePanel>

                <FrameFooter className="flex-row flex-wrap items-center gap-2">
                    {auth.can_manage_tuition &&
                        documentLinks.tuition_adjustments_url && (
                            <Button
                                size="sm"
                                variant="outline"
                                nativeButton={false}
                                render={
                                    <Link
                                        href={
                                            documentLinks.tuition_adjustments_url
                                        }
                                    />
                                }
                            >
                                <SlidersHorizontal className="size-3.5" />
                                Open Tuition Adjustments
                            </Button>
                        )}
                    {documentLinks.cashier_payment_url && (
                        <Button
                            size="sm"
                            variant="outline"
                            nativeButton={false}
                            render={
                                <Link
                                    href={documentLinks.cashier_payment_url}
                                />
                            }
                        >
                            <CreditCard className="size-3.5" />
                            Open Cashier Desk
                        </Button>
                    )}
                    {auth.can_manage_tuition && !enrollment.is_trashed && (
                        <Button
                            type="button"
                            size="sm"
                            variant="default"
                            onClick={handleSendInvoice}
                            disabled={sendingInvoice}
                        >
                            {sendingInvoice ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <Send className="size-3.5" />
                            )}
                            Send Official eInvoice
                        </Button>
                    )}
                    {documentLinks.soa_url && (
                        <Button
                            size="sm"
                            variant="outline"
                            nativeButton={false}
                            render={
                                <a
                                    href={documentLinks.soa_url}
                                    target="_blank"
                                    rel="noreferrer"
                                />
                            }
                        >
                            <FileSpreadsheet className="size-3.5" />
                            Download SOA PDF
                        </Button>
                    )}
                </FrameFooter>
            </Frame>

            {/* Right Frame: Payments, Receipts & Financial Documents */}
            <Frame className="min-w-0">
                <FrameHeader className="flex-row items-center justify-between gap-2">
                    <div className="flex items-center gap-2.5">
                        <IconTile
                            variant="soft"
                            size="sm"
                            className="text-emerald-600 dark:text-emerald-400"
                        >
                            <Receipt />
                        </IconTile>
                        <div className="min-w-0">
                            <FrameTitle>
                                Payments, Receipts & Financial Documents
                            </FrameTitle>
                            <FrameDescription>
                                Verified payment receipts, invoice issuances,
                                and billing attachments.
                            </FrameDescription>
                        </div>
                    </div>
                    <Badge variant="outline" className="tabular-nums">
                        {enrollment.transactions.length}{" "}
                        {enrollment.transactions.length === 1
                            ? "Payment"
                            : "Payments"}
                    </Badge>
                </FrameHeader>

                <FramePanel className="space-y-4">
                    {enrollment.transactions.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed py-8 text-center">
                            <Receipt className="size-7 text-muted-foreground/40" />
                            <p className="text-xs font-medium text-foreground">
                                No recorded payments yet
                            </p>
                            <p className="text-[11px] text-muted-foreground">
                                Payments posted through the Cashier Desk or
                                verification steps will appear here.
                            </p>
                        </div>
                    ) : (
                        <Timeline
                            value={enrollment.transactions.length}
                            className="pt-1"
                        >
                            {enrollment.transactions.map((tx, index) => {
                                const receiptLabel =
                                    tx.invoicenumber ||
                                    tx.transaction_number ||
                                    `TX-${tx.id}`;
                                const isResending = resendingTxId === tx.id;

                                return (
                                    <TimelineItem
                                        key={tx.id}
                                        step={index + 1}
                                        className="not-last:pb-5"
                                    >
                                        <TimelineHeader>
                                            <TimelineSeparator />
                                            <TimelineIndicator />
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <TimelineTitle className="font-mono text-xs font-semibold">
                                                        {receiptLabel}
                                                    </TimelineTitle>
                                                    {tx.status && (
                                                        <Badge
                                                            size="xs"
                                                            variant={resolveTransactionStatusVariant(
                                                                tx.status,
                                                            )}
                                                            className="capitalize"
                                                        >
                                                            {tx.status}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <span className="text-sm font-semibold text-foreground tabular-nums">
                                                    {formatMoney(
                                                        tx.amount,
                                                        currency,
                                                    )}
                                                </span>
                                            </div>
                                            <TimelineDate>
                                                {formatShortDate(
                                                    tx.transaction_date ??
                                                        tx.created_at,
                                                )}
                                            </TimelineDate>
                                        </TimelineHeader>
                                        <TimelineContent className="space-y-2">
                                            {tx.description && (
                                                <p className="text-xs text-muted-foreground">
                                                    {tx.description}
                                                </p>
                                            )}
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                {tx.receipt_url && (
                                                    <Button
                                                        size="xs"
                                                        variant="outline"
                                                        nativeButton={false}
                                                        render={
                                                            <a
                                                                href={
                                                                    tx.receipt_url
                                                                }
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            />
                                                        }
                                                    >
                                                        <ExternalLink className="size-3" />
                                                        View Receipt
                                                    </Button>
                                                )}
                                                {!enrollment.is_trashed && (
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        variant="ghost"
                                                        disabled={isResending}
                                                        onClick={() =>
                                                            handleResendReceipt(
                                                                tx.id,
                                                            )
                                                        }
                                                    >
                                                        {isResending ? (
                                                            <Loader2 className="size-3 animate-spin" />
                                                        ) : (
                                                            <Mail className="size-3" />
                                                        )}
                                                        Resend Receipt
                                                    </Button>
                                                )}
                                                {auth.can_manage_tuition &&
                                                    !enrollment.is_trashed && (
                                                        <Button
                                                            type="button"
                                                            size="xs"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                onEditTransaction(
                                                                    tx,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="size-3" />
                                                            Edit
                                                        </Button>
                                                    )}
                                            </div>
                                        </TimelineContent>
                                    </TimelineItem>
                                );
                            })}
                        </Timeline>
                    )}
                </FramePanel>

                {(enrollment.latest_invoice ||
                    enrollment.financial_documents.length > 0) && (
                    <FramePanel className="space-y-2.5">
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Financial Documents & Official Invoices
                        </p>
                        <div className="divide-y rounded-lg border">
                            {enrollment.latest_invoice &&
                                !enrollment.financial_documents.some(
                                    (doc) =>
                                        doc.id === enrollment.latest_invoice?.id,
                                ) && (
                                    <div className="flex items-center justify-between gap-2 p-2.5">
                                        <div className="flex min-w-0 items-center gap-2">
                                            <FileText className="size-4 shrink-0 text-primary" />
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span className="truncate text-xs font-medium text-foreground">
                                                        Latest Official eInvoice
                                                        {enrollment
                                                            .latest_invoice
                                                            .document_number
                                                            ? ` · ${enrollment.latest_invoice.document_number}`
                                                            : ""}
                                                    </span>
                                                    {enrollment.latest_invoice
                                                        .status && (
                                                        <Badge
                                                            size="xs"
                                                            variant={resolveTransactionStatusVariant(
                                                                enrollment
                                                                    .latest_invoice
                                                                    .status,
                                                            )}
                                                            className="capitalize"
                                                        >
                                                            {
                                                                enrollment
                                                                    .latest_invoice
                                                                    .status
                                                            }
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-[11px] text-muted-foreground">
                                                    Sent{" "}
                                                    {formatShortDate(
                                                        enrollment
                                                            .latest_invoice
                                                            .sent_at,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            size="xs"
                                            variant="outline"
                                            nativeButton={false}
                                            render={
                                                <a
                                                    href={
                                                        enrollment
                                                            .latest_invoice
                                                            .download_url
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                />
                                            }
                                        >
                                            <Download className="size-3" />
                                            Download
                                        </Button>
                                    </div>
                                )}

                            {enrollment.financial_documents.map((doc) => (
                                <div
                                    key={doc.id}
                                    className="flex items-center justify-between gap-2 p-2.5"
                                >
                                    <div className="flex min-w-0 items-center gap-2">
                                        <FileText className="size-4 shrink-0 text-muted-foreground" />
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span className="truncate text-xs font-medium text-foreground">
                                                    {formatDocumentTypeLabel(
                                                        doc.document_type,
                                                    )}
                                                    {doc.document_number
                                                        ? ` · ${doc.document_number}`
                                                        : ""}
                                                </span>
                                                {doc.status && (
                                                    <Badge
                                                        size="xs"
                                                        variant={resolveTransactionStatusVariant(
                                                            doc.status,
                                                        )}
                                                        className="capitalize"
                                                    >
                                                        {doc.status}
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-[11px] text-muted-foreground">
                                                Issued{" "}
                                                {formatShortDate(
                                                    doc.created_at,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        size="xs"
                                        variant="outline"
                                        nativeButton={false}
                                        render={
                                            <a
                                                href={doc.download_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            />
                                        }
                                    >
                                        <Download className="size-3" />
                                        Download
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </FramePanel>
                )}
            </Frame>
        </div>
    );
}
