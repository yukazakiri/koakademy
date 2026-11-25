import { registerDirtyGuard } from "@/lib/app-update";
import { useEffect, useId } from "react";

/**
 * Mark the current component as having unsaved user edits so background
 * application updates never automatically reload the tab until work is saved.
 */
export function useDirtyGuard(isDirty: boolean, id?: string): void {
    const generatedId = useId();
    const guardId = id ?? `dirty-guard:${generatedId}`;

    useEffect(() => {
        if (!isDirty) {
            return;
        }

        return registerDirtyGuard(guardId);
    }, [guardId, isDirty]);
}
