import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from "@/components/ui/command";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, ChevronsUpDown, FileText, Loader2, Package, Plus, Search, User as UserIcon, X } from "lucide-react";
import { useCallback, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

// --- Interfaces ---

interface InventoryItem {
    id: number;
    name: string;
    price: number;
    sku: string;
    category: string;
}

interface StudentOption {
    id: number;
    full_name: string;
    email: string;
    course_code: string | null;
    formatted_academic_year: string | null;
}

interface UnpaidEnrollment {
    id: number; // StudentTuition ID
    enrollment_id: number;
    school_year: string;
    semester: number;
    total_amount: number;
    paid: number;
    balance: number;
}

interface StudentFinancialDetails {
    id: number;
    full_name: string;
    student_id: number;
    course: string;
    year_level: number;
    outstanding_balance: number;
    unpaid_enrollments: UnpaidEnrollment[];
}

const FEE_TYPES = [
    { id: "registration_fee", label: "Registration Fee" },
    { id: "miscelanous_fee", label: "Miscellaneous Fee" },
    { id: "diploma_or_certificate", label: "Diploma / Certificate" },
    { id: "transcript_of_records", label: "Transcript of Records" },
    { id: "certification", label: "Certification" },
    { id: "special_exam", label: "Special Exam" },
    { id: "others", label: "Other Fees" },
];

interface CreatePaymentProps {
    user: User;
    items: InventoryItem[];
    currency: string;
}

interface Branding {
    currency: string;
}

function formatCurrency(amount: number, currency: string = "PHP"): string {
    return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
        style: "currency",
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
}

function generateId(): string {
    return Math.random().toString(36).substring(2, 9);
}

export default function CreatePaymentPage({ user, items, currency: propCurrency }: CreatePaymentProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || propCurrency || "PHP";
    // Student Search
    const [studentOpen, setStudentOpen] = useState(false);
    const [studentSearch, setStudentSearch] = useState("");
    const [studentOptions, setStudentOptions] = useState<StudentOption[]>([]);
    const [studentLoading, setStudentLoading] = useState(false);
    const [selectedStudent, setSelectedStudent] = useState<StudentFinancialDetails | null>(null);

    // Transaction State
    const [selectedTuitionId, setSelectedTuitionId] = useState<string>("none");
    const [tuitionAmount, setTuitionAmount] = useState<string>("");

    const [otherItems, setOtherItems] = useState<
        {
            id: string;
            type: "fee" | "item";
            label: string;
            amount: string;
            data: any;
        }[]
    >([]);

    const [addItemOpen, setAddItemOpen] = useState(false);

    // Payment Details
    const [paymentMethod, setPaymentMethod] = useState("Cash");
    const [referenceNumber, setReferenceNumber] = useState("");
    const [remarks, setRemarks] = useState("");

    const [submitting, setSubmitting] = useState(false);
    const studentSearchTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Search Logic
    const searchStudents = useCallback((search: string) => {
        if (studentSearchTimeout.current) clearTimeout(studentSearchTimeout.current);
        if (search.length < 2) {
            setStudentOptions([]);
            return;
        }
        studentSearchTimeout.current = setTimeout(async () => {
            setStudentLoading(true);
            try {
                const response = await fetch(route("administrators.enrollments.api.students") + `?search=${encodeURIComponent(search)}`);
                const data = await response.json();
                setStudentOptions(data);
            } catch (error) {
                toast.error("Search failed");
            } finally {
                setStudentLoading(false);
            }
        }, 300);
    }, []);

    const handleSelectStudent = async (student: StudentOption) => {
        setStudentOpen(false);
        setStudentSearch("");
        try {
            const response = await fetch(route("administrators.finance.api.student-details") + `?student_id=${student.id}`);
            const details: StudentFinancialDetails = await response.json();
            setSelectedStudent(details);

            // Reset form
            setOtherItems([]);
            setSelectedTuitionId("none");
            setTuitionAmount("");

            toast.success(`Active: ${details.full_name}`);
        } catch (error) {
            toast.error("Failed to load details");
        }
    };

    // Handle Tuition Selection
    const handleTuitionChange = (val: string) => {
        setSelectedTuitionId(val);
        if (val === "none") {
            setTuitionAmount("");
        } else {
            // Manual input required, clear it initially
            setTuitionAmount("");
        }
    };

    // Add Item Logic
    const addFee = (fee: (typeof FEE_TYPES)[0]) => {
        setOtherItems((prev) => [
            ...prev,
            {
                id: generateId(),
                type: "fee",
                label: fee.label,
                amount: "",
                data: fee,
            },
        ]);
        setAddItemOpen(false);
    };

    const addItem = (item: InventoryItem) => {
        setOtherItems((prev) => [
            ...prev,
            {
                id: generateId(),
                type: "item",
                label: item.name,
                amount: item.price.toString(),
                data: item,
            },
        ]);
        setAddItemOpen(false);
    };

    const removeOtherItem = (id: string) => {
        setOtherItems((prev) => prev.filter((item) => item.id !== id));
    };

    const updateOtherItemAmount = (id: string, amount: string) => {
        setOtherItems((prev) => prev.map((item) => (item.id === id ? { ...item, amount } : item)));
    };

    // Calculation
    const totalAmount = useMemo(() => {
        let total = 0;
        if (selectedTuitionId !== "none") {
            total += parseFloat(tuitionAmount) || 0;
        }
        total += otherItems.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
        return total;
    }, [selectedTuitionId, tuitionAmount, otherItems]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedStudent || totalAmount <= 0) return;

        setSubmitting(true);

        const itemsPayload = [];

        // Add Tuition
        if (selectedTuitionId !== "none") {
            const enrollment = selectedStudent.unpaid_enrollments.find((e) => e.id.toString() === selectedTuitionId);
            if (enrollment) {
                itemsPayload.push({
                    type: "tuition",
                    name: `Tuition: SY ${enrollment.school_year} ${enrollment.semester === 1 ? "1st" : "2nd"} Sem`,
                    amount: parseFloat(tuitionAmount),
                    tuition_id: enrollment.id,
                });
            }
        }

        // Add Others
        otherItems.forEach((item) => {
            if (parseFloat(item.amount) > 0) {
                if (item.type === "fee") {
                    itemsPayload.push({
                        type: "fee",
                        name: item.label,
                        amount: parseFloat(item.amount),
                        fee_key: item.data.id,
                    });
                } else {
                    itemsPayload.push({
                        type: "item",
                        name: item.label,
                        amount: parseFloat(item.amount),
                        id: item.data.id,
                    });
                }
            }
        });

        router.post(
            route("administrators.finance.payments.store"),
            {
                student_id: selectedStudent.id,
                payment_method: paymentMethod,
                reference_number: referenceNumber,
                remarks: remarks,
                items: itemsPayload,
            },
            {
                onSuccess: () => toast.success("Payment recorded!"),
                onError: () => toast.error("Transaction failed"),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <AdminLayout user={user} title="New Payment">
            <Head title="Finance • Pay" />

            <div className="mx-auto flex max-w-5xl flex-col gap-6 py-6">
                {/* Top Navigation & Title */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-foreground text-2xl font-bold tracking-tight">Transaction Entry</h1>
                        <p className="text-muted-foreground">Process student payments and fees.</p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/administrators/finance/payments">
                            <ArrowLeft className="mr-2 h-4 w-4" /> Cancel
                        </Link>
                    </Button>
                </div>

                {/* Student Selection Card */}
                <Card className="has-[[data-state=open]]:border-primary/50 border-2 border-dashed shadow-sm transition-colors">
                    <CardContent className="p-6">
                        <div className="flex flex-col items-center gap-6 md:flex-row">
                            <div className="w-full flex-1 md:w-auto">
                                <Label className="text-muted-foreground mb-1.5 block text-xs font-semibold uppercase">Payer / Student</Label>
                                <Popover open={studentOpen} onOpenChange={setStudentOpen}>
                                    <PopoverTrigger asChild>
                                        <Button variant="outline" role="combobox" className="h-11 w-full justify-between">
                                            {selectedStudent ? (
                                                <div className="flex items-center gap-2">
                                                    <div className="bg-primary/10 text-primary flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold">
                                                        {selectedStudent.full_name.charAt(0)}
                                                    </div>
                                                    <span className="font-semibold">{selectedStudent.full_name}</span>
                                                    <span className="text-muted-foreground ml-2 border-l pl-2 text-xs">
                                                        {selectedStudent.student_id}
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground flex items-center gap-2">
                                                    <Search className="h-4 w-4" /> Search Student...
                                                </span>
                                            )}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-[400px] p-0" align="start">
                                        <Command shouldFilter={false}>
                                            <CommandInput
                                                placeholder="Type name or ID..."
                                                value={studentSearch}
                                                onValueChange={(val) => {
                                                    setStudentSearch(val);
                                                    searchStudents(val);
                                                }}
                                            />
                                            <CommandList>
                                                {studentLoading ? (
                                                    <div className="flex justify-center p-4">
                                                        <Loader2 className="h-4 w-4 animate-spin" />
                                                    </div>
                                                ) : studentOptions.length === 0 ? (
                                                    <CommandEmpty>No students found.</CommandEmpty>
                                                ) : (
                                                    <CommandGroup>
                                                        {studentOptions.map((student) => (
                                                            <CommandItem
                                                                key={student.id}
                                                                onSelect={() => handleSelectStudent(student)}
                                                                className="cursor-pointer"
                                                            >
                                                                <UserIcon className="mr-2 h-4 w-4 opacity-70" />
                                                                <div className="flex flex-col">
                                                                    <span className="font-medium">{student.full_name}</span>
                                                                    <span className="text-muted-foreground text-xs">{student.course_code}</span>
                                                                </div>
                                                            </CommandItem>
                                                        ))}
                                                    </CommandGroup>
                                                )}
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            </div>

                            {selectedStudent && (
                                <>
                                    <Separator orientation="vertical" className="hidden h-10 md:block" />
                                    <div className="flex w-full justify-between gap-8 md:w-auto md:justify-start">
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium uppercase">Year Level</p>
                                            <p className="text-lg font-bold tabular-nums">{selectedStudent.year_level}</p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground text-xs font-medium uppercase">Total Arrears</p>
                                            <p
                                                className={cn(
                                                    "text-lg font-bold tabular-nums",
                                                    selectedStudent.outstanding_balance > 0 ? "text-destructive" : "text-emerald-600",
                                                )}
                                            >
                                                {formatCurrency(selectedStudent.outstanding_balance, currency)}
                                            </p>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {selectedStudent ? (
                    <div className="animate-in fade-in slide-in-from-bottom-2 grid gap-6">
                        {/* Main Transaction Card */}
                        <Card className="shadow-md">
                            <CardHeader className="bg-muted/40 border-b pb-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                                            <FileText className="text-muted-foreground h-5 w-5" />
                                            Items & Fees
                                        </CardTitle>
                                        <CardDescription>Add tuition and other fees to this transaction.</CardDescription>
                                    </div>

                                    <Popover open={addItemOpen} onOpenChange={setAddItemOpen}>
                                        <PopoverTrigger asChild>
                                            <Button size="sm" className="h-9 gap-1.5">
                                                <Plus className="h-3.5 w-3.5" />
                                                Add Item
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-[300px] p-0" align="end">
                                            <Command>
                                                <CommandInput placeholder="Search items..." />
                                                <CommandList>
                                                    <CommandEmpty>No results.</CommandEmpty>
                                                    <CommandGroup heading="School Fees">
                                                        {FEE_TYPES.map((fee) => (
                                                            <CommandItem key={fee.id} onSelect={() => addFee(fee)}>
                                                                <span>{fee.label}</span>
                                                            </CommandItem>
                                                        ))}
                                                    </CommandGroup>
                                                    <CommandSeparator />
                                                    <CommandGroup heading="Inventory">
                                                        {items.map((item) => (
                                                            <CommandItem key={item.id} onSelect={() => addItem(item)}>
                                                                <div className="flex flex-col">
                                                                    <span>{item.name}</span>
                                                                    <span className="text-muted-foreground text-xs">
                                                                        {formatCurrency(item.price, currency)}
                                                                    </span>
                                                                </div>
                                                            </CommandItem>
                                                        ))}
                                                    </CommandGroup>
                                                </CommandList>
                                            </Command>
                                        </PopoverContent>
                                    </Popover>
                                </div>
                            </CardHeader>

                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead className="w-[45%] pl-6">Description</TableHead>
                                            <TableHead className="w-[20%]">Category</TableHead>
                                            <TableHead className="w-[25%]">Amount</TableHead>
                                            <TableHead className="w-[10%] pr-6 text-right"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {/* Row 1: Tuition Logic */}
                                        <TableRow className="bg-muted/5">
                                            <TableCell className="py-4 pl-6">
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="tuition-select" className="text-muted-foreground text-xs font-bold uppercase">
                                                        Tuition Payment
                                                    </Label>
                                                    <Select value={selectedTuitionId} onValueChange={handleTuitionChange}>
                                                        <SelectTrigger id="tuition-select" className="bg-background border-input w-full shadow-sm">
                                                            <SelectValue placeholder="Select Enrollment Period..." />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="none">-- Skip Tuition --</SelectItem>
                                                            {selectedStudent.unpaid_enrollments.map((enrollment) => (
                                                                <SelectItem key={enrollment.id} value={enrollment.id.toString()}>
                                                                    SY {enrollment.school_year} • {enrollment.semester === 1 ? "1st" : "2nd"} Sem
                                                                    <span className="text-muted-foreground ml-2">
                                                                        (Bal: {formatCurrency(enrollment.balance, currency)})
                                                                    </span>
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </TableCell>
                                            <TableCell className="pb-5 align-bottom">
                                                <Badge variant="outline" className="font-normal">
                                                    Academic
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="pb-4 align-bottom">
                                                <div className="relative">
                                                    <span className="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2 text-sm font-medium">
                                                        {currency === "USD" ? "$" : "₱"}
                                                    </span>
                                                    <Input
                                                        className="bg-background pl-8 font-mono tabular-nums"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="0.00"
                                                        value={tuitionAmount}
                                                        onChange={(e) => setTuitionAmount(e.target.value)}
                                                        disabled={selectedTuitionId === "none"}
                                                    />
                                                </div>
                                            </TableCell>
                                            <TableCell></TableCell>
                                        </TableRow>

                                        {/* Dynamic Rows */}
                                        {otherItems.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell className="pl-6 font-medium">{item.label}</TableCell>
                                                <TableCell>
                                                    <Badge variant="secondary" className="text-muted-foreground font-normal capitalize">
                                                        {item.type}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="relative">
                                                        <span className="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2 text-sm font-medium">
                                                            {currency === "USD" ? "$" : "₱"}
                                                        </span>
                                                        <Input
                                                            className="pl-8 font-mono tabular-nums"
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            value={item.amount}
                                                            onChange={(e) => updateOtherItemAmount(item.id, e.target.value)}
                                                            placeholder="0.00"
                                                        />
                                                    </div>
                                                </TableCell>
                                                <TableCell className="pr-6 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-muted-foreground hover:text-destructive h-8 w-8"
                                                        onClick={() => removeOtherItem(item.id)}
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}

                                        {selectedTuitionId === "none" && otherItems.length === 0 && (
                                            <TableRow>
                                                <TableCell colSpan={4} className="text-muted-foreground bg-muted/5 h-32 border-dashed text-center">
                                                    <div className="flex flex-col items-center gap-2">
                                                        <Package className="h-8 w-8 opacity-20" />
                                                        <p>No items selected.</p>
                                                        <p className="text-xs">Choose an enrollment above or add a fee to begin.</p>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        {/* Footer Payment Details */}
                        <Card className="bg-muted/40 border-none shadow-sm">
                            <CardContent className="p-6">
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-12">
                                    {/* Payment Options */}
                                    <div className="space-y-4 md:col-span-8">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label>Payment Method</Label>
                                                <Select value={paymentMethod} onValueChange={setPaymentMethod}>
                                                    <SelectTrigger className="bg-background">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="Cash">Cash</SelectItem>
                                                        <SelectItem value="Check">Check</SelectItem>
                                                        <SelectItem value="Bank Transfer">Bank Transfer</SelectItem>
                                                        <SelectItem value="GCash">GCash</SelectItem>
                                                        <SelectItem value="Maya">Maya</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>
                                                    Reference No. <span className="text-muted-foreground ml-1 text-xs font-normal">(Optional)</span>
                                                </Label>
                                                <Input
                                                    placeholder="OR Number / Ref ID"
                                                    className="bg-background"
                                                    value={referenceNumber}
                                                    onChange={(e) => setReferenceNumber(e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Remarks</Label>
                                            <Textarea
                                                placeholder="Add any additional notes about this transaction..."
                                                className="bg-background h-20 resize-none"
                                                value={remarks}
                                                onChange={(e) => setRemarks(e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    {/* Totals & Submit */}
                                    <div className="bg-card flex flex-col justify-between rounded-xl border p-6 shadow-sm md:col-span-4">
                                        <div>
                                            <Label className="text-muted-foreground text-xs tracking-wider uppercase">Total Amount Due</Label>
                                            <div className="text-primary mt-1 text-3xl font-bold tracking-tight">
                                                {formatCurrency(totalAmount, currency)}
                                            </div>
                                        </div>

                                        <Button
                                            size="lg"
                                            className="mt-6 w-full text-base font-semibold"
                                            onClick={handleSubmit}
                                            disabled={submitting || totalAmount <= 0}
                                        >
                                            {submitting ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" /> Processing
                                                </>
                                            ) : (
                                                <>
                                                    <CheckCircle2 className="mr-2 h-4 w-4" /> Confirm Payment
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                ) : (
                    // Empty State
                    <div className="bg-muted/10 animate-in fade-in flex flex-col items-center justify-center rounded-xl border-2 border-dashed py-24 duration-500">
                        <div className="bg-muted mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <UserIcon className="text-muted-foreground/50 h-8 w-8" />
                        </div>
                        <h3 className="text-lg font-semibold">No Student Selected</h3>
                        <p className="text-muted-foreground mt-2 max-w-sm text-center text-sm">
                            Use the search bar at the top to find a student and begin a transaction.
                        </p>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
