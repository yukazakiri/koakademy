import AdminLayout from "@/components/administrators/admin-layout";
import { Area, AreaChart, Bar, BarChart, BarXAxis, ChartTooltip, Grid, XAxis, chartCssVars } from "@/components/charts";
import { StatCardArea, type StatCardAreaPoint } from "@/components/stat-card-area";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import { ArrowRight, Banknote, ClipboardList, CreditCard, FileSpreadsheet, Landmark, Percent, ReceiptText, Search, UsersRound } from "lucide-react";
import { route } from "ziggy-js";

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

interface CollectionQueueItem {
    id: number;
    student_id: string;
    student_name: string;
    course: string;
    year_level: string | number;
    total_amount: number;
    paid: number;
    balance: number;
    payment_progress: number;
}

interface CashierDeskAction {
    label: string;
    description: string;
    href: string;
}

interface CashierDesk {
    ready_for_collection: number;
    average_transaction_today: number;
    next_actions: CashierDeskAction[];
}

interface FinanceDashboardProps {
    user: User;
    stats: FinanceStats;
    payment_methods: PaymentMethodData[];
    daily_collection: DailyCollectionData[];
    recent_transactions: TransactionItem[];
    top_students: TopStudent[];
    collection_queue: CollectionQueueItem[];
    cashier_desk: CashierDesk;
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

function statusTone(value: number): string {
    if (value >= 80) {
        return "text-emerald-600";
    }

    if (value >= 50) {
        return "text-amber-600";
    }

    return "text-destructive";
}

function toIsoMonthDate(label: string, fallbackIndex: number): string {
    const parsed = new Date(label);

    if (!Number.isNaN(parsed.getTime())) {
        return parsed.toISOString();
    }

    const fallback = new Date();
    fallback.setMonth(fallback.getMonth() - fallbackIndex);
    fallback.setDate(1);
    fallback.setHours(0, 0, 0, 0);

    return fallback.toISOString();
}

function toIsoDayDate(label: string, fallbackIndex: number): string {
    const year = new Date().getFullYear();
    const parsed = new Date(`${label} ${year}`);

    if (!Number.isNaN(parsed.getTime())) {
        return parsed.toISOString();
    }

    const fallback = new Date();
    fallback.setDate(fallback.getDate() - fallbackIndex);
    fallback.setHours(0, 0, 0, 0);

    return fallback.toISOString();
}

function computeTrend(series: StatCardAreaPoint[]): number {
    const current = series.at(-1)?.value ?? 0;
    const previous = series.at(-2)?.value ?? 0;

    if (previous === 0) {
        return current > 0 ? 100 : 0;
    }

    return ((current - previous) / previous) * 100;
}

function buildScaledSeries(baseSeries: StatCardAreaPoint[], targetValue: number): StatCardAreaPoint[] {
    const lastValue = baseSeries.at(-1)?.value ?? 0;

    if (baseSeries.length === 0) {
        return [{ date: new Date().toISOString(), value: targetValue }];
    }

    if (lastValue <= 0) {
        return baseSeries.map((point) => ({ ...point, value: targetValue }));
    }

    const scale = targetValue / lastValue;

    return baseSeries.map((point) => ({
        date: point.date,
        value: Math.max(0, point.value * scale),
    }));
}

function hasPositiveSeries(series: StatCardAreaPoint[]): boolean {
    return series.some((point) => point.value > 0);
}

export default function FinanceDashboard({
    user,
    stats,
    payment_methods,
    daily_collection,
    recent_transactions,
    top_students,
    collection_queue,
    cashier_desk,
    fee_breakdown,
    chart_data,
    current_period,
}: FinanceDashboardProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const formatCurrency = (amount: number) =>
        new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency,
        }).format(amount || 0);

    const paidPercentage = stats.total_enrolled > 0 ? Math.round((stats.fully_paid_count / stats.total_enrolled) * 100) : 0;
    const balancePercentage = stats.total_enrolled > 0 ? Math.round((stats.outstanding_count / stats.total_enrolled) * 100) : 0;
    const topFeeBreakdown = fee_breakdown.slice(0, 4);
    const totalPaymentChannelAmount = payment_methods.reduce((total, method) => total + method.total, 0);
    const totalFeeBreakdownAmount = topFeeBreakdown.reduce((total, fee) => total + fee.total, 0);
    const monthlyCollectionSeries = chart_data.map((point, index) => ({
        date: toIsoMonthDate(point.month, chart_data.length - index),
        value: point.total,
    }));
    const dailyCollectionSeries = daily_collection.map((point, index) => ({
        date: toIsoDayDate(point.date, daily_collection.length - index),
        value: point.total,
    }));
    const dailyCollectionBars = daily_collection.map((point) => ({
        label: point.date,
        value: point.total,
    }));
    const outstandingSeries = buildScaledSeries(monthlyCollectionSeries, stats.total_collectibles);
    const clearedAccountsSeries = buildScaledSeries(monthlyCollectionSeries, stats.fully_paid_count);
    const currencyFormat = {
        currency,
        style: "currency",
    } satisfies Intl.NumberFormatOptions;

    return (
        <AdminLayout user={user} title="Finance Desk">
            <Head title="Finance Desk" />

            <div className="space-y-6">
                <section className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="max-w-3xl">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary" className="rounded-md">
                                SY {current_period.school_year}
                            </Badge>
                            <Badge variant="outline" className="rounded-md">
                                Semester {current_period.semester}
                            </Badge>
                        </div>
                        <h1 className="text-foreground mt-3 text-3xl font-bold tracking-tight">Finance Desk</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            A cashier and accounting workspace for receiving tuition payments, checking balances, printing receipts, and watching
                            collection health without leaving the desk flow.
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button asChild size="lg" className="gap-2">
                            <Link href={route("administrators.finance.payments.create")}>
                                <Banknote className="size-4" />
                                Receive Payment
                            </Link>
                        </Button>
                        <Button asChild variant="outline" size="lg" className="gap-2">
                            <Link href={route("administrators.finance.payments")}>
                                <Search className="size-4" />
                                Find Receipt
                            </Link>
                        </Button>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCardArea
                        chartColor="var(--chart-1)"
                        data={monthlyCollectionSeries}
                        description={`${stats.collection_rate}% collection rate for this school period.`}
                        formatOptions={currencyFormat}
                        label="Collected"
                        title="Collected this period"
                        trend={computeTrend(monthlyCollectionSeries)}
                        value={stats.total_revenue}
                    />
                    <StatCardArea
                        chartColor="var(--chart-4)"
                        data={outstandingSeries}
                        description={`${stats.outstanding_count} students still need cashier attention.`}
                        formatOptions={currencyFormat}
                        label="Balance"
                        title="Outstanding balances"
                        trend={computeTrend(outstandingSeries)}
                        value={stats.total_collectibles}
                    />
                    <StatCardArea
                        chartColor="var(--chart-2)"
                        data={dailyCollectionSeries}
                        description={`${stats.today_transactions} payments today, avg ${formatCurrency(cashier_desk.average_transaction_today)}.`}
                        formatOptions={currencyFormat}
                        label="Today"
                        title="Cashier drawer"
                        trend={computeTrend(dailyCollectionSeries)}
                        value={stats.today_collection}
                    />
                    <StatCardArea
                        chartColor="var(--chart-3)"
                        data={clearedAccountsSeries}
                        description={`${paidPercentage}% fully paid, ${balancePercentage}% with balances.`}
                        label="Students"
                        title="Cleared accounts"
                        trend={computeTrend(clearedAccountsSeries)}
                        value={stats.fully_paid_count}
                    />
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(340px,0.8fr)]">
                    <Card>
                        <CardHeader className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <CardTitle>Cashier Work Queue</CardTitle>
                                <CardDescription>Students with the largest current-period balances are listed first.</CardDescription>
                            </div>
                            <Button asChild variant="outline" className="gap-2">
                                <Link href={route("administrators.finance.invoices", { query: { status: "unpaid" } })}>
                                    Open Billing List
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Student</TableHead>
                                        <TableHead>Progress</TableHead>
                                        <TableHead className="text-right">Balance</TableHead>
                                        <TableHead></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {collection_queue.length > 0 ? (
                                        collection_queue.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <div className="font-medium">{item.student_name}</div>
                                                    <div className="text-muted-foreground text-xs">
                                                        {item.student_id} / {item.course} / Year {item.year_level}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="min-w-36">
                                                    <div className="flex items-center gap-2">
                                                        <Progress value={item.payment_progress} className="h-2" />
                                                        <span className="text-muted-foreground w-10 text-right text-xs tabular-nums">
                                                            {item.payment_progress}%
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right font-semibold text-amber-600">
                                                    {formatCurrency(item.balance)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button asChild size="sm" variant="outline">
                                                        <Link
                                                            href={route("administrators.finance.payments.create", {
                                                                query: { student: item.student_id },
                                                            })}
                                                        >
                                                            Pay
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-muted-foreground h-24 text-center">
                                                No unpaid current-period balances are queued.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Desk Actions</CardTitle>
                            <CardDescription>Common cashier and accounting tasks.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {cashier_desk.next_actions.map((action) => (
                                <Button key={action.label} asChild variant="outline" className="h-auto w-full justify-start gap-3 p-4 text-left">
                                    <Link href={action.href}>
                                        <ClipboardList className="text-primary size-5 shrink-0" />
                                        <span className="min-w-0">
                                            <span className="block font-semibold">{action.label}</span>
                                            <span className="text-muted-foreground mt-1 block text-xs font-normal whitespace-normal">
                                                {action.description}
                                            </span>
                                        </span>
                                    </Link>
                                </Button>
                            ))}
                            <div className="bg-muted/30 rounded-lg border p-4">
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-medium">Collection health</p>
                                        <p className="text-muted-foreground text-xs">Assessed {formatCurrency(stats.total_assessed)}</p>
                                    </div>
                                    <p className={`text-2xl font-bold ${statusTone(stats.collection_rate)}`}>{stats.collection_rate}%</p>
                                </div>
                                <Progress value={stats.collection_rate} className="mt-3 h-2" />
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 xl:grid-cols-3">
                    <Card className="overflow-hidden xl:col-span-2">
                        <CardHeader className="border-border/60 border-b">
                            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <CardTitle>Collection Trend</CardTitle>
                                    <CardDescription>Monthly collections, paired with reporting and receipt review actions.</CardDescription>
                                </div>
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <Button asChild variant="outline" size="sm" className="gap-2">
                                        <Link href={route("administrators.finance.reports", { tab: "revenue" })}>
                                            <FileSpreadsheet className="size-4" />
                                            Revenue report
                                        </Link>
                                    </Button>
                                    <Button asChild variant="ghost" size="sm" className="gap-2">
                                        <Link href={route("administrators.finance.payments")}>
                                            Review receipts
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-5 p-5">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="bg-background/40 rounded-lg border p-4">
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Collected</p>
                                    <p className="mt-2 text-xl font-bold">{formatCurrency(stats.total_revenue)}</p>
                                </div>
                                <div className="bg-background/40 rounded-lg border p-4">
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Assessed</p>
                                    <p className="mt-2 text-xl font-bold">{formatCurrency(stats.total_assessed)}</p>
                                </div>
                                <div className="bg-background/40 rounded-lg border p-4">
                                    <p className="text-muted-foreground text-xs font-medium uppercase">Collection rate</p>
                                    <p className={`mt-2 text-xl font-bold ${statusTone(stats.collection_rate)}`}>{stats.collection_rate}%</p>
                                </div>
                            </div>
                            {hasPositiveSeries(monthlyCollectionSeries) ? (
                                <AreaChart
                                    data={monthlyCollectionSeries}
                                    xDataKey="date"
                                    className="h-[280px] w-full"
                                    aspectRatio="16 / 6"
                                    margin={{ left: 24, right: 24, top: 24, bottom: 36 }}
                                    revealSignature={`finance-monthly-${stats.total_revenue}`}
                                >
                                    <Grid horizontal />
                                    <Area
                                        dataKey="value"
                                        fill={chartCssVars.linePrimary}
                                        fillOpacity={0.38}
                                        gradientToOpacity={0.04}
                                        showMarkers
                                        stroke={chartCssVars.linePrimary}
                                    />
                                    <XAxis tickMode="data" />
                                    <ChartTooltip
                                        showDatePill
                                        rows={(point) => [
                                            {
                                                color: chartCssVars.linePrimary,
                                                label: "Collected",
                                                value: formatCurrency(Number(point.value ?? 0)),
                                            },
                                        ]}
                                    />
                                </AreaChart>
                            ) : (
                                <EmptyState label="No collection trend data is available yet." />
                            )}
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden">
                        <CardHeader className="border-border/60 border-b">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <CardTitle>Payment Channels</CardTitle>
                                    <CardDescription>Open a filtered receipt list by method.</CardDescription>
                                </div>
                                <CreditCard className="text-muted-foreground mt-1 size-5" />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 p-5">
                            <div className="bg-background/40 rounded-lg border p-4">
                                <p className="text-muted-foreground text-xs font-medium uppercase">Channel total</p>
                                <p className="mt-2 text-2xl font-bold">{formatCurrency(totalPaymentChannelAmount)}</p>
                                <p className="text-muted-foreground mt-1 text-xs">{payment_methods.length} active methods</p>
                            </div>
                            {payment_methods.length > 0 ? (
                                payment_methods.map((method) => (
                                    <Button key={method.method} asChild variant="outline" className="h-auto w-full justify-start p-0 text-left">
                                        <Link href={route("administrators.finance.payments", { method: method.method })}>
                                            <span className="block w-full p-4">
                                                <span className="flex items-start justify-between gap-3">
                                                    <span>
                                                        <span className="block font-semibold">{method.method}</span>
                                                        <span className="text-muted-foreground mt-1 block text-xs font-normal">
                                                            {method.count} transactions
                                                        </span>
                                                    </span>
                                                    <span className="text-right font-semibold">{formatCurrency(method.total)}</span>
                                                </span>
                                                <span className="bg-muted mt-3 block h-2 overflow-hidden rounded-full">
                                                    <span
                                                        className="bg-primary block h-full rounded-full"
                                                        style={{
                                                            width: `${Math.max(2, Math.round((method.total / Math.max(totalPaymentChannelAmount, 1)) * 100))}%`,
                                                        }}
                                                    />
                                                </span>
                                            </span>
                                        </Link>
                                    </Button>
                                ))
                            ) : (
                                <EmptyState label="No payment channel data yet." />
                            )}
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(420px,0.95fr)]">
                    <Card className="overflow-hidden">
                        <CardHeader className="border-border/60 border-b">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle>Recent Receipts</CardTitle>
                                    <CardDescription>Open receipts directly or continue intake.</CardDescription>
                                </div>
                                <div className="flex gap-2">
                                    <Button asChild variant="outline" size="sm" className="gap-2">
                                        <Link href={route("administrators.finance.payments.create")}>
                                            <Banknote className="size-4" />
                                            New
                                        </Link>
                                    </Button>
                                    <Button asChild variant="ghost" size="sm" className="gap-2">
                                        <Link href={route("administrators.finance.payments")}>
                                            View all
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-5">Receipt</TableHead>
                                        <TableHead>Cashier</TableHead>
                                        <TableHead className="text-right">Amount</TableHead>
                                        <TableHead className="pr-5 text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recent_transactions.slice(0, 6).map((transaction) => (
                                        <TableRow key={transaction.id}>
                                            <TableCell className="pl-5">
                                                <div className="font-medium">{transaction.student_name}</div>
                                                <div className="text-muted-foreground text-xs">
                                                    {transaction.transaction_number} / {transaction.date} {transaction.time}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">{transaction.cashier}</Badge>
                                            </TableCell>
                                            <TableCell className="text-right font-semibold">{formatCurrency(transaction.amount)}</TableCell>
                                            <TableCell className="pr-5 text-right">
                                                <Button asChild size="sm" variant="ghost" className="gap-2">
                                                    <Link href={route("administrators.finance.payments.show", transaction.id)}>
                                                        <ReceiptText className="size-4" />
                                                        Open
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {recent_transactions.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-muted-foreground h-28 text-center">
                                                No receipts recorded yet.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden">
                        <CardHeader className="border-border/60 border-b">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <CardTitle>Accounting Snapshot</CardTitle>
                                    <CardDescription>Quick jumps into the reports behind the numbers.</CardDescription>
                                </div>
                                <Landmark className="text-muted-foreground mt-1 size-5" />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-5 p-5">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <Button asChild variant="outline" className="h-auto justify-start p-4 text-left">
                                    <Link href={route("administrators.finance.reports", { tab: "scholarship" })}>
                                        <span className="block">
                                            <span className="text-muted-foreground flex items-center gap-2 text-sm">
                                                <Percent className="size-4" />
                                                Discounts
                                            </span>
                                            <span className="mt-2 block text-xl font-bold">{formatCurrency(stats.total_discounts)}</span>
                                            <span className="text-muted-foreground mt-1 block text-xs font-normal">
                                                {stats.discounted_students} students
                                            </span>
                                        </span>
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="h-auto justify-start p-4 text-left">
                                    <Link href={route("administrators.finance.invoices", { query: { status: "unpaid" } })}>
                                        <span className="block">
                                            <span className="text-muted-foreground flex items-center gap-2 text-sm">
                                                <ClipboardList className="size-4" />
                                                Follow-up
                                            </span>
                                            <span className="mt-2 block text-xl font-bold">{cashier_desk.ready_for_collection}</span>
                                            <span className="text-muted-foreground mt-1 block text-xs font-normal">highest balances queued</span>
                                        </span>
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="h-auto justify-start p-4 text-left">
                                    <Link href={route("administrators.finance.reports", { tab: "fullypaid" })}>
                                        <span className="block">
                                            <span className="text-muted-foreground flex items-center gap-2 text-sm">
                                                <UsersRound className="size-4" />
                                                Cleared
                                            </span>
                                            <span className="mt-2 block text-xl font-bold">{stats.fully_paid_count}</span>
                                            <span className="text-muted-foreground mt-1 block text-xs font-normal">{paidPercentage}% fully paid</span>
                                        </span>
                                    </Link>
                                </Button>
                            </div>

                            <div className="bg-background/40 rounded-lg border p-4">
                                <div className="mb-4 flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold">Fee breakdown</p>
                                        <p className="text-muted-foreground text-xs">Posted receipts by fee type</p>
                                    </div>
                                    <Button asChild variant="ghost" size="sm" className="gap-2">
                                        <Link href={route("administrators.finance.reports", { tab: "revenue" })}>
                                            Report
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                                <div className="space-y-3">
                                    {topFeeBreakdown.map((fee) => (
                                        <div key={fee.key} className="space-y-2">
                                            <div className="flex items-center justify-between gap-4 text-sm">
                                                <span className="text-muted-foreground">{fee.label}</span>
                                                <span className="font-semibold">{formatCurrency(fee.total)}</span>
                                            </div>
                                            <div className="bg-muted h-2 overflow-hidden rounded-full">
                                                <div
                                                    className="bg-primary h-full rounded-full"
                                                    style={{
                                                        width: `${Math.max(2, Math.round((fee.total / Math.max(totalFeeBreakdownAmount, 1)) * 100))}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                    {topFeeBreakdown.length === 0 && <EmptyState label="No fee breakdown available yet." />}
                                </div>
                            </div>

                            <div className="bg-background/40 rounded-lg border p-4">
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold">Last 7 days</p>
                                        <p className="text-muted-foreground text-xs">Daily cashier totals</p>
                                    </div>
                                    <Button asChild variant="ghost" size="sm" className="gap-2">
                                        <Link href={route("administrators.finance.reports", { tab: "daily" })}>
                                            Daily
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                                {dailyCollectionBars.some((point) => point.value > 0) ? (
                                    <BarChart
                                        data={dailyCollectionBars}
                                        xDataKey="label"
                                        className="h-[170px] w-full"
                                        aspectRatio="16 / 5"
                                        margin={{ left: 18, right: 18, top: 18, bottom: 34 }}
                                        revealSignature={`finance-daily-${stats.today_collection}`}
                                    >
                                        <Grid horizontal />
                                        <Bar dataKey="value" fill={chartCssVars.linePrimary} minBarHeight={3} />
                                        <BarXAxis showAllLabels />
                                        <ChartTooltip
                                            showDatePill={false}
                                            rows={(point) => [
                                                {
                                                    color: chartCssVars.linePrimary,
                                                    label: "Collected",
                                                    value: formatCurrency(Number(point.value ?? 0)),
                                                },
                                            ]}
                                        />
                                    </BarChart>
                                ) : (
                                    <EmptyState label="No daily collection data yet." />
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </section>

                {top_students.length > 0 && (
                    <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        {top_students.map((student, index) => (
                            <Card key={student.student_id}>
                                <CardContent className="space-y-4 p-4">
                                    <div className="flex items-center gap-3">
                                        <div className="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-full text-sm font-bold">
                                            {index + 1}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold">{student.student_name}</p>
                                            <p className="text-muted-foreground text-xs">{student.transaction_count} payments</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-semibold">{formatCurrency(student.total_paid)}</p>
                                        <Button asChild size="sm" variant="ghost">
                                            <Link href={route("administrators.finance.payments", { search: student.student_id })}>Receipts</Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </section>
                )}
            </div>
        </AdminLayout>
    );
}

function EmptyState({ label }: { label: string }) {
    return (
        <div className="text-muted-foreground flex min-h-24 items-center justify-center rounded-lg border border-dashed text-center text-sm">
            {label}
        </div>
    );
}
