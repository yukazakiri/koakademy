import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { AlertCircle, ArrowLeft, Box, Calendar, ClipboardCheck, Package, RotateCcw, Save, User as UserIcon } from "lucide-react";
import { type FormEvent, useMemo } from "react";

declare const route: any;

interface SelectOption {
    value: string | number;
    label: string;
    available?: number | null;
    unit?: string | null;
    email?: string | null;
}

interface BorrowingFormData {
    product_id: string;
    quantity_borrowed: string;
    borrower_name: string;
    borrower_email: string;
    borrower_phone: string;
    department: string;
    purpose: string;
    status: string;
    borrowed_date: string;
    expected_return_date: string;
    actual_return_date: string;
    quantity_returned: string;
    return_notes: string;
    issued_by: string;
    returned_to: string;
}

interface BorrowingRecord {
    id: number;
    product_id: number;
    quantity_borrowed: number;
    borrower_name: string;
    borrower_email?: string | null;
    borrower_phone?: string | null;
    department?: string | null;
    purpose?: string | null;
    status: string;
    borrowed_date?: string | null;
    expected_return_date?: string | null;
    actual_return_date?: string | null;
    quantity_returned?: number | null;
    return_notes?: string | null;
    issued_by: number;
    returned_to?: number | null;
}

interface Props {
    user: User;
    record: BorrowingRecord | null;
    options: {
        products: SelectOption[];
        staff: SelectOption[];
        statuses: SelectOption[];
    };
}

const formatDateTimeLocal = (value?: string | null) => {
    if (!value) return "";
    return value.replace(" ", "T").slice(0, 16);
};

const nowLocal = () => new Date().toISOString().slice(0, 16);

const statusStyles: Record<string, string> = {
    borrowed: "bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20",
    returned: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20",
    overdue: "bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20",
    lost: "bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20",
};

export default function InventoryBorrowingEdit({ user, record, options }: Props) {
    const form = useForm<BorrowingFormData>({
        product_id: record?.product_id ? String(record.product_id) : "",
        quantity_borrowed: record?.quantity_borrowed ? String(record.quantity_borrowed) : "1",
        borrower_name: record?.borrower_name ?? "",
        borrower_email: record?.borrower_email ?? "",
        borrower_phone: record?.borrower_phone ?? "",
        department: record?.department ?? "",
        purpose: record?.purpose ?? "",
        status: record?.status ?? "borrowed",
        borrowed_date: record?.borrowed_date ? formatDateTimeLocal(record.borrowed_date) : nowLocal(),
        expected_return_date: formatDateTimeLocal(record?.expected_return_date),
        actual_return_date: formatDateTimeLocal(record?.actual_return_date),
        quantity_returned: record?.quantity_returned ? String(record.quantity_returned) : "0",
        return_notes: record?.return_notes ?? "",
        issued_by: record?.issued_by ? String(record.issued_by) : String(user.id),
        returned_to: record?.returned_to ? String(record.returned_to) : "",
    });

    const selectedProduct = useMemo(() => {
        if (!form.data.product_id) return null;
        return options.products.find((p) => String(p.value) === form.data.product_id) ?? null;
    }, [form.data.product_id, options.products]);

    const isReturned = form.data.status === "returned";
    const isLost = form.data.status === "lost";
    const showReturnFields = isReturned || isLost;

    const handleStatusChange = (value: string) => {
        form.setData("status", value);

        if (value === "returned") {
            if (!form.data.actual_return_date) {
                form.setData("actual_return_date", nowLocal());
            }
            if (!form.data.quantity_returned || form.data.quantity_returned === "0") {
                form.setData("quantity_returned", form.data.quantity_borrowed || "0");
            }
            if (!form.data.returned_to) {
                form.setData("returned_to", String(user.id));
            }
        } else if (value === "lost") {
            form.setData("quantity_returned", "0");
            form.setData("actual_return_date", nowLocal());
        }
    };

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (record) {
            form.put(route("administrators.inventory.borrowings.update", record.id));
            return;
        }

        form.post(route("administrators.inventory.borrowings.store"));
    };

    return (
        <AdminLayout user={user} title={record ? "Edit Borrow Record" : "Log Borrow"}>
            <Head title={`Administrators • ${record ? "Edit" : "New"} Borrow Log`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                {/* Header Card */}
                <Card className="via-background border-0 bg-gradient-to-br from-amber-500/10 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
                                <ClipboardCheck className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle>{record ? "Update Borrow Log" : "New Borrow Log"}</CardTitle>
                                <CardDescription>
                                    {record ? "Update borrowing details and track returns." : "Record who is borrowing equipment and when."}
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" className="gap-2" asChild>
                                <Link href={route("administrators.inventory.borrowings.index")}>
                                    <ArrowLeft className="h-4 w-4" />
                                    Back to logs
                                </Link>
                            </Button>
                            <Button type="submit" className="gap-2" disabled={form.processing}>
                                <Save className="h-4 w-4" />
                                {form.processing ? "Saving..." : record ? "Save changes" : "Create log"}
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Form Column */}
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        {/* Borrower Information */}
                        <Card>
                            <CardHeader className="pb-4">
                                <div className="flex items-center gap-2">
                                    <UserIcon className="text-muted-foreground h-4 w-4" />
                                    <CardTitle className="text-base">Borrower Information</CardTitle>
                                </div>
                                <CardDescription>Who is borrowing the item?</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="borrower_name">
                                        Full Name <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="borrower_name"
                                        placeholder="Enter borrower's full name"
                                        value={form.data.borrower_name}
                                        onChange={(event) => form.setData("borrower_name", event.target.value)}
                                        autoComplete="name"
                                    />
                                    {form.errors.borrower_name && (
                                        <p className="text-destructive flex items-center gap-1 text-xs">
                                            <AlertCircle className="h-3 w-3" />
                                            {form.errors.borrower_name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="department">Department</Label>
                                    <Input
                                        id="department"
                                        placeholder="e.g., IT, Facilities, Maintenance"
                                        value={form.data.department}
                                        onChange={(event) => form.setData("department", event.target.value)}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="borrower_email">Email Address</Label>
                                    <Input
                                        id="borrower_email"
                                        type="email"
                                        placeholder="borrower@example.com"
                                        value={form.data.borrower_email}
                                        onChange={(event) => form.setData("borrower_email", event.target.value)}
                                        autoComplete="email"
                                    />
                                    {form.errors.borrower_email && (
                                        <p className="text-destructive flex items-center gap-1 text-xs">
                                            <AlertCircle className="h-3 w-3" />
                                            {form.errors.borrower_email}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="borrower_phone">Phone Number</Label>
                                    <Input
                                        id="borrower_phone"
                                        type="tel"
                                        placeholder="+63 900 000 0000"
                                        value={form.data.borrower_phone}
                                        onChange={(event) => form.setData("borrower_phone", event.target.value)}
                                        autoComplete="tel"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Item & Quantity */}
                        <Card>
                            <CardHeader className="pb-4">
                                <div className="flex items-center gap-2">
                                    <Package className="text-muted-foreground h-4 w-4" />
                                    <CardTitle className="text-base">Item Details</CardTitle>
                                </div>
                                <CardDescription>Select the item and specify quantity to borrow.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>
                                        Item to Borrow <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.data.product_id} onValueChange={(value) => form.setData("product_id", value)}>
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Select an item..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.products.map((product) => (
                                                <SelectItem key={product.value} value={String(product.value)}>
                                                    <div className="flex items-center gap-2">
                                                        <Box className="text-muted-foreground h-4 w-4" />
                                                        <span>{product.label}</span>
                                                        {typeof product.available === "number" && (
                                                            <span className="text-muted-foreground">
                                                                ({product.available} {product.unit ?? "pcs"} available)
                                                            </span>
                                                        )}
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.product_id && (
                                        <p className="text-destructive flex items-center gap-1 text-xs">
                                            <AlertCircle className="h-3 w-3" />
                                            {form.errors.product_id}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="quantity_borrowed">
                                        Quantity to Borrow <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="quantity_borrowed"
                                        type="number"
                                        min={1}
                                        max={selectedProduct?.available ?? undefined}
                                        value={form.data.quantity_borrowed}
                                        onChange={(event) => form.setData("quantity_borrowed", event.target.value)}
                                    />
                                    {selectedProduct && typeof selectedProduct.available === "number" && (
                                        <p className="text-muted-foreground text-xs">
                                            Available: {selectedProduct.available} {selectedProduct.unit ?? "pcs"}
                                        </p>
                                    )}
                                    {form.errors.quantity_borrowed && (
                                        <p className="text-destructive flex items-center gap-1 text-xs">
                                            <AlertCircle className="h-3 w-3" />
                                            {form.errors.quantity_borrowed}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label>
                                        Issued By <span className="text-destructive">*</span>
                                    </Label>
                                    <Select value={form.data.issued_by} onValueChange={(value) => form.setData("issued_by", value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select staff member" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.staff.map((staff) => (
                                                <SelectItem key={staff.value} value={String(staff.value)}>
                                                    {staff.label}
                                                    {staff.email && <span className="text-muted-foreground ml-1">({staff.email})</span>}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.issued_by && (
                                        <p className="text-destructive flex items-center gap-1 text-xs">
                                            <AlertCircle className="h-3 w-3" />
                                            {form.errors.issued_by}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="purpose">Purpose / Reason</Label>
                                    <Textarea
                                        id="purpose"
                                        rows={3}
                                        placeholder="Describe why this item is being borrowed..."
                                        value={form.data.purpose}
                                        onChange={(event) => form.setData("purpose", event.target.value)}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Dates */}
                        <Card>
                            <CardHeader className="pb-4">
                                <div className="flex items-center gap-2">
                                    <Calendar className="text-muted-foreground h-4 w-4" />
                                    <CardTitle className="text-base">Borrowing Schedule</CardTitle>
                                </div>
                                <CardDescription>When was the item borrowed and when should it be returned?</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="borrowed_date">
                                        Borrowed Date <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="borrowed_date"
                                        type="datetime-local"
                                        value={form.data.borrowed_date}
                                        onChange={(event) => form.setData("borrowed_date", event.target.value)}
                                    />
                                    {form.errors.borrowed_date && (
                                        <p className="text-destructive flex items-center gap-1 text-xs">
                                            <AlertCircle className="h-3 w-3" />
                                            {form.errors.borrowed_date}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="expected_return_date">Expected Return Date</Label>
                                    <Input
                                        id="expected_return_date"
                                        type="datetime-local"
                                        min={form.data.borrowed_date}
                                        value={form.data.expected_return_date}
                                        onChange={(event) => form.setData("expected_return_date", event.target.value)}
                                    />
                                    <p className="text-muted-foreground text-xs">Leave empty if no specific return date is required.</p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Return Details - Shown when editing or status allows */}
                        {(record || showReturnFields) && (
                            <Card className={showReturnFields ? "border-emerald-500/20" : ""}>
                                <CardHeader className="pb-4">
                                    <div className="flex items-center gap-2">
                                        <RotateCcw className="text-muted-foreground h-4 w-4" />
                                        <CardTitle className="text-base">Return Information</CardTitle>
                                    </div>
                                    <CardDescription>Track what has been returned and when.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="quantity_returned">Quantity Returned</Label>
                                        <Input
                                            id="quantity_returned"
                                            type="number"
                                            min={0}
                                            max={Number(form.data.quantity_borrowed) || undefined}
                                            value={form.data.quantity_returned}
                                            onChange={(event) => form.setData("quantity_returned", event.target.value)}
                                            disabled={!showReturnFields}
                                        />
                                        {form.errors.quantity_returned && (
                                            <p className="text-destructive flex items-center gap-1 text-xs">
                                                <AlertCircle className="h-3 w-3" />
                                                {form.errors.quantity_returned}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="actual_return_date">Actual Return Date</Label>
                                        <Input
                                            id="actual_return_date"
                                            type="datetime-local"
                                            min={form.data.borrowed_date}
                                            value={form.data.actual_return_date}
                                            onChange={(event) => form.setData("actual_return_date", event.target.value)}
                                            disabled={!showReturnFields}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Returned To</Label>
                                        <Select
                                            value={form.data.returned_to || "none"}
                                            onValueChange={(value) => form.setData("returned_to", value === "none" ? "" : value)}
                                            disabled={!showReturnFields}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select staff member" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">Not assigned</SelectItem>
                                                {options.staff.map((staff) => (
                                                    <SelectItem key={staff.value} value={String(staff.value)}>
                                                        {staff.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2 sm:col-span-2">
                                        <Label htmlFor="return_notes">Return Notes</Label>
                                        <Textarea
                                            id="return_notes"
                                            rows={3}
                                            placeholder="Any notes about the condition of returned items..."
                                            value={form.data.return_notes}
                                            onChange={(event) => form.setData("return_notes", event.target.value)}
                                            disabled={!showReturnFields}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="flex flex-col gap-6">
                        {/* Status Card */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="text-base">Status</CardTitle>
                                <CardDescription>Current borrowing status</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Select value={form.data.status} onValueChange={handleStatusChange}>
                                    <SelectTrigger className={form.data.status ? statusStyles[form.data.status] : ""}>
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.statuses.map((status) => (
                                            <SelectItem key={status.value} value={String(status.value)}>
                                                <div className="flex items-center gap-2">
                                                    <div
                                                        className={`h-2 w-2 rounded-full ${
                                                            status.value === "borrowed"
                                                                ? "bg-amber-500"
                                                                : status.value === "returned"
                                                                  ? "bg-emerald-500"
                                                                  : "bg-rose-500"
                                                        }`}
                                                    />
                                                    {status.label}
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.status && (
                                    <p className="text-destructive flex items-center gap-1 text-xs">
                                        <AlertCircle className="h-3 w-3" />
                                        {form.errors.status}
                                    </p>
                                )}

                                <Separator />

                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Borrowed</span>
                                        <Badge variant="outline" className={statusStyles.borrowed}>
                                            {form.data.quantity_borrowed} {selectedProduct?.unit ?? "pcs"}
                                        </Badge>
                                    </div>
                                    {showReturnFields && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Returned</span>
                                            <Badge variant="outline" className={statusStyles.returned}>
                                                {form.data.quantity_returned} {selectedProduct?.unit ?? "pcs"}
                                            </Badge>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Selected Item Preview */}
                        {selectedProduct && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="text-base">Selected Item</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex items-start gap-3">
                                        <div className="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-lg">
                                            <Box className="text-muted-foreground h-5 w-5" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">{selectedProduct.label}</p>
                                            {typeof selectedProduct.available === "number" && (
                                                <p className="text-muted-foreground text-sm">
                                                    {selectedProduct.available} {selectedProduct.unit ?? "pcs"} in stock
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Quick Actions */}
                        {record && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="text-base">Quick Actions</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {form.data.status === "borrowed" && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full justify-start gap-2"
                                            onClick={() => handleStatusChange("returned")}
                                        >
                                            <RotateCcw className="h-4 w-4" />
                                            Mark as Returned
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}
