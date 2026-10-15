import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Frame, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { SiFacebook, SiGithub, SiGoogle, SiX } from "@icons-pack/react-simple-icons";
import { router, usePage } from "@inertiajs/react";
import { Link2, Plus, Share2, Trash2 } from "lucide-react";
import { toast } from "sonner";

type ConnectedAccount = {
    id: number;
    provider: string;
    provider_id: string;
    name?: string | null;
    nickname?: string | null;
    email?: string | null;
    avatar_path?: string | null;
};

type ConnectedAccountsPayload = {
    providers: Record<string, boolean>;
    accounts: ConnectedAccount[];
};

type SocialAuthProvider = {
    key: string;
    label: string;
    redirect_url: string;
};

interface ConnectionsTabProps {
    connectedAccounts: ConnectedAccountsPayload;
}

const providerConfigs = [
    {
        key: "google",
        label: "Google",
        description: "Use Google for login and Calendar access",
        icon: SiGoogle,
        iconClassName: "text-red-600 dark:text-red-400",
        iconBackground: "bg-red-100 dark:bg-red-900/30",
    },
    {
        key: "facebook",
        label: "Facebook",
        description: "Connect your Facebook account",
        icon: SiFacebook,
        iconClassName: "text-blue-600 dark:text-blue-400",
        iconBackground: "bg-blue-100 dark:bg-blue-900/30",
    },
    {
        key: "twitter",
        label: "X (Twitter)",
        description: "Connect your X account",
        icon: SiX,
        iconClassName: "text-slate-900 dark:text-slate-100",
        iconBackground: "bg-slate-100 dark:bg-slate-800",
    },
    {
        key: "linkedin",
        label: "LinkedIn",
        description: "Connect your LinkedIn professional profile",
        icon: Link2,
        iconClassName: "text-blue-700 dark:text-blue-400",
        iconBackground: "bg-blue-50 dark:bg-blue-900/20",
    },
    {
        key: "github",
        label: "GitHub",
        description: "Connect your GitHub account",
        icon: SiGithub,
        iconClassName: "text-slate-900 dark:text-slate-100",
        iconBackground: "bg-slate-100 dark:bg-slate-800",
    },
];

export function ConnectionsTab({ connectedAccounts }: ConnectionsTabProps) {
    const { socialAuthProviders = [] } = usePage<{ socialAuthProviders?: SocialAuthProvider[] }>().props;
    const enabledProviderKeys = new Set(socialAuthProviders.map((provider) => provider.key));

    const handleConnect = (provider: string) => {
        window.location.href = `/integrations/${provider}/connect`;
    };

    const handleDisconnect = (provider: string, accountId?: number) => {
        const label = providerConfigs.find((config) => config.key === provider)?.label ?? provider;

        if (!confirm(`Are you sure you want to disconnect ${label}?`)) {
            return;
        }

        router.post(
            `/integrations/${provider}/disconnect`,
            accountId ? { account_id: accountId } : {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success(`${label} disconnected successfully`),
            },
        );
    };

    return (
        <Frame spacing="sm">
            <FrameHeader>
                <FrameTitle className="flex items-center gap-2">
                    <Share2 className="h-5 w-5 text-primary" />
                    Social profiles
                </FrameTitle>
                <p className="text-muted-foreground text-sm">Connect social accounts for easier login and account access.</p>
            </FrameHeader>
            <FramePanel className="grid gap-4">
                {providerConfigs.map((provider) => {
                    const Icon = provider.icon;
                    const accounts = connectedAccounts.accounts.filter((account) => account.provider === provider.key);
                    const isConnected = Boolean(connectedAccounts.providers[provider.key]);
                    const isEnabled = enabledProviderKeys.has(provider.key);

                    return (
                        <div key={provider.key} className="rounded-lg border p-4">
                            <div className="flex items-center justify-between gap-4">
                                <div className="flex min-w-0 items-center gap-4">
                                    <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${provider.iconBackground}`}>
                                        <Icon className={`h-5 w-5 ${provider.iconClassName}`} />
                                    </div>
                                    <div className="min-w-0">
                                        <p className="font-medium">{provider.label}</p>
                                        <p className="text-muted-foreground text-sm">{provider.description}</p>
                                    </div>
                                </div>

                                {provider.key === "google" && isConnected ? (
                                    <Button type="button" variant="outline" size="sm" onClick={() => handleConnect(provider.key)} disabled={!isEnabled}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add
                                    </Button>
                                ) : (
                                    <Switch
                                        checked={isConnected}
                                        disabled={!isEnabled && !isConnected}
                                        onCheckedChange={(checked) =>
                                            checked ? handleConnect(provider.key) : handleDisconnect(provider.key)
                                        }
                                    />
                                )}
                            </div>

                            {provider.key === "google" && accounts.length > 0 ? (
                                <div className="mt-4 grid gap-2 border-t pt-4">
                                    {accounts.map((account) => (
                                        <div key={account.id} className="flex items-center justify-between gap-3 rounded-md bg-muted/40 p-3">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <Avatar className="h-8 w-8">
                                                    <AvatarImage src={account.avatar_path ?? undefined} alt={account.name ?? account.email ?? "Google"} />
                                                    <AvatarFallback>{(account.name ?? account.email ?? "G").slice(0, 1).toUpperCase()}</AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">{account.name ?? "Google account"}</p>
                                                    <p className="text-muted-foreground truncate text-xs">{account.email}</p>
                                                </div>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleDisconnect(provider.key, account.id)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                                <span className="sr-only">Disconnect {account.email}</span>
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            ) : null}
                        </div>
                    );
                })}
            </FramePanel>
        </Frame>
    );
}
