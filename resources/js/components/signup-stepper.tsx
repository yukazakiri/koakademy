import { Link, router, useForm, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Check,
    ClipboardList,
    Eye,
    EyeOff,
    GraduationCap,
    KeyRound,
    Loader2,
    Lock,
    LogIn,
    Mail,
    RefreshCw,
    ShieldAlert,
    Sparkles,
    User,
    UserCheck,
} from "lucide-react";
import { useCallback, useEffect, useRef, useState, type FormEvent } from "react";
import { toast } from "sonner";

import { evaluatePasswordPolicy, PasswordRequirements, type PasswordPolicy } from "@/components/auth/password-requirements";
import { IconTile } from "@/components/reui/icon-tile";
import { SocialAuthButtons } from "@/components/social-auth-buttons";
import { Button } from "@/components/ui/button";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from "@/components/ui/input-group";
import { InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot } from "@/components/ui/input-otp";
import { cn } from "@/lib/utils";
import type { SignupPresentationConfig } from "@/types/student-configuration";

type UserType = "faculty" | "student" | null;

interface LookupHints {
    student_type?: string | null;
    identifier_type?: "student_id" | "lrn";
    identifier_label?: string | null;
    identifier_placeholder?: string | null;
    identifier_help_text?: string | null;
    student_id_label?: string | null;
    external_identifier_label?: string | null;
    accepts_external_identifier?: boolean;
    allow_identifier_recovery?: boolean;
    multiple_types?: boolean;
    requires_faculty_id?: boolean;
    has_faculty_id?: boolean;
    faculty_id_label?: string | null;
    faculty_id_placeholder?: string | null;
    faculty_id_help_text?: string | null;
}

interface EmailLookupResult {
    found: boolean;
    type?: "faculty" | "student";
    message?: string;
    account_exists?: boolean;
    hints?: LookupHints;
}

const STORAGE_KEY = "signup_form_state";

interface PersistedState {
    currentStep: number;
    userType: UserType;
    name: string;
    email: string;
    faculty_id_number: string;
    identifier: string;
    user_type: "" | "faculty" | "student";
    lookupHints?: LookupHints | null;
}

interface SocialiteSignup {
    name?: string | null;
    email?: string | null;
    avatar_url?: string | null;
    provider?: string | null;
}

const FALLBACK_CONFIG: SignupPresentationConfig = {
    student_id_label: "Student ID",
    faculty_id_label: "Faculty ID",
    external_identifier_enabled: false,
    external_identifier_label: "External Identifier",
    external_identifier_help_text: null,
    external_identifier_only: false,
    universal_identifier_label: "Student ID",
    student_identifier_placeholder: "e.g. 2026-0001",
    student_identifier_help_text: "Found on your admission notice, registration slip, or campus ID card.",
    faculty_identifier_placeholder: "e.g. FAC-2026-001",
    faculty_identifier_help_text: "Enter the ID assigned by HR or your institution.",
    enabled_track_labels: ["Students"],
    allow_student_self_signup: true,
    allow_faculty_self_signup: true,
    allow_identifier_recovery: true,
    require_faculty_id: false,
};

function loadPersistedState(): PersistedState | null {
    try {
        if (typeof localStorage !== "undefined" && localStorage.getItem(STORAGE_KEY)) {
            localStorage.removeItem(STORAGE_KEY);
        }
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as Partial<PersistedState> & {
            password?: unknown;
            password_confirmation?: unknown;
            student_id?: string;
            lrn?: string;
        };
        delete parsed.password;
        delete parsed.password_confirmation;
        return {
            currentStep: parsed.currentStep ?? 0,
            userType: parsed.userType ?? null,
            name: parsed.name ?? "",
            email: parsed.email ?? "",
            faculty_id_number: parsed.faculty_id_number ?? "",
            identifier: parsed.identifier ?? parsed.student_id ?? parsed.lrn ?? "",
            user_type: parsed.user_type ?? "",
            lookupHints: parsed.lookupHints ?? null,
        };
    } catch {
        return null;
    }
}

function persistState(state: PersistedState): void {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        return;
    }
}

function clearPersistedState(): void {
    try {
        sessionStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        return;
    }
}

export function SignupStepper({
    className,
    socialiteSignup,
    signupConfig,
    ...props
}: React.ComponentProps<"div"> & {
    socialiteSignup?: SocialiteSignup | null;
    signupConfig?: SignupPresentationConfig;
}) {
    const config = signupConfig ?? FALLBACK_CONFIG;
    const { passwordPolicy } = usePage<{ passwordPolicy?: Partial<PasswordPolicy> }>().props;
    const queryEmail = useRef(
        typeof window !== "undefined" ? (new URLSearchParams(window.location.search).get("email")?.trim() ?? "") : "",
    );
    const hasQueryEmail = queryEmail.current.length > 0 && !socialiteSignup?.email;
    const saved = useRef(socialiteSignup?.email || hasQueryEmail ? null : loadPersistedState());
    const emailInputRef = useRef<HTMLInputElement | null>(null);

    // Cap restored step at Step 1 (Credentials) because passwords are never persisted.
    const [currentStep, setCurrentStep] = useState(hasQueryEmail ? 0 : Math.min(saved.current?.currentStep ?? 0, 1));
    const [isCheckingEmail, setIsCheckingEmail] = useState(false);
    const [isSendingOtp, setIsSendingOtp] = useState(false);
    const [otpCooldown, setOtpCooldown] = useState(0);
    const [isRecoveringIdentifier, setIsRecoveringIdentifier] = useState(false);
    const [identifierRecoveryCooldown, setIdentifierRecoveryCooldown] = useState(0);
    const [emailLookupResult, setEmailLookupResult] = useState<EmailLookupResult | null>(null);
    const [userType, setUserType] = useState<UserType>(saved.current?.userType ?? null);
    const [lookupHints, setLookupHints] = useState<LookupHints | null>(saved.current?.lookupHints ?? null);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: saved.current?.name ?? socialiteSignup?.name ?? "",
        email: socialiteSignup?.email ?? (hasQueryEmail ? queryEmail.current : (saved.current?.email ?? "")),
        password: "",
        password_confirmation: "",
        faculty_id_number: saved.current?.faculty_id_number ?? "",
        identifier: saved.current?.identifier ?? "",
        otp: "",
        user_type: saved.current?.user_type ?? ("" as "faculty" | "student" | ""),
    });

    const isRestored = useRef(saved.current !== null);

    useEffect(() => {
        if (otpCooldown <= 0) return;
        const timer = setInterval(() => {
            setOtpCooldown((prev) => prev - 1);
        }, 1000);
        return () => clearInterval(timer);
    }, [otpCooldown]);

    useEffect(() => {
        if (identifierRecoveryCooldown <= 0) return;
        const timer = setInterval(() => {
            setIdentifierRecoveryCooldown((prev) => prev - 1);
        }, 1000);
        return () => clearInterval(timer);
    }, [identifierRecoveryCooldown]);

    const persistFormState = useCallback(() => {
        persistState({
            currentStep: currentStep === 3 ? 2 : currentStep,
            userType,
            name: data.name,
            email: data.email,
            faculty_id_number: data.faculty_id_number,
            identifier: data.identifier,
            user_type: data.user_type,
            lookupHints,
        });
    }, [currentStep, userType, data.name, data.email, data.faculty_id_number, data.identifier, data.user_type, lookupHints]);

    useEffect(() => {
        persistFormState();
    }, [persistFormState]);

    useEffect(() => {
        if (isRestored.current && emailLookupResult === null) {
            setEmailLookupResult(saved.current?.email?.length ? { found: true, type: saved.current.userType ?? undefined } : null);
        }
        isRestored.current = false;
    }, []);

    const formErrors = errors as Record<string, string | undefined>;
    const identifierError = formErrors.identifier ?? formErrors.student_id ?? formErrors.lrn;

    useEffect(() => {
        if (formErrors && Object.keys(formErrors).length > 0) {
            const firstErrorKey = Object.keys(formErrors)[0];
            const firstErrorMessage = formErrors[firstErrorKey];
            if (firstErrorMessage) {
                toast.error(firstErrorMessage);
            }
            if (formErrors.identifier || formErrors.student_id || formErrors.lrn || formErrors.faculty_id_number) {
                setCurrentStep(2);
            } else if (formErrors.email) {
                setCurrentStep(0);
            } else if (formErrors.name || formErrors.password) {
                setCurrentStep(1);
            }
        }
    }, [errors]);

    const validateEmail = (email: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

    const passwordsMatch = data.password === data.password_confirmation && data.password_confirmation !== "";
    const policyEvaluation = evaluatePasswordPolicy(data.password, passwordPolicy);
    const minPasswordLength = passwordPolicy?.minLength ?? 8;

    const studentIdentifierLabel = lookupHints?.identifier_label || config.universal_identifier_label || "Student ID";
    const studentIdPrimaryLabel = lookupHints?.student_id_label || config.student_id_label || "Student ID";
    const externalIdentifierLabel = lookupHints?.external_identifier_label ?? (config.external_identifier_enabled ? config.external_identifier_label : null);
    const acceptsExternalIdentifier = lookupHints?.accepts_external_identifier ?? config.external_identifier_enabled;
    const studentIdentifierPlaceholder = lookupHints?.identifier_placeholder || config.student_identifier_placeholder || "e.g. 2026-0001";
    const studentIdentifierHelpText =
        lookupHints?.identifier_help_text || config.student_identifier_help_text || "Found on your admission notice, registration slip, or campus ID card.";
    const allowIdentifierRecovery = lookupHints?.allow_identifier_recovery ?? config.allow_identifier_recovery ?? true;

    const facultyIdLabel = lookupHints?.faculty_id_label || config.faculty_id_label || "Faculty ID";
    const facultyIdPlaceholder = lookupHints?.faculty_id_placeholder || config.faculty_identifier_placeholder || "e.g. FAC-2026-001";
    const facultyIdHelpText = lookupHints?.faculty_id_help_text || config.faculty_identifier_help_text || "Enter the ID assigned by HR or your institution.";
    const requiresFacultyId = Boolean(lookupHints?.requires_faculty_id ?? config.require_faculty_id);

    const getSteps = () => {
        if (userType === "student") {
            return [
                { id: "email", label: "Find Record" },
                { id: "details", label: "Credentials" },
                { id: "verification", label: studentIdPrimaryLabel },
                { id: "otp", label: "Email Code" },
            ];
        }
        if (userType === "faculty") {
            return [
                { id: "email", label: "Find Record" },
                { id: "details", label: "Credentials" },
                { id: "verification", label: facultyIdLabel },
                { id: "otp", label: "Email Code" },
            ];
        }
        return [
            { id: "email", label: "Find Record" },
            { id: "details", label: "Credentials" },
            { id: "verification", label: "Verify ID" },
            { id: "otp", label: "Email Code" },
        ];
    };

    const steps = getSteps();

    const canSendOtp =
        !isSendingOtp &&
        (userType === "student"
            ? Boolean(data.identifier.trim())
            : !requiresFacultyId || Boolean(data.faculty_id_number.trim()));

    const sendOtp = async () => {
        if (!canSendOtp) return;
        setIsSendingOtp(true);
        try {
            await axios.post("/signup/send-otp", {
                email: data.email,
                user_type: data.user_type,
                identifier: data.identifier.trim(),
                faculty_id_number: data.faculty_id_number.trim(),
            });
            setOtpCooldown(60);
            toast.success("Verification code sent to your email.");
            setCurrentStep(steps.length - 1);
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
            const errorMessage =
                err.response?.data?.message ||
                (err.response?.data?.errors && Object.values(err.response.data.errors)[0]?.[0]) ||
                "Failed to send verification code. Please try again.";
            toast.error(errorMessage);
        } finally {
            setIsSendingOtp(false);
        }
    };

    const handleRecoverIdentifier = async () => {
        if (!validateEmail(data.email) || isRecoveringIdentifier || identifierRecoveryCooldown > 0) return;
        setIsRecoveringIdentifier(true);
        try {
            const response = await axios.post("/signup/recover-identifier", { email: data.email });
            setIdentifierRecoveryCooldown(60);
            toast.success(
                response.data?.message ||
                    `If a matching student record exists, we have sent your ${studentIdentifierLabel} to that email address.`,
            );
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Could not send your identifier right now. Please try again shortly.");
        } finally {
            setIsRecoveringIdentifier(false);
        }
    };

    const handleEmailLookup = async () => {
        if (!validateEmail(data.email)) {
            toast.error("Please enter a valid email address.");
            return;
        }

        setIsCheckingEmail(true);
        try {
            const response = await axios.post("/signup/email-lookup", { email: data.email, include_hints: true });
            const result = response.data as EmailLookupResult;
            setEmailLookupResult(result);

            if (result.found && (result.type === "faculty" || result.type === "student")) {
                setUserType(result.type);
                setLookupHints(result.hints ?? null);
                setData((prev) => ({
                    ...prev,
                    faculty_id_number: "",
                    identifier: "",
                    user_type: result.type!,
                }));
                toast.success(result.type === "faculty" ? "Faculty record found!" : "Student record found!");
                setCurrentStep(1);
            } else if (result.account_exists) {
                setLookupHints(null);
                toast.info("This email already has an active portal account. Sign in or reset your password.");
            } else {
                setLookupHints(null);
                toast.error(result.message || "Email address is not associated with an existing academic record.");
            }
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to verify email. Please try again.");
        } finally {
            setIsCheckingEmail(false);
        }
    };

    const handleChangeEmail = () => {
        setCurrentStep(0);
        setEmailLookupResult(null);
        setLookupHints(null);
        setUserType(null);
        setData((prev) => ({
            ...prev,
            user_type: "",
            identifier: "",
            faculty_id_number: "",
            otp: "",
        }));
        setTimeout(() => emailInputRef.current?.focus(), 60);
    };

    const handleNext = () => {
        setCurrentStep((prev) => Math.min(prev + 1, steps.length - 1));
    };

    const handlePrev = () => {
        setCurrentStep((prev) => Math.max(prev - 1, 0));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (currentStep < steps.length - 1) {
            return;
        }
        post("/signup", {
            onSuccess: () => {
                clearPersistedState();
                toast.success("Account created successfully!");
            },
        });
    };

    const canProceedFromDetails = data.name.trim().length > 0 && policyEvaluation.allMet && passwordsMatch;

    return (
        <div className={cn("space-y-5", className)} {...props}>
            {/* Guided Campus Canvas 4-Step Progress Header */}
            <div className="border-border/70 bg-card/75 rounded-2xl border p-3.5 shadow-2xs backdrop-blur-xs">
                <div className="mb-3 flex items-center justify-between text-xs">
                    <span className="text-muted-foreground font-medium">
                        Step <span className="text-foreground font-semibold">{currentStep + 1}</span> of {steps.length}
                    </span>
                    <span className="text-primary font-semibold">{steps[currentStep]?.label}</span>
                </div>
                <ol className="grid grid-cols-4 gap-2" aria-label="Account activation progress">
                    {steps.map((step, idx) => {
                        const isCompleted = idx < currentStep;
                        const isActive = idx === currentStep;
                        return (
                            <li key={step.id} className="space-y-1.5">
                                <div
                                    className={cn(
                                        "h-1.5 w-full rounded-full transition-all duration-300",
                                        isCompleted ? "bg-primary" : isActive ? "bg-primary ring-primary/25 ring-2" : "bg-muted",
                                    )}
                                />
                                <div className="flex items-center justify-center gap-1 text-[11px] sm:justify-start">
                                    <span
                                        className={cn(
                                            "flex size-4 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold transition-colors",
                                            isCompleted
                                                ? "bg-primary text-primary-foreground"
                                                : isActive
                                                  ? "border-primary/50 bg-primary/10 text-primary border"
                                                  : "bg-muted text-muted-foreground",
                                        )}
                                    >
                                        {isCompleted ? <Check className="size-2.5" /> : idx + 1}
                                    </span>
                                    <span
                                        className={cn(
                                            "hidden truncate sm:inline",
                                            isActive ? "text-foreground font-semibold" : isCompleted ? "text-foreground/80" : "text-muted-foreground",
                                        )}
                                    >
                                        {step.label}
                                    </span>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            </div>

            {/* Persistent Matched Record Identity Pill (Steps 2-4) */}
            {currentStep > 0 && userType && (
                <div className="border-primary/20 bg-primary/5 flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-xs">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <span className="bg-primary/15 text-primary flex size-7 shrink-0 items-center justify-center rounded-lg">
                            {userType === "student" ? <GraduationCap className="size-4" /> : <UserCheck className="size-4" />}
                        </span>
                        <div className="min-w-0">
                            <p className="text-foreground font-semibold">
                                {userType === "student" ? "Student Record Matched" : "Faculty Record Matched"}
                            </p>
                            <p className="text-muted-foreground truncate font-mono text-[11px]">{data.email}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={handleChangeEmail}
                        className="border-border/70 bg-background/80 text-muted-foreground hover:border-primary/40 hover:text-foreground shrink-0 rounded-lg border px-2.5 py-1 text-[11px] font-medium transition-colors"
                    >
                        Change
                    </button>
                </div>
            )}

            <form onSubmit={submit}>
                <FieldGroup className="gap-5">
                    {/* Step 0: Email Verification */}
                    {currentStep === 0 && (
                        <div className="space-y-4">
                            {socialiteSignup?.email && (
                                <div className="border-primary/25 bg-primary/5 flex items-center gap-3 rounded-xl border p-3.5 text-xs">
                                    {socialiteSignup.avatar_url ? (
                                        <img
                                            src={socialiteSignup.avatar_url}
                                            alt=""
                                            className="border-border size-8 shrink-0 rounded-full border object-cover"
                                        />
                                    ) : (
                                        <IconTile variant="soft" size="sm" className="shrink-0 rounded-lg">
                                            <User className="size-4" />
                                        </IconTile>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="text-foreground font-semibold">
                                            Completing signup with{" "}
                                            {socialiteSignup.provider
                                                ? socialiteSignup.provider.charAt(0).toUpperCase() + socialiteSignup.provider.slice(1)
                                                : "Social Account"}
                                        </p>
                                        <p className="text-muted-foreground truncate">{socialiteSignup.email}</p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-muted-foreground hover:text-foreground h-7 shrink-0 px-2 text-xs"
                                        onClick={() => {
                                            clearPersistedState();
                                            router.post("/signup/cancel-social");
                                        }}
                                    >
                                        Switch
                                    </Button>
                                </div>
                            )}

                            {/* Compact Self-Activation Track Summary */}
                            <div className="grid grid-cols-2 gap-2 text-[11px]">
                                <div className="border-border/70 bg-card/60 flex items-center gap-2 rounded-xl border px-3 py-2">
                                    <GraduationCap className="text-primary size-3.5 shrink-0" />
                                    <div className="min-w-0">
                                        <p className="text-foreground font-semibold">Students</p>
                                        <p className="text-muted-foreground truncate">Email + {config.universal_identifier_label}</p>
                                    </div>
                                </div>
                                <div className="border-border/70 bg-card/60 flex items-center gap-2 rounded-xl border px-3 py-2">
                                    <UserCheck className="text-primary size-3.5 shrink-0" />
                                    <div className="min-w-0">
                                        <p className="text-foreground font-semibold">Faculty</p>
                                        <p className="text-muted-foreground truncate">
                                            {config.require_faculty_id ? `Email + ${config.faculty_id_label}` : "School email"}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <Field>
                                <FieldLabel htmlFor="email" className="text-foreground text-xs font-semibold">
                                    Registered Email Address
                                </FieldLabel>
                                <InputGroup
                                    className={cn(
                                        "border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-primary/20 h-11 rounded-xl shadow-2xs transition-all duration-200 focus-within:ring-2",
                                        errors.email && "border-destructive focus-within:ring-destructive/20",
                                    )}
                                >
                                    <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                        <Mail className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        ref={emailInputRef}
                                        id="email"
                                        type="email"
                                        autoComplete="email"
                                        placeholder="you@school.edu"
                                        required
                                        autoFocus
                                        value={data.email}
                                        onChange={(e) => {
                                            setData("email", e.target.value);
                                            setEmailLookupResult(null);
                                        }}
                                        onKeyDown={(e) => {
                                            if (e.key === "Enter") {
                                                e.preventDefault();
                                                handleEmailLookup();
                                            }
                                        }}
                                        className="text-sm font-normal"
                                    />
                                </InputGroup>
                                {errors.email && <FieldError errors={[{ message: errors.email }]} />}
                                <FieldDescription className="text-muted-foreground text-xs">
                                    We&apos;ll automatically detect whether you&apos;re a student or faculty member and match your school roster.
                                </FieldDescription>
                            </Field>

                            <Button
                                type="button"
                                onClick={handleEmailLookup}
                                disabled={isCheckingEmail || !validateEmail(data.email)}
                                className="h-11 w-full rounded-xl font-semibold shadow-md transition-all duration-200"
                            >
                                {isCheckingEmail ? (
                                    <>
                                        <Loader2 className="mr-2 size-4 animate-spin" />
                                        <span>Searching Academic Directory...</span>
                                    </>
                                ) : (
                                    <span className="inline-flex items-center gap-2">
                                        <span>Find My Record</span>
                                        <ArrowRight className="size-4" />
                                    </span>
                                )}
                            </Button>

                            {/* Recovery State A: Account Already Exists */}
                            {emailLookupResult && !emailLookupResult.found && emailLookupResult.account_exists && (
                                <div className="border-primary/30 bg-primary/5 space-y-3 rounded-2xl border p-4 text-xs shadow-2xs">
                                    <div className="flex items-start gap-3">
                                        <IconTile variant="soft" size="sm" className="text-primary shrink-0 rounded-lg">
                                            <KeyRound className="size-4" />
                                        </IconTile>
                                        <div className="space-y-1">
                                            <p className="text-foreground font-semibold">Account Already Active</p>
                                            <p className="text-muted-foreground leading-relaxed">
                                                A portal account for <span className="text-foreground font-medium">{data.email}</span> already exists.
                                                If your administrator provisioned it for you, use{" "}
                                                <span className="text-foreground font-medium">Reset Password</span> to set your login password.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Button
                                            render={<Link href="/login" />}
                                            nativeButton={false}
                                            size="sm"
                                            className="h-9 gap-1.5 rounded-xl text-xs font-semibold"
                                        >
                                            <LogIn className="size-3.5" />
                                            Sign In
                                        </Button>
                                        <Button
                                            render={<Link href="/forgot-password" />}
                                            nativeButton={false}
                                            variant="outline"
                                            size="sm"
                                            className="h-9 gap-1.5 rounded-xl text-xs font-semibold"
                                        >
                                            <KeyRound className="size-3.5" />
                                            Reset Password
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {/* Recovery State B: Email Not Found / Disabled / Inactive */}
                            {emailLookupResult && !emailLookupResult.found && !emailLookupResult.account_exists && (
                                <div className="border-destructive/30 bg-destructive/5 space-y-3 rounded-2xl border p-4 text-xs shadow-2xs">
                                    <div className="flex items-start gap-3">
                                        <IconTile variant="soft" size="sm" className="text-destructive shrink-0 rounded-lg">
                                            <ShieldAlert className="size-4" />
                                        </IconTile>
                                        <div className="space-y-1">
                                            <p className="text-foreground font-semibold">Unable to Continue Activation</p>
                                            <p className="text-muted-foreground leading-relaxed">
                                                {emailLookupResult.message || "This email is not yet linked to an active student or faculty record."}
                                            </p>
                                            <p className="text-muted-foreground text-[11px]">
                                                Tip: Check whether you gave a personal email or an institutional email during admissions or onboarding.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Button
                                            render={<Link href="/enrollment" />}
                                            nativeButton={false}
                                            variant="outline"
                                            size="sm"
                                            className="bg-background/80 h-8 gap-1.5 rounded-lg text-xs font-medium"
                                        >
                                            <ClipboardList className="size-3.5" />
                                            New Student? Apply for Enrollment
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setEmailLookupResult(null);
                                                emailInputRef.current?.focus();
                                                emailInputRef.current?.select();
                                            }}
                                            className="text-muted-foreground hover:text-foreground h-8 gap-1 rounded-lg px-2.5 text-xs"
                                        >
                                            <RefreshCw className="size-3" />
                                            Try another email
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {!socialiteSignup?.email && <SocialAuthButtons />}
                        </div>
                    )}

                    {/* Step 1: Personal Details & Password */}
                    {currentStep === 1 && (
                        <div className="space-y-4">
                            <Field>
                                <FieldLabel htmlFor="name" className="text-foreground text-xs font-semibold">
                                    Full Legal Name
                                </FieldLabel>
                                <InputGroup className="border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-primary/20 h-11 rounded-xl focus-within:ring-2">
                                    <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                        <User className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="name"
                                        type="text"
                                        autoComplete="name"
                                        required
                                        autoFocus
                                        value={data.name}
                                        onChange={(e) => setData("name", e.target.value)}
                                        placeholder="e.g. Alex Morgan"
                                        className="text-sm font-normal"
                                    />
                                </InputGroup>
                                {errors.name && <FieldError errors={[{ message: errors.name }]} />}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="password" className="text-foreground text-xs font-semibold">
                                    Create Your Portal Password
                                </FieldLabel>
                                <InputGroup className="border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-primary/20 h-11 rounded-xl focus-within:ring-2">
                                    <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                        <Lock className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="password"
                                        type={showPassword ? "text" : "password"}
                                        autoComplete="new-password"
                                        required
                                        value={data.password}
                                        onChange={(e) => setData("password", e.target.value)}
                                        placeholder={`At least ${minPasswordLength} characters`}
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
                                <PasswordRequirements password={data.password} policy={passwordPolicy} />
                                {errors.password && <FieldError errors={[{ message: errors.password }]} />}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="password_confirmation" className="text-foreground text-xs font-semibold">
                                    Confirm Password
                                </FieldLabel>
                                <InputGroup
                                    className={cn(
                                        "border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-primary/20 h-11 rounded-xl focus-within:ring-2",
                                        passwordsMatch && "border-success focus-within:ring-success/20",
                                    )}
                                >
                                    <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                        <Lock className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="password_confirmation"
                                        type={showConfirmPassword ? "text" : "password"}
                                        autoComplete="new-password"
                                        required
                                        value={data.password_confirmation}
                                        onChange={(e) => setData("password_confirmation", e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === "Enter" && canProceedFromDetails) {
                                                e.preventDefault();
                                                handleNext();
                                            }
                                        }}
                                        placeholder="Repeat your password"
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
                            </Field>
                        </div>
                    )}

                    {/* Step 2: Universal Student ID Verification (Zero manual dropdowns!) */}
                    {currentStep === 2 && userType === "student" && (
                        <div className="space-y-4">
                            <div className="border-primary/25 bg-primary/5 flex items-start gap-3 rounded-2xl border px-4 py-3 text-xs">
                                <span className="bg-primary/15 text-primary mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg">
                                    <Sparkles className="size-3.5" />
                                </span>
                                <div className="space-y-0.5">
                                    <p className="text-foreground font-semibold">Automatic record verification</p>
                                    <p className="text-muted-foreground leading-relaxed">
                                        {acceptsExternalIdentifier && externalIdentifierLabel ? (
                                            <>
                                                Enter your <span className="text-foreground font-medium">{studentIdPrimaryLabel}</span> or{" "}
                                                <span className="text-foreground font-medium">{externalIdentifierLabel}</span> below — we&apos;ll
                                                automatically match your student record and send your 6-digit code.
                                            </>
                                        ) : (
                                            <>
                                                Enter your <span className="text-foreground font-medium">{studentIdentifierLabel}</span> below —
                                                we&apos;ll automatically verify your record and send your 6-digit code.
                                            </>
                                        )}
                                    </p>
                                </div>
                            </div>

                            <Field>
                                <FieldLabel htmlFor="identifier" className="text-foreground text-xs font-semibold">
                                    {studentIdentifierLabel}
                                </FieldLabel>
                                <InputGroup
                                    className={cn(
                                        "border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-primary/20 h-11 rounded-xl focus-within:ring-2",
                                        identifierError && "border-destructive focus-within:ring-destructive/20",
                                    )}
                                >
                                    <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                        <BadgeCheck className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="identifier"
                                        autoFocus
                                        placeholder={studentIdentifierPlaceholder}
                                        value={data.identifier}
                                        onChange={(e) => setData("identifier", e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === "Enter" && canSendOtp) {
                                                e.preventDefault();
                                                sendOtp();
                                            }
                                        }}
                                        className="font-mono text-sm font-normal tabular-nums"
                                    />
                                </InputGroup>
                                {identifierError && <FieldError errors={[{ message: identifierError }]} />}
                                <FieldDescription className="text-muted-foreground text-xs">{studentIdentifierHelpText}</FieldDescription>
                            </Field>

                            {allowIdentifierRecovery && (
                                <div className="border-border/70 bg-muted/25 flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3.5 py-2.5 text-xs">
                                    <span className="text-muted-foreground">Don&apos;t remember your ID?</span>
                                    <button
                                        type="button"
                                        onClick={handleRecoverIdentifier}
                                        disabled={isRecoveringIdentifier || identifierRecoveryCooldown > 0 || !validateEmail(data.email)}
                                        className="text-primary font-medium underline-offset-4 hover:underline disabled:no-underline disabled:opacity-50"
                                    >
                                        {isRecoveringIdentifier
                                            ? "Sending..."
                                            : identifierRecoveryCooldown > 0
                                              ? `Email sent · retry in ${identifierRecoveryCooldown}s`
                                              : `Email my ${studentIdentifierLabel}`}
                                    </button>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 2: Faculty ID Verification */}
                    {currentStep === 2 && userType === "faculty" && (
                        <div className="space-y-4">
                            <div className="border-primary/25 bg-primary/5 rounded-2xl border p-3.5 text-xs leading-relaxed">
                                <p className="text-foreground font-semibold">
                                    {requiresFacultyId
                                        ? `${facultyIdLabel} required to verify your record`
                                        : `Faculty record uniquely matched — ${facultyIdLabel} is optional`}
                                </p>
                                <p className="text-muted-foreground mt-0.5">
                                    {requiresFacultyId
                                        ? `Enter your institutional ${facultyIdLabel} so we can confirm and link the right roster record.`
                                        : `You can send your email verification code right away, or enter your ${facultyIdLabel} below if your school issued one.`}
                                </p>
                            </div>

                            <Field>
                                <FieldLabel htmlFor="faculty_id_number" className="text-foreground text-xs font-semibold">
                                    {facultyIdLabel} {requiresFacultyId ? "(Required)" : "(Optional)"}
                                </FieldLabel>
                                <InputGroup
                                    className={cn(
                                        "border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-primary/20 h-11 rounded-xl focus-within:ring-2",
                                        errors.faculty_id_number && "border-destructive focus-within:ring-destructive/20",
                                    )}
                                >
                                    <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                        <BadgeCheck className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="faculty_id_number"
                                        autoFocus
                                        placeholder={facultyIdPlaceholder}
                                        required={requiresFacultyId}
                                        value={data.faculty_id_number}
                                        onChange={(e) => setData("faculty_id_number", e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === "Enter" && canSendOtp) {
                                                e.preventDefault();
                                                sendOtp();
                                            }
                                        }}
                                        className="font-mono text-sm font-normal uppercase placeholder:normal-case"
                                    />
                                </InputGroup>
                                {errors.faculty_id_number && <FieldError errors={[{ message: errors.faculty_id_number }]} />}
                                <FieldDescription className="text-muted-foreground text-xs">{facultyIdHelpText}</FieldDescription>
                            </Field>
                        </div>
                    )}

                    {/* Step Last: OTP Code */}
                    {currentStep === steps.length - 1 && (
                        <div className="space-y-4">
                            <div className="border-primary/20 bg-primary/5 flex items-start gap-3 rounded-2xl border p-4 text-xs">
                                <IconTile variant="soft" size="sm" className="text-primary shrink-0 rounded-lg">
                                    <Mail className="size-4" />
                                </IconTile>
                                <div className="space-y-1">
                                    <p className="text-foreground font-semibold">Verification Code Sent</p>
                                    <p className="text-muted-foreground">
                                        Enter the 6-digit code sent to <span className="text-foreground font-semibold">{data.email}</span>.
                                    </p>
                                    <p className="text-muted-foreground text-[11px]">
                                        Don&apos;t see it? Check your spam or promotions folder — codes arrive within a minute.
                                    </p>
                                </div>
                            </div>

                            <Field>
                                <FieldLabel htmlFor="otp" className="text-foreground text-xs font-semibold">
                                    6-Digit Verification Code
                                </FieldLabel>
                                <div className="flex justify-center pt-1">
                                    <InputOTP
                                        id="otp"
                                        maxLength={6}
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        autoFocus
                                        value={data.otp}
                                        onChange={(value) => setData("otp", value.replace(/\D/g, "").slice(0, 6))}
                                    >
                                        <InputOTPGroup>
                                            <InputOTPSlot index={0} className="h-12 w-11 font-mono text-base font-semibold" />
                                            <InputOTPSlot index={1} className="h-12 w-11 font-mono text-base font-semibold" />
                                            <InputOTPSlot index={2} className="h-12 w-11 font-mono text-base font-semibold" />
                                        </InputOTPGroup>
                                        <InputOTPSeparator />
                                        <InputOTPGroup>
                                            <InputOTPSlot index={3} className="h-12 w-11 font-mono text-base font-semibold" />
                                            <InputOTPSlot index={4} className="h-12 w-11 font-mono text-base font-semibold" />
                                            <InputOTPSlot index={5} className="h-12 w-11 font-mono text-base font-semibold" />
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                {errors.otp && <FieldError errors={[{ message: errors.otp }]} />}
                                <div className="flex items-center justify-between pt-2 text-xs">
                                    <span className="text-muted-foreground text-[11px]">Code expires in 10 minutes</span>
                                    <button
                                        type="button"
                                        onClick={sendOtp}
                                        disabled={isSendingOtp || otpCooldown > 0}
                                        className="text-primary font-medium hover:underline disabled:no-underline disabled:opacity-50"
                                    >
                                        {isSendingOtp ? "Sending..." : otpCooldown > 0 ? `Resend in ${otpCooldown}s` : "Resend verification code"}
                                    </button>
                                </div>
                            </Field>
                        </div>
                    )}

                    {/* Navigation Buttons */}
                    {currentStep > 0 && (
                        <div className="flex items-center gap-3 pt-1">
                            <Button type="button" variant="outline" onClick={handlePrev} className="h-11 rounded-xl px-4 font-medium">
                                <ArrowLeft className="mr-1.5 size-4" />
                                <span>Back</span>
                            </Button>

                            {currentStep < steps.length - 2 ? (
                                <Button
                                    type="button"
                                    onClick={handleNext}
                                    disabled={currentStep === 1 && !canProceedFromDetails}
                                    className="h-11 flex-1 rounded-xl font-semibold shadow-md"
                                >
                                    <span>Continue</span>
                                    <ArrowRight className="ml-1.5 size-4" />
                                </Button>
                            ) : currentStep === steps.length - 2 ? (
                                <Button
                                    type="button"
                                    onClick={sendOtp}
                                    disabled={!canSendOtp}
                                    className="h-11 flex-1 rounded-xl font-semibold shadow-md"
                                >
                                    {isSendingOtp ? (
                                        <>
                                            <Loader2 className="mr-2 size-4 animate-spin" />
                                            <span>Sending Code...</span>
                                        </>
                                    ) : (
                                        <span>Send Verification Code</span>
                                    )}
                                </Button>
                            ) : (
                                <Button
                                    type="submit"
                                    disabled={processing || !data.otp || data.otp.length < 6}
                                    className="h-11 flex-1 rounded-xl font-semibold shadow-md"
                                >
                                    {processing ? (
                                        <>
                                            <Loader2 className="mr-2 size-4 animate-spin" />
                                            <span>Activating Account...</span>
                                        </>
                                    ) : (
                                        "Confirm & Activate Account"
                                    )}
                                </Button>
                            )}
                        </div>
                    )}

                    <div className="text-muted-foreground space-y-1.5 pt-2 text-center text-xs">
                        <p>
                            Already have an active portal account?{" "}
                            <Link href="/login" className="text-primary font-semibold underline-offset-4 hover:underline">
                                Sign in here
                            </Link>
                        </p>
                        {currentStep === 0 && (
                            <p className="lg:hidden">
                                New student not enrolled yet?{" "}
                                <Link href="/enrollment" className="text-primary font-semibold underline-offset-4 hover:underline">
                                    Apply for enrollment
                                </Link>
                            </p>
                        )}
                    </div>
                </FieldGroup>
            </form>
        </div>
    );
}
