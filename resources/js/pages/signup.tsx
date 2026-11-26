import { Link, usePage } from "@inertiajs/react";
import { ArrowRight, BadgeCheck, BookOpenCheck, CheckCircle2, GraduationCap, KeyRound, MailCheck, Sparkles, UserCheck } from "lucide-react";

import { SignupStepper } from "@/components/signup-stepper";
import { AuthLayout, type AuthLayoutProps } from "@/layouts/auth-layout";
import { resolveBranding, type Branding } from "@/lib/branding";

function SignupOnboardingPanel({
    appName,
    organizationShortName,
}: {
    appName: string;
    organizationShortName: string;
}) {
    const steps = [
        {
            icon: MailCheck,
            title: "1. Find your roster record",
            detail: "Enter the email registered with admissions, registrar, or HR.",
        },
        {
            icon: KeyRound,
            title: "2. Create your own password",
            detail: "Set a secure personal password — no admin-issued credentials needed.",
        },
        {
            icon: BadgeCheck,
            title: "3. Verify your campus ID",
            detail: "Students confirm with Student # or LRN; faculty link automatically.",
        },
        {
            icon: CheckCircle2,
            title: "4. Confirm via 6-digit email code",
            detail: "Enter the one-time code sent to your inbox for instant portal access.",
        },
    ];

    return (
        <div className="flex h-full flex-col justify-between gap-6">
            {/* Top Status Pill */}
            <div className="flex items-center justify-between gap-3">
                <span className="inline-flex items-center gap-2 rounded-full border border-border/70 bg-background/90 px-3 py-1 text-xs font-medium text-foreground shadow-xs backdrop-blur-md">
                    <span className="size-1.5 rounded-full bg-primary animate-pulse" />
                    {organizationShortName} Self-Service Activation
                </span>
                <span className="inline-flex items-center gap-1 rounded-full border border-border/70 bg-background/90 px-2.5 py-1 text-[11px] font-semibold text-foreground shadow-xs backdrop-blur-md">
                    <Sparkles className="size-3 text-primary" />
                    ~2 min setup
                </span>
            </div>

            {/* Middle Guided Campus Canvas Card */}
            <div className="my-auto space-y-4">
                <div className="space-y-1.5 rounded-2xl border border-border/70 bg-background/90 p-5 shadow-sm backdrop-blur-md">
                    <h2 className="text-xl font-bold tracking-tight text-foreground xl:text-2xl xl:leading-tight">
                        No login credentials yet?{" "}
                        <span className="text-primary">Claim your portal account here.</span>
                    </h2>
                    <p className="text-xs leading-relaxed text-muted-foreground">
                        If your school hasn&apos;t provisioned a password for you, activate your own {appName} account in four guided steps.
                    </p>
                </div>

                {/* Role Track Requirements */}
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-2xl border border-border/70 bg-background/85 p-4 shadow-sm backdrop-blur-md">
                        <div className="mb-2 flex items-center gap-2">
                            <span className="flex size-7 items-center justify-center rounded-lg bg-primary/15 text-primary">
                                <GraduationCap className="size-4" />
                            </span>
                            <span className="text-xs font-semibold text-foreground">Students</span>
                        </div>
                        <p className="text-[11px] leading-relaxed text-muted-foreground">
                            College, SHS, TESDA &amp; DHRT learners. Keep your <span className="font-medium text-foreground">Student Number</span> or{" "}
                            <span className="font-medium text-foreground">LRN</span> handy.
                        </p>
                    </div>

                    <div className="rounded-2xl border border-border/70 bg-background/85 p-4 shadow-sm backdrop-blur-md">
                        <div className="mb-2 flex items-center gap-2">
                            <span className="flex size-7 items-center justify-center rounded-lg bg-primary/15 text-primary">
                                <UserCheck className="size-4" />
                            </span>
                            <span className="text-xs font-semibold text-foreground">Faculty</span>
                        </div>
                        <p className="text-[11px] leading-relaxed text-muted-foreground">
                            Instructors &amp; professors. Use your <span className="font-medium text-foreground">institutional email</span> on file with HR or Registrar.
                        </p>
                    </div>
                </div>

                {/* 4-Step Activation Roadmap */}
                <div className="rounded-2xl border border-border/70 bg-background/85 p-4 shadow-sm backdrop-blur-md">
                    <div className="mb-3 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        <BookOpenCheck className="size-3.5 text-primary" />
                        <span>How activation works</span>
                    </div>
                    <div className="space-y-3">
                        {steps.map((step) => {
                            const StepIcon = step.icon;
                            return (
                                <div key={step.title} className="flex items-start gap-3">
                                    <span className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg border border-border/70 bg-card text-primary">
                                        <StepIcon className="size-3.5" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="text-xs font-semibold text-foreground">{step.title}</p>
                                        <p className="text-[11px] leading-snug text-muted-foreground">{step.detail}</p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {/* Bottom New Applicant Fallback Callout */}
            <div className="flex items-center justify-between gap-4 rounded-2xl border border-border/70 bg-background/90 px-4 py-3.5 shadow-sm backdrop-blur-md">
                <div className="min-w-0">
                    <p className="text-xs font-semibold text-foreground">New student not enrolled yet?</p>
                    <p className="text-[11px] text-muted-foreground">Submit an online admission &amp; enrollment application first.</p>
                </div>
                <Link
                    href="/enrollment"
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground shadow-xs transition-opacity hover:opacity-90"
                >
                    <span>Apply Now</span>
                    <ArrowRight className="size-3.5" />
                </Link>
            </div>
        </div>
    );
}

export default function SignupPage() {
    const { branding, announcements, socialiteSignup } = usePage<{
        branding?: Partial<Branding> | null;
        announcements?: AuthLayoutProps["announcements"];
        socialiteSignup?: {
            name?: string | null;
            email?: string | null;
            avatar_url?: string | null;
            provider?: string | null;
        } | null;
    }>().props;

    const resolvedBranding = resolveBranding(branding);
    const appName = resolvedBranding.appName;

    return (
        <AuthLayout
            metaTitle="Activate Account"
            badge="Self-Service Account Activation"
            title={
                <span>
                    Activate <span className="text-primary">{appName}</span> Access
                </span>
            }
            description="No admin-issued password? Verify your student or faculty record to create your own portal login."
            announcements={announcements}
            showBackToLogin={true}
            maxWidth="md"
            customRightPanel={
                <SignupOnboardingPanel
                    appName={appName}
                    organizationShortName={resolvedBranding.organizationShortName}
                />
            }
        >
            <SignupStepper socialiteSignup={socialiteSignup} />
        </AuthLayout>
    );
}
