import { useForm } from "@inertiajs/react";
import { AlertCircle, BadgeCheck, CheckCircle2, Loader2, Mail, UserCheck } from "lucide-react";
import type { FormEvent } from "react";

import { AuthLayout } from "@/layouts/auth-layout";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { cn } from "@/lib/utils";

interface FacultyVerifyProps {
    email?: string;
    errors?: Record<string, string>;
    status?: string;
    warning?: string;
}

export default function FacultyVerifyPage({ email = "", errors = {}, status, warning }: FacultyVerifyProps) {
    const { data, setData, post, processing } = useForm({
        email: email,
        faculty_id_number: "",
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post("/faculty-verify");
    };

    return (
        <AuthLayout
            metaTitle="Faculty Verification"
            badge="Official Credentials"
            icon={<UserCheck className="size-6 text-primary" />}
            title="Faculty Identity Verification"
            description="Verify your faculty ID number provided by the institution to access academic tools and rosters."
            maxWidth="sm"
        >
            <form onSubmit={submit}>
                <FieldGroup className="gap-5">
                    {/* Warning Alert */}
                    {warning && (
                        <Alert className="border-warning/30 bg-warning/10 text-warning-foreground">
                            <AlertCircle className="size-4 text-warning" />
                            <AlertDescription className="text-xs">{warning}</AlertDescription>
                        </Alert>
                    )}

                    {/* Status Alert */}
                    {status && (
                        <Alert className="border-success/30 bg-success/10 text-success-foreground">
                            <CheckCircle2 className="size-4 text-success" />
                            <AlertDescription className="text-xs">{status}</AlertDescription>
                        </Alert>
                    )}

                    {/* Email field (readonly) */}
                    <Field>
                        <FieldLabel htmlFor="email" className="text-xs font-semibold text-foreground">
                            Account Email
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
                        {errors.email && <FieldError errors={[{ message: errors.email }]} />}
                    </Field>

                    {/* Faculty ID field */}
                    <Field>
                        <FieldLabel htmlFor="faculty_id_number" className="text-xs font-semibold text-foreground">
                            Faculty Identification Number
                        </FieldLabel>
                        <InputGroup
                            className={cn(
                                "h-11 rounded-xl border-border/80 bg-background/60 shadow-2xs transition-all duration-200 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20",
                                errors.faculty_id_number && "border-destructive focus-within:ring-destructive/20"
                            )}
                        >
                            <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                <BadgeCheck className="size-4" />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="faculty_id_number"
                                type="text"
                                placeholder="e.g. FAC-2025-001"
                                required
                                autoFocus
                                value={data.faculty_id_number}
                                onChange={(e) => setData("faculty_id_number", e.target.value)}
                                disabled={processing}
                                className="text-sm font-normal uppercase"
                            />
                        </InputGroup>
                        {errors.faculty_id_number && <FieldError errors={[{ message: errors.faculty_id_number }]} />}
                        <FieldDescription className="text-xs text-muted-foreground">
                            Enter the institutional faculty ID number provided by the Human Resources or Registrar&apos;s office.
                        </FieldDescription>
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
                                <span>Verifying credentials...</span>
                            </>
                        ) : (
                            "Verify & Continue"
                        )}
                    </Button>

                    <div className="rounded-xl border border-border/60 bg-muted/30 p-3 text-center text-xs text-muted-foreground">
                        Need assistance? Please contact your institution&apos;s IT Helpdesk or visit the Registrar&apos;s Office.
                    </div>
                </FieldGroup>
            </form>
        </AuthLayout>
    );
}
