import { Head, usePage } from "@inertiajs/react";

import { resolveBranding, type Branding } from "@/lib/branding";
import { LoginForm } from "@/components/login-form";
import { OnboardingPanel } from "@/components/onboarding-panel";
import { ThemeToggle } from "@/components/theme-toggle";
import { TransitionWrapper } from "@/components/transition-wrapper";

export default function LoginPage() {
    const { errors, status, branding } = usePage<{
        errors?: Record<string, string>;
        status?: string | null;
        branding?: Partial<Branding> | null;
    }>().props;

    const resolvedBranding = resolveBranding(branding);
    const appName = resolvedBranding.appName;
    const organizationName = resolvedBranding.organizationName;
    const organizationShortName = resolvedBranding.organizationShortName;
    const authLayout = resolvedBranding.authLayout;
    const isSplitLayout = authLayout === "split";

    return (
        <div className={isSplitLayout ? "grid min-h-svh lg:grid-cols-2" : "min-h-svh"}>
            <Head title={`${appName} - Academic Management System`}>
                <meta
                    name="description"
                    content={`The official academic portal for ${organizationName}. Access student records, grades, schedules, and faculty resources.`}
                />
            </Head>
            <div className="relative flex flex-col gap-4 p-6 md:p-10">
                <div className="flex items-center justify-between md:justify-start">
                    <a href="#" className="flex items-center gap-2 font-medium">
                        <div className="flex h-10 w-10 items-center justify-center rounded-md">
                            <img src={resolvedBranding.logo} alt={`${organizationShortName} Logo`} className="h-10 w-10 object-contain" />
                        </div>
                        <span className="text-foreground text-4xl font-extrabold tracking-tight">{appName}</span>
                    </a>
                    <div className="md:absolute md:top-6 md:right-6">
                        <ThemeToggle />
                    </div>
                </div>
                <div className="flex flex-1 items-center justify-center">
                    <div
                        className={
                            authLayout === "card"
                                ? "bg-card border-border w-full max-w-md space-y-6 rounded-2xl border p-6 shadow-sm"
                                : authLayout === "minimal"
                                  ? "w-full max-w-sm space-y-4"
                                  : "w-full max-w-sm space-y-6"
                        }
                    >
                        <div className="space-y-2 text-center">
                            <h1 className="text-foreground text-2xl font-bold tracking-tight">Academic Management Portal</h1>
                            <p className="text-muted-foreground text-sm text-pretty">
                                Welcome to the official {appName}. A centralized platform for {organizationName} students and faculty to manage
                                academic records, schedules, and class requirements efficiently.
                            </p>
                        </div>

                        <TransitionWrapper>
                            <LoginForm errors={errors} status={status} />
                        </TransitionWrapper>
                    </div>
                </div>
            </div>

            {isSplitLayout ? (
                <div className="bg-muted relative hidden lg:block">
                    <TransitionWrapper className="h-full">
                        <OnboardingPanel className="h-full" />
                    </TransitionWrapper>
                </div>
            ) : null}
        </div>
    );
}
