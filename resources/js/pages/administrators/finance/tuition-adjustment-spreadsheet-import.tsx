import { confirmSpreadsheetImport, index } from "@/actions/App/Http/Controllers/AdministratorTuitionAdjustmentController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { AlertTriangle, CheckCircle2, FileSpreadsheet, Send } from "lucide-react";

type ImportRow = {
    id: number;
    row_number: number;
    student_number: string | null;
    status: "ready" | "invalid" | "applied" | "rejected";
    input: Record<string, unknown>;
    canonical: Record<string, unknown> | null;
    proposal: Record<string, unknown> | null;
    errors: string[];
    result: Record<string, unknown> | null;
};

type Props = {
    user: User;
    import: {
        id: string;
        filename: string;
        school_year: string;
        semester: number;
        status: string;
        counts: { ready: number; invalid: number; applied: number; rejected: number };
        uploaded_at: string | null;
        confirmed_at: string | null;
        uploader: { id: number; name: string } | null;
        confirmer: { id: number; name: string } | null;
        rows: ImportRow[];
    };
    can_confirm: boolean;
};

const money = (value: unknown) => new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" }).format(Number(value) || 0);

export default function TuitionAdjustmentSpreadsheetImportPage({ user, import: spreadsheetImport, can_confirm }: Props) {
    const validRows = spreadsheetImport.rows.filter((row) => row.status === "ready");
    const submit = () => router.post(confirmSpreadsheetImport.url({ spreadsheetImport: spreadsheetImport.id }));

    return (
        <AdminLayout user={user} title="Review Tuition Spreadsheet">
            <Head title="Finance · Review Tuition Spreadsheet" />
            <div className="mx-auto w-full max-w-[1600px] space-y-5">
                <div className="flex flex-col gap-3 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-primary text-xs font-semibold tracking-[0.18em] uppercase">Finance · spreadsheet review</p>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight">Review tuition adjustments</h1>
                        <p className="text-muted-foreground mt-1 text-sm"><FileSpreadsheet className="mr-1 inline size-4" />{spreadsheetImport.filename} · {spreadsheetImport.school_year} · Semester {spreadsheetImport.semester}</p>
                    </div>
                    <Button variant="outline" asChild><Link href={index.url()}>Back to adjustments</Link></Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-4">
                    <Summary label="Ready to apply" value={spreadsheetImport.counts.ready} tone="success" />
                    <Summary label="Needs correction" value={spreadsheetImport.counts.invalid} tone="warning" />
                    <Summary label="Applied" value={spreadsheetImport.counts.applied} tone="success" />
                    <Summary label="Rejected on confirm" value={spreadsheetImport.counts.rejected} tone="warning" />
                </div>

                {can_confirm && (
                    <Alert>
                        <CheckCircle2 className="size-4" />
                        <AlertTitle>{validRows.length} valid row{validRows.length === 1 ? "" : "s"} ready for confirmation</AlertTitle>
                        <AlertDescription>Confirmation re-checks current tuition and verified payments. Only valid rows will be changed and students will be notified.</AlertDescription>
                    </Alert>
                )}
                {spreadsheetImport.counts.invalid > 0 && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>Some rows need a corrected workbook</AlertTitle>
                        <AlertDescription>Invalid rows are not applied. Upload a corrected template after reviewing the errors below.</AlertDescription>
                    </Alert>
                )}

                <Card className="overflow-hidden">
                    <CardHeader className="border-b">
                        <CardTitle>Imported rows</CardTitle>
                        <CardDescription>Current values are a snapshot from upload time; confirmation checks them again before making changes.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader><TableRow><TableHead>Row</TableHead><TableHead>Student</TableHead><TableHead>Reason</TableHead><TableHead>Current total</TableHead><TableHead>Proposed total</TableHead><TableHead>Balance</TableHead><TableHead>Schedule</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {spreadsheetImport.rows.map((row) => <ReviewRow key={row.id} row={row} />)}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                {can_confirm && validRows.length > 0 && (
                    <div className="flex justify-end"><Button onClick={submit}><Send className="size-4" /> Confirm {validRows.length} valid row{validRows.length === 1 ? "" : "s"}</Button></div>
                )}
            </div>
        </AdminLayout>
    );
}

function ReviewRow({ row }: { row: ImportRow }) {
    const proposal = row.proposal ?? {};
    const canonical = row.canonical ?? {};
    const installments = Array.isArray(proposal.review_installments) ? proposal.review_installments as Array<{ term: string; amount: number }> : [];
    const label = row.status === "ready" ? "Ready" : row.status === "applied" ? "Applied" : row.status === "rejected" ? "Rejected" : "Needs correction";

    return <TableRow className={row.status === "invalid" || row.status === "rejected" ? "bg-destructive/5" : undefined}>
        <TableCell className="font-mono text-xs">{row.row_number}</TableCell>
        <TableCell>{row.student_number ?? "—"}<div className="text-muted-foreground text-xs">{String(canonical.student_name ?? "Unmatched")}</div></TableCell>
        <TableCell className="max-w-64 whitespace-normal">{String(proposal.reason ?? row.input.reason ?? "—")}</TableCell>
        <TableCell>{row.canonical ? money(canonical.total_fees) : "—"}</TableCell>
        <TableCell>{row.proposal ? money(proposal.total_fees) : "—"}</TableCell>
        <TableCell>{row.proposal ? money(proposal.balance) : "—"}</TableCell>
        <TableCell className="text-xs">{installments.length ? installments.map((item) => `${item.term}: ${money(item.amount)}`).join(" · ") : "—"}</TableCell>
        <TableCell><Badge variant={row.status === "ready" || row.status === "applied" ? "secondary" : "destructive"}>{label}</Badge>{row.errors.length > 0 && <p className="text-destructive mt-1 max-w-xs whitespace-normal text-xs">{row.errors.join(" ")}</p>}</TableCell>
    </TableRow>;
}

function Summary({ label, value, tone }: { label: string; value: number; tone: "success" | "warning" }) {
    return <Card><CardContent className="p-4"><p className="text-muted-foreground text-sm">{label}</p><p className={tone === "success" ? "mt-1 text-2xl font-bold text-emerald-600" : "mt-1 text-2xl font-bold text-amber-600"}>{value}</p></CardContent></Card>;
}
