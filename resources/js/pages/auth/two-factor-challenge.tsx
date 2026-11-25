import { Link, useForm, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    ArrowLeft,
    Fingerprint,
    KeyRound,
    Loader2,
    Mail,
    ShieldAlert,
    ShieldCheck,
    Smartphone,
} from "lucide-react";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { toast } from "sonner";

import { AuthLayout, type AuthLayoutProps } from "@/layouts/auth-layout";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { InputOTP, InputOTPGroup, InputOTPSeparator, InputOTPSlot } from "@/components/ui/input-otp";

type VerificationMethod = "select" | "passkey" | "authenticator" | "email" | "recovery";

function base64urlToBuffer(base64url: string): Uint8Array {
    const base64 = base64url.replace(/-/g, "+").replace(/_/g, "/");
    const padded = base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), "=");
    const binary = atob(padded);
    return Uint8Array.from(binary, (c) => c.charCodeAt(0));
}

function bufferToBase64url(buffer: ArrayBuffer): string {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)))
        .replace(/\+/g, "-")
        .replace(/\//g, "_")
        .replace(/=+$/, "");
}

function supportsWebAuthn(): boolean {
    return typeof window !== "undefined" && !!window.PublicKeyCredential;
}

export default function TwoFactorChallengePage() {
    const { has_app_auth, has_email_auth, has_passkeys, announcements } = usePage<{
        has_app_auth: boolean;
        has_email_auth: boolean;
        has_passkeys: boolean;
        announcements?: AuthLayoutProps["announcements"];
    }>().props;

    const [browserSupportsPasskeys] = useState<boolean>(() => supportsWebAuthn());
    const passkeyAvailable = has_passkeys && browserSupportsPasskeys;

    const availableMethods: Exclude<VerificationMethod, "select">[] = [];
    if (passkeyAvailable) availableMethods.push("passkey");
    if (has_app_auth) availableMethods.push("authenticator");
    if (has_email_auth) availableMethods.push("email");
    if (has_app_auth) availableMethods.push("recovery");

    const getInitialMethod = (): VerificationMethod => {
        if (availableMethods.length === 1) {
            return availableMethods[0];
        }
        if (passkeyAvailable) return "passkey";
        if (has_app_auth) return "authenticator";
        if (has_email_auth) return "email";
        return "select";
    };

    const [activeMethod, setActiveMethod] = useState<VerificationMethod>(getInitialMethod);
    const [passkeyVerifying, setPasskeyVerifying] = useState(false);
    const [passkeyAutoPrompted, setPasskeyAutoPrompted] = useState(false);
    const [emailCodeSent, setEmailCodeSent] = useState(false);
    const [sendingEmailCode, setSendingEmailCode] = useState(false);
    const [emailCooldown, setEmailCooldown] = useState(0);
    const passkeyTriggeredRef = useRef(false);

    const form = useForm({
        code: "",
        recovery_code: "",
    });

    useEffect(() => {
        if (emailCooldown <= 0) return;
        const timer = setInterval(() => {
            setEmailCooldown((prev) => prev - 1);
        }, 1000);
        return () => clearInterval(timer);
    }, [emailCooldown]);

    useEffect(() => {
        if (activeMethod === "passkey" && !passkeyAutoPrompted && !passkeyTriggeredRef.current) {
            passkeyTriggeredRef.current = true;
            setPasskeyAutoPrompted(true);
            handlePasskeyVerify(true);
        }
    }, [activeMethod]);

    const handlePasskeyVerify = async (isAutoPrompt = false) => {
        if (passkeyVerifying) return;
        setPasskeyVerifying(true);

        try {
            const optionsResponse = await axios.post("/two-factor-challenge/passkey-options");
            const options = optionsResponse.data.options;

            const challenge = base64urlToBuffer(options.challenge);
            const allowCredentials = (options.allowCredentials ?? []).map((cred: { id: string; type: string }) => ({
                ...cred,
                id: base64urlToBuffer(cred.id),
            }));

            const publicKey: PublicKeyCredentialRequestOptions = {
                ...options,
                challenge,
                allowCredentials,
            };

            const credential = (await navigator.credentials.get({ publicKey })) as PublicKeyCredential | null;
            if (!credential) {
                throw new Error("Failed to get credential");
            }

            const rawId = bufferToBase64url(credential.rawId);
            const assertionResponse = credential.response as AuthenticatorAssertionResponse;

            const passkeyData = {
                id: credential.id,
                rawId,
                type: credential.type,
                response: {
                    authenticatorData: bufferToBase64url(assertionResponse.authenticatorData),
                    clientDataJSON: bufferToBase64url(assertionResponse.clientDataJSON),
                    signature: bufferToBase64url(assertionResponse.signature),
                    userHandle: assertionResponse.userHandle ? bufferToBase64url(assertionResponse.userHandle) : null,
                },
            };

            const verifyResponse = await axios.post("/two-factor-challenge/passkey-verify", {
                credential: passkeyData,
            });

            const redirectUrl = verifyResponse.data.url ?? verifyResponse.data.redirect ?? "/dashboard";
            toast.success("Authentication successful");
            window.location.href = redirectUrl;
        } catch (error: unknown) {
            const err = error as { name?: string; response?: { status?: number; data?: { error?: string; message?: string } } };
            const serverMessage = err.response?.data?.error ?? err.response?.data?.message;
            if (err.name === "NotAllowedError") {
                if (!isAutoPrompt) {
                    toast.error("Passkey verification cancelled.");
                }
            } else if (serverMessage) {
                toast.error(serverMessage);
                if (err.response?.status === 429 || err.response?.status === 403) {
                    window.location.href = "/login";
                }
            } else {
                toast.error("Passkey verification failed.");
            }
        } finally {
            setPasskeyVerifying(false);
        }
    };

    const handleCodeSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.post("/two-factor-challenge");
    };

    const handleSendEmailCode = async () => {
        if (sendingEmailCode || emailCooldown > 0) return;
        setSendingEmailCode(true);
        try {
            const response = await axios.post("/two-factor-challenge/send-email", {}, { headers: { Accept: "application/json" } });
            setEmailCodeSent(true);
            setEmailCooldown(60);
            toast.success(response.data?.message ?? "Verification code sent to your email.");
        } catch (error: unknown) {
            const err = error as { response?: { status?: number; data?: { error?: string; message?: string } } };
            const message = err.response?.data?.error ?? err.response?.data?.message ?? "Failed to send verification code. Please try again.";
            toast.error(message);
            if (err.response?.status === 403) {
                window.location.href = "/login";
            }
        } finally {
            setSendingEmailCode(false);
        }
    };

    const switchMethod = (method: VerificationMethod) => {
        form.reset();
        form.clearErrors();
        setActiveMethod(method);
    };

    const showBackButton = activeMethod !== "select" && availableMethods.length > 1;

    const methodConfig: Record<Exclude<VerificationMethod, "select">, { icon: typeof Fingerprint; label: string; description: string }> = {
        passkey: {
            icon: Fingerprint,
            label: "Passkey / Biometrics",
            description: "Use your fingerprint, face, or hardware key",
        },
        authenticator: {
            icon: Smartphone,
            label: "Authenticator App",
            description: "Enter the 6-digit code from your authenticator app",
        },
        email: {
            icon: Mail,
            label: "Email Verification",
            description: "Receive a one-time code sent to your inbox",
        },
        recovery: {
            icon: KeyRound,
            label: "Recovery Code",
            description: "Use an emergency single-use backup code",
        },
    };

    const renderMethodSelect = () => (
        <div className="space-y-4">
            <div className="grid gap-2.5">
                {availableMethods.map((method) => {
                    const config = methodConfig[method];
                    const Icon = config.icon;
                    return (
                        <button
                            key={method}
                            type="button"
                            onClick={() => switchMethod(method)}
                            className="group flex w-full items-center gap-3.5 rounded-xl border border-border/80 bg-background/80 p-3.5 text-left transition-all duration-200 hover:border-primary/40 hover:bg-primary/5 hover:shadow-xs focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                        >
                            <IconTile variant="soft" size="lg" className="rounded-xl shrink-0 group-hover:scale-105 transition-transform">
                                <Icon className="size-5" />
                            </IconTile>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-semibold text-foreground group-hover:text-primary transition-colors">
                                    {config.label}
                                </p>
                                <p className="text-xs text-muted-foreground">{config.description}</p>
                            </div>
                        </button>
                    );
                })}
            </div>

            {has_passkeys && !browserSupportsPasskeys && (
                <div className="flex items-start gap-2.5 rounded-xl border border-warning/30 bg-warning/10 p-3 text-xs text-warning-foreground">
                    <ShieldAlert className="mt-0.5 size-4 shrink-0 text-warning" />
                    <p>
                        You have passkeys registered, but this browser does not support them. Please choose another method.
                    </p>
                </div>
            )}

            <div className="pt-2 text-center">
                <Link
                    href="/login"
                    className="text-xs font-medium text-muted-foreground hover:text-foreground transition-colors underline-offset-4 hover:underline"
                >
                    Cancel and return to sign in
                </Link>
            </div>
        </div>
    );

    const renderPasskey = () => (
        <div className="space-y-5">
            <div className="flex flex-col items-center gap-3 rounded-2xl border border-border/60 bg-muted/20 py-6 text-center">
                <IconTile variant={passkeyVerifying ? "soft" : "elevated"} size="xl" className="rounded-2xl">
                    {passkeyVerifying ? (
                        <Loader2 className="size-7 animate-spin text-primary" />
                    ) : (
                        <Fingerprint className="size-7 text-primary" />
                    )}
                </IconTile>
                <div className="space-y-1">
                    <p className="text-sm font-semibold text-foreground">
                        {passkeyVerifying ? "Waiting for authorization..." : "Authorize with Passkey"}
                    </p>
                    <p className="max-w-xs text-xs text-muted-foreground">
                        {passkeyVerifying
                            ? "Follow the biometric or security key prompt on your device."
                            : "Touch your fingerprint sensor, use facial recognition, or insert your security key."}
                    </p>
                </div>
            </div>

            <Button
                type="button"
                className="h-11 w-full rounded-xl font-semibold shadow-md"
                onClick={() => handlePasskeyVerify(false)}
                disabled={passkeyVerifying}
            >
                {passkeyVerifying ? (
                    <>
                        <Loader2 className="mr-2 size-4 animate-spin" />
                        <span>Verifying...</span>
                    </>
                ) : (
                    <>
                        <Fingerprint className="mr-2 size-4" />
                        <span>Authenticate with Passkey</span>
                    </>
                )}
            </Button>

            <div className="space-y-2 text-center text-xs">
                {availableMethods.length > 1 && (
                    <button
                        type="button"
                        onClick={() => switchMethod("select")}
                        className="font-medium text-primary hover:text-primary/80 transition-colors hover:underline block w-full"
                    >
                        Try another verification method
                    </button>
                )}
                <Link
                    href="/login"
                    className="text-muted-foreground hover:text-foreground transition-colors block w-full underline-offset-4 hover:underline"
                >
                    Return to login
                </Link>
            </div>
        </div>
    );

    const renderAuthenticator = () => (
        <form onSubmit={handleCodeSubmit}>
            <FieldGroup className="gap-5">
                <Field>
                    <FieldLabel htmlFor="code" className="text-xs font-semibold text-foreground">
                        Authenticator Code
                    </FieldLabel>
                    <div className="flex justify-center pt-1">
                        <InputOTP
                            id="code"
                            maxLength={6}
                            inputMode="numeric"
                            autoFocus
                            autoComplete="one-time-code"
                            value={form.data.code}
                            onChange={(value) => form.setData("code", value.replace(/\D/g, "").slice(0, 6))}
                        >
                            <InputOTPGroup>
                                <InputOTPSlot index={0} className="h-12 w-11 text-base font-mono font-semibold" />
                                <InputOTPSlot index={1} className="h-12 w-11 text-base font-mono font-semibold" />
                                <InputOTPSlot index={2} className="h-12 w-11 text-base font-mono font-semibold" />
                            </InputOTPGroup>
                            <InputOTPSeparator />
                            <InputOTPGroup>
                                <InputOTPSlot index={3} className="h-12 w-11 text-base font-mono font-semibold" />
                                <InputOTPSlot index={4} className="h-12 w-11 text-base font-mono font-semibold" />
                                <InputOTPSlot index={5} className="h-12 w-11 text-base font-mono font-semibold" />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    {form.errors.code && <FieldError errors={[{ message: form.errors.code }]} />}
                </Field>

                <Button
                    type="submit"
                    className="h-11 w-full rounded-xl font-semibold shadow-md"
                    disabled={form.processing || form.data.code.length < 6}
                >
                    {form.processing ? (
                        <>
                            <Loader2 className="mr-2 size-4 animate-spin" />
                            <span>Verifying code...</span>
                        </>
                    ) : (
                        "Verify Code"
                    )}
                </Button>

                <div className="space-y-2 text-center text-xs">
                    {availableMethods.length > 1 && (
                        <button
                            type="button"
                            onClick={() => switchMethod("select")}
                            className="font-medium text-primary hover:text-primary/80 transition-colors hover:underline block w-full"
                        >
                            Try another verification method
                        </button>
                    )}
                    <Link
                        href="/login"
                        className="text-muted-foreground hover:text-foreground transition-colors block w-full underline-offset-4 hover:underline"
                    >
                        Return to login
                    </Link>
                </div>
            </FieldGroup>
        </form>
    );

    const renderEmail = () => (
        <form onSubmit={handleCodeSubmit}>
            <FieldGroup className="gap-5">
                <Field>
                    <FieldLabel htmlFor="code" className="text-xs font-semibold text-foreground">
                        Email One-Time Code
                    </FieldLabel>
                    {!emailCodeSent ? (
                        <div className="space-y-3">
                            <p className="text-xs text-muted-foreground">
                                We will dispatch a temporary 6-digit verification code to your verified email address.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11 w-full rounded-xl gap-2 font-medium"
                                onClick={handleSendEmailCode}
                                disabled={sendingEmailCode}
                            >
                                {sendingEmailCode ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin text-primary" />
                                        <span>Sending code...</span>
                                    </>
                                ) : (
                                    <>
                                        <Mail className="size-4 text-primary" />
                                        <span>Send Verification Code to Email</span>
                                    </>
                                )}
                            </Button>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            <div className="flex justify-center pt-1">
                                <InputOTP
                                    id="code"
                                    maxLength={6}
                                    inputMode="numeric"
                                    autoFocus
                                    autoComplete="one-time-code"
                                    value={form.data.code}
                                    onChange={(value) => form.setData("code", value.replace(/\D/g, "").slice(0, 6))}
                                >
                                    <InputOTPGroup>
                                        <InputOTPSlot index={0} className="h-12 w-11 text-base font-mono font-semibold" />
                                        <InputOTPSlot index={1} className="h-12 w-11 text-base font-mono font-semibold" />
                                        <InputOTPSlot index={2} className="h-12 w-11 text-base font-mono font-semibold" />
                                    </InputOTPGroup>
                                    <InputOTPSeparator />
                                    <InputOTPGroup>
                                        <InputOTPSlot index={3} className="h-12 w-11 text-base font-mono font-semibold" />
                                        <InputOTPSlot index={4} className="h-12 w-11 text-base font-mono font-semibold" />
                                        <InputOTPSlot index={5} className="h-12 w-11 text-base font-mono font-semibold" />
                                    </InputOTPGroup>
                                </InputOTP>
                            </div>
                            {form.errors.code && <FieldError errors={[{ message: form.errors.code }]} />}
                            <div className="flex justify-end">
                                <button
                                    type="button"
                                    onClick={handleSendEmailCode}
                                    disabled={sendingEmailCode || emailCooldown > 0}
                                    className="text-xs font-medium text-primary hover:underline disabled:opacity-50 disabled:no-underline"
                                >
                                    {sendingEmailCode ? "Sending..." : emailCooldown > 0 ? `Resend code (${emailCooldown}s)` : "Resend code"}
                                </button>
                            </div>
                        </div>
                    )}
                </Field>

                {emailCodeSent && (
                    <Button
                        type="submit"
                        className="h-11 w-full rounded-xl font-semibold shadow-md"
                        disabled={form.processing || form.data.code.length < 6}
                    >
                        {form.processing ? (
                            <>
                                <Loader2 className="mr-2 size-4 animate-spin" />
                                <span>Verifying...</span>
                            </>
                        ) : (
                            "Verify & Continue"
                        )}
                    </Button>
                )}

                <div className="space-y-2 text-center text-xs">
                    {availableMethods.length > 1 && (
                        <button
                            type="button"
                            onClick={() => switchMethod("select")}
                            className="font-medium text-primary hover:text-primary/80 transition-colors hover:underline block w-full"
                        >
                            Try another verification method
                        </button>
                    )}
                    <Link
                        href="/login"
                        className="text-muted-foreground hover:text-foreground transition-colors block w-full underline-offset-4 hover:underline"
                    >
                        Return to login
                    </Link>
                </div>
            </FieldGroup>
        </form>
    );

    const renderRecovery = () => (
        <form onSubmit={handleCodeSubmit}>
            <FieldGroup className="gap-5">
                <Field>
                    <FieldLabel htmlFor="recovery_code" className="text-xs font-semibold text-foreground">
                        Emergency Recovery Code
                    </FieldLabel>
                    <InputGroup className="h-12 rounded-xl border-border/80 bg-background/60 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                        <InputGroupAddon align="inline-start" className="pl-3 text-muted-foreground/70">
                            <KeyRound className="size-4" />
                        </InputGroupAddon>
                        <InputGroupInput
                            id="recovery_code"
                            type="text"
                            autoFocus
                            autoComplete="off"
                            value={form.data.recovery_code}
                            onChange={(e) => form.setData("recovery_code", e.target.value)}
                            placeholder="xxxx-xxxx-xxxx"
                            className="font-mono text-sm tracking-wider text-center"
                        />
                    </InputGroup>
                    {form.errors.recovery_code && <FieldError errors={[{ message: form.errors.recovery_code }]} />}
                </Field>

                <Button
                    type="submit"
                    className="h-11 w-full rounded-xl font-semibold shadow-md"
                    disabled={form.processing || !form.data.recovery_code}
                >
                    {form.processing ? (
                        <>
                            <Loader2 className="mr-2 size-4 animate-spin" />
                            <span>Verifying recovery code...</span>
                        </>
                    ) : (
                        "Verify Recovery Code"
                    )}
                </Button>

                <div className="space-y-2 text-center text-xs">
                    {availableMethods.length > 1 && (
                        <button
                            type="button"
                            onClick={() => switchMethod("select")}
                            className="font-medium text-primary hover:text-primary/80 transition-colors hover:underline block w-full"
                        >
                            Try another verification method
                        </button>
                    )}
                    <Link
                        href="/login"
                        className="text-muted-foreground hover:text-foreground transition-colors block w-full underline-offset-4 hover:underline"
                    >
                        Return to login
                    </Link>
                </div>
            </FieldGroup>
        </form>
    );

    const renderActiveMethod = () => {
        switch (activeMethod) {
            case "select":
                return renderMethodSelect();
            case "passkey":
                return renderPasskey();
            case "authenticator":
                return renderAuthenticator();
            case "email":
                return renderEmail();
            case "recovery":
                return renderRecovery();
        }
    };

    const currentTitle =
        activeMethod === "select"
            ? "Two-Factor Verification"
            : methodConfig[activeMethod]?.label;

    const currentDescription =
        activeMethod === "select"
            ? "Please confirm your identity using one of your registered authentication methods."
            : methodConfig[activeMethod]?.description;

    return (
        <AuthLayout
            metaTitle="Two-Factor Authentication"
            badge="Security Gate"
            icon={
                showBackButton ? (
                    <button
                        type="button"
                        onClick={() => switchMethod("select")}
                        className="text-primary hover:text-primary/80 flex items-center justify-center"
                        title="Back to method selection"
                        aria-label="Back to method selection"
                    >
                        <ArrowLeft className="size-6" />
                    </button>
                ) : (
                    <ShieldCheck className="size-6 text-primary" />
                )
            }
            title={currentTitle}
            description={currentDescription}
            announcements={announcements}
            showBackToLogin={false}
            maxWidth="sm"
        >
            {renderActiveMethod()}
        </AuthLayout>
    );
}
