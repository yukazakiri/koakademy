import { usePage } from "@inertiajs/react";

import { LoginForm, type DemoMode } from "@/components/login-form";
import { AuthLayout, type AuthLayoutProps } from "@/layouts/auth-layout";
import { resolveBranding, type Branding } from "@/lib/branding";

export default function LoginPage() {
    const { errors, status, branding, announcements, demoMode } = usePage<{
        errors?: Record<string, string>;
        status?: string | null;
        branding?: Partial<Branding> | null;
        announcements?: AuthLayoutProps["announcements"];
        demoMode?: DemoMode;
    }>().props;

    const resolvedBranding = resolveBranding(branding);
    const appName = resolvedBranding.appName;

    return (
        <AuthLayout
            metaTitle="Sign In"
            title={`Sign in to ${appName}`}
            description="Welcome back."
            announcements={announcements}
            maxWidth="sm"
        >
            <LoginForm errors={errors} status={status} demoMode={demoMode} />
        </AuthLayout>
    );
}
