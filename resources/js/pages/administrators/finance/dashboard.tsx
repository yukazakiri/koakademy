import AdminLayout from "@/components/administrators/admin-layout";
import { Area, AreaChart, Bar, BarChart, BarXAxis, ChartTooltip, Gauge, Grid, XAxis, chartCssVars } from "@/components/charts";
import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { StatCardArea, type StatCardAreaPoint } from "@/components/stat-card-area";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import { Head, Link, usePage } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowRight,
    ArrowUpRight,
    Banknote,
    BarChart3,
    Building2,
    CalendarDays,
    CheckCircle2,
    CircleDollarSign,
    ClipboardList,
    CreditCard,
    FileSpreadsheet,
    FileText,
    Landmark,
    Layers,
    Percent,
    ReceiptText,
    Search,
    Sparkles,
    TrendingUp,
    UsersRound,
    Wallet,
    type LucideIcon,
} from "lucide-react";
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

function getCollectionHealth(rate: number): {
    tone: string;
    badgeVariant: "success-light" | "warning-light" | "destructive-light";
    label: string;
} {
    if (rate >= 80) {
        return {
            tone: "text-emerald-600 dark:text-emerald-400",
            badgeVariant: "success-light",
            label: "Healthy",
        };
    }

    if (rate >= 50) {
        return {
            tone: "text-amber-600 dark:text-amber-400",
            badgeVariant: "warning-light",
            label: "Attention",
        };
    }

    return {
        tone: "text-destructive dark:text-destructive",
        badgeVariant: "destructive-light",
        label: "Critical",
    };
}

function getPaymentMethodIcon(methodName: string): LucideIcon {
    const m = methodName.toLowerCase();
    if (m.includes("cash")) {
        return Banknote;
    }
    if (m.includes("card") || m.includes("credit") || m.includes("debit")) {
        return CreditCard;
    }
    if (m.includes("bank") || m.includes("transfer") || m.includes("check") || m.includes("cheque")) {
        return Landmark;
    }
    if (m.includes("online") || m.includes("wallet") || m.includes("gcash") || m.includes("maya")) {
        return Wallet;
    }

    return CircleDollarSign;
}

function getDeskActionIcon(label: string): LucideIcon {
    const l = label.toLowerCase();
    if (l.includes("pay") || l.includes("receive") || l.includes("collect")) {
        return Banknote;
    }
    if (l.includes("receipt")) {
        return ReceiptText;
    }
    if (l.includes("invoice") || l.includes("bill")) {
        return FileText;
    }
    if (l.includes("adjust") || l.includes("tuition")) {
        return Building2;
    }
    if (l.includes("report") || l.includes("sheet")) {
        return FileSpreadsheet;
    }

    return ClipboardList;
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

function EmptyState({ label, icon: Icon = ClipboardList }: { label: string; icon?: LucideIcon }) {
    return (
        <div className="border-border/80 bg-muted/15 flex min-h-36 flex-col items-center justify-center rounded-xl border border-dashed p-6 text-center">
            <IconTile variant="outline" size="sm" className="text-muted-foreground/60 mb-2.5">
                <Icon className="size-4" />
            </IconTile>
            <p className="text-muted-foreground max-w-xs text-xs leading-relaxed font-medium">{label}</p>
        </div>
    );
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
            maximumFractionDigits: 2,
        }).format(amount || 0);

    const formatNumber = (value: number) => new Intl.NumberFormat("en-US").format(value || 0);

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

    const collectionHealth = getCollectionHealth(stats.collection_rate);
    const queuedTotalBalance = collection_queue.reduce((acc, item) => acc + item.balance, 0);

    return (
        <AdminLayout user={user} title="Finance Desk">
            <Head title="Finance Desk" />

            <div className="space-y-6">
                {/* Executive Master Frame */}
                <Frame variant="default" className="w-full shadow-2xs">
                    <FramePanel className="overflow-hidden p-0">
                        <div className="border-border/60 bg-card/60 flex flex-col gap-5 border-b p-5 md:p-6 lg:flex-row lg:items-center lg:justify-between">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline" size="sm" className="gap-1.5 font-medium">
                                        <CalendarDays className="text-primary size-3.5" />
                                        SY {current_period.school_year}
                                    </Badge>
                                    <Badge variant="outline" size="sm" className="font-medium">
                                        Semester {current_period.semester}
                                    </Badge>
                                    <Badge variant="success-light" size="sm" className="gap-1.5 font-medium">
                                        <span className="relative flex size-2">
                                            <span className="bg-success absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" />
                                            <span className="bg-success relative inline-flex size-2 rounded-full" />
                                        </span>
                                        Cashier Desk Active
                                    </Badge>
                                    <Badge variant="secondary" size="sm" className="text-muted-foreground font-mono tabular-nums">
                                        Cashier: {user.name}
                                    </Badge>
                                </div>
                                <h1 className="text-foreground mt-3.5 text-2xl font-bold tracking-tight md:text-3xl">Finance Desk</h1>
                                <p className="text-muted-foreground mt-1.5 max-w-3xl text-sm leading-relaxed">
                                    A cashier and accounting workspace for receiving tuition payments, checking balances, issuing receipts, and
                                    monitoring institutional collection health.
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2.5">
                                <Button size="sm" render={<Link href={route("administrators.finance.payments.create")} />}>
                                    <Banknote className="mr-1.5 size-4" />
                                    Receive Payment
                                </Button>
                                <Button variant="outline" size="sm" render={<Link href={route("administrators.finance.payments")} />}>
                                    <Search className="mr-1.5 size-4" />
                                    Find Receipt
                                </Button>
                                <Button variant="outline" size="sm" render={<Link href={route("administrators.finance.invoices")} />}>
                                    <FileText className="mr-1.5 size-4" />
                                    Billing List
                                </Button>
                            </div>
                        </div>

                        {/* Highlight Alert for Action Items */}
                        {cashier_desk.ready_for_collection > 0 && (
                            <div className="border-border/60 bg-muted/20 border-b p-4 sm:px-6">
                                <Alert variant="warning" className="border-warning/30 bg-warning/5">
                                    <IconTile variant="soft" size="sm" className="text-warning shrink-0">
                                        <AlertTriangle className="size-4" />
                                    </IconTile>
                                    <AlertTitle className="text-foreground flex items-center gap-2 text-sm font-semibold">
                                        <span>Action Required: Accounts Ready for Collection</span>
                                        <Badge variant="warning-light" size="xs" className="font-semibold tabular-nums">
                                            {formatNumber(cashier_desk.ready_for_collection)} students
                                        </Badge>
                                    </AlertTitle>
                                    <AlertDescription className="text-muted-foreground text-xs leading-relaxed">
                                        There are {formatNumber(cashier_desk.ready_for_collection)} student accounts queued with high outstanding
                                        balances for this semester awaiting cashier intake.
                                    </AlertDescription>
                                    <AlertAction className="gap-2">
                                        <Button
                                            variant="outline"
                                            size="xs"
                                            render={<Link href={route("administrators.finance.invoices", { query: { status: "unpaid" } })} />}
                                        >
                                            Open Unpaid Invoices
                                            <ArrowRight className="ml-1 size-3" />
                                        </Button>
                                    </AlertAction>
                                </Alert>
                            </div>
                        )}

                        {/* Four Area Stat Cards with Sparklines */}
                        <div className="bg-muted/15 grid gap-3.5 p-4 sm:p-5 md:grid-cols-2 xl:grid-cols-4">
                            <StatCardArea
                                chartColor="var(--chart-1)"
                                data={monthlyCollectionSeries}
                                description={`${stats.collection_rate}% collection rate for this school period.`}
                                formatOptions={currencyFormat}
                                label="Revenue"
                                title="Collected this period"
                                trend={computeTrend(monthlyCollectionSeries)}
                                value={stats.total_revenue}
                            />
                            <StatCardArea
                                chartColor="var(--chart-4)"
                                data={outstandingSeries}
                                description={`${formatNumber(stats.outstanding_count)} students still need cashier attention.`}
                                formatOptions={currencyFormat}
                                label="Balance"
                                title="Outstanding balances"
                                trend={computeTrend(outstandingSeries)}
                                value={stats.total_collectibles}
                            />
                            <StatCardArea
                                chartColor="var(--chart-2)"
                                data={dailyCollectionSeries}
                                description={`${formatNumber(stats.today_transactions)} payments today, avg ${formatCurrency(cashier_desk.average_transaction_today)}.`}
                                formatOptions={currencyFormat}
                                label="Drawer"
                                title="Cashier drawer (Today)"
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
                        </div>
                    </FramePanel>
                </Frame>

                {/* Cashier Work Queue & Desk Operations */}
                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(360px,0.85fr)]">
                    {/* Cashier Work Queue */}
                    <Frame variant="default" className="w-full">
                        <FramePanel className="overflow-hidden p-0">
                            <FrameHeader className="border-border/60 bg-card/60 flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <ClipboardList className="size-3.5" />
                                        </IconTile>
                                        <FrameTitle className="text-base font-semibold">Cashier Work Queue</FrameTitle>
                                        <Badge variant="secondary" size="xs" className="font-semibold tabular-nums">
                                            {formatNumber(collection_queue.length)} queued
                                        </Badge>
                                    </div>
                                    <FrameDescription className="text-xs">
                                        Prioritized students with largest current-period balances awaiting cashier settlement.
                                    </FrameDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="xs"
                                        render={<Link href={route("administrators.finance.invoices", { query: { status: "unpaid" } })} />}
                                    >
                                        Open Billing List
                                        <ArrowRight className="ml-1 size-3" />
                                    </Button>
                                </div>
                            </FrameHeader>

                            {collection_queue.length > 0 && (
                                <div className="border-border/40 bg-muted/20 grid grid-cols-2 gap-3 border-b px-5 py-3 sm:grid-cols-3">
                                    <div>
                                        <p className="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">Queue Total</p>
                                        <p className="text-foreground text-sm font-bold tabular-nums">{formatCurrency(queuedTotalBalance)}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">Queued Students</p>
                                        <p className="text-foreground text-sm font-bold tabular-nums">
                                            {formatNumber(collection_queue.length)} accounts
                                        </p>
                                    </div>
                                    <div className="col-span-2 sm:col-span-1">
                                        <p className="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">Avg Progress</p>
                                        <p className="text-foreground text-sm font-bold tabular-nums">
                                            {collection_queue.length > 0
                                                ? Math.round(
                                                      collection_queue.reduce((acc, i) => acc + i.payment_progress, 0) / collection_queue.length,
                                                  )
                                                : 0}
                                            %
                                        </p>
                                    </div>
                                </div>
                            )}

                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="border-border/60 bg-muted/30">
                                            <TableHead className="py-3 pl-5 text-[11px] font-semibold tracking-wider uppercase">Student</TableHead>
                                            <TableHead className="py-3 text-[11px] font-semibold tracking-wider uppercase">
                                                Payment Progress
                                            </TableHead>
                                            <TableHead className="py-3 text-right text-[11px] font-semibold tracking-wider uppercase">
                                                Balance Due
                                            </TableHead>
                                            <TableHead className="py-3 pr-5 text-right text-[11px] font-semibold tracking-wider uppercase">
                                                Action
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {collection_queue.length > 0 ? (
                                            collection_queue.map((item) => (
                                                <TableRow key={item.id} className="border-border/40 hover:bg-muted/30 transition-colors">
                                                    <TableCell className="py-3.5 pl-5">
                                                        <div className="text-foreground text-sm font-semibold">{item.student_name}</div>
                                                        <div className="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
                                                            <span className="border-border/60 bg-muted/60 text-foreground/80 py-0.2 rounded border px-1.5 font-mono text-[10px] font-medium tabular-nums">
                                                                {item.student_id}
                                                            </span>
                                                            <span>&middot;</span>
                                                            <span>{item.course}</span>
                                                            <span>&middot;</span>
                                                            <span>Year {item.year_level}</span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="min-w-44 py-3.5">
                                                        <div className="space-y-1">
                                                            <div className="flex items-center justify-between text-xs">
                                                                <span className="text-muted-foreground text-[11px] tabular-nums">
                                                                    Paid {formatCurrency(item.paid)}
                                                                </span>
                                                                <span className="font-mono text-xs font-semibold tabular-nums">
                                                                    {item.payment_progress}%
                                                                </span>
                                                            </div>
                                                            <Progress value={item.payment_progress} className="h-2" />
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="py-3.5 text-right font-semibold text-amber-600 tabular-nums dark:text-amber-400">
                                                        <div className="text-sm font-bold">{formatCurrency(item.balance)}</div>
                                                        <div className="text-muted-foreground text-[10px] tabular-nums">
                                                            of {formatCurrency(item.total_amount)}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="py-3.5 pr-5 text-right">
                                                        <Button
                                                            size="xs"
                                                            variant="default"
                                                            render={
                                                                <Link
                                                                    href={route("administrators.finance.payments.create", {
                                                                        query: { student: item.student_id },
                                                                    })}
                                                                />
                                                            }
                                                        >
                                                            Pay
                                                            <ArrowRight className="ml-1 size-3" />
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell colSpan={4} className="p-8">
                                                    <EmptyState
                                                        label="No unpaid current-period balances in the queue. All student accounts are up to date."
                                                        icon={CheckCircle2}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </FramePanel>
                    </Frame>

                    {/* Desk Actions & Collection Health */}
                    <Frame variant="default" className="w-full">
                        <FramePanel className="overflow-hidden p-0">
                            <FrameHeader className="border-border/60 bg-card/60 flex items-center justify-between border-b p-5">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Layers className="size-3.5" />
                                        </IconTile>
                                        <FrameTitle className="text-base font-semibold">Desk Actions & Health</FrameTitle>
                                    </div>
                                    <FrameDescription className="text-xs">Common cashier tasks and institutional fiscal status.</FrameDescription>
                                </div>
                            </FrameHeader>

                            <div className="space-y-4 p-5">
                                <div className="grid gap-2.5">
                                    {cashier_desk.next_actions.map((action) => {
                                        const ActionIcon = getDeskActionIcon(action.label);

                                        return (
                                            <div
                                                key={action.label}
                                                className="border-border/60 bg-card/60 hover:border-border hover:bg-card/90 group flex items-start gap-3 rounded-xl border p-3.5 transition-all duration-150"
                                            >
                                                <IconTile variant="soft" size="sm" className="text-primary mt-0.5 shrink-0">
                                                    <ActionIcon className="size-4" />
                                                </IconTile>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="text-foreground text-sm font-semibold">{action.label}</span>
                                                        <Button
                                                            variant="ghost"
                                                            size="xs"
                                                            className="text-primary shrink-0 transition-transform group-hover:translate-x-0.5"
                                                            render={<Link href={action.href} />}
                                                        >
                                                            Open
                                                            <ArrowUpRight className="ml-1 size-3" />
                                                        </Button>
                                                    </div>
                                                    <p className="text-muted-foreground mt-0.5 text-xs leading-relaxed">{action.description}</p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>

                                {/* Collection Health Card with Gauge */}
                                <div className="border-border/60 bg-muted/25 rounded-xl border p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="text-foreground text-sm font-semibold">Collection Health</p>
                                            <p className="text-muted-foreground text-xs">Total assessed vs collected</p>
                                        </div>
                                        <Badge variant={collectionHealth.badgeVariant} size="sm" className="font-semibold">
                                            {collectionHealth.label}
                                        </Badge>
                                    </div>

                                    <div className="my-2 flex justify-center">
                                        <Gauge
                                            value={stats.collection_rate}
                                            centerValue={stats.collection_rate}
                                            defaultLabel="Collected"
                                            suffix="%"
                                            inactiveFillOpacity={0.25}
                                            spacing={20}
                                            useGradient
                                        />
                                    </div>

                                    <div className="border-border/50 grid grid-cols-2 gap-3 border-t pt-3">
                                        <div>
                                            <p className="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">Assessed</p>
                                            <p className="text-foreground text-sm font-bold tabular-nums">{formatCurrency(stats.total_assessed)}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">Collected</p>
                                            <p className="text-sm font-bold text-emerald-600 tabular-nums dark:text-emerald-400">
                                                {formatCurrency(stats.total_revenue)}
                                            </p>
                                        </div>
                                    </div>
                                    <Progress value={stats.collection_rate} className="mt-3 h-2" />
                                </div>
                            </div>
                        </FramePanel>
                    </Frame>
                </section>

                {/* Collection Trend & Payment Channels */}
                <section className="grid gap-6 xl:grid-cols-3">
                    {/* Collection Trend (2 Cols) */}
                    <Frame variant="default" className="xl:col-span-2">
                        <FramePanel className="overflow-hidden p-0">
                            <FrameHeader className="border-border/60 bg-card/60 flex flex-col gap-4 border-b p-5 lg:flex-row lg:items-start lg:justify-between">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <TrendingUp className="size-3.5" />
                                        </IconTile>
                                        <FrameTitle className="text-base font-semibold">Collection Trend</FrameTitle>
                                    </div>
                                    <FrameDescription className="text-xs">
                                        Monthly collections, paired with reporting and receipt review actions.
                                    </FrameDescription>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="xs"
                                        render={<Link href={route("administrators.finance.reports", { tab: "revenue" })} />}
                                    >
                                        <FileSpreadsheet className="mr-1.5 size-3.5" />
                                        Revenue report
                                    </Button>
                                    <Button variant="ghost" size="xs" render={<Link href={route("administrators.finance.payments")} />}>
                                        Review receipts
                                        <ArrowRight className="ml-1 size-3.5" />
                                    </Button>
                                </div>
                            </FrameHeader>

                            <div className="space-y-5 p-5">
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="border-border/60 bg-muted/20 rounded-xl border p-3.5">
                                        <p className="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">Collected Revenue</p>
                                        <p className="text-foreground mt-1 text-xl font-bold tabular-nums">{formatCurrency(stats.total_revenue)}</p>
                                    </div>
                                    <div className="border-border/60 bg-muted/20 rounded-xl border p-3.5">
                                        <p className="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">Assessed Billing</p>
                                        <p className="text-foreground mt-1 text-xl font-bold tabular-nums">{formatCurrency(stats.total_assessed)}</p>
                                    </div>
                                    <div className="border-border/60 bg-muted/20 rounded-xl border p-3.5">
                                        <div className="flex items-center justify-between">
                                            <p className="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">Collection Rate</p>
                                            <Badge variant={collectionHealth.badgeVariant} size="xs">
                                                {collectionHealth.label}
                                            </Badge>
                                        </div>
                                        <p className={cn("mt-1 text-xl font-bold tabular-nums", collectionHealth.tone)}>{stats.collection_rate}%</p>
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
                                    <EmptyState label="No collection trend data is available yet." icon={TrendingUp} />
                                )}
                            </div>
                        </FramePanel>
                    </Frame>

                    {/* Payment Channels (1 Col) */}
                    <Frame variant="default">
                        <FramePanel className="overflow-hidden p-0">
                            <FrameHeader className="border-border/60 bg-card/60 flex items-start justify-between border-b p-5">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <CreditCard className="size-3.5" />
                                        </IconTile>
                                        <FrameTitle className="text-base font-semibold">Payment Channels</FrameTitle>
                                    </div>
                                    <FrameDescription className="text-xs">Distribution by payment method and settlement route.</FrameDescription>
                                </div>
                                <Badge variant="secondary" size="xs" className="font-semibold tabular-nums">
                                    {payment_methods.length} active
                                </Badge>
                            </FrameHeader>

                            <div className="space-y-4 p-5">
                                <div className="border-border/60 bg-muted/20 rounded-xl border p-4">
                                    <div className="flex items-center justify-between">
                                        <p className="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">Channel Volume</p>
                                        <Badge variant="outline" size="xs" className="font-medium tabular-nums">
                                            {formatNumber(stats.today_transactions)} txns today
                                        </Badge>
                                    </div>
                                    <p className="text-foreground mt-1 text-2xl font-bold tabular-nums">
                                        {formatCurrency(totalPaymentChannelAmount)}
                                    </p>
                                    <p className="text-muted-foreground mt-0.5 text-xs">Total settled through recorded methods</p>
                                </div>

                                {payment_methods.length > 0 ? (
                                    <div className="space-y-2.5">
                                        {payment_methods.map((method) => {
                                            const MethodIcon = getPaymentMethodIcon(method.method);
                                            const percentage = Math.max(2, Math.round((method.total / Math.max(totalPaymentChannelAmount, 1)) * 100));

                                            return (
                                                <Button
                                                    key={method.method}
                                                    variant="outline"
                                                    className="border-border/60 hover:border-border hover:bg-card/90 group h-auto w-full justify-start p-3.5 text-left transition-all duration-150"
                                                    render={<Link href={route("administrators.finance.payments", { method: method.method })} />}
                                                >
                                                    <div className="w-full space-y-2">
                                                        <div className="flex items-center justify-between gap-3">
                                                            <div className="flex items-center gap-2.5">
                                                                <IconTile variant="soft" size="xs" className="text-primary shrink-0">
                                                                    <MethodIcon className="size-3.5" />
                                                                </IconTile>
                                                                <div>
                                                                    <span className="text-foreground block text-sm font-semibold">
                                                                        {method.method}
                                                                    </span>
                                                                    <span className="text-muted-foreground block text-xs font-normal tabular-nums">
                                                                        {formatNumber(method.count)} transactions
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div className="text-right">
                                                                <span className="text-foreground block text-sm font-bold tabular-nums">
                                                                    {formatCurrency(method.total)}
                                                                </span>
                                                                <span className="text-muted-foreground block text-[11px] font-medium tabular-nums">
                                                                    {percentage}%
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                                            <div
                                                                className="bg-primary h-full rounded-full transition-all"
                                                                style={{ width: `${percentage}%` }}
                                                            />
                                                        </div>
                                                    </div>
                                                </Button>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <EmptyState label="No payment channel data recorded yet." icon={CreditCard} />
                                )}
                            </div>
                        </FramePanel>
                    </Frame>
                </section>

                {/* Recent Receipts & Accounting Snapshot */}
                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(420px,0.85fr)]">
                    {/* Recent Receipts Table */}
                    <Frame variant="default">
                        <FramePanel className="overflow-hidden p-0">
                            <FrameHeader className="border-border/60 bg-card/60 flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-start sm:justify-between">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <ReceiptText className="size-3.5" />
                                        </IconTile>
                                        <FrameTitle className="text-base font-semibold">Recent Receipts</FrameTitle>
                                    </div>
                                    <FrameDescription className="text-xs">
                                        Verified intake receipts, cashier logs, and transaction numbers.
                                    </FrameDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button size="xs" render={<Link href={route("administrators.finance.payments.create")} />}>
                                        <Banknote className="mr-1 size-3.5" />
                                        New Receipt
                                    </Button>
                                    <Button variant="ghost" size="xs" render={<Link href={route("administrators.finance.payments")} />}>
                                        View all
                                        <ArrowRight className="ml-1 size-3.5" />
                                    </Button>
                                </div>
                            </FrameHeader>

                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="border-border/60 bg-muted/30">
                                            <TableHead className="py-3 pl-5 text-[11px] font-semibold tracking-wider uppercase">
                                                Receipt & Student
                                            </TableHead>
                                            <TableHead className="py-3 text-[11px] font-semibold tracking-wider uppercase">Cashier</TableHead>
                                            <TableHead className="py-3 text-right text-[11px] font-semibold tracking-wider uppercase">
                                                Amount
                                            </TableHead>
                                            <TableHead className="py-3 pr-5 text-right text-[11px] font-semibold tracking-wider uppercase">
                                                Action
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recent_transactions.slice(0, 6).map((transaction) => {
                                            const MethodIcon = getPaymentMethodIcon(transaction.payment_method);

                                            return (
                                                <TableRow key={transaction.id} className="border-border/40 hover:bg-muted/30 transition-colors">
                                                    <TableCell className="py-3 pl-5">
                                                        <div className="text-foreground text-sm font-semibold">{transaction.student_name}</div>
                                                        <div className="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
                                                            <span className="font-mono text-[11px] font-medium tabular-nums">
                                                                {transaction.transaction_number}
                                                            </span>
                                                            <span>&middot;</span>
                                                            <span className="tabular-nums">
                                                                {transaction.date} {transaction.time}
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="py-3">
                                                        <div className="flex flex-col gap-1">
                                                            <Badge variant="outline" size="xs" className="w-fit font-medium">
                                                                {transaction.cashier}
                                                            </Badge>
                                                            <div className="text-muted-foreground flex items-center gap-1 text-[11px]">
                                                                <MethodIcon className="size-3" />
                                                                <span>{transaction.payment_method}</span>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-foreground py-3 text-right text-sm font-bold tabular-nums">
                                                        {formatCurrency(transaction.amount)}
                                                    </TableCell>
                                                    <TableCell className="py-3 pr-5 text-right">
                                                        <Button
                                                            size="xs"
                                                            variant="ghost"
                                                            className="text-primary gap-1"
                                                            render={<Link href={route("administrators.finance.payments.show", transaction.id)} />}
                                                        >
                                                            <ReceiptText className="size-3.5" />
                                                            Open
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                        {recent_transactions.length === 0 && (
                                            <TableRow>
                                                <TableCell colSpan={4} className="p-8">
                                                    <EmptyState label="No receipts recorded yet for this session." icon={ReceiptText} />
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </FramePanel>
                    </Frame>

                    {/* Accounting Snapshot & Fee Breakdown */}
                    <Frame variant="default">
                        <FramePanel className="overflow-hidden p-0">
                            <FrameHeader className="border-border/60 bg-card/60 flex items-start justify-between border-b p-5">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Landmark className="size-3.5" />
                                        </IconTile>
                                        <FrameTitle className="text-base font-semibold">Accounting Snapshot</FrameTitle>
                                    </div>
                                    <FrameDescription className="text-xs">Quick fiscal metrics, fee categories, and daily totals.</FrameDescription>
                                </div>
                            </FrameHeader>

                            <div className="space-y-4 p-5">
                                {/* 3 Mini Snapshot Cards */}
                                <div className="grid gap-2.5 sm:grid-cols-3">
                                    <Button
                                        variant="outline"
                                        className="border-border/60 hover:border-border hover:bg-card/90 group h-auto justify-start p-3 text-left transition-all duration-150"
                                        render={<Link href={route("administrators.finance.reports", { tab: "scholarship" })} />}
                                    >
                                        <div className="space-y-1">
                                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                                                <IconTile variant="soft" size="xs" className="text-primary">
                                                    <Percent className="size-3" />
                                                </IconTile>
                                                Discounts
                                            </span>
                                            <span className="text-foreground block text-base font-bold tabular-nums">
                                                {formatCurrency(stats.total_discounts)}
                                            </span>
                                            <span className="text-muted-foreground block text-[11px] font-normal tabular-nums">
                                                {formatNumber(stats.discounted_students)} students
                                            </span>
                                        </div>
                                    </Button>

                                    <Button
                                        variant="outline"
                                        className="border-border/60 hover:border-border hover:bg-card/90 group h-auto justify-start p-3 text-left transition-all duration-150"
                                        render={<Link href={route("administrators.finance.invoices", { query: { status: "unpaid" } })} />}
                                    >
                                        <div className="space-y-1">
                                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                                                <IconTile variant="soft" size="xs" className="text-amber-600 dark:text-amber-400">
                                                    <ClipboardList className="size-3" />
                                                </IconTile>
                                                Follow-up
                                            </span>
                                            <span className="text-foreground block text-base font-bold tabular-nums">
                                                {formatNumber(cashier_desk.ready_for_collection)}
                                            </span>
                                            <span className="text-muted-foreground block text-[11px] font-normal">queued balances</span>
                                        </div>
                                    </Button>

                                    <Button
                                        variant="outline"
                                        className="border-border/60 hover:border-border hover:bg-card/90 group h-auto justify-start p-3 text-left transition-all duration-150"
                                        render={<Link href={route("administrators.finance.reports", { tab: "fullypaid" })} />}
                                    >
                                        <div className="space-y-1">
                                            <span className="text-muted-foreground flex items-center gap-1.5 text-xs font-medium">
                                                <IconTile variant="soft" size="xs" className="text-emerald-600 dark:text-emerald-400">
                                                    <UsersRound className="size-3" />
                                                </IconTile>
                                                Cleared
                                            </span>
                                            <span className="text-foreground block text-base font-bold tabular-nums">
                                                {formatNumber(stats.fully_paid_count)}
                                            </span>
                                            <span className="text-muted-foreground block text-[11px] font-normal tabular-nums">
                                                {paidPercentage}% fully paid
                                            </span>
                                        </div>
                                    </Button>
                                </div>

                                {/* Fee Breakdown */}
                                <div className="border-border/60 bg-muted/20 rounded-xl border p-4">
                                    <div className="mb-3 flex items-center justify-between gap-3">
                                        <div>
                                            <p className="text-foreground text-sm font-semibold">Fee Breakdown</p>
                                            <p className="text-muted-foreground text-xs">Posted collections by fee item</p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="xs"
                                            render={<Link href={route("administrators.finance.reports", { tab: "revenue" })} />}
                                        >
                                            Report
                                            <ArrowRight className="ml-1 size-3" />
                                        </Button>
                                    </div>
                                    <div className="space-y-3">
                                        {topFeeBreakdown.map((fee) => {
                                            const feePercentage = Math.max(2, Math.round((fee.total / Math.max(totalFeeBreakdownAmount, 1)) * 100));

                                            return (
                                                <div key={fee.key} className="space-y-1.5">
                                                    <div className="flex items-center justify-between gap-4 text-xs">
                                                        <span className="text-muted-foreground font-medium">{fee.label}</span>
                                                        <span className="text-foreground font-bold tabular-nums">{formatCurrency(fee.total)}</span>
                                                    </div>
                                                    <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                                                        <div
                                                            className="bg-primary h-full rounded-full transition-all"
                                                            style={{ width: `${feePercentage}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                        {topFeeBreakdown.length === 0 && <EmptyState label="No fee breakdown available yet." />}
                                    </div>
                                </div>

                                {/* Last 7 Days Daily Cashier Totals */}
                                <div className="border-border/60 bg-muted/20 rounded-xl border p-4">
                                    <div className="mb-3 flex items-center justify-between gap-3">
                                        <div>
                                            <p className="text-foreground text-sm font-semibold">Last 7 Days</p>
                                            <p className="text-muted-foreground text-xs">Daily cashier totals</p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="xs"
                                            render={<Link href={route("administrators.finance.reports", { tab: "daily" })} />}
                                        >
                                            Daily
                                            <ArrowRight className="ml-1 size-3" />
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
                                        <EmptyState label="No daily collection data recorded yet." icon={BarChart3} />
                                    )}
                                </div>
                            </div>
                        </FramePanel>
                    </Frame>
                </section>

                {/* Top Students Ranking */}
                {top_students.length > 0 && (
                    <Frame variant="default" className="w-full">
                        <FramePanel className="overflow-hidden p-0">
                            <FrameHeader className="border-border/60 bg-card/60 flex items-center justify-between border-b p-5">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <IconTile variant="soft" size="xs" className="text-primary">
                                            <Sparkles className="size-3.5" />
                                        </IconTile>
                                        <FrameTitle className="text-base font-semibold">Top Student Contributors</FrameTitle>
                                    </div>
                                    <FrameDescription className="text-xs">
                                        Highest tuition and fees settled during the current academic period.
                                    </FrameDescription>
                                </div>
                            </FrameHeader>

                            <div className="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                                {top_students.map((student, index) => {
                                    const isFirst = index === 0;

                                    return (
                                        <div
                                            key={student.student_id}
                                            className={cn(
                                                "border-border/60 bg-card/60 hover:border-border hover:bg-card/90 flex flex-col justify-between gap-3.5 rounded-xl border p-4 transition-all duration-150",
                                                isFirst && "border-primary/40 bg-primary/5",
                                            )}
                                        >
                                            <div className="flex items-center gap-3">
                                                <IconTile
                                                    variant={isFirst ? "solid" : "soft"}
                                                    size="sm"
                                                    className={cn("font-bold tabular-nums", isFirst && "bg-primary text-primary-foreground")}
                                                >
                                                    {index + 1}
                                                </IconTile>
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-foreground truncate text-sm font-semibold">{student.student_name}</p>
                                                    <p className="text-muted-foreground flex items-center gap-1 font-mono text-xs tabular-nums">
                                                        <span>{student.student_id}</span>
                                                        <span>&middot;</span>
                                                        <span>{student.transaction_count} txns</span>
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="border-border/40 flex items-center justify-between border-t pt-2.5">
                                                <p className="text-foreground text-sm font-bold tabular-nums">{formatCurrency(student.total_paid)}</p>
                                                <Button
                                                    size="xs"
                                                    variant="ghost"
                                                    className="text-primary"
                                                    render={<Link href={route("administrators.finance.payments", { search: student.student_id })} />}
                                                >
                                                    Receipts
                                                    <ArrowRight className="ml-1 size-3" />
                                                </Button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </FramePanel>
                    </Frame>
                )}
            </div>
        </AdminLayout>
    );
}
