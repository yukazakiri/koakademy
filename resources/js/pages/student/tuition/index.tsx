import { index as tuitionUpdateRequestsIndex } from "@/actions/App/Http/Controllers/StudentTuitionUpdateRequestController";
import StudentLayout from "@/components/student/student-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import {
    ArrowRight,
    ArrowUpRight,
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    FileQuestion,
    FileText,
    GraduationCap,
    History,
    LayoutDashboard,
    Loader2,
    Printer,
    Receipt,
    ShieldAlert,
    Sparkles,
    Wallet,
    Wrench,
    X,
} from "lucide-react";
import { useState } from "react";

// Declare Ziggy route function
declare const route: (name: string, params?: any, absolute?: boolean) => string;

interface Branding {
    currency: string;
}

interface Tuition {
    total_tuition: number;
    total_balance: number;
    total_lectures: number;
    total_laboratory: number;
    total_miscelaneous_fees: number;
    downpayment: number;
    discount: number;
    overall_tuition: number;
    paid: number;
    status: string;
    formatted_total_balance: string;
    formatted_overall_tuition: string;
    formatted_total_tuition: string;
    formatted_semester: string;
    payment_progress: number;
    status_class: string;
    payment_status: string;
    formatted_total_lectures: string;
    formatted_total_laboratory: string;
    formatted_total_miscelaneous_fees: string;
    formatted_downpayment: string;
    formatted_discount: string;
    formatted_total_paid: string;
}

interface Transaction {
    id: number;
    date: string;
    description: string;
    amount: string; // formatted
    status: string;
    invoice: string;
    method: string;
}

interface HistoryItem {
    school_year: string;
    semester: number;
    label: string;
}

interface Props {
    auth: any;
    tuition: Tuition | null;
    transactions: Transaction[];
    filters: {
        semester: number;
        school_year: string;
    };
    history: HistoryItem[];
}

const dashboardCardClass =
    "border-border/60 bg-card/75 rounded-lg shadow-sm transition-all duration-200 hover:border-primary/30 hover:bg-card hover:shadow-md";
const dashboardPanelClass = "border-border/60 bg-card/75 rounded-lg shadow-sm";

export default function TuitionIndex({ auth, tuition, transactions, filters, history }: Props) {
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";
    const currencySymbol = currency === "USD" ? "$" : "₱";

    const [isFilterLoading, setIsFilterLoading] = useState(false);
    const [isNoticeDismissed, setIsNoticeDismissed] = useState(false);

    const handleFilterChange = (value: string) => {
        setIsFilterLoading(true);
        const [sy, sem] = value.split("|");
        router.get(
            route("student.tuition.index"),
            {
                school_year: sy,
                semester: sem,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setIsFilterLoading(false),
            },
        );
    };

    const containerVariants = {
        hidden: { opacity: 0 },
        show: {
            opacity: 1,
            transition: {
                staggerChildren: 0.05,
            },
        },
    };

    const itemVariants = {
        hidden: { opacity: 0, y: 20 },
        show: { opacity: 1, y: 0, transition: { duration: 0.4, ease: "easeOut" as const } },
    };

    return (
        <StudentLayout
            user={{
                name: auth.user.name,
                email: auth.user.email,
                avatar: auth.user.avatar,
                role: auth.user.role,
            }}
        >
            <Head title="Tuition & Fees" />

            {/* Mobile Header Background */}
            <div className="bg-primary/10 relative h-[110px] w-full overflow-hidden px-4 pt-5 md:hidden">
                <div className="bg-primary/20 absolute -top-24 -right-24 h-64 w-64 rounded-full blur-3xl" />
                <div className="bg-primary/10 absolute -bottom-12 -left-12 h-40 w-40 rounded-full blur-2xl" />
                <div className="relative">
                    <p className="text-foreground/60 text-[9px] font-bold tracking-wider uppercase">Student Finance</p>
                    <h1 className="text-foreground mt-0.5 text-xl font-bold tracking-tight">
                        Tuition & <span className="from-primary to-primary/60 bg-gradient-to-r bg-clip-text text-transparent">Fees</span>
                    </h1>
                </div>
            </div>

            <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: "easeOut" }}
                className={cn("mx-auto flex w-full max-w-7xl flex-col gap-2.5 p-3.5 pb-20 md:gap-6 md:p-6", "relative z-20 -mt-10 md:mt-0")}
            >
                {/* Header */}
                <Card className={cn(dashboardPanelClass, "relative overflow-hidden")}>
                    {/* Decorative Glass Elements */}
                    <div className="bg-primary/5 absolute -top-24 -right-24 h-64 w-64 rounded-full blur-3xl" />
                    <div className="bg-primary/10 absolute -bottom-12 -left-12 h-48 w-48 rounded-full blur-2xl" />

                    <CardContent className="relative z-10 flex flex-col justify-between gap-3 p-3 md:flex-row md:items-end md:gap-5 md:p-5">
                        <div className="space-y-1.5 md:space-y-2">
                            <div className="text-primary flex hidden items-center gap-2 font-medium md:flex">
                                <Wallet className="h-3.5 w-3.5 md:h-4 md:w-4" />
                                <span className="text-muted-foreground text-[9px] font-semibold tracking-wide uppercase md:text-[10px]">
                                    Student Finance
                                </span>
                            </div>
                            <h1 className="text-foreground hidden text-xl leading-tight font-bold tracking-tight sm:text-3xl md:block md:text-4xl">
                                Tuition & <span className="from-primary to-primary/60 bg-gradient-to-r bg-clip-text text-transparent">Fees</span>
                            </h1>
                            <p className="text-muted-foreground hidden max-w-xl text-sm sm:block sm:text-base">
                                Overview of your financial status, assessment breakdown, and payment history for the semester.
                            </p>

                            <div className="mb-1 block w-full md:hidden">
                                <p className="text-foreground/50 text-[10px] font-bold tracking-wider uppercase">Active Academic Period</p>
                            </div>
                        </div>

                        <div className="flex w-full items-center gap-2 md:w-auto md:gap-3">
                            <div className="border-border/60 bg-background/65 flex min-w-0 flex-1 items-center gap-1.5 rounded-lg border p-1 pl-2 shadow-sm md:gap-2 md:pl-3">
                                <Calendar className="text-muted-foreground h-3.5 w-3.5 shrink-0 md:h-4 md:w-4" />
                                <Select
                                    value={`${filters.school_year}|${filters.semester}`}
                                    onValueChange={handleFilterChange}
                                    disabled={isFilterLoading}
                                >
                                    <SelectTrigger className="text-foreground h-8 w-full border-0 bg-transparent px-1.5 text-xs font-medium shadow-none focus:ring-0 md:h-9 md:w-[220px] md:px-2 md:text-sm">
                                        <SelectValue placeholder="Select Term" />
                                    </SelectTrigger>
                                    <SelectContent align="end">
                                        {history.length > 0 ? (
                                            history.map((h) => (
                                                <SelectItem key={`${h.school_year}-${h.semester}`} value={`${h.school_year}|${h.semester}`}>
                                                    {h.label}
                                                </SelectItem>
                                            ))
                                        ) : (
                                            <SelectItem value={`${filters.school_year}|${filters.semester}`}>Current Term</SelectItem>
                                        )}
                                    </SelectContent>
                                </Select>
                                {isFilterLoading && (
                                    <div className="pr-3">
                                        <Loader2 className="text-primary h-3 w-3 animate-spin" />
                                    </div>
                                )}
                            </div>

                            {tuition && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="hidden rounded-lg shadow-sm sm:inline-flex"
                                    onClick={() => {
                                        window.open(
                                            route("student.tuition.soa", {
                                                school_year: filters.school_year,
                                                semester: filters.semester,
                                            }),
                                            "_blank",
                                        );
                                    }}
                                >
                                    <Printer className="mr-2 h-4 w-4" />
                                    Print SOA
                                </Button>
                            )}
                            <Button variant="outline" size="sm" className="rounded-lg shadow-sm" asChild>
                                <Link href={tuitionUpdateRequestsIndex.url({ query: filters })}>
                                    <FileQuestion className="mr-2 h-4 w-4" />
                                    <span className="hidden sm:inline">Report an issue</span>
                                    <span className="sm:hidden">Report</span>
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* System Maintenance Notice */}
                <AnimatePresence>
                    {!isNoticeDismissed && (
                        <motion.div
                            initial={{ opacity: 0, y: -20, scale: 0.95 }}
                            animate={{ opacity: 1, y: 0, scale: 1 }}
                            exit={{ opacity: 0, y: -10, scale: 0.95 }}
                            transition={{ duration: 0.3, ease: "easeOut" }}
                            className="relative overflow-hidden"
                        >
                            <div className="relative rounded-lg border border-amber-500/35 bg-amber-500/10 shadow-sm">
                                <div className="relative p-3 md:p-6">
                                    <div className="flex items-start gap-2.5 md:gap-4">
                                        {/* Icon Container */}
                                        <div className="shrink-0 rounded-lg bg-amber-500/15 p-2 text-amber-500 md:p-3">
                                            <Wrench className="h-4 w-4 md:h-5 md:w-5" />
                                        </div>

                                        {/* Content */}
                                        <div className="min-w-0 flex-1">
                                            <div className="mb-1.5 flex flex-wrap items-center gap-2">
                                                <h3 className="text-sm font-semibold text-amber-900 md:text-lg dark:text-amber-100">
                                                    System Under Maintenance
                                                </h3>
                                                <span className="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/15 px-1.5 py-0.5 md:px-2">
                                                    <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-amber-500 md:h-2 md:w-2" />
                                                    <span className="text-[10px] font-semibold text-amber-700 md:text-xs dark:text-amber-300">
                                                        Active
                                                    </span>
                                                </span>
                                            </div>

                                            <p className="mb-2 line-clamp-2 text-xs leading-relaxed text-amber-800/90 md:mb-3 md:line-clamp-none md:text-base dark:text-amber-200/90">
                                                The tuition and fees system is currently being maintained. The values displayed on this page
                                                <span className="font-semibold text-amber-900 dark:text-amber-100"> may not be accurate</span>.
                                            </p>

                                            <div className="hidden md:flex">
                                                <Link
                                                    href={tuitionUpdateRequestsIndex.url({ query: filters })}
                                                    prefetch
                                                    className="bg-background/45 inline-flex items-center gap-2 rounded-lg border border-amber-300/50 px-3 py-1.5 shadow-sm transition-colors hover:bg-amber-500/15 focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 focus-visible:outline-none md:px-4 md:py-2 dark:border-amber-600/30"
                                                >
                                                    <ShieldAlert className="h-3.5 w-3.5 text-amber-600 md:h-4 md:w-4 dark:text-amber-400" />
                                                    <span className="text-[11px] font-medium text-amber-900 md:text-sm dark:text-amber-100">
                                                        Contact the <span className="font-bold">MIS Administrator</span>
                                                    </span>
                                                </Link>
                                            </div>
                                        </div>

                                        {/* Dismiss Button */}
                                        <button
                                            onClick={() => setIsNoticeDismissed(true)}
                                            className="shrink-0 rounded-lg p-1 text-amber-600/70 transition-colors hover:bg-amber-500/15 hover:text-amber-900 md:p-2 dark:text-amber-400/70 dark:hover:text-amber-200"
                                            aria-label="Dismiss notice"
                                        >
                                            <X className="h-4 w-4 md:h-5 md:w-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>

                {tuition ? (
                    <motion.div variants={containerVariants} initial="hidden" animate="show" className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        {/* LEFT COLUMN (2/3 width on large screens) */}
                        <div className="space-y-6 lg:col-span-2">
                            {/* MAIN STATUS CARD */}
                            <motion.div variants={itemVariants}>
                                <Card className="border-border/40 bg-card relative overflow-hidden rounded-xl shadow-lg shadow-black/5">
                                    <CardContent className="p-4 md:p-6">
                                        <div className="flex flex-col gap-5 md:gap-8">
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <p className="text-foreground/50 text-[9px] font-bold tracking-wider uppercase sm:text-xs">
                                                        Total Assessment
                                                    </p>
                                                    <h2 className="text-foreground mt-0.5 font-mono text-xl font-bold tracking-tight sm:text-3xl md:text-4xl">
                                                        {tuition.formatted_overall_tuition}
                                                    </h2>
                                                </div>
                                                <div className="bg-primary/10 text-primary flex h-8 w-8 items-center justify-center rounded-lg">
                                                    <Wallet className="h-4 w-4" />
                                                </div>
                                            </div>

                                            <div className="space-y-2 sm:space-y-3">
                                                <div className="flex justify-between text-[10px] font-bold sm:text-sm">
                                                    <span className="text-foreground/50 tracking-wider uppercase">Payment Status</span>
                                                    <span className="bg-primary/10 text-primary rounded px-1.5 py-0.5 text-[9px] font-bold sm:px-2 sm:text-xs">
                                                        {tuition.payment_progress}% Paid
                                                    </span>
                                                </div>
                                                <div className="bg-muted h-2.5 w-full overflow-hidden rounded-full p-[1px]">
                                                    <motion.div
                                                        initial={{ width: 0 }}
                                                        animate={{ width: `${tuition.payment_progress}%` }}
                                                        transition={{ duration: 1, delay: 0.2, ease: "easeOut" as const }}
                                                        className="bg-primary h-full rounded-full shadow-[0_0_10px_rgba(var(--primary),0.3)]"
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-2.5 pt-0.5 md:gap-4">
                                                <div className="border-border/40 bg-background/45 relative overflow-hidden rounded-lg border p-2.5 md:p-4">
                                                    <div className="flex items-center gap-1.5 opacity-60 md:gap-2">
                                                        <CheckCircle2 className="h-2.5 w-2.5 text-emerald-500" />
                                                        <span className="text-[8px] font-bold uppercase sm:text-[10px]">Paid</span>
                                                    </div>
                                                    <p className="text-foreground mt-0.5 font-mono text-sm font-bold sm:text-lg md:text-xl">
                                                        {tuition.formatted_total_paid}
                                                    </p>
                                                </div>
                                                <div className="border-border/40 bg-background/45 relative overflow-hidden rounded-lg border p-2.5 md:p-4">
                                                    <div className="flex items-center gap-1.5 opacity-60 md:gap-2">
                                                        <Clock className="h-2.5 w-2.5 text-amber-500" />
                                                        <span className="text-[8px] font-bold uppercase sm:text-[10px]">Balance</span>
                                                    </div>
                                                    <p className="text-foreground mt-0.5 font-mono text-sm font-bold sm:text-lg md:text-xl">
                                                        {tuition.formatted_total_balance}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </motion.div>

                            {/* FEE BREAKDOWN */}
                            <motion.div variants={itemVariants}>
                                <Card className={dashboardPanelClass}>
                                    <CardHeader className="pb-4">
                                        <div className="flex items-center justify-between">
                                            <div className="space-y-1">
                                                <CardTitle className="flex items-center gap-2 text-lg">
                                                    <LayoutDashboard className="text-primary h-5 w-5" />
                                                    Assessment Details
                                                </CardTitle>
                                                <CardDescription>Breakdown of fees for {tuition.formatted_semester}</CardDescription>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="grid gap-6">
                                        <div className="grid gap-4">
                                            <div className="grid gap-3">
                                                <FeeItem
                                                    label="Tuition Fees"
                                                    value={tuition.formatted_total_tuition}
                                                    icon={<GraduationCap className="h-4 w-4 text-blue-500" />}
                                                    color="bg-blue-500/10 text-blue-500"
                                                />
                                                <FeeItem
                                                    label="Laboratory Fees"
                                                    value={tuition.formatted_total_laboratory}
                                                    icon={<FileText className="h-4 w-4 text-purple-500" />}
                                                    color="bg-purple-500/10 text-purple-500"
                                                />
                                                <FeeItem
                                                    label="Miscellaneous Fees"
                                                    value={tuition.formatted_total_miscelaneous_fees}
                                                    icon={<LayoutDashboard className="h-4 w-4 text-amber-500" />}
                                                    color="bg-amber-500/10 text-amber-500"
                                                />
                                                <FeeItem
                                                    label="Lecture Fees"
                                                    value={tuition.formatted_total_lectures}
                                                    icon={<ArrowUpRight className="h-4 w-4 text-emerald-500" />}
                                                    color="bg-emerald-500/10 text-emerald-500"
                                                />
                                            </div>

                                            {tuition.discount > 0 && (
                                                <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/30">
                                                    <div className="flex items-center justify-between">
                                                        <div className="flex items-center gap-3">
                                                            <div className="rounded-full bg-emerald-100 p-2 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400">
                                                                <Sparkles className="h-4 w-4" />
                                                            </div>
                                                            <div className="space-y-0.5">
                                                                <p className="text-sm font-medium text-emerald-900 dark:text-emerald-200">
                                                                    Scholarship Discount
                                                                </p>
                                                                <p className="text-xs text-emerald-700 dark:text-emerald-400">
                                                                    {tuition.formatted_discount} Applied
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div className="text-right font-mono font-bold text-emerald-700 dark:text-emerald-400">
                                                            -{(tuition.overall_tuition / (1 - tuition.discount / 100)) * (tuition.discount / 100)}
                                                        </div>
                                                    </div>
                                                </div>
                                            )}

                                            <Separator />

                                            <div className="flex items-center justify-between pt-2">
                                                <span className="text-muted-foreground font-semibold">Total Assessment</span>
                                                <span className="text-foreground font-mono text-xl font-bold">
                                                    {tuition.formatted_overall_tuition}
                                                </span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        </div>

                        {/* RIGHT COLUMN (Sidebar) */}
                        <div className="space-y-6">
                            {/* SUMMARY BOXES */}
                            <div className="grid grid-cols-1 gap-4">
                                <motion.div variants={itemVariants}>
                                    <Card className={`${dashboardCardClass} group cursor-pointer hover:-translate-y-0.5`}>
                                        <CardContent className="flex items-center justify-between p-5">
                                            <div className="space-y-1">
                                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Last Payment</p>
                                                {transactions.length > 0 ? (
                                                    <div>
                                                        <p className="text-primary font-mono text-xl font-bold">
                                                            {currencySymbol}
                                                            {transactions[0].amount}
                                                        </p>
                                                        <p className="text-muted-foreground mt-1 text-xs">{transactions[0].date}</p>
                                                    </div>
                                                ) : (
                                                    <p className="text-muted-foreground text-sm font-medium">No records</p>
                                                )}
                                            </div>
                                            <div className="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-lg transition-transform group-hover:scale-105">
                                                <History className="h-5 w-5" />
                                            </div>
                                        </CardContent>
                                    </Card>
                                </motion.div>

                                <motion.div variants={itemVariants}>
                                    <Card className={dashboardPanelClass}>
                                        <CardContent className="p-5">
                                            <div className="mb-4 flex items-center justify-between">
                                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Discount</p>
                                                <Badge variant="outline" className="font-mono">
                                                    {tuition.formatted_discount}
                                                </Badge>
                                            </div>
                                            <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                                                <div className="h-full bg-indigo-500" style={{ width: `${Math.min(tuition.discount, 100)}%` }} />
                                            </div>
                                            <p className="text-muted-foreground mt-2 text-xs">
                                                You are enjoying a {tuition.discount}% discount on tuition fees.
                                            </p>
                                        </CardContent>
                                    </Card>
                                </motion.div>
                            </div>

                            {/* TRANSACTION HISTORY */}
                            <motion.div variants={itemVariants}>
                                <Card className={`${dashboardPanelClass} flex flex-col`}>
                                    <CardHeader className="bg-muted/25 border-b pb-3">
                                        <div className="flex items-center justify-between">
                                            <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                                <Receipt className="text-muted-foreground h-4 w-4" />
                                                Recent Payments
                                            </CardTitle>
                                            <Badge variant="secondary" className="text-[10px]">
                                                {transactions.length}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex-1 p-0">
                                        <ScrollArea className="h-[400px]">
                                            {transactions.length > 0 ? (
                                                <div className="divide-border divide-y">
                                                    {transactions.map((tx) => (
                                                        <div key={tx.id} className="hover:bg-muted/50 group p-4 transition-colors">
                                                            <div className="mb-1 flex items-start justify-between">
                                                                <p className="text-foreground group-hover:text-primary text-sm font-semibold transition-colors">
                                                                    {tx.description || "Tuition Payment"}
                                                                </p>
                                                                <span className="text-foreground font-mono text-sm font-bold">
                                                                    {currencySymbol}
                                                                    {tx.amount}
                                                                </span>
                                                            </div>
                                                            <div className="flex items-end justify-between">
                                                                <div className="space-y-1">
                                                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                                        <Calendar className="h-3 w-3" />
                                                                        <span>{tx.date}</span>
                                                                    </div>
                                                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                                        <CreditCard className="h-3 w-3" />
                                                                        <span>{tx.method}</span>
                                                                        {tx.invoice && (
                                                                            <span className="bg-muted rounded px-1 font-mono text-[10px]">
                                                                                #{tx.invoice}
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                                <Badge
                                                                    variant={tx.status === "verified" ? "default" : "outline"}
                                                                    className={`h-5 text-[10px] ${tx.status === "verified" ? "bg-emerald-500 hover:bg-emerald-600" : ""}`}
                                                                >
                                                                    {tx.status}
                                                                </Badge>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <div className="flex h-48 flex-col items-center justify-center space-y-3 p-6 text-center opacity-60">
                                                    <Receipt className="text-muted-foreground h-10 w-10" />
                                                    <p className="text-muted-foreground text-sm">No payment records found for this term.</p>
                                                </div>
                                            )}
                                        </ScrollArea>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        </div>
                    </motion.div>
                ) : (
                    <motion.div initial={{ opacity: 0, scale: 0.98 }} animate={{ opacity: 1, scale: 1 }} transition={{ duration: 0.35 }}>
                        <Card className={`${dashboardPanelClass} overflow-hidden`}>
                            <CardContent className="relative flex min-h-[340px] flex-col items-center justify-center p-6 text-center sm:min-h-[380px] sm:p-8">
                                <Wallet className="text-primary pointer-events-none absolute top-4 right-4 h-16 w-16 opacity-5 sm:top-8 sm:right-8 sm:h-24 sm:w-24 sm:opacity-10" />
                                <div className="bg-primary/10 text-primary mb-4 rounded-xl p-3.5 sm:mb-5 sm:p-4">
                                    <Wallet className="h-8 w-8 sm:h-10 sm:w-10" />
                                </div>
                                <h3 className="text-foreground mb-2 text-lg font-semibold sm:text-xl">No Assessment Record Found</h3>
                                <p className="text-muted-foreground mx-auto mb-6 max-w-xs text-xs sm:max-w-md sm:text-sm">
                                    We couldn't find a tuition record for <span className="text-foreground font-semibold">{filters.school_year}</span>
                                    . Please ensure your enrollment is finalized.
                                </p>

                                {history.length > 0 && (
                                    <Button
                                        size="default"
                                        onClick={() => handleFilterChange(`${history[0]?.school_year}|${history[0]?.semester}`)}
                                        className="h-10 rounded-lg px-6 sm:h-11 sm:px-8"
                                    >
                                        View Latest Available Record
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    </motion.div>
                )}
            </motion.div>
        </StudentLayout>
    );
}

function FeeItem({ label, value, icon, color }: { label: string; value: string; icon: any; color: string }) {
    return (
        <div className="hover:bg-muted/35 group flex items-center justify-between rounded-lg p-3 transition-colors">
            <div className="flex items-center gap-3">
                <div className={`rounded-lg p-2 ${color}`}>{icon}</div>
                <span className="text-foreground/80 text-sm font-medium">{label}</span>
            </div>
            <span className="text-foreground font-mono font-medium">{value}</span>
        </div>
    );
}
