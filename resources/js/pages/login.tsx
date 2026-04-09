import { Head, usePage } from "@inertiajs/react";

import { LoginForm } from "@/components/login-form";
import { OnboardingPanel } from "@/components/onboarding-panel";
import { ThemeToggle } from "@/components/theme-toggle";
import { TransitionWrapper } from "@/components/transition-wrapper";

interface Branding {
    appName: string;
    appShortName: string;
    organizationName: string;
    organizationShortName: string;
    tagline: string | null;
    logo?: string | null;
}

export default function LoginPage() {
    const { errors, status, branding } = usePage<{
        errors?: Record<string, string>;
        status?: string;
        branding?: Branding;
    }>().props;

    const appName = branding?.appName || "School Portal";
    const organizationName = branding?.organizationName || "University";
    const organizationShortName = branding?.organizationShortName || "UNI";

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
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
                            <img src={branding?.logo || "/web-app-manifest-192x192.png"} alt={`${organizationShortName} Logo`} className="h-10 w-10" />
                        </div>
                        <span className="text-foreground text-4xl font-extrabold tracking-tight">{appName}</span>
                    </a>
                    <div className="md:absolute md:top-6 md:right-6">
                        <ThemeToggle />
                    </div>
                </div>
                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-sm space-y-6">
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

            <div className="bg-muted relative hidden lg:block">
                <TransitionWrapper className="h-full">
                    <OnboardingPanel className="h-full" />
                </TransitionWrapper>
            </div>
        </div>
    );
}
