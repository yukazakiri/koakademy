import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    CircleAlert,
    Clock,
    FileCheck2,
    GraduationCap,
    Hash,
    Loader2,
    LogIn,
    Mail,
    Search,
    Sparkles,
} from "lucide-react";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { toast } from "sonner";

interface TimelineStep {
    key: string;
    label: string;
    description: string;
    state: "completed" | "current" | "upcoming" | "halted";
}

interface ApplicationStatusPayload {
    reference_id: string;
    applicant_name: string;
    email: string;
    program: string | null;
    program_code: string | null;
    department: string | null;
    submitted_at: string | null;
    school_year: string | null;
    semester: number | null;
    status: string;
    status_label: string;
    is_cancelled: boolean;
    documents_on_file: number;
    documents_total: number;
    timeline: TimelineStep[];
    can_activate_portal: boolean;
    has_portal_account: boolean;
    signup_url: string | null;
    login_url: string;
}

interface EnrollmentStatusProps {
    school_name?: string;
    prefill?: {
        email?: string;
        reference_id?: string;
    };
}

export default function EnrollmentStatusPage({ school_name = "KoAkademy", prefill }: EnrollmentStatusProps) {
    const [email, setEmail] = useState(prefill?.email ?? "");
    const [referenceId, setReferenceId] = useState(prefill?.reference_id ?? "");
    const [isChecking, setIsChecking] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [application, setApplication] = useState<ApplicationStatusPayload | null>(null);
    const didAutoLookup = useRef(false);

    const performLookup = async (lookupEmail: string, lookupRef: string) => {
        if (!lookupEmail.trim() || !lookupRef.trim()) {
            setErrorMessage("Please enter both your registered email address and Reference / Applicant ID.");
            return;
        }

        setIsChecking(true);
        setErrorMessage(null);

        try {
            const response = await axios.post("/enrollment/status", {
                email: lookupEmail.trim(),
                reference_id: lookupRef.trim(),
            });

            if (response.data?.found && response.data?.application) {
                setApplication(response.data.application as ApplicationStatusPayload);
            } else {
                setApplication(null);
                setErrorMessage(response.data?.message || "No matching application was found.");
            }
        } catch (error: unknown) {
            setApplication(null);
            const err = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
            const message =
                err.response?.data?.message ||
                (err.response?.data?.errors && Object.values(err.response.data.errors)[0]?.[0]) ||
                "We could not find an application matching that email and Reference ID.";
            setErrorMessage(message);
            toast.error(message);
        } finally {
            setIsChecking(false);
        }
    };

    useEffect(() => {
        if (!didAutoLookup.current && prefill?.email && prefill?.reference_id) {
            didAutoLookup.current = true;
            void performLookup(prefill.email, prefill.reference_id);
        }
    }, [prefill?.email, prefill?.reference_id]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        void performLookup(email, referenceId);
    };

    return (
        <div className="min-h-screen bg-background">
            <Head title={`Application Status — ${school_name}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-10 sm:px-6 md:py-14">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button render={<Link href="/enrollment" />} nativeButton={false} variant="ghost" size="sm" className="gap-1.5">
                        <ArrowLeft className="size-4" />
                        Back to Online Enrollment
                    </Button>
                    <Badge variant="outline" className="gap-1.5 rounded-full px-3 py-1 text-xs">
                        <GraduationCap className="size-3.5 text-primary" />
                        {school_name}
                    </Badge>
                </div>

                <div className="space-y-2 text-center">
                    <div className="mx-auto inline-flex items-center gap-1.5 rounded-full border border-primary/25 bg-primary/5 px-3 py-1 text-xs font-semibold text-primary">
                        <Sparkles className="size-3.5" />
                        Applicant Self-Service Tracker
                    </div>
                    <h1 className="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">Check Your Application Status</h1>
                    <p className="text-muted-foreground mx-auto max-w-xl text-sm leading-relaxed">
                        Enter the email address and Reference / Applicant ID from your confirmation email to view your enrollment timeline and next
                        steps.
                    </p>
                </div>

                <Card className="border-border/70 bg-card/85 shadow-sm">
                    <CardHeader className="pb-4">
                        <CardTitle className="text-base">Find Your Application</CardTitle>
                        <CardDescription className="text-xs">
                            Both fields are required to keep your admission record private.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="status-email" className="text-xs font-semibold">
                                    Registered Email Address
                                </Label>
                                <div className="relative">
                                    <Mail className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="status-email"
                                        type="email"
                                        required
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        placeholder="applicant@email.com"
                                        className="pl-9"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="status-ref" className="text-xs font-semibold">
                                    Reference / Applicant ID
                                </Label>
                                <div className="relative">
                                    <Hash className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="status-ref"
                                        type="text"
                                        required
                                        value={referenceId}
                                        onChange={(e) => setReferenceId(e.target.value)}
                                        placeholder="e.g. 20260001"
                                        className="pl-9 font-mono"
                                    />
                                </div>
                            </div>

                            <div className="sm:col-span-2">
                                <Button type="submit" disabled={isChecking} className="w-full gap-2">
                                    {isChecking ? (
                                        <>
                                            <Loader2 className="size-4 animate-spin" />
                                            Checking Application...
                                        </>
                                    ) : (
                                        <>
                                            <Search className="size-4" />
                                            Check Status
                                        </>
                                    )}
                                </Button>
                            </div>
                        </form>

                        {errorMessage && (
                            <div className="mt-4 flex items-start gap-2.5 rounded-xl border border-destructive/30 bg-destructive/5 p-3.5 text-xs text-destructive">
                                <CircleAlert className="mt-0.5 size-4 shrink-0" />
                                <span>{errorMessage}</span>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {application && (
                    <Card className="border-primary/25 bg-card/90 shadow-md">
                        <CardHeader className="gap-3 border-b pb-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline" className="font-mono text-xs">
                                        Ref #{application.reference_id}
                                    </Badge>
                                    <Badge
                                        variant={application.is_cancelled ? "destructive" : "secondary"}
                                        className="rounded-full text-xs font-semibold"
                                    >
                                        {application.status_label}
                                    </Badge>
                                </div>
                                <CardTitle className="text-xl">{application.applicant_name}</CardTitle>
                                <CardDescription className="text-xs">
                                    {application.program ? (
                                        <>
                                            {application.program}
                                            {application.department ? ` · ${application.department}` : ""}
                                        </>
                                    ) : (
                                        application.email
                                    )}
                                    {application.submitted_at ? ` · Submitted ${application.submitted_at}` : ""}
                                </CardDescription>
                            </div>

                            <div className="flex items-center gap-2 rounded-xl border border-border/70 bg-background/70 px-3 py-2 text-xs">
                                <FileCheck2 className="size-4 text-primary" />
                                <div>
                                    <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Documents</p>
                                    <p className="font-mono font-semibold tabular-nums">
                                        {application.documents_on_file} / {application.documents_total} on file
                                    </p>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-6 pt-5">
                            {/* 4-Stage Status Timeline */}
                            <ol className="space-y-3" aria-label="Application progress timeline">
                                {application.timeline.map((step, index) => {
                                    const isCompleted = step.state === "completed";
                                    const isCurrent = step.state === "current";
                                    const isHalted = step.state === "halted";

                                    return (
                                        <li
                                            key={step.key}
                                            className={cn(
                                                "flex items-start gap-3.5 rounded-xl border p-3.5 transition-colors",
                                                isCompleted
                                                    ? "border-emerald-500/25 bg-emerald-500/5"
                                                    : isCurrent
                                                      ? "border-primary/35 bg-primary/5"
                                                      : isHalted
                                                        ? "border-destructive/30 bg-destructive/5"
                                                        : "border-border/60 bg-background/50",
                                            )}
                                        >
                                            <div
                                                className={cn(
                                                    "mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold",
                                                    isCompleted
                                                        ? "bg-emerald-600 text-white"
                                                        : isCurrent
                                                          ? "bg-primary text-primary-foreground"
                                                          : isHalted
                                                            ? "bg-destructive text-destructive-foreground"
                                                            : "bg-muted text-muted-foreground",
                                                )}
                                            >
                                                {isCompleted ? (
                                                    <CheckCircle2 className="size-4" />
                                                ) : isCurrent ? (
                                                    <Clock className="size-3.5" />
                                                ) : isHalted ? (
                                                    <CircleAlert className="size-3.5" />
                                                ) : (
                                                    index + 1
                                                )}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="text-sm font-semibold text-foreground">{step.label}</p>
                                                    {isCurrent && (
                                                        <Badge variant="outline" className="border-primary/40 text-primary text-[10px]">
                                                            Current Stage
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-muted-foreground mt-0.5 text-xs leading-relaxed">{step.description}</p>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>

                            {/* Next-Step Portal Activation Handoff */}
                            {application.can_activate_portal && (
                                <div className="flex flex-col gap-3 rounded-2xl border border-primary/30 bg-primary/5 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="space-y-0.5">
                                        <p className="text-sm font-semibold text-foreground">
                                            {application.has_portal_account
                                                ? "Your Student Portal Account Is Ready"
                                                : "Ready to Activate Your Student Portal?"}
                                        </p>
                                        <p className="text-muted-foreground text-xs leading-relaxed">
                                            {application.has_portal_account
                                                ? "Sign in to view your schedule, tuition statement, and registrar document vault."
                                                : "Continue to self-service activation with your email pre-filled — have your Student Number handy."}
                                        </p>
                                    </div>
                                    {application.has_portal_account ? (
                                        <Button
                                            render={<Link href={application.login_url} />}
                                            nativeButton={false}
                                            size="sm"
                                            className="shrink-0 gap-1.5"
                                        >
                                            <LogIn className="size-4" />
                                            Sign In to Portal
                                        </Button>
                                    ) : application.signup_url ? (
                                        <Button
                                            render={<Link href={application.signup_url} />}
                                            nativeButton={false}
                                            size="sm"
                                            className="shrink-0 gap-1.5"
                                        >
                                            Activate Student Portal
                                            <ArrowRight className="size-4" />
                                        </Button>
                                    ) : null}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                <p className="text-muted-foreground text-center text-xs">
                    Need to submit a new application?{" "}
                    <Link href="/enrollment" className="text-primary font-medium hover:underline">
                        Start Online Enrollment
                    </Link>{" "}
                    · Already have a portal account?{" "}
                    <Link href="/login" className="text-primary font-medium hover:underline">
                        Sign in
                    </Link>
                </p>
            </div>
        </div>
    );
}
