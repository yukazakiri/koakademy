import AppRootLayout from "@/components/app-root-layout";
import type { ResolvedComponent } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import type { ReactNode } from "react";

export type InertiaPageModule = {
    default: ResolvedComponent;
};

export type InertiaPageModules = Record<string, () => Promise<InertiaPageModule>>;

function wrapResolvedPage(original: ResolvedComponent): ResolvedComponent {
    const Original = original;
    const Page = ((props: Record<string, unknown>) => <Original {...props} />) as ResolvedComponent;

    Page.layout = original.layout ?? ((children: ReactNode) => <AppRootLayout>{children}</AppRootLayout>);

    return Page;
}

export async function resolveInertiaPage(name: string, appPages: InertiaPageModules, modulePages: InertiaPageModules): Promise<ResolvedComponent> {
    const modulePagePath = Object.keys(modulePages).find((path) => path.endsWith(`/resources/assets/js/Pages/${name}.tsx`));
    const pageModule = modulePagePath ? await modulePages[modulePagePath]() : await resolvePageComponent(`./pages/${name}.tsx`, appPages);

    return wrapResolvedPage(pageModule.default);
}
