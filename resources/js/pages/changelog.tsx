import AdminLayout from "@/components/administrators/admin-layout";
import PortalLayout from "@/components/portal-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import { ChangelogEntry, VersionInfo } from "@/types/version";
import { Head } from "@inertiajs/react";
import {
    IconAlertTriangle,
    IconBug,
    IconCalendar,
    IconCheck,
    IconCode,
    IconExternalLink,
    IconGitBranch,
    IconHistory,
    IconRocket,
    IconSearch,
    IconShieldCheck,
    IconSparkles,
    IconTool,
    IconX,
} from "@tabler/icons-react";
import { useMemo, useState } from "react";

interface ChangelogProps {
    user: User;
    layout?: "admin" | "portal";
    version: string;
    versionInfo?: VersionInfo;
    changelog: ChangelogEntry[];
    changelog_source?: "github_releases" | "build_metadata";
    show_technical_links?: boolean;
    github_repo?: string;
}

type ChangeType = ChangelogEntry["changes"][number]["type"];
type FilterType = "all" | ChangeType;

const filters: { value: FilterType; label: string }[] = [
    { value: "all", label: "All updates" },
    { value: "feature", label: "Features" },
    { value: "fix", label: "Fixes" },
    { value: "improvement", label: "Improvements" },
    { value: "security", label: "Security" },
    { value: "breaking", label: "Breaking" },
];

const typeConfig = {
    feature: {
        label: "Feature",
        icon: IconRocket,
        iconClass: "bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300",
    },
    fix: {
        label: "Fix",
        icon: IconBug,
        iconClass: "bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300",
    },
    improvement: {
        label: "Improvement",
        icon: IconSparkles,
        iconClass: "bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300",
    },
    breaking: {
        label: "Breaking",
        icon: IconAlertTriangle,
        iconClass: "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
    },
    security: {
        label: "Security",
        icon: IconShieldCheck,
        iconClass: "bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-300",
    },
} satisfies Record<ChangeType, { label: string; icon: typeof IconRocket; iconClass: string }>;

const releaseTypeConfig: Record<string, { label: string; className: string }> = {
    major: { label: "Major release", className: "border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300" },
    minor: { label: "Minor release", className: "border-sky-300 bg-sky-50 text-sky-800 dark:bg-sky-950 dark:text-sky-300" },
    patch: { label: "Patch", className: "border-border bg-muted text-muted-foreground" },
    feature: { label: "Feature release", className: "border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" },
    bugfix: { label: "Bug fix", className: "border-rose-300 bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300" },
    chore: { label: "Maintenance", className: "border-border bg-muted text-muted-foreground" },
    stable: { label: "Stable release", className: "border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" },
    edge: { label: "Edge build", className: "border-violet-300 bg-violet-50 text-violet-800 dark:bg-violet-950 dark:text-violet-300" },
};

function formatDate(dateString: string): string {
    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
        return dateString;
    }

    return date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
}

function formatTimestamp(timestamp?: string | null): string | null {
    if (!timestamp) return null;

    const date = new Date(timestamp);
    if (Number.isNaN(date.getTime())) return null;

    return date.toLocaleString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
}

export default function Changelog({
    user,
    layout = "portal",
    version,
    versionInfo,
    changelog,
    changelog_source = "build_metadata",
    show_technical_links = false,
    github_repo,
}: ChangelogProps) {
    const [searchQuery, setSearchQuery] = useState("");
    const [activeFilter, setActiveFilter] = useState<FilterType>("all");

    const currentVersion = versionInfo?.version || version;
    const currentEntry = changelog.find((entry) => entry.version === currentVersion) ?? changelog[0];
    const releaseType = versionInfo?.release_type ?? currentEntry?.type ?? "patch";
    const releaseTypeDetails = releaseTypeConfig[releaseType] ?? releaseTypeConfig.patch;
    const deployedAt = formatTimestamp(versionInfo?.timestamp) ?? (currentEntry ? formatDate(currentEntry.date) : null);
    const developmentBuild = currentVersion.includes("-") || currentEntry?.prerelease;
    const githubReleasesUrl = github_repo ? `https://github.com/${github_repo}/releases` : null;

    const filteredChangelog = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();

        return changelog.filter((entry) => {
            const matchesSearch =
                query === "" ||
                entry.version.toLowerCase().includes(query) ||
                entry.title.toLowerCase().includes(query) ||
                entry.changes.some((change) => change.description.toLowerCase().includes(query));
            const matchesFilter = activeFilter === "all" || entry.changes.some((change) => change.type === activeFilter);

            return matchesSearch && matchesFilter;
        });
    }, [activeFilter, changelog, searchQuery]);

    const resetFilters = () => {
        setSearchQuery("");
        setActiveFilter("all");
    };

    const content = (
        <>
            <Head title="What's new" />
            <main className={cn("flex flex-1 flex-col", layout === "portal" && "px-4 pb-10 sm:px-6 lg:px-8")}>
                <header className="border-border border-b py-6 sm:py-8">
                    <div className="mx-auto w-full max-w-6xl">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                            <div className="max-w-2xl space-y-2">
                                <div className="text-primary flex items-center gap-2 text-sm font-medium">
                                    <IconHistory className="size-4" />
                                    Product updates
                                </div>
                                <h1 className="text-foreground text-3xl font-semibold sm:text-4xl">What&apos;s new</h1>
                                <p className="text-muted-foreground max-w-xl text-sm leading-6 sm:text-base">
                                    A transparent record of the features, fixes, and maintenance updates deployed to this portal.
                                </p>
                            </div>

                            {show_technical_links && githubReleasesUrl && (
                                <Button variant="outline" size="sm" asChild className="w-fit">
                                    <a href={githubReleasesUrl} target="_blank" rel="noopener noreferrer">
                                        <IconCode className="size-4" />
                                        View releases
                                        <IconExternalLink className="size-3.5" />
                                    </a>
                                </Button>
                            )}
                        </div>
                    </div>
                </header>

                <section className="border-border bg-muted/30 border-b py-5" aria-labelledby="current-deployment-heading">
                    <div className="mx-auto grid w-full max-w-6xl gap-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                        <div className="flex min-w-0 items-start gap-3">
                            <span className="bg-background border-border mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md border">
                                <IconCheck className="size-5 text-emerald-600" />
                            </span>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 id="current-deployment-heading" className="text-foreground text-sm font-semibold">
                                        Current deployment
                                    </h2>
                                    <Badge variant="outline" className={cn("rounded-sm", releaseTypeDetails.className)}>
                                        {releaseTypeDetails.label}
                                    </Badge>
                                    <Badge variant="outline" className="rounded-sm">
                                        {developmentBuild ? "Development channel" : "Stable channel"}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    <span className="text-foreground font-mono font-medium">v{currentVersion}</span>
                                    {deployedAt && <span> · Deployed {deployedAt}</span>}
                                </p>
                            </div>
                        </div>

                        <div className="text-muted-foreground flex items-start gap-2 text-xs leading-5 md:max-w-sm">
                            <IconGitBranch className="mt-0.5 size-4 shrink-0" />
                            <span>
                                {changelog_source === "github_releases"
                                    ? "Release notes are synced from the deployment's GitHub release record."
                                    : "GitHub release notes are temporarily unavailable. Build metadata is shown instead."}
                            </span>
                        </div>
                    </div>
                </section>

                <section className="mx-auto w-full max-w-6xl py-6 sm:py-8" aria-label="Release history">
                    <div className="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-center lg:justify-between">
                        <div className="overflow-x-auto pb-1">
                            <div className="flex min-w-max gap-1" role="group" aria-label="Filter updates">
                                {filters.map((filter) => (
                                    <Button
                                        key={filter.value}
                                        type="button"
                                        size="sm"
                                        variant={activeFilter === filter.value ? "secondary" : "ghost"}
                                        aria-pressed={activeFilter === filter.value}
                                        onClick={() => setActiveFilter(filter.value)}
                                        className="rounded-md"
                                    >
                                        {filter.label}
                                    </Button>
                                ))}
                            </div>
                        </div>

                        <div className="relative w-full lg:w-72">
                            <IconSearch className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                type="search"
                                aria-label="Search release notes"
                                placeholder="Search release notes"
                                value={searchQuery}
                                onChange={(event) => setSearchQuery(event.target.value)}
                                className="h-9 pr-9 pl-9"
                            />
                            {searchQuery && (
                                <button
                                    type="button"
                                    title="Clear search"
                                    aria-label="Clear search"
                                    onClick={() => setSearchQuery("")}
                                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 flex size-7 -translate-y-1/2 items-center justify-center rounded-sm"
                                >
                                    <IconX className="size-4" />
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="text-muted-foreground flex items-center justify-between py-5 text-sm">
                        <p>
                            {filteredChangelog.length} {filteredChangelog.length === 1 ? "release" : "releases"}
                        </p>
                        {(activeFilter !== "all" || searchQuery) && (
                            <Button type="button" variant="ghost" size="sm" onClick={resetFilters}>
                                Clear filters
                            </Button>
                        )}
                    </div>

                    {filteredChangelog.length === 0 ? (
                        <div className="border-border flex min-h-56 flex-col items-center justify-center rounded-md border border-dashed px-6 py-12 text-center">
                            <IconCalendar className="text-muted-foreground mb-3 size-8" />
                            <h2 className="text-foreground text-base font-semibold">No matching updates</h2>
                            <p className="text-muted-foreground mt-1 max-w-sm text-sm leading-6">
                                Try a different search term or clear the selected category.
                            </p>
                            <Button type="button" variant="outline" size="sm" onClick={resetFilters} className="mt-4">
                                Clear filters
                            </Button>
                        </div>
                    ) : (
                        <div className="space-y-5">
                            {filteredChangelog.map((entry) => {
                                const isCurrentVersion = entry.version === currentVersion;
                                const visibleChanges = entry.changes.filter((change) => activeFilter === "all" || change.type === activeFilter);
                                const entryReleaseType = releaseTypeConfig[entry.type] ?? releaseTypeConfig.patch;

                                return (
                                    <article
                                        key={entry.version}
                                        className={cn(
                                            "border-border bg-background overflow-hidden rounded-md border",
                                            isCurrentVersion && "border-primary/50",
                                        )}
                                    >
                                        <div className="grid md:grid-cols-[11rem_minmax(0,1fr)]">
                                            <div className="border-border bg-muted/20 border-b p-4 md:border-r md:border-b-0 md:p-5">
                                                <time
                                                    className="text-muted-foreground text-xs font-medium"
                                                    dateTime={entry.published_at ?? entry.date}
                                                >
                                                    {formatDate(entry.published_at ?? entry.date)}
                                                </time>
                                                <p className="text-foreground mt-1 font-mono text-lg font-semibold">v{entry.version}</p>
                                                <div className="mt-3 flex flex-wrap gap-1.5">
                                                    {isCurrentVersion && <Badge className="rounded-sm">Current</Badge>}
                                                    {entry.prerelease && (
                                                        <Badge variant="outline" className="rounded-sm">
                                                            Pre-release
                                                        </Badge>
                                                    )}
                                                    <Badge variant="outline" className={cn("rounded-sm", entryReleaseType.className)}>
                                                        {entryReleaseType.label}
                                                    </Badge>
                                                </div>
                                            </div>

                                            <div className="min-w-0 p-4 sm:p-5">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <h2 className="text-foreground text-base font-semibold sm:text-lg">{entry.title}</h2>
                                                        <p className="text-muted-foreground mt-1 text-xs">
                                                            {entry.source === "github_release" ? "GitHub release notes" : "Deployment metadata"}
                                                        </p>
                                                    </div>
                                                    {show_technical_links && entry.github_url && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            asChild
                                                            title="Open release on GitHub"
                                                            className="shrink-0"
                                                        >
                                                            <a
                                                                href={entry.github_url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                aria-label="Open release on GitHub"
                                                            >
                                                                <IconExternalLink className="size-4" />
                                                            </a>
                                                        </Button>
                                                    )}
                                                </div>

                                                {visibleChanges.length > 0 ? (
                                                    <ul className="divide-border mt-4 divide-y">
                                                        {visibleChanges.map((change, index) => {
                                                            const config = typeConfig[change.type] ?? typeConfig.improvement;
                                                            const ChangeIcon = config.icon;

                                                            return (
                                                                <li key={`${change.type}-${index}`} className="flex gap-3 py-3 first:pt-0 last:pb-0">
                                                                    <span
                                                                        className={cn(
                                                                            "mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md",
                                                                            config.iconClass,
                                                                        )}
                                                                    >
                                                                        <ChangeIcon className="size-4" />
                                                                    </span>
                                                                    <div className="min-w-0 flex-1">
                                                                        <p className="text-muted-foreground text-xs font-medium">{config.label}</p>
                                                                        <p className="text-foreground mt-0.5 text-sm leading-6">
                                                                            {change.description}
                                                                        </p>
                                                                    </div>
                                                                </li>
                                                            );
                                                        })}
                                                    </ul>
                                                ) : (
                                                    <div className="text-muted-foreground mt-4 flex items-center gap-2 text-sm">
                                                        <IconTool className="size-4" />
                                                        Detailed release notes are not available for this build.
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </section>
            </main>
        </>
    );

    if (layout === "admin") {
        return (
            <AdminLayout user={user} title="What's new">
                {content}
            </AdminLayout>
        );
    }

    return <PortalLayout user={{ name: user.name, email: user.email, avatar: user.avatar, role: user.role }}>{content}</PortalLayout>;
}
