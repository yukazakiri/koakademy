import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { Link, useForm } from "@inertiajs/react";
import axios from "axios";
import { Eye, EyeOff, Key, Loader2, Lock, Mail } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

// Helper to check if WebAuthn is supported
const isWebAuthnSupported = () => {
    return !!(window.PublicKeyCredential && navigator.credentials);
};

export function LoginForm({
    className,
    errors,
    status,
    ...props
}: React.ComponentPropsWithoutRef<"div"> & {
    errors?: Record<string, string>;
    status?: string;
}) {
    const { data, setData, post, processing } = useForm({
        email: "",
        password: "",
        remember: false,
    });

    // State for password visibility
    const [showPassword, setShowPassword] = useState(false);
    const [loggingInWithPasskey, setLoggingInWithPasskey] = useState(false);
    const [passkeyAvailable, setPasskeyAvailable] = useState(false);
    const [showPasskeyPrompt, setShowPasskeyPrompt] = useState(false);
    const hasPromptedRef = useRef(false);

    // Check if passkeys are available on mount
    useEffect(() => {
        const checkPasskeySupport = async () => {
            let supported = isWebAuthnSupported();

            // Also check if the device supports platform authenticators (fingerprint, face, PIN)
            if (supported && window.PublicKeyCredential?.isUserVerifyingPlatformAuthenticatorAvailable) {
                supported = await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            }

            setPasskeyAvailable(supported);

            // Show prompt if passkeys are supported and we haven't prompted yet
            if (supported && !hasPromptedRef.current) {
                hasPromptedRef.current = true;
                // Small delay to let the page render first
                setTimeout(() => {
                    setShowPasskeyPrompt(true);
                }, 500);
            }
        };
        checkPasskeySupport();
    }, []);

    // Display status message if available
    useEffect(() => {
        if (status) {
            toast.success(status);
        }
    }, [status]);

    // Display errors if available
    useEffect(() => {
        if (errors && Object.keys(errors).length > 0) {
            Object.entries(errors).forEach(([_, message]) => {
                toast.error(message);
            });
        }
    }, [errors]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post("/login", {
            onError: (errors) => {
                Object.values(errors).forEach((error) => {
                    toast.error(error);
                });
            },
            onSuccess: () => {
                toast.success("Welcome back!");
            },
        });
    };

    const handlePasskeyLogin = useCallback(async () => {
        if (loggingInWithPasskey) return;

        setLoggingInWithPasskey(true);
        setShowPasskeyPrompt(false);

        try {
            // 1. Get options from backend (no email needed for discoverable credentials)
            const optionsResponse = await axios.post("/passkeys/options", {});
            const options = optionsResponse.data.options;

            // 2. Authenticate using WebAuthn API
            const challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, "+").replace(/_/g, "/")), (c) => c.charCodeAt(0));

            // For discoverable credentials, allowCredentials should be empty
            const allowCredentials =
                Array.isArray(options.allowCredentials) && options.allowCredentials.length > 0
                    ? options.allowCredentials.map((cred: any) => ({
                          ...cred,
                          id: Uint8Array.from(atob(cred.id.replace(/-/g, "+").replace(/_/g, "/")), (c) => c.charCodeAt(0)),
                      }))
                    : [];

            const publicKey: PublicKeyCredentialRequestOptions = {
                ...options,
                challenge,
                allowCredentials,
                userVerification: "preferred",
            };

            const credential = (await navigator.credentials.get({ publicKey })) as PublicKeyCredential;

            if (!credential) {
                throw new Error("Failed to get credential");
            }

            // 3. Verify passkey
            const rawId = btoa(String.fromCharCode(...new Uint8Array(credential.rawId)))
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");
            const authenticatorData = btoa(
                String.fromCharCode(...new Uint8Array((credential.response as AuthenticatorAssertionResponse).authenticatorData)),
            )
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");
            const clientDataJSON = btoa(
                String.fromCharCode(...new Uint8Array((credential.response as AuthenticatorAssertionResponse).clientDataJSON)),
            )
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");
            const signature = btoa(String.fromCharCode(...new Uint8Array((credential.response as AuthenticatorAssertionResponse).signature)))
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");
            // @ts-ignore
            const userHandle = credential.response.userHandle
                ? btoa(String.fromCharCode(...new Uint8Array(credential.response.userHandle)))
                      .replace(/\+/g, "-")
                      .replace(/\//g, "_")
                      .replace(/=+$/, "")
                : null;

            const passkeyData = JSON.stringify({
                id: credential.id,
                rawId,
                type: credential.type,
                response: {
                    authenticatorData,
                    clientDataJSON,
                    signature,
                    userHandle,
                },
            });

            const verifyResponse = await axios.post("/passkeys/login", {
                passkey: passkeyData,
            });

            if (verifyResponse.data.url) {
                toast.success("Welcome back!");
                // Inertia manual visit
                window.location.href = verifyResponse.data.url;
            } else {
                toast.error("Passkey verification failed.");
            }
        } catch (error: any) {
            console.error("Passkey Error:", error);
            if (error.response?.data?.error) {
                toast.error(error.response.data.error);
            } else if (error.name === "NotAllowedError") {
                // User cancelled or timed out - don't show error, just dismiss prompt
                // toast.error("Passkey request canceled or timed out.")
            } else if (error.name === "InvalidStateError") {
                toast.error("No passkey found. Please sign in with your password.");
            } else {
                toast.error("Failed to login with passkey. Please try again.");
            }
        } finally {
            setLoggingInWithPasskey(false);
        }
    }, [loggingInWithPasskey]);

    return (
        <div className={cn("flex flex-col gap-6", className)} {...props}>
            {/* Passkey prompt banner */}
            {showPasskeyPrompt && passkeyAvailable && (
                <div className="border-primary/20 bg-primary/5 animate-in fade-in slide-in-from-top-2 rounded-lg border p-4 duration-300">
                    <div className="flex items-start gap-3">
                        <div className="bg-primary/10 rounded-full p-2">
                            <Key className="text-primary h-4 w-4" />
                        </div>
                        <div className="flex-1 space-y-2">
                            <p className="text-foreground text-sm font-medium">Sign in faster with a passkey</p>
                            <p className="text-muted-foreground text-xs">
                                Use your fingerprint, face, or device PIN to sign in securely without a password.
                            </p>
                            <div className="flex gap-2 pt-1">
                                <Button type="button" size="sm" onClick={handlePasskeyLogin} disabled={loggingInWithPasskey} className="h-8">
                                    {loggingInWithPasskey ? (
                                        <>
                                            <Loader2 className="mr-2 h-3 w-3 animate-spin" />
                                            Verifying...
                                        </>
                                    ) : (
                                        <>
                                            <Key className="mr-2 h-3 w-3" />
                                            Use passkey
                                        </>
                                    )}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setShowPasskeyPrompt(false)}
                                    className="text-muted-foreground h-8"
                                >
                                    Not now
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <form onSubmit={submit}>
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-2">
                        <h2 className="from-primary to-primary/60 bg-gradient-to-r bg-clip-text pb-1 text-3xl font-bold text-transparent">
                            Welcome back
                        </h2>
                        <div className="text-muted-foreground mx-auto w-full max-w-[250px] text-center text-sm text-balance">
                            Enter your credentials to access your account dashboard
                        </div>
                    </div>

                    <div className="grid gap-5">
                        <div className="group relative grid gap-2">
                            <Label htmlFor="email" className="sr-only">
                                Email
                            </Label>
                            <div className="relative">
                                <Mail className="text-muted-foreground group-focus-within:text-primary absolute top-3 left-3 z-10 h-4 w-4 transition-colors" />
                                <Input
                                    id="email"
                                    type="email"
                                    placeholder="name@example.com"
                                    required
                                    value={data.email}
                                    onChange={(e) => setData("email", e.target.value)}
                                    disabled={processing || loggingInWithPasskey}
                                    className={cn(
                                        "bg-background/50 border-muted-foreground/20 hover:border-primary/50 focus-visible:border-primary h-10 pl-10 transition-all duration-300",
                                        "text-foreground placeholder:text-muted-foreground/70",
                                        errors?.email && "border-destructive focus-visible:ring-destructive",
                                    )}
                                />
                            </div>
                        </div>

                        <div className="group relative grid gap-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password" className="sr-only">
                                    Password
                                </Label>
                            </div>
                            <div className="relative">
                                <Lock className="text-muted-foreground group-focus-within:text-primary absolute top-3 left-3 z-10 h-4 w-4 transition-colors" />
                                <Input
                                    id="password"
                                    type={showPassword ? "text" : "password"}
                                    placeholder="••••••••"
                                    required
                                    value={data.password}
                                    onChange={(e) => setData("password", e.target.value)}
                                    disabled={processing || loggingInWithPasskey}
                                    className={cn(
                                        "bg-background/50 border-muted-foreground/20 hover:border-primary/50 focus-visible:border-primary h-10 pr-10 pl-10 transition-all duration-300",
                                        "text-foreground placeholder:text-muted-foreground/70",
                                        errors?.password && "border-destructive focus-visible:ring-destructive",
                                    )}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="text-muted-foreground hover:text-primary absolute top-0 right-0 h-10 w-10 transition-colors"
                                    onClick={() => setShowPassword(!showPassword)}
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    <span className="sr-only">Toggle password visibility</span>
                                </Button>
                            </div>
                            <div className="text-right">
                                <Link
                                    href="/forgot-password"
                                    className="text-muted-foreground hover:text-primary text-xs font-medium underline-offset-4 transition-colors hover:underline"
                                >
                                    Forgot your password?
                                </Link>
                            </div>
                        </div>

                        <div className="grid gap-3">
                            <Button
                                type="submit"
                                className="shadow-primary/20 hover:shadow-primary/40 h-10 w-full font-bold tracking-wide shadow-lg transition-all duration-300"
                                disabled={processing || loggingInWithPasskey}
                            >
                                {processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Signing in...
                                    </>
                                ) : (
                                    "Sign in"
                                )}
                            </Button>

                            {passkeyAvailable && (
                                <>
                                    <div className="relative">
                                        <div className="absolute inset-0 flex items-center">
                                            <span className="w-full border-t" />
                                        </div>
                                        <div className="relative flex justify-center text-xs uppercase">
                                            <span className="bg-background text-muted-foreground px-2">Or continue with</span>
                                        </div>
                                    </div>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-10 w-full"
                                        onClick={handlePasskeyLogin}
                                        disabled={processing || loggingInWithPasskey}
                                    >
                                        {loggingInWithPasskey ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Verifying...
                                            </>
                                        ) : (
                                            <>
                                                <Key className="mr-2 h-4 w-4" />
                                                Sign in with passkey
                                            </>
                                        )}
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="text-muted-foreground text-center text-sm">
                        Don&apos;t have an account?{" "}
                        <Link href="/signup" className="text-primary font-semibold underline-offset-4 transition-all hover:underline">
                            Sign up
                        </Link>
                    </div>
                </div>
            </form>

            <div className="text-muted-foreground/60 hover:[&_a]:text-primary text-center text-xs text-balance [&_a]:underline [&_a]:underline-offset-4 [&_a]:transition-colors">
                By clicking continue, you agree to our <Link href="/terms-of-service">ToS</Link> and{" "}
                <Link href="/privacy-policy">Privacy Policy</Link>.
            </div>
        </div>
    );
}
