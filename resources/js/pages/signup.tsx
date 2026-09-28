import { usePage } from "@inertiajs/react";

import { SignupStepper } from "@/components/signup-stepper";
import { AuthLayout, type AuthLayoutProps } from "@/layouts/auth-layout";
import { resolveBranding, type Branding } from "@/lib/branding";

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
            metaTitle="Register Account"
            badge="Academic Onboarding"
            title={
                <span>
                    Join <span className="text-primary">{appName}</span>
                </span>
            }
            description="Create your institutional account to access your courses, enrollment records, class schedules, and digital ID."
            announcements={announcements}
            showBackToLogin={true}
            maxWidth="md"
        >
            <SignupStepper socialiteSignup={socialiteSignup} />
        </AuthLayout>
    );
}
