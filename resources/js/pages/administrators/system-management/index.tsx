import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { motion, useReducedMotion } from "framer-motion";
import { ArrowUpRight, Search, Settings2 } from "lucide-react";
import { useMemo, useState } from "react";

import { getVisibleSystemSettingsGroups, SystemSettingsStateBadge } from "./settings-catalog";
import type { SystemManagementAccess } from "./types";

interface SystemManagementHomeProps {
    user: User;
    access: SystemManagementAccess;
}

export default function SystemManagementHome({ user, access }: SystemManagementHomeProps) {
    const [query, setQuery] = useState("");
    const reducedMotion = useReducedMotion();
    const normalizedQuery = query.trim().toLocaleLowerCase();
    const visibleGroups = useMemo(
        () =>
            getVisibleSystemSettingsGroups(access)
                .map((group) => ({
                    ...group,
                    items: group.items.filter((item) => {
                        if (normalizedQuery === "") return true;

                        const searchable = [group.label, group.description, item.label, item.description, ...item.keywords]
                            .join(" ")
                            .toLocaleLowerCase();

                        return searchable.includes(normalizedQuery);
                    }),
                }))
                .filter((group) => group.items.length > 0),
        [access, normalizedQuery],
    );

    const transition = reducedMotion ? { duration: 0 } : { type: "spring" as const, bounce: 0, duration: 0.32 };

    return (
        <AdminLayout user={user} title="System Settings">
            <Head title="System Settings" />

            <div className="system-settings mx-auto w-full max-w-[80rem] space-y-8 pb-8">
                <header className="border-border/70 bg-card/85 [@media(prefers-contrast:more)]:border-foreground/60 [@media(prefers-reduced-transparency:reduce)]:bg-card overflow-hidden rounded-2xl border px-5 py-6 shadow-sm backdrop-blur-xl sm:px-7 sm:py-8">
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-2xl">
                            <span className="bg-primary text-primary-foreground flex size-10 items-center justify-center rounded-xl shadow-sm">
                                <Settings2 className="size-5" aria-hidden="true" />
                            </span>
                            <p className="text-muted-foreground mt-5 text-xs font-semibold tracking-[0.1em] uppercase">Administration workspace</p>
                            <h1 className="text-foreground mt-2 text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">System Settings</h1>
                            <p className="text-muted-foreground mt-3 max-w-xl text-sm leading-6 sm:text-base">
                                Configure the institution, student experience, communications, and connected services from one focused workspace.
                            </p>
                        </div>
                        <div className="w-full lg:max-w-sm">
                            <label htmlFor="system-settings-search" className="text-foreground mb-2 block text-sm font-medium">
                                Find a setting
                            </label>
                            <div className="relative">
                                <Search
                                    className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                    aria-hidden="true"
                                />
                                <Input
                                    id="system-settings-search"
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Search enrollment, API, grading…"
                                    className="bg-background/80 h-10 pl-9"
                                />
                            </div>
                        </div>
                    </div>
                </header>

                <section aria-label="Available system settings" className="space-y-8">
                    {visibleGroups.length > 0 ? (
                        visibleGroups.map((group) => (
                            <section key={group.key} aria-labelledby={`settings-home-${group.key}`}>
                                <div className="mb-3 flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                                    <h2 id={`settings-home-${group.key}`} className="text-foreground text-lg font-semibold tracking-[-0.015em]">
                                        {group.label}
                                    </h2>
                                    <p className="text-muted-foreground text-sm">{group.description}</p>
                                </div>
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    {group.items.map((item) => {
                                        const Icon = item.icon;

                                        return (
                                            <motion.div key={item.key} layout transition={transition}>
                                                <Link
                                                    href={item.href}
                                                    className="group border-border/70 bg-card [@media(prefers-contrast:more)]:border-foreground/60 hover:border-primary/30 focus-visible:ring-ring flex min-h-44 flex-col rounded-2xl border p-5 shadow-sm transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none motion-reduce:transform-none"
                                                >
                                                    <div className="flex items-start justify-between gap-3">
                                                        <span className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-xl transition-transform duration-200 group-hover:scale-105 motion-reduce:transform-none">
                                                            <Icon className="size-5" aria-hidden="true" />
                                                        </span>
                                                        <ArrowUpRight
                                                            className="text-muted-foreground group-hover:text-foreground size-4 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transform-none"
                                                            aria-hidden="true"
                                                        />
                                                    </div>
                                                    <h3 className="text-foreground mt-5 text-base font-semibold">{item.label}</h3>
                                                    <p className="text-muted-foreground mt-1.5 text-sm leading-5">{item.description}</p>
                                                    <div className="mt-auto pt-4">
                                                        <SystemSettingsStateBadge item={item} access={access} />
                                                    </div>
                                                </Link>
                                            </motion.div>
                                        );
                                    })}
                                </div>
                            </section>
                        ))
                    ) : (
                        <div className="border-border bg-card rounded-2xl border border-dashed px-6 py-12 text-center">
                            <Badge variant="outline" className="mb-3">
                                No matching settings
                            </Badge>
                            <p className="text-muted-foreground text-sm">Try another term, such as “campus”, “email”, or “API”.</p>
                        </div>
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}
