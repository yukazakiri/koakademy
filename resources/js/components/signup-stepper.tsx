import { Link, useForm } from "@inertiajs/react";
import axios from "axios";
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Check,
    Eye,
    EyeOff,
    GraduationCap,
    KeyRound,
    Loader2,
    Lock,
    Mail,
    ShieldAlert,
    User,
    UserCheck,
} from "lucide-react";
import { useCallback, useEffect, useRef, useState, type FormEvent } from "react";
import { toast } from "sonner";

import { IconTile } from "@/components/reui/icon-tile";
import { SocialAuthButtons } from "@/components/social-auth-buttons";
import { Button } from "@/components/ui/button";
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from "@/components/ui/input-group";
import { cn } from "@/lib/utils";

type UserType = "faculty" | "student" | null;
type StudentType = "college" | "shs" | null;

interface EmailLookupResult {
    found: boolean;
    type?: "faculty" | "student";
    name?: string;
    faculty_id_number?: string;
    department?: string;
    student_type?: string;
    is_shs?: boolean;
    student_id?: number;
    lrn?: string;
    course?: string;
    academic_year?: number;
    record_id?: string | number;
    message?: string;
}

const STORAGE_KEY = "signup_form_state";

interface PersistedState {
    currentStep: number;
    userType: UserType;
    studentType: StudentType;
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    faculty_id_number: string;
    student_id: string;
    lrn: string;
    role: string;
    user_type: "" | "faculty" | "student";
    student_type: "" | "college" | "shs";
    record_id: string | number;
}

interface SocialiteSignup {
    name?: string | null;
    email?: string | null;
    avatar_url?: string | null;
    provider?: string | null;
}

function loadPersistedState(): PersistedState | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function persistState(state: PersistedState): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        return;
    }
}

function clearPersistedState(): void {
    try {
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
    const saved = useRef(socialiteSignup?.email ? null : loadPersistedState());

    const [currentStep, setCurrentStep] = useState(saved.current?.currentStep ?? 0);
    const [isCheckingEmail, setIsCheckingEmail] = useState(false);
    const [emailLookupResult, setEmailLookupResult] = useState<EmailLookupResult | null>(null);
    const [userType, setUserType] = useState<UserType>(saved.current?.userType ?? null);
    const [studentType, setStudentType] = useState<StudentType>(saved.current?.studentType ?? null);
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: saved.current?.name ?? socialiteSignup?.name ?? "",
        email: saved.current?.email ?? socialiteSignup?.email ?? "",
        password: saved.current?.password ?? "",
        password_confirmation: saved.current?.password_confirmation ?? "",
        faculty_id_number: saved.current?.faculty_id_number ?? "",
        student_id: saved.current?.student_id ?? "",
        lrn: saved.current?.lrn ?? "",
        role: saved.current?.role ?? "",
        otp: "",
        user_type: saved.current?.user_type ?? ("" as "faculty" | "student" | ""),
        student_type: saved.current?.student_type ?? ("" as "college" | "shs" | ""),
        record_id: saved.current?.record_id ?? ("" as string | number),
    });

    const isRestored = useRef(saved.current !== null);

    const persistFormState = useCallback(() => {
        persistState({
            currentStep,
            userType,
            studentType,
            name: data.name,
            email: data.email,
            password: data.password,
            password_confirmation: data.password_confirmation,
            faculty_id_number: data.faculty_id_number,
            student_id: data.student_id,
            lrn: data.lrn,
            role: data.role,
            user_type: data.user_type,
            student_type: data.student_type,
            record_id: data.record_id,
        });
    }, [currentStep, userType, studentType, data]);

    useEffect(() => {
        persistFormState();
    }, [persistFormState]);

    useEffect(() => {
        if (isRestored.current && emailLookupResult === null) {
            setEmailLookupResult(
                saved.current?.email?.length
                    ? { found: true, type: saved.current.userType ?? undefined, name: saved.current.name || undefined }
                    : null
            );
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
        }
    }, [errors]);

    const validateEmail = (email: string) => {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    };

    const passwordsMatch = data.password === data.password_confirmation && data.password_confirmation !== "";

    const getPasswordStrength = (password: string) => {
        if (!password) return { strength: 0, label: "" };
        let strength = 0;
        if (password.length >= 8) strength += 1;
        if (/[A-Z]/.test(password)) strength += 1;
        if (/[a-z]/.test(password)) strength += 1;
        if (/[0-9]/.test(password)) strength += 1;
        if (/[^A-Za-z0-9]/.test(password)) strength += 1;

        if (strength <= 2) return { strength, label: "Weak", color: "bg-destructive" };
        if (strength <= 3) return { strength, label: "Medium", color: "bg-warning" };
        if (strength <= 4) return { strength, label: "Strong", color: "bg-success" };
        return { strength, label: "Very Strong", color: "bg-success" };
    };

    const passwordStrength = getPasswordStrength(data.password);

    const getSteps = () => {
        if (userType === "student") {
            return [
                { id: "email", label: "Email" },
                { id: "details", label: "Personal" },
                { id: "verification", label: "Student ID" },
                { id: "otp", label: "Confirm" },
            ];
        }
        return [
            { id: "email", label: "Email" },
            { id: "details", label: "Personal" },
            { id: "role", label: "Position" },
            { id: "verification", label: "Faculty ID" },
            { id: "otp", label: "Confirm" },
        ];
    };

    const steps = getSteps();

    const facultyRoles = [
        { value: "professor", label: "Professor", description: "Full Professor rank" },
        { value: "associate_professor", label: "Associate Professor", description: "Associate Professor rank" },
        { value: "assistant_professor", label: "Assistant Professor", description: "Assistant Professor rank" },
        { value: "instructor", label: "Instructor", description: "Teaching Instructor" },
        { value: "part_time_faculty", label: "Part-time Faculty", description: "Part-time teaching appointment" },
    ];

    const sendOtp = async () => {
        try {
            await axios.post("/signup/send-otp", {
                email: data.email,
                user_type: data.user_type,
                student_type: data.student_type,
                record_id: data.record_id,
                student_id: data.student_id,
                lrn: data.lrn,
                role: data.role,
                faculty_id_number: data.faculty_id_number,
            });
            toast.success("Verification code sent to your email.");
            setCurrentStep(steps.length - 1);
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
            const errorMessage =
                err.response?.data?.message ||
                (err.response?.data?.errors && Object.values(err.response.data.errors)[0]?.[0]) ||
                "Failed to send verification code. Please try again.";
            toast.error(errorMessage);
        }
    };

    const handleEmailLookup = async () => {
        if (!validateEmail(data.email)) {
            toast.error("Please enter a valid email address.");
            return;
        }

        setIsCheckingEmail(true);
        try {
            const response = await axios.post("/signup/email-lookup", { email: data.email });
            const result: EmailLookupResult = response.data;
            setEmailLookupResult(result);

            if (result.found) {
                if (result.type === "faculty") {
                    setUserType("faculty");
                    setData((prev) => ({
                        ...prev,
                        name: result.name || prev.name,
                        faculty_id_number: result.faculty_id_number || prev.faculty_id_number,
                        user_type: "faculty",
                        record_id: result.record_id || "",
                    }));
                    toast.success("Faculty record found!");
                } else if (result.type === "student") {
                    const isShs = result.student_type === "shs" || result.is_shs;
                    setUserType("student");
                    setStudentType(isShs ? "shs" : "college");
                    setData((prev) => ({
                        ...prev,
                        name: result.name || prev.name,
                        student_id: result.student_id ? String(result.student_id) : prev.student_id,
                        lrn: result.lrn || prev.lrn,
                        user_type: "student",
                        student_type: isShs ? "shs" : "college",
                        record_id: result.record_id || "",
                    }));
                    toast.success("Student record found!");
                }
                setCurrentStep(1);
            } else {
                toast.error(result.message || "Email address is not associated with an existing academic record.");
            }
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || "Failed to verify email. Please try again.");
        } finally {
            setIsCheckingEmail(false);
        }
    };

    const handleNext = () => {
        setCurrentStep((prev) => Math.min(prev + 1, steps.length - 1));
    };

    const handlePrev = () => {
        setCurrentStep((prev) => Math.max(prev - 1, 0));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post("/signup", {
            onSuccess: () => {
                clearPersistedState();
                toast.success("Account created successfully!");
            },
            onError: (formErrors) => {
                const first = Object.values(formErrors)[0];
                if (first) toast.error(first);
            },
        });
    };

    const canProceedFromDetails =
        data.name.trim().length > 0 && data.password.length >= 8 && passwordsMatch;

    const canProceedFromRole = data.role.length > 0;

    return (
        <div className={cn("space-y-6", className)} {...props}>
            {/* Step Progress Header */}
            <div className="rounded-2xl border border-border/70 bg-card/60 p-4 shadow-2xs backdrop-blur-xs">
                <div className="mb-2.5 flex items-center justify-between text-xs">
                    <span className="font-semibold text-foreground">
                        Step {currentStep + 1} of {steps.length}
                    </span>
                    <span className="font-bold text-primary">
                        {steps[currentStep]?.label}
                    </span>
                </div>
                <div
                    className="grid gap-1.5"
                    style={{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }}
                >
                    {steps.map((step, idx) => (
                        <div
                            key={step.id}
                            className={cn(
                                "h-1.5 rounded-full transition-all duration-300",
                                idx < currentStep
                                    ? "bg-primary"
                                    : idx === currentStep
                                      ? "bg-primary shadow-xs ring-2 ring-primary/30"
                                      : "bg-muted"
                            )}
                        />
                    ))}
                </div>
            </div>

            <form onSubmit={submit}>
                <FieldGroup className="gap-5">
                    {/* Step 0: Email Verification */}
                    {currentStep === 0 && (
                        <div className="space-y-5">
                            <SocialAuthButtons />

                            <Field>
                                <FieldLabel htmlFor="email" className="text-xs font-semibold text-foreground">
                                    Institutional or Registered Email
                                </FieldLabel>
                                <InputGroup
                                    className={cn(
                                        "h-11 rounded-xl border-border/80 bg-background/60 shadow-2xs transition-all duration-200 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20",
                                        errors.email && "border-destructive focus-within:ring-destructive/20"
                                    )}
                                >
                                    <InputGroupAddon align="inline-start" className="pl-3 text-muted-foreground/70">
                                        <Mail className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="email"
                                        type="email"
                                        placeholder="name@school.edu"
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
                                <FieldDescription className="text-xs text-muted-foreground">
                                    Enter your registered academic email to automatically locate your record.
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
                                        <span>Continue with Email</span>
                                        <ArrowRight className="size-4" />
                                    </span>
                                )}
                            </Button>

                            {emailLookupResult && (
                                <div
                                    className={cn(
                                        "flex items-start gap-3 rounded-xl border p-4 text-xs shadow-2xs",
                                        emailLookupResult.found
                                            ? "border-success/30 bg-success/10 text-success-foreground"
                                            : "border-destructive/30 bg-destructive/10 text-destructive-foreground"
                                    )}
                                >
                                    <IconTile
                                        variant="soft"
                                        size="sm"
                                        className={cn(
                                            "rounded-lg shrink-0",
                                            emailLookupResult.found ? "text-success" : "text-destructive"
                                        )}
                                    >
                                        {emailLookupResult.found ? <Check className="size-4" /> : <ShieldAlert className="size-4" />}
                                    </IconTile>
                                    <div className="space-y-1">
                                        <p className="font-semibold text-foreground">
                                            {emailLookupResult.found
                                                ? `${emailLookupResult.type === "student" ? "Student" : "Faculty"} Record Identified`
                                                : "No Record Found"}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {emailLookupResult.found
                                                ? `Welcome, ${emailLookupResult.name}! Your academic records are linked.`
                                                : emailLookupResult.message || "Email address is not yet in the official roster."}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 1: Personal Details */}
                    {currentStep === 1 && (
                        <div className="space-y-4">
                            <Field>
                                <FieldLabel htmlFor="name" className="text-xs font-semibold text-foreground">
                                    Full Legal Name
                                </FieldLabel>
                                <InputGroup className="h-11 rounded-xl border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                                    <InputGroupAddon align="inline-start" className="pl-3 text-muted-foreground/70">
                                        <User className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="name"
                                        type="text"
                                        required
                                        autoFocus
                                        value={data.name}
                                        onChange={(e) => setData("name", e.target.value)}
                                        placeholder="Given Name Surname"
                                        className="text-sm font-normal"
                                    />
                                </InputGroup>
                                {errors.name && <FieldError errors={[{ message: errors.name }]} />}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="email_display" className="text-xs font-semibold text-foreground">
                                    Verified Email
                                </FieldLabel>
                                <InputGroup className="h-11 rounded-xl border-border/80 bg-muted/40 opacity-90">
                                    <InputGroupAddon align="inline-start" className="pl-3 text-muted-foreground/70">
                                        <Mail className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="email_display"
                                        type="email"
                                        value={data.email}
                                        disabled
                                        className="text-sm font-normal text-muted-foreground"
                                    />
                                </InputGroup>
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="password" className="text-xs font-semibold text-foreground">
                                    Password
                                </FieldLabel>
                                <InputGroup className="h-11 rounded-xl border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                                    <InputGroupAddon align="inline-start" className="pl-3 text-muted-foreground/70">
                                        <Lock className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="password"
                                        type={showPassword ? "text" : "password"}
                                        required
                                        value={data.password}
                                        onChange={(e) => setData("password", e.target.value)}
                                        placeholder="At least 8 characters"
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
                                {data.password && (
                                    <div className="mt-1 space-y-1">
                                        <div className="flex h-1.5 gap-1">
                                            {[1, 2, 3, 4, 5].map((lvl) => (
                                                <div
                                                    key={lvl}
                                                    className={cn(
                                                        "h-full flex-1 rounded-full transition-colors",
                                                        lvl <= passwordStrength.strength
                                                            ? passwordStrength.color
                                                            : "bg-muted"
                                                    )}
                                                />
                                            ))}
                                        </div>
                                        <p className="text-[11px] text-muted-foreground">
                                            Strength: <span className="font-semibold text-foreground">{passwordStrength.label}</span>
                                        </p>
                                    </div>
                                )}
                                {errors.password && <FieldError errors={[{ message: errors.password }]} />}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="password_confirmation" className="text-xs font-semibold text-foreground">
                                    Confirm Password
                                </FieldLabel>
                                <InputGroup
                                    className={cn(
                                        "h-11 rounded-xl border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20",
                                        passwordsMatch && "border-success focus-within:ring-success/20"
                                    )}
                                >
                                    <InputGroupAddon align="inline-start" className="pl-3 text-muted-foreground/70">
                                        <Lock className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="password_confirmation"
                                        type={showConfirmPassword ? "text" : "password"}
                                        required
                                        value={data.password_confirmation}
                                        onChange={(e) => setData("password_confirmation", e.target.value)}
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

                    {/* Step 2: Role Selection (Faculty) */}
                    {currentStep === 2 && userType === "faculty" && (
                        <div className="space-y-3">
                            <FieldLabel className="text-xs font-semibold text-foreground">
                                Select Academic Rank or Title
                            </FieldLabel>
                            <div className="grid gap-2">
                                {facultyRoles.map((r) => {
                                    const selected = data.role === r.value;
                                    return (
                                        <button
                                            key={r.value}
                                            type="button"
                                            onClick={() => setData("role", r.value)}
                                            className={cn(
                                                "group flex w-full items-center gap-3.5 rounded-xl border p-3.5 text-left transition-all duration-200",
                                                selected
                                                    ? "border-primary bg-primary/10 shadow-xs ring-1 ring-primary/30"
                                                    : "border-border/80 bg-background/60 hover:border-primary/40 hover:bg-muted/30"
                                            )}
                                        >
                                            <IconTile
                                                variant={selected ? "solid" : "soft"}
                                                size="sm"
                                                className="rounded-lg shrink-0"
                                            >
                                                {selected ? <Check className="size-4" /> : <UserCheck className="size-4" />}
                                            </IconTile>
                                            <div className="min-w-0 flex-1">
                                                <div className="text-xs font-semibold text-foreground">{r.label}</div>
                                                <div className="text-[11px] text-muted-foreground">{r.description}</div>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Step 2: Student ID Verification (Student) */}
                    {currentStep === 2 && userType === "student" && (
                        <div className="space-y-4">
                            <div className="rounded-xl border border-primary/20 bg-primary/5 p-3.5 text-xs text-muted-foreground">
                                Confirm your {studentType === "shs" ? "Learner Reference Number (LRN)" : "Official Student ID Number"} to verify your student account.
                            </div>

                            {studentType === "shs" ? (
                                <Field>
                                    <FieldLabel htmlFor="lrn" className="text-xs font-semibold text-foreground">
                                        12-Digit LRN
                                    </FieldLabel>
                                    <InputGroup className="h-11 rounded-xl border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                                        <InputGroupAddon align="inline-start" className="pl-3 text-muted-foreground/70">
                                            <BadgeCheck className="size-4" />
                                        </InputGroupAddon>
                                        <InputGroupInput
                                            id="lrn"
                                            placeholder="123456789012"
                                            value={data.lrn}
                                            onChange={(e) => setData("lrn", e.target.value)}
                                            className="text-sm font-normal"
                                        />
                                    </InputGroup>
                                </Field>
                            ) : (
                                <Field>
                                    <FieldLabel htmlFor="student_id" className="text-xs font-semibold text-foreground">
                                        College Student ID
                                    </FieldLabel>
                                    <InputGroup className="h-11 rounded-xl border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                                        <InputGroupAddon align="inline-start" className="pl-3 text-muted-foreground/70">
                                            <GraduationCap className="size-4" />
                                        </InputGroupAddon>
                                        <InputGroupInput
                                            id="student_id"
                                            placeholder="2025-00123"
                                            value={data.student_id}
                                            onChange={(e) => setData("student_id", e.target.value)}
                                            className="text-sm font-normal"
                                        />
                                    </InputGroup>
                                </Field>
                            )}
                        </div>
                    )}

                    {/* Step 3: Faculty ID (Optional) */}
                    {currentStep === 3 && userType === "faculty" && (
                        <div className="space-y-4">
                            <Field>
                                <FieldLabel htmlFor="faculty_id_number" className="text-xs font-semibold text-foreground">
                                    Faculty ID Number (Optional)
                                </FieldLabel>
                                <InputGroup className="h-11 rounded-xl border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                                    <InputGroupAddon align="inline-start" className="pl-3 text-muted-foreground/70">
                                        <BadgeCheck className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="faculty_id_number"
                                        placeholder="e.g. FAC-2025-001"
                                        value={data.faculty_id_number}
                                        onChange={(e) => setData("faculty_id_number", e.target.value)}
                                        className="text-sm font-normal uppercase"
                                    />
                                </InputGroup>
                                <FieldDescription className="text-xs text-muted-foreground">
                                    You may also complete this verification step later from your faculty profile.
                                </FieldDescription>
                            </Field>
                        </div>
                    )}

                    {/* Step Last: OTP Code */}
                    {currentStep === steps.length - 1 && (
                        <div className="space-y-4">
                            <div className="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-xs">
                                <IconTile variant="soft" size="sm" className="rounded-lg shrink-0">
                                    <Mail className="size-4" />
                                </IconTile>
                                <div className="space-y-1">
                                    <p className="font-semibold text-foreground">Verification Code Sent</p>
                                    <p className="text-muted-foreground">
                                        We sent a 6-digit confirmation code to{" "}
                                        <span className="font-semibold text-foreground">{data.email}</span>.
                                    </p>
                                </div>
                            </div>

                            <Field>
                                <FieldLabel htmlFor="otp" className="text-xs font-semibold text-foreground">
                                    6-Digit Verification Code
                                </FieldLabel>
                                <InputGroup className="h-12 rounded-xl border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                                    <InputGroupAddon align="inline-start" className="pl-3 text-muted-foreground/70">
                                        <KeyRound className="size-4" />
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        id="otp"
                                        placeholder="123456"
                                        maxLength={6}
                                        value={data.otp}
                                        onChange={(e) => setData("otp", e.target.value.toUpperCase())}
                                        className="font-mono text-center text-lg tracking-widest uppercase"
                                    />
                                </InputGroup>
                                <div className="flex justify-end pt-1">
                                    <button
                                        type="button"
                                        onClick={sendOtp}
                                        className="text-xs font-medium text-primary hover:underline"
                                    >
                                        Resend Code
                                    </button>
                                </div>
                            </Field>
                        </div>
                    )}

                    {/* Navigation Buttons */}
                    {currentStep > 0 && (
                        <div className="flex items-center gap-3 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handlePrev}
                                className="h-11 rounded-xl px-4 font-medium"
                            >
                                <ArrowLeft className="mr-1.5 size-4" />
                                <span>Back</span>
                            </Button>

                            {currentStep < steps.length - 2 ? (
                                <Button
                                    type="button"
                                    onClick={handleNext}
                                    disabled={
                                        (currentStep === 1 && !canProceedFromDetails) ||
                                        (currentStep === 2 && userType === "faculty" && !canProceedFromRole)
                                    }
                                    className="h-11 flex-1 rounded-xl font-semibold shadow-md"
                                >
                                    <span>Continue</span>
                                    <ArrowRight className="ml-1.5 size-4" />
                                </Button>
                            ) : currentStep === steps.length - 2 ? (
                                <Button
                                    type="button"
                                    onClick={sendOtp}
                                    disabled={
                                        userType === "student" &&
                                        ((studentType === "shs" && !data.lrn) || (studentType === "college" && !data.student_id))
                                    }
                                    className="h-11 flex-1 rounded-xl font-semibold shadow-md"
                                >
                                    <span>Send Verification Code</span>
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
                                            <span>Creating Account...</span>
                                        </>
                                    ) : (
                                        "Confirm & Activate Account"
                                    )}
                                </Button>
                            )}
                        </div>
                    )}

                    <div className="pt-2 text-center text-xs text-muted-foreground">
                        Already have an institutional account?{" "}
                        <Link href="/login" className="font-semibold text-primary underline-offset-4 hover:underline">
                            Sign in here
                        </Link>
                    </div>
                </FieldGroup>
            </form>
        </div>
    );
}
