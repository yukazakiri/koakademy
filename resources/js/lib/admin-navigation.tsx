import type { VisitOptions } from "@inertiajs/core";
import { Link, router, type InertiaLinkProps } from "@inertiajs/react";
import type { ReactNode } from "react";

export function adminVisit(href: string, options: VisitOptions = {}): void {
    router.visit(href, options);
}

export interface AdminLinkProps extends Omit<InertiaLinkProps, "component" | "href" | "instant" | "pageProps" | "prefetch" | "cacheFor"> {
    href: string;
    prefetch?: InertiaLinkProps["prefetch"];
    cacheFor?: InertiaLinkProps["cacheFor"];
    children?: ReactNode;
}

export function AdminLink({ href, prefetch = false, cacheFor = 0, children, ...props }: AdminLinkProps) {
    return (
        <Link {...props} cacheFor={cacheFor} href={href} prefetch={prefetch}>
            {children}
        </Link>
    );
}
