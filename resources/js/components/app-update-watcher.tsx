import {
    APP_UPDATE_EVENT,
    hasActiveUserWork,
    isBuildUpdateAvailable,
    shouldAutoReloadOnVisit,
    type AppBuildInfo,
    type AppUpdateEventDetail,
} from "@/lib/app-update";
import { router, usePage } from "@inertiajs/react";
import { RefreshCw } from "lucide-react";
import { useCallback, useEffect, useRef } from "react";
import { toast } from "sonner";

const UPDATE_TOAST_ID = "koakademy-app-update";
const MIN_VISIBILITY_CHECK_INTERVAL_MS = 60_000;

interface UpdatePromptContentProps {
    requiresRefreshForView: boolean;
    onRefresh: () => void;
    onLater: () => void;
}

function UpdatePromptContent({ requiresRefreshForView, onRefresh, onLater }: UpdatePromptContentProps) {
    return (
        <div className="flex w-full min-w-[280px] flex-col gap-2">
            <div className="flex items-center gap-2">
                <RefreshCw className="text-primary size-4 shrink-0" />
                <span className="text-sm font-medium">{requiresRefreshForView ? "Refresh needed to open this view" : "Update ready"}</span>
            </div>
            <p className="text-muted-foreground text-xs">
                {requiresRefreshForView
                    ? "KoAkademy was updated while this tab was open. Save your current work, then refresh to load the updated page."
                    : "A newer version of KoAkademy is available. Finish what you are doing, then refresh when ready."}
            </p>
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={onRefresh}
                    className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors"
                >
                    <RefreshCw className="size-3" />
                    Refresh now
                </button>
                <button
                    type="button"
                    onClick={onLater}
                    className="bg-muted text-muted-foreground hover:bg-muted/80 inline-flex items-center rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors"
                >
                    Later
                </button>
            </div>
        </div>
    );
}

/**
 * Detects new application deployments without interrupting active portal work.
 *
 * - Keeps the current tab working smoothly via retained Vite chunks and soft
 *   Inertia version responses.
 * - Shows a non-blocking toast so users can refresh on their own schedule.
 * - Transparently upgrades on the next clean cross-page GET navigation when
 *   no forms are dirty and no dialogs are open.
 * - Cancels surprise 409 reloads while the user has unsaved edits.
 */
export function AppUpdateWatcher() {
    const page = usePage();
    const appBuild = (page.props as { appBuild?: AppBuildInfo }).appBuild;

    const initialBuildIdRef = useRef<string | null>(appBuild?.buildId ?? null);
    const pendingBuildIdRef = useRef<string | null>(null);
    const dismissedBuildIdRef = useRef<string | null>(null);
    const lastPollAtRef = useRef<number>(Date.now());
    const pollInFlightRef = useRef<boolean>(false);

    const pollIntervalMs = Math.max(30_000, (appBuild?.pollIntervalSeconds ?? 300) * 1000);

    const showUpdateToast = useCallback((nextBuildId: string | null, requiresRefreshForView: boolean) => {
        if (!requiresRefreshForView && nextBuildId && dismissedBuildIdRef.current === nextBuildId) {
            return;
        }

        const handleRefresh = () => {
            toast.dismiss(UPDATE_TOAST_ID);
            window.location.reload();
        };

        const handleLater = () => {
            if (nextBuildId) {
                dismissedBuildIdRef.current = nextBuildId;
            }
            toast.dismiss(UPDATE_TOAST_ID);
        };

        toast.info(<UpdatePromptContent requiresRefreshForView={requiresRefreshForView} onRefresh={handleRefresh} onLater={handleLater} />, {
            id: UPDATE_TOAST_ID,
            duration: Infinity,
        });
    }, []);

    const notifyServerBuild = useCallback(
        (serverBuildId: string | null | undefined, source: AppUpdateEventDetail["source"] = "inertia") => {
            if (source === "preload-error") {
                pendingBuildIdRef.current = serverBuildId ?? pendingBuildIdRef.current ?? "preload-error";
                showUpdateToast(pendingBuildIdRef.current, true);
                return;
            }

            if (!serverBuildId) {
                return;
            }

            if (!initialBuildIdRef.current) {
                initialBuildIdRef.current = serverBuildId;
                return;
            }

            if (!isBuildUpdateAvailable(initialBuildIdRef.current, serverBuildId)) {
                return;
            }

            pendingBuildIdRef.current = serverBuildId;
            showUpdateToast(serverBuildId, false);
        },
        [showUpdateToast],
    );

    // Observe build changes carried in shared Inertia props.
    useEffect(() => {
        notifyServerBuild(appBuild?.buildId, "inertia");
    }, [appBuild?.buildId, notifyServerBuild]);

    // Observe update signals dispatched from Axios headers or vite:preloadError.
    useEffect(() => {
        const handleCustomUpdate = (event: Event) => {
            const detail = (event as CustomEvent<AppUpdateEventDetail>).detail;
            notifyServerBuild(detail?.buildId, detail?.source ?? "header");
        };

        window.addEventListener(APP_UPDATE_EVENT, handleCustomUpdate);

        return () => {
            window.removeEventListener(APP_UPDATE_EVENT, handleCustomUpdate);
        };
    }, [notifyServerBuild]);

    // Upgrade seamlessly on clean cross-page GET navigation, and shield active
    // user work if a hard 409 version-change location event ever arrives.
    useEffect(() => {
        const removeBeforeListener = router.on("before", (event) => {
            if (!pendingBuildIdRef.current) {
                return;
            }

            const visit = event.detail.visit;
            const targetUrl = visit.url;
            const isPartialReload = (Array.isArray(visit.only) && visit.only.length > 0) || (Array.isArray(visit.except) && visit.except.length > 0);

            const shouldReload = shouldAutoReloadOnVisit({
                updateAvailable: true,
                method: visit.method,
                isPartialReload,
                isPrefetch: visit.prefetch === true,
                currentPathname: window.location.pathname,
                targetPathname: targetUrl.pathname,
                hasActiveWork: hasActiveUserWork(),
            });

            if (shouldReload) {
                event.preventDefault();
                window.location.assign(targetUrl.href);
            }
        });

        // Inertia v3 fires a cancelable "location" event on 409 version changes.
        const removeLocationListener = (
            router.on as unknown as (type: string, callback: (event: CustomEvent<{ url: URL; versionChange: boolean }>) => void) => () => void
        )("location", (event) => {
            if (!event.detail?.versionChange) {
                return;
            }

            if (hasActiveUserWork()) {
                event.preventDefault();
                pendingBuildIdRef.current = pendingBuildIdRef.current ?? "version-change";
                showUpdateToast(pendingBuildIdRef.current, false);
            }
        });

        return () => {
            removeBeforeListener();
            removeLocationListener();
        };
    }, [showUpdateToast]);

    // Low-frequency background check when a tab stays open without Inertia visits.
    useEffect(() => {
        const checkBuildVersion = async () => {
            if (pollInFlightRef.current || document.visibilityState !== "visible") {
                return;
            }

            pollInFlightRef.current = true;
            lastPollAtRef.current = Date.now();

            try {
                const response = await fetch("/build-version", {
                    credentials: "same-origin",
                    cache: "no-store",
                    headers: {
                        Accept: "application/json",
                    },
                });

                if (!response.ok) {
                    return;
                }

                const data = (await response.json()) as AppBuildInfo;
                notifyServerBuild(data.buildId, "poll");
            } catch {
                // Ignore transient network errors while checking build metadata.
            } finally {
                pollInFlightRef.current = false;
            }
        };

        const intervalId = window.setInterval(() => {
            if (!pendingBuildIdRef.current) {
                void checkBuildVersion();
            }
        }, pollIntervalMs);

        const handleVisibilityChange = () => {
            if (document.visibilityState !== "visible" || pendingBuildIdRef.current) {
                return;
            }

            const elapsed = Date.now() - lastPollAtRef.current;

            if (elapsed >= Math.min(MIN_VISIBILITY_CHECK_INTERVAL_MS, pollIntervalMs)) {
                void checkBuildVersion();
            }
        };

        document.addEventListener("visibilitychange", handleVisibilityChange);

        return () => {
            window.clearInterval(intervalId);
            document.removeEventListener("visibilitychange", handleVisibilityChange);
        };
    }, [notifyServerBuild, pollIntervalMs]);

    return null;
}
