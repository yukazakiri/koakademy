import { Link, usePage } from "@inertiajs/react";
import { ArrowRight, BadgeCheck, BookOpenCheck, CheckCircle2, GraduationCap, KeyRound, MailCheck, Sparkles, UserCheck } from "lucide-react";

import { SignupStepper } from "@/components/signup-stepper";
import { AuthLayout, type AuthLayoutProps } from "@/layouts/auth-layout";
import { resolveBranding, type Branding } from "@/lib/branding";
import type { SignupPresentationConfig } from "@/types/student-configuration";

const DEFAULT_SIGNUP_CONFIG: SignupPresentationConfig = {
    student_id_label: "Student ID",
    faculty_id_label: "Faculty ID",
    external_identifier_enabled: false,
    external_identifier_label: "External Identifier",
    external_identifier_help_text: null,
    external_identifier_only: false,
    universal_identifier_label: "Student ID",
    student_identifier_placeholder: "e.g. 2026-0001",
    student_identifier_help_text: "Found on your admission notice, registration slip, or campus ID card.",
    faculty_identifier_placeholder: "e.g. FAC-2026-001",
    faculty_identifier_help_text: "Enter the ID assigned by HR or your institution.",
    enabled_track_labels: ["Students"],
    allow_student_self_signup: true,
    allow_faculty_self_signup: true,
    allow_identifier_recovery: true,
    require_faculty_id: false,
};

function formatTrackSummary(labels: string[]): string {
    if (labels.length === 0) return "Enrolled learners";
    if (labels.length === 1) return labels[0];
    if (labels.length === 2) return `${labels[0]} & ${labels[1]}`;
    return `${labels.slice(0, -1).join(", ")} & ${labels[labels.length - 1]}`;
}

function SignupOnboardingPanel({
    appName,
    organizationShortName,
    signupConfig,
}: {
    appName: string;
    organizationShortName: string;
    signupConfig: SignupPresentationConfig;
}) {
    const trackSummary = formatTrackSummary(signupConfig.enabled_track_labels);

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
            title: "3. Confirm your campus ID",
            detail: `Students verify automatically with their ${signupConfig.universal_identifier_label}; faculty match via roster.`,
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
                <span className="border-border/70 bg-background/90 text-foreground inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium shadow-xs backdrop-blur-md">
                    <span className="bg-primary size-1.5 animate-pulse rounded-full" />
                    {organizationShortName} Self-Service Activation
                </span>
                <span className="border-border/70 bg-background/90 text-foreground inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold shadow-xs backdrop-blur-md">
                    <Sparkles className="text-primary size-3" />
                    ~2 min setup
                </span>
            </div>

            {/* Middle Guided Campus Canvas Card */}
            <div className="my-auto space-y-4">
                <div className="border-border/70 bg-background/90 space-y-1.5 rounded-2xl border p-5 shadow-sm backdrop-blur-md">
                    <h2 className="text-foreground text-xl font-bold tracking-tight xl:text-2xl xl:leading-tight">
                        No login credentials yet? <span className="text-primary">Claim your portal account here.</span>
                    </h2>
                    <p className="text-muted-foreground text-xs leading-relaxed">
                        If your school hasn&apos;t provisioned a password for you, activate your own {appName} account in four guided steps.
                    </p>
                </div>

                {/* Role Track Requirements */}
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="border-border/70 bg-background/85 rounded-2xl border p-4 shadow-sm backdrop-blur-md">
                        <div className="mb-2 flex items-center gap-2">
                            <span className="bg-primary/15 text-primary flex size-7 items-center justify-center rounded-lg">
                                <GraduationCap className="size-4" />
                            </span>
                            <span className="text-foreground text-xs font-semibold">Students</span>
                        </div>
                        <p className="text-muted-foreground text-[11px] leading-relaxed">
                            {signupConfig.allow_student_self_signup ? (
                                <>
                                    {trackSummary}. Keep your{" "}
                                    <span className="text-foreground font-medium">{signupConfig.universal_identifier_label}</span> handy — we match
                                    your record automatically.
                                </>
                            ) : (
                                "Student accounts are provisioned directly by your school administrator."
                            )}
                        </p>
                    </div>

                    <div className="border-border/70 bg-background/85 rounded-2xl border p-4 shadow-sm backdrop-blur-md">
                        <div className="mb-2 flex items-center gap-2">
                            <span className="bg-primary/15 text-primary flex size-7 items-center justify-center rounded-lg">
                                <UserCheck className="size-4" />
                            </span>
                            <span className="text-foreground text-xs font-semibold">Faculty</span>
                        </div>
                        <p className="text-muted-foreground text-[11px] leading-relaxed">
                            {signupConfig.allow_faculty_self_signup ? (
                                <>
                                    Instructors &amp; staff. Use your <span className="text-foreground font-medium">institutional email</span>
                                    {signupConfig.require_faculty_id ? (
                                        <>
                                            {" "}
                                            and <span className="text-foreground font-medium">{signupConfig.faculty_id_label}</span>
                                        </>
                                    ) : null}{" "}
                                    on file with HR or Registrar.
                                </>
                            ) : (
                                "Faculty accounts are provisioned directly by your school administrator."
                            )}
                        </p>
                    </div>
                </div>

                {/* 4-Step Activation Roadmap */}
                <div className="border-border/70 bg-background/85 rounded-2xl border p-4 shadow-sm backdrop-blur-md">
                    <div className="text-muted-foreground mb-3 flex items-center gap-2 text-[11px] font-semibold tracking-wider uppercase">
                        <BookOpenCheck className="text-primary size-3.5" />
                        <span>How activation works</span>
                    </div>
                    <div className="space-y-3">
                        {steps.map((step) => {
                            const StepIcon = step.icon;
                            return (
                                <div key={step.title} className="flex items-start gap-3">
                                    <span className="border-border/70 bg-card text-primary mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg border">
                                        <StepIcon className="size-3.5" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="text-foreground text-xs font-semibold">{step.title}</p>
                                        <p className="text-muted-foreground text-[11px] leading-snug">{step.detail}</p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {/* Bottom New Applicant Fallback Callout */}
            <div className="border-border/70 bg-background/90 flex items-center justify-between gap-4 rounded-2xl border px-4 py-3.5 shadow-sm backdrop-blur-md">
                <div className="min-w-0">
                    <p className="text-foreground text-xs font-semibold">New student not enrolled yet?</p>
                    <p className="text-muted-foreground text-[11px]">Submit an online admission &amp; enrollment application first.</p>
                </div>
                <Link
                    href="/enrollment"
                    className="bg-primary text-primary-foreground inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold shadow-xs transition-opacity hover:opacity-90"
                >
                    <span>Apply Now</span>
                    <ArrowRight className="size-3.5" />
                </Link>
            </div>
        </div>
    );
}

export default function SignupPage() {
    const { branding, announcements, socialiteSignup, signupConfig } = usePage<{
        branding?: Partial<Branding> | null;
        announcements?: AuthLayoutProps["announcements"];
        socialiteSignup?: {
            name?: string | null;
            email?: string | null;
            avatar_url?: string | null;
            provider?: string | null;
        } | null;
        signupConfig?: Partial<SignupPresentationConfig> | null;
    }>().props;

    const resolvedBranding = resolveBranding(branding);
    const appName = resolvedBranding.appName;
    const resolvedSignupConfig: SignupPresentationConfig = {
        ...DEFAULT_SIGNUP_CONFIG,
        ...(signupConfig ?? {}),
    };

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
                    signupConfig={resolvedSignupConfig}
                />
            }
        >
            <SignupStepper socialiteSignup={socialiteSignup} signupConfig={resolvedSignupConfig} />
        </AuthLayout>
    );
}
