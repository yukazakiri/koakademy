import { Deferred, router } from "@inertiajs/react";
import { Skeleton as BoneyardSkeleton } from "boneyard-js/react";
import { useEffect, useState, type ReactNode } from "react";

import { ADMIN_PAGE_DEFINITIONS, type AdminPageDefinition, type AdminSkeletonVariant } from "@/config/admin-page-definitions";
import { cn } from "@/lib/utils";

interface AdminSkeletonFallbackProps {
    variant: AdminSkeletonVariant;
}

function Bone({ className }: { className?: string }) {
    return <div aria-hidden="true" className={cn("bg-muted rounded-md", className)} />;
}

function AdminSkeletonFallback({ variant }: AdminSkeletonFallbackProps) {
    if (variant === "dashboard" || variant === "analytics") {
        return (
            <div className="w-full space-y-6" data-admin-skeleton-fallback={variant}>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <Bone className="h-28 w-full" key={index} />
                    ))}
                </div>
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
                    <Bone className="h-80 w-full" />
                    <Bone className="h-80 w-full" />
                </div>
            </div>
        );
    }

    if (variant === "detail") {
        return (
            <div className="w-full space-y-6" data-admin-skeleton-fallback={variant}>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-2">
                        <Bone className="h-8 w-64" />
                        <Bone className="h-4 w-44" />
                    </div>
                    <Bone className="h-10 w-28" />
                </div>
                <div className="grid gap-6 lg:grid-cols-3">
                    <Bone className="h-52 w-full lg:col-span-2" />
                    <Bone className="h-52 w-full" />
                </div>
                <Bone className="h-72 w-full" />
            </div>
        );
    }

    if (variant === "form" || variant === "settings") {
        return (
            <div className="w-full space-y-6" data-admin-skeleton-fallback={variant}>
                <div className="space-y-2">
                    <Bone className="h-8 w-64" />
                    <Bone className="h-4 w-80 max-w-full" />
                </div>
                <div className="grid gap-6 md:grid-cols-2">
                    {Array.from({ length: 6 }).map((_, index) => (
                        <div className="space-y-2" key={index}>
                            <Bone className="h-4 w-28" />
                            <Bone className="h-10 w-full" />
                        </div>
                    ))}
                </div>
                <div className="flex justify-end gap-3">
                    <Bone className="h-10 w-24" />
                    <Bone className="h-10 w-32" />
                </div>
            </div>
        );
    }

    return (
        <div className="w-full space-y-6" data-admin-skeleton-fallback={variant}>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-2">
                    <Bone className="h-8 w-64" />
                    <Bone className="h-4 w-80 max-w-full" />
                </div>
                <Bone className="h-10 w-32" />
            </div>
            <div className="flex flex-col gap-3 sm:flex-row">
                <Bone className="h-10 w-full sm:max-w-sm" />
                <Bone className="h-10 w-full sm:w-40" />
                <Bone className="h-10 w-full sm:w-40" />
            </div>
            <div className="border-border/60 overflow-hidden rounded-xl border">
                <div className="border-border/60 grid grid-cols-4 gap-4 border-b p-4">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <Bone className="h-4 w-24 max-w-full" key={index} />
                    ))}
                </div>
                {Array.from({ length: 7 }).map((_, index) => (
                    <div className="border-border/40 grid grid-cols-4 gap-4 border-b p-4 last:border-0" key={index}>
                        <Bone className="h-5 w-32 max-w-full" />
                        <Bone className="h-5 w-40 max-w-full" />
                        <Bone className="h-5 w-24 max-w-full" />
                        <Bone className="h-5 w-16 max-w-full" />
                    </div>
                ))}
            </div>
        </div>
    );
}

function useReducedMotion(): boolean {
    const [reducedMotion, setReducedMotion] = useState(false);

    useEffect(() => {
        const mediaQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
        const update = () => setReducedMotion(mediaQuery.matches);

        update();
        mediaQuery.addEventListener("change", update);

        return () => mediaQuery.removeEventListener("change", update);
    }, []);

    return reducedMotion;
}

export interface AdminSkeletonProps {
    name: string;
    loading?: boolean;
    variant?: AdminSkeletonVariant;
    label?: string;
    className?: string;
    children?: ReactNode;
    fixture?: ReactNode;
}

export function AdminSkeleton({
    name,
    loading = true,
    variant = "list",
    label = "Loading administrator page",
    className,
    children = null,
    fixture,
}: AdminSkeletonProps) {
    const reducedMotion = useReducedMotion();

    return (
        <div
            aria-busy={loading}
            aria-label={loading ? label : undefined}
            className={cn("w-full", className)}
            data-admin-skeleton={name}
            role={loading ? "status" : undefined}
        >
            {loading && <span className="sr-only">{label}</span>}
            <BoneyardSkeleton
                animate={reducedMotion ? "solid" : "shimmer"}
                boneClass="admin-boneyard-bone"
                className="w-full"
                darkColor="rgba(255, 255, 255, 0.08)"
                fixture={fixture ?? <AdminSkeletonFallback variant={variant} />}
                fallback={<AdminSkeletonFallback variant={variant} />}
                loading={loading}
                name={name}
                select="viewport"
                transition={300}
            >
                {children}
            </BoneyardSkeleton>
        </div>
    );
}

export function AdminSkeletonFixtureCatalog() {
    const definitions = Array.from(new Map(ADMIN_PAGE_DEFINITIONS.map((definition) => [definition.skeleton, definition])).values());

    return (
        <main className="bg-background min-h-screen w-full p-6" data-admin-skeleton-fixtures>
            <div className="mx-auto flex max-w-7xl flex-col gap-12">
                {definitions.map((definition) => (
                    <section data-admin-skeleton-fixture={definition.skeleton} key={definition.skeleton}>
                        <AdminSkeleton
                            label={`${definition.component} fixture`}
                            loading={false}
                            name={definition.skeleton}
                            variant={definition.fixture}
                        />
                    </section>
                ))}
            </div>
        </main>
    );
}

interface AdminDeferredSectionProps {
    data: string | string[];
    name: string;
    variant?: AdminSkeletonVariant;
    label?: string;
    className?: string;
    children: ReactNode | ((props: { reloading: boolean }) => ReactNode);
}

export function AdminDeferredSection({ data, name, variant = "list", label = "Loading section", className, children }: AdminDeferredSectionProps) {
    const deferredKeys = Array.isArray(data) ? data : [data];

    return (
        <Deferred
            data={data}
            fallback={<AdminSkeleton className={className} label={label} name={name} variant={variant} />}
            rescue={({ reloading }) => (
                <div
                    aria-live="polite"
                    className={cn(
                        "flex w-full flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-8 text-center",
                        className,
                    )}
                    role="status"
                >
                    <p className="text-muted-foreground text-sm">{reloading ? "Retrying section…" : "This section could not be loaded."}</p>
                    {!reloading && (
                        <button
                            className="text-primary text-sm font-medium underline-offset-4 hover:underline"
                            onClick={() => router.reload({ only: deferredKeys })}
                            type="button"
                        >
                            Try again
                        </button>
                    )}
                </div>
            )}
        >
            {children}
        </Deferred>
    );
}

export function AdminPageNavigationSkeleton({ definition }: { definition: AdminPageDefinition }) {
    return (
        <AdminSkeleton
            className="min-h-[28rem]"
            label={`Loading ${definition.component.replaceAll("/", " ")}`}
            name={definition.skeleton}
            variant={definition.variant}
        />
    );
}
