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

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 pb-16 md:gap-6 md:p-6">
                {/* Header */}
                <Card className={dashboardPanelClass}>
                    <CardContent className="flex flex-col justify-between gap-5 p-4 md:flex-row md:items-end md:p-5">
                        <motion.div initial={{ opacity: 0, x: -20 }} animate={{ opacity: 1, x: 0 }} className="space-y-2">
                            <div className="text-primary flex items-center gap-2 font-medium">
                                <Wallet className="h-4 w-4" />
                                <span className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">Student Finance</span>
                            </div>
                            <h1 className="text-foreground text-2xl font-semibold tracking-tight md:text-3xl">Tuition & Fees</h1>
                            <p className="text-muted-foreground max-w-xl text-sm">
                                Overview of your financial status, assessment breakdown, and payment history for the semester.
                            </p>
                        </motion.div>

                        <motion.div
                            initial={{ opacity: 0, scale: 0.95 }}
                            animate={{ opacity: 1, scale: 1 }}
                            className="flex w-full items-center gap-3 md:w-auto"
                        >
                            <div className="border-border/60 bg-background/65 flex items-center gap-2 rounded-lg border p-1 pl-3 shadow-sm">
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
                                    className="rounded-lg shadow-sm"
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
                                <div className="relative p-5 md:p-6">
                                    <div className="flex items-start gap-4">
                                        {/* Icon Container */}
                                        <div className="shrink-0 rounded-lg bg-amber-500/15 p-3 text-amber-500">
                                            <Wrench className="h-5 w-5" />
                                        </div>

                                        {/* Content */}
                                        <div className="min-w-0 flex-1">
                                            <div className="mb-2 flex items-center gap-2">
                                                <h3 className="text-base font-semibold text-amber-900 md:text-lg dark:text-amber-100">
                                                    System Under Maintenance
                                                </h3>
                                                <span className="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/15 px-2 py-0.5">
                                                    <span className="relative inline-flex h-2 w-2 rounded-full bg-amber-500" />
                                                    <span className="text-xs font-semibold text-amber-700 dark:text-amber-300">Active</span>
                                                </span>
                                            </div>

                                            <p className="mb-3 text-sm leading-relaxed text-amber-800/90 md:text-base dark:text-amber-200/90">
                                                The tuition and fees system is currently being maintained. The values displayed on this page
                                                <span className="font-semibold text-amber-900 dark:text-amber-100"> may not be accurate</span>.
                                            </p>

                                            <div className="flex flex-wrap items-center gap-3">
                                                <div className="bg-background/45 inline-flex items-center gap-2 rounded-lg border border-amber-300/50 px-4 py-2 shadow-sm dark:border-amber-600/30">
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
                                            className="shrink-0 rounded-lg p-2 text-amber-600/70 transition-colors hover:bg-amber-500/15 hover:text-amber-900 dark:text-amber-400/70 dark:hover:text-amber-200"
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
                                <Card className={`${dashboardCardClass} group relative overflow-hidden hover:-translate-y-0.5`}>
                                    {/* Background Patterns */}
                                    <Wallet className="text-primary pointer-events-none absolute top-5 right-6 h-20 w-20 opacity-15 transition-all duration-200 group-hover:scale-105 group-hover:opacity-25" />

                                    <CardContent className="relative z-10 p-5 pr-24 md:p-6 md:pr-28">
                                        <div className="flex flex-col gap-8">
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <p className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">
                                                        Total Assessment
                                                    </p>
                                                    <h2 className="text-foreground font-mono text-3xl font-semibold tracking-tight md:text-4xl">
                                                        {tuition.formatted_overall_tuition}
                                                    </h2>
                                                </div>
                                                <div className="border-border/60 bg-primary/10 text-primary rounded-lg border p-2">
                                                    <Sparkles className="h-5 w-5" />
                                                </div>
                                            </div>

                                            <div className="space-y-3">
                                                <div className="flex justify-between text-sm font-medium">
                                                    <span className="text-muted-foreground">Payment Status</span>
                                                    <span className="bg-primary/10 text-primary rounded px-2 py-0.5 text-xs font-semibold">
                                                        {tuition.payment_progress}% Paid
                                                    </span>
                                                </div>
                                                <div className="bg-muted h-3 w-full overflow-hidden rounded-full p-[1px]">
                                                    <motion.div
                                                        initial={{ width: 0 }}
                                                        animate={{ width: `${tuition.payment_progress}%` }}
                                                        transition={{ duration: 1, delay: 0.2, ease: "easeOut" as const }}
                                                        className="bg-primary h-full rounded-full"
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4 pt-2">
                                                <div className="border-border/60 bg-background/45 rounded-lg border p-4">
                                                    <div className="mb-1 flex items-center gap-2 opacity-80">
                                                        <CheckCircle2 className="h-3 w-3" />
                                                        <span className="text-xs font-bold uppercase">Paid Amount</span>
                                                    </div>
                                                    <p className="font-mono text-xl font-bold">{tuition.formatted_total_paid}</p>
                                                </div>
                                                <div className="border-border/60 bg-background/45 rounded-lg border p-4">
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
                            <CardContent className="relative flex min-h-[380px] flex-col items-center justify-center p-8 text-center">
                                <Wallet className="text-primary pointer-events-none absolute top-8 right-8 h-24 w-24 opacity-10" />
                                <div className="bg-primary/10 text-primary mb-5 rounded-lg p-4">
                                    <Wallet className="h-10 w-10" />
                                </div>
                                <h3 className="text-foreground mb-2 text-xl font-semibold">No Assessment Record Found</h3>
                                <p className="text-muted-foreground mx-auto mb-6 max-w-md text-sm">
                                    We couldn't find a tuition record for <span className="text-foreground font-semibold">{filters.school_year}</span>
                                    . Please ensure your enrollment is finalized.
                                </p>

                                {history.length > 0 && (
                                    <Button
                                        size="lg"
                                        onClick={() => handleFilterChange(`${history[0]?.school_year}|${history[0]?.semester}`)}
                                        className="rounded-lg"
                                    >
                                        View Latest Available Record
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    </motion.div>
                )}
            </div>
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
