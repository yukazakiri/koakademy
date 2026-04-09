import { Button } from "@/components/ui/button";
import { router, usePage } from "@inertiajs/react";
import { AlertTriangle, LogOut } from "lucide-react";

export default function ImpersonationBanner() {
    const { auth } = usePage().props as any;

    if (!auth.isImpersonating) {
        return null;
    }

    const handleStopImpersonating = () => {
        router.post(route("administrators.users.stop-impersonating"));
    };

    return (
        <div className="flex items-center justify-between bg-amber-600 px-4 py-2 text-white shadow-md">
            <div className="flex items-center gap-2">
                <AlertTriangle className="h-5 w-5" />
                <span className="font-medium">
                    You are currently impersonating <strong>{auth.user.name}</strong>
                </span>
            </div>
            <Button variant="secondary" size="sm" onClick={handleStopImpersonating} className="border-0 bg-white text-amber-600 hover:bg-amber-50">
                <LogOut className="mr-2 h-4 w-4" />
                Stop Impersonating
            </Button>
        </div>
    );
}
