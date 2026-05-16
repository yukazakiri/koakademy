import { usePage } from "@inertiajs/react";
import { FlaskConical } from "lucide-react";

type DemoMode = {
    enabled: boolean;
};

export function DemoModeBanner() {
    const { demoMode } = usePage().props as { demoMode?: DemoMode };

    if (!demoMode?.enabled) {
        return null;
    }

    return (
        <div className="border-primary/20 from-primary/15 via-background to-primary/10 text-foreground sticky top-0 z-50 border-b bg-gradient-to-r px-4 py-2 shadow-sm backdrop-blur-xl">
            <div className="mx-auto flex max-w-7xl items-center justify-center gap-2 text-center text-xs font-semibold tracking-[0.22em] uppercase sm:text-sm">
                <FlaskConical className="text-primary h-4 w-4" />
                Demo Mode — database resets daily and sample accounts are available
            </div>
        </div>
    );
}
