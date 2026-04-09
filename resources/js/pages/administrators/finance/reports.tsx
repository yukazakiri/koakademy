import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from "@/components/ui/chart";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { User } from "@/types/user";
import { Head, usePage } from "@inertiajs/react";
import {
    IconCalendar,
    IconCash,
    IconChartBar,
    IconFileSpreadsheet,
    IconLoader2,
    IconReceipt,
    IconRefresh,
    IconReportMoney,
    IconSchool,
    IconUserCheck,
    IconUsers,
} from "@tabler/icons-react";
import { format } from "date-fns";
import { useCallback, useState } from "react";
import { Bar, BarChart, Cell, Pie, PieChart, XAxis, YAxis } from "recharts";

interface Branding {
    currency: string;
}

interface ReportsProps {
    user: User;
    filters: {
        school_years: string[];
        semesters: number[];
        payment_methods: string[];
        current_school_year: string;
        current_semester: number;
    };
}

interface DailyCollectionData {
    transactions: Array<{
        id: number;
        transaction_number: string;
        student_name: string;
        student_id: string;
        amount: number;
        payment_method: string;
        description: string;
        cashier: string;
        time: string;
    }>;
    summary: {
        total_transactions: number;
        total_amount: number;
        by_payment_method: Record<string, { count: number; total: number }>;
        date: string;
    };
}

interface CollectionData {
    transactions: Array<{
        id: number;
        transaction_number: string;
        student_name: string;
        student_id: string;
        amount: number;
        payment_method: string;
        description: string;
        cashier: string;
        date: string;
        time: string;
    }>;
    summary: {
        total_transactions: number;
        total_amount: number;
        by_payment_method: Record<string, { count: number; total: number }>;
        daily_breakdown: Array<{ date: string; count: number; total: number }>;
        start_date: string;
        end_date: string;
    };
}

interface OutstandingData {
    students: Array<{
        id: number;
        student_id: string;
        student_name: string;
        course: string;
        year_level: string;
        total_tuition: number;
        total_paid: number;
        balance: number;
        payment_progress: number;
        school_year: string;
        semester: number;
    }>;
    summary: {
        total_students: number;
        total_outstanding: number;
        total_collectible: number;
        total_collected: number;
        collection_rate: number;
        school_year: string;
        semester: number;
    };
}

interface ScholarshipData {
    scholars: Array<{
        id: number;
        student_id: string;
        student_name: string;
        course: string;
        year_level: string;
        discount_percentage: number;
        original_tuition: number;
        discount_amount: number;
        discounted_tuition: number;
        school_year: string;
        semester: number;
    }>;
    summary: {
        total_scholars: number;
        total_discount_granted: number;
        original_revenue: number;
        discounted_revenue: number;
        by_discount_level: Array<{
            discount: string;
            count: number;
            total_discount: number;
        }>;
        school_year: string;
        semester: number;
    };
}

interface RevenueData {
    summary: {
        total_revenue: number;
        total_transactions: number;
        breakdown: Array<{ key: string; label: string; total: number }>;
        monthly_trend: Array<{ month: string; total: number; count: number }>;
        school_year: string;
        semester: number;
    };
}

interface FullyPaidData {
    students: Array<{
        id: number;
        student_id: string;
        student_name: string;
        course: string;
        year_level: string;
        total_paid: number;
        discount: string;
        school_year: string;
        semester: number;
    }>;
    summary: {
        total_students: number;
        total_collected: number;
        school_year: string;
        semester: number;
    };
}

interface CashierData {
    cashiers: Array<{
        cashier_id: number;
        cashier_name: string;
        transaction_count: number;
        total_collected: number;
        average_transaction: number;
    }>;
    summary: {
        total_cashiers: number;
        total_transactions: number;
        total_collected: number;
        start_date: string;
        end_date: string;
    };
}

const chartColors = [
    "hsl(142.1 76.2% 36.3%)",
    "hsl(221.2 83.2% 53.3%)",
    "hsl(47.9 95.8% 53.1%)",
    "hsl(346.8 77.2% 49.8%)",
    "hsl(262.1 83.3% 57.8%)",
    "hsl(24.6 95% 53.1%)",
    "hsl(173.4 80.4% 40%)",
    "hsl(340 75% 55%)",
];

const pieChartConfig = {
    value: { label: "Amount" },
} satisfies ChartConfig;

const barChartConfig = {
    total: { label: "Amount", color: "hsl(142.1 76.2% 36.3%)" },
} satisfies ChartConfig;

export default function ReportsPage({ user, filters }: ReportsProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const [activeTab, setActiveTab] = useState("daily");
    const [loading, setLoading] = useState(false);

    // Date states
    const [selectedDate, setSelectedDate] = useState<Date>(new Date());
    const [startDate, setStartDate] = useState<Date>(new Date(new Date().setDate(new Date().getDate() - 30)));
    const [endDate, setEndDate] = useState<Date>(new Date());

    // Filter states
    const [schoolYear, setSchoolYear] = useState(filters.current_school_year);
    const [semester, setSemester] = useState(filters.current_semester.toString());
    const [paymentMethod, setPaymentMethod] = useState<string>("");

    // Report data states
    const [dailyData, setDailyData] = useState<DailyCollectionData | null>(null);
    const [collectionData, setCollectionData] = useState<CollectionData | null>(null);
    const [outstandingData, setOutstandingData] = useState<OutstandingData | null>(null);
    const [scholarshipData, setScholarshipData] = useState<ScholarshipData | null>(null);
    const [revenueData, setRevenueData] = useState<RevenueData | null>(null);
    const [fullyPaidData, setFullyPaidData] = useState<FullyPaidData | null>(null);
    const [cashierData, setCashierData] = useState<CashierData | null>(null);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency: currency,
        }).format(amount);
    };

    const fetchDailyCollection = useCallback(async () => {
        setLoading(true);
        try {
            const response = await fetch(`/administrators/finance/reports/daily-collection?date=${format(selectedDate, "yyyy-MM-dd")}`);
            const data = await response.json();
            setDailyData(data);
        } catch (error) {
            console.error("Failed to fetch daily collection:", error);
        } finally {
            setLoading(false);
        }
    }, [selectedDate]);

    const fetchCollectionReport = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                start_date: format(startDate, "yyyy-MM-dd"),
                end_date: format(endDate, "yyyy-MM-dd"),
            });
            if (paymentMethod) params.append("payment_method", paymentMethod);

            const response = await fetch(`/administrators/finance/reports/collection?${params}`);
            const data = await response.json();
            setCollectionData(data);
        } catch (error) {
            console.error("Failed to fetch collection report:", error);
        } finally {
            setLoading(false);
        }
    }, [startDate, endDate, paymentMethod]);

    const fetchOutstandingBalances = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                school_year: schoolYear,
                semester: semester,
            });

            const response = await fetch(`/administrators/finance/reports/outstanding-balances?${params}`);
            const data = await response.json();
            setOutstandingData(data);
        } catch (error) {
            console.error("Failed to fetch outstanding balances:", error);
        } finally {
            setLoading(false);
        }
    }, [schoolYear, semester]);

    const fetchScholarshipReport = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                school_year: schoolYear,
                semester: semester,
            });

            const response = await fetch(`/administrators/finance/reports/scholarship?${params}`);
            const data = await response.json();
            setScholarshipData(data);
        } catch (error) {
            console.error("Failed to fetch scholarship report:", error);
        } finally {
            setLoading(false);
        }
    }, [schoolYear, semester]);

    const fetchRevenueBreakdown = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                school_year: schoolYear,
                semester: semester,
            });

            const response = await fetch(`/administrators/finance/reports/revenue-breakdown?${params}`);
            const data = await response.json();
            setRevenueData(data);
        } catch (error) {
            console.error("Failed to fetch revenue breakdown:", error);
        } finally {
            setLoading(false);
        }
    }, [schoolYear, semester]);

    const fetchFullyPaidReport = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                school_year: schoolYear,
                semester: semester,
            });

            const response = await fetch(`/administrators/finance/reports/fully-paid?${params}`);
            const data = await response.json();
            setFullyPaidData(data);
        } catch (error) {
            console.error("Failed to fetch fully paid report:", error);
        } finally {
            setLoading(false);
        }
    }, [schoolYear, semester]);

    const fetchCashierPerformance = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                start_date: format(startDate, "yyyy-MM-dd"),
                end_date: format(endDate, "yyyy-MM-dd"),
            });

            const response = await fetch(`/administrators/finance/reports/cashier-performance?${params}`);
            const data = await response.json();
            setCashierData(data);
        } catch (error) {
            console.error("Failed to fetch cashier performance:", error);
        } finally {
            setLoading(false);
        }
    }, [startDate, endDate]);

    const handleGenerateReport = () => {
        switch (activeTab) {
            case "daily":
                fetchDailyCollection();
                break;
            case "collection":
                fetchCollectionReport();
                break;
            case "outstanding":
                fetchOutstandingBalances();
                break;
            case "scholarship":
                fetchScholarshipReport();
                break;
            case "revenue":
                fetchRevenueBreakdown();
                break;
            case "fullypaid":
                fetchFullyPaidReport();
                break;
            case "cashier":
                fetchCashierPerformance();
                break;
        }
    };

    const exportToCSV = (data: Record<string, unknown>[], filename: string) => {
        if (!data || data.length === 0) return;

        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(","),
            ...data.map((row) =>
                headers
                    .map((header) => {
                        const value = row[header];
                        if (typeof value === "string" && value.includes(",")) {
                            return `"${value}"`;
                        }
                        return value;
                    })
                    .join(","),
            ),
        ].join("\n");

        const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `${filename}_${format(new Date(), "yyyy-MM-dd")}.csv`;
        link.click();
    };

    const renderLoadingSkeleton = () => (
        <div className="space-y-4">
            <div className="grid gap-4 md:grid-cols-4">
                {[1, 2, 3, 4].map((i) => (
                    <Skeleton key={i} className="h-24" />
                ))}
            </div>
            <Skeleton className="h-64" />
        </div>
    );

    return (
        <AdminLayout user={user} title="Financial Reports">
            <Head title="Finance - Reports" />

            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-foreground text-2xl font-bold tracking-tight">Financial Reports</h2>
                    <p className="text-muted-foreground">Generate comprehensive financial reports and analytics.</p>
                </div>
            </div>

            <Tabs value={activeTab} onValueChange={setActiveTab} className="mt-6">
                <TabsList className="mb-4 h-auto flex-wrap gap-1">
                    <TabsTrigger value="daily" className="gap-2">
                        <IconReceipt className="size-4" />
                        <span className="hidden sm:inline">Daily Collection</span>
                        <span className="sm:hidden">Daily</span>
                    </TabsTrigger>
                    <TabsTrigger value="collection" className="gap-2">
                        <IconCash className="size-4" />
                        <span className="hidden sm:inline">Collection Report</span>
                        <span className="sm:hidden">Collection</span>
                    </TabsTrigger>
                    <TabsTrigger value="outstanding" className="gap-2">
                        <IconReportMoney className="size-4" />
                        <span className="hidden sm:inline">Outstanding</span>
                        <span className="sm:hidden">Balance</span>
                    </TabsTrigger>
                    <TabsTrigger value="scholarship" className="gap-2">
                        <IconSchool className="size-4" />
                        <span className="hidden sm:inline">Scholarships</span>
                        <span className="sm:hidden">Scholar</span>
                    </TabsTrigger>
                    <TabsTrigger value="revenue" className="gap-2">
                        <IconChartBar className="size-4" />
                        <span className="hidden sm:inline">Revenue</span>
                        <span className="sm:hidden">Revenue</span>
                    </TabsTrigger>
                    <TabsTrigger value="fullypaid" className="gap-2">
                        <IconUserCheck className="size-4" />
                        <span className="hidden sm:inline">Fully Paid</span>
                        <span className="sm:hidden">Paid</span>
                    </TabsTrigger>
                    <TabsTrigger value="cashier" className="gap-2">
                        <IconUsers className="size-4" />
                        <span className="hidden sm:inline">Cashier</span>
                        <span className="sm:hidden">Staff</span>
                    </TabsTrigger>
                </TabsList>

                {/* Daily Collection Report */}
                <TabsContent value="daily">
                    <Card>
                        <CardHeader>
                            <CardTitle>Daily Collection Report</CardTitle>
                            <CardDescription>View all transactions collected on a specific date.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Filters */}
                            <div className="flex flex-wrap items-end gap-4">
                                <div className="space-y-2">
                                    <Label>Select Date</Label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button variant="outline" className="w-[200px] justify-start text-left font-normal">
                                                <IconCalendar className="mr-2 size-4" />
                                                {format(selectedDate, "PPP")}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0" align="start">
                                            <Calendar
                                                mode="single"
                                                selected={selectedDate}
                                                onSelect={(date) => date && setSelectedDate(date)}
                                                initialFocus
                                            />
                                        </PopoverContent>
                                    </Popover>
                                </div>
                                <Button onClick={handleGenerateReport} disabled={loading}>
                                    {loading ? <IconLoader2 className="mr-2 size-4 animate-spin" /> : <IconRefresh className="mr-2 size-4" />}
                                    Generate Report
                                </Button>
                                {dailyData && dailyData.transactions.length > 0 && (
                                    <Button variant="outline" onClick={() => exportToCSV(dailyData.transactions, "daily_collection")}>
                                        <IconFileSpreadsheet className="mr-2 size-4" />
                                        Export CSV
                                    </Button>
                                )}
                            </div>

                            {loading && renderLoadingSkeleton()}

                            {!loading && dailyData && (
                                <>
                                    {/* Summary Cards */}
                                    <div className="grid gap-4 md:grid-cols-4">
                                        <Card className="border-l-4 border-l-emerald-500">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Collected</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{formatCurrency(dailyData.summary.total_amount)}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Transactions</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{dailyData.summary.total_transactions}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Report Date</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{dailyData.summary.date}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Avg. Transaction</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">
                                                    {formatCurrency(
                                                        dailyData.summary.total_transactions > 0
                                                            ? dailyData.summary.total_amount / dailyData.summary.total_transactions
                                                            : 0,
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    {/* Payment Method Breakdown */}
                                    {Object.keys(dailyData.summary.by_payment_method).length > 0 && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-lg">By Payment Method</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="flex flex-wrap gap-4">
                                                    {Object.entries(dailyData.summary.by_payment_method).map(([method, data]) => (
                                                        <div key={method} className="flex items-center gap-2 rounded-lg border p-3">
                                                            <Badge variant="outline">{method}</Badge>
                                                            <span className="text-muted-foreground text-sm">{data.count} txns</span>
                                                            <span className="font-semibold">{formatCurrency(data.total)}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    )}

                                    {/* Transactions Table */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-lg">Transactions</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>Transaction #</TableHead>
                                                        <TableHead>Student</TableHead>
                                                        <TableHead>Time</TableHead>
                                                        <TableHead>Method</TableHead>
                                                        <TableHead>Cashier</TableHead>
                                                        <TableHead className="text-right">Amount</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {dailyData.transactions.length > 0 ? (
                                                        dailyData.transactions.map((tx) => (
                                                            <TableRow key={tx.id}>
                                                                <TableCell className="font-mono text-xs">{tx.transaction_number}</TableCell>
                                                                <TableCell>
                                                                    <div className="font-medium">{tx.student_name}</div>
                                                                    <div className="text-muted-foreground text-xs">{tx.student_id}</div>
                                                                </TableCell>
                                                                <TableCell>{tx.time}</TableCell>
                                                                <TableCell>
                                                                    <Badge variant="outline">{tx.payment_method}</Badge>
                                                                </TableCell>
                                                                <TableCell>{tx.cashier}</TableCell>
                                                                <TableCell className="text-right font-bold">{formatCurrency(tx.amount)}</TableCell>
                                                            </TableRow>
                                                        ))
                                                    ) : (
                                                        <TableRow>
                                                            <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                                                                No transactions found for this date.
                                                            </TableCell>
                                                        </TableRow>
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </CardContent>
                                    </Card>
                                </>
                            )}

                            {!loading && !dailyData && (
                                <div className="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed text-center">
                                    <IconReceipt className="text-muted-foreground/50 mb-4 size-12" />
                                    <p className="text-muted-foreground">Select a date and click "Generate Report" to view the daily collection.</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Collection Report (Date Range) */}
                <TabsContent value="collection">
                    <Card>
                        <CardHeader>
                            <CardTitle>Collection Report</CardTitle>
                            <CardDescription>View all transactions within a date range with optional filters.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Filters */}
                            <div className="flex flex-wrap items-end gap-4">
                                <div className="space-y-2">
                                    <Label>Start Date</Label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button variant="outline" className="w-[180px] justify-start text-left font-normal">
                                                <IconCalendar className="mr-2 size-4" />
                                                {format(startDate, "PP")}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0" align="start">
                                            <Calendar
                                                mode="single"
                                                selected={startDate}
                                                onSelect={(date) => date && setStartDate(date)}
                                                initialFocus
                                            />
                                        </PopoverContent>
                                    </Popover>
                                </div>
                                <div className="space-y-2">
                                    <Label>End Date</Label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button variant="outline" className="w-[180px] justify-start text-left font-normal">
                                                <IconCalendar className="mr-2 size-4" />
                                                {format(endDate, "PP")}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0" align="start">
                                            <Calendar mode="single" selected={endDate} onSelect={(date) => date && setEndDate(date)} initialFocus />
                                        </PopoverContent>
                                    </Popover>
                                </div>
                                <div className="space-y-2">
                                    <Label>Payment Method</Label>
                                    <Select value={paymentMethod || "all"} onValueChange={(value) => setPaymentMethod(value === "all" ? "" : value)}>
                                        <SelectTrigger className="w-[180px]">
                                            <SelectValue placeholder="All methods" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All methods</SelectItem>
                                            {filters.payment_methods
                                                .filter((method) => method)
                                                .map((method) => (
                                                    <SelectItem key={method} value={method}>
                                                        {method}
                                                    </SelectItem>
                                                ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button onClick={handleGenerateReport} disabled={loading}>
                                    {loading ? <IconLoader2 className="mr-2 size-4 animate-spin" /> : <IconRefresh className="mr-2 size-4" />}
                                    Generate
                                </Button>
                                {collectionData && collectionData.transactions.length > 0 && (
                                    <Button variant="outline" onClick={() => exportToCSV(collectionData.transactions, "collection_report")}>
                                        <IconFileSpreadsheet className="mr-2 size-4" />
                                        Export CSV
                                    </Button>
                                )}
                            </div>

                            {loading && renderLoadingSkeleton()}

                            {!loading && collectionData && (
                                <>
                                    {/* Summary */}
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <Card className="border-l-4 border-l-emerald-500">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Collected</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{formatCurrency(collectionData.summary.total_amount)}</div>
                                                <p className="text-muted-foreground text-xs">
                                                    {collectionData.summary.start_date} - {collectionData.summary.end_date}
                                                </p>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Transactions</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{collectionData.summary.total_transactions}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Daily Average</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">
                                                    {formatCurrency(
                                                        collectionData.summary.daily_breakdown.length > 0
                                                            ? collectionData.summary.total_amount / collectionData.summary.daily_breakdown.length
                                                            : 0,
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    {/* Daily Breakdown Chart */}
                                    {collectionData.summary.daily_breakdown.length > 0 && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-lg">Daily Breakdown</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <ChartContainer config={barChartConfig} className="h-[250px] w-full">
                                                    <BarChart data={collectionData.summary.daily_breakdown}>
                                                        <XAxis dataKey="date" tickLine={false} axisLine={false} fontSize={12} />
                                                        <YAxis
                                                            tickFormatter={(v) =>
                                                                new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
                                                                    notation: "compact",
                                                                    compactDisplay: "short",
                                                                }).format(v)
                                                            }
                                                            tickLine={false}
                                                            axisLine={false}
                                                            fontSize={12}
                                                        />
                                                        <ChartTooltip content={<ChartTooltipContent />} />
                                                        <Bar dataKey="total" fill="var(--color-total)" radius={4} />
                                                    </BarChart>
                                                </ChartContainer>
                                            </CardContent>
                                        </Card>
                                    )}

                                    {/* Transactions Table */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-lg">Transactions ({collectionData.transactions.length})</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="max-h-[400px] overflow-auto">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Transaction #</TableHead>
                                                            <TableHead>Student</TableHead>
                                                            <TableHead>Date</TableHead>
                                                            <TableHead>Method</TableHead>
                                                            <TableHead className="text-right">Amount</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {collectionData.transactions.length > 0 ? (
                                                            collectionData.transactions.map((tx) => (
                                                                <TableRow key={tx.id}>
                                                                    <TableCell className="font-mono text-xs">{tx.transaction_number}</TableCell>
                                                                    <TableCell>
                                                                        <div className="font-medium">{tx.student_name}</div>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {tx.date} {tx.time}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <Badge variant="outline">{tx.payment_method}</Badge>
                                                                    </TableCell>
                                                                    <TableCell className="text-right font-bold">
                                                                        {formatCurrency(tx.amount)}
                                                                    </TableCell>
                                                                </TableRow>
                                                            ))
                                                        ) : (
                                                            <TableRow>
                                                                <TableCell colSpan={5} className="text-muted-foreground h-24 text-center">
                                                                    No transactions found for this period.
                                                                </TableCell>
                                                            </TableRow>
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </>
                            )}

                            {!loading && !collectionData && (
                                <div className="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed text-center">
                                    <IconCash className="text-muted-foreground/50 mb-4 size-12" />
                                    <p className="text-muted-foreground">Set date range and click "Generate" to view the collection report.</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Outstanding Balances Report */}
                <TabsContent value="outstanding">
                    <Card>
                        <CardHeader>
                            <CardTitle>Outstanding Balances Report</CardTitle>
                            <CardDescription>View students with remaining balances for a specific academic period.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Filters */}
                            <div className="flex flex-wrap items-end gap-4">
                                <div className="space-y-2">
                                    <Label>School Year</Label>
                                    <Select value={schoolYear} onValueChange={setSchoolYear}>
                                        <SelectTrigger className="w-[180px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filters.school_years.map((sy) => (
                                                <SelectItem key={sy} value={sy}>
                                                    {sy}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Semester</Label>
                                    <Select value={semester} onValueChange={setSemester}>
                                        <SelectTrigger className="w-[150px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">1st Semester</SelectItem>
                                            <SelectItem value="2">2nd Semester</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button onClick={handleGenerateReport} disabled={loading}>
                                    {loading ? <IconLoader2 className="mr-2 size-4 animate-spin" /> : <IconRefresh className="mr-2 size-4" />}
                                    Generate
                                </Button>
                                {outstandingData && outstandingData.students.length > 0 && (
                                    <Button variant="outline" onClick={() => exportToCSV(outstandingData.students, "outstanding_balances")}>
                                        <IconFileSpreadsheet className="mr-2 size-4" />
                                        Export CSV
                                    </Button>
                                )}
                            </div>

                            {loading && renderLoadingSkeleton()}

                            {!loading && outstandingData && (
                                <>
                                    {/* Summary */}
                                    <div className="grid gap-4 md:grid-cols-4">
                                        <Card className="border-l-4 border-l-red-500">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Outstanding</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold text-red-600">
                                                    {formatCurrency(outstandingData.summary.total_outstanding)}
                                                </div>
                                            </CardContent>
                                        </Card>
                                        <Card className="border-l-4 border-l-emerald-500">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Collected</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold text-emerald-600">
                                                    {formatCurrency(outstandingData.summary.total_collected)}
                                                </div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Students with Balance</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{outstandingData.summary.total_students}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Collection Rate</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{outstandingData.summary.collection_rate}%</div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    {/* Students Table */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-lg">
                                                Students with Outstanding Balance ({outstandingData.students.length})
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="max-h-[500px] overflow-auto">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Student ID</TableHead>
                                                            <TableHead>Name</TableHead>
                                                            <TableHead>Course</TableHead>
                                                            <TableHead className="text-right">Total Tuition</TableHead>
                                                            <TableHead className="text-right">Paid</TableHead>
                                                            <TableHead className="text-right">Balance</TableHead>
                                                            <TableHead className="text-center">Progress</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {outstandingData.students.length > 0 ? (
                                                            outstandingData.students.map((student) => (
                                                                <TableRow key={student.id}>
                                                                    <TableCell className="font-mono text-xs">{student.student_id}</TableCell>
                                                                    <TableCell className="font-medium">{student.student_name}</TableCell>
                                                                    <TableCell>
                                                                        <Badge variant="outline">
                                                                            {student.course} - Year {student.year_level}
                                                                        </Badge>
                                                                    </TableCell>
                                                                    <TableCell className="text-right">
                                                                        {formatCurrency(student.total_tuition)}
                                                                    </TableCell>
                                                                    <TableCell className="text-right text-emerald-600">
                                                                        {formatCurrency(student.total_paid)}
                                                                    </TableCell>
                                                                    <TableCell className="text-right font-bold text-red-600">
                                                                        {formatCurrency(student.balance)}
                                                                    </TableCell>
                                                                    <TableCell className="text-center">
                                                                        <Badge
                                                                            variant={
                                                                                student.payment_progress >= 75
                                                                                    ? "default"
                                                                                    : student.payment_progress >= 50
                                                                                      ? "secondary"
                                                                                      : "destructive"
                                                                            }
                                                                        >
                                                                            {student.payment_progress}%
                                                                        </Badge>
                                                                    </TableCell>
                                                                </TableRow>
                                                            ))
                                                        ) : (
                                                            <TableRow>
                                                                <TableCell colSpan={7} className="text-muted-foreground h-24 text-center">
                                                                    No students with outstanding balances found.
                                                                </TableCell>
                                                            </TableRow>
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </>
                            )}

                            {!loading && !outstandingData && (
                                <div className="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed text-center">
                                    <IconReportMoney className="text-muted-foreground/50 mb-4 size-12" />
                                    <p className="text-muted-foreground">
                                        Select an academic period and click "Generate" to view outstanding balances.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Scholarship Report */}
                <TabsContent value="scholarship">
                    <Card>
                        <CardHeader>
                            <CardTitle>Scholarship & Discount Summary</CardTitle>
                            <CardDescription>View all students with scholarships or discounts applied.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Filters */}
                            <div className="flex flex-wrap items-end gap-4">
                                <div className="space-y-2">
                                    <Label>School Year</Label>
                                    <Select value={schoolYear} onValueChange={setSchoolYear}>
                                        <SelectTrigger className="w-[180px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filters.school_years.map((sy) => (
                                                <SelectItem key={sy} value={sy}>
                                                    {sy}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Semester</Label>
                                    <Select value={semester} onValueChange={setSemester}>
                                        <SelectTrigger className="w-[150px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">1st Semester</SelectItem>
                                            <SelectItem value="2">2nd Semester</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button onClick={handleGenerateReport} disabled={loading}>
                                    {loading ? <IconLoader2 className="mr-2 size-4 animate-spin" /> : <IconRefresh className="mr-2 size-4" />}
                                    Generate
                                </Button>
                                {scholarshipData && scholarshipData.scholars.length > 0 && (
                                    <Button variant="outline" onClick={() => exportToCSV(scholarshipData.scholars, "scholarship_report")}>
                                        <IconFileSpreadsheet className="mr-2 size-4" />
                                        Export CSV
                                    </Button>
                                )}
                            </div>

                            {loading && renderLoadingSkeleton()}

                            {!loading && scholarshipData && (
                                <>
                                    {/* Summary */}
                                    <div className="grid gap-4 md:grid-cols-4">
                                        <Card className="border-l-4 border-l-purple-500">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Scholars</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{scholarshipData.summary.total_scholars}</div>
                                            </CardContent>
                                        </Card>
                                        <Card className="border-l-4 border-l-amber-500">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Discount Granted</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">
                                                    {formatCurrency(scholarshipData.summary.total_discount_granted)}
                                                </div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Original Revenue</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{formatCurrency(scholarshipData.summary.original_revenue)}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Discounted Revenue</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{formatCurrency(scholarshipData.summary.discounted_revenue)}</div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    {/* Discount Level Breakdown */}
                                    {scholarshipData.summary.by_discount_level.length > 0 && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-lg">Discount Level Breakdown</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="flex flex-wrap gap-3">
                                                        {scholarshipData.summary.by_discount_level.map((level, idx) => (
                                                            <div key={level.discount} className="flex items-center gap-2 rounded-lg border p-3">
                                                                <Badge
                                                                    style={{
                                                                        backgroundColor: chartColors[idx % chartColors.length],
                                                                    }}
                                                                    className="text-white"
                                                                >
                                                                    {level.discount}
                                                                </Badge>
                                                                <span className="text-muted-foreground text-sm">{level.count} students</span>
                                                                <span className="font-semibold">{formatCurrency(level.total_discount)}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                    <ChartContainer config={pieChartConfig} className="h-[200px]">
                                                        <PieChart>
                                                            <ChartTooltip content={<ChartTooltipContent />} />
                                                            <Pie
                                                                data={scholarshipData.summary.by_discount_level.map((l, i) => ({
                                                                    name: l.discount,
                                                                    value: l.count,
                                                                    fill: chartColors[i % chartColors.length],
                                                                }))}
                                                                dataKey="value"
                                                                nameKey="name"
                                                                innerRadius={50}
                                                                outerRadius={80}
                                                            >
                                                                {scholarshipData.summary.by_discount_level.map((_, i) => (
                                                                    <Cell key={i} fill={chartColors[i % chartColors.length]} />
                                                                ))}
                                                            </Pie>
                                                        </PieChart>
                                                    </ChartContainer>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    )}

                                    {/* Scholars Table */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-lg">Scholars ({scholarshipData.scholars.length})</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="max-h-[400px] overflow-auto">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Student ID</TableHead>
                                                            <TableHead>Name</TableHead>
                                                            <TableHead>Course</TableHead>
                                                            <TableHead className="text-center">Discount</TableHead>
                                                            <TableHead className="text-right">Original</TableHead>
                                                            <TableHead className="text-right">Discount Amt</TableHead>
                                                            <TableHead className="text-right">Final</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {scholarshipData.scholars.length > 0 ? (
                                                            scholarshipData.scholars.map((scholar) => (
                                                                <TableRow key={scholar.id}>
                                                                    <TableCell className="font-mono text-xs">{scholar.student_id}</TableCell>
                                                                    <TableCell className="font-medium">{scholar.student_name}</TableCell>
                                                                    <TableCell>
                                                                        <Badge variant="outline">{scholar.course}</Badge>
                                                                    </TableCell>
                                                                    <TableCell className="text-center">
                                                                        <Badge variant="secondary">{scholar.discount_percentage}%</Badge>
                                                                    </TableCell>
                                                                    <TableCell className="text-right">
                                                                        {formatCurrency(scholar.original_tuition)}
                                                                    </TableCell>
                                                                    <TableCell className="text-right text-amber-600">
                                                                        -{formatCurrency(scholar.discount_amount)}
                                                                    </TableCell>
                                                                    <TableCell className="text-right font-bold">
                                                                        {formatCurrency(scholar.discounted_tuition)}
                                                                    </TableCell>
                                                                </TableRow>
                                                            ))
                                                        ) : (
                                                            <TableRow>
                                                                <TableCell colSpan={7} className="text-muted-foreground h-24 text-center">
                                                                    No scholars found for this period.
                                                                </TableCell>
                                                            </TableRow>
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </>
                            )}

                            {!loading && !scholarshipData && (
                                <div className="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed text-center">
                                    <IconSchool className="text-muted-foreground/50 mb-4 size-12" />
                                    <p className="text-muted-foreground">
                                        Select an academic period and click "Generate" to view scholarship summary.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Revenue Breakdown Report */}
                <TabsContent value="revenue">
                    <Card>
                        <CardHeader>
                            <CardTitle>Revenue Breakdown</CardTitle>
                            <CardDescription>Analyze revenue by fee type and monthly trends.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Filters */}
                            <div className="flex flex-wrap items-end gap-4">
                                <div className="space-y-2">
                                    <Label>School Year</Label>
                                    <Select value={schoolYear} onValueChange={setSchoolYear}>
                                        <SelectTrigger className="w-[180px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filters.school_years.map((sy) => (
                                                <SelectItem key={sy} value={sy}>
                                                    {sy}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Semester</Label>
                                    <Select value={semester} onValueChange={setSemester}>
                                        <SelectTrigger className="w-[150px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">1st Semester</SelectItem>
                                            <SelectItem value="2">2nd Semester</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button onClick={handleGenerateReport} disabled={loading}>
                                    {loading ? <IconLoader2 className="mr-2 size-4 animate-spin" /> : <IconRefresh className="mr-2 size-4" />}
                                    Generate
                                </Button>
                            </div>

                            {loading && renderLoadingSkeleton()}

                            {!loading && revenueData && (
                                <>
                                    {/* Summary */}
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <Card className="border-l-4 border-l-emerald-500">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Revenue</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{formatCurrency(revenueData.summary.total_revenue)}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Transactions</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{revenueData.summary.total_transactions}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Academic Period</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-lg font-bold">
                                                    {revenueData.summary.school_year} - Sem {revenueData.summary.semester}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    {/* Revenue by Fee Type */}
                                    <div className="grid gap-4 lg:grid-cols-2">
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-lg">Revenue by Fee Type</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="space-y-3">
                                                    {revenueData.summary.breakdown
                                                        .filter((item) => item.total > 0)
                                                        .sort((a, b) => b.total - a.total)
                                                        .map((item, idx) => (
                                                            <div key={item.key} className="flex items-center justify-between rounded-lg border p-3">
                                                                <div className="flex items-center gap-3">
                                                                    <div
                                                                        className="size-3 rounded-full"
                                                                        style={{
                                                                            backgroundColor: chartColors[idx % chartColors.length],
                                                                        }}
                                                                    />
                                                                    <span className="font-medium">{item.label}</span>
                                                                </div>
                                                                <span className="font-bold">{formatCurrency(item.total)}</span>
                                                            </div>
                                                        ))}
                                                </div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-lg">Distribution</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <ChartContainer config={pieChartConfig} className="h-[300px]">
                                                    <PieChart>
                                                        <ChartTooltip content={<ChartTooltipContent />} />
                                                        <Pie
                                                            data={revenueData.summary.breakdown
                                                                .filter((item) => item.total > 0)
                                                                .map((item, i) => ({
                                                                    name: item.label,
                                                                    value: item.total,
                                                                    fill: chartColors[i % chartColors.length],
                                                                }))}
                                                            dataKey="value"
                                                            nameKey="name"
                                                            innerRadius={60}
                                                            outerRadius={100}
                                                            label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                                                            labelLine={false}
                                                        >
                                                            {revenueData.summary.breakdown
                                                                .filter((item) => item.total > 0)
                                                                .map((_, i) => (
                                                                    <Cell key={i} fill={chartColors[i % chartColors.length]} />
                                                                ))}
                                                        </Pie>
                                                    </PieChart>
                                                </ChartContainer>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    {/* Monthly Trend */}
                                    {revenueData.summary.monthly_trend.length > 0 && (
                                        <Card>
                                            <CardHeader>
                                                <CardTitle className="text-lg">Monthly Trend</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <ChartContainer config={barChartConfig} className="h-[250px] w-full">
                                                    <BarChart data={revenueData.summary.monthly_trend}>
                                                        <XAxis dataKey="month" tickLine={false} axisLine={false} fontSize={12} />
                                                        <YAxis
                                                            tickFormatter={(v) =>
                                                                new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
                                                                    notation: "compact",
                                                                    compactDisplay: "short",
                                                                }).format(v)
                                                            }
                                                            tickLine={false}
                                                            axisLine={false}
                                                            fontSize={12}
                                                        />
                                                        <ChartTooltip content={<ChartTooltipContent />} />
                                                        <Bar dataKey="total" fill="var(--color-total)" radius={4} />
                                                    </BarChart>
                                                </ChartContainer>
                                            </CardContent>
                                        </Card>
                                    )}
                                </>
                            )}

                            {!loading && !revenueData && (
                                <div className="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed text-center">
                                    <IconChartBar className="text-muted-foreground/50 mb-4 size-12" />
                                    <p className="text-muted-foreground">Select an academic period and click "Generate" to view revenue breakdown.</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Fully Paid Students Report */}
                <TabsContent value="fullypaid">
                    <Card>
                        <CardHeader>
                            <CardTitle>Fully Paid Students</CardTitle>
                            <CardDescription>View all students who have completed their payments.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Filters */}
                            <div className="flex flex-wrap items-end gap-4">
                                <div className="space-y-2">
                                    <Label>School Year</Label>
                                    <Select value={schoolYear} onValueChange={setSchoolYear}>
                                        <SelectTrigger className="w-[180px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filters.school_years.map((sy) => (
                                                <SelectItem key={sy} value={sy}>
                                                    {sy}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Semester</Label>
                                    <Select value={semester} onValueChange={setSemester}>
                                        <SelectTrigger className="w-[150px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">1st Semester</SelectItem>
                                            <SelectItem value="2">2nd Semester</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button onClick={handleGenerateReport} disabled={loading}>
                                    {loading ? <IconLoader2 className="mr-2 size-4 animate-spin" /> : <IconRefresh className="mr-2 size-4" />}
                                    Generate
                                </Button>
                                {fullyPaidData && fullyPaidData.students.length > 0 && (
                                    <Button variant="outline" onClick={() => exportToCSV(fullyPaidData.students, "fully_paid_students")}>
                                        <IconFileSpreadsheet className="mr-2 size-4" />
                                        Export CSV
                                    </Button>
                                )}
                            </div>

                            {loading && renderLoadingSkeleton()}

                            {!loading && fullyPaidData && (
                                <>
                                    {/* Summary */}
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <Card className="border-l-4 border-l-emerald-500">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Fully Paid Students</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold text-emerald-600">{fullyPaidData.summary.total_students}</div>
                                            </CardContent>
                                        </Card>
                                        <Card className="border-l-4 border-l-blue-500">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Collected</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{formatCurrency(fullyPaidData.summary.total_collected)}</div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    {/* Students Table */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-lg">Fully Paid Students ({fullyPaidData.students.length})</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="max-h-[500px] overflow-auto">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Student ID</TableHead>
                                                            <TableHead>Name</TableHead>
                                                            <TableHead>Course</TableHead>
                                                            <TableHead>Year</TableHead>
                                                            <TableHead className="text-center">Discount</TableHead>
                                                            <TableHead className="text-right">Total Paid</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {fullyPaidData.students.length > 0 ? (
                                                            fullyPaidData.students.map((student) => (
                                                                <TableRow key={student.id}>
                                                                    <TableCell className="font-mono text-xs">{student.student_id}</TableCell>
                                                                    <TableCell className="font-medium">{student.student_name}</TableCell>
                                                                    <TableCell>
                                                                        <Badge variant="outline">{student.course}</Badge>
                                                                    </TableCell>
                                                                    <TableCell>{student.year_level}</TableCell>
                                                                    <TableCell className="text-center">
                                                                        <Badge variant="secondary">{student.discount}</Badge>
                                                                    </TableCell>
                                                                    <TableCell className="text-right font-bold text-emerald-600">
                                                                        {formatCurrency(student.total_paid)}
                                                                    </TableCell>
                                                                </TableRow>
                                                            ))
                                                        ) : (
                                                            <TableRow>
                                                                <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                                                                    No fully paid students found for this period.
                                                                </TableCell>
                                                            </TableRow>
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </>
                            )}

                            {!loading && !fullyPaidData && (
                                <div className="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed text-center">
                                    <IconUserCheck className="text-muted-foreground/50 mb-4 size-12" />
                                    <p className="text-muted-foreground">
                                        Select an academic period and click "Generate" to view fully paid students.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Cashier Performance Report */}
                <TabsContent value="cashier">
                    <Card>
                        <CardHeader>
                            <CardTitle>Cashier Performance Report</CardTitle>
                            <CardDescription>Analyze transaction performance by cashier.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Filters */}
                            <div className="flex flex-wrap items-end gap-4">
                                <div className="space-y-2">
                                    <Label>Start Date</Label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button variant="outline" className="w-[180px] justify-start text-left font-normal">
                                                <IconCalendar className="mr-2 size-4" />
                                                {format(startDate, "PP")}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0" align="start">
                                            <Calendar
                                                mode="single"
                                                selected={startDate}
                                                onSelect={(date) => date && setStartDate(date)}
                                                initialFocus
                                            />
                                        </PopoverContent>
                                    </Popover>
                                </div>
                                <div className="space-y-2">
                                    <Label>End Date</Label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button variant="outline" className="w-[180px] justify-start text-left font-normal">
                                                <IconCalendar className="mr-2 size-4" />
                                                {format(endDate, "PP")}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0" align="start">
                                            <Calendar mode="single" selected={endDate} onSelect={(date) => date && setEndDate(date)} initialFocus />
                                        </PopoverContent>
                                    </Popover>
                                </div>
                                <Button onClick={handleGenerateReport} disabled={loading}>
                                    {loading ? <IconLoader2 className="mr-2 size-4 animate-spin" /> : <IconRefresh className="mr-2 size-4" />}
                                    Generate
                                </Button>
                                {cashierData && cashierData.cashiers.length > 0 && (
                                    <Button variant="outline" onClick={() => exportToCSV(cashierData.cashiers, "cashier_performance")}>
                                        <IconFileSpreadsheet className="mr-2 size-4" />
                                        Export CSV
                                    </Button>
                                )}
                            </div>

                            {loading && renderLoadingSkeleton()}

                            {!loading && cashierData && (
                                <>
                                    {/* Summary */}
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <Card className="border-l-4 border-l-emerald-500">
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Collected</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{formatCurrency(cashierData.summary.total_collected)}</div>
                                                <p className="text-muted-foreground text-xs">
                                                    {cashierData.summary.start_date} - {cashierData.summary.end_date}
                                                </p>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Total Transactions</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{cashierData.summary.total_transactions}</div>
                                            </CardContent>
                                        </Card>
                                        <Card>
                                            <CardHeader className="pb-2">
                                                <CardTitle className="text-muted-foreground text-sm font-medium">Active Cashiers</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="text-2xl font-bold">{cashierData.summary.total_cashiers}</div>
                                            </CardContent>
                                        </Card>
                                    </div>

                                    {/* Cashiers Table */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-lg">Cashier Performance</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>Cashier</TableHead>
                                                        <TableHead className="text-center">Transactions</TableHead>
                                                        <TableHead className="text-right">Total Collected</TableHead>
                                                        <TableHead className="text-right">Avg Transaction</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {cashierData.cashiers.length > 0 ? (
                                                        cashierData.cashiers.map((cashier, idx) => (
                                                            <TableRow key={cashier.cashier_id || idx}>
                                                                <TableCell className="font-medium">{cashier.cashier_name}</TableCell>
                                                                <TableCell className="text-center">
                                                                    <Badge variant="outline">{cashier.transaction_count}</Badge>
                                                                </TableCell>
                                                                <TableCell className="text-right font-bold">
                                                                    {formatCurrency(cashier.total_collected)}
                                                                </TableCell>
                                                                <TableCell className="text-muted-foreground text-right">
                                                                    {formatCurrency(cashier.average_transaction)}
                                                                </TableCell>
                                                            </TableRow>
                                                        ))
                                                    ) : (
                                                        <TableRow>
                                                            <TableCell colSpan={4} className="text-muted-foreground h-24 text-center">
                                                                No cashier data found for this period.
                                                            </TableCell>
                                                        </TableRow>
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </CardContent>
                                    </Card>
                                </>
                            )}

                            {!loading && !cashierData && (
                                <div className="flex h-64 flex-col items-center justify-center rounded-lg border border-dashed text-center">
                                    <IconUsers className="text-muted-foreground/50 mb-4 size-12" />
                                    <p className="text-muted-foreground">Set date range and click "Generate" to view cashier performance.</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </AdminLayout>
    );
}
