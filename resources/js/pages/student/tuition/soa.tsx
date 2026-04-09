import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Head, usePage } from "@inertiajs/react";
import { ArrowLeft, Printer } from "lucide-react";
import { useMemo, useState } from "react";

interface Branding {
    currency: string;
}

interface Tuition {
    total_tuition: number;
    total_balance: number;
    total_lectures: number;
    total_laboratory: number;
    total_miscelaneous_fees: number;
    downpayment: number;
    discount: number;
    overall_tuition: number;
    paid: number;
    formatted_total_balance: string;
    formatted_overall_tuition: string;
    formatted_total_tuition: string;
    formatted_semester: string;
    formatted_total_lectures: string;
    formatted_total_laboratory: string;
    formatted_total_miscelaneous_fees: string;
    formatted_downpayment: string;
    formatted_discount: string;
    formatted_total_paid: string;
    payment_progress: number;
    payment_status: string;
}

interface Transaction {
    id: number;
    date: string;
    description: string;
    amount: string;
    status: string;
    invoice: string;
    method: string;
}

interface Student {
    id: number;
    name: string;
    email: string;
    course: string;
}

interface School {
    name: string;
    address: string;
    logo: string;
}

interface Props {
    student: Student | null;
    tuition: Tuition | null;
    transactions: Transaction[];
    filters: {
        semester: number;
        school_year: string;
    };
    school: School | null;
    generated_at: string;
}

type PaperSize = "letter" | "a4" | "long" | "short";

const PAPER_SIZES: Record<PaperSize, { name: string; css: string; width: string }> = {
    short: { name: 'Short Bond (8.5" × 11")', css: "letter", width: "8.5in" },
    letter: { name: 'Letter (8.5" × 11")', css: "letter", width: "8.5in" },
    a4: { name: "A4 (210mm × 297mm)", css: "A4", width: "210mm" },
    long: { name: 'Long Bond (8.5" × 13")', css: "8.5in 13in", width: "8.5in" },
};

const parseAmount = (amount: number | string | null | undefined): number => {
    if (typeof amount === "number") {
        return Number.isFinite(amount) ? amount : 0;
    }

    if (typeof amount !== "string") {
        return 0;
    }

    const normalized = amount.replace(/[^0-9.-]/g, "");
    const parsed = Number.parseFloat(normalized);

    return Number.isNaN(parsed) ? 0 : parsed;
};

export default function StatementOfAccount({ student, tuition, transactions, filters, school, generated_at }: Props) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";
    const [paperSize, setPaperSize] = useState<PaperSize>("short");

    const formatCurrency = (amount: number | string | null | undefined): string => {
        const symbol = currency === "USD" ? "$" : "₱";

        if (amount === null || amount === undefined || amount === "") {
            return `${symbol}0.00`;
        }

        const num = parseAmount(amount);

        if (Number.isNaN(num)) {
            return `${symbol}0.00`;
        }

        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(num);
    };

    const paymentHistoryTotal = useMemo(() => {
        return transactions.reduce((sum, transaction) => sum + parseAmount(transaction.amount), 0);
    }, [transactions]);

    const assessmentTotal = tuition ? parseAmount(tuition.overall_tuition) : 0;
    const ledgerBalance = tuition ? Math.max(0, parseAmount(tuition.total_balance)) : 0;
    const ledgerPaid = tuition ? Math.max(0, assessmentTotal - ledgerBalance) : paymentHistoryTotal;
    const totalPayments = tuition ? ledgerPaid : paymentHistoryTotal;
    const balanceDue = tuition ? ledgerBalance : Math.max(0, assessmentTotal - paymentHistoryTotal);
    const paymentVariance = Math.abs(totalPayments - paymentHistoryTotal);

    const lectureFee = tuition ? parseAmount(tuition.total_lectures) : 0;
    const laboratoryFee = tuition ? parseAmount(tuition.total_laboratory) : 0;
    const tuitionSubtotal = tuition ? parseAmount(tuition.total_tuition) : 0;
    const miscellaneousFee = tuition ? parseAmount(tuition.total_miscelaneous_fees) : 0;
    const adjustmentAmount = tuition ? assessmentTotal - (tuitionSubtotal + miscellaneousFee) : 0;

    const semesterLabel = filters.semester === 1 ? "1st Semester" : "2nd Semester";
    const documentNo = student
        ? `${student.id}-${filters.school_year.replace(/\s|-/g, "")}-${filters.semester}`
        : `UNAVAILABLE-${filters.school_year.replace(/\s|-/g, "")}-${filters.semester}`;

    const institutionName = school?.name || "Data Center College of the Philippines";
    const institutionAddress = school?.address || "Baguio City";

    const handlePrint = (): void => {
        window.print();
    };

    const handleBack = (): void => {
        window.history.back();
    };

    return (
        <>
            <Head title="Statement of Account" />

            <div className="fixed top-4 right-4 z-50 flex items-center gap-3 rounded-lg border border-slate-300 bg-white/95 p-2 shadow-lg backdrop-blur print:hidden">
                <div className="flex items-center gap-2">
                    <span className="text-xs font-medium text-slate-600">Paper:</span>
                    <Select value={paperSize} onValueChange={(value) => setPaperSize(value as PaperSize)}>
                        <SelectTrigger className="h-8 w-[190px] text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(PAPER_SIZES).map(([key, { name }]) => (
                                <SelectItem key={key} value={key} className="text-xs">
                                    {name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <Button variant="outline" size="sm" onClick={handleBack} className="h-8 border-slate-400">
                    <ArrowLeft className="mr-1 h-3 w-3" />
                    Back
                </Button>
                <Button size="sm" onClick={handlePrint} className="h-8 bg-slate-900 text-white hover:bg-slate-700">
                    <Printer className="mr-1 h-3 w-3" />
                    Print
                </Button>
            </div>

            <div className="min-h-screen bg-slate-300 px-4 py-6 print:bg-white print:px-0 print:py-0">
                <div className="mx-auto border border-slate-800 bg-white shadow-2xl print:border-0 print:shadow-none" style={{ maxWidth: PAPER_SIZES[paperSize].width }}>
                    <div
                        className="soa-document bg-white p-8 text-slate-900 print:p-8"
                        style={{ fontFamily: "'Times New Roman', Times, serif", fontSize: "10pt", lineHeight: "1.35" }}
                    >
                        <header className="border-b-2 border-slate-900 pb-3 text-center">
                            <div className="mb-2 flex items-center justify-center gap-4">
                                <img
                                    src={school?.logo || "/web-app-manifest-192x192.png"}
                                    alt="School logo"
                                    className="h-14 w-14 object-contain print:h-12 print:w-12"
                                    onError={(event) => {
                                        (event.target as HTMLImageElement).style.display = "none";
                                    }}
                                />
                                <div className="text-center">
                                    <p className="text-[8pt] tracking-[0.2em] text-slate-600 uppercase">Republic of the Philippines</p>
                                    <h1 className="text-[16pt] leading-tight font-bold uppercase">{institutionName}</h1>
                                    <p className="text-[8pt] text-slate-600">{institutionAddress}</p>
                                </div>
                            </div>
                        </header>

                        <section className="mt-4 text-center">
                            <h2 className="text-[13pt] font-bold tracking-[0.18em] uppercase">Statement of Account</h2>
                            <p className="mt-0.5 text-[8pt] text-slate-600">Official Financial Record</p>
                        </section>

                        <section className="mt-3 grid grid-cols-2 text-[8.5pt] text-slate-700">
                            <p>
                                <span className="font-bold">Control No.:</span> SOA-{documentNo}
                            </p>
                            <p className="text-right">
                                <span className="font-bold">Date Issued:</span> {generated_at}
                            </p>
                        </section>

                        <section className="mt-3">
                            <table className="w-full border border-slate-800 text-[9.5pt]" style={{ borderCollapse: "collapse" }}>
                                <tbody>
                                    <tr>
                                        <td className="w-[7.5rem] border border-slate-800 bg-slate-100 px-2 py-1 font-bold">Student No.</td>
                                        <td className="w-[11rem] border border-slate-800 px-2 py-1">{student?.id ?? "N/A"}</td>
                                        <td className="w-[7.5rem] border border-slate-800 bg-slate-100 px-2 py-1 font-bold">Student Name</td>
                                        <td className="border border-slate-800 px-2 py-1">{student?.name ?? "N/A"}</td>
                                    </tr>
                                    <tr>
                                        <td className="border border-slate-800 bg-slate-100 px-2 py-1 font-bold">Course</td>
                                        <td className="border border-slate-800 px-2 py-1">{student?.course ?? "N/A"}</td>
                                        <td className="border border-slate-800 bg-slate-100 px-2 py-1 font-bold">Term</td>
                                        <td className="border border-slate-800 px-2 py-1">
                                            {semesterLabel}, A.Y. {filters.school_year}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>

                        {tuition ? (
                            <>
                                <section className="mt-4 grid grid-cols-5 gap-3">
                                    <div className="col-span-3">
                                        <p className="mb-1 text-[10pt] font-bold tracking-wide uppercase">Assessment of Fees</p>
                                        <table className="w-full border border-slate-800 text-[9.5pt]" style={{ borderCollapse: "collapse" }}>
                                            <tbody>
                                                <tr>
                                                    <td className="border border-slate-800 px-2 py-1">Lecture Fee</td>
                                                    <td className="w-36 border border-slate-800 px-2 py-1 text-right font-mono">
                                                        {formatCurrency(lectureFee)}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td className="border border-slate-800 px-2 py-1">Laboratory Fee</td>
                                                    <td className="border border-slate-800 px-2 py-1 text-right font-mono">
                                                        {formatCurrency(laboratoryFee)}
                                                    </td>
                                                </tr>
                                                <tr className="bg-slate-50">
                                                    <td className="border border-slate-800 px-2 py-1 font-semibold">Tuition Subtotal</td>
                                                    <td className="border border-slate-800 px-2 py-1 text-right font-mono font-semibold">
                                                        {formatCurrency(tuitionSubtotal)}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td className="border border-slate-800 px-2 py-1">Miscellaneous Fee</td>
                                                    <td className="border border-slate-800 px-2 py-1 text-right font-mono">
                                                        {formatCurrency(miscellaneousFee)}
                                                    </td>
                                                </tr>
                                                {Math.abs(adjustmentAmount) >= 0.01 && (
                                                    <tr>
                                                        <td className="border border-slate-800 px-2 py-1">Other Adjustments</td>
                                                        <td className="border border-slate-800 px-2 py-1 text-right font-mono">
                                                            {formatCurrency(adjustmentAmount)}
                                                        </td>
                                                    </tr>
                                                )}
                                                {tuition.discount > 0 && (
                                                    <tr>
                                                        <td className="border border-slate-800 px-2 py-1">Discount Applied</td>
                                                        <td className="border border-slate-800 px-2 py-1 text-right font-mono">{tuition.discount}%</td>
                                                    </tr>
                                                )}
                                                <tr className="bg-slate-200 font-bold">
                                                    <td className="border border-slate-800 px-2 py-1.5">TOTAL ASSESSMENT</td>
                                                    <td className="border border-slate-800 px-2 py-1.5 text-right font-mono">
                                                        {formatCurrency(assessmentTotal)}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div className="col-span-2">
                                        <p className="mb-1 text-[10pt] font-bold tracking-wide uppercase">Account Summary</p>
                                        <table className="w-full border border-slate-800 text-[10pt]" style={{ borderCollapse: "collapse" }}>
                                            <tbody>
                                                <tr>
                                                    <td className="border border-slate-800 bg-slate-100 px-2 py-1">Total Assessment</td>
                                                    <td className="border border-slate-800 px-2 py-1 text-right font-mono font-semibold">
                                                        {formatCurrency(assessmentTotal)}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td className="border border-slate-800 bg-slate-100 px-2 py-1">Total Payments</td>
                                                    <td className="border border-slate-800 px-2 py-1 text-right font-mono font-semibold">
                                                        {formatCurrency(totalPayments)}
                                                    </td>
                                                </tr>
                                                <tr className="font-bold">
                                                    <td className="border-2 border-slate-800 bg-slate-200 px-2 py-1.5">BALANCE DUE</td>
                                                    <td className="border-2 border-slate-800 bg-slate-200 px-2 py-1.5 text-right font-mono text-[12pt]">
                                                        {formatCurrency(balanceDue)}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <div className={`mt-2 border border-slate-800 py-1.5 text-center text-[9pt] font-bold ${balanceDue <= 0 ? "bg-emerald-100" : "bg-rose-100"}`}>
                                            {balanceDue <= 0 ? "ACCOUNT SETTLED" : "ACCOUNT WITH OUTSTANDING BALANCE"}
                                        </div>
                                        {paymentVariance >= 0.01 && (
                                            <p className="mt-2 text-[8pt] text-slate-600 italic">
                                                Ledger payments: {formatCurrency(totalPayments)} | Listed payment entries: {formatCurrency(paymentHistoryTotal)}
                                            </p>
                                        )}
                                    </div>
                                </section>

                                <section className="mt-4">
                                    <p className="mb-1 text-[10pt] font-bold tracking-wide uppercase">Payment History</p>
                                    <table className="w-full border border-slate-800 text-[8.8pt]" style={{ borderCollapse: "collapse" }}>
                                        <thead>
                                            <tr className="bg-slate-100">
                                                <th className="border border-slate-800 px-2 py-1 text-left font-bold">Date</th>
                                                <th className="border border-slate-800 px-2 py-1 text-left font-bold">OR No.</th>
                                                <th className="border border-slate-800 px-2 py-1 text-left font-bold">Particulars</th>
                                                <th className="w-36 border border-slate-800 px-2 py-1 text-right font-bold">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {transactions.length > 0 ? (
                                                transactions.slice(0, 8).map((transaction) => (
                                                    <tr key={transaction.id}>
                                                        <td className="border border-slate-800 px-2 py-1">{transaction.date}</td>
                                                        <td className="border border-slate-800 px-2 py-1 font-mono">{transaction.invoice || "-"}</td>
                                                        <td className="border border-slate-800 px-2 py-1">{transaction.description}</td>
                                                        <td className="border border-slate-800 px-2 py-1 text-right font-mono">
                                                            {formatCurrency(transaction.amount)}
                                                        </td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={4} className="border border-slate-800 px-2 py-3 text-center text-slate-500 italic">
                                                        No payment records found for the selected term.
                                                    </td>
                                                </tr>
                                            )}
                                            {transactions.length > 8 && (
                                                <tr>
                                                    <td colSpan={4} className="border border-slate-800 px-2 py-1 text-center text-[8pt] text-slate-500 italic">
                                                        ... and {transactions.length - 8} more transaction(s)
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                        <tfoot>
                                            <tr className="bg-slate-100 font-bold">
                                                <td colSpan={3} className="border border-slate-800 px-2 py-1">
                                                    PAYMENT HISTORY TOTAL
                                                </td>
                                                <td className="border border-slate-800 px-2 py-1 text-right font-mono">
                                                    {formatCurrency(paymentHistoryTotal)}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </section>
                            </>
                        ) : (
                            <section className="mt-4 border border-slate-700 px-4 py-6 text-center">
                                <p className="text-[10pt] text-slate-700">No assessment record found for this academic period.</p>
                            </section>
                        )}

                        <section className="mt-4 border-t border-slate-400 pt-2 text-justify text-[8.7pt]">
                            <span className="font-bold uppercase">Certification:</span> This document certifies that the foregoing figures reflect the
                            recorded financial status of the student as of the date and time of issuance.
                        </section>

                        <section className="mt-8 grid grid-cols-3 gap-8 text-[8.7pt]">
                            <div className="text-center">
                                <div className="mb-1 h-8 border-b border-slate-800" />
                                <p className="font-bold uppercase">Prepared By</p>
                                <p className="text-[8pt] text-slate-500">Finance Staff</p>
                            </div>
                            <div className="text-center">
                                <div className="mb-1 h-8 border-b border-slate-800" />
                                <p className="font-bold uppercase">Verified By</p>
                                <p className="text-[8pt] text-slate-500">Accounting Officer</p>
                            </div>
                            <div className="text-center">
                                <div className="mb-1 h-8 border-b border-slate-800" />
                                <p className="font-bold uppercase">Received By</p>
                                <p className="text-[8pt] text-slate-500">Student / Representative</p>
                            </div>
                        </section>

                        <footer className="mt-4 flex justify-between border-t border-slate-300 pt-1 text-[7.5pt] text-slate-500">
                            <span>Generated: {generated_at}</span>
                            <span>This is a system-generated official document.</span>
                        </footer>
                    </div>
                </div>
            </div>

            <style>{`
                .soa-document { color: #0f172a !important; background: #ffffff !important; }
                .soa-document * { color: inherit; }
                .soa-document table,
                .soa-document td,
                .soa-document th { border-color: #0f172a !important; }
                .soa-document .font-mono { font-family: 'Courier New', Courier, monospace; }

                @media print {
                    @page { size: ${PAPER_SIZES[paperSize].css}; margin: 0.45in; }

                    html,
                    body {
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                        color-adjust: exact !important;
                        color: #000 !important;
                        background: #fff !important;
                    }

                    .print\\:hidden { display: none !important; }
                    .print\\:bg-white { background: #fff !important; }
                    .print\\:border-0 { border: 0 !important; }
                    .print\\:shadow-none { box-shadow: none !important; }
                }
            `}</style>
        </>
    );
}
