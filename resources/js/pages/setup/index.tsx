import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Head, useForm, usePage } from "@inertiajs/react";
import { AnimatePresence, motion } from "framer-motion";
import {
    AlertCircle,
    BookOpen,
    Building2,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Eye,
    EyeOff,
    GraduationCap,
    Loader2,
    ShieldCheck,
    Sparkles,
    UserCircle,
} from "lucide-react";
import React, { useState } from "react";
import { toast } from "sonner";

const STEPS = [
    { label: "Administrator", description: "Super Admin Account", icon: UserCircle },
    { label: "Institution", description: "Primary School Record", icon: Building2 },
    { label: "Academic Period", description: "School Year & Semester", icon: GraduationCap },
];

const slideVariants = {
    hidden: (dir: number) => ({ x: dir > 0 ? 32 : -32, opacity: 0 }),
    visible: { x: 0, opacity: 1, transition: { type: "spring" as const, stiffness: 320, damping: 32 } },
    exit: (dir: number) => ({ x: dir < 0 ? 32 : -32, opacity: 0, transition: { ease: "easeInOut" as const, duration: 0.18 } }),
};

/* ── Stable field components defined OUTSIDE the page component ── */

interface FieldProps {
    id: string;
    label: string;
    type?: string;
    placeholder?: string;
    description?: string;
    uppercase?: boolean;
    value: string;
    onChange: (val: string) => void;
    error?: string;
}

function TextField({ id, label, type = "text", placeholder = "", description = "", uppercase = false, value, onChange, error }: FieldProps) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id} className="text-foreground text-sm font-medium">
                {label}
            </Label>
            <Input
                id={id}
                name={id}
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                autoComplete={type === "password" ? "new-password" : "off"}
                className={[
                    "border-border bg-background text-foreground placeholder:text-muted-foreground/60 h-11",
                    "focus-visible:ring-primary transition-colors duration-150 focus-visible:border-transparent focus-visible:ring-2",
                    uppercase ? "uppercase placeholder:normal-case" : "",
                    error ? "border-destructive focus-visible:ring-destructive" : "hover:border-primary/60",
                ]
                    .filter(Boolean)
                    .join(" ")}
            />
            {description && !error && <p className="text-muted-foreground text-xs">{description}</p>}
            {error && (
                <motion.p initial={{ opacity: 0, y: -4 }} animate={{ opacity: 1, y: 0 }} className="text-destructive text-xs">
                    {error}
                </motion.p>
            )}
        </div>
    );
}

interface PasswordFieldProps {
    id: string;
    label: string;
    placeholder?: string;
    description?: string;
    value: string;
    onChange: (val: string) => void;
    error?: string;
}

function PasswordField({ id, label, placeholder = "", description = "", value, onChange, error }: PasswordFieldProps) {
    const [show, setShow] = useState(false);

    return (
        <div className="space-y-1.5">
            <Label htmlFor={id} className="text-foreground text-sm font-medium">
                {label}
            </Label>
            <div className="relative">
                <Input
                    id={id}
                    name={id}
                    type={show ? "text" : "password"}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    autoComplete="new-password"
                    className={[
                        "border-border bg-background text-foreground placeholder:text-muted-foreground/60 h-11 pr-11",
                        "focus-visible:ring-primary transition-colors duration-150 focus-visible:border-transparent focus-visible:ring-2",
                        error ? "border-destructive focus-visible:ring-destructive" : "hover:border-primary/60",
                    ]
                        .filter(Boolean)
                        .join(" ")}
                />
                <button
                    type="button"
                    tabIndex={-1}
                    onClick={() => setShow((s) => !s)}
                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2 transition-colors focus:outline-none"
                    aria-label={show ? "Hide password" : "Show password"}
                >
                    {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
            </div>
            {description && !error && <p className="text-muted-foreground text-xs">{description}</p>}
            {error && (
                <motion.p initial={{ opacity: 0, y: -4 }} animate={{ opacity: 1, y: 0 }} className="text-destructive text-xs">
                    {error}
                </motion.p>
            )}
        </div>
    );
}

interface DateFieldProps {
    id: string;
    label: string;
    value: string;
    onChange: (val: string) => void;
    error?: string;
    min?: string;
}

function DateField({ id, label, value, onChange, error, min }: DateFieldProps) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id} className="text-foreground text-sm font-medium">
                {label}
            </Label>
            <div className="relative">
                <CalendarDays className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <Input
                    id={id}
                    name={id}
                    type="date"
                    value={value}
                    min={min}
                    onChange={(e) => onChange(e.target.value)}
                    className={[
                        "border-border bg-background text-foreground h-11 pl-9",
                        "focus-visible:ring-primary transition-colors duration-150 focus-visible:border-transparent focus-visible:ring-2",
                        error ? "border-destructive focus-visible:ring-destructive" : "hover:border-primary/60",
                    ]
                        .filter(Boolean)
                        .join(" ")}
                />
            </div>
            {error && (
                <motion.p initial={{ opacity: 0, y: -4 }} animate={{ opacity: 1, y: 0 }} className="text-destructive text-xs">
                    {error}
                </motion.p>
            )}
        </div>
    );
}

/* ── Page ── */

export default function Setup() {
    const { meta, branding } = usePage().props as any;
    const appName = meta?.appName || "Platform";
    const logoUrl = branding?.logo || null;

    const [step, setStep] = useState(1);
    const [direction, setDirection] = useState(1);

    const goToStep = (next: number) => {
        setDirection(next > step ? 1 : -1);
        setStep(next);
    };

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        admin_name: "",
        admin_email: "",
        admin_password: "",
        admin_password_confirmation: "",
        school_name: "",
        school_code: "",
        school_email: "",
        school_starting_date: "",
        school_ending_date: "",
        semester: "1",
    });

    const handleNextStep1 = () => {
        clearErrors();
        const errs: string[] = [];
        if (!data.admin_name.trim()) errs.push("Please provide your full name.");
        if (!data.admin_email || !/^\S+@\S+\.\S+$/.test(data.admin_email)) errs.push("Please provide a valid admin email.");
        if (!data.admin_password || data.admin_password.length < 8) errs.push("Password must be at least 8 characters.");
        if (data.admin_password !== data.admin_password_confirmation) errs.push("Passwords do not match.");
        if (errs.length) {
            errs.forEach((e) => toast.error(e));
            return;
        }
        goToStep(2);
    };

    const handleNextStep2 = () => {
        clearErrors();
        const errs: string[] = [];
        if (!data.school_name.trim()) errs.push("Please provide an institution name.");
        if (!data.school_code.trim()) errs.push("Please provide an institution code.");
        if (errs.length) {
            errs.forEach((e) => toast.error(e));
            return;
        }
        goToStep(3);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post("/setup", {
            onSuccess: () => {
                toast.success(`${appName} initialized successfully!`);
            },
            onError: (errs) => {
                toast.error("Please correct the errors and try again.");
                if (errs.admin_name || errs.admin_email || errs.admin_password) goToStep(1);
                else if (errs.school_name || errs.school_code || errs.school_email) goToStep(2);
                else goToStep(3);
            },
        });
    };

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <div className="bg-background relative flex min-h-screen flex-col items-center justify-center overflow-hidden p-4 py-10">
            <Head title={`Initialize ${appName}`} />

            {/* Ambient blobs */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
                <div className="bg-primary/5 absolute -top-1/4 -left-1/4 h-1/2 w-1/2 rounded-full blur-[120px]" />
                <div className="bg-secondary/10 absolute -right-1/4 -bottom-1/4 h-1/2 w-1/2 rounded-full blur-[120px]" />
                <div className="absolute inset-0 bg-[linear-gradient(to_right,hsl(var(--border))_1px,transparent_1px),linear-gradient(to_bottom,hsl(var(--border))_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_20%,transparent_100%)] bg-[size:4rem_4rem] opacity-20" />
            </div>

            <div className="relative z-10 flex w-full max-w-[1040px] flex-col gap-8 md:flex-row md:items-stretch">
                {/* ── Left sidebar ── */}
                <div className="flex w-full shrink-0 flex-col gap-8 md:w-[260px] md:pt-8">
                    {/* Brand */}
                    <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} className="flex items-center gap-3">
                        {logoUrl ? (
                            <img src={logoUrl} alt={appName} className="h-9 w-auto" />
                        ) : (
                            <div className="bg-primary/10 ring-primary/20 rounded-xl p-2 ring-1">
                                <ShieldCheck className="text-primary h-5 w-5" />
                            </div>
                        )}
                        <span className="text-foreground text-lg font-bold tracking-tight">{appName}</span>
                    </motion.div>

                    {/* Headline */}
                    <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.08 }} className="space-y-2">
                        <h2 className="text-foreground font-serif text-3xl leading-tight">
                            System <br />
                            <span className="text-muted-foreground">Setup.</span>
                        </h2>
                        <p className="text-muted-foreground text-sm leading-relaxed">Three quick steps to bootstrap your fresh installation.</p>
                    </motion.div>

                    {/* Step tracker */}
                    <motion.div
                        initial={{ opacity: 0, y: 16 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.16 }}
                        className="relative hidden space-y-6 pt-4 md:block"
                    >
                        <div className="bg-border absolute top-2 bottom-8 left-4 w-0.5 rounded-full">
                            <motion.div
                                className="bg-primary w-full rounded-full"
                                animate={{ height: step === 1 ? "0%" : step === 2 ? "50%" : "100%" }}
                                transition={{ ease: "easeInOut", duration: 0.4 }}
                            />
                        </div>

                        {STEPS.map((s, i) => {
                            const num = i + 1;
                            const active = step === num;
                            const done = step > num;
                            return (
                                <div key={num} className="relative z-10 flex items-start gap-4">
                                    <div
                                        className={[
                                            "ring-background mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 transition-all duration-300",
                                            done || active
                                                ? "bg-primary text-primary-foreground shadow-primary/20 shadow-md"
                                                : "border-border bg-muted text-muted-foreground border",
                                        ].join(" ")}
                                    >
                                        {done ? <ShieldCheck className="h-3.5 w-3.5" /> : <span className="text-xs font-bold">{num}</span>}
                                    </div>
                                    <div className={`pt-0.5 transition-opacity duration-300 ${active || done ? "opacity-100" : "opacity-40"}`}>
                                        <p className={`text-sm leading-none font-semibold ${active ? "text-foreground" : "text-muted-foreground"}`}>
                                            {s.label}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">{s.description}</p>
                                    </div>
                                </div>
                            );
                        })}
                    </motion.div>
                </div>

                {/* ── Form area ── */}
                <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.24 }} className="w-full flex-1">
                    {/* Progress bar */}
                    <div className="bg-border mb-2 h-1 w-full overflow-hidden rounded-full">
                        <motion.div
                            className="bg-primary h-full rounded-full"
                            animate={{ width: `${(step / STEPS.length) * 100}%` }}
                            transition={{ ease: "easeInOut", duration: 0.35 }}
                        />
                    </div>

                    <Card className="border-border bg-card shadow-foreground/5 relative flex min-h-[500px] flex-col overflow-hidden shadow-xl">
                        {/* Error banner */}
                        <AnimatePresence>
                            {hasErrors && (
                                <motion.div
                                    key="error-banner"
                                    initial={{ opacity: 0, y: -8 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    exit={{ opacity: 0, y: -8 }}
                                    className="absolute inset-x-0 top-0 z-50"
                                >
                                    <Alert variant="destructive" className="bg-destructive/10 text-destructive rounded-none border-x-0 border-t-0">
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertTitle className="text-sm font-semibold">Action Required</AlertTitle>
                                        <AlertDescription className="text-xs">Please correct the highlighted fields below.</AlertDescription>
                                    </Alert>
                                </motion.div>
                            )}
                        </AnimatePresence>

                        <CardContent className="flex-1 p-0">
                            <form onSubmit={submit} noValidate>
                                <AnimatePresence mode="wait" custom={direction}>
                                    {/* ── STEP 1: Admin ── */}
                                    {step === 1 && (
                                        <motion.div
                                            key="step1"
                                            custom={direction}
                                            variants={slideVariants}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="space-y-6 p-6 md:p-10"
                                        >
                                            <div className="space-y-1">
                                                <div className="mb-3 flex items-center gap-2">
                                                    <div className="bg-primary text-primary-foreground flex h-7 w-7 items-center justify-center rounded-full">
                                                        <UserCircle className="h-4 w-4" />
                                                    </div>
                                                    <span className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                                                        Step 1 of 3
                                                    </span>
                                                </div>
                                                <CardTitle className="text-foreground text-2xl font-bold tracking-tight">
                                                    Create your account
                                                </CardTitle>
                                                <CardDescription className="text-muted-foreground text-sm">
                                                    This account will have full unrestricted access to all modules and settings.
                                                </CardDescription>
                                            </div>

                                            <div className="space-y-4">
                                                <TextField
                                                    id="admin_name"
                                                    label="Full Name"
                                                    placeholder="John Doe"
                                                    value={data.admin_name}
                                                    onChange={(v) => setData("admin_name", v)}
                                                    error={errors.admin_name}
                                                />
                                                <TextField
                                                    id="admin_email"
                                                    label="Email Address"
                                                    type="email"
                                                    placeholder="admin@example.com"
                                                    description="Used for account recovery and system notifications."
                                                    value={data.admin_email}
                                                    onChange={(v) => setData("admin_email", v)}
                                                    error={errors.admin_email}
                                                />
                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <PasswordField
                                                        id="admin_password"
                                                        label="Password"
                                                        description="At least 8 characters."
                                                        value={data.admin_password}
                                                        onChange={(v) => setData("admin_password", v)}
                                                        error={errors.admin_password}
                                                    />
                                                    <PasswordField
                                                        id="admin_password_confirmation"
                                                        label="Confirm Password"
                                                        value={data.admin_password_confirmation}
                                                        onChange={(v) => setData("admin_password_confirmation", v)}
                                                    />
                                                </div>
                                            </div>

                                            <div className="flex justify-end pt-2">
                                                <Button
                                                    type="button"
                                                    onClick={handleNextStep1}
                                                    className="group bg-primary text-primary-foreground hover:bg-primary/90 h-11 w-full px-8 sm:w-auto"
                                                >
                                                    Continue to Institution
                                                    <ChevronRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                                </Button>
                                            </div>
                                        </motion.div>
                                    )}

                                    {/* ── STEP 2: Institution ── */}
                                    {step === 2 && (
                                        <motion.div
                                            key="step2"
                                            custom={direction}
                                            variants={slideVariants}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="space-y-6 p-6 md:p-10"
                                        >
                                            <div className="space-y-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => goToStep(1)}
                                                    className="text-muted-foreground hover:bg-muted hover:text-foreground mb-3 -ml-2 h-8 px-2"
                                                >
                                                    <ChevronLeft className="mr-1 h-4 w-4" /> Back
                                                </Button>
                                                <div className="mb-3 flex items-center gap-2">
                                                    <div className="bg-primary text-primary-foreground flex h-7 w-7 items-center justify-center rounded-full">
                                                        <Building2 className="h-4 w-4" />
                                                    </div>
                                                    <span className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                                                        Step 2 of 3
                                                    </span>
                                                </div>
                                                <CardTitle className="text-foreground text-2xl font-bold tracking-tight">
                                                    Institution Details
                                                </CardTitle>
                                                <CardDescription className="text-muted-foreground text-sm">
                                                    Register the primary educational institution for this deployment.
                                                </CardDescription>
                                            </div>

                                            <div className="space-y-4">
                                                <TextField
                                                    id="school_name"
                                                    label="Institution Name"
                                                    placeholder="e.g. KoAkademy"
                                                    value={data.school_name}
                                                    onChange={(v) => setData("school_name", v)}
                                                    error={errors.school_name}
                                                />
                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <TextField
                                                        id="school_code"
                                                        label="Institution Code"
                                                        placeholder="KOA"
                                                        uppercase
                                                        description="Short acronym identifier."
                                                        value={data.school_code}
                                                        onChange={(v) => setData("school_code", v)}
                                                        error={errors.school_code}
                                                    />
                                                    <TextField
                                                        id="school_email"
                                                        label="Official Email"
                                                        type="email"
                                                        placeholder="info@institution.edu"
                                                        description="Optional public contact email."
                                                        value={data.school_email}
                                                        onChange={(v) => setData("school_email", v)}
                                                        error={errors.school_email}
                                                    />
                                                </div>
                                            </div>

                                            <div className="flex justify-end pt-2">
                                                <Button
                                                    type="button"
                                                    onClick={handleNextStep2}
                                                    className="group bg-primary text-primary-foreground hover:bg-primary/90 h-11 w-full px-8 sm:w-auto"
                                                >
                                                    Continue to Academic Period
                                                    <ChevronRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                                </Button>
                                            </div>
                                        </motion.div>
                                    )}

                                    {/* ── STEP 3: Academic Period ── */}
                                    {step === 3 && (
                                        <motion.div
                                            key="step3"
                                            custom={direction}
                                            variants={slideVariants}
                                            initial="hidden"
                                            animate="visible"
                                            exit="exit"
                                            className="space-y-6 p-6 md:p-10"
                                        >
                                            <div className="space-y-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => goToStep(2)}
                                                    className="text-muted-foreground hover:bg-muted hover:text-foreground mb-3 -ml-2 h-8 px-2"
                                                >
                                                    <ChevronLeft className="mr-1 h-4 w-4" /> Back
                                                </Button>
                                                <div className="mb-3 flex items-center gap-2">
                                                    <div className="bg-primary text-primary-foreground flex h-7 w-7 items-center justify-center rounded-full">
                                                        <GraduationCap className="h-4 w-4" />
                                                    </div>
                                                    <span className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">
                                                        Step 3 of 3
                                                    </span>
                                                </div>
                                                <CardTitle className="text-foreground text-2xl font-bold tracking-tight">Academic Period</CardTitle>
                                                <CardDescription className="text-muted-foreground text-sm">
                                                    Set the current school year and semester to seed <strong>GeneralSettings</strong>.
                                                </CardDescription>
                                            </div>

                                            <div className="space-y-4">
                                                {/* Semester select */}
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="semester" className="text-foreground text-sm font-medium">
                                                        Current Semester
                                                    </Label>
                                                    <Select value={data.semester} onValueChange={(val) => setData("semester", val)}>
                                                        <SelectTrigger
                                                            id="semester"
                                                            className={[
                                                                "border-border bg-background text-foreground hover:border-primary/60 h-11 transition-colors",
                                                                errors.semester ? "border-destructive" : "",
                                                            ].join(" ")}
                                                        >
                                                            <SelectValue placeholder="Select semester…" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="1">1st Semester</SelectItem>
                                                            <SelectItem value="2">2nd Semester</SelectItem>
                                                            <SelectItem value="3">Summer / Midyear</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    {errors.semester && (
                                                        <motion.p
                                                            initial={{ opacity: 0, y: -4 }}
                                                            animate={{ opacity: 1, y: 0 }}
                                                            className="text-destructive text-xs"
                                                        >
                                                            {errors.semester}
                                                        </motion.p>
                                                    )}
                                                </div>

                                                {/* Date pickers */}
                                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <DateField
                                                        id="school_starting_date"
                                                        label="School Year Start"
                                                        value={data.school_starting_date}
                                                        onChange={(v) => setData("school_starting_date", v)}
                                                        error={errors.school_starting_date}
                                                    />
                                                    <DateField
                                                        id="school_ending_date"
                                                        label="School Year End"
                                                        value={data.school_ending_date}
                                                        min={data.school_starting_date || undefined}
                                                        onChange={(v) => setData("school_ending_date", v)}
                                                        error={errors.school_ending_date}
                                                    />
                                                </div>

                                                {/* Info callout */}
                                                <div className="border-border bg-muted/50 flex items-start gap-3 rounded-lg border p-4">
                                                    <BookOpen className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                                    <p className="text-muted-foreground text-xs leading-relaxed">
                                                        These values populate <span className="text-foreground font-medium">GeneralSettings</span> and
                                                        are used to filter classes, enrollments, and reports. You can update them anytime from the
                                                        admin panel.
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="pt-2">
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="group bg-primary text-primary-foreground hover:bg-primary/90 relative h-12 w-full overflow-hidden text-base font-medium"
                                                >
                                                    <span
                                                        className={`flex items-center justify-center gap-2 transition-transform duration-300 ${processing ? "-translate-y-12" : "translate-y-0"}`}
                                                    >
                                                        <Sparkles className="h-4 w-4 opacity-70" />
                                                        Complete Setup
                                                    </span>
                                                    <span
                                                        className={`absolute inset-0 flex items-center justify-center gap-2 transition-transform duration-300 ${processing ? "translate-y-0" : "translate-y-12"}`}
                                                    >
                                                        <Loader2 className="h-4 w-4 animate-spin opacity-70" />
                                                        Initializing…
                                                    </span>
                                                </Button>
                                            </div>
                                        </motion.div>
                                    )}
                                </AnimatePresence>
                            </form>
                        </CardContent>
                    </Card>
                </motion.div>
            </div>

            <style dangerouslySetInnerHTML={{ __html: `[type="date"]::-webkit-calendar-picker-indicator { opacity: 0.4; cursor: pointer; }` }} />
        </div>
    );
}
