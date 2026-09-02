import "@/echo"; // Initialize Laravel Echo for real-time
import { ThemeProvider } from "@/hooks/use-theme";
import { resolveInertiaPage, type InertiaPageModule, type InertiaPageModules } from "@/lib/inertia-page-resolver";
import { createInertiaApp, router } from "@inertiajs/react";
import { createRoot, hydrateRoot } from "react-dom/client";
import "./bootstrap"; // Initialize Axios

declare global {
    interface Window {
        appName?: string;
    }
}

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
            <ThemeProvider defaultTheme="dark" storageKey="vite-ui-theme">
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
