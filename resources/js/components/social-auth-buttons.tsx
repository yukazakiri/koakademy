import { usePage } from "@inertiajs/react";
import { SiApple, SiFacebook, SiGithub, SiGoogle, SiX } from "@icons-pack/react-simple-icons";
import { Link2 } from "lucide-react";
import type { ComponentType } from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

type SocialAuthProvider = {
    key: string;
    label: string;
    redirect_url: string;
};

const providerIcons: Record<string, ComponentType<{ className?: string }>> = {
    google: SiGoogle,
    apple: SiApple,
    github: SiGithub,
    facebook: SiFacebook,
    twitter: SiX,
    x: SiX,
    linkedin: Link2,
};

export function SocialAuthButtons() {
    const { socialAuthProviders = [] } = usePage<{ socialAuthProviders?: SocialAuthProvider[] }>().props;

    if (socialAuthProviders.length === 0) {
        return null;
    }

    const isMultiColumn = socialAuthProviders.length > 1;

    return (
        <div className="grid gap-3">
            <div className="relative my-1">
                <div className="absolute inset-0 flex items-center">
                    <span className="w-full border-t border-zinc-800" />
                </div>
                <div className="relative flex justify-center text-xs">
                    <span className="bg-zinc-950 px-2 text-zinc-400">Or continue with</span>
                </div>
            </div>

            <div className={cn("grid gap-3", isMultiColumn ? "grid-cols-2" : "grid-cols-1")}>
                {socialAuthProviders.map((provider, index) => {
                    const Icon = providerIcons[provider.key] ?? Link2;
                    // If odd number of providers > 1, make the last one span full width
                    const isLastOdd = isMultiColumn && socialAuthProviders.length % 2 !== 0 && index === socialAuthProviders.length - 1;

                    return (
                        <Button
                            key={provider.key}
                            type="button"
                            variant="outline"
                            className={cn(
                                "h-10 w-full rounded-lg border border-zinc-800 bg-zinc-900/60 font-medium text-sm text-zinc-200 transition-colors hover:border-zinc-700 hover:bg-zinc-800/80 hover:text-white shadow-xs",
                                isLastOdd && "col-span-2"
                            )}
                            onClick={() => {
                                window.location.href = provider.redirect_url;
                            }}
                        >
                            <Icon className="mr-2 size-4 shrink-0" />
                            <span className="truncate">{provider.label}</span>
                        </Button>
                    );
                })}
            </div>
        </div>
    );
}

export default SocialAuthButtons;
