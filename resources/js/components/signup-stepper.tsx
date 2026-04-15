import { Link, router, useForm } from "@inertiajs/react";
import axios from "axios";
import { Check, Loader2, Mail } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Toaster } from "@/components/ui/sonner";
import { cn } from "@/lib/utils";

type UserType = "faculty" | "student" | null;
type StudentType = "college" | "shs" | null;

interface EmailLookupResult {
    found: boolean;
    type?: "faculty" | "student";
    name?: string;
    // Faculty fields
    faculty_id_number?: string;
    department?: string;
    // Student fields
    student_type?: string;
    is_shs?: boolean;
    student_id?: number;
    lrn?: string;
    course?: string;
    academic_year?: number;
    record_id?: string | number;
    message?: string;
}

export function SignupStepper({ className, ...props }: React.ComponentProps<"div">) {
    const [currentStep, setCurrentStep] = useState(0);
    const [isCheckingEmail, setIsCheckingEmail] = useState(false);
    const [emailLookupResult, setEmailLookupResult] = useState<EmailLookupResult | null>(null);
    const [userType, setUserType] = useState<UserType>(null);
    const [studentType, setStudentType] = useState<StudentType>(null);

    const [otpSent, setOtpSent] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        faculty_id_number: "",
        student_id: "",
        lrn: "",
        role: "",
        otp: "",
        user_type: "" as "faculty" | "student" | "",
        student_type: "" as "college" | "shs" | "",
        record_id: "" as string | number,
    });

    // Show error notifications
    useEffect(() => {
        if (errors && Object.keys(errors).length > 0) {
            const firstErrorKey = Object.keys(errors)[0] as keyof typeof errors;
            const firstErrorMessage = errors[firstErrorKey];

            if (firstErrorMessage) {
                toast.error(firstErrorMessage, {
                    description: "Please check the form and try again.",
                });
            }
        }
    }, [errors]);

    // Client-side validation
    const validateEmail = (email: string) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    };

    const validatePassword = (password: string) => {
        return password.length >= 8;
    };

    const passwordsMatch = data.password === data.password_confirmation && data.password_confirmation !== "";

    // Password strength calculation
    const getPasswordStrength = (password: string) => {
        if (!password) return { strength: 0, label: "" };

        let strength = 0;
        if (password.length >= 8) strength += 1;
        if (/[A-Z]/.test(password)) strength += 1;
        if (/[a-z]/.test(password)) strength += 1;
        if (/[0-9]/.test(password)) strength += 1;
        if (/[^A-Za-z0-9]/.test(password)) strength += 1;

        if (strength <= 2) return { strength, label: "Weak", color: "bg-red-500" };
        if (strength <= 3) return { strength, label: "Medium", color: "bg-yellow-500" };
        if (strength <= 4) return { strength, label: "Strong", color: "bg-green-500" };
        return { strength, label: "Very Strong", color: "bg-green-600" };
    };

    const passwordStrength = getPasswordStrength(data.password);

    // Steps based on user type
    const getSteps = () => {
        if (userType === "student") {
            return [
                { id: "email", label: "Email" },
                { id: "details", label: "Details" },
                { id: "verification", label: "Verify" },
                { id: "otp", label: "Confirm" },
            ];
        }
        return [
            { id: "email", label: "Email" },
            { id: "details", label: "Details" },
            { id: "role", label: "Role" },
            { id: "verification", label: "Verify" },
            { id: "otp", label: "Confirm" },
        ];
    };

    const steps = getSteps();

    const facultyRoles = [
        { value: "professor", label: "Professor", description: "Full Professor rank" },
        { value: "associate_professor", label: "Associate Professor", description: "Associate Professor rank" },
        { value: "assistant_professor", label: "Assistant Professor", description: "Assistant Professor rank" },
        { value: "instructor", label: "Instructor", description: "Teaching Instructor" },
        { value: "part_time_faculty", label: "Part-time Faculty", description: "Part-time teaching position" },
    ];

    const sendOtp = async () => {
        try {
            await axios.post("/signup/send-otp", {
                email: data.email,
                user_type: userType,
                student_type: data.student_type,
                record_id: data.record_id,
                student_id: data.student_id,
                lrn: data.lrn,
                role: data.role,
                faculty_id_number: data.faculty_id_number,
            });
            toast.success("Verification code sent", {
                description: `We sent a code to ${data.email}`,
            });
            setOtpSent(true);
            setCurrentStep(currentStep + 1);
        } catch (error: any) {
            console.error("OTP Error:", error);
            const errorMessage = error.response?.data?.message || "Failed to send verification code.";
            const errors = error.response?.data?.errors;

            if (errors && typeof errors === "object") {
                Object.values(errors)
                    .flat()
                    .forEach((err: any) => {
                        toast.error(err as string);
                    });
            } else {
                toast.error("Error", {
                    description: errorMessage,
                });
            }
        }
    };

    // Email lookup function
    const handleEmailLookup = async () => {
        if (!validateEmail(data.email)) {
            toast.error("Please enter a valid email address");
            return;
        }

        setIsCheckingEmail(true);
        setEmailLookupResult(null);

        try {
            const response = await axios.post("/signup/email-lookup", { email: data.email });
            const result: EmailLookupResult = response.data;
            setEmailLookupResult(result);

            if (result.found) {
                setUserType(result.type || null);

                if (result.type === "faculty") {
                    setData((prev) => ({
                        ...prev,
                        name: result.name || "",
                        faculty_id_number: result.faculty_id_number || "",
                        user_type: "faculty",
                        record_id: result.record_id || "",
                    }));
                    toast.success(`Welcome, ${result.name}!`, {
                        description: "Your faculty record was found.",
                    });
                } else if (result.type === "student") {
                    const isShs = result.is_shs || result.student_type === "shs";
                    setStudentType(isShs ? "shs" : "college");
                    setData((prev) => ({
                        ...prev,
                        name: result.name || "",
                        // Do not prefill sensitive IDs to ensure user verifies them
                        student_id: "",
                        lrn: "",
                        user_type: "student",
                        student_type: isShs ? "shs" : "college",
                        record_id: result.record_id || "",
                    }));
                    toast.success(`Welcome, ${result.name}!`, {
                        description: `Your ${isShs ? "SHS" : "College"} student record was found. Please complete verification.`,
                    });
                }
                setCurrentStep(1);
            } else {
                if (result.message?.includes("already exists")) {
                    toast.error("Account exists", {
                        description: result.message,
                        action: {
                            label: "Sign in",
                            onClick: () => router.visit("/login"),
                        },
                    });
                } else {
                    toast.error("Email not found", {
                        description: result.message || "Please use your registered school email.",
                    });
                }
            }
        } catch (error: any) {
            console.error("Email lookup error:", error);
            const errorMessage = error.response?.data?.message || "Please try again later.";
            const errors = error.response?.data?.errors;

            if (errors && typeof errors === "object") {
                // Show validation errors if any
                Object.values(errors)
                    .flat()
                    .forEach((err: any) => {
                        toast.error(err as string);
                    });
            } else {
                toast.error("Error checking email", {
                    description: errorMessage,
                });
            }
        } finally {
            setIsCheckingEmail(false);
        }
    };

    const handleNext = () => {
        if (currentStep < steps.length - 1) {
            setCurrentStep(currentStep + 1);
        }
    };

    const handlePrev = () => {
        if (currentStep > 0) {
            setCurrentStep(currentStep - 1);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!canProceedFromDetails) {
            toast.error("Please fill in all required fields correctly");
            return;
        }

        if (userType === "faculty" && !canProceedFromRole) {
            toast.error("Please select a faculty role");
            return;
        }

        post("/signup", {
            onSuccess: () => {
                toast.success("Account created successfully!", {
                    description: "Redirecting to dashboard...",
                });
            },
            onError: (errors) => {
                const firstErrorKey = Object.keys(errors || {})[0] as keyof typeof errors;
                const firstErrorMessage = errors?.[firstErrorKey];

                if (firstErrorMessage) {
                    toast.error(firstErrorMessage, {
                        description: "Please check the form and try again.",
                    });
                }
            },
        });
    };

    const canProceedFromDetails = data.name && data.password && validatePassword(data.password) && data.password_confirmation && passwordsMatch;

    const canProceedFromRole = userType === "student" || data.role !== "";

    const canSubmit = canProceedFromDetails && (userType === "student" || canProceedFromRole);

    const progressValue = ((currentStep + 1) / steps.length) * 100;

    return (
        <div className={cn("flex flex-col gap-6", className)} {...props}>
            <form onSubmit={submit}>
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-2 text-center">
                        <h1 className="text-2xl font-bold tracking-tight">Create an account</h1>
                        <p className="text-muted-foreground text-sm">Enter your email below to create your account</p>
                    </div>

                    <div className="space-y-2">
                        <Progress value={progressValue} className="h-1" />
                        <div className="text-muted-foreground flex justify-between px-1 text-xs">
                            <span>Step {currentStep + 1}</span>
                            <span>{steps[currentStep]?.label}</span>
                        </div>
                    </div>

                    <div className="grid gap-6">
                        {/* Step 0: Email Verification */}
                        {currentStep === 0 && (
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        placeholder="m@example.com"
                                        required
                                        value={data.email}
                                        onChange={(e) => {
                                            setData("email", e.target.value);
                                            setEmailLookupResult(null);
                                        }}
                                        className={cn(
                                            errors.email && "border-destructive focus-visible:ring-destructive",
                                            emailLookupResult?.found && "border-green-500 focus-visible:ring-green-500",
                                        )}
                                        onKeyDown={(e) => {
                                            if (e.key === "Enter") {
                                                e.preventDefault();
                                                handleEmailLookup();
                                            }
                                        }}
                                    />
                                    {errors.email && <p className="text-destructive text-sm">{errors.email}</p>}
                                </div>
                                <Button
                                    type="button"
                                    onClick={handleEmailLookup}
                                    disabled={isCheckingEmail || !validateEmail(data.email)}
                                    className="w-full"
                                >
                                    {isCheckingEmail ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : "Verify Email"}
                                </Button>

                                {emailLookupResult && (
                                    <div
                                        className={cn(
                                            "rounded-md border p-4 text-sm",
                                            emailLookupResult.found
                                                ? "border-green-200 bg-green-50/50 text-green-800 dark:bg-green-950/20 dark:text-green-300"
                                                : "border-red-200 bg-red-50/50 text-red-800 dark:bg-red-950/20 dark:text-red-300",
                                        )}
                                    >
                                        {emailLookupResult.found ? (
                                            <div className="flex items-center gap-2">
                                                <Check className="h-4 w-4" />
                                                <div>
                                                    <p className="font-medium">
                                                        {emailLookupResult.type === "student" ? "Student" : "Faculty"} record found
                                                    </p>
                                                    <p className="opacity-90">Welcome, {emailLookupResult.name}</p>
                                                </div>
                                            </div>
                                        ) : (
                                            <p>{emailLookupResult.message || "Email not found."}</p>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Step 1: Personal Details */}
                        {currentStep === 1 && (
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Full Name</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        required
                                        value={data.name}
                                        onChange={(e) => setData("name", e.target.value)}
                                        className={errors.name && "border-destructive"}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email_display">Email</Label>
                                    <Input id="email_display" type="email" value={data.email} disabled className="bg-muted opacity-50" />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        required
                                        value={data.password}
                                        onChange={(e) => setData("password", e.target.value)}
                                    />
                                    {data.password && !errors.password && (
                                        <div className="mt-1 flex h-1 gap-1">
                                            {[1, 2, 3, 4, 5].map((level) => (
                                                <div
                                                    key={level}
                                                    className={cn(
                                                        "h-full flex-1 rounded-full transition-colors",
                                                        level <= passwordStrength.strength ? passwordStrength.color : "bg-secondary",
                                                    )}
                                                />
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">Confirm Password</Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        required
                                        value={data.password_confirmation}
                                        onChange={(e) => setData("password_confirmation", e.target.value)}
                                        className={cn(
                                            data.password_confirmation && !passwordsMatch && "border-destructive focus-visible:ring-destructive",
                                            data.password_confirmation && passwordsMatch && "border-green-500 focus-visible:ring-green-500",
                                        )}
                                    />
                                </div>
                            </div>
                        )}

                        {/* Step 2: Role Selection (Faculty only) */}
                        {currentStep === 2 && userType === "faculty" && (
                            <div className="grid gap-4">
                                <Label>Select Faculty Position</Label>
                                <div className="grid gap-2">
                                    {facultyRoles.map((role) => (
                                        <div
                                            key={role.value}
                                            className={cn(
                                                "hover:bg-accent flex cursor-pointer items-center space-x-3 rounded-md border p-3 transition-colors",
                                                data.role === role.value && "border-primary bg-accent",
                                            )}
                                            onClick={() => setData("role", role.value)}
                                        >
                                            <div
                                                className={cn(
                                                    "flex h-4 w-4 items-center justify-center rounded-full border",
                                                    data.role === role.value
                                                        ? "border-primary bg-primary text-primary-foreground"
                                                        : "border-muted-foreground",
                                                )}
                                            >
                                                {data.role === role.value && <Check className="h-3 w-3" />}
                                            </div>
                                            <div className="flex-1 space-y-1">
                                                <p className="text-sm leading-none font-medium">{role.label}</p>
                                                <p className="text-muted-foreground text-xs">{role.description}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Step 2: Student Verification */}
                        {currentStep === 2 && userType === "student" && (
                            <div className="grid gap-4">
                                <div className="bg-muted/50 rounded-md p-4">
                                    <p className="text-muted-foreground text-sm">
                                        Please verify your {studentType === "shs" ? "LRN" : "Student ID"} to continue.
                                    </p>
                                </div>

                                {studentType === "shs" ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="lrn">LRN</Label>
                                        <Input
                                            id="lrn"
                                            placeholder="12-digit Learner Reference Number"
                                            value={data.lrn}
                                            onChange={(e) => setData("lrn", e.target.value)}
                                        />
                                    </div>
                                ) : (
                                    <div className="grid gap-2">
                                        <Label htmlFor="student_id">Student ID</Label>
                                        <Input
                                            id="student_id"
                                            placeholder="Student ID Number"
                                            value={data.student_id}
                                            onChange={(e) => setData("student_id", e.target.value)}
                                        />
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Step 3: Faculty Verification */}
                        {currentStep === 3 && userType === "faculty" && (
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="faculty_id_number">Faculty ID (Optional)</Label>
                                    <Input
                                        id="faculty_id_number"
                                        placeholder="Enter your Faculty ID"
                                        value={data.faculty_id_number}
                                        onChange={(e) => setData("faculty_id_number", e.target.value)}
                                    />
                                    <p className="text-muted-foreground text-xs">You can verify this later in your dashboard.</p>
                                </div>
                            </div>
                        )}

                        {/* Step X: OTP Verification */}
                        {currentStep === steps.length - 1 && (
                            <div className="grid gap-4">
                                <div className="bg-muted/40 rounded-lg border p-4">
                                    <div className="flex items-start gap-3">
                                        <Mail className="text-primary mt-0.5 h-5 w-5" />
                                        <div className="flex-1 space-y-1">
                                            <p className="text-sm leading-none font-medium">Check your email</p>
                                            <p className="text-muted-foreground text-sm">
                                                We sent a verification code to <span className="text-foreground font-medium">{data.email}</span>.
                                                Enter it below to confirm your account.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="otp">Verification Code</Label>
                                    <Input
                                        id="otp"
                                        placeholder="Enter 6-character code"
                                        value={data.otp}
                                        onChange={(e) => setData("otp", e.target.value.toUpperCase())}
                                        maxLength={6}
                                        className="text-center font-mono text-lg tracking-widest uppercase"
                                    />
                                    <p className="text-muted-foreground text-center text-xs">
                                        Didn't receive the code?{" "}
                                        <button type="button" onClick={sendOtp} className="text-primary font-medium hover:underline">
                                            Resend Code
                                        </button>
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Navigation Buttons */}
                        {currentStep > 0 && (
                            <div className="flex flex-col gap-3 sm:flex-row sm:justify-between">
                                <Button type="button" variant="ghost" onClick={handlePrev} className="w-full">
                                    Back
                                </Button>

                                {currentStep < steps.length - 2 ? (
                                    <Button
                                        type="button"
                                        onClick={handleNext}
                                        disabled={
                                            (currentStep === 1 && !canProceedFromDetails) ||
                                            (currentStep === 2 && userType === "faculty" && !canProceedFromRole)
                                        }
                                        className="w-full"
                                    >
                                        Continue
                                    </Button>
                                ) : currentStep === steps.length - 2 ? (
                                    <Button
                                        type="button"
                                        onClick={sendOtp}
                                        disabled={
                                            userType === "student" &&
                                            ((studentType === "shs" && !data.lrn) || (studentType === "college" && !data.student_id))
                                        }
                                        className="w-full"
                                    >
                                        Send Verification Code
                                    </Button>
                                ) : (
                                    <Button type="submit" disabled={processing || !data.otp || data.otp.length < 6} className="w-full">
                                        {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                        Verify & Create Account
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="text-center text-sm">
                        Already have an account?{" "}
                        <Link href="/login" className="hover:text-primary underline underline-offset-4">
                            Sign in
                        </Link>
                    </div>
                </div>
            </form>
            <Toaster position="top-right" richColors />
        </div>
    );
}
