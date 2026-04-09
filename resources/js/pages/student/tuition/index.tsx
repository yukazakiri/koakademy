import StudentLayout from "@/components/student/student-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Head, router, usePage } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import {
    ArrowRight,
    ArrowUpRight,
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
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

            <div className="bg-background/50 min-h-screen pb-20">
                <div className="mx-auto w-full max-w-7xl space-y-8 p-4 md:p-8">
                    {/* Header */}
                    <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
                        <motion.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }} className="space-y-1">
                            <div className="text-primary flex items-center gap-2 font-medium">
                                <div className="bg-primary/10 rounded-md p-1.5">
                                    <Wallet className="h-4 w-4" />
                                </div>
                                <span className="text-xs font-bold tracking-wider uppercase">Student Finance</span>
                            </div>
                            <h1 className="text-foreground text-3xl font-bold tracking-tight md:text-4xl">Tuition & Fees</h1>
                            <p className="text-muted-foreground max-w-xl text-sm md:text-base">
                                Overview of your financial status, assessment breakdown, and payment history for the semester.
                            </p>
                        </motion.div>

                        <motion.div
                            initial={{ opacity: 0, scale: 0.95 }}
                            animate={{ opacity: 1, scale: 1 }}
                            className="flex w-full items-center gap-3 md:w-auto"
                        >
                            <div className="bg-card ring-border/50 flex items-center gap-2 rounded-full border p-1 pl-3 shadow-sm ring-1">
                                <Calendar className="text-muted-foreground h-4 w-4 shrink-0" />
                                <Select
                                    value={`${filters.school_year}|${filters.semester}`}
                                    onValueChange={handleFilterChange}
                                    disabled={isFilterLoading}
                                >
                                    <SelectTrigger className="text-foreground h-9 w-full border-0 bg-transparent px-2 text-sm font-medium shadow-none focus:ring-0 md:w-[220px]">
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
                                    className="rounded-full shadow-sm"
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
                        </motion.div>
                    </div>

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
                                <div className="relative rounded-2xl border-2 border-amber-400/50 bg-gradient-to-r from-amber-50 via-orange-50 to-amber-50 shadow-lg shadow-amber-500/10 dark:from-amber-950/40 dark:via-orange-950/30 dark:to-amber-950/40">
                                    {/* Animated background pattern */}
                                    <div className="pointer-events-none absolute inset-0 overflow-hidden">
                                        <div className="absolute -top-4 -right-4 h-32 w-32 animate-pulse rounded-full bg-amber-400/20 blur-2xl" />
                                        <div className="absolute -bottom-4 -left-4 h-24 w-24 animate-pulse rounded-full bg-orange-400/20 blur-2xl delay-700" />
                                        <div className="animate-shimmer absolute top-1/2 left-1/2 h-full w-full -translate-x-1/2 -translate-y-1/2 bg-gradient-to-r from-transparent via-white/5 to-transparent" />
                                    </div>

                                    <div className="relative p-5 md:p-6">
                                        <div className="flex items-start gap-4">
                                            {/* Icon Container */}
                                            <motion.div
                                                initial={{ rotate: 0 }}
                                                animate={{ rotate: [0, -10, 10, -10, 0] }}
                                                transition={{ duration: 0.5, delay: 0.5, repeat: Infinity, repeatDelay: 5 }}
                                                className="shrink-0"
                                            >
                                                <div className="relative">
                                                    <div className="absolute inset-0 animate-pulse rounded-xl bg-amber-500 opacity-40 blur-md" />
                                                    <div className="relative rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 p-3 shadow-lg">
                                                        <Wrench className="h-6 w-6 text-white" />
                                                    </div>
                                                </div>
                                            </motion.div>

                                            {/* Content */}
                                            <div className="min-w-0 flex-1">
                                                <div className="mb-2 flex items-center gap-2">
                                                    <h3 className="text-base font-bold text-amber-900 md:text-lg dark:text-amber-200">
                                                        System Under Maintenance
                                                    </h3>
                                                    <span className="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/20 px-2 py-0.5">
                                                        <span className="relative flex h-2 w-2">
                                                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-500 opacity-75" />
                                                            <span className="relative inline-flex h-2 w-2 rounded-full bg-amber-500" />
                                                        </span>
                                                        <span className="text-xs font-semibold text-amber-700 dark:text-amber-300">Active</span>
                                                    </span>
                                                </div>

                                                <p className="mb-3 text-sm leading-relaxed text-amber-800/90 md:text-base dark:text-amber-200/90">
                                                    The tuition and fees system is currently being maintained. The values displayed on this page
                                                    <span className="font-semibold text-amber-900 dark:text-amber-100"> may not be accurate</span>.
                                                </p>

                                                <div className="flex flex-wrap items-center gap-3">
                                                    <div className="inline-flex items-center gap-2 rounded-xl border border-amber-300/50 bg-white/60 px-4 py-2 shadow-sm dark:border-amber-600/30 dark:bg-white/10">
                                                        <ShieldAlert className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                                        <span className="text-sm font-medium text-amber-900 dark:text-amber-100">
                                                            Contact the <span className="font-bold">MIS Administrator</span> for any discrepancies
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Dismiss Button */}
                                            <button
                                                onClick={() => setIsNoticeDismissed(true)}
                                                className="shrink-0 rounded-lg p-2 text-amber-600/70 transition-colors hover:bg-amber-200/50 hover:text-amber-900 dark:text-amber-400/70 dark:hover:bg-amber-800/30 dark:hover:text-amber-200"
                                                aria-label="Dismiss notice"
                                            >
                                                <X className="h-5 w-5" />
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
                                    <Card className="from-primary via-primary/90 to-primary/80 text-primary-foreground relative overflow-hidden border-none bg-gradient-to-br shadow-xl">
                                        {/* Background Patterns */}
                                        <div className="pointer-events-none absolute top-0 right-0 -mt-16 -mr-16 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                                        <div className="pointer-events-none absolute bottom-0 left-0 -mb-10 -ml-10 h-48 w-48 rounded-full bg-black/10 blur-2xl" />

                                        <CardContent className="relative z-10 p-6 md:p-8">
                                            <div className="flex flex-col gap-8">
                                                <div className="flex items-start justify-between">
                                                    <div>
                                                        <p className="text-primary-foreground/80 mb-1 text-xs font-bold tracking-wider uppercase">
                                                            Total Assessment
                                                        </p>
                                                        <h2 className="font-mono text-4xl font-bold tracking-tighter md:text-5xl">
                                                            {tuition.formatted_overall_tuition}
                                                        </h2>
                                                    </div>
                                                    <div className="rounded-xl border border-white/10 bg-white/20 p-2 backdrop-blur-md">
                                                        <Sparkles className="h-6 w-6 text-white" />
                                                    </div>
                                                </div>

                                                <div className="space-y-3">
                                                    <div className="flex justify-between text-sm font-medium">
                                                        <span className="text-primary-foreground/90">Payment Status</span>
                                                        <span className="rounded bg-white/20 px-2 py-0.5 text-xs font-bold text-white backdrop-blur-sm">
                                                            {tuition.payment_progress}% Paid
                                                        </span>
                                                    </div>
                                                    <div className="h-3 w-full overflow-hidden rounded-full bg-black/20 p-[1px]">
                                                        <motion.div
                                                            initial={{ width: 0 }}
                                                            animate={{ width: `${tuition.payment_progress}%` }}
                                                            transition={{ duration: 1, delay: 0.2, ease: "easeOut" as const }}
                                                            className="h-full rounded-full bg-white shadow-[0_0_15px_rgba(255,255,255,0.6)]"
                                                        />
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-2 gap-4 pt-2">
                                                    <div className="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                                                        <div className="mb-1 flex items-center gap-2 opacity-80">
                                                            <CheckCircle2 className="h-3 w-3" />
                                                            <span className="text-xs font-bold uppercase">Paid Amount</span>
                                                        </div>
                                                        <p className="font-mono text-xl font-bold">{tuition.formatted_total_paid}</p>
                                                    </div>
                                                    <div className="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                                                        <div className="mb-1 flex items-center gap-2 opacity-80">
                                                            <Clock className="h-3 w-3" />
                                                            <span className="text-xs font-bold uppercase">Balance Due</span>
                                                        </div>
                                                        <p className="font-mono text-xl font-bold">{tuition.formatted_total_balance}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </motion.div>

                                {/* FEE BREAKDOWN */}
                                <motion.div variants={itemVariants}>
                                    <Card className="border shadow-sm">
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
                                        <Card className="bg-card hover:border-primary/50 group cursor-pointer border shadow-sm transition-colors">
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
                                                <div className="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-full transition-transform group-hover:scale-110">
                                                    <History className="h-5 w-5" />
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </motion.div>

                                    <motion.div variants={itemVariants}>
                                        <Card className="bg-card border shadow-sm">
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
                                    <Card className="flex flex-col border shadow-sm">
                                        <CardHeader className="bg-muted/20 border-b pb-3">
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
                        <motion.div
                            initial={{ opacity: 0, scale: 0.95 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ duration: 0.5 }}
                            className="flex flex-col items-center justify-center py-20 text-center"
                        >
                            <div className="relative mb-6">
                                <div className="bg-primary/20 absolute inset-0 rounded-full blur-3xl" />
                                <div className="bg-card ring-border relative rounded-full p-8 shadow-2xl ring-1">
                                    <Wallet className="text-primary h-16 w-16" />
                                </div>
                            </div>
                            <h3 className="text-foreground mb-2 text-2xl font-bold">No Assessment Record Found</h3>
                            <p className="text-muted-foreground mx-auto mb-8 max-w-md">
                                We couldn't find a tuition record for <span className="text-foreground font-semibold">{filters.school_year}</span>.
                                <br />
                                Please ensure your enrollment is finalized.
                            </p>

                            {history.length > 0 && (
                                <Button
                                    size="lg"
                                    onClick={() => handleFilterChange(`${history[0]?.school_year}|${history[0]?.semester}`)}
                                    className="hover:shadow-primary/25 rounded-full shadow-lg"
                                >
                                    View Latest Available Record
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Button>
                            )}
                        </motion.div>
                    )}
                </div>
            </div>
        </StudentLayout>
    );
}

function FeeItem({ label, value, icon, color }: { label: string; value: string; icon: any; color: string }) {
    return (
        <div className="hover:bg-muted/50 group flex items-center justify-between rounded-lg p-3 transition-colors">
            <div className="flex items-center gap-3">
                <div className={`rounded-lg p-2 ${color} bg-opacity-10`}>{icon}</div>
                <span className="text-foreground/80 text-sm font-medium">{label}</span>
            </div>
            <span className="text-foreground font-mono font-medium">{value}</span>
        </div>
    );
}
