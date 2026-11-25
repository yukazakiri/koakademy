import { useDirtyGuard } from "@/hooks/use-dirty-guard";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

export interface UseUnsavedDraftOptions<T> {
    key: string;
    initialValue: T;
    enabled?: boolean;
    isEqual?: (left: T, right: T) => boolean;
}

export interface UseUnsavedDraftResult<T> {
    value: T;
    setValue: React.Dispatch<React.SetStateAction<T>>;
    isDirty: boolean;
    hasRestoredDraft: boolean;
    clearDraft: () => void;
    resetToInitial: () => void;
}

const DRAFT_PREFIX = "koakademy:draft:";

function readStoredDraft<T>(storageKey: string): { found: true; value: T } | { found: false } {
    if (typeof window === "undefined") {
        return { found: false };
    }

    try {
        const raw = window.sessionStorage.getItem(storageKey);

        if (raw === null) {
            return { found: false };
        }

        return { found: true, value: JSON.parse(raw) as T };
    } catch {
        return { found: false };
    }
}

function writeStoredDraft<T>(storageKey: string, value: T): void {
    if (typeof window === "undefined") {
        return;
    }

    try {
        window.sessionStorage.setItem(storageKey, JSON.stringify(value));
    } catch {
        // Ignore storage quota or private-mode errors gracefully.
    }
}

function removeStoredDraft(storageKey: string): void {
    if (typeof window === "undefined") {
        return;
    }

    try {
        window.sessionStorage.removeItem(storageKey);
    } catch {
        // Ignore storage access errors gracefully.
    }
}

function defaultEquals<T>(left: T, right: T): boolean {
    try {
        return JSON.stringify(left) === JSON.stringify(right);
    } catch {
        return Object.is(left, right);
    }
}

/**
 * Persist in-progress form drafts in sessionStorage and register a dirty guard
 * so deployments or manual page refreshes never discard user work.
 */
export function useUnsavedDraft<T>({
    key,
    initialValue,
    enabled = true,
    isEqual = defaultEquals,
}: UseUnsavedDraftOptions<T>): UseUnsavedDraftResult<T> {
    const storageKey = `${DRAFT_PREFIX}${key}`;
    const initialRef = useRef(initialValue);

    const [restoredState] = useState(() => {
        if (!enabled) {
            return { value: initialValue, restored: false };
        }

        const stored = readStoredDraft<T>(storageKey);

        if (stored.found) {
            return { value: stored.value, restored: true };
        }

        return { value: initialValue, restored: false };
    });

    const [value, setValue] = useState<T>(restoredState.value);
    const [hasRestoredDraft, setHasRestoredDraft] = useState<boolean>(restoredState.restored);

    const isDirty = useMemo(() => !isEqual(value, initialRef.current), [isEqual, value]);

    useDirtyGuard(enabled && isDirty, `draft:${storageKey}`);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        if (isDirty) {
            writeStoredDraft(storageKey, value);
        } else {
            removeStoredDraft(storageKey);
        }
    }, [enabled, isDirty, storageKey, value]);

    const clearDraft = useCallback(() => {
        removeStoredDraft(storageKey);
        setHasRestoredDraft(false);
    }, [storageKey]);

    const resetToInitial = useCallback(() => {
        removeStoredDraft(storageKey);
        setValue(initialRef.current);
        setHasRestoredDraft(false);
    }, [storageKey]);

    return {
        value,
        setValue,
        isDirty,
        hasRestoredDraft,
        clearDraft,
        resetToInitial,
    };
}
