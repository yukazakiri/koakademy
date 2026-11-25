import { Check, Circle } from "lucide-react";

import { cn } from "@/lib/utils";

export interface PasswordPolicy {
    minLength: number;
    requireLetters: boolean;
    requireMixedCase: boolean;
    requireNumbers: boolean;
    requireSymbols: boolean;
}

export const DEFAULT_PASSWORD_POLICY: PasswordPolicy = {
    minLength: 8,
    requireLetters: true,
    requireMixedCase: false,
    requireNumbers: true,
    requireSymbols: false,
};

export interface PasswordRuleCheck {
    id: string;
    label: string;
    met: boolean;
}

export function evaluatePasswordPolicy(password: string, policy?: Partial<PasswordPolicy> | null): {
    checks: PasswordRuleCheck[];
    allMet: boolean;
    score: number;
    label: string;
    color: string;
} {
    const resolved: PasswordPolicy = {
        ...DEFAULT_PASSWORD_POLICY,
        ...policy,
    };

    const checks: PasswordRuleCheck[] = [
        {
            id: "length",
            label: `At least ${resolved.minLength} characters`,
            met: password.length >= resolved.minLength,
        },
    ];

    if (resolved.requireMixedCase) {
        checks.push(
            { id: "upper", label: "An uppercase letter (A-Z)", met: /[A-Z]/.test(password) },
            { id: "lower", label: "A lowercase letter (a-z)", met: /[a-z]/.test(password) },
        );
    } else if (resolved.requireLetters) {
        checks.push({ id: "letters", label: "At least one letter", met: /[A-Za-z]/.test(password) });
    }

    if (resolved.requireNumbers) {
        checks.push({ id: "numbers", label: "At least one number (0-9)", met: /[0-9]/.test(password) });
    }

    if (resolved.requireSymbols) {
        checks.push({ id: "symbols", label: "At least one symbol (!@#$...)", met: /[^A-Za-z0-9]/.test(password) });
    }

    const allMet = checks.every((c) => c.met);

    let score = 0;
    if (password.length >= resolved.minLength) score += 1;
    if (/[A-Z]/.test(password)) score += 1;
    if (/[a-z]/.test(password)) score += 1;
    if (/[0-9]/.test(password)) score += 1;
    if (/[^A-Za-z0-9]/.test(password)) score += 1;

    let label = "";
    let color = "bg-muted";
    if (password.length > 0) {
        if (!allMet || score <= 2) {
            label = "Weak";
            color = "bg-destructive";
        } else if (score <= 3) {
            label = "Fair";
            color = "bg-amber-500";
        } else if (score === 4) {
            label = "Strong";
            color = "bg-emerald-500";
        } else {
            label = "Very Strong";
            color = "bg-emerald-500";
        }
    }

    return { checks, allMet, score, label, color };
}

export function PasswordRequirements({
    password,
    policy,
    className,
}: {
    password: string;
    policy?: Partial<PasswordPolicy> | null;
    className?: string;
}) {
    const { checks, score, label, color } = evaluatePasswordPolicy(password, policy);

    return (
        <div className={cn("mt-2 space-y-2", className)}>
            {password.length > 0 && (
                <div className="space-y-1">
                    <div className="flex h-1.5 gap-1">
                        {[1, 2, 3, 4, 5].map((lvl) => (
                            <div
                                key={lvl}
                                className={cn("h-full flex-1 rounded-full transition-colors", lvl <= score ? color : "bg-muted")}
                            />
                        ))}
                    </div>
                    <p className="text-muted-foreground text-[11px]">
                        Strength: <span className="text-foreground font-semibold">{label}</span>
                    </p>
                </div>
            )}

            <ul className="grid grid-cols-1 gap-1 sm:grid-cols-2">
                {checks.map((check) => (
                    <li
                        key={check.id}
                        className={cn(
                            "flex items-center gap-1.5 text-[11px] transition-colors",
                            check.met ? "text-emerald-600 dark:text-emerald-400" : "text-muted-foreground",
                        )}
                    >
                        {check.met ? <Check className="size-3 shrink-0" /> : <Circle className="size-2.5 shrink-0 opacity-50" />}
                        <span>{check.label}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
