import AppRootLayout from "@/components/app-root-layout";
import "@/echo"; // Initialize Laravel Echo for real-time
import { ThemeProvider } from "@/hooks/use-theme";
import { createInertiaApp, router } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import type { ReactNode } from "react";
import { createRoot, hydrateRoot } from "react-dom/client";
import "./bootstrap"; // Initialize Axios

type InertiaPageModule = {
    default: {
        layout?: (children: ReactNode) => ReactNode;
    };
};

declare global {
    interface Window {
        appName?: string;
    }
}

const appPages = import.meta.glob("./pages/**/*.tsx");
const modulePages = import.meta.glob("../../Modules/**/resources/assets/js/Pages/**/*.tsx");

createInertiaApp({
    id: "app",
    title: (title) => {
        // Use dynamic appName from shared props, fallback to VITE_APP_NAME
        const envAppName = import.meta.env.VITE_APP_NAME;
        const appName = window.appName || envAppName || "KoAkademy";
        return title ? `${title} - ${appName}` : appName;
    },
    resolve: async (name) => {
        const modulePagePath = Object.keys(modulePages).find((path) => path.endsWith(`/resources/assets/js/Pages/${name}.tsx`));

        const module = modulePagePath ? await modulePages[modulePagePath]() : await resolvePageComponent(`./pages/${name}.tsx`, appPages);

        const page = (module as InertiaPageModule).default;

        if (!page.layout) {
            page.layout = (children: ReactNode) => <AppRootLayout>{children}</AppRootLayout>;
        }

        return module;
    },
    setup({ el, App, props }) {
        router.on("start", () => document.documentElement.classList.add("is-navigating"));
        router.on("finish", () => document.documentElement.classList.remove("is-navigating"));

        // @ts-expect-error Inertia's generated app props use an opaque generic signature.
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
