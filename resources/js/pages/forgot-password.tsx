import { Link, useForm, usePage } from "@inertiajs/react";
import { ArrowLeft, KeyRound, Loader2, Mail } from "lucide-react";
import { useEffect, type FormEvent } from "react";
import { toast } from "sonner";

import { AuthLayout, type AuthLayoutProps } from "@/layouts/auth-layout";
import { Button } from "@/components/ui/button";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { resolveBranding, type Branding } from "@/lib/branding";
import { cn } from "@/lib/utils";

export default function ForgotPasswordPage() {
    const { props } = usePage<{ branding?: Partial<Branding> | null; announcements?: AuthLayoutProps["announcements"] }>();
    const branding = resolveBranding(props.branding);
    const appName = branding.appName;

    const { data, setData, post, processing, errors } = useForm({
        email: "",
    });

    useEffect(() => {
        if (errors && Object.keys(errors).length) {
            Object.values(errors).forEach((m) => toast.error(m));
        }
    }, [errors]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post("/forgot-password", {
            onSuccess: () => toast.success("Password reset instructions sent if an account exists for this email."),
        });
    };

    return (
        <AuthLayout
            metaTitle="Forgot Password"
            badge="Security & Recovery"
            icon={<KeyRound className="size-6 text-primary" />}
            title="Reset your password"
            description={`Enter the institutional or personal email associated with your ${appName} account and we'll send you a recovery link.`}
            announcements={props.announcements}
            showBackToLogin={true}
            maxWidth="sm"
        >
            <form onSubmit={submit}>
                <FieldGroup className="gap-5">
                    <Field>
                        <FieldLabel htmlFor="email" className="text-xs font-semibold text-foreground">
                            Account Email Address
                        </FieldLabel>
                        <InputGroup
                            className={cn(
                                "h-11 rounded-xl border-border/80 bg-background/60 shadow-2xs transition-all duration-200 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20",
                                errors.email && "border-destructive focus-within:ring-destructive/20"
                            )}
                        >
                            <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
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
                                className="text-sm font-normal"
                            />
                        </InputGroup>
                        {errors.email && <FieldError errors={[{ message: errors.email }]} />}
                    </Field>

                    <Button
                        type="submit"
                        className="h-11 w-full rounded-xl font-semibold shadow-md transition-all duration-200"
                        disabled={processing}
                    >
                        {processing ? (
                            <>
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                <span>Sending reset link...</span>
                            </>
                        ) : (
                            "Send Reset Link"
                        )}
                    </Button>

                    <div className="pt-2 text-center text-xs text-muted-foreground">
                        Remembered your password?{" "}
                        <Link href="/login" className="font-semibold text-primary underline-offset-4 hover:underline inline-flex items-center gap-1">
                            <ArrowLeft className="size-3" />
                            <span>Return to login</span>
                        </Link>
                    </div>
                </FieldGroup>
            </form>
        </AuthLayout>
    );
}
