import { usePage } from "@inertiajs/react";

import { LoginForm, type DemoMode, type PendingSocialLink } from "@/components/login-form";
import { AuthLayout, type AuthLayoutProps } from "@/layouts/auth-layout";
import { resolveBranding, type Branding } from "@/lib/branding";

export default function LoginPage() {
    const { errors, status, branding, announcements, demoMode, pendingSocialLink } = usePage<{
        errors?: Record<string, string>;
        status?: string | null;
        branding?: Partial<Branding> | null;
        announcements?: AuthLayoutProps["announcements"];
        demoMode?: DemoMode;
        pendingSocialLink?: PendingSocialLink | null;
    }>().props;

    const resolvedBranding = resolveBranding(branding);
    const appName = resolvedBranding.appName;
    const providerLabel = pendingSocialLink?.provider
        ? pendingSocialLink.provider.charAt(0).toUpperCase() + pendingSocialLink.provider.slice(1)
        : null;

    return (
        <AuthLayout
            metaTitle={providerLabel ? `Link ${providerLabel}` : "Sign In"}
            title={providerLabel ? `Sign in to link ${providerLabel}` : `Sign in to ${appName}`}
            description={
                providerLabel
                    ? "Sign in with your existing portal credentials to connect your social login."
                    : "Welcome back."
            }
            announcements={announcements}
            maxWidth="sm"
        >
            <LoginForm errors={errors} status={status} demoMode={demoMode} pendingSocialLink={pendingSocialLink} />
        </AuthLayout>
    );
}
