import { Button } from "@/components/ui/button";
import { Plus, RefreshCw } from "lucide-react";

type SchedulingHeaderProps = {
    onCreateClass: () => void;
    onSync: () => void;
};

export default function SchedulingHeader({ onCreateClass, onSync }: SchedulingHeaderProps) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 className="text-2xl font-bold tracking-tight">Schedule Overview</h1>
                <p className="text-muted-foreground mt-0.5 text-sm">Bird&apos;s-eye view of all academic schedules. Filter by any dimension.</p>
            </div>

            <div className="flex items-center gap-2">
                <Button variant="default" size="sm" onClick={onCreateClass}>
                    <Plus className="mr-1.5 h-3.5 w-3.5" /> Create Class
                </Button>
                <Button variant="outline" size="sm" onClick={onSync}>
                    <RefreshCw className="mr-1.5 h-3.5 w-3.5" /> Sync
                </Button>
            </div>
        </div>
    );
}
