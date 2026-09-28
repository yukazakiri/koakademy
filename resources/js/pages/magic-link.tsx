import { Link, useForm, usePage } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, Loader2, Mail, Sparkles } from "lucide-react";
import { useEffect, useState, type FormEvent } from "react";
import { toast } from "sonner";

import { AuthLayout, type AuthLayoutProps } from "@/layouts/auth-layout";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { resolveBranding, type Branding } from "@/lib/branding";
import { cn } from "@/lib/utils";

export default function MagicLinkPage() {
    const { props } = usePage<{
        branding?: Partial<Branding> | null;
        announcements?: AuthLayoutProps["announcements"];
        status?: string | null;
    }>();

    const branding = resolveBranding(props.branding);
    const appName = branding.appName;

    const { data, setData, post, processing, errors } = useForm({
        email: "",
        remember: true,
    });

    const [linkSent, setLinkSent] = useState(Boolean(props.status));
    const [cooldown, setCooldown] = useState(0);

    useEffect(() => {
        if (props.status) {
            setLinkSent(true);
            toast.success(props.status);
            setCooldown(60);
        }
    }, [props.status]);

    useEffect(() => {
        if (cooldown <= 0) return;
        const timer = setInterval(() => {
            setCooldown((prev) => prev - 1);
        }, 1000);
        return () => clearInterval(timer);
    }, [cooldown]);

    useEffect(() => {
        if (errors && Object.keys(errors).length) {
            Object.values(errors).forEach((m) => toast.error(m));
        }
    }, [errors]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post("/magic-link/send", {
            onSuccess: () => {
                setLinkSent(true);
                setCooldown(60);
                toast.success("Secure sign-in link dispatched. Check your inbox!");
            },
            onError: (formErrors) => {
                Object.values(formErrors).forEach((err) => toast.error(err));
            },
        });
    };

    return (
        <AuthLayout
            metaTitle="Passwordless Sign In"
            badge="Passwordless Access"
            icon={<Sparkles className="size-6 text-primary" />}
            title={
                <span>
                    Sign in with <span className="text-primary">Magic Link</span>
                </span>
            }
            description={
                linkSent
                    ? "Check your inbox for your secure, single-use authentication link."
                    : `Enter the email associated with your ${appName} account and we'll send an instant sign-in link.`
            }
            announcements={props.announcements}
            showBackToLogin={true}
            maxWidth="sm"
        >
            {linkSent ? (
                <div className="space-y-6 text-center">
                    <div className="mx-auto flex size-14 items-center justify-center rounded-2xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-400 shadow-md">
                        <CheckCircle2 className="size-7" />
                    </div>

                    <div className="space-y-2">
                        <h3 className="text-lg font-semibold text-foreground">Check your email</h3>
                        <p className="text-sm text-muted-foreground text-pretty">
                            We have sent a single-use login link to{" "}
                            <span className="font-medium text-foreground">{data.email || "your address"}</span>.
                            Click the link in your email to sign in instantly.
                        </p>
                    </div>

                    <div className="rounded-xl border border-border bg-card/60 p-3 text-xs text-muted-foreground">
                        The link expires in 15 minutes and can only be used once. Check your spam folder if it doesn&apos;t arrive soon.
                    </div>

                    <div className="space-y-3 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full h-10 rounded-lg border-border bg-card/80 text-foreground hover:bg-accent hover:text-accent-foreground"
                            onClick={submit}
                            disabled={processing || cooldown > 0}
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 size-4 animate-spin text-muted-foreground" />
                                    <span>Sending new link...</span>
                                </>
                            ) : cooldown > 0 ? (
                                `Resend link (${cooldown}s)`
                            ) : (
                                "Resend magic link"
                            )}
                        </Button>

                        <div>
                            <Link
                                href="/login"
                                className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-primary transition-colors"
                            >
                                <ArrowLeft className="size-3" />
                                <span>Return to password login</span>
                            </Link>
                        </div>
                    </div>
                </div>
            ) : (
                <form onSubmit={submit}>
                    <FieldGroup className="gap-5">
                        <Field>
                            <FieldLabel htmlFor="email" className="text-sm font-medium text-foreground">
                                Email address
                            </FieldLabel>
                            <InputGroup
                                className={cn(
                                    "h-10 rounded-lg border-input bg-background/80 shadow-xs transition-colors focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/20",
                                    errors?.email && "border-destructive focus-within:ring-destructive/20"
                                )}
                            >
                                <InputGroupAddon align="inline-start" className="text-muted-foreground pl-3">
                                    <Mail className="size-4" />
                                </InputGroupAddon>
                                <InputGroupInput
                                    id="email"
                                    type="email"
                                    placeholder="name@school.edu"
                                    required
                                    autoFocus
                                    value={data.email}
                                    onChange={(e) => setData("email", e.target.value)}
                                    disabled={processing}
                                    className="text-sm text-foreground placeholder:text-muted-foreground font-normal px-2"
                                />
                            </InputGroup>
                            {errors?.email && <FieldError errors={[{ message: errors.email }]} />}
                        </Field>

                        <div className="flex items-center space-x-2 pt-0.5">
                            <Checkbox
                                id="remember"
                                checked={data.remember}
                                onCheckedChange={(checked) => setData("remember", Boolean(checked))}
                                disabled={processing}
                                className="border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground focus-visible:ring-ring"
                            />
                            <label
                                htmlFor="remember"
                                className="text-xs font-normal text-muted-foreground peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer select-none"
                            >
                                Stay signed in on this device
                            </label>
                        </div>

                        <Button
                            type="submit"
                            className="h-10 w-full rounded-lg bg-primary font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 focus-visible:ring-ring disabled:opacity-50 text-sm mt-1"
                            disabled={processing}
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 size-4 animate-spin text-primary-foreground" />
                                    <span>Sending sign-in link...</span>
                                </>
                            ) : (
                                "Send magic link"
                            )}
                        </Button>

                        <div className="pt-2 text-center text-xs text-muted-foreground">
                            Prefer passwords?{" "}
                            <Link href="/login" className="font-semibold text-primary underline-offset-4 hover:underline">
                                Sign in with password
                            </Link>
                        </div>
                    </FieldGroup>
                </form>
            )}
        </AuthLayout>
    );
}
