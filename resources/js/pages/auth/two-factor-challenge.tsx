import { Head, Link, useForm, usePage } from "@inertiajs/react";
import axios from "axios";
import { ArrowLeft, Fingerprint, KeyRound, Loader2, Mail, ShieldAlert, ShieldCheck, Smartphone } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { OnboardingPanel } from "@/components/onboarding-panel";
import { ThemeToggle } from "@/components/theme-toggle";
import { TransitionWrapper } from "@/components/transition-wrapper";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

interface Branding {
    appName: string;
    appShortName: string;
    organizationShortName: string;
}

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
    const { has_app_auth, has_email_auth, has_passkeys, branding } = usePage<{
        has_app_auth: boolean;
        has_email_auth: boolean;
        has_passkeys: boolean;
        branding?: Branding;
    }>().props;

    const appName = branding?.appName || "School Portal";
    const orgShortName = branding?.organizationShortName || "UNI";

    const browserSupportsPasskeys = supportsWebAuthn();
    const passkeyAvailable = has_passkeys && browserSupportsPasskeys;

    // Build list of available methods
    const availableMethods: Exclude<VerificationMethod, "select">[] = [];
    if (passkeyAvailable) {
        availableMethods.push("passkey");
    }
    if (has_app_auth) {
        availableMethods.push("authenticator");
    }
    if (has_email_auth) {
        availableMethods.push("email");
    }
    if (has_app_auth) {
        availableMethods.push("recovery");
    }

    // Determine initial view: if only one method, go straight to it; otherwise select
    const getInitialMethod = (): VerificationMethod => {
        if (availableMethods.length === 1) {
            return availableMethods[0];
        }
        // Auto-prompt passkey if available
        if (passkeyAvailable) {
            return "passkey";
        }
        if (availableMethods.length > 1) {
            return "select";
        }
        return availableMethods[0] ?? "select";
    };

    const [activeMethod, setActiveMethod] = useState<VerificationMethod>(getInitialMethod);
    const [passkeyVerifying, setPasskeyVerifying] = useState(false);
    const [passkeyAutoPrompted, setPasskeyAutoPrompted] = useState(false);
    const [emailCodeSent, setEmailCodeSent] = useState(false);
    const passkeyTriggeredRef = useRef(false);

    const form = useForm({
        code: "",
        recovery_code: "",
    });

    // Auto-prompt passkey ceremony on mount when it's the active method
    useEffect(() => {
        if (activeMethod === "passkey" && passkeyAvailable && !passkeyAutoPrompted && !passkeyTriggeredRef.current) {
            passkeyTriggeredRef.current = true;
            setPasskeyAutoPrompted(true);
            handlePasskeyVerify(true);
        }
    }, [activeMethod, passkeyAvailable, passkeyAutoPrompted]);

    const handlePasskeyVerify = async (isAutoPrompt = false) => {
        setPasskeyVerifying(true);

        try {
            const optionsResponse = await axios.post("/two-factor-challenge/passkey-options");
            const options = optionsResponse.data.options;

            const challenge = base64urlToBuffer(options.challenge);
            const allowCredentials = (options.allowCredentials || []).map((cred: any) => ({
                ...cred,
                id: base64urlToBuffer(cred.id),
            }));

            const publicKey: PublicKeyCredentialRequestOptions = {
                ...options,
                challenge,
                allowCredentials,
            };

            const credential = (await navigator.credentials.get({ publicKey })) as PublicKeyCredential;

            if (!credential) {
                throw new Error("Failed to get credential");
            }

            const assertionResponse = credential.response as AuthenticatorAssertionResponse;

            const passkeyData = JSON.stringify({
                id: credential.id,
                rawId: bufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    authenticatorData: bufferToBase64url(assertionResponse.authenticatorData),
                    clientDataJSON: bufferToBase64url(assertionResponse.clientDataJSON),
                    signature: bufferToBase64url(assertionResponse.signature),
                    userHandle: assertionResponse.userHandle ? bufferToBase64url(assertionResponse.userHandle) : null,
                },
            });

            const verifyResponse = await axios.post("/two-factor-challenge/passkey-verify", {
                passkey: passkeyData,
            });

            if (verifyResponse.data.url) {
                window.location.href = verifyResponse.data.url;
            }
        } catch (error: any) {
            console.error("Passkey verification failed:", error);
            setPasskeyVerifying(false);

            if (error?.name === "NotAllowedError") {
                // User cancelled or timed out — fall back to method select if other methods exist
                if (isAutoPrompt && availableMethods.length > 1) {
                    setActiveMethod("select");
                    return;
                }
                // If auto-prompted with no other methods, stay silent — user can retry or go back
                if (!isAutoPrompt) {
                    toast.error("Passkey verification was cancelled or timed out.");
                }
            } else if (error?.response?.data?.error) {
                toast.error(error.response.data.error);
            } else {
                toast.error("Passkey verification failed.");
            }

            // If there are other methods, show the selector
            if (availableMethods.length > 1) {
                setActiveMethod("select");
            }
        }
    };

    const handleCodeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post("/two-factor-challenge");
    };

    const handleSendEmailCode = () => {
        axios
            .post("/two-factor-challenge/send-email")
            .then(() => {
                setEmailCodeSent(true);
                toast.success("Verification code sent to your email.");
            })
            .catch(() => {
                toast.error("Failed to send verification code.");
            });
    };

    const switchMethod = (method: VerificationMethod) => {
        setActiveMethod(method);
        form.reset();
        form.clearErrors();
    };

    const showBackButton = activeMethod !== "select" && availableMethods.length > 1;

    const methodConfig: Record<Exclude<VerificationMethod, "select">, { icon: React.ElementType; label: string; description: string }> = {
        passkey: {
            icon: Fingerprint,
            label: "Passkey",
            description: "Use your fingerprint, face, or hardware security key",
        },
        authenticator: {
            icon: Smartphone,
            label: "Authenticator App",
            description: "Enter the 6-digit code from your authenticator app",
        },
        email: {
            icon: Mail,
            label: "Email Code",
            description: "Receive a one-time code sent to your email address",
        },
        recovery: {
            icon: KeyRound,
            label: "Recovery Code",
            description: "Use one of your saved recovery codes",
        },
    };

    const renderMethodSelect = () => (
        <div className="space-y-3">
            <p className="text-muted-foreground text-sm">Choose how you want to verify your identity:</p>
            <div className="space-y-2">
                {availableMethods.map((method) => {
                    const config = methodConfig[method];
                    const Icon = config.icon;
                    return (
                        <button
                            key={method}
                            type="button"
                            onClick={() => switchMethod(method)}
                            className="hover:bg-muted/80 border-border hover:border-primary/30 flex w-full items-center gap-3 rounded-lg border p-3 text-left transition-colors"
                        >
                            <div className="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-lg">
                                <Icon className="text-foreground h-5 w-5" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium">{config.label}</p>
                                <p className="text-muted-foreground text-xs">{config.description}</p>
                            </div>
                        </button>
                    );
                })}
            </div>

            {/* Show notice if passkeys exist but browser doesn't support them */}
            {has_passkeys && !browserSupportsPasskeys && (
                <div className="bg-muted/50 flex items-start gap-2 rounded-lg border p-3">
                    <ShieldAlert className="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                    <p className="text-muted-foreground text-xs">
                        You have passkeys registered, but this browser does not support them. Use a different browser or choose another method.
                    </p>
                </div>
            )}

            <Link
                href="/login"
                className="text-muted-foreground hover:text-primary block w-full text-center text-xs underline-offset-4 hover:underline"
            >
                Back to login
            </Link>
        </div>
    );

    const renderPasskey = () => (
        <div className="space-y-4">
            <div className="flex flex-col items-center gap-3 py-4">
                {passkeyVerifying ? (
                    <>
                        <div className="bg-primary/10 flex h-16 w-16 items-center justify-center rounded-full">
                            <Loader2 className="text-primary h-8 w-8 animate-spin" />
                        </div>
                        <div className="text-center">
                            <p className="text-sm font-medium">Waiting for passkey...</p>
                            <p className="text-muted-foreground text-xs">Follow the prompts from your browser or device.</p>
                        </div>
                    </>
                ) : (
                    <>
                        <div className="bg-muted flex h-16 w-16 items-center justify-center rounded-full">
                            <Fingerprint className="text-foreground h-8 w-8" />
                        </div>
                        <div className="text-center">
                            <p className="text-sm font-medium">Verify with your passkey</p>
                            <p className="text-muted-foreground text-xs">Use your fingerprint, face, or hardware security key.</p>
                        </div>
                    </>
                )}
            </div>
            <Button type="button" className="w-full" onClick={() => handlePasskeyVerify(false)} disabled={passkeyVerifying}>
                <Fingerprint className="mr-2 h-4 w-4" />
                {passkeyVerifying ? "Verifying..." : "Verify with Passkey"}
            </Button>
            <div className="space-y-1.5">
                {availableMethods.length > 1 && (
                    <button
                        type="button"
                        onClick={() => switchMethod("select")}
                        className="text-muted-foreground hover:text-primary w-full text-center text-sm underline-offset-4 hover:underline"
                    >
                        Try another way
                    </button>
                )}
                <Link
                    href="/login"
                    className="text-muted-foreground hover:text-primary block w-full text-center text-xs underline-offset-4 hover:underline"
                >
                    Back to login
                </Link>
            </div>
        </div>
    );

    const renderAuthenticator = () => (
        <form onSubmit={handleCodeSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="code" className="flex items-center gap-1.5">
                    <Smartphone className="h-3.5 w-3.5" />
                    Authenticator Code
                </Label>
                <p className="text-muted-foreground text-xs">Enter the 6-digit code from your authenticator app.</p>
                <Input
                    id="code"
                    type="text"
                    inputMode="numeric"
                    autoFocus
                    autoComplete="one-time-code"
                    value={form.data.code}
                    onChange={(e) => form.setData("code", e.target.value)}
                    placeholder="XXX XXX"
                    className="text-center font-mono text-lg tracking-widest"
                />
                {form.errors.code && <p className="text-destructive text-sm">{form.errors.code}</p>}
            </div>
            <Button type="submit" className="w-full" disabled={form.processing}>
                {form.processing ? "Verifying..." : "Verify"}
            </Button>
        </form>
    );

    const renderEmail = () => (
        <form onSubmit={handleCodeSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="code" className="flex items-center gap-1.5">
                    <Mail className="h-3.5 w-3.5" />
                    Email Verification Code
                </Label>
                <p className="text-muted-foreground text-xs">
                    {emailCodeSent
                        ? "A code has been sent to your email. Enter it below."
                        : "Click the button below to receive a verification code at your email address."}
                </p>
                {!emailCodeSent && (
                    <Button type="button" variant="outline" className="w-full" onClick={handleSendEmailCode}>
                        <Mail className="mr-2 h-4 w-4" />
                        Send Code to Email
                    </Button>
                )}
                {emailCodeSent && (
                    <>
                        <Input
                            id="code"
                            type="text"
                            inputMode="numeric"
                            autoFocus
                            autoComplete="one-time-code"
                            value={form.data.code}
                            onChange={(e) => form.setData("code", e.target.value)}
                            placeholder="XXX XXX"
                            className="text-center font-mono text-lg tracking-widest"
                        />
                        {form.errors.code && <p className="text-destructive text-sm">{form.errors.code}</p>}
                    </>
                )}
            </div>
            {emailCodeSent && (
                <>
                    <Button type="submit" className="w-full" disabled={form.processing}>
                        {form.processing ? "Verifying..." : "Verify"}
                    </Button>
                    <button
                        type="button"
                        onClick={handleSendEmailCode}
                        className="text-muted-foreground hover:text-primary w-full text-center text-xs underline-offset-4 hover:underline"
                    >
                        Resend code
                    </button>
                </>
            )}
        </form>
    );

    const renderRecovery = () => (
        <form onSubmit={handleCodeSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="recovery_code" className="flex items-center gap-1.5">
                    <KeyRound className="h-3.5 w-3.5" />
                    Recovery Code
                </Label>
                <p className="text-muted-foreground text-xs">Enter one of the recovery codes you saved when setting up two-factor authentication.</p>
                <Input
                    id="recovery_code"
                    type="text"
                    autoFocus
                    autoComplete="off"
                    value={form.data.recovery_code}
                    onChange={(e) => form.setData("recovery_code", e.target.value)}
                    placeholder="xxxx-xxxx-xxxx"
                    className="font-mono"
                />
                {form.errors.recovery_code && <p className="text-destructive text-sm">{form.errors.recovery_code}</p>}
            </div>
            <Button type="submit" className="w-full" disabled={form.processing}>
                {form.processing ? "Verifying..." : "Verify"}
            </Button>
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

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <Head title="Two Factor Authentication" />
            <div className="relative flex flex-col gap-4 p-6 md:p-10">
                <div className="flex items-center justify-between md:justify-start">
                    <a href="#" className="flex items-center gap-2 font-medium">
                        <div className="flex h-10 w-10 items-center justify-center rounded-md">
                            <img src="/web-app-manifest-192x192.png" alt={`${orgShortName} Logo`} className="h-10 w-10" />
                        </div>
                        <span className="text-foreground text-4xl font-extrabold tracking-tight">{appName}</span>
                    </a>
                    <div className="md:absolute md:top-6 md:right-6">
                        <ThemeToggle />
                    </div>
                </div>
                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-sm">
                        <TransitionWrapper>
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        {showBackButton && (
                                            <Button variant="ghost" size="icon" className="h-8 w-8 shrink-0" onClick={() => switchMethod("select")}>
                                                <ArrowLeft className="h-4 w-4" />
                                            </Button>
                                        )}
                                        <div>
                                            <CardTitle className="flex items-center gap-2">
                                                <ShieldCheck className="h-5 w-5" />
                                                {activeMethod === "select"
                                                    ? "Verify Your Identity"
                                                    : (methodConfig[activeMethod as keyof typeof methodConfig]?.label ?? "Two-Factor Authentication")}
                                            </CardTitle>
                                            <CardDescription className="mt-1">
                                                {activeMethod === "select"
                                                    ? "An additional verification step is required to access your account."
                                                    : (methodConfig[activeMethod as keyof typeof methodConfig]?.description ??
                                                      "Confirm access to your account.")}
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>{renderActiveMethod()}</CardContent>
                            </Card>
                        </TransitionWrapper>
                    </div>
                </div>
            </div>

            <div className="bg-muted relative hidden lg:block">
                <TransitionWrapper className="h-full">
                    <OnboardingPanel className="h-full" />
                </TransitionWrapper>
            </div>
        </div>
    );
}
