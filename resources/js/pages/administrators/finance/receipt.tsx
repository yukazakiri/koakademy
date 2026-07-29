import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { payments } from "@/routes/administrators/finance";
import { create, resendReceipt } from "@/routes/administrators/finance/payments";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, Download, Loader2, Mail, Printer, ReceiptText, RotateCw } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

interface ReceiptInstitution {
    name: string;
    description: string | null;
    support_email: string | null;
    support_phone: string | null;
}

interface EmailDelivery {
    status: "awaiting_reference" | "ready" | "queued" | "pending" | "sent" | "failed" | "skipped" | "revoked" | null;
    recipient: string | null;
    sent_at: string | null;
    failed_at: string | null;
    error: string | null;
}

interface TransactionDetails {
    id: number;
    transaction_number: string;
    reference_number: string | null;
    date: string;
    time: string;
    issued_at: string;
    student_name: string;
    student_id: string;
    student_email: string | null;
    student_course: string;
    student_year_level: number | null;
    amount: number;
    method: string;
    status: string;
    items: Record<string, number>;
    cashier: string;
    remarks: string | null;
    currency: string;
    institution: ReceiptInstitution;
    email_delivery: EmailDelivery;
    official_document: {
        number: string;
        verification_url: string;
        download_url: string | null;
    } | null;
}

interface ReceiptProps {
    transaction: TransactionDetails;
}

function formatCurrency(amount: number, currency: string): string {
    return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
        style: "currency",
        currency,
        minimumFractionDigits: 2,
    }).format(amount || 0);
}

function formatSettlementLabel(key: string): string {
    const normalized = key === "tuition_fee" ? "tuition fee payment" : key.replaceAll("_", " ");
    return normalized.replace(/\b\w/g, (character) => character.toUpperCase());
}

function deliveryVariant(status: EmailDelivery["status"]): "default" | "secondary" | "destructive" | "outline" {
    if (status === "sent") return "default";
    if (status === "failed") return "destructive";
    if (status === "pending" || status === "queued" || status === "awaiting_reference") return "secondary";
    return "outline";
}

export default function ReceiptPage({ transaction }: ReceiptProps) {
    const [resendOpen, setResendOpen] = useState(false);
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        recipient: transaction.email_delivery.recipient || transaction.student_email || "",
        reference_number: transaction.reference_number || "",
    });

    const submitResend = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(resendReceipt.url(transaction.id), {
            preserveScroll: true,
            onSuccess: () => {
                setResendOpen(false);
                toast.success("Official eReceipt queued for delivery.");
            },
            onError: () => toast.error("Check the email address and try again."),
        });
    };

    const documentStyles = `
        @media print {
            @page { size: A4 portrait; margin: 0; }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body {
                visibility: hidden !important;
                overflow: visible !important;
            }
            .print-wrapper {
                visibility: visible !important;
                display: block !important;
                position: fixed !important;
                inset: 0 !important;
                z-index: 9999 !important;
                width: 100% !important;
                height: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }
            .receipt-doc {
                visibility: visible !important;
                display: block !important;
                width: 100% !important;
                min-height: 100% !important;
                margin: 0 !important;
                border: 0 !important;
                box-shadow: none !important;
                transform: none !important;
            }
            .receipt-doc section,
            .receipt-doc table,
            .receipt-doc tr {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            .no-print { display: none !important; }
        }
    `;

    return (
        <>
            <Head title={`Receipt #${transaction.transaction_number}`}>
                <style>{documentStyles}</style>
            </Head>

            <div className="print-wrapper min-h-screen overflow-auto bg-[#525659] px-4 py-8">
                <div className="no-print fixed top-4 right-4 z-50 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                    <Button variant="outline" asChild className="min-h-10 active:scale-[0.96]">
                        <Link href={payments.url()} prefetch>
                            <ArrowLeft data-icon="inline-start" />
                            Back to payments
                        </Link>
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        <Dialog
                            open={resendOpen}
                            onOpenChange={(open) => {
                                setResendOpen(open);
                                clearErrors();
                            }}
                        >
                            <DialogTrigger asChild>
                                <Button
                                    variant="outline"
                                    disabled={transaction.email_delivery.status === "pending" || transaction.email_delivery.status === "queued"}
                                    className="min-h-10 active:scale-[0.96]"
                                >
                                    <RotateCw data-icon="inline-start" />
                                    {transaction.email_delivery.status === "awaiting_reference" ? "Add O.R. & send" : "Send eReceipt"}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <form onSubmit={submitResend}>
                                    <DialogHeader>
                                        <DialogTitle>Email this official eReceipt</DialogTitle>
                                        <DialogDescription>
                                            The address is used for this delivery only and will not change the student record.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-2 py-5">
                                        {!transaction.reference_number ? (
                                            <div className="space-y-2">
                                                <Label htmlFor="receipt-reference">Paper Official Receipt number</Label>
                                                <Input
                                                    id="receipt-reference"
                                                    value={data.reference_number}
                                                    onChange={(event) => setData("reference_number", event.target.value)}
                                                    aria-invalid={Boolean(errors.reference_number)}
                                                    placeholder="OR-2026-0001"
                                                    autoFocus
                                                />
                                                {errors.reference_number && <p className="text-destructive text-sm">{errors.reference_number}</p>}
                                            </div>
                                        ) : null}
                                        <Label htmlFor="receipt-recipient">Recipient email</Label>
                                        <Input
                                            id="receipt-recipient"
                                            type="email"
                                            value={data.recipient}
                                            onChange={(event) => setData("recipient", event.target.value)}
                                            aria-invalid={Boolean(errors.recipient)}
                                            placeholder="student@example.com"
                                            autoFocus={Boolean(transaction.reference_number)}
                                        />
                                        {errors.recipient && <p className="text-destructive text-sm">{errors.recipient}</p>}
                                    </div>
                                    <DialogFooter>
                                        <Button type="button" variant="outline" onClick={() => setResendOpen(false)}>
                                            Cancel
                                        </Button>
                                        <Button type="submit" disabled={processing}>
                                            {processing ? (
                                                <>
                                                    <Loader2 className="size-4 animate-spin" />
                                                    Queueing…
                                                </>
                                            ) : (
                                                <>
                                                    <Mail className="size-4" />
                                                    Send eReceipt
                                                </>
                                            )}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                        {transaction.official_document?.download_url ? (
                            <Button asChild variant="outline" className="min-h-10 active:scale-[0.96]">
                                <a href={transaction.official_document.download_url}>
                                    <Download data-icon="inline-start" />
                                    Download PDF
                                </a>
                            </Button>
                        ) : null}
                        <Button variant="outline" onClick={() => window.print()} className="min-h-10 active:scale-[0.96]">
                            <Printer data-icon="inline-start" />
                            Print
                        </Button>
                        <Button asChild className="min-h-10 active:scale-[0.96]">
                            <Link href={create.url()}>
                                <ReceiptText data-icon="inline-start" />
                                New payment
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="no-print mx-auto mb-5 grid max-w-[210mm] gap-3 pt-14 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                    <div className="bg-card flex items-start gap-3 rounded-xl border p-4 shadow-xs">
                        <span className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-lg">
                            <Mail className="size-4" />
                        </span>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="font-semibold">E-receipt delivery</p>
                                <Badge variant={deliveryVariant(transaction.email_delivery.status)} className="capitalize">
                                    {transaction.email_delivery.status || "Not sent"}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {transaction.email_delivery.status === "sent"
                                    ? `Sent to ${transaction.email_delivery.recipient} on ${transaction.email_delivery.sent_at}.`
                                    : transaction.email_delivery.status === "pending" || transaction.email_delivery.status === "queued"
                                      ? `Queued for ${transaction.email_delivery.recipient}.`
                                      : transaction.email_delivery.status === "awaiting_reference"
                                        ? "Waiting for the paper Official Receipt number before email delivery."
                                        : transaction.email_delivery.status === "failed"
                                          ? transaction.email_delivery.error
                                          : "No student email was available when the payment was recorded."}
                            </p>
                        </div>
                    </div>
                    <div className="bg-card flex items-center gap-2 rounded-xl border px-4 py-3 text-sm shadow-xs">
                        <CheckCircle2 className="size-4 text-emerald-600" />
                        <span>Payment recorded</span>
                    </div>
                </div>

                <article className="receipt-doc mx-auto min-h-[297mm] w-[210mm] max-w-full overflow-hidden border bg-white text-slate-900 shadow-[0_20px_60px_-30px_rgba(0,0,0,0.5)]">
                    <header className="border-b-[3px] border-slate-900 px-8 py-8 sm:px-12">
                        <div className="flex items-start justify-between gap-8">
                            <div>
                                <p className="text-lg font-bold tracking-wide">{transaction.institution.name}</p>
                                {transaction.institution.description && (
                                    <p className="mt-1 max-w-md text-xs text-slate-500">{transaction.institution.description}</p>
                                )}
                            </div>
                            <div className="text-right">
                                <h1 className="text-2xl font-bold tracking-[0.12em]">OFFICIAL eRECEIPT</h1>
                                <p className="mt-2 font-mono text-sm text-slate-500">
                                    {transaction.official_document?.number || `TX-${transaction.transaction_number}`}
                                </p>
                            </div>
                        </div>
                    </header>

                    <section className="grid gap-6 border-b border-slate-200 px-8 py-6 sm:grid-cols-3 sm:px-12">
                        <div>
                            <p className="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Received from</p>
                            <p className="mt-1 font-semibold">{transaction.student_name}</p>
                            <p className="text-xs text-slate-500">Student ID {transaction.student_id}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Date issued</p>
                            <p className="mt-1 font-semibold">{transaction.date}</p>
                            <p className="text-xs text-slate-500">{transaction.time}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Paper O.R. reference</p>
                            <p className="mt-1 font-semibold">{transaction.reference_number || "—"}</p>
                            <p className="text-xs text-slate-500">{transaction.method}</p>
                        </div>
                    </section>

                    <section className="px-8 py-8 sm:px-12">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-slate-300 text-left text-[10px] tracking-widest text-slate-500 uppercase">
                                    <th className="py-3 font-bold">Payment description</th>
                                    <th className="py-3 text-right font-bold">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                {Object.entries(transaction.items).map(([key, amount]) => (
                                    <tr key={key} className="border-b border-slate-100">
                                        <td className="py-4">{formatSettlementLabel(key)}</td>
                                        <td className="py-4 text-right font-medium tabular-nums">{formatCurrency(amount, transaction.currency)}</td>
                                    </tr>
                                ))}
                                <tr>
                                    <td className="pt-6 text-lg font-bold">Total paid</td>
                                    <td className="pt-6 text-right text-xl font-bold tabular-nums">
                                        {formatCurrency(transaction.amount, transaction.currency)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section className="grid gap-6 border-y border-slate-200 bg-slate-50 px-8 py-5 sm:grid-cols-3 sm:px-12">
                        <div>
                            <p className="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Status</p>
                            <p className="mt-1 font-semibold capitalize">{transaction.status}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Payment method</p>
                            <p className="mt-1 font-semibold">{transaction.method}</p>
                        </div>
                        <div>
                            <p className="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Processed by</p>
                            <p className="mt-1 font-semibold">{transaction.cashier}</p>
                        </div>
                    </section>

                    <section className="px-8 py-7 sm:px-12">
                        <p className="text-[10px] font-bold tracking-widest text-slate-500 uppercase">Remarks</p>
                        <div className="mt-2 min-h-14 bg-slate-50 p-4 text-sm text-slate-600">{transaction.remarks || "No additional remarks."}</div>
                    </section>

                    <footer className="mt-auto flex items-end justify-between gap-6 border-t border-slate-200 px-8 py-6 text-[10px] text-slate-500 sm:px-12">
                        <div>
                            <p>This is an institution-issued official electronic receipt.</p>
                            {transaction.official_document && <p>{transaction.official_document.verification_url}</p>}
                            {transaction.institution.support_email && <p>{transaction.institution.support_email}</p>}
                        </div>
                        <p>Transaction ID {transaction.id}</p>
                    </footer>
                </article>
            </div>
        </>
    );
}
