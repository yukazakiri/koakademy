import type { PageProps, VisitOptions } from "@inertiajs/core";
import { Link, router, type InertiaLinkProps } from "@inertiajs/react";
import type { ReactNode } from "react";

import { resolveAdminPageDefinition } from "@/config/admin-page-definitions";

type InstantPageProps = NonNullable<VisitOptions["pageProps"]>;

function loadingPageProps(loadingProps: Record<string, unknown>): Exclude<InstantPageProps, Record<string, unknown>> {
    return (currentProps: PageProps, sharedProps: Partial<PageProps>) => ({
        ...sharedProps,
        ...currentProps,
        ...loadingProps,
    });
}

export function adminVisit(href: string, options: VisitOptions = {}): void {
    const definition = resolveAdminPageDefinition(href);

    if (options.method && options.method !== "get") {
        router.visit(href, options);

        return;
    }

    router.visit(href, {
        ...options,
        component: options.component ?? definition?.component,
        pageProps: options.pageProps ?? (definition ? loadingPageProps(definition.loadingProps) : null),
    });
}

export interface AdminLinkProps extends Omit<InertiaLinkProps, "component" | "href" | "instant" | "pageProps" | "prefetch" | "cacheFor"> {
    href: string;
    component?: InertiaLinkProps["component"];
    instant?: boolean;
    pageProps?: InertiaLinkProps["pageProps"];
    prefetch?: InertiaLinkProps["prefetch"];
    cacheFor?: InertiaLinkProps["cacheFor"];
    children?: ReactNode;
}

export function AdminLink({ href, component, instant = true, pageProps, prefetch = "hover", cacheFor = "30s", children, ...props }: AdminLinkProps) {
    const definition = resolveAdminPageDefinition(href);

    return (
        <Link
            {...props}
            cacheFor={cacheFor}
            component={component ?? definition?.component}
            href={href}
            instant={Boolean(component ?? definition?.component) && instant}
            pageProps={pageProps ?? (definition ? loadingPageProps(definition.loadingProps) : null)}
            prefetch={prefetch}
        >
            {children}
        </Link>
    );
}
