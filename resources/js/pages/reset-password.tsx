import { Link, useForm, usePage } from "@inertiajs/react";
import { ArrowLeft, Check, Eye, EyeOff, Loader2, Lock, Mail, ShieldCheck } from "lucide-react";
import { useEffect, useState, type FormEvent } from "react";
import { toast } from "sonner";

import { AuthLayout, type AuthLayoutProps } from "@/layouts/auth-layout";
import { Button } from "@/components/ui/button";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from "@/components/ui/input-group";
import { cn } from "@/lib/utils";

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPasswordPage({ token, email }: ResetPasswordProps) {
    const { props: pageProps } = usePage<{ announcements?: AuthLayoutProps["announcements"] }>();

    const { data, setData, post, processing, errors } = useForm({
        token,
        email: email ?? "",
        password: "",
        password_confirmation: "",
    });

    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    useEffect(() => {
        if (errors && Object.keys(errors).length) {
            Object.values(errors).forEach((m) => toast.error(m));
        }
    }, [errors]);

    const passwordsMatch = data.password && data.password_confirmation && data.password === data.password_confirmation;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post("/reset-password", {
            onSuccess: () => toast.success("Password updated successfully! You can now log in."),
        });
    };

    return (
        <AuthLayout
            metaTitle="Set New Password"
            badge="Security Verification"
            icon={<ShieldCheck className="size-6 text-primary" />}
            title="Create new password"
            description="Choose a strong, secure password with at least 8 characters to protect your account."
            announcements={pageProps.announcements}
            showBackToLogin={true}
            maxWidth="sm"
        >
            <form onSubmit={submit}>
                <FieldGroup className="gap-5">
                    {/* Email field (readonly) */}
                    <Field>
                        <FieldLabel htmlFor="email" className="text-xs font-semibold text-foreground">
                            Account Email Address
                        </FieldLabel>
                        <InputGroup className="h-11 rounded-xl border-border/80 bg-muted/40 opacity-90 shadow-2xs">
                            <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                <Mail className="size-4" />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="email"
                                type="email"
                                required
                                value={data.email}
                                onChange={(e) => setData("email", e.target.value)}
                                disabled
                                className="text-sm font-normal text-muted-foreground"
                            />
                        </InputGroup>
                    </Field>

                    {/* New password field */}
                    <Field>
                        <FieldLabel htmlFor="password" className="text-xs font-semibold text-foreground">
                            New Password
                        </FieldLabel>
                        <InputGroup
                            className={cn(
                                "h-11 rounded-xl border-border/80 bg-background/60 shadow-2xs transition-all duration-200 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20",
                                errors.password && "border-destructive focus-within:ring-destructive/20"
                            )}
                        >
                            <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                <Lock className="size-4" />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="password"
                                type={showPassword ? "text" : "password"}
                                placeholder="Enter new password"
                                required
                                autoFocus
                                value={data.password}
                                onChange={(e) => setData("password", e.target.value)}
                                disabled={processing}
                                className="text-sm font-normal"
                            />
                            <InputGroupAddon align="inline-end" className="pr-1.5">
                                <InputGroupButton
                                    size="icon-xs"
                                    onClick={() => setShowPassword(!showPassword)}
                                    aria-label={showPassword ? "Hide password" : "Show password"}
                                    className="text-muted-foreground hover:text-foreground"
                                >
                                    {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </InputGroupButton>
                            </InputGroupAddon>
                        </InputGroup>
                        {errors.password && <FieldError errors={[{ message: errors.password }]} />}
                    </Field>

                    {/* Confirm password field */}
                    <Field>
                        <FieldLabel htmlFor="password_confirmation" className="text-xs font-semibold text-foreground">
                            Confirm New Password
                        </FieldLabel>
                        <InputGroup
                            className={cn(
                                "h-11 rounded-xl border-border/80 bg-background/60 shadow-2xs transition-all duration-200 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20",
                                errors.password_confirmation && "border-destructive focus-within:ring-destructive/20",
                                passwordsMatch && "border-success focus-within:ring-success/20"
                            )}
                        >
                            <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                <Lock className="size-4" />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="password_confirmation"
                                type={showConfirmPassword ? "text" : "password"}
                                placeholder="Confirm your new password"
                                required
                                value={data.password_confirmation}
                                onChange={(e) => setData("password_confirmation", e.target.value)}
                                disabled={processing}
                                className="text-sm font-normal"
                            />
                            <InputGroupAddon align="inline-end" className="pr-1.5">
                                {passwordsMatch && (
                                    <span className="text-success mr-1 flex items-center">
                                        <Check className="size-4" />
                                    </span>
                                )}
                                <InputGroupButton
                                    size="icon-xs"
                                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                    aria-label={showConfirmPassword ? "Hide password" : "Show password"}
                                    className="text-muted-foreground hover:text-foreground"
                                >
                                    {showConfirmPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </InputGroupButton>
                            </InputGroupAddon>
                        </InputGroup>
                        {errors.password_confirmation && <FieldError errors={[{ message: errors.password_confirmation }]} />}
                    </Field>

                    {/* Submit button */}
                    <Button
                        type="submit"
                        className="h-11 w-full rounded-xl font-semibold shadow-md transition-all duration-200"
                        disabled={processing}
                    >
                        {processing ? (
                            <>
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                <span>Updating password...</span>
                            </>
                        ) : (
                            "Reset Password"
                        )}
                    </Button>

                    <div className="pt-2 text-center text-xs text-muted-foreground">
                        <Link href="/login" className="font-semibold text-primary underline-offset-4 hover:underline inline-flex items-center gap-1">
                            <ArrowLeft className="size-3" />
                            <span>Back to sign in</span>
                        </Link>
                    </div>
                </FieldGroup>
            </form>
        </AuthLayout>
    );
}
