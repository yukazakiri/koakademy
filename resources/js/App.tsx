import AdminLayout from "@/components/administrators/admin-layout";
import { AdminPageNavigationSkeleton } from "@/components/administrators/admin-skeleton";
import AppRootLayout from "@/components/app-root-layout";
import { resolveAdminPageDefinitionByComponent, type AdminPageDefinition } from "@/config/admin-page-definitions";
import "@/echo"; // Initialize Laravel Echo for real-time
import { ThemeProvider } from "@/hooks/use-theme";
import { createInertiaApp, router } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import type { ReactNode } from "react";
import { createRoot, hydrateRoot } from "react-dom/client";
import "./bones/registry";
import "./bootstrap"; // Initialize Axios

import type { User } from "@/types/user";

type InertiaPageComponent = ((props: Record<string, unknown>) => ReactNode) & {
    layout?: (children: ReactNode) => ReactNode;
};
type InertiaPageModule = {
    default: InertiaPageComponent;
};

type AdminInstantPageProps = Record<string, unknown> & {
    __adminLoading?: boolean;
    auth?: {
        user?: User | null;
    };
    user?: User;
};

function AdminInstantLoadingPage({
    component,
    definition,
    props,
}: {
    component: string;
    definition: AdminPageDefinition;
    props: AdminInstantPageProps;
}) {
    const user = props.auth?.user ?? props.user;
    const skeleton = <AdminPageNavigationSkeleton definition={definition} />;

    if (!user) {
        return skeleton;
    }

    return (
        <AdminLayout title={component} user={user}>
            {skeleton}
        </AdminLayout>
    );
}

function AdminInstantPage({ component, original, props }: { component: string; original: InertiaPageComponent; props: AdminInstantPageProps }) {
    const definition = resolveAdminPageDefinitionByComponent(component);

    if (!props.__adminLoading || !definition) {
        return original(props);
    }

    return <AdminInstantLoadingPage component={component} definition={definition} props={props} />;
}

declare global {
    interface Window {
        appName?: string;
    }
}

const appPages = import.meta.glob<InertiaPageModule>("./pages/**/*.tsx");
const modulePages = {
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
    resolve: async (name) => {
        const modulePagePath = Object.keys(modulePages).find((path) => path.endsWith(`/resources/assets/js/Pages/${name}.tsx`));

        const module = modulePagePath ? await modulePages[modulePagePath]() : await resolvePageComponent(`./pages/${name}.tsx`, appPages);
        const resolvedModule = module as InertiaPageModule;

        const page = resolvedModule.default;

        if (!page.layout) {
            page.layout = (children: ReactNode) => <AppRootLayout>{children}</AppRootLayout>;
        }

        const adminDefinition = resolveAdminPageDefinitionByComponent(name);

        if (!adminDefinition) {
            return resolvedModule.default;
        }

        const instantPage = ((props: Record<string, unknown>) => (
            <AdminInstantPage component={name} original={page} props={props as AdminInstantPageProps} />
        )) as InertiaPageComponent;

        instantPage.layout = page.layout;

        return instantPage;
    },
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
