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
    SlidersHorizontal,
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";

type UserType = "faculty" | "student" | null;
type StudentType = "college" | "shs" | "tesda" | "dhrt" | null;

interface EmailLookupResult {
    found: boolean;
    type?: "faculty" | "student";
    message?: string;
    account_exists?: boolean;
}

const STORAGE_KEY = "signup_form_state";

const STUDENT_TYPE_LABELS: Record<Exclude<StudentType, null>, string> = {
    college: "College",
    shs: "Senior High School",
    tesda: "TESDA",
    dhrt: "DHRT",
};

interface PersistedState {
    currentStep: number;
    userType: UserType;
    studentType: StudentType;
    identifierField?: "lrn" | "student_id";
    name: string;
    email: string;
    faculty_id_number: string;
    student_id: string;
    lrn: string;
    user_type: "" | "faculty" | "student";
    student_type: "" | "college" | "shs" | "tesda" | "dhrt";
}

interface SocialiteSignup {
    name?: string | null;
    email?: string | null;
    avatar_url?: string | null;
    provider?: string | null;
}

function loadPersistedState(): PersistedState | null {
    try {
        // Clear any legacy localStorage entry that may have stored plaintext passwords.
        if (typeof localStorage !== "undefined" && localStorage.getItem(STORAGE_KEY)) {
            localStorage.removeItem(STORAGE_KEY);
        }
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as Partial<PersistedState> & { password?: unknown; password_confirmation?: unknown };
        delete parsed.password;
        delete parsed.password_confirmation;
        return parsed as PersistedState;
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
    ...props
}: React.ComponentProps<"div"> & {
    socialiteSignup?: SocialiteSignup | null;
}) {
    const { passwordPolicy } = usePage<{ passwordPolicy?: Partial<PasswordPolicy> }>().props;
    const saved = useRef(socialiteSignup?.email ? null : loadPersistedState());
    const emailInputRef = useRef<HTMLInputElement | null>(null);

    // Cap restored step at Step 1 (Personal & Password) because passwords are never persisted.
    const [currentStep, setCurrentStep] = useState(Math.min(saved.current?.currentStep ?? 0, 1));
    const [isCheckingEmail, setIsCheckingEmail] = useState(false);
    const [isSendingOtp, setIsSendingOtp] = useState(false);
    const [otpCooldown, setOtpCooldown] = useState(0);
    const [emailLookupResult, setEmailLookupResult] = useState<EmailLookupResult | null>(null);
    const [userType, setUserType] = useState<UserType>(saved.current?.userType ?? null);
    const initialIdentifierField: "lrn" | "student_id" = saved.current?.identifierField ?? (saved.current?.lrn ? "lrn" : "student_id");
    const [identifierField, setIdentifierField] = useState<"lrn" | "student_id">(initialIdentifierField);
    const usesExternalIdentifier = identifierField === "lrn";
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [showManualStudentOverrides, setShowManualStudentOverrides] = useState(false);

    const initialStudentType = (saved.current?.student_type || saved.current?.studentType || "") as "" | "college" | "shs" | "tesda" | "dhrt";

    const { data, setData, post, processing, errors } = useForm({
        name: saved.current?.name ?? socialiteSignup?.name ?? "",
        email: saved.current?.email ?? socialiteSignup?.email ?? "",
        password: "",
        password_confirmation: "",
        faculty_id_number: saved.current?.faculty_id_number ?? "",
        student_id: saved.current?.student_id ?? "",
        lrn: saved.current?.lrn ?? "",
        otp: "",
        user_type: saved.current?.user_type ?? ("" as "faculty" | "student" | ""),
        student_type: initialStudentType,
    });

    const studentType = (data.student_type || null) as StudentType;

    const isRestored = useRef(saved.current !== null);

    useEffect(() => {
        if (otpCooldown <= 0) return;
        const timer = setInterval(() => {
            setOtpCooldown((prev) => prev - 1);
        }, 1000);
        return () => clearInterval(timer);
    }, [otpCooldown]);

    const persistFormState = useCallback(() => {
        // Never persist passwords or OTPs to browser storage.
        persistState({
            currentStep: currentStep === 3 ? 2 : currentStep,
            userType,
            studentType,
            identifierField,
            name: data.name,
            email: data.email,
            faculty_id_number: data.faculty_id_number,
            student_id: data.student_id,
            lrn: data.lrn,
            user_type: data.user_type,
            student_type: data.student_type,
        });
    }, [
        currentStep,
        userType,
        studentType,
        identifierField,
        data.name,
        data.email,
        data.faculty_id_number,
        data.student_id,
        data.lrn,
        data.user_type,
        data.student_type,
    ]);

    useEffect(() => {
        persistFormState();
    }, [persistFormState]);

    useEffect(() => {
        if (isRestored.current && emailLookupResult === null) {
            setEmailLookupResult(saved.current?.email?.length ? { found: true, type: saved.current.userType ?? undefined } : null);
        }
        isRestored.current = false;
    }, []);

    useEffect(() => {
        if (errors && Object.keys(errors).length > 0) {
            const firstErrorKey = Object.keys(errors)[0] as keyof typeof errors;
            const firstErrorMessage = errors[firstErrorKey];
            if (firstErrorMessage) {
                toast.error(firstErrorMessage);
            }
            if (errors.student_id || errors.lrn || errors.student_type || errors.faculty_id_number) {
                setCurrentStep(2);
                if (errors.student_type) {
                    setShowManualStudentOverrides(true);
                }
            } else if (errors.email) {
                setCurrentStep(0);
            } else if (errors.name || errors.password) {
                setCurrentStep(1);
            }
        }
    }, [errors]);

    const validateEmail = (email: string) => {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    };

    const passwordsMatch = data.password === data.password_confirmation && data.password_confirmation !== "";
    const policyEvaluation = evaluatePasswordPolicy(data.password, passwordPolicy);
    const minPasswordLength = passwordPolicy?.minLength ?? 8;

    const getSteps = () => {
        if (userType === "student") {
            return [
                { id: "email", label: "Find Record" },
                { id: "details", label: "Credentials" },
                { id: "verification", label: "Student ID" },
                { id: "otp", label: "Email Code" },
            ];
        }
        if (userType === "faculty") {
            return [
                { id: "email", label: "Find Record" },
                { id: "details", label: "Credentials" },
                { id: "verification", label: "Faculty ID" },
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

    const [lookupHints, setLookupHints] = useState<{
        student_type?: string | null;
        identifier_type?: "student_id" | "lrn";
        multiple_schools?: boolean;
        requires_faculty_id?: boolean;
        has_faculty_id?: boolean;
    } | null>(null);

    const canSendOtp =
        !isSendingOtp &&
        (userType === "student"
            ? Boolean(data.student_type && (usesExternalIdentifier ? data.lrn.trim() : data.student_id.trim()))
            : !lookupHints?.requires_faculty_id || Boolean(data.faculty_id_number.trim()));

    const sendOtp = async () => {
        if (!canSendOtp) return;
        setIsSendingOtp(true);
        try {
            await axios.post("/signup/send-otp", {
                email: data.email,
                user_type: data.user_type,
                student_type: data.student_type,
                student_id: data.student_id,
                lrn: data.lrn,
                faculty_id_number: data.faculty_id_number,
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

    const handleEmailLookup = async () => {
        if (!validateEmail(data.email)) {
            toast.error("Please enter a valid email address.");
            return;
        }

        setIsCheckingEmail(true);
        try {
            const response = await axios.post("/signup/email-lookup", { email: data.email, include_hints: true });
            const result = response.data as EmailLookupResult & {
                hints?: {
                    student_type?: string | null;
                    identifier_type?: "student_id" | "lrn";
                    multiple_schools?: boolean;
                    requires_faculty_id?: boolean;
                    has_faculty_id?: boolean;
                };
            };
            setEmailLookupResult(result);

            if (result.found && (result.type === "faculty" || result.type === "student")) {
                setUserType(result.type);
                setLookupHints(result.hints ?? null);
                const detectedIdentifier = result.hints?.identifier_type === "lrn" ? "lrn" : "student_id";
                const detectedStudentType = (result.hints?.student_type ?? "") as Exclude<StudentType, null> | "";
                setIdentifierField(detectedIdentifier);
                setShowManualStudentOverrides(!detectedStudentType);
                setData((prev) => ({
                    ...prev,
                    faculty_id_number: "",
                    student_id: "",
                    lrn: "",
                    user_type: result.type!,
                    student_type: result.type === "student" ? detectedStudentType : "",
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
        setShowManualStudentOverrides(false);
        setData((prev) => ({
            ...prev,
            user_type: "",
            student_type: "",
            student_id: "",
            lrn: "",
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
    const matchedStudentTypeLabel = data.student_type ? STUDENT_TYPE_LABELS[data.student_type] : null;
    const isAutoMatchedStudent = Boolean(userType === "student" && lookupHints?.student_type && matchedStudentTypeLabel);

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
                                <div className="flex items-center gap-1 text-[11px]">
                                    <span
                                        className={cn(
                                            "flex size-4 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold transition-colors",
                                            isCompleted
                                                ? "bg-primary text-primary-foreground"
                                                : isActive
                                                  ? "border border-primary/50 bg-primary/10 text-primary"
                                                  : "bg-muted text-muted-foreground",
                                        )}
                                    >
                                        {isCompleted ? <Check className="size-2.5" /> : idx + 1}
                                    </span>
                                    <span
                                        className={cn(
                                            "truncate",
                                            isActive ? "font-semibold text-foreground" : isCompleted ? "text-foreground/80" : "text-muted-foreground",
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
                <div className="flex items-center justify-between gap-3 rounded-xl border border-primary/20 bg-primary/5 px-3.5 py-2.5 text-xs">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary">
                            {userType === "student" ? <GraduationCap className="size-4" /> : <UserCheck className="size-4" />}
                        </span>
                        <div className="min-w-0">
                            <p className="font-semibold text-foreground">
                                {userType === "student" ? "Student Record Matched" : "Faculty Record Matched"}
                            </p>
                            <p className="truncate font-mono text-[11px] text-muted-foreground">{data.email}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={handleChangeEmail}
                        className="shrink-0 rounded-lg border border-border/70 bg-background/80 px-2.5 py-1 text-[11px] font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground"
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
                                <div className="flex items-center gap-3 rounded-xl border border-primary/25 bg-primary/5 p-3.5 text-xs">
                                    {socialiteSignup.avatar_url ? (
                                        <img
                                            src={socialiteSignup.avatar_url}
                                            alt=""
                                            className="size-8 shrink-0 rounded-full border border-border object-cover"
                                        />
                                    ) : (
                                        <IconTile variant="soft" size="sm" className="shrink-0 rounded-lg">
                                            <User className="size-4" />
                                        </IconTile>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="font-semibold text-foreground">
                                            Completing signup with{" "}
                                            {socialiteSignup.provider
                                                ? socialiteSignup.provider.charAt(0).toUpperCase() + socialiteSignup.provider.slice(1)
                                                : "Social Account"}
                                        </p>
                                        <p className="truncate text-muted-foreground">{socialiteSignup.email}</p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 shrink-0 px-2 text-xs text-muted-foreground hover:text-foreground"
                                        onClick={() => {
                                            clearPersistedState();
                                            router.post("/signup/cancel-social");
                                        }}
                                    >
                                        Switch
                                    </Button>
                                </div>
                            )}

                            {/* Compact Self-Activation Track Summary (especially helpful on mobile where right panel is hidden) */}
                            <div className="grid grid-cols-2 gap-2 text-[11px]">
                                <div className="flex items-center gap-2 rounded-xl border border-border/70 bg-card/60 px-3 py-2">
                                    <GraduationCap className="size-3.5 shrink-0 text-primary" />
                                    <div className="min-w-0">
                                        <p className="font-semibold text-foreground">Students</p>
                                        <p className="truncate text-muted-foreground">Email + ID / LRN</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 rounded-xl border border-border/70 bg-card/60 px-3 py-2">
                                    <UserCheck className="size-3.5 shrink-0 text-primary" />
                                    <div className="min-w-0">
                                        <p className="font-semibold text-foreground">Faculty</p>
                                        <p className="truncate text-muted-foreground">School email</p>
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
                                    We&apos;ll automatically detect whether you&apos;re a student or faculty member and guide you through activation.
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

                            {/* Recovery State A: Account Already Exists (e.g., admin provisioned or prior signup) */}
                            {emailLookupResult && !emailLookupResult.found && emailLookupResult.account_exists && (
                                <div className="space-y-3 rounded-2xl border border-primary/30 bg-primary/5 p-4 text-xs shadow-2xs">
                                    <div className="flex items-start gap-3">
                                        <IconTile variant="soft" size="sm" className="shrink-0 rounded-lg text-primary">
                                            <KeyRound className="size-4" />
                                        </IconTile>
                                        <div className="space-y-1">
                                            <p className="font-semibold text-foreground">Account Already Active</p>
                                            <p className="leading-relaxed text-muted-foreground">
                                                A portal account for <span className="font-medium text-foreground">{data.email}</span> already exists.
                                                If your administrator provisioned it for you, use <span className="font-medium text-foreground">Reset Password</span> to set your login password.
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

                            {/* Recovery State B: Email Not Found / Inactive Record */}
                            {emailLookupResult && !emailLookupResult.found && !emailLookupResult.account_exists && (
                                <div className="space-y-3 rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-xs shadow-2xs">
                                    <div className="flex items-start gap-3">
                                        <IconTile variant="soft" size="sm" className="shrink-0 rounded-lg text-destructive">
                                            <ShieldAlert className="size-4" />
                                        </IconTile>
                                        <div className="space-y-1">
                                            <p className="font-semibold text-foreground">We Couldn&apos;t Find That Email on the Roster</p>
                                            <p className="leading-relaxed text-muted-foreground">
                                                {emailLookupResult.message || "This email is not yet linked to an active student or faculty record."}
                                            </p>
                                            <p className="text-[11px] text-muted-foreground">
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
                                            className="h-8 gap-1.5 rounded-lg bg-background/80 text-xs font-medium"
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
                                            className="h-8 gap-1 rounded-lg px-2.5 text-xs text-muted-foreground hover:text-foreground"
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
                                        placeholder="e.g. Juan Dela Cruz"
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

                    {/* Step 2: Student ID Verification (Student) */}
                    {currentStep === 2 && userType === "student" && (
                        <div className="space-y-4">
                            {isAutoMatchedStudent ? (
                                <div className="flex items-center justify-between gap-3 rounded-2xl border border-primary/25 bg-primary/5 px-4 py-3 text-xs">
                                    <div className="space-y-0.5">
                                        <p className="font-semibold text-foreground">
                                            Auto-detected: {matchedStudentTypeLabel} ·{" "}
                                            {usesExternalIdentifier ? "LRN / External ID" : "Student Number"}
                                        </p>
                                        <p className="text-muted-foreground">
                                            Enter your {usesExternalIdentifier ? "LRN" : "student number"} below to receive your 6-digit verification code.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setShowManualStudentOverrides((prev) => !prev)}
                                        className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-border/70 bg-background/80 px-2.5 py-1 text-[11px] font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground"
                                    >
                                        <SlidersHorizontal className="size-3" />
                                        {showManualStudentOverrides ? "Hide" : "Change"}
                                    </button>
                                </div>
                            ) : (
                                <div className="border-primary/25 bg-primary/5 text-foreground rounded-2xl border p-3.5 text-xs leading-relaxed">
                                    <p className="text-muted-foreground">
                                        Select your student type and enter the identifier issued by your school to verify your account.
                                    </p>
                                </div>
                            )}

                            {(!isAutoMatchedStudent || showManualStudentOverrides) && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Field>
                                        <FieldLabel htmlFor="student_type" className="text-foreground text-xs font-semibold">
                                            Student Type
                                        </FieldLabel>
                                        <Select
                                            value={data.student_type}
                                            onValueChange={(value) => {
                                                const category = value as Exclude<StudentType, null>;
                                                setData((prev) => ({ ...prev, student_type: category }));
                                            }}
                                        >
                                            <SelectTrigger id="student_type">
                                                <SelectValue placeholder="Select student type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="college">College</SelectItem>
                                                <SelectItem value="shs">Senior High School</SelectItem>
                                                <SelectItem value="tesda">TESDA</SelectItem>
                                                <SelectItem value="dhrt">DHRT</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.student_type && <FieldError errors={[{ message: errors.student_type }]} />}
                                    </Field>

                                    <Field>
                                        <FieldLabel htmlFor="identifier_type" className="text-foreground text-xs font-semibold">
                                            Identifier Type
                                        </FieldLabel>
                                        <Select
                                            value={identifierField}
                                            onValueChange={(value) => {
                                                setIdentifierField(value as "student_id" | "lrn");
                                                setData((prev) => ({ ...prev, student_id: "", lrn: "" }));
                                            }}
                                        >
                                            <SelectTrigger id="identifier_type">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="student_id">Student number</SelectItem>
                                                <SelectItem value="lrn">LRN / External identifier</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                </div>
                            )}

                            {usesExternalIdentifier ? (
                                <Field>
                                    <FieldLabel htmlFor="lrn" className="text-foreground text-xs font-semibold">
                                        LRN / External Identifier
                                    </FieldLabel>
                                    <InputGroup
                                        className={cn(
                                            "border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-primary/20 h-11 rounded-xl focus-within:ring-2",
                                            errors.lrn && "border-destructive focus-within:ring-destructive/20",
                                        )}
                                    >
                                        <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                            <BadgeCheck className="size-4" />
                                        </InputGroupAddon>
                                        <InputGroupInput
                                            id="lrn"
                                            autoFocus
                                            placeholder="123456789012"
                                            value={data.lrn}
                                            onChange={(e) => setData("lrn", e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === "Enter" && canSendOtp) {
                                                    e.preventDefault();
                                                    sendOtp();
                                                }
                                            }}
                                            className="font-mono text-sm font-normal tabular-nums"
                                        />
                                    </InputGroup>
                                    {errors.lrn && <FieldError errors={[{ message: errors.lrn }]} />}
                                    <FieldDescription className="text-muted-foreground text-xs">
                                        Found on your DepEd Form 138, registration slip, or official admission notice.
                                    </FieldDescription>
                                </Field>
                            ) : (
                                <Field>
                                    <FieldLabel htmlFor="student_id" className="text-foreground text-xs font-semibold">
                                        Student Number
                                    </FieldLabel>
                                    <InputGroup
                                        className={cn(
                                            "border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-primary/20 h-11 rounded-xl focus-within:ring-2",
                                            errors.student_id && "border-destructive focus-within:ring-destructive/20",
                                        )}
                                    >
                                        <InputGroupAddon align="inline-start" className="text-muted-foreground/70 pl-3">
                                            <GraduationCap className="size-4" />
                                        </InputGroupAddon>
                                        <InputGroupInput
                                            id="student_id"
                                            autoFocus
                                            placeholder="20260001"
                                            value={data.student_id}
                                            onChange={(e) => setData("student_id", e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === "Enter" && canSendOtp) {
                                                    e.preventDefault();
                                                    sendOtp();
                                                }
                                            }}
                                            className="font-mono text-sm font-normal tabular-nums"
                                        />
                                    </InputGroup>
                                    {errors.student_id && <FieldError errors={[{ message: errors.student_id }]} />}
                                    <FieldDescription className="text-muted-foreground text-xs">
                                        Found on your Certificate of Registration (COR), school ID, or registrar approval email.
                                    </FieldDescription>
                                </Field>
                            )}
                        </div>
                    )}

                    {/* Step 2: Faculty ID */}
                    {currentStep === 2 && userType === "faculty" && (
                        <div className="space-y-4">
                            <div className="rounded-2xl border border-primary/25 bg-primary/5 p-3.5 text-xs leading-relaxed">
                                <p className="font-semibold text-foreground">
                                    {lookupHints?.requires_faculty_id
                                        ? "Multi-campus match — Faculty ID required"
                                        : "Faculty record uniquely matched — Faculty ID is optional"}
                                </p>
                                <p className="mt-0.5 text-muted-foreground">
                                    {lookupHints?.requires_faculty_id
                                        ? "Your email appears in more than one school. Enter your institutional Faculty ID Number so we link the right roster."
                                        : "You can send your email verification code right away, or enter your Faculty ID Number below if HR issued one."}
                                </p>
                            </div>

                            <Field>
                                <FieldLabel htmlFor="faculty_id_number" className="text-foreground text-xs font-semibold">
                                    Faculty ID Number {lookupHints?.requires_faculty_id ? "(Required)" : "(Optional)"}
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
                                        placeholder="e.g. FAC-2025-001"
                                        required={Boolean(lookupHints?.requires_faculty_id)}
                                        value={data.faculty_id_number}
                                        onChange={(e) => setData("faculty_id_number", e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === "Enter" && canSendOtp) {
                                                e.preventDefault();
                                                sendOtp();
                                            }
                                        }}
                                        className="font-mono text-sm font-normal uppercase"
                                    />
                                </InputGroup>
                                {errors.faculty_id_number && <FieldError errors={[{ message: errors.faculty_id_number }]} />}
                            </Field>
                        </div>
                    )}

                    {/* Step Last: OTP Code */}
                    {currentStep === steps.length - 1 && (
                        <div className="space-y-4">
                            <div className="border-primary/20 bg-primary/5 flex items-start gap-3 rounded-2xl border p-4 text-xs">
                                <IconTile variant="soft" size="sm" className="shrink-0 rounded-lg text-primary">
                                    <Mail className="size-4" />
                                </IconTile>
                                <div className="space-y-1">
                                    <p className="text-foreground font-semibold">Verification Code Sent</p>
                                    <p className="text-muted-foreground">
                                        Enter the 6-digit code sent to <span className="text-foreground font-semibold">{data.email}</span>.
                                    </p>
                                    <p className="text-[11px] text-muted-foreground">
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
                                    ) : userType === "faculty" && !lookupHints?.requires_faculty_id && !data.faculty_id_number.trim() ? (
                                        <span>Skip ID &amp; Send Code</span>
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
