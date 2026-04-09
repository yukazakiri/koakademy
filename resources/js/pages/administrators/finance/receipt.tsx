import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, Printer } from "lucide-react";

interface TransactionDetails {
    id: number;
    transaction_number: string;
    date: string;
    student_name: string;
    student_id: string;
    amount: number;
    method: string;
    items: Record<string, number>;
    cashier: string;
    remarks: string | null;
}

interface ReceiptProps {
    user: User;
    transaction: TransactionDetails;
}

interface Branding {
    appName: string;
    currency: string;
}

function formatCurrency(amount: number, currency: string = "PHP"): string {
    return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
        style: "currency",
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
}

export default function ReceiptPage({ user, transaction }: ReceiptProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const appName = props.branding?.appName || "Administrative Portal";
    const currency = props.branding?.currency || "PHP";

    const handlePrint = () => {
        window.print();
    };

    return (
        <AdminLayout user={user} title="Transaction Receipt">
            <Head title={`Receipt #${transaction.transaction_number}`} />

            <div className="flex flex-col items-center justify-center py-8 print:block print:py-0">
                <div className="w-full max-w-md print:w-full print:max-w-none">
                    {/* Actions - Hidden in Print */}
                    <div className="mb-6 flex items-center justify-between print:hidden">
                        <Button variant="outline" asChild>
                            <Link href="/administrators/finance/payments">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Payments
                            </Link>
                        </Button>
                        <div className="flex gap-2">
                            <Button variant="outline" onClick={handlePrint}>
                                <Printer className="mr-2 h-4 w-4" />
                                Print
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href="/administrators/finance/payments/create">New Transaction</Link>
                            </Button>
                        </div>
                    </div>

                    <Card className="print:border-0 print:shadow-none">
                        <CardHeader className="border-b pb-6 text-center">
                            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-green-600 print:hidden">
                                <CheckCircle2 className="h-6 w-6" />
                            </div>
                            <CardTitle className="text-2xl font-bold tracking-wider uppercase">Official Receipt</CardTitle>
                            <CardDescription className="mt-1 text-xs tracking-widest uppercase">{appName}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6 pt-6">
                            {/* Transaction Meta */}
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-muted-foreground text-xs uppercase">Transaction No.</p>
                                    <p className="font-mono font-medium">{transaction.transaction_number}</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-muted-foreground text-xs uppercase">Date</p>
                                    <p className="font-medium">{transaction.date}</p>
                                </div>
                            </div>

                            {/* Student Info */}
                            <div className="bg-muted/30 rounded-lg p-4">
                                <p className="text-muted-foreground mb-1 text-xs uppercase">Received From</p>
                                <p className="text-lg font-bold">{transaction.student_name}</p>
                                <p className="text-muted-foreground text-sm">ID: {transaction.student_id}</p>
                            </div>

                            <Separator />

                            {/* Line Items */}
                            <div className="space-y-3">
                                <p className="text-muted-foreground text-xs font-semibold uppercase">Payment Details</p>

                                {Object.entries(transaction.items || {}).map(([key, amount]) => {
                                    const value = parseFloat(amount as unknown as string);
                                    if (value <= 0) return null; // Hide zero amounts

                                    return (
                                        <div key={key} className="flex justify-between text-sm">
                                            <span className="capitalize">
                                                {key === "tuition_fee"
                                                    ? "Tuition Fee Payment"
                                                    : key === "others"
                                                      ? "Miscellaneous / Other Fees"
                                                      : key.replace(/_/g, " ")}
                                            </span>
                                            <span className="font-mono">{formatCurrency(value, currency)}</span>
                                        </div>
                                    );
                                })}

                                <Separator className="my-2" />

                                <div className="flex justify-between text-lg font-bold">
                                    <span>Total Amount</span>
                                    <span>{formatCurrency(transaction.amount, currency)}</span>
                                </div>
                            </div>

                            {/* Additional Info */}
                            <div className="text-muted-foreground grid grid-cols-2 gap-4 pt-4 text-xs">
                                <div>
                                    <span className="block text-[10px] uppercase">Payment Method</span>
                                    <span className="text-foreground font-medium">{transaction.method}</span>
                                </div>
                                <div className="text-right">
                                    <span className="block text-[10px] uppercase">Cashier</span>
                                    <span className="text-foreground font-medium">{transaction.cashier}</span>
                                </div>
                            </div>

                            {transaction.remarks && (
                                <div className="text-muted-foreground mt-4 border-t pt-2 text-xs">
                                    <span className="mb-1 block text-[10px] uppercase">Remarks</span>
                                    <p>{transaction.remarks}</p>
                                </div>
                            )}
                        </CardContent>
                        <CardFooter className="bg-muted/10 text-muted-foreground block py-4 text-center text-[10px] print:mt-8">
                            <p>This is a system generated receipt.</p>
                            <p className="print:hidden">Thank you for your payment.</p>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
