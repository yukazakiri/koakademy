import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { SiFacebook, SiGithub, SiGoogle, SiX } from "@icons-pack/react-simple-icons";
import { router } from "@inertiajs/react";
import { Link2, Share2 } from "lucide-react";
import { toast } from "sonner";

interface ConnectionsTabProps {
    connectedAccounts: Record<string, boolean>;
}

export function ConnectionsTab({ connectedAccounts }: ConnectionsTabProps) {
    const handleSocialToggle = (provider: string, checked: boolean) => {
        if (checked) {
            window.location.href = `/integrations/${provider}/connect`;
        } else {
            if (confirm(`Are you sure you want to disconnect ${provider}?`)) {
                router.post(
                    `/integrations/${provider}/disconnect`,
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            toast.success(`${provider} disconnected successfully`);
                        },
                    },
                );
            }
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Share2 className="h-5 w-5" />
                    Social Profiles
                </CardTitle>
                <CardDescription>Connect your social media accounts for easier login and sharing</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-6">
                <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                            <SiGoogle className="h-5 w-5 text-red-600 dark:text-red-400" />
                        </div>
                        <div>
                            <p className="font-medium">Google</p>
                            <p className="text-muted-foreground text-sm">Connect your Google account for Calendar and Auth</p>
                        </div>
                    </div>
                    <Switch checked={!!connectedAccounts["google"]} onCheckedChange={(checked) => handleSocialToggle("google", checked)} />
                </div>

                <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                            <SiFacebook className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p className="font-medium">Facebook</p>
                            <p className="text-muted-foreground text-sm">Connect your Facebook account</p>
                        </div>
                    </div>
                    <Switch checked={!!connectedAccounts["facebook"]} onCheckedChange={(checked) => handleSocialToggle("facebook", checked)} />
                </div>

                <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                            <SiX className="h-5 w-5 text-slate-900 dark:text-slate-100" />
                        </div>
                        <div>
                            <p className="font-medium">X (Twitter)</p>
                            <p className="text-muted-foreground text-sm">Connect your X account</p>
                        </div>
                    </div>
                    <Switch checked={!!connectedAccounts["twitter"]} onCheckedChange={(checked) => handleSocialToggle("twitter", checked)} />
                </div>

                <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20">
                            <Link2 className="h-5 w-5 text-blue-700 dark:text-blue-400" />
                        </div>
                        <div>
                            <p className="font-medium">LinkedIn</p>
                            <p className="text-muted-foreground text-sm">Connect your LinkedIn professional profile</p>
                        </div>
                    </div>
                    <Switch checked={!!connectedAccounts["linkedin"]} onCheckedChange={(checked) => handleSocialToggle("linkedin", checked)} />
                </div>

                <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                            <SiGithub className="h-5 w-5 text-slate-900 dark:text-slate-100" />
                        </div>
                        <div>
                            <p className="font-medium">GitHub</p>
                            <p className="text-muted-foreground text-sm">Connect your GitHub account</p>
                        </div>
                    </div>
                    <Switch checked={!!connectedAccounts["github"]} onCheckedChange={(checked) => handleSocialToggle("github", checked)} />
                </div>
            </CardContent>
        </Card>
    );
}
