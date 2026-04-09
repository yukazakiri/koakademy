import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from "@/components/ui/chart";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import {
    IconArrowDownRight,
    IconArrowUpRight,
    IconCash,
    IconCashBanknote,
    IconCreditCard,
    IconFileText,
    IconPercentage,
    IconReceipt,
    IconReportMoney,
    IconSchool,
    IconTrendingUp,
    IconUser,
    IconUsers,
    IconWallet,
} from "@tabler/icons-react";
import { Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, Pie, PieChart, XAxis, YAxis } from "recharts";

interface FinanceStats {
    total_revenue: number;
    total_collectibles: number;
    total_assessed: number;
    collection_rate: number;
    fully_paid_count: number;
    outstanding_count: number;
    total_enrolled: number;
    today_collection: number;
    today_transactions: number;
    total_discounts: number;
    discounted_students: number;
}

interface PaymentMethodData {
    method: string;
    count: number;
    total: number;
}

interface DailyCollectionData {
    date: string;
    day: string;
    count: number;
    total: number;
}

interface TransactionItem {
    id: number;
    transaction_number: string;
    student_name: string;
    student_id: string;
    amount: number;
    payment_method: string;
    status: string;
    cashier: string;
    date: string;
    time: string;
}

interface TopStudent {
    student_id: string;
    student_name: string;
    total_paid: number;
    transaction_count: number;
}

interface FeeBreakdown {
    key: string;
    label: string;
    total: number;
}

interface ChartDataPoint {
    month: string;
    total: number;
}

interface FinanceDashboardProps {
    user: User;
    stats: FinanceStats;
    payment_methods: PaymentMethodData[];
    daily_collection: DailyCollectionData[];
    recent_transactions: TransactionItem[];
    top_students: TopStudent[];
    fee_breakdown: FeeBreakdown[];
    chart_data: ChartDataPoint[];
    current_period: {
        school_year: string;
        semester: number;
    };
}

interface Branding {
    currency: string;
}

const chartConfig = {
    revenue: {
        label: "Revenue",
        color: "hsl(142.1 76.2% 36.3%)",
    },
} satisfies ChartConfig;

const pieChartConfig = {
    value: { label: "Amount" },
} satisfies ChartConfig;

const COLORS = [
    "hsl(142.1 76.2% 36.3%)",
    "hsl(221.2 83.2% 53.3%)",
    "hsl(47.9 95.8% 53.1%)",
    "hsl(346.8 77.2% 49.8%)",
    "hsl(262.1 83.3% 57.8%)",
    "hsl(24.6 95% 53.1%)",
    "hsl(173.4 80.4% 40%)",
    "hsl(340 75% 55%)",
];

export default function FinanceDashboard({
    user,
    stats,
    payment_methods,
    daily_collection,
    recent_transactions,
    top_students,
    fee_breakdown,
    chart_data,
    current_period,
}: FinanceDashboardProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency: currency,
        }).format(amount);
    };

    const formatCompact = (amount: number) => {
        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            notation: "compact",
            compactDisplay: "short",
        }).format(amount);
    };

    const pendingPercentage = stats.total_enrolled > 0 ? Math.round((stats.outstanding_count / stats.total_enrolled) * 100) : 0;

    const paidPercentage = stats.total_enrolled > 0 ? Math.round((stats.fully_paid_count / stats.total_enrolled) * 100) : 0;

    return (
        <AdminLayout user={user} title="Finance Overview">
            <Head title="Finance • Overview" />

            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 className="text-foreground text-2xl font-bold tracking-tight">Finance Dashboard</h2>
                    <p className="text-muted-foreground">
                        Financial overview for SY {current_period.school_year}, Semester {current_period.semester}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button asChild variant="outline">
                        <Link href="/administrators/finance/reports">
                            <IconReportMoney className="mr-2 size-4" />
                            Reports
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href="/administrators/finance/payments/create">
                            <IconCash className="mr-2 size-4" />
                            New Payment
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Key Metrics Row */}
            <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card className="border-l-4 border-l-emerald-500 shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">Total Revenue</CardTitle>
                        <IconWallet className="size-4 text-emerald-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-foreground text-2xl font-bold">{formatCurrency(stats.total_revenue)}</div>
                        <p className="text-muted-foreground mt-1 text-xs">Collected this period</p>
                    </CardContent>
                </Card>

                <Card className="border-l-4 border-l-amber-500 shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">Outstanding Balance</CardTitle>
                        <IconCashBanknote className="size-4 text-amber-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-foreground text-2xl font-bold">{formatCurrency(stats.total_collectibles)}</div>
                        <p className="text-muted-foreground mt-1 text-xs">Remaining collectibles</p>
                    </CardContent>
                </Card>

                <Card className="border-l-4 border-l-blue-500 shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">Total Assessed</CardTitle>
                        <IconFileText className="size-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-foreground text-2xl font-bold">{formatCurrency(stats.total_assessed)}</div>
                        <p className="text-muted-foreground mt-1 text-xs">Total tuition assessed</p>
                    </CardContent>
                </Card>

                <Card className="border-l-4 border-l-purple-500 shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">Collection Rate</CardTitle>
                        <IconPercentage className="size-4 text-purple-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-foreground text-2xl font-bold">{stats.collection_rate}%</div>
                        <div className="mt-1 flex items-center gap-1 text-xs">
                            {stats.collection_rate >= 80 ? (
                                <span className="flex items-center text-emerald-500">
                                    <IconArrowUpRight className="size-3" /> Excellent
                                </span>
                            ) : stats.collection_rate >= 50 ? (
                                <span className="flex items-center text-amber-500">
                                    <IconTrendingUp className="size-3" /> Good
                                </span>
                            ) : (
                                <span className="flex items-center text-red-500">
                                    <IconArrowDownRight className="size-3" /> Needs Attention
                                </span>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Second Row - Today's Stats & Enrolled Students */}
            <div className="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card className="shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">Today's Collection</CardTitle>
                        <IconCash className="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-foreground text-2xl font-bold">{formatCurrency(stats.today_collection)}</div>
                        <p className="text-muted-foreground mt-1 text-xs">{stats.today_transactions} transactions</p>
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">Total Enrolled</CardTitle>
                        <IconUsers className="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-foreground text-2xl font-bold">{stats.total_enrolled}</div>
                        <p className="text-muted-foreground mt-1 text-xs">Active students</p>
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">Fully Paid</CardTitle>
                        <IconUser className="size-4 text-emerald-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-foreground text-2xl font-bold">
                            {stats.fully_paid_count} <span className="text-muted-foreground text-sm font-normal">({paidPercentage}%)</span>
                        </div>
                        <p className="text-muted-foreground mt-1 text-xs">Cleared accounts</p>
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">With Balance</CardTitle>
                        <IconCreditCard className="size-4 text-amber-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-foreground text-2xl font-bold">
                            {stats.outstanding_count} <span className="text-muted-foreground text-sm font-normal">({pendingPercentage}%)</span>
                        </div>
                        <p className="text-muted-foreground mt-1 text-xs">Pending payments</p>
                    </CardContent>
                </Card>
            </div>

            {/* Third Row - Discounts & Quick Links */}
            <div className="mt-4 grid gap-4 md:grid-cols-3">
                <Card className="shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">Total Discounts</CardTitle>
                        <IconPercentage className="size-4 text-purple-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-foreground text-2xl font-bold">{formatCurrency(stats.total_discounts)}</div>
                        <p className="text-muted-foreground mt-1 text-xs">{stats.discounted_students} students with discounts</p>
                    </CardContent>
                </Card>

                <Card className="col-span-2 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-lg">Quick Actions</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/administrators/finance/reports?tab=daily">
                                <IconReceipt className="mr-2 size-4" />
                                Daily Collection
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/administrators/finance/reports?tab=collection">
                                <IconFileText className="mr-2 size-4" />
                                Collection Report
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/administrators/finance/reports?tab=outstanding">
                                <IconCashBanknote className="mr-2 size-4" />
                                Outstanding
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/administrators/finance/reports?tab=scholarship">
                                <IconSchool className="mr-2 size-4" />
                                Scholarships
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/administrators/finance/reports?tab=revenue">
                                <IconTrendingUp className="mr-2 size-4" />
                                Revenue Breakdown
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            {/* Charts Row */}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                {/* Revenue Trend */}
                <Card>
                    <CardHeader>
                        <CardTitle>Revenue Trend</CardTitle>
                        <CardDescription>Monthly collection for the past 12 months</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ChartContainer config={chartConfig} className="h-[250px] w-full">
                            <AreaChart data={chart_data}>
                                <defs>
                                    <linearGradient id="fillRevenue" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="var(--color-revenue)" stopOpacity={0.8} />
                                        <stop offset="95%" stopColor="var(--color-revenue)" stopOpacity={0.1} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid vertical={false} strokeDasharray="3 3" />
                                <XAxis dataKey="month" tickLine={false} axisLine={false} tickMargin={8} fontSize={10} />
                                <YAxis
                                    tickFormatter={(value) => formatCompact(value)}
                                    tickLine={false}
                                    axisLine={false}
                                    tickMargin={8}
                                    fontSize={10}
                                />
                                <ChartTooltip cursor={false} content={<ChartTooltipContent indicator="dot" />} />
                                <Area dataKey="total" type="monotone" fill="url(#fillRevenue)" stroke="var(--color-revenue)" strokeWidth={2} />
                            </AreaChart>
                        </ChartContainer>
                    </CardContent>
                </Card>

                {/* Fee Breakdown Pie Chart */}
                <Card>
                    <CardHeader>
                        <CardTitle>Revenue by Fee Type</CardTitle>
                        <CardDescription>Distribution of collected fees</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {fee_breakdown.length > 0 ? (
                            <ChartContainer config={pieChartConfig} className="h-[250px] w-full">
                                <PieChart>
                                    <Pie
                                        data={fee_breakdown}
                                        dataKey="total"
                                        nameKey="label"
                                        cx="50%"
                                        cy="50%"
                                        outerRadius={80}
                                        label={({ label, percent }) => `${label}: ${(percent * 100).toFixed(0)}%`}
                                        labelLine={false}
                                    >
                                        {fee_breakdown.map((entry, index) => (
                                            <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <ChartTooltip content={<ChartTooltipContent />} />
                                </PieChart>
                            </ChartContainer>
                        ) : (
                            <div className="text-muted-foreground flex h-[250px] items-center justify-center">No fee data available</div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Daily Collection & Payment Methods */}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                {/* Daily Collection Bar Chart */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daily Collection</CardTitle>
                        <CardDescription>Last 7 days collection</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ChartContainer config={chartConfig} className="h-[200px] w-full">
                            <BarChart data={daily_collection}>
                                <CartesianGrid vertical={false} strokeDasharray="3 3" />
                                <XAxis dataKey="date" tickLine={false} axisLine={false} tickMargin={8} fontSize={10} />
                                <YAxis
                                    tickFormatter={(value) => formatCompact(value)}
                                    tickLine={false}
                                    axisLine={false}
                                    tickMargin={8}
                                    fontSize={10}
                                />
                                <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
                                <Bar dataKey="total" fill="var(--color-revenue)" radius={4} />
                            </BarChart>
                        </ChartContainer>
                    </CardContent>
                </Card>

                {/* Payment Methods */}
                <Card>
                    <CardHeader>
                        <CardTitle>Payment Methods</CardTitle>
                        <CardDescription>Collection by payment type</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {payment_methods.length > 0 ? (
                            <div className="space-y-4">
                                {payment_methods.map((pm, index) => (
                                    <div key={pm.method} className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div
                                                className="flex h-8 w-8 items-center justify-center rounded-full"
                                                style={{ backgroundColor: COLORS[index % COLORS.length] + "20" }}
                                            >
                                                <IconCash className="size-4" style={{ color: COLORS[index % COLORS.length] }} />
                                            </div>
                                            <div>
                                                <div className="font-medium">{pm.method}</div>
                                                <div className="text-muted-foreground text-xs">{pm.count} transactions</div>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-medium">{formatCurrency(pm.total)}</div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-muted-foreground flex h-[150px] items-center justify-center">No payment data available</div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Recent Transactions & Top Students */}
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                {/* Recent Transactions */}
                <Card>
                    <CardHeader className="flex flex-row items-center">
                        <div className="flex-1">
                            <CardTitle>Recent Transactions</CardTitle>
                            <CardDescription>Latest payments received</CardDescription>
                        </div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/administrators/finance/payments">View All</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Student</TableHead>
                                    <TableHead>Method</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recent_transactions.length > 0 ? (
                                    recent_transactions.slice(0, 5).map((tx) => (
                                        <TableRow key={tx.id}>
                                            <TableCell>
                                                <div className="font-medium">{tx.student_name}</div>
                                                <div className="text-muted-foreground text-xs">
                                                    {tx.date} • {tx.transaction_number}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">{tx.payment_method}</Badge>
                                            </TableCell>
                                            <TableCell className="text-right font-medium">{formatCurrency(tx.amount)}</TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={3} className="text-muted-foreground text-center">
                                            No recent transactions
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Top Students */}
                <Card>
                    <CardHeader className="flex flex-row items-center">
                        <div className="flex-1">
                            <CardTitle>Top Payers</CardTitle>
                            <CardDescription>Students with highest payments this period</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {top_students.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Student</TableHead>
                                        <TableHead className="text-center">Transactions</TableHead>
                                        <TableHead className="text-right">Total Paid</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {top_students.map((student, index) => (
                                        <TableRow key={student.student_id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div className="bg-primary/10 flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium">
                                                        {index + 1}
                                                    </div>
                                                    <div className="font-medium">{student.student_name}</div>
                                                </div>
                                                <div className="text-muted-foreground text-xs">{student.student_id}</div>
                                            </TableCell>
                                            <TableCell className="text-center">{student.transaction_count}</TableCell>
                                            <TableCell className="text-right font-medium text-emerald-600">
                                                {formatCurrency(student.total_paid)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-muted-foreground flex h-[150px] items-center justify-center">No payment data available</div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
