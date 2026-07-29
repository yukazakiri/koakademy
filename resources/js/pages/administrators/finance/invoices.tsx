import { resendFinancialDocument, sendInvoice } from "@/actions/App/Http/Controllers/AdministratorFinanceController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { User } from "@/types/user";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { AlertCircle, ClipboardList, Download, FileSpreadsheet, Loader2, Mail, ReceiptText, Search, WalletCards } from "lucide-react";
import { FormEvent, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

interface InvoiceItem {
    id: number;
    invoice_number: string;
    student_id: string;
    student_name: string;
    course: string;
    year_level: string | number;
    total_amount: number;
    balance: number;
    status: string;
    date: string;
    payment_progress: number;
    student_email: string | null;
    latest_invoice: {
        uuid: string;
        number: string;
        status: string;
        recipient: string | null;
        sent_at: string | null;
        download_url: string | null;
    } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface InvoicesProps {
    user: User;
    invoices: {
        data: InvoiceItem[];
        links: PaginationLink[];
    };
    summary: {
        total_billings: number;
        total_assessed: number;
        total_outstanding: number;
        paid_count: number;
        unpaid_count: number;
    };
    filters: {
        search: string;
        status: string;
    };
    finance_document_settings: {
        manual_invoices_enabled: boolean;
        mail_delivery_available: boolean;
    };
}

interface Branding {
    currency: string;
}

export default function InvoicesPage({ user, invoices, summary, filters, finance_document_settings }: InvoicesProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";
    const [search, setSearch] = useState(filters.search || "");
    const [status, setStatus] = useState(filters.status || "all");
    const [selectedInvoice, setSelectedInvoice] = useState<InvoiceItem | null>(null);
    const invoiceForm = useForm({ recipient: "" });

    const formatCurrency = (amount: number) =>
        new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency,
        }).format(amount || 0);

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            route("administrators.finance.invoices"),
            {
                search: search || undefined,
                status: status === "all" ? undefined : status,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const resetFilters = () => {
        setSearch("");
        setStatus("all");
        router.get(route("administrators.finance.invoices"), {}, { preserveScroll: true, replace: true });
    };

    const openInvoiceDialog = (invoice: InvoiceItem) => {
        setSelectedInvoice(invoice);
        invoiceForm.setData("recipient", invoice.latest_invoice?.recipient || invoice.student_email || "");
        invoiceForm.clearErrors();
    };

    const submitInvoice = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selectedInvoice) return;

        const retryExisting = selectedInvoice.latest_invoice?.status === "failed";
        const endpoint = retryExisting ? resendFinancialDocument.url(selectedInvoice.latest_invoice!.uuid) : sendInvoice.url(selectedInvoice.id);

        invoiceForm.post(endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedInvoice(null);
                toast.success(retryExisting ? "Official eInvoice requeued." : "Official eInvoice queued.");
            },
            onError: () => toast.error("Review the recipient and billing status, then try again."),
        });
    };

    return (
        <AdminLayout user={user} title="Billing Desk">
            <Head title="Finance - Billing" />

            <div className="space-y-6">
                <section className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h1 className="text-foreground text-3xl font-bold tracking-tight">Billing Desk</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Review enrollment billings before payment intake, identify unpaid accounts, and hand off students to cashier.
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button variant="outline" disabled className="gap-2">
                            <FileSpreadsheet className="size-4" />
                            Export
                        </Button>
                        <Button asChild className="gap-2">
                            <Link href={route("administrators.finance.payments.create")}>
                                <ReceiptText className="size-4" />
                                Receive Payment
                            </Link>
                        </Button>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-4">
                    <SummaryCard icon={ClipboardList} label="Billings" value={String(summary.total_billings)} detail="Enrollment billing records" />
                    <SummaryCard icon={WalletCards} label="Assessed" value={formatCurrency(summary.total_assessed)} detail="Total charges in view" />
                    <SummaryCard
                        icon={AlertCircle}
                        label="Outstanding"
                        value={formatCurrency(summary.total_outstanding)}
                        detail={`${summary.unpaid_count} unpaid accounts`}
                    />
                    <SummaryCard icon={ReceiptText} label="Paid" value={String(summary.paid_count)} detail="Accounts cleared" />
                </section>

                <Card>
                    <CardHeader>
                        <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                            <div>
                                <CardTitle>Student Billing Statements</CardTitle>
                                <CardDescription>Filter by student or status before sending a payer to the cashier.</CardDescription>
                            </div>
                            <form onSubmit={submitFilters} className="grid gap-2 sm:grid-cols-[minmax(240px,1fr)_150px_auto_auto]">
                                <div className="relative">
                                    <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Search student or ID"
                                        className="pl-9"
                                    />
                                </div>
                                <Select value={status} onValueChange={setStatus}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All status</SelectItem>
                                        <SelectItem value="unpaid">Unpaid</SelectItem>
                                        <SelectItem value="paid">Paid</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button type="submit">Apply</Button>
                                <Button type="button" variant="ghost" onClick={resetFilters}>
                                    Reset
                                </Button>
                            </form>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Billing</TableHead>
                                    <TableHead>Student</TableHead>
                                    <TableHead>Payment Progress</TableHead>
                                    <TableHead className="text-right">Assessed</TableHead>
                                    <TableHead className="text-right">Balance</TableHead>
                                    <TableHead className="text-center">Status</TableHead>
                                    <TableHead></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.data.length > 0 ? (
                                    invoices.data.map((invoice) => (
                                        <TableRow key={invoice.id}>
                                            <TableCell>
                                                <div className="font-mono text-xs font-semibold">{invoice.invoice_number}</div>
                                                <div className="text-muted-foreground text-xs">{invoice.date}</div>
                                                {invoice.latest_invoice ? (
                                                    <div className="text-muted-foreground mt-1 text-xs">
                                                        {invoice.latest_invoice.number} ·{" "}
                                                        <span className="capitalize">{invoice.latest_invoice.status}</span>
                                                    </div>
                                                ) : null}
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-medium">{invoice.student_name}</div>
                                                <div className="text-muted-foreground text-xs">
                                                    {invoice.student_id} · {invoice.course} · Year {invoice.year_level}
                                                </div>
                                            </TableCell>
                                            <TableCell className="min-w-40">
                                                <div className="flex items-center gap-2">
                                                    <Progress value={invoice.payment_progress} className="h-2" />
                                                    <span className="text-muted-foreground w-10 text-right text-xs tabular-nums">
                                                        {invoice.payment_progress}%
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">{formatCurrency(invoice.total_amount)}</TableCell>
                                            <TableCell className="text-right font-semibold text-amber-600">
                                                {formatCurrency(invoice.balance)}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge variant={invoice.status === "Paid" ? "default" : "secondary"}>{invoice.status}</Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    {invoice.latest_invoice?.download_url ? (
                                                        <Button asChild size="sm" variant="ghost">
                                                            <a
                                                                href={invoice.latest_invoice.download_url}
                                                                aria-label={`Download ${invoice.latest_invoice.number}`}
                                                            >
                                                                <Download className="size-4" />
                                                            </a>
                                                        </Button>
                                                    ) : null}
                                                    {invoice.status !== "Paid" ? (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => openInvoiceDialog(invoice)}
                                                            disabled={
                                                                !finance_document_settings.manual_invoices_enabled ||
                                                                !finance_document_settings.mail_delivery_available
                                                            }
                                                        >
                                                            <Mail className="size-4" />
                                                            {invoice.latest_invoice?.status === "failed"
                                                                ? "Retry eInvoice"
                                                                : invoice.latest_invoice
                                                                  ? "Send updated eInvoice"
                                                                  : "Send eInvoice"}
                                                        </Button>
                                                    ) : null}
                                                    <Button asChild size="sm" variant={invoice.status === "Paid" ? "outline" : "default"}>
                                                        <Link
                                                            href={route("administrators.finance.payments.create", {
                                                                query: { student: invoice.student_id },
                                                            })}
                                                        >
                                                            {invoice.status === "Paid" ? "New receipt" : "Collect"}
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-muted-foreground h-32 text-center">
                                            No billing records match the current filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <p className="text-muted-foreground text-sm">Showing {invoices.data.length} billing records on this page</p>
                            <div className="flex flex-wrap gap-1">
                                {invoices.links.map((link) => (
                                    <Button
                                        key={link.label}
                                        asChild={Boolean(link.url)}
                                        disabled={!link.url}
                                        variant={link.active ? "default" : "outline"}
                                        size="sm"
                                    >
                                        {link.url ? (
                                            <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                                        ) : (
                                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                        )}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Dialog open={Boolean(selectedInvoice)} onOpenChange={(open) => !open && setSelectedInvoice(null)}>
                    <DialogContent>
                        <form onSubmit={submitInvoice}>
                            <DialogHeader>
                                <DialogTitle>
                                    {selectedInvoice?.latest_invoice?.status === "failed" ? "Retry official eInvoice" : "Send official eInvoice"}
                                </DialogTitle>
                                <DialogDescription>
                                    This creates a frozen, verifiable copy of the selected enrollment balance. No due date is included.
                                </DialogDescription>
                            </DialogHeader>
                            {selectedInvoice ? (
                                <div className="space-y-4 py-5">
                                    <div className="bg-muted/30 rounded-lg border p-4 text-sm">
                                        <p className="font-semibold">{selectedInvoice.student_name}</p>
                                        <p className="text-muted-foreground">
                                            {selectedInvoice.student_id} · {selectedInvoice.invoice_number}
                                        </p>
                                        <p className="mt-3 text-lg font-bold">{formatCurrency(selectedInvoice.balance)}</p>
                                        <p className="text-muted-foreground text-xs">Outstanding balance</p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="invoice-recipient">Recipient email</Label>
                                        <Input
                                            id="invoice-recipient"
                                            type="email"
                                            value={invoiceForm.data.recipient}
                                            onChange={(event) => invoiceForm.setData("recipient", event.target.value)}
                                            aria-invalid={Boolean(invoiceForm.errors.recipient)}
                                            placeholder="student@example.com"
                                            autoFocus
                                        />
                                        {invoiceForm.errors.recipient ? (
                                            <p className="text-destructive text-sm">{invoiceForm.errors.recipient}</p>
                                        ) : null}
                                        {invoiceForm.errors.invoice ? <p className="text-destructive text-sm">{invoiceForm.errors.invoice}</p> : null}
                                    </div>
                                </div>
                            ) : null}
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setSelectedInvoice(null)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={invoiceForm.processing}>
                                    {invoiceForm.processing ? <Loader2 className="size-4 animate-spin" /> : <Mail className="size-4" />}
                                    {selectedInvoice?.latest_invoice?.status === "failed" ? "Retry delivery" : "Issue and send"}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AdminLayout>
    );
}

function SummaryCard({ icon: Icon, label, value, detail }: { icon: typeof ClipboardList; label: string; value: string; detail: string }) {
    return (
        <Card>
            <CardContent className="flex items-start justify-between gap-4 p-5">
                <div>
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p className="mt-2 text-2xl font-bold tracking-tight">{value}</p>
                    <p className="text-muted-foreground mt-1 text-xs">{detail}</p>
                </div>
                <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-lg">
                    <Icon className="size-5" />
                </div>
            </CardContent>
        </Card>
    );
}
