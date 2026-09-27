import { Link, router, useForm } from "@inertiajs/react";
import axios from "axios";
import {
    Eye,
    EyeOff,
    Fingerprint,
    GraduationCap,
    Key,
    Loader2,
    Lock,
    Mail,
    ShieldCheck,
    UserRoundCog,
} from "lucide-react";
import { useCallback, useEffect, useState, type ComponentPropsWithoutRef, type FormEvent } from "react";
import { toast } from "sonner";

import { SocialAuthButtons } from "@/components/social-auth-buttons";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from "@/components/ui/input-group";
import { cn } from "@/lib/utils";

declare const route: (name: string, params?: Record<string, unknown>) => string;

const isWebAuthnSupported = (): boolean => {
    return typeof window !== "undefined" && !!(window.PublicKeyCredential && navigator.credentials);
};

export type DemoAccount = {
    role: string;
    label: string;
    description: string;
};

export type DemoMode = {
    enabled: boolean;
    accounts: DemoAccount[];
};

const demoAccountIcons: Record<string, typeof GraduationCap> = {
    student: GraduationCap,
    faculty: UserRoundCog,
    admin: ShieldCheck,
};

export interface LoginFormProps extends ComponentPropsWithoutRef<"div"> {
    demoMode?: DemoMode;
    errors?: Record<string, string>;
    status?: string | null;
}

export function LoginForm({ className, demoMode, errors, status, ...props }: LoginFormProps) {
    const { data, setData, post, processing } = useForm({
        email: "",
        password: "",
        remember: false,
    });

    const [showPassword, setShowPassword] = useState(false);
    const [loggingInWithPasskey, setLoggingInWithPasskey] = useState(false);
    const [passkeyAvailable, setPasskeyAvailable] = useState(false);

    useEffect(() => {
        const checkPasskeySupport = async () => {
            let supported = isWebAuthnSupported();
            if (supported && window.PublicKeyCredential?.isUserVerifyingPlatformAuthenticatorAvailable) {
                supported = await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            }
            setPasskeyAvailable(supported);
        };
        checkPasskeySupport();
    }, []);

    useEffect(() => {
        if (status) {
            toast.success(status);
        }
    }, [status]);

    useEffect(() => {
        if (errors && Object.keys(errors).length > 0) {
            Object.values(errors).forEach((message) => {
                toast.error(message);
            });
        }
    }, [errors]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post("/login", {
            onError: (formErrors) => {
                Object.values(formErrors).forEach((err) => {
                    toast.error(err);
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

        try {
            const optionsResponse = await axios.post("/passkeys/options", {});
            const options = optionsResponse.data.options;

            const challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, "+").replace(/_/g, "/")), (c) =>
                c.charCodeAt(0)
            );

            const allowCredentials =
                Array.isArray(options.allowCredentials) && options.allowCredentials.length > 0
                    ? options.allowCredentials.map((cred: { id: string }) => ({
                          ...cred,
                          id: Uint8Array.from(atob(cred.id.replace(/-/g, "+").replace(/_/g, "/")), (c) =>
                              c.charCodeAt(0)
                          ),
                      }))
                    : [];

            const publicKey: PublicKeyCredentialRequestOptions = {
                ...options,
                challenge,
                allowCredentials,
            };

            const credential = (await navigator.credentials.get({ publicKey })) as PublicKeyCredential;
            if (!credential) {
                throw new Error("Failed to get credential");
            }

            const rawId = btoa(String.fromCharCode(...new Uint8Array(credential.rawId)))
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");
            const assertionResponse = credential.response as AuthenticatorAssertionResponse;
            const authenticatorData = btoa(String.fromCharCode(...new Uint8Array(assertionResponse.authenticatorData)))
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");
            const clientDataJSON = btoa(String.fromCharCode(...new Uint8Array(assertionResponse.clientDataJSON)))
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");
            const signature = btoa(String.fromCharCode(...new Uint8Array(assertionResponse.signature)))
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");
            const userHandle = assertionResponse.userHandle
                ? btoa(String.fromCharCode(...new Uint8Array(assertionResponse.userHandle)))
                      .replace(/\+/g, "-")
                      .replace(/\//g, "_")
                      .replace(/=+$/, "")
                : null;

            const passkeyData = {
                id: credential.id,
                rawId,
                type: credential.type,
                response: {
                    authenticatorData,
                    clientDataJSON,
                    signature,
                    userHandle,
                },
            };

            const verifyResponse = await axios.post("/passkeys/login", {
                credential: passkeyData,
            });

            const redirectUrl = verifyResponse.data.url ?? verifyResponse.data.redirect;
            if (redirectUrl) {
                toast.success("Welcome back!");
                window.location.href = redirectUrl;
            } else {
                toast.error("Passkey verification failed.");
            }
        } catch (error: unknown) {
            const err = error as { response?: { data?: { error?: string } }; name?: string };
            if (err.response?.data?.error) {
                toast.error(err.response.data.error);
            } else if (err.name === "InvalidStateError") {
                toast.error("No passkey found. Please sign in with your password.");
            } else if (err.name !== "NotAllowedError") {
                console.error("Passkey Error:", error);
                toast.error("Failed to sign in with passkey. Please try again.");
            }
        } finally {
            setLoggingInWithPasskey(false);
        }
    }, [loggingInWithPasskey]);

    const handleDemoLogin = (role: string) => {
        router.post(
            route("demo.login", { role }),
            {},
            {
                onStart: () => toast.info(`Opening ${role} demo workspace...`),
                onError: () => toast.error("Demo login is unavailable. Please try again."),
            }
        );
    };

    return (
        <div className={cn("flex flex-col gap-6", className)} {...props}>
            <form onSubmit={submit}>
                <FieldGroup className="gap-4">
                    {/* Email field */}
                    <Field>
                        <FieldLabel htmlFor="email" className="text-sm font-medium text-zinc-200">
                            Email or username
                        </FieldLabel>
                        <InputGroup
                            className={cn(
                                "h-10 rounded-lg border-zinc-800 bg-zinc-900/60 shadow-xs transition-colors focus-within:border-zinc-700 focus-within:ring-2 focus-within:ring-zinc-700/40",
                                errors?.email && "border-destructive focus-within:ring-destructive/20"
                            )}
                        >
                            <InputGroupAddon align="inline-start" className="text-zinc-500 pl-3">
                                <Mail className="size-4" />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="email"
                                type="text"
                                placeholder="name@school.edu"
                                required
                                autoFocus
                                value={data.email}
                                onChange={(e) => setData("email", e.target.value)}
                                disabled={processing || loggingInWithPasskey}
                                className="text-sm text-zinc-100 placeholder:text-zinc-500 font-normal px-2"
                            />
                        </InputGroup>
                        {errors?.email && <FieldError errors={[{ message: errors.email }]} />}
                    </Field>

                    {/* Password field */}
                    <Field>
                        <div className="flex items-center justify-between">
                            <FieldLabel htmlFor="password" className="text-sm font-medium text-zinc-200">
                                Password
                            </FieldLabel>
                            <Link
                                href="/forgot-password"
                                className="text-xs text-zinc-400 hover:text-zinc-200 hover:underline transition-colors"
                            >
                                Forgot password?
                            </Link>
                        </div>
                        <InputGroup
                            className={cn(
                                "h-10 rounded-lg border-zinc-800 bg-zinc-900/60 shadow-xs transition-colors focus-within:border-zinc-700 focus-within:ring-2 focus-within:ring-zinc-700/40",
                                errors?.password && "border-destructive focus-within:ring-destructive/20"
                            )}
                        >
                            <InputGroupAddon align="inline-start" className="text-zinc-500 pl-3">
                                <Lock className="size-4" />
                            </InputGroupAddon>
                            <InputGroupInput
                                id="password"
                                type={showPassword ? "text" : "password"}
                                placeholder="Enter your password"
                                required
                                value={data.password}
                                onChange={(e) => setData("password", e.target.value)}
                                disabled={processing || loggingInWithPasskey}
                                className="text-sm text-zinc-100 placeholder:text-zinc-500 font-normal px-2"
                            />
                            <InputGroupAddon align="inline-end" className="pr-1.5">
                                <InputGroupButton
                                    size="icon-xs"
                                    onClick={() => setShowPassword(!showPassword)}
                                    aria-label={showPassword ? "Hide password" : "Show password"}
                                    className="text-zinc-400 hover:text-zinc-200"
                                >
                                    {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </InputGroupButton>
                            </InputGroupAddon>
                        </InputGroup>
                        {errors?.password && <FieldError errors={[{ message: errors.password }]} />}
                    </Field>

                    {/* Remember me option */}
                    <div className="flex items-center space-x-2 pt-0.5">
                        <Checkbox
                            id="remember"
                            checked={data.remember}
                            onCheckedChange={(checked) => setData("remember", Boolean(checked))}
                            disabled={processing || loggingInWithPasskey}
                            className="border-zinc-700 data-[state=checked]:bg-white data-[state=checked]:text-zinc-950"
                        />
                        <label
                            htmlFor="remember"
                            className="text-xs font-normal text-zinc-400 peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer select-none"
                        >
                            Remember this device for 30 days
                        </label>
                    </div>

                    {/* Submit button - High contrast solid white */}
                    <Button
                        type="submit"
                        className="h-10 w-full rounded-lg bg-white font-semibold text-zinc-950 shadow-sm transition-colors hover:bg-zinc-200 disabled:opacity-50 text-sm mt-1"
                        disabled={processing || loggingInWithPasskey}
                    >
                        {processing ? (
                            <>
                                <Loader2 className="mr-2 size-4 animate-spin text-zinc-950" />
                                <span>Signing in...</span>
                            </>
                        ) : (
                            "Sign in"
                        )}
                    </Button>

                    {/* Demo mode section */}
                    {demoMode?.enabled && demoMode.accounts.length > 0 && (
                        <div className="rounded-xl border border-zinc-800 bg-zinc-900/50 p-3 space-y-2 mt-1">
                            <div className="text-center space-y-0.5">
                                <p className="text-xs font-semibold text-zinc-200">Explore Demo Workspaces</p>
                                <p className="text-[11px] text-zinc-400">Select a persona to test the portal immediately</p>
                            </div>
                            <div className="grid gap-1.5">
                                {demoMode.accounts.map((account) => {
                                    const Icon = demoAccountIcons[account.role] ?? Key;
                                    return (
                                        <button
                                            key={account.role}
                                            type="button"
                                            onClick={() => handleDemoLogin(account.role)}
                                            disabled={processing || loggingInWithPasskey}
                                            className="group flex w-full items-center gap-2.5 rounded-lg border border-zinc-800 bg-zinc-900/80 p-2 text-left transition-colors hover:border-zinc-700 hover:bg-zinc-800/80 disabled:opacity-50"
                                        >
                                            <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-zinc-800 text-zinc-300 group-hover:text-white">
                                                <Icon className="size-3.5" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="text-xs font-medium text-zinc-200 group-hover:text-white transition-colors">
                                                    {account.label}
                                                </div>
                                                <div className="truncate text-[10px] text-zinc-400">
                                                    {account.description}
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Social auth */}
                    <SocialAuthButtons />

                    {/* Passkey authentication */}
                    {passkeyAvailable && (
                        <div className="space-y-3">
                            <div className="relative my-1">
                                <div className="absolute inset-0 flex items-center">
                                    <span className="w-full border-t border-zinc-800" />
                                </div>
                                <div className="relative flex justify-center text-xs">
                                    <span className="bg-zinc-950 px-2 text-zinc-400">Or passwordless</span>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-10 w-full rounded-lg border-zinc-800 bg-zinc-900/60 text-zinc-200 hover:bg-zinc-800 hover:text-white transition-colors text-sm font-medium gap-2"
                                onClick={handlePasskeyLogin}
                                disabled={processing || loggingInWithPasskey}
                            >
                                {loggingInWithPasskey ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin text-zinc-400" />
                                        <span>Authenticating with passkey...</span>
                                    </>
                                ) : (
                                    <>
                                        <Fingerprint className="size-4 text-zinc-300" />
                                        <span>Sign in with Passkey / Biometrics</span>
                                    </>
                                )}
                            </Button>
                        </div>
                    )}

                    {/* Register link */}
                    <div className="pt-2 text-center text-sm text-zinc-400">
                        Need an account?{" "}
                        <Link href="/signup" className="font-semibold text-white underline-offset-4 hover:underline">
                            Sign up
                        </Link>
                    </div>
                </FieldGroup>
            </form>
        </div>
    );
}
