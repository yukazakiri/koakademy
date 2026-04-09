import AppRootLayout from "@/components/app-root-layout";
import "@/echo"; // Initialize Laravel Echo for real-time
import { ThemeProvider } from "@/hooks/use-theme";
import type { Page } from "@inertiajs/core";
import { createInertiaApp, router } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot, hydrateRoot } from "react-dom/client";
import "./bootstrap"; // Initialize Axios

const appPages = import.meta.glob("./pages/**/*.tsx");
const modulePages = import.meta.glob("../../Modules/**/resources/assets/js/Pages/**/*.tsx");
const appElement = document.getElementById("app");
const initialPage = resolveInitialPage(appElement);

if (appElement !== null && initialPage !== null) {
    createInertiaApp({
        id: "app",
        page: initialPage,
        title: (title) => {
            // Use dynamic appName from shared props, fallback to VITE_APP_NAME
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const windowAppName = (window as any).appName;
            const envAppName = import.meta.env.VITE_APP_NAME;
            const appName = windowAppName || envAppName || "KoAkademy";
            return title ? `${title} - ${appName}` : appName;
        },
        resolve: async (name) => {
            const modulePagePath = Object.keys(modulePages).find((path) => path.endsWith(`/resources/assets/js/Pages/${name}.tsx`));

            const module =
                modulePagePath
                    ? await modulePages[modulePagePath]()
                    : await resolvePageComponent(`./pages/${name}.tsx`, appPages);

            const page = (module as any).default;

            if (!page.layout) {
                page.layout = (children: any) => <AppRootLayout>{children}</AppRootLayout>;
            }

            return module;
        },
        setup({ el, App, props }) {
            router.on("start", () => document.documentElement.classList.add("is-navigating"));
            router.on("finish", () => document.documentElement.classList.remove("is-navigating"));

            // @ts-ignore
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
} else if (appElement !== null) {
    console.error("Inertia initial page payload is missing or invalid.");
}

function resolveInitialPage(element: HTMLElement | null): Page | null {
    const pagePayload = element?.dataset.page;

    if (!pagePayload || pagePayload === "null") {
        return null;
    }

    try {
        return JSON.parse(pagePayload) as Page;
    } catch (error) {
        console.error("Failed to parse the Inertia initial page payload.", error);

        return null;
    }
}
