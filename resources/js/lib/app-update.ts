export const APP_UPDATE_EVENT = "koakademy:update-available";
export const PRELOAD_RECOVERY_STORAGE_KEY = "koakademy:preload-recovery-at";
export const DEFAULT_PRELOAD_RECOVERY_COOLDOWN_MS = 30_000;

export interface AppBuildInfo {
    buildId?: string | null;
    softReload?: boolean;
    pollIntervalSeconds?: number;
}

export interface AppUpdateEventDetail {
    buildId?: string | null;
    source?: "inertia" | "header" | "poll" | "preload-error" | "location";
}

export interface AutoReloadDecisionInput {
    updateAvailable: boolean;
    method?: string | null;
    isPartialReload?: boolean;
    isPrefetch?: boolean;
    currentPathname: string;
    targetPathname: string;
    hasActiveWork: boolean;
}

const activeDirtyGuards = new Set<string>();

/**
 * Register a dirty form or in-progress workspace so automatic refresh on
 * navigation never discards unsaved user input.
 */
export function registerDirtyGuard(id: string): () => void {
    activeDirtyGuards.add(id);

    return () => {
        activeDirtyGuards.delete(id);
    };
}

export function clearAllDirtyGuards(): void {
    activeDirtyGuards.clear();
}

export function hasRegisteredDirtyGuard(): boolean {
    return activeDirtyGuards.size > 0;
}

/**
 * Inspect the live DOM for open modal dialogs, active rich-text editors, or
 * modified form controls so pages that don't explicitly register a dirty guard
 * are still protected from surprise reloads.
 */
export function hasActiveDomWork(doc?: Document | null): boolean {
    const targetDoc = doc ?? (typeof document !== "undefined" ? document : null);

    if (!targetDoc) {
        return false;
    }

    if (typeof targetDoc.querySelector === "function") {
        const openDialog = targetDoc.querySelector('[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"], dialog[open]');

        if (openDialog) {
            return true;
        }
    }

    const activeElement = targetDoc.activeElement as HTMLElement | null;

    if (activeElement?.isContentEditable) {
        return true;
    }

    if (typeof targetDoc.querySelectorAll !== "function") {
        return false;
    }

    const controls = targetDoc.querySelectorAll("form input, form textarea, form select");

    for (const element of controls) {
        const htmlElement = element as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;

        if (htmlElement.disabled || ("readOnly" in htmlElement && htmlElement.readOnly)) {
            continue;
        }

        if (htmlElement.dataset?.updateSafe === "true") {
            continue;
        }

        const tag = htmlElement.tagName.toLowerCase();

        if (tag === "input") {
            const input = htmlElement as HTMLInputElement;
            const inputType = (input.type || "text").toLowerCase();

            if (["hidden", "submit", "button", "reset", "image", "search"].includes(inputType)) {
                continue;
            }

            if (inputType === "checkbox" || inputType === "radio") {
                if (input.checked !== input.defaultChecked) {
                    return true;
                }

                continue;
            }

            if (inputType === "file") {
                if (input.files && input.files.length > 0) {
                    return true;
                }

                continue;
            }

            if (input.value !== input.defaultValue) {
                return true;
            }

            continue;
        }

        if (tag === "textarea") {
            const textarea = htmlElement as HTMLTextAreaElement;

            if (textarea.value !== textarea.defaultValue) {
                return true;
            }

            continue;
        }

        if (tag === "select") {
            const select = htmlElement as HTMLSelectElement;

            for (const option of Array.from(select.options)) {
                if (option.selected !== option.defaultSelected) {
                    return true;
                }
            }
        }
    }

    return false;
}

export function hasActiveUserWork(doc?: Document | null): boolean {
    return hasRegisteredDirtyGuard() || hasActiveDomWork(doc);
}

export function isBuildUpdateAvailable(initialBuildId: string | null | undefined, serverBuildId: string | null | undefined): boolean {
    if (!initialBuildId || !serverBuildId) {
        return false;
    }

    return initialBuildId !== serverBuildId;
}

/**
 * Only upgrade transparently on a clean, full cross-page GET navigation when
 * the user has no unsaved edits or open modal dialogs. Same-page filters,
 * pagination, partial reloads, prefetches, and mutations stay uninterrupted.
 */
export function shouldAutoReloadOnVisit(input: AutoReloadDecisionInput): boolean {
    if (!input.updateAvailable) {
        return false;
    }

    if ((input.method ?? "get").toLowerCase() !== "get") {
        return false;
    }

    if (input.isPartialReload || input.isPrefetch) {
        return false;
    }

    if (input.hasActiveWork) {
        return false;
    }

    return input.targetPathname !== input.currentPathname;
}

export function canAttemptPreloadRecovery(
    nowMs: number,
    lastAttemptMs: number | null | undefined,
    cooldownMs: number = DEFAULT_PRELOAD_RECOVERY_COOLDOWN_MS,
): boolean {
    if (lastAttemptMs === null || lastAttemptMs === undefined || Number.isNaN(lastAttemptMs)) {
        return true;
    }

    return nowMs - lastAttemptMs >= cooldownMs;
}

export function dispatchAppUpdateAvailable(detail: AppUpdateEventDetail): void {
    if (typeof window === "undefined") {
        return;
    }

    window.dispatchEvent(new CustomEvent<AppUpdateEventDetail>(APP_UPDATE_EVENT, { detail }));
}
