import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import { IconSearch } from "@tabler/icons-react";

interface InvoiceItem {
    id: number;
    invoice_number: string;
    student_name: string;
    total_amount: number;
    balance: number;
    status: string;
    date: string;
}

interface InvoicesProps {
    user: User;
    invoices: {
        data: InvoiceItem[];
        links: any[];
    };
}

interface Branding {
    currency: string;
}

export default function InvoicesPage({ user, invoices }: InvoicesProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency: currency,
        }).format(amount);
    };

    return (
        <AdminLayout user={user} title="Invoices & Billing">
            <Head title="Finance • Invoices" />

            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-foreground text-2xl font-bold tracking-tight">Invoices & Billing</h2>
                    <p className="text-muted-foreground">Manage student billing statements and invoices.</p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" disabled>
                        Export List
                    </Button>
                    <Button disabled>Create Invoice</Button>
                </div>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle>Invoices</CardTitle>
                            <CardDescription>List of recent enrollment billings.</CardDescription>
                        </div>
                        <div className="relative w-full max-w-sm">
                            <IconSearch className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                            <Input type="search" placeholder="Search invoice or student..." className="pl-8" />
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Invoice #</TableHead>
                                <TableHead>Student</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead className="text-right">Total Amount</TableHead>
                                <TableHead className="text-right">Balance</TableHead>
                                <TableHead className="text-center">Status</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invoices.data.length > 0 ? (
                                invoices.data.map((invoice) => (
                                    <TableRow key={invoice.id}>
                                        <TableCell className="font-mono text-xs">{invoice.invoice_number}</TableCell>
                                        <TableCell className="font-medium">{invoice.student_name}</TableCell>
                                        <TableCell>{invoice.date}</TableCell>
                                        <TableCell className="text-right">{formatCurrency(invoice.total_amount)}</TableCell>
                                        <TableCell className="text-right font-medium text-amber-600">{formatCurrency(invoice.balance)}</TableCell>
                                        <TableCell className="text-center">
                                            <Badge
                                                variant={invoice.status === "Paid" ? "default" : "secondary"}
                                                className={invoice.status === "Paid" ? "bg-emerald-500 hover:bg-emerald-600" : ""}
                                            >
                                                {invoice.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/administrators/enrollments/${invoice.id}`}>View</Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={7} className="text-muted-foreground h-24 text-center">
                                        No invoices found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>

                    {/* Pagination would go here */}
                    <div className="text-muted-foreground mt-4 flex justify-center text-sm">Showing {invoices.data.length} records</div>
                </CardContent>
            </Card>
        </AdminLayout>
    );
}
