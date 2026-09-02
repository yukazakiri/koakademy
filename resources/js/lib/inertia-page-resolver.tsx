import AdminLayout from "@/components/administrators/admin-layout";
import { AdminPageNavigationSkeleton } from "@/components/administrators/admin-skeleton";
import AppRootLayout from "@/components/app-root-layout";
import { resolveAdminPageDefinitionByComponent, type AdminPageDefinition } from "@/config/admin-page-definitions";
import type { User } from "@/types/user";
import type { ResolvedComponent } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import type { ReactNode } from "react";

export type InertiaPageModule = {
    default: ResolvedComponent;
};

export type InertiaPageModules = Record<string, () => Promise<InertiaPageModule>>;

export type AdminInstantPageProps = Record<string, unknown> & {
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

function AdminInstantPage({
    component,
    original: Original,
    props,
}: {
    component: string;
    original: ResolvedComponent;
    props: AdminInstantPageProps;
}) {
    const definition = resolveAdminPageDefinitionByComponent(component);

    if (!props.__adminLoading || !definition) {
        return <Original {...props} />;
    }

    return <AdminInstantLoadingPage component={component} definition={definition} props={props} />;
}

function wrapResolvedPage(component: string, original: ResolvedComponent): ResolvedComponent {
    const Original = original;
    const Page = ((props: Record<string, unknown>) => <Original {...props} />) as ResolvedComponent;

    Page.layout = original.layout ?? ((children: ReactNode) => <AppRootLayout>{children}</AppRootLayout>);

    if (!resolveAdminPageDefinitionByComponent(component)) {
        return Page;
    }

    const InstantPage = ((props: Record<string, unknown>) => (
        <AdminInstantPage component={component} original={original} props={props as AdminInstantPageProps} />
    )) as ResolvedComponent;

    InstantPage.layout = Page.layout;

    return InstantPage;
}

export async function resolveInertiaPage(name: string, appPages: InertiaPageModules, modulePages: InertiaPageModules): Promise<ResolvedComponent> {
    const modulePagePath = Object.keys(modulePages).find((path) => path.endsWith(`/resources/assets/js/Pages/${name}.tsx`));
    const pageModule = modulePagePath ? await modulePages[modulePagePath]() : await resolvePageComponent(`./pages/${name}.tsx`, appPages);

    return wrapResolvedPage(name, pageModule.default);
}
