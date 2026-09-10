import AppRootLayout from "@/components/app-root-layout";
import type { ResolvedComponent } from "@inertiajs/react";
import type { ReactNode } from "react";

export type InertiaPageModule = {
    default: ResolvedComponent;
};

export type InertiaPageModules = Record<string, () => Promise<InertiaPageModule>>;

// Inlined from laravel-vite-plugin/inertia-helpers. That package is a
// build-time (dev) dependency, but this resolver runs inside the Node SSR
// bundle where Vite may leave the bare import for Node to resolve at
// runtime. Importing it here would make the production image carry the
// whole Vite toolchain (or crash with ERR_MODULE_NOT_FOUND after
// `npm prune --omit=dev`). Keep this helper self-contained; keep it in
// sync with the upstream implementation.
async function resolvePageComponent(path: string | string[], pages: InertiaPageModules): Promise<InertiaPageModule> {
    for (const p of Array.isArray(path) ? path : [path]) {
        const page = pages[p];

        if (typeof page === "undefined") {
            continue;
        }

        return typeof page === "function" ? page() : page;
    }

    throw new Error(`Page not found: ${path}`);
}

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
