import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { ClipboardCheck, Save } from "lucide-react";
import type { FormEvent } from "react";

declare const route: any;

interface BorrowRecordFormData {
    book_id: string;
    user_id: string;
    borrowed_at: string;
    due_date: string;
    returned_at: string;
    status: string;
    fine_amount: string;
    notes: string;
}

interface BorrowRecordItem {
    id: number;
    book_id: number;
    user_id: number;
    borrowed_at: string | null;
    due_date: string | null;
    returned_at: string | null;
    status: string;
    fine_amount: string | number;
    notes: string | null;
}

interface SelectOption {
    value: string | number;
    label: string;
    available_copies?: number;
}

interface Props {
    user: User;
    record: BorrowRecordItem | null;
    options: {
        books: SelectOption[];
        users: SelectOption[];
        statuses: SelectOption[];
    };
}

const formatDateTimeLocal = (value?: string | null) => {
    if (!value) return "";
    return value.replace(" ", "T").slice(0, 16);
};

export default function LibraryBorrowRecordEdit({ user, record, options }: Props) {
    const form = useForm<BorrowRecordFormData>({
        book_id: record?.book_id ? String(record.book_id) : "",
        user_id: record?.user_id ? String(record.user_id) : "",
        borrowed_at: formatDateTimeLocal(record?.borrowed_at),
        due_date: formatDateTimeLocal(record?.due_date),
        returned_at: formatDateTimeLocal(record?.returned_at),
        status: record?.status ?? "borrowed",
        fine_amount: record?.fine_amount ? String(record.fine_amount) : "0",
        notes: record?.notes ?? "",
    });

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (record) {
            form.put(route("administrators.library.borrow-records.update", record.id));
            return;
        }

        form.post(route("administrators.library.borrow-records.store"));
    };

    return (
        <AdminLayout user={user} title={record ? "Edit Borrow Record" : "New Borrow Record"}>
            <Head title={`Administrators • ${record ? "Edit" : "New"} Borrow Record`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-amber-500/10 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
                                <ClipboardCheck className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle>{record ? "Update Borrow Record" : "Log a Borrow"}</CardTitle>
                                <CardDescription>Track loans and due dates accurately.</CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route("administrators.library.borrow-records.index")}>Back to records</Link>
                            </Button>
                            <Button type="submit" className="gap-2" disabled={form.processing}>
                                <Save className="h-4 w-4" />
                                {record ? "Save changes" : "Create record"}
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Borrow Details</CardTitle>
                        <CardDescription>Assign the loan to a borrower and book.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Book</Label>
                            <Select value={form.data.book_id} onValueChange={(value) => form.setData("book_id", value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select book" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.books.map((book) => (
                                        <SelectItem key={book.value} value={String(book.value)}>
                                            {book.label}
                                            {typeof book.available_copies === "number" ? ` (${book.available_copies} available)` : ""}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.book_id && <p className="text-destructive text-xs">{form.errors.book_id}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label>Borrower</Label>
                            <Select value={form.data.user_id} onValueChange={(value) => form.setData("user_id", value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select borrower" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.users.map((borrower) => (
                                        <SelectItem key={borrower.value} value={String(borrower.value)}>
                                            {borrower.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.user_id && <p className="text-destructive text-xs">{form.errors.user_id}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="borrowed_at">Borrowed At</Label>
                            <Input
                                id="borrowed_at"
                                type="datetime-local"
                                value={form.data.borrowed_at}
                                onChange={(event) => form.setData("borrowed_at", event.target.value)}
                            />
                            {form.errors.borrowed_at && <p className="text-destructive text-xs">{form.errors.borrowed_at}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="due_date">Due Date</Label>
                            <Input
                                id="due_date"
                                type="datetime-local"
                                value={form.data.due_date}
                                onChange={(event) => form.setData("due_date", event.target.value)}
                            />
                            {form.errors.due_date && <p className="text-destructive text-xs">{form.errors.due_date}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label>Status</Label>
                            <Select value={form.data.status} onValueChange={(value) => form.setData("status", value)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.statuses.map((status) => (
                                        <SelectItem key={status.value} value={String(status.value)}>
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.status && <p className="text-destructive text-xs">{form.errors.status}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="returned_at">Returned At</Label>
                            <Input
                                id="returned_at"
                                type="datetime-local"
                                value={form.data.returned_at}
                                onChange={(event) => form.setData("returned_at", event.target.value)}
                                disabled={form.data.status !== "returned"}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="fine_amount">Fine Amount</Label>
                            <Input
                                id="fine_amount"
                                type="number"
                                step="0.01"
                                value={form.data.fine_amount}
                                onChange={(event) => form.setData("fine_amount", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea id="notes" rows={4} value={form.data.notes} onChange={(event) => form.setData("notes", event.target.value)} />
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AdminLayout>
    );
}
