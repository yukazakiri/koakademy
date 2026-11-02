import { Field, FieldLabel } from "@/components/ui/field";
import type { StudentFieldPolicy } from "@/types/student-configuration";
import type { ReactNode } from "react";

export function ConfiguredStudentField({
    name,
    policy,
    error,
    children,
    className,
}: {
    name: string;
    policy: StudentFieldPolicy;
    error?: string;
    children: ReactNode;
    className?: string;
}) {
    if (!policy.visible) return null;

    return (
        <Field className={className}>
            <FieldLabel htmlFor={name}>
                {policy.label}
                {policy.required && <span className="text-destructive"> *</span>}
            </FieldLabel>
            {children}
            {error && (
                <p className="text-destructive text-sm" role="alert">
                    {error}
                </p>
            )}
        </Field>
    );
}
