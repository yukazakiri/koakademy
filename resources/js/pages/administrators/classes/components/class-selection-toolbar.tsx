import { Button } from "@/components/ui/button";
import { show } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import { Eye, GitCompare, X } from "lucide-react";
import type { ClassRow } from "../columns";
import { ClassRowActions, type ClassActionHandlers } from "./class-row-actions";

export function ClassSelectionToolbar({
    selectedClasses,
    onClear,
    onCompare,
    ...actions
}: ClassActionHandlers & {
    selectedClasses: ClassRow[];
    onClear: () => void;
    onCompare: () => void;
}) {
    if (selectedClasses.length === 0) return null;

    return (
        <div className="bg-muted/50 flex flex-wrap items-center gap-3 border-b px-4 py-3 sm:px-6" role="region" aria-label="Selected class actions">
            <p className="mr-auto text-sm font-medium" aria-live="polite">
                {selectedClasses.length} selected on this page
            </p>
            {selectedClasses.length === 1 ? (
                <>
                    <Button
                        variant="outline"
                        className="h-9 px-3 text-sm"
                        nativeButton={false}
                        render={<Link href={show.url(selectedClasses[0].id)} />}
                    >
                        <Eye className="size-4" aria-hidden="true" /> View class
                    </Button>
                    <ClassRowActions classRow={selectedClasses[0]} {...actions} />
                </>
            ) : null}
            <Button type="button" variant="outline" className="h-9 gap-2 px-3 text-sm" disabled={selectedClasses.length !== 2} onClick={onCompare}>
                <GitCompare className="size-4" aria-hidden="true" /> Compare students
            </Button>
            <Button type="button" variant="ghost" className="h-9 gap-2 px-3 text-sm" onClick={onClear}>
                <X className="size-4" aria-hidden="true" /> Clear selection
            </Button>
            {selectedClasses.length !== 2 ? (
                <span className="text-muted-foreground w-full text-sm">Select two classes to compare enrolled students.</span>
            ) : null}
        </div>
    );
}
