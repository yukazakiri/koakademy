import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import { IconPlus, IconSearch } from "@tabler/icons-react";

interface PaymentItem {
    id: number;
    transaction_number: string;
    student_name: string;
    amount: number;
    method: string;
    status: string;
    date: string;
}

interface PaymentsProps {
    user: User;
    payments: {
        data: PaymentItem[];
        links: any[];
    };
}

interface Branding {
    currency: string;
}

export default function PaymentsPage({ user, payments }: PaymentsProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency: currency,
        }).format(amount);
    };

    return (
        <AdminLayout user={user} title="Payments History">
            <Head title="Finance • Payments" />

            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-foreground text-2xl font-bold tracking-tight">Payments History</h2>
                    <p className="text-muted-foreground">View and track all received payments.</p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" disabled>
                        Export Report
                    </Button>
                    <Button asChild>
                        <Link href="/administrators/finance/payments/create">
                            <IconPlus className="mr-2 size-4" />
                            New Transaction
                        </Link>
                    </Button>
                </div>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle>Transactions</CardTitle>
                            <CardDescription>List of recorded transactions.</CardDescription>
                        </div>
                        <div className="relative w-full max-w-sm">
                            <IconSearch className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                            <Input type="search" placeholder="Search transaction..." className="pl-8" />
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Transaction #</TableHead>
                                <TableHead>Student</TableHead>
                                <TableHead>Date & Time</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead className="text-right">Amount</TableHead>
                                <TableHead className="text-center">Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payments.data.length > 0 ? (
                                payments.data.map((payment) => (
                                    <TableRow key={payment.id}>
                                        <TableCell className="font-mono text-xs">{payment.transaction_number}</TableCell>
                                        <TableCell className="font-medium">{payment.student_name}</TableCell>
                                        <TableCell>{payment.date}</TableCell>
                                        <TableCell>{payment.method}</TableCell>
                                        <TableCell className="text-right font-bold">{formatCurrency(payment.amount)}</TableCell>
                                        <TableCell className="text-center">
                                            <Badge variant="outline">{payment.status}</Badge>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                                        No payments found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                    <div className="text-muted-foreground mt-4 flex justify-center text-sm">Showing {payments.data.length} records</div>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
