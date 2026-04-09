import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Switch } from "@/components/ui/switch";
import { router } from "@inertiajs/react";
import axios from "axios";
import { CheckCircle2, Key, QrCode, ShieldCheck, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

interface SecuritySectionProps {
    isFaculty: boolean;
    isStudent: boolean;
    user: {
        two_factor_enabled: boolean;
        email_two_factor_enabled: boolean;
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
        email_auth_toggle: string;
    };
    developerModeEnabled?: boolean;
}

export function SecuritySection({ isFaculty, isStudent, user, paths, developerModeEnabled = false }: SecuritySectionProps) {
    const [passkeys, setPasskeys] = useState<any[]>([]);
    const [addingPasskey, setAddingPasskey] = useState(false);
    const [passkeyForm, setPasskeyForm] = useState({ name: "" });
    const [apiTokens, setApiTokens] = useState<any[]>([]);
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
    const [passwordProcessing, setPasswordProcessing] = useState(false);
    const [twoFactorEnabling, setTwoFactorEnabling] = useState(false);
    const [twoFactorData, setTwoFactorData] = useState<{ secret: string; qr_code: string } | null>(null);
    const [twoFactorForm, setTwoFactorForm] = useState({ code: "", secret: "" });
    const [twoFactorProcessing, setTwoFactorProcessing] = useState(false);
    const [showRecoveryCodes, setShowRecoveryCodes] = useState(false);

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
        const basePath = isFaculty ? "/faculty" : isStudent ? "/student" : "";
        axios
            .get(`${basePath}/profile/api-keys`)
            .then((response) => {
                setApiTokens(response.data.tokens);
            })
            .catch((error) => {
                console.error("Failed to fetch API tokens", error);
            });
    };

    const handlePasswordSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setPasswordProcessing(true);
        router.put(paths.password_update, passwordForm, {
            onSuccess: () => {
                toast.success("Password changed successfully");
                setPasswordForm({ current_password: "", password: "", password_confirmation: "" });
                setPasswordProcessing(false);
            },
            onError: () => {
                toast.error("Failed to change password. Please check your inputs.");
                setPasswordProcessing(false);
            },
        });
    };

    const handleEnableTwoFactor = () => {
        setTwoFactorEnabling(true);
        axios
            .post(paths.two_factor_enable)
            .then((response: any) => {
                setTwoFactorData(response.data);
                setTwoFactorForm((prev) => ({ ...prev, secret: response.data.secret }));
            })
            .catch((error: any) => {
                toast.error("Failed to start two-factor authentication setup.");
                setTwoFactorEnabling(false);
            });
    };

    const handleConfirmTwoFactor = (e: React.FormEvent) => {
        e.preventDefault();
        setTwoFactorProcessing(true);
        router.post(
            paths.two_factor_confirm,
            { code: twoFactorForm.code, secret: twoFactorForm.secret },
            {
                onSuccess: () => {
                    setTwoFactorEnabling(false);
                    setTwoFactorData(null);
                    setTwoFactorForm({ code: "", secret: "" });
                    setShowRecoveryCodes(true);
                    toast.success("Two-factor authentication enabled successfully!");
                    setTwoFactorProcessing(false);
                },
                onError: () => {
                    toast.error("Invalid authentication code.");
                    setTwoFactorProcessing(false);
                },
            },
        );
    };

    const handleDisableTwoFactor = () => {
        if (confirm("Are you sure you want to disable two-factor authentication?")) {
            const password = prompt("Please confirm your password to disable 2FA:");
            if (!password) return;

            router.delete(paths.two_factor_disable, {
                data: { password },
                onSuccess: () => toast.success("Two-factor authentication disabled."),
                onError: () => toast.error("Incorrect password or failed to disable."),
            });
        }
    };

    const handleRegenerateRecoveryCodes = () => {
        router.post(
            paths.two_factor_recovery_codes,
            {},
            {
                onSuccess: () => {
                    setShowRecoveryCodes(true);
                    toast.success("Recovery codes regenerated.");
                },
            },
        );
    };

    const handleToggleEmailAuth = (checked: boolean) => {
        router.post(
            paths.email_auth_toggle,
            { enabled: checked },
            {
                preserveScroll: true,
                onSuccess: () => toast.success(checked ? "Email authentication enabled." : "Email authentication disabled."),
            },
        );
    };

    const handleAddPasskey = async (e: React.FormEvent) => {
        e.preventDefault();
        setAddingPasskey(true);

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
                excludeCredentials: options.excludeCredentials?.map((cred: any) => ({
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

            const response = credential.response as AuthenticatorAttestationResponse;
            // @ts-ignore
            const transports = response.getTransports ? response.getTransports() : [];

            const passkeyData = JSON.stringify({
                id: credential.id,
                rawId,
                type: credential.type,
                response: {
                    attestationObject,
                    clientDataJSON,
                    transports: transports,
                },
            });

            router.post(
                paths.passkeys,
                {
                    passkey: passkeyData,
                    name: passkeyForm.name,
                },
                {
                    onSuccess: () => {
                        toast.success("Passkey added successfully!");
                        setPasskeyForm({ name: "" });
                        setAddingPasskey(false);
                        fetchPasskeys();
                    },
                    onError: () => {
                        toast.error("Failed to register passkey.");
                        setAddingPasskey(false);
                    },
                },
            );
        } catch (error) {
            console.error(error);
            toast.error("Failed to add passkey. Please try again.");
            setAddingPasskey(false);
        }
    };

    const handleDeletePasskey = (id: number) => {
        if (confirm("Are you sure you want to delete this passkey?")) {
            router.delete(`${paths.passkeys}/${id}`, {
                onSuccess: () => {
                    toast.success("Passkey deleted.");
                    fetchPasskeys();
                },
                onError: () => {
                    toast.error("Failed to delete passkey.");
                },
            });
        }
    };

    const handleAddApiKey = async (e: React.FormEvent) => {
        e.preventDefault();
        const basePath = isFaculty ? "/faculty" : isStudent ? "/student" : "";

        try {
            const response = await axios.post(`${basePath}/profile/api-keys`, {
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
        } catch (error: any) {
            console.error("Failed to create API key", error);
            if (error.response?.data?.errors) {
                const errors = error.response.data.errors;
                Object.values(errors).forEach((msgs: any) => {
                    msgs.forEach((msg: string) => toast.error(msg));
                });
            } else {
                toast.error("Failed to create API key.");
            }
        }
    };

    const handleDeleteApiKey = async (id: number) => {
        if (confirm("Are you sure you want to delete this API key? Any applications using it will no longer have access.")) {
            const basePath = isFaculty ? "/faculty" : isStudent ? "/student" : "";

            try {
                await axios.delete(`${basePath}/profile/api-keys/${id}`);
                toast.success("API key deleted successfully.");
                fetchApiTokens();
            } catch (error) {
                console.error("Failed to delete API key", error);
                toast.error("Failed to delete API key.");
            }
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
                    <CardTitle className="flex items-center gap-2">
                        <ShieldCheck className="h-5 w-5" />
                        Password
                    </CardTitle>
                    <CardDescription>Update your password</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handlePasswordSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="current_password">Current Password</Label>
                            <Input
                                id="current_password"
                                type="password"
                                value={passwordForm.current_password}
                                onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="password">New Password</Label>
                            <Input
                                id="password"
                                type="password"
                                value={passwordForm.password}
                                onChange={(e) => setPasswordForm({ ...passwordForm, password: e.target.value })}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="password_confirmation">Confirm Password</Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                value={passwordForm.password_confirmation}
                                onChange={(e) => setPasswordForm({ ...passwordForm, password_confirmation: e.target.value })}
                                required
                            />
                        </div>
                        <Button type="submit" disabled={passwordProcessing}>
                            {passwordProcessing ? "Updating..." : "Update Password"}
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
                    <CardDescription>Add an extra layer of security to your account</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {passkeys.length > 0 && (
                        <>
                            <div className="flex items-center justify-between space-x-2">
                                <Label className="flex flex-col space-y-1">
                                    <span>Passkey Verification</span>
                                    <span className="text-muted-foreground text-xs font-normal">
                                        Your {passkeys.length} registered passkey
                                        {passkeys.length !== 1 ? "s" : ""} can be used as a second factor during login
                                    </span>
                                </Label>
                                <Badge variant="outline" className="text-primary border-primary/30 bg-primary/5">
                                    Active
                                </Badge>
                            </div>
                            <Separator />
                        </>
                    )}
                    <div className="flex items-center justify-between space-x-2">
                        <Label htmlFor="email-auth" className="flex flex-col space-y-1">
                            <span>Email Authentication</span>
                            <span className="text-muted-foreground text-xs font-normal">Receive a verification code via email</span>
                        </Label>
                        <Switch id="email-auth" checked={user.email_two_factor_enabled} onCheckedChange={handleToggleEmailAuth} />
                    </div>
                    <Separator />
                    {user.two_factor_enabled ? (
                        <>
                            <div className="text-primary bg-primary/10 dark:bg-primary/20 flex items-center gap-2 rounded-md p-3">
                                <CheckCircle2 className="h-5 w-5" />
                                <span className="font-medium">Authenticator App is enabled.</span>
                            </div>
                            <div className="flex gap-3">
                                <Button variant="outline" onClick={handleRegenerateRecoveryCodes}>
                                    Regenerate Recovery Codes
                                </Button>
                                <Button variant="destructive" onClick={handleDisableTwoFactor}>
                                    Disable App Auth
                                </Button>
                            </div>
                            {showRecoveryCodes && user.recovery_codes && (
                                <div className="bg-muted mt-4 rounded-lg p-4">
                                    <p className="mb-2 text-sm font-medium">Recovery Codes</p>
                                    <p className="text-muted-foreground mb-3 text-xs">
                                        Store these codes in a secure place. They can be used to access your account if you lose your authentication
                                        device.
                                    </p>
                                    <div className="grid grid-cols-2 gap-2 font-mono text-sm">
                                        {user.recovery_codes.map((code, index) => (
                                            <div key={index}>{code}</div>
                                        ))}
                                    </div>
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
                                                onChange={(e) => setTwoFactorForm({ ...twoFactorForm, code: e.target.value })}
                                                placeholder="XXX XXX"
                                                className="font-mono"
                                                required
                                            />
                                            <Button type="submit" disabled={twoFactorProcessing}>
                                                Confirm
                                            </Button>
                                        </form>
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
                                {passkeys.map((passkey: any) => (
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
                                            className="text-destructive hover:text-destructive/90"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">No passkeys added yet.</p>
                        )}
                    </div>

                    {!addingPasskey ? (
                        <Button onClick={() => setAddingPasskey(true)}>Add Passkey</Button>
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
                                        required
                                        autoFocus
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit">Create Passkey</Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => {
                                            setAddingPasskey(false);
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
                                    {apiTokens.map((token: any) => (
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
                                                        <div className="mt-1 flex gap-1">
                                                            {token.abilities.map((ability: string) => (
                                                                <Badge key={ability} variant="secondary" className="text-[10px]">
                                                                    {ability}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleDeleteApiKey(token.id)}
                                                className="text-destructive hover:text-destructive/90"
                                            >
                                                <Trash2 className="h-4 w-4" />
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
                                    <div className="space-y-2">
                                        <Label>Abilities</Label>
                                        <div className="space-y-2">
                                            <label className="flex items-start gap-2">
                                                <input
                                                    type="radio"
                                                    name="abilities"
                                                    checked={
                                                        apiKeyForm.abilities.includes("read") &&
                                                        !apiKeyForm.abilities.includes("write") &&
                                                        !apiKeyForm.abilities.includes("*")
                                                    }
                                                    onChange={() => setApiKeyForm({ ...apiKeyForm, abilities: ["read"] })}
                                                    className="mt-1"
                                                />
                                                <div>
                                                    <span className="text-sm font-medium">Read Only</span>
                                                    <p className="text-muted-foreground text-xs">
                                                        View your{" "}
                                                        {isStudent
                                                            ? "student profile, grades, schedule, and enrollments"
                                                            : "faculty profile, courses, and schedules"}
                                                    </p>
                                                </div>
                                            </label>
                                            <label className="flex items-start gap-2">
                                                <input
                                                    type="radio"
                                                    name="abilities"
                                                    checked={apiKeyForm.abilities.includes("write") && !apiKeyForm.abilities.includes("*")}
                                                    onChange={() => setApiKeyForm({ ...apiKeyForm, abilities: ["read", "write"] })}
                                                    className="mt-1"
                                                />
                                                <div>
                                                    <span className="text-sm font-medium">Read & Write</span>
                                                    <p className="text-muted-foreground text-xs">
                                                        Read and update your{" "}
                                                        {isStudent ? "student profile and emergency contacts" : "faculty profile and office hours"}
                                                    </p>
                                                </div>
                                            </label>
                                        </div>
                                        <p className="text-muted-foreground text-xs italic">
                                            These permissions are limited to your own account only. You cannot access other users&apos; data.
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
        </div>
    );
}
