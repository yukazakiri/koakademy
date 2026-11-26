import { evaluatePasswordPolicy, PasswordRequirements, type PasswordPolicy } from "@/components/auth/password-requirements";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemSeparator, ItemTitle } from "@/components/ui/item";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { router, usePage } from "@inertiajs/react";
import axios from "axios";
import { Copy, Download, Eye, EyeOff, Key, Loader2, Mail, QrCode, ShieldCheck, ShieldOff, Smartphone, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

type PasswordConfirmedAction = "disable_app" | "pause_2fa" | "disable_email" | "regenerate_codes" | "delete_passkey" | null;

interface PasskeyItem {
    id: number;
    name: string;
    created_at: string;
    last_used_at?: string | null;
}

interface ApiTokenItem {
    id: number;
    name: string;
    abilities?: string[];
    created_at: string;
    last_used_at?: string | null;
    expires_at?: string | null;
}

interface SecuritySectionProps {
    isFaculty: boolean;
    isStudent: boolean;
    user: {
        security_two_factor_enabled: boolean;
        two_factor_enabled: boolean;
        email_two_factor_enabled: boolean;
        has_password?: boolean;
        recovery_codes_count?: number;
        recovery_codes?: string[];
    };
    paths: {
        password_update: string;
        passkeys: string;
        passkeys_options: string;
        two_factor_enable: string;
        two_factor_confirm: string;
        two_factor_disable: string;
        two_factor_recovery_codes: string;
        security_two_factor_toggle: string;
        email_auth_toggle: string;
        api_keys: string;
    };
    developerModeEnabled?: boolean;
}

export function SecuritySection({ isFaculty, isStudent, user, paths, developerModeEnabled = false }: SecuritySectionProps) {
    const pageProps = usePage<{
        passwordPolicy?: Partial<PasswordPolicy>;
        newly_generated_recovery_codes?: string[] | null;
    }>().props;

    const [passkeys, setPasskeys] = useState<PasskeyItem[]>([]);
    const [passkeyFormOpen, setPasskeyFormOpen] = useState(false);
    const [passkeySubmitting, setPasskeySubmitting] = useState(false);
    const [passkeyForm, setPasskeyForm] = useState({ name: "" });
    const [apiTokens, setApiTokens] = useState<ApiTokenItem[]>([]);
    const [addingApiKey, setAddingApiKey] = useState(false);
    const [newlyCreatedToken, setNewlyCreatedToken] = useState<string | null>(null);
    const [apiKeyForm, setApiKeyForm] = useState({
        name: "",
        abilities: ["read"] as string[],
        expires_at: "",
    });
    const [passwordForm, setPasswordForm] = useState({
        current_password: "",
        password: "",
        password_confirmation: "",
    });
    const [passwordErrors, setPasswordErrors] = useState<Record<string, string>>({});
    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [passwordProcessing, setPasswordProcessing] = useState(false);
    const [twoFactorEnabling, setTwoFactorEnabling] = useState(false);
    const [twoFactorData, setTwoFactorData] = useState<{ secret: string; qr_code: string } | null>(null);
    const [twoFactorForm, setTwoFactorForm] = useState({ code: "" });
    const [twoFactorError, setTwoFactorError] = useState<string | null>(null);
    const [twoFactorProcessing, setTwoFactorProcessing] = useState(false);
    const [visibleRecoveryCodes, setVisibleRecoveryCodes] = useState<string[] | null>(
        pageProps.newly_generated_recovery_codes ?? null,
    );
    const [securityTwoFactorProcessing, setSecurityTwoFactorProcessing] = useState(false);
    const [confirmAction, setConfirmAction] = useState<PasswordConfirmedAction>(null);
    const [confirmPassword, setConfirmPassword] = useState("");
    const [confirmPasswordError, setConfirmPasswordError] = useState<string | null>(null);
    const [confirmProcessing, setConfirmProcessing] = useState(false);
    const [passkeyIdPendingDelete, setPasskeyIdPendingDelete] = useState<number | null>(null);
    const [apiKeyIdPendingDelete, setApiKeyIdPendingDelete] = useState<number | null>(null);

    useEffect(() => {
        if (Array.isArray(pageProps.newly_generated_recovery_codes) && pageProps.newly_generated_recovery_codes.length > 0) {
            setVisibleRecoveryCodes(pageProps.newly_generated_recovery_codes);
        }
    }, [pageProps.newly_generated_recovery_codes]);

    useEffect(() => {
        fetchPasskeys();
        if (developerModeEnabled) {
            fetchApiTokens();
        }
    }, [developerModeEnabled]);

    const fetchPasskeys = () => {
        axios
            .get(paths.passkeys)
            .then((response) => {
                setPasskeys(response.data.passkeys);
            })
            .catch((error) => {
                console.error("Failed to fetch passkeys", error);
            });
    };

    const fetchApiTokens = () => {
        axios
            .get(paths.api_keys)
            .then((response) => {
                setApiTokens(response.data.tokens);
            })
            .catch((error) => {
                console.error("Failed to fetch API tokens", error);
            });
    };

    const hasPassword = user.has_password ?? true;
    const newPasswordCheck = evaluatePasswordPolicy(passwordForm.password, pageProps.passwordPolicy);

    const handlePasswordSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setPasswordProcessing(true);
        setPasswordErrors({});
        const payload = hasPassword
            ? passwordForm
            : {
                  password: passwordForm.password,
                  password_confirmation: passwordForm.password_confirmation,
              };
        router.put(paths.password_update, payload, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    hasPassword
                        ? "Password changed successfully"
                        : "Password created! You can now sign in with your email and password too.",
                );
                setPasswordForm({ current_password: "", password: "", password_confirmation: "" });
                setPasswordErrors({});
                setPasswordProcessing(false);
            },
            onError: (errors) => {
                setPasswordErrors(errors);
                const first = Object.values(errors)[0];
                toast.error(first || "Failed to update password. Please check your inputs.");
                setPasswordProcessing(false);
            },
        });
    };

    const handleEnableTwoFactor = () => {
        setTwoFactorEnabling(true);
        setTwoFactorError(null);
        axios
            .post<{ secret: string; qr_code: string }>(paths.two_factor_enable)
            .then((response) => {
                setTwoFactorData(response.data);
            })
            .catch(() => {
                toast.error("Failed to start two-factor authentication setup.");
                setTwoFactorEnabling(false);
            });
    };

    const handleConfirmTwoFactor = (e: React.FormEvent) => {
        e.preventDefault();
        setTwoFactorProcessing(true);
        setTwoFactorError(null);
        router.post(
            paths.two_factor_confirm,
            { code: twoFactorForm.code },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTwoFactorEnabling(false);
                    setTwoFactorData(null);
                    setTwoFactorForm({ code: "" });
                    toast.success("Two-factor authentication enabled successfully!");
                    setTwoFactorProcessing(false);
                },
                onError: (errors) => {
                    const msg = errors.code ?? "Invalid authentication code.";
                    setTwoFactorError(msg);
                    toast.error(msg);
                    setTwoFactorProcessing(false);
                },
            },
        );
    };

    const openPasswordConfirm = (action: Exclude<PasswordConfirmedAction, null>) => {
        setConfirmPassword("");
        setConfirmPasswordError(null);
        setConfirmAction(action);
    };

    const handleToggleEmailAuth = (checked: boolean) => {
        if (!checked) {
            openPasswordConfirm("disable_email");
            return;
        }
        router.post(
            paths.email_auth_toggle,
            { enabled: true },
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Email authentication enabled."),
                onError: (errors) => toast.error(Object.values(errors)[0] || "Failed to update email authentication."),
            },
        );
    };

    const handleToggleSecurityTwoFactor = (checked: boolean) => {
        if (!checked) {
            openPasswordConfirm("pause_2fa");
            return;
        }
        setSecurityTwoFactorProcessing(true);
        router.post(
            paths.security_two_factor_toggle,
            { enabled: true },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("2FA login protection is on.");
                    setSecurityTwoFactorProcessing(false);
                },
                onError: (errors) => {
                    toast.error(Object.values(errors)[0] || "Failed to update 2FA login protection.");
                    setSecurityTwoFactorProcessing(false);
                },
            },
        );
    };

    const submitPasswordConfirmedAction = (e: React.FormEvent) => {
        e.preventDefault();
        if (!confirmAction || !confirmPassword) return;

        setConfirmProcessing(true);
        setConfirmPasswordError(null);

        const onError = (errors: Record<string, string>) => {
            const msg = errors.password ?? Object.values(errors)[0] ?? "Incorrect password.";
            setConfirmPasswordError(msg);
            toast.error(msg);
        };

        const closeDialog = () => {
            setConfirmAction(null);
            setConfirmPassword("");
            setConfirmPasswordError(null);
        };

        if (confirmAction === "disable_app") {
            router.delete(paths.two_factor_disable, {
                data: { password: confirmPassword },
                preserveScroll: true,
                onSuccess: () => {
                    closeDialog();
                    setVisibleRecoveryCodes(null);
                    toast.success("Authenticator app removed.");
                },
                onError,
                onFinish: () => setConfirmProcessing(false),
            });
            return;
        }

        if (confirmAction === "pause_2fa") {
            router.post(
                paths.security_two_factor_toggle,
                { enabled: false, password: confirmPassword },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        closeDialog();
                        toast.success("2FA login protection is paused.");
                    },
                    onError,
                    onFinish: () => setConfirmProcessing(false),
                },
            );
            return;
        }

        if (confirmAction === "disable_email") {
            router.post(
                paths.email_auth_toggle,
                { enabled: false, password: confirmPassword },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        closeDialog();
                        toast.success("Email authentication disabled.");
                    },
                    onError,
                    onFinish: () => setConfirmProcessing(false),
                },
            );
            return;
        }

        if (confirmAction === "regenerate_codes") {
            router.post(
                paths.two_factor_recovery_codes,
                { password: confirmPassword },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        closeDialog();
                        toast.success("Recovery codes regenerated.");
                    },
                    onError,
                    onFinish: () => setConfirmProcessing(false),
                },
            );
            return;
        }

        if (confirmAction === "delete_passkey" && passkeyIdPendingDelete !== null) {
            const id = passkeyIdPendingDelete;
            router.delete(`${paths.passkeys}/${id}`, {
                data: { password: confirmPassword },
                preserveScroll: true,
                onSuccess: () => {
                    closeDialog();
                    setPasskeyIdPendingDelete(null);
                    toast.success("Passkey deleted.");
                    fetchPasskeys();
                },
                onError,
                onFinish: () => setConfirmProcessing(false),
            });
        }
    };

    const copyRecoveryCodes = async () => {
        if (!visibleRecoveryCodes) return;
        try {
            await navigator.clipboard.writeText(visibleRecoveryCodes.join("\n"));
            toast.success("Recovery codes copied to clipboard.");
        } catch {
            toast.error("Failed to copy recovery codes.");
        }
    };

    const downloadRecoveryCodes = () => {
        if (!visibleRecoveryCodes) return;
        const blob = new Blob([visibleRecoveryCodes.join("\n") + "\n"], { type: "text/plain;charset=utf-8" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "recovery-codes.txt";
        a.click();
        URL.revokeObjectURL(url);
    };

    const handleAddPasskey = async (e: React.FormEvent) => {
        e.preventDefault();
        if (passkeySubmitting) return;
        setPasskeySubmitting(true);

        try {
            const optionsResponse = await axios.post(paths.passkeys_options);
            const options = optionsResponse.data.options;

            const challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, "+").replace(/_/g, "/")), (c) => c.charCodeAt(0));
            const userId = Uint8Array.from(atob(options.user.id.replace(/-/g, "+").replace(/_/g, "/")), (c) => c.charCodeAt(0));

            const publicKey: PublicKeyCredentialCreationOptions = {
                ...options,
                challenge,
                user: {
                    ...options.user,
                    id: userId,
                },
                excludeCredentials: options.excludeCredentials?.map((cred: { id: string; type: PublicKeyCredentialType }) => ({
                    ...cred,
                    id: Uint8Array.from(atob(cred.id.replace(/-/g, "+").replace(/_/g, "/")), (c) => c.charCodeAt(0)),
                })),
            };

            const credential = (await navigator.credentials.create({ publicKey })) as PublicKeyCredential;

            if (!credential) {
                throw new Error("Failed to create credential");
            }

            const rawId = btoa(String.fromCharCode(...new Uint8Array(credential.rawId)))
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");
            const attestationObject = btoa(
                String.fromCharCode(...new Uint8Array((credential.response as AuthenticatorAttestationResponse).attestationObject)),
            )
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");
            const clientDataJSON = btoa(
                String.fromCharCode(...new Uint8Array((credential.response as AuthenticatorAttestationResponse).clientDataJSON)),
            )
                .replace(/\+/g, "-")
                .replace(/\//g, "_")
                .replace(/=+$/, "");

            const response = credential.response as AuthenticatorAttestationResponse & { getTransports?: () => string[] };
            const transports = response.getTransports ? response.getTransports() : [];

            const passkeyData = {
                id: credential.id,
                rawId,
                type: credential.type,
                response: {
                    attestationObject,
                    clientDataJSON,
                    transports: transports,
                },
            };

            router.post(
                paths.passkeys,
                {
                    credential: passkeyData,
                    name: passkeyForm.name,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success("Passkey added successfully!");
                        setPasskeyForm({ name: "" });
                        setPasskeyFormOpen(false);
                        setPasskeySubmitting(false);
                        fetchPasskeys();
                    },
                    onError: (errors) => {
                        toast.error(Object.values(errors)[0] || "Failed to register passkey.");
                        setPasskeySubmitting(false);
                    },
                },
            );
        } catch (error) {
            console.error(error);
            toast.error("Failed to add passkey. Please try again.");
            setPasskeySubmitting(false);
        }
    };

    const handleDeletePasskey = (id: number) => {
        setPasskeyIdPendingDelete(id);
        openPasswordConfirm("delete_passkey");
    };

    const handleAddApiKey = async (e: React.FormEvent) => {
        e.preventDefault();

        try {
            const response = await axios.post(paths.api_keys, {
                name: apiKeyForm.name,
                abilities: apiKeyForm.abilities,
                expires_at: apiKeyForm.expires_at || undefined,
            });

            if (response.data.token) {
                setNewlyCreatedToken(response.data.token);
                toast.success("API key created successfully!");
                setApiKeyForm({ name: "", abilities: ["read"], expires_at: "" });
                fetchApiTokens();
            }
        } catch (error: unknown) {
            console.error("Failed to create API key", error);
            const axiosErr = error as { response?: { data?: { errors?: Record<string, string[]> } } };
            if (axiosErr.response?.data?.errors) {
                Object.values(axiosErr.response.data.errors).forEach((msgs) => {
                    msgs.forEach((msg) => toast.error(msg));
                });
            } else {
                toast.error("Failed to create API key.");
            }
        }
    };

    const handleDeleteApiKey = (id: number) => {
        setApiKeyIdPendingDelete(id);
    };

    const confirmDeleteApiKey = async () => {
        if (apiKeyIdPendingDelete === null) return;
        const id = apiKeyIdPendingDelete;
        setApiKeyIdPendingDelete(null);
        try {
            await axios.delete(`${paths.api_keys}/${id}`);
            toast.success("API key deleted successfully.");
            fetchApiTokens();
        } catch (error) {
            console.error("Failed to delete API key", error);
            toast.error("Failed to delete API key.");
        }
    };

    const copyTokenToClipboard = async (token: string) => {
        try {
            await navigator.clipboard.writeText(token);
            toast.success("API key copied to clipboard!");
        } catch {
            toast.error("Failed to copy API key.");
        }
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center justify-between gap-2">
                        <span className="flex items-center gap-2">
                            <ShieldCheck className="h-5 w-5" />
                            {hasPassword ? "Password" : "Set a Portal Password"}
                        </span>
                        {!hasPassword && <Badge variant="secondary">Social sign-in only</Badge>}
                    </CardTitle>
                    <CardDescription>
                        {hasPassword
                            ? "Update your password"
                            : "You activated your account with social sign-in. Create a password anytime if you also want to sign in with your email and password."}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handlePasswordSubmit} className="space-y-4">
                        {hasPassword && (
                            <div className="space-y-2">
                                <Label htmlFor="current_password">Current Password</Label>
                                <div className="relative">
                                    <Input
                                        id="current_password"
                                        type={showCurrentPassword ? "text" : "password"}
                                        autoComplete="current-password"
                                        value={passwordForm.current_password}
                                        onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })}
                                        className="pr-10"
                                        required
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="text-muted-foreground hover:text-foreground absolute top-0 right-0 h-full px-3"
                                        onClick={() => setShowCurrentPassword((v) => !v)}
                                        aria-label={showCurrentPassword ? "Hide current password" : "Show current password"}
                                    >
                                        {showCurrentPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </Button>
                                </div>
                                {passwordErrors.current_password && <p className="text-destructive text-xs">{passwordErrors.current_password}</p>}
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="password">New Password</Label>
                            <div className="relative">
                                <Input
                                    id="password"
                                    type={showNewPassword ? "text" : "password"}
                                    autoComplete="new-password"
                                    value={passwordForm.password}
                                    onChange={(e) => setPasswordForm({ ...passwordForm, password: e.target.value })}
                                    className="pr-10"
                                    required
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="text-muted-foreground hover:text-foreground absolute top-0 right-0 h-full px-3"
                                    onClick={() => setShowNewPassword((v) => !v)}
                                    aria-label={showNewPassword ? "Hide new password" : "Show new password"}
                                >
                                    {showNewPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </Button>
                            </div>
                            <PasswordRequirements password={passwordForm.password} policy={pageProps.passwordPolicy} />
                            {passwordErrors.password && <p className="text-destructive text-xs">{passwordErrors.password}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="password_confirmation">Confirm Password</Label>
                            <div className="relative">
                                <Input
                                    id="password_confirmation"
                                    type={showConfirmPassword ? "text" : "password"}
                                    autoComplete="new-password"
                                    value={passwordForm.password_confirmation}
                                    onChange={(e) => setPasswordForm({ ...passwordForm, password_confirmation: e.target.value })}
                                    className="pr-10"
                                    required
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="text-muted-foreground hover:text-foreground absolute top-0 right-0 h-full px-3"
                                    onClick={() => setShowConfirmPassword((v) => !v)}
                                    aria-label={showConfirmPassword ? "Hide confirm password" : "Show confirm password"}
                                >
                                    {showConfirmPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </Button>
                            </div>
                            {passwordErrors.password_confirmation && (
                                <p className="text-destructive text-xs">{passwordErrors.password_confirmation}</p>
                            )}
                        </div>
                        <Button
                            type="submit"
                            disabled={
                                passwordProcessing ||
                                (hasPassword && !passwordForm.current_password) ||
                                !newPasswordCheck.allMet ||
                                passwordForm.password !== passwordForm.password_confirmation
                            }
                        >
                            {passwordProcessing
                                ? hasPassword
                                    ? "Updating..."
                                    : "Creating..."
                                : hasPassword
                                  ? "Update Password"
                                  : "Create Password"}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <QrCode className="h-5 w-5" />
                        Two-Factor Authentication
                    </CardTitle>
                    <CardDescription>Choose whether saved security methods are required when you sign in</CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <Alert className={user.security_two_factor_enabled ? "border-primary/30 bg-primary/5" : "border-amber-500/30 bg-amber-500/10"}>
                        {user.security_two_factor_enabled ? <ShieldCheck className="h-4 w-4" /> : <ShieldOff className="h-4 w-4" />}
                        <AlertTitle>{user.security_two_factor_enabled ? "Login protection is on" : "Login protection is paused"}</AlertTitle>
                        <AlertDescription>
                            {user.security_two_factor_enabled
                                ? "After your password, you may be asked for one of your saved security methods."
                                : "You will sign in with your password only. Your passkeys, email option, and authenticator setup stay saved."}
                        </AlertDescription>
                    </Alert>

                    <Item variant="outline" className="items-center">
                        <ItemMedia variant="icon">
                            {user.security_two_factor_enabled ? <ShieldCheck className="h-4 w-4" /> : <ShieldOff className="h-4 w-4" />}
                        </ItemMedia>
                        <ItemContent>
                            <ItemTitle>Require security check after password</ItemTitle>
                            <ItemDescription>This is the only switch that controls whether login asks for 2FA.</ItemDescription>
                        </ItemContent>
                        <ItemActions>
                            <Badge variant={user.security_two_factor_enabled ? "default" : "secondary"}>
                                {user.security_two_factor_enabled ? "On" : "Paused"}
                            </Badge>
                            <Switch
                                id="security-two-factor"
                                checked={user.security_two_factor_enabled}
                                disabled={securityTwoFactorProcessing}
                                onCheckedChange={handleToggleSecurityTwoFactor}
                            />
                        </ItemActions>
                    </Item>

                    <div className="space-y-3">
                        <div>
                            <h3 className="text-sm font-medium">Saved security methods</h3>
                            <p className="text-muted-foreground text-xs">These stay saved even when login protection is paused.</p>
                        </div>

                        <ItemGroup className="rounded-lg border">
                            <Item>
                                <ItemMedia variant="icon">
                                    <Key className="h-4 w-4" />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>Passkeys</ItemTitle>
                                    <ItemDescription>
                                        {passkeys.length > 0
                                            ? `${passkeys.length} passkey${passkeys.length === 1 ? "" : "s"} saved for quick verification.`
                                            : "No passkeys saved yet."}
                                    </ItemDescription>
                                </ItemContent>
                                <ItemActions>
                                    <Badge variant={passkeys.length > 0 ? "outline" : "secondary"}>{passkeys.length > 0 ? "Saved" : "Not set"}</Badge>
                                </ItemActions>
                            </Item>
                            <ItemSeparator />
                            <Item>
                                <ItemMedia variant="icon">
                                    <Mail className="h-4 w-4" />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>Email codes</ItemTitle>
                                    <ItemDescription>Receive a verification code by email when login protection is on.</ItemDescription>
                                </ItemContent>
                                <ItemActions>
                                    <Switch id="email-auth" checked={user.email_two_factor_enabled} onCheckedChange={handleToggleEmailAuth} />
                                </ItemActions>
                            </Item>
                            <ItemSeparator />
                            <Item>
                                <ItemMedia variant="icon">
                                    <Smartphone className="h-4 w-4" />
                                </ItemMedia>
                                <ItemContent>
                                    <ItemTitle>Authenticator app</ItemTitle>
                                    <ItemDescription>
                                        {user.two_factor_enabled
                                            ? "An authenticator app is saved for this account."
                                            : "Use Google Authenticator, Authy, or a compatible app."}
                                    </ItemDescription>
                                </ItemContent>
                                <ItemActions>
                                    <Badge variant={user.two_factor_enabled ? "outline" : "secondary"}>
                                        {user.two_factor_enabled ? "Saved" : "Not set"}
                                    </Badge>
                                </ItemActions>
                            </Item>
                        </ItemGroup>
                    </div>
                    <Separator />
                    {user.two_factor_enabled ? (
                        <>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-muted-foreground text-xs">
                                    {typeof user.recovery_codes_count === "number"
                                        ? `${user.recovery_codes_count} unused recovery code${user.recovery_codes_count === 1 ? "" : "s"} remaining.`
                                        : "Recovery codes are stored securely."}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <Button variant="outline" onClick={() => openPasswordConfirm("regenerate_codes")}>
                                        Regenerate Recovery Codes
                                    </Button>
                                    <Button variant="destructive" onClick={() => openPasswordConfirm("disable_app")}>
                                        Remove Authenticator App
                                    </Button>
                                </div>
                            </div>
                            {visibleRecoveryCodes && visibleRecoveryCodes.length > 0 && (
                                <div className="border-primary/30 bg-primary/5 mt-4 space-y-3 rounded-lg border p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm font-medium">Save Your Recovery Codes</p>
                                        <div className="flex gap-1.5">
                                            <Button type="button" size="sm" variant="outline" onClick={copyRecoveryCodes}>
                                                <Copy className="mr-1.5 size-3.5" />
                                                Copy
                                            </Button>
                                            <Button type="button" size="sm" variant="outline" onClick={downloadRecoveryCodes}>
                                                <Download className="mr-1.5 size-3.5" />
                                                Download
                                            </Button>
                                        </div>
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        Store these codes in a secure place now. For your security, they will not be shown again after you leave this page.
                                    </p>
                                    <div className="bg-background/80 grid grid-cols-2 gap-2 rounded-md border p-3 font-mono text-xs sm:text-sm">
                                        {visibleRecoveryCodes.map((code, index) => (
                                            <div key={index} className="select-all">
                                                {code}
                                            </div>
                                        ))}
                                    </div>
                                    <Button type="button" size="sm" variant="ghost" onClick={() => setVisibleRecoveryCodes(null)}>
                                        I have saved these codes
                                    </Button>
                                </div>
                            )}
                        </>
                    ) : (
                        <>
                            {!twoFactorEnabling ? (
                                <div className="space-y-4">
                                    <div className="flex flex-col space-y-1">
                                        <span className="text-sm font-medium">Authenticator App</span>
                                        <p className="text-muted-foreground text-sm">
                                            Use an authenticator app like Google Authenticator or Authy to secure your account.
                                        </p>
                                    </div>
                                    <Button onClick={handleEnableTwoFactor}>Enable Authenticator App</Button>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">1. Scan the QR Code</p>
                                        <p className="text-muted-foreground text-xs">
                                            Use an authenticator app like Google Authenticator or Authy to scan the QR code.
                                        </p>
                                        {twoFactorData?.qr_code && (
                                            <div className="mx-auto flex w-fit justify-center rounded-lg bg-white p-4">
                                                <img src={twoFactorData.qr_code} alt="QR Code" className="h-48 w-48" />
                                            </div>
                                        )}
                                        <div className="text-center">
                                            <p className="text-muted-foreground text-xs">
                                                Setup Key: <span className="font-mono select-all">{twoFactorData?.secret}</span>
                                            </p>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">2. Enter Confirmation Code</p>
                                        <form onSubmit={handleConfirmTwoFactor} className="flex gap-2">
                                            <Input
                                                value={twoFactorForm.code}
                                                onChange={(e) => {
                                                    setTwoFactorForm({ code: e.target.value });
                                                    if (twoFactorError) setTwoFactorError(null);
                                                }}
                                                inputMode="numeric"
                                                autoComplete="one-time-code"
                                                placeholder="123 456"
                                                className="font-mono"
                                                required
                                            />
                                            <Button type="submit" disabled={twoFactorProcessing}>
                                                {twoFactorProcessing ? "Confirming..." : "Confirm"}
                                            </Button>
                                        </form>
                                        {twoFactorError && <p className="text-destructive text-xs">{twoFactorError}</p>}
                                    </div>
                                    <Button variant="ghost" size="sm" onClick={() => setTwoFactorEnabling(false)}>
                                        Cancel
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Key className="h-5 w-5" />
                        Passkeys
                    </CardTitle>
                    <CardDescription>
                        Sign in securely with your fingerprint, face recognition, or hardware key. Passkeys also serve as a second factor during
                        two-factor authentication.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-4">
                        {passkeys.length > 0 ? (
                            <div className="space-y-2">
                                {passkeys.map((passkey) => (
                                    <div key={passkey.id} className="flex items-center justify-between rounded-md border p-3">
                                        <div className="flex items-center gap-3">
                                            <Key className="text-muted-foreground h-5 w-5" />
                                            <div>
                                                <p className="font-medium">{passkey.name}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    Added {new Date(passkey.created_at).toLocaleDateString()}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleDeletePasskey(passkey.id)}
                                            aria-label={`Delete passkey ${passkey.name}`}
                                            className="text-destructive hover:text-destructive/90"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">No passkeys added yet.</p>
                        )}
                    </div>

                    {!passkeyFormOpen ? (
                        <Button onClick={() => setPasskeyFormOpen(true)}>Add Passkey</Button>
                    ) : (
                        <div className="bg-muted/50 space-y-4 rounded-md border p-4">
                            <form onSubmit={handleAddPasskey} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="passkey-name">Passkey Name</Label>
                                    <Input
                                        id="passkey-name"
                                        value={passkeyForm.name}
                                        onChange={(e) => setPasskeyForm({ name: e.target.value })}
                                        placeholder="e.g. MacBook Pro Touch ID"
                                        disabled={passkeySubmitting}
                                        required
                                        autoFocus
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={passkeySubmitting || !passkeyForm.name.trim()}>
                                        {passkeySubmitting ? (
                                            <>
                                                <Loader2 className="mr-2 size-4 animate-spin" />
                                                Waiting for device...
                                            </>
                                        ) : (
                                            "Create Passkey"
                                        )}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={passkeySubmitting}
                                        onClick={() => {
                                            setPasskeyFormOpen(false);
                                            setPasskeyForm({ name: "" });
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        </div>
                    )}
                </CardContent>
            </Card>

            {developerModeEnabled && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Key className="h-5 w-5" />
                            API Keys
                        </CardTitle>
                        <CardDescription>Manage API keys for accessing the portal programmatically. Developer mode must be enabled.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {newlyCreatedToken && (
                            <div className="bg-primary/10 border-primary/30 rounded-lg border p-4">
                                <p className="mb-2 font-medium">API Key Created!</p>
                                <p className="text-muted-foreground mb-3 text-sm">Copy this token now. You won&apos;t be able to see it again.</p>
                                <div className="flex items-center gap-2">
                                    <code className="bg-muted flex-1 rounded-md p-2 text-xs break-all">{newlyCreatedToken}</code>
                                    <Button size="sm" variant="outline" onClick={() => copyTokenToClipboard(newlyCreatedToken)}>
                                        Copy
                                    </Button>
                                </div>
                                <Button size="sm" variant="ghost" className="mt-2" onClick={() => setNewlyCreatedToken(null)}>
                                    Dismiss
                                </Button>
                            </div>
                        )}

                        <div className="space-y-4">
                            {apiTokens.length > 0 ? (
                                <div className="space-y-2">
                                    {apiTokens.map((token) => (
                                        <div key={token.id} className="flex items-center justify-between rounded-md border p-3">
                                            <div className="flex items-center gap-3">
                                                <Key className="text-muted-foreground h-5 w-5" />
                                                <div>
                                                    <p className="font-medium">{token.name}</p>
                                                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                        <span>Created {new Date(token.created_at).toLocaleDateString()}</span>
                                                        {token.last_used_at && <span>• Last used {token.last_used_at}</span>}
                                                        {token.expires_at && <span>• Expires {new Date(token.expires_at).toLocaleDateString()}</span>}
                                                    </div>
                                                    {token.abilities && token.abilities.length > 0 && token.abilities[0] !== "*" && (
                                                        <div className="mt-1 flex flex-wrap gap-1">
                                                            {token.abilities.map((ability: string) => {
                                                                if (ability === "mcp:read") {
                                                                    return (
                                                                        <Badge key={ability} variant="outline" className="border-blue-500/30 text-blue-600 dark:text-blue-400 text-[10px]">
                                                                            MCP Read
                                                                        </Badge>
                                                                    );
                                                                }
                                                                if (ability === "mcp:write") {
                                                                    return (
                                                                        <Badge key={ability} variant="outline" className="border-amber-500/30 text-amber-600 dark:text-amber-400 text-[10px]">
                                                                            MCP Write
                                                                        </Badge>
                                                                    );
                                                                }
                                                                return (
                                                                    <Badge key={ability} variant="secondary" className="text-[10px]">
                                                                        {ability}
                                                                    </Badge>
                                                                );
                                                            })}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleDeleteApiKey(token.id)}
                                                aria-label={`Revoke API key ${token.name}`}
                                                className="text-destructive hover:text-destructive/90"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">No API keys created yet.</p>
                            )}
                        </div>

                        {!addingApiKey ? (
                            <Button onClick={() => setAddingApiKey(true)}>Create API Key</Button>
                        ) : (
                            <div className="bg-muted/50 space-y-4 rounded-md border p-4">
                                <form onSubmit={handleAddApiKey} className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="api-key-name">Key Name</Label>
                                        <Input
                                            id="api-key-name"
                                            value={apiKeyForm.name}
                                            onChange={(e) => setApiKeyForm({ ...apiKeyForm, name: e.target.value })}
                                            placeholder="e.g. Mobile App, Integration Script"
                                            required
                                            autoFocus
                                        />
                                    </div>
                                    <div className="space-y-3">
                                        <Label>Key Type & Abilities</Label>
                                        <RadioGroup
                                            value={
                                                apiKeyForm.abilities.includes("mcp:write")
                                                    ? "mcp_write"
                                                    : apiKeyForm.abilities.includes("mcp:read")
                                                      ? "mcp_read"
                                                      : apiKeyForm.abilities.includes("write")
                                                        ? "api_write"
                                                        : "api_read"
                                            }
                                            onValueChange={(value) => {
                                                const abilitiesMap: Record<string, string[]> = {
                                                    api_read: ["read"],
                                                    api_write: ["read", "write"],
                                                    mcp_read: ["mcp:read"],
                                                    mcp_write: ["mcp:read", "mcp:write"],
                                                };
                                                setApiKeyForm({ ...apiKeyForm, abilities: abilitiesMap[value] ?? ["read"] });
                                            }}
                                            className="space-y-2"
                                        >
                                            <label htmlFor="ability-api-read" className="flex cursor-pointer items-start gap-2.5">
                                                <RadioGroupItem id="ability-api-read" value="api_read" className="mt-1" />
                                                <div>
                                                    <span className="text-sm font-medium">Standard API — Read Only</span>
                                                    <p className="text-muted-foreground text-xs">
                                                        View your{" "}
                                                        {isStudent
                                                            ? "student profile, grades, schedule, and enrollments"
                                                            : isFaculty
                                                              ? "faculty profile, courses, and schedules"
                                                              : "profile, account details, and portal data"}
                                                    </p>
                                                </div>
                                            </label>
                                            <label htmlFor="ability-api-write" className="flex cursor-pointer items-start gap-2.5">
                                                <RadioGroupItem id="ability-api-write" value="api_write" className="mt-1" />
                                                <div>
                                                    <span className="text-sm font-medium">Standard API — Read & Write</span>
                                                    <p className="text-muted-foreground text-xs">
                                                        Read and update your{" "}
                                                        {isStudent
                                                            ? "student profile and emergency contacts"
                                                            : isFaculty
                                                              ? "faculty profile and office hours"
                                                              : "profile, account details, and settings"}
                                                    </p>
                                                </div>
                                            </label>

                                            <div className="pt-2">
                                                <span className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                                    Model Context Protocol (MCP) for AI Agents
                                                </span>
                                            </div>
                                            <label
                                                htmlFor="ability-mcp-read"
                                                className="flex cursor-pointer items-start gap-2.5 rounded-lg border border-blue-500/20 bg-blue-500/5 p-2.5"
                                            >
                                                <RadioGroupItem id="ability-mcp-read" value="mcp_read" className="mt-1" />
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-medium">MCP — Read Only</span>
                                                        <Badge variant="outline" className="border-blue-500/30 text-[10px] text-blue-600">
                                                            Agent Read
                                                        </Badge>
                                                    </div>
                                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                                        {isStudent
                                                            ? "Allows connected AI agents (OpenCode, Claude, etc.) to query your student profile, enrollments, enrolled subjects, grades, and schedule."
                                                            : "Allows connected AI agents (OpenCode, Claude, etc.) to query student directory, schedules, enrollment status, and academic offerings using your account permissions."}
                                                    </p>
                                                </div>
                                            </label>
                                            {!isStudent && (
                                                <label
                                                    htmlFor="ability-mcp-write"
                                                    className="flex cursor-pointer items-start gap-2.5 rounded-lg border border-amber-500/20 bg-amber-500/5 p-2.5"
                                                >
                                                    <RadioGroupItem id="ability-mcp-write" value="mcp_write" className="mt-1" />
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-sm font-medium">MCP — Read & Write</span>
                                                            <Badge variant="outline" className="border-amber-500/30 text-[10px] text-amber-600">
                                                                Agent Mutation
                                                            </Badge>
                                                        </div>
                                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                                            Allows read tools plus permitted data modifications (such as subject enrollments, grading, and verifying enrollment requirements). Requires explicit idempotency keys and your domain permissions.
                                                        </p>
                                                    </div>
                                                </label>
                                            )}
                                        </RadioGroup>
                                        <p className="text-muted-foreground text-xs italic">
                                            All keys operate within the authorized school context and are strictly bounded by your account permissions.
                                        </p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="api-key-expires">Expiration Date (Optional)</Label>
                                        <Input
                                            id="api-key-expires"
                                            type="datetime-local"
                                            value={apiKeyForm.expires_at}
                                            onChange={(e) => setApiKeyForm({ ...apiKeyForm, expires_at: e.target.value })}
                                        />
                                    </div>
                                    <div className="flex gap-2">
                                        <Button type="submit">Create Key</Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => {
                                                setAddingApiKey(false);
                                                setApiKeyForm({ name: "", abilities: ["read"], expires_at: "" });
                                            }}
                                        >
                                            Cancel
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            <Dialog
                open={confirmAction !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirmAction(null);
                        setPasskeyIdPendingDelete(null);
                        setConfirmPassword("");
                        setConfirmPasswordError(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={submitPasswordConfirmedAction}>
                        <DialogHeader>
                            <DialogTitle>
                                {confirmAction === "disable_app" && "Remove authenticator app?"}
                                {confirmAction === "pause_2fa" && "Pause two-factor login protection?"}
                                {confirmAction === "disable_email" && "Disable email verification codes?"}
                                {confirmAction === "regenerate_codes" && "Regenerate recovery codes?"}
                                {confirmAction === "delete_passkey" && "Delete passkey?"}
                            </DialogTitle>
                            <DialogDescription>
                                {confirmAction === "disable_app" &&
                                    "Confirm your current password to remove authenticator app two-factor protection from your account."}
                                {confirmAction === "pause_2fa" &&
                                    "Confirm your current password to pause two-factor login challenges. Your saved methods will stay configured."}
                                {confirmAction === "disable_email" &&
                                    "Confirm your current password to turn off email one-time verification codes."}
                                {confirmAction === "regenerate_codes" &&
                                    "Confirm your current password to generate a new set of recovery codes. Any previously saved codes will stop working."}
                                {confirmAction === "delete_passkey" &&
                                    "Confirm your current password to delete this passkey. It will no longer be able to sign in to your account."}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="my-4 space-y-2">
                            <Label htmlFor="confirm-security-password">Current Password</Label>
                            <Input
                                id="confirm-security-password"
                                type="password"
                                autoComplete="current-password"
                                value={confirmPassword}
                                onChange={(e) => {
                                    setConfirmPassword(e.target.value);
                                    if (confirmPasswordError) setConfirmPasswordError(null);
                                }}
                                placeholder="Enter your current password"
                                required
                                autoFocus
                            />
                            {confirmPasswordError && <p className="text-destructive text-xs">{confirmPasswordError}</p>}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setConfirmAction(null);
                                    setPasskeyIdPendingDelete(null);
                                }}
                                disabled={confirmProcessing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant={confirmAction === "regenerate_codes" ? "default" : "destructive"}
                                disabled={confirmProcessing || !confirmPassword}
                            >
                                {confirmProcessing ? "Confirming..." : confirmAction === "delete_passkey" ? "Delete Passkey" : "Confirm"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={apiKeyIdPendingDelete !== null} onOpenChange={(open) => !open && setApiKeyIdPendingDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Revoke API key?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Any applications or integrations using this API key will immediately lose access. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={confirmDeleteApiKey}
                        >
                            Revoke Key
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
