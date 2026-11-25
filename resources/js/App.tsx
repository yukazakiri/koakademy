import "@/echo"; // Initialize Laravel Echo for real-time
import { ThemeProvider } from "@/hooks/use-theme";
import { canAttemptPreloadRecovery, dispatchAppUpdateAvailable, hasActiveUserWork, PRELOAD_RECOVERY_STORAGE_KEY } from "@/lib/app-update";
import { resolveInertiaPage, type InertiaPageModule, type InertiaPageModules } from "@/lib/inertia-page-resolver";
import { createInertiaApp, router } from "@inertiajs/react";
import { createRoot, hydrateRoot } from "react-dom/client";
import "./bootstrap"; // Initialize Axios

declare global {
    interface Window {
        appName?: string;
    }
}

window.addEventListener("vite:preloadError", (event) => {
    event.preventDefault();

    if (hasActiveUserWork()) {
        dispatchAppUpdateAvailable({ source: "preload-error" });
        return;
    }

    const now = Date.now();
    let lastAttempt: number | null = null;

    try {
        const stored = window.sessionStorage.getItem(PRELOAD_RECOVERY_STORAGE_KEY);
        lastAttempt = stored ? Number(stored) : null;
    } catch {
        lastAttempt = null;
    }

    if (canAttemptPreloadRecovery(now, lastAttempt)) {
        try {
            window.sessionStorage.setItem(PRELOAD_RECOVERY_STORAGE_KEY, String(now));
        } catch {
            // Continue with single recovery reload even when storage is unavailable.
        }

        window.location.reload();
        return;
    }

    dispatchAppUpdateAvailable({ source: "preload-error" });
});

const appPages: InertiaPageModules = import.meta.glob<InertiaPageModule>("./pages/**/*.tsx");
const modulePages: InertiaPageModules = {
    ...import.meta.glob<InertiaPageModule>("../../Modules/**/resources/assets/js/Pages/**/*.tsx"),
    ...import.meta.glob<InertiaPageModule>("../../vendor/*/*/resources/assets/js/Pages/**/*.tsx"),
};

createInertiaApp({
    id: "app",
    title: (title) => {
        // Use dynamic appName from shared props, fallback to VITE_APP_NAME
        const envAppName = import.meta.env.VITE_APP_NAME;
        const appName = window.appName || envAppName || "KoAkademy";
        return title ? `${title} - ${appName}` : appName;
    },
    resolve: (name) => resolveInertiaPage(name, appPages, modulePages),
    setup({ el, App, props }) {
        router.on("start", () => document.documentElement.classList.add("is-navigating"));
        router.on("finish", () => document.documentElement.classList.remove("is-navigating"));

        const inertiaApp = (
            <ThemeProvider defaultTheme="system" storageKey="ui-theme" colorStorageKey="ui-color-theme">
                <App {...props} />
            </ThemeProvider>
        );

        if (el.hasChildNodes()) {
            hydrateRoot(el, inertiaApp);
            return;
        }

        createRoot(el).render(inertiaApp);
    },
    progress: {
        color: "#4B5563",
    },
}).then();
