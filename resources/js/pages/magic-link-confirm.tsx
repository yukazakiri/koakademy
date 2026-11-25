import { Link, router, usePage } from "@inertiajs/react";
import { ArrowLeft, Loader2, ShieldCheck, Sparkles } from "lucide-react";
import { useState, type FormEvent } from "react";

import { AuthLayout, type AuthLayoutProps } from "@/layouts/auth-layout";
import { Button } from "@/components/ui/button";
import { resolveBranding, type Branding } from "@/lib/branding";

interface MagicLinkConfirmProps {
    email: string;
    consumeUrl: string;
}

export default function MagicLinkConfirmPage({ email, consumeUrl }: MagicLinkConfirmProps) {
    const { props } = usePage<{
        branding?: Partial<Branding> | null;
        announcements?: AuthLayoutProps["announcements"];
    }>();

    const appName = resolveBranding(props.branding).appName;
    const [submitting, setSubmitting] = useState(false);

    const handleConfirm = (e: FormEvent) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        router.post(
            consumeUrl,
            {},
            {
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <AuthLayout
            metaTitle="Confirm Sign-In"
            badge="Passwordless Verification"
            icon={<Sparkles className="size-6 text-primary" />}
            title="Complete your sign-in"
            description={`Confirm below to sign in to ${appName} as ${email}.`}
            announcements={props.announcements}
            showBackToLogin={true}
            maxWidth="sm"
        >
            <form onSubmit={handleConfirm} className="space-y-5">
                <div className="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-3.5 text-xs text-muted-foreground">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0 text-primary" />
                    <p>
                        This extra confirmation prevents automated email security scanners from accidentally expiring your one-time sign-in link.
                    </p>
                </div>

                <Button type="submit" className="h-11 w-full rounded-xl font-semibold shadow-sm" disabled={submitting}>
                    {submitting ? (
                        <>
                            <Loader2 className="mr-2 size-4 animate-spin" />
                            <span>Signing you in...</span>
                        </>
                    ) : (
                        `Continue as ${email}`
                    )}
                </Button>

                <div className="text-center text-xs text-muted-foreground">
                    <Link href="/login" className="inline-flex items-center gap-1 font-medium text-muted-foreground hover:text-primary transition-colors">
                        <ArrowLeft className="size-3" />
                        <span>Cancel and return to login</span>
                    </Link>
                </div>
            </form>
        </AuthLayout>
    );
}
