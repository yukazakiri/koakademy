import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link } from "@inertiajs/react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import {
    Activity,
    ArrowRight,
    ArrowUpRight,
    CheckCircle2,
    ChevronRight,
    Command,
    Compass,
    Grid3X3,
    Layers,
    LayoutGrid,
    List as ListIcon,
    Lock,
    Search,
    Server,
    Settings2,
    Shield,
    Sparkles,
    X,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import {
    categoryThemes,
    getSystemSettingsStatus,
    getVisibleSystemSettingsGroups,
    systemSettingsGroups,
    SystemSettingsGroupKey,
    SystemSettingsItem,
    SystemSettingsStateBadge,
} from "./settings-catalog";
import type { SystemManagementAccess } from "./types";

interface SystemManagementHomeProps {
    user: User;
    access: SystemManagementAccess;
}

type ViewMode = "grid" | "list";

export default function SystemManagementHome({ user, access }: SystemManagementHomeProps) {
    const [query, setQuery] = useState("");
    const [selectedCategory, setSelectedCategory] = useState<SystemSettingsGroupKey | "all">("all");
    const [viewMode, setViewMode] = useState<ViewMode>("grid");
    const searchInputRef = useRef<HTMLInputElement>(null);
    const reducedMotion = useReducedMotion();

    // Keyboard shortcut to focus search with '/' and clear with 'Esc'
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === "/" && document.activeElement !== searchInputRef.current && !["INPUT", "TEXTAREA"].includes((document.activeElement as HTMLElement)?.tagName)) {
                e.preventDefault();
                searchInputRef.current?.focus();
            } else if (e.key === "Escape" && document.activeElement === searchInputRef.current) {
                if (query) {
                    setQuery("");
                } else {
                    searchInputRef.current?.blur();
                }
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [query]);

    const normalizedQuery = query.trim().toLowerCase();

    const allVisibleGroups = useMemo(() => getVisibleSystemSettingsGroups(access), [access]);

    const totalAvailableItems = useMemo(
        () => allVisibleGroups.reduce((total, group) => total + group.items.length, 0),
        [allVisibleGroups],
    );

    const telemetryCounts = useMemo(() => {
        let editable = 0;
        let deployment = 0;
        let monitor = 0;
        let readonly = 0;

        allVisibleGroups.forEach((group) => {
            group.items.forEach((item) => {
                const status = getSystemSettingsStatus(item, access);
                if (item.mode === "deployment") deployment++;
                else if (item.mode === "monitor") monitor++;
                else if (status.label === "Editable") editable++;
                else readonly++;
            });
        });

        return { editable, deployment, monitor, readonly };
    }, [allVisibleGroups, access]);

    const filteredGroups = useMemo(() => {
        return allVisibleGroups
            .filter((group) => {
                if (selectedCategory === "all") return true;
                return group.key === selectedCategory;
            })
            .map((group) => ({
                ...group,
                items: group.items.filter((item) => {
                    if (!normalizedQuery) return true;
                    const searchable = [
                        group.label,
                        group.description,
                        item.label,
                        item.description,
                        ...item.keywords,
                    ]
                        .join(" ")
                        .toLowerCase();
                    return searchable.includes(normalizedQuery);
                }),
            }))
            .filter((group) => group.items.length > 0);
    }, [allVisibleGroups, selectedCategory, normalizedQuery]);

    const matchingItemsCount = useMemo(
        () => filteredGroups.reduce((total, group) => total + group.items.length, 0),
        [filteredGroups],
    );

    const transitionConfig = reducedMotion
        ? { duration: 0 }
        : { type: "spring" as const, bounce: 0, duration: 0.28 };

    return (
        <AdminLayout user={user} title="System Settings">
            <Head title="System Management" />

            <div className="system-settings mx-auto w-full max-w-[92rem] space-y-6 pb-12">
                {/* Control Center Hero Panel */}
                <header className="relative overflow-hidden rounded-2xl border border-border/60 bg-card/65 p-5 shadow-xs backdrop-blur-md sm:p-7">
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <div className="max-w-2xl">
                            <div className="flex items-center gap-2">
                                <span className="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Settings2 className="size-4" aria-hidden="true" />
                                </span>
                                <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Institutional Control Plane
                                </span>
                            </div>

                            <h1 className="mt-2.5 text-2xl font-bold tracking-tight text-foreground sm:text-3xl lg:text-4xl">
                                System Management
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground leading-relaxed sm:text-base">
                                Configure campus parameters, academic computation, official finance instruments, communications, and live telemetry.
                            </p>

                            {/* Telemetry Indicator Pills */}
                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                <Badge variant="outline" className="h-6 gap-1.5 px-2 text-xs font-medium border-border/70 bg-background/60">
                                    <span className="size-1.5 rounded-full bg-primary" />
                                    <span>{totalAvailableItems} Configuration Engines</span>
                                </Badge>
                                <Badge variant="outline" className="h-6 gap-1.5 px-2 text-xs font-medium border-emerald-500/30 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300">
                                    <span className="size-1.5 rounded-full bg-emerald-500" />
                                    <span>{telemetryCounts.editable} Editable</span>
                                </Badge>
                                <Badge variant="outline" className="h-6 gap-1.5 px-2 text-xs font-medium border-sky-500/30 bg-sky-500/5 text-sky-700 dark:text-sky-300">
                                    <span className="size-1.5 rounded-full bg-sky-500" />
                                    <span>{telemetryCounts.deployment} Server-Managed</span>
                                </Badge>
                                <Badge variant="outline" className="h-6 gap-1.5 px-2 text-xs font-medium border-emerald-500/30 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300">
                                    <span className="size-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                    <span>{telemetryCounts.monitor} Live Telemetry</span>
                                </Badge>
                            </div>
                        </div>

                        {/* Search & Shortcut Input */}
                        <div className="w-full lg:max-w-md">
                            <label htmlFor="system-settings-search" className="mb-2 block text-xs font-medium text-muted-foreground">
                                Quick search setting modules
                            </label>
                            <div className="relative">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    ref={searchInputRef}
                                    id="system-settings-search"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Search school, grading, mail, API, oauth…"
                                    className="h-10 pl-9 pr-14 text-sm bg-background/80 border-border/70"
                                />
                                {query ? (
                                    <button
                                        onClick={() => setQuery("")}
                                        className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground text-xs"
                                        aria-label="Clear search"
                                    >
                                        <X className="size-4" />
                                    </button>
                                ) : (
                                    <kbd className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 hidden sm:inline-flex h-5 items-center gap-0.5 rounded border border-border bg-muted/60 px-1.5 font-mono text-[10px] text-muted-foreground">
                                        /
                                    </kbd>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Category Filter Pills & View Mode Switcher */}
                    <div className="mt-6 flex flex-col gap-3 border-t border-border/50 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <button
                                onClick={() => setSelectedCategory("all")}
                                className={cn(
                                    "relative h-8 rounded-lg px-3 text-xs font-medium transition-colors outline-none",
                                    selectedCategory === "all"
                                        ? "bg-primary text-primary-foreground font-semibold shadow-xs"
                                        : "text-muted-foreground hover:bg-muted/70 hover:text-foreground",
                                )}
                            >
                                All Modules ({totalAvailableItems})
                            </button>

                            {allVisibleGroups.map((group) => {
                                const active = selectedCategory === group.key;
                                const theme = categoryThemes[group.key];

                                return (
                                    <button
                                        key={group.key}
                                        onClick={() => setSelectedCategory(group.key)}
                                        className={cn(
                                            "relative h-8 rounded-lg px-3 text-xs font-medium transition-colors outline-none",
                                            active
                                                ? "bg-accent text-foreground font-semibold shadow-xs border border-border/80"
                                                : "text-muted-foreground hover:bg-muted/70 hover:text-foreground",
                                        )}
                                    >
                                        <span>{group.label}</span>
                                        <span className="ml-1.5 opacity-60 font-mono text-[11px]">({group.items.length})</span>
                                    </button>
                                );
                            })}
                        </div>

                        {/* View Switcher: Grid vs List */}
                        <div className="flex items-center gap-1 self-end sm:self-center border border-border/60 bg-muted/40 p-0.5 rounded-lg">
                            <button
                                onClick={() => setViewMode("grid")}
                                className={cn(
                                    "flex items-center gap-1.5 h-7 px-2.5 rounded-md text-xs font-medium transition-colors",
                                    viewMode === "grid"
                                        ? "bg-background text-foreground shadow-xs"
                                        : "text-muted-foreground hover:text-foreground",
                                )}
                                aria-label="Grid layout"
                            >
                                <LayoutGrid className="size-3.5" />
                                <span className="hidden sm:inline">Grid</span>
                            </button>
                            <button
                                onClick={() => setViewMode("list")}
                                className={cn(
                                    "flex items-center gap-1.5 h-7 px-2.5 rounded-md text-xs font-medium transition-colors",
                                    viewMode === "list"
                                        ? "bg-background text-foreground shadow-xs"
                                        : "text-muted-foreground hover:text-foreground",
                                )}
                                aria-label="List layout"
                            >
                                <ListIcon className="size-3.5" />
                                <span className="hidden sm:inline">List</span>
                            </button>
                        </div>
                    </div>
                </header>

                {/* Main Results Section */}
                <section aria-label="Available system settings" className="space-y-8">
                    {filteredGroups.length > 0 ? (
                        filteredGroups.map((group) => {
                            const theme = categoryThemes[group.key];

                            return (
                                <section key={group.key} aria-labelledby={`settings-group-heading-${group.key}`} className="space-y-3">
                                    <div className="flex flex-col gap-0.5 sm:flex-row sm:items-center sm:justify-between border-b border-border/40 pb-2">
                                        <div className="flex items-center gap-2">
                                            <span className={cn("size-2 rounded-full", theme.accentBg, theme.accentText)} />
                                            <h2
                                                id={`settings-group-heading-${group.key}`}
                                                className="text-base font-semibold tracking-tight text-foreground"
                                            >
                                                {group.label}
                                            </h2>
                                            <span className="text-xs font-mono text-muted-foreground/60">
                                                ({group.items.length})
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted-foreground">{group.description}</p>
                                    </div>

                                    {viewMode === "grid" ? (
                                        <div className="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                                            {group.items.map((item) => {
                                                const Icon = item.icon;
                                                const status = getSystemSettingsStatus(item, access);

                                                return (
                                                    <motion.div key={item.key} layout transition={transitionConfig}>
                                                        <Link
                                                            href={item.href}
                                                            prefetch
                                                            cacheFor="30s"
                                                            className={cn(
                                                                "group relative flex flex-col justify-between rounded-xl border border-border/60 bg-card/70 p-4.5 shadow-xs transition-all duration-200 outline-none",
                                                                "hover:border-border hover:bg-card hover:shadow-md active:scale-[0.985] focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2",
                                                            )}
                                                        >
                                                            <div>
                                                                <div className="flex items-start justify-between gap-3">
                                                                    <div
                                                                        className={cn(
                                                                            "flex size-10 items-center justify-center rounded-xl border shadow-xs transition-transform duration-200 group-hover:scale-105",
                                                                            theme.accentBg,
                                                                            theme.accentBorder,
                                                                            theme.accentText,
                                                                        )}
                                                                    >
                                                                        <Icon className="size-5" aria-hidden="true" />
                                                                    </div>

                                                                    <div className="flex items-center gap-1.5">
                                                                        <Badge
                                                                            variant="outline"
                                                                            className={cn(
                                                                                "h-5 gap-1.5 px-2 text-[11px] font-normal border-border/60 bg-background/60",
                                                                                status.badgeClass,
                                                                            )}
                                                                        >
                                                                            <span
                                                                                className={cn("size-1.5 rounded-full shrink-0", status.dotColor)}
                                                                                aria-hidden="true"
                                                                            />
                                                                            <span>{status.label}</span>
                                                                        </Badge>

                                                                        <ArrowUpRight
                                                                            className="size-4 text-muted-foreground/60 transition-all duration-200 group-hover:text-foreground group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                                                            aria-hidden="true"
                                                                        />
                                                                    </div>
                                                                </div>

                                                                <h3 className="mt-3.5 text-sm font-semibold tracking-tight text-foreground group-hover:text-primary transition-colors">
                                                                    {item.label}
                                                                </h3>
                                                                <p className="mt-1 text-xs text-muted-foreground line-clamp-2 leading-relaxed">
                                                                    {item.description}
                                                                </p>
                                                            </div>

                                                            <div className="mt-4 flex items-center justify-between border-t border-border/30 pt-3 text-[11px] text-muted-foreground/70">
                                                                <span className="font-mono text-[10px] uppercase tracking-wider">{group.label}</span>
                                                                <span className="group-hover:text-foreground font-medium transition-colors">
                                                                    Open configuration →
                                                                </span>
                                                            </div>
                                                        </Link>
                                                    </motion.div>
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        /* Compact macOS / iOS Style List View */
                                        <div className="divide-y divide-border/40 rounded-xl border border-border/60 bg-card/70 overflow-hidden shadow-xs">
                                            {group.items.map((item) => {
                                                const Icon = item.icon;
                                                const status = getSystemSettingsStatus(item, access);

                                                return (
                                                    <Link
                                                        key={item.key}
                                                        href={item.href}
                                                        prefetch
                                                        cacheFor="30s"
                                                        className="group flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-muted/50 active:bg-muted/70 outline-none"
                                                    >
                                                        <div className="flex items-center gap-3.5 min-w-0">
                                                            <div
                                                                className={cn(
                                                                    "flex size-8 shrink-0 items-center justify-center rounded-lg border shadow-xs transition-transform group-hover:scale-105",
                                                                    theme.accentBg,
                                                                    theme.accentBorder,
                                                                    theme.accentText,
                                                                )}
                                                            >
                                                                <Icon className="size-4" aria-hidden="true" />
                                                            </div>

                                                            <div className="min-w-0">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="text-sm font-semibold text-foreground group-hover:text-primary transition-colors truncate">
                                                                        {item.label}
                                                                    </span>
                                                                </div>
                                                                <p className="text-xs text-muted-foreground truncate">{item.description}</p>
                                                            </div>
                                                        </div>

                                                        <div className="flex items-center gap-3 shrink-0">
                                                            <Badge
                                                                variant="outline"
                                                                className={cn(
                                                                    "h-5 gap-1.5 px-2 text-[11px] font-normal border-border/60 bg-background/60",
                                                                    status.badgeClass,
                                                                )}
                                                            >
                                                                <span
                                                                    className={cn("size-1.5 rounded-full shrink-0", status.dotColor)}
                                                                    aria-hidden="true"
                                                                />
                                                                <span>{status.label}</span>
                                                            </Badge>

                                                            <ChevronRight className="size-4 text-muted-foreground/60 transition-transform group-hover:translate-x-0.5 group-hover:text-foreground" />
                                                        </div>
                                                    </Link>
                                                );
                                            })}
                                        </div>
                                    )}
                                </section>
                            );
                        })
                    ) : (
                        <div className="rounded-2xl border border-dashed border-border/80 bg-card/50 p-12 text-center">
                            <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                <Search className="size-6" />
                            </div>
                            <h3 className="mt-3 text-sm font-semibold text-foreground">No matching system settings found</h3>
                            <p className="mt-1 text-xs text-muted-foreground">
                                No configuration modules matched your search "{query}".
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setQuery("");
                                    setSelectedCategory("all");
                                }}
                                className="mt-4 h-8 text-xs"
                            >
                                Reset search filters
                            </Button>
                        </div>
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}
