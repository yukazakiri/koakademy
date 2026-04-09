import { Head } from "@inertiajs/react";
import { useEffect } from "react";
import { toast } from "sonner";

interface ForbiddenPageProps {
    message?: string;
}

export default function ForbiddenPage({ message }: ForbiddenPageProps) {
    useEffect(() => {
        toast.error(message ?? "You do not have permission to access this page.");
    }, [message]);

    return (
        <div className="flex min-h-svh items-center justify-center p-6">
            <Head title="Forbidden" />
            <div className="max-w-md space-y-2 text-center">
                <p className="text-muted-foreground text-sm uppercase tracking-wide">403</p>
                <h1 className="text-foreground text-2xl font-semibold">Access denied</h1>
                <p className="text-muted-foreground text-sm">{message ?? "You do not have permission to access this page."}</p>
            </div>
        </div>
    );
}
