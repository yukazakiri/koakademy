import AdminLayout from "@/components/administrators/admin-layout";
import PortalLayout from "@/components/portal-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
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
    IconHistory,
    IconRocket,
    IconShieldCheck,
    IconSparkles,
    IconTool,
} from "@tabler/icons-react";
import { motion, useReducedMotion } from "motion/react";

interface ChangelogProps {
    user: User;
    layout?: "admin" | "portal";
    version: string;
    versionInfo?: VersionInfo;
    changelog: ChangelogEntry[];
    changelog_status?: "live" | "stale" | "empty" | "unavailable";
    changelog_last_synced_at?: string | null;
    github_repo?: string | null;
}

type ChangeType = ChangelogEntry["changes"][number]["type"];

const changeOrder: ChangeType[] = ["feature", "fix", "improvement", "security", "breaking"];

const typeConfig = {
    feature: {
        label: "New features",
        icon: IconRocket,
        iconClass: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    },
    fix: {
        label: "Fixes",
        icon: IconBug,
        iconClass: "bg-rose-500/10 text-rose-700 dark:text-rose-300",
    },
    improvement: {
        label: "Improvements",
        icon: IconSparkles,
        iconClass: "bg-sky-500/10 text-sky-700 dark:text-sky-300",
    },
    breaking: {
        label: "Breaking changes",
        icon: IconAlertTriangle,
        iconClass: "bg-amber-500/10 text-amber-700 dark:text-amber-300",
    },
    security: {
        label: "Security",
        icon: IconShieldCheck,
        iconClass: "bg-violet-500/10 text-violet-700 dark:text-violet-300",
    },
} satisfies Record<ChangeType, { label: string; icon: typeof IconRocket; iconClass: string }>;

const releaseTypeConfig: Record<string, { label: string; className: string }> = {
    major: { label: "Major release", className: "border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300" },
    minor: { label: "Minor release", className: "border-sky-500/30 bg-sky-500/10 text-sky-800 dark:text-sky-300" },
    patch: { label: "Patch", className: "border-border bg-muted/70 text-muted-foreground" },
    feature: { label: "Feature release", className: "border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300" },
    bugfix: { label: "Bug fix", className: "border-rose-500/30 bg-rose-500/10 text-rose-800 dark:text-rose-300" },
    chore: { label: "Maintenance", className: "border-border bg-muted/70 text-muted-foreground" },
    stable: { label: "Stable release", className: "border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300" },
    edge: { label: "Edge build", className: "border-violet-500/30 bg-violet-500/10 text-violet-800 dark:text-violet-300" },
};

function formatDate(dateString?: string | null): string | null {
    if (!dateString) return null;

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

function groupedChanges(changes: ChangelogEntry["changes"]) {
    return changeOrder
        .map((type) => ({ type, changes: changes.filter((change) => change.type === type) }))
        .filter((group) => group.changes.length > 0);
}

export default function Changelog({
    user,
    layout = "portal",
    version,
    versionInfo,
    changelog,
    changelog_status = "unavailable",
    changelog_last_synced_at,
    github_repo,
}: ChangelogProps) {
    const reducedMotion = useReducedMotion();
    const currentVersion = versionInfo?.version || version;
    const releaseType = versionInfo?.release_type ?? "patch";
    const releaseTypeDetails = releaseTypeConfig[releaseType] ?? releaseTypeConfig.patch;
    const deployedAt = formatTimestamp(versionInfo?.timestamp);
    const developmentBuild = currentVersion.includes("-");
    const githubReleasesUrl = github_repo ? `https://github.com/${github_repo}/releases` : null;
    const lastSyncedAt = formatTimestamp(changelog_last_synced_at);

    const status = {
        live: {
            icon: IconCheck,
            title: "Synced from GitHub",
            description: lastSyncedAt ? `Release notes last synced ${lastSyncedAt}.` : "Release notes are synced from GitHub.",
            className: "text-emerald-700 dark:text-emerald-300",
        },
        stale: {
            icon: IconHistory,
            title: "Showing last synced release notes",
            description: lastSyncedAt
                ? `GitHub is temporarily unavailable. These notes were last synced ${lastSyncedAt}.`
                : "GitHub is temporarily unavailable. Showing the most recently synced notes.",
            className: "text-amber-700 dark:text-amber-300",
        },
        empty: {
            icon: IconCalendar,
            title: "No releases published yet",
            description: "GitHub is connected, but there are no release notes to show yet.",
            className: "text-muted-foreground",
        },
        unavailable: {
            icon: IconAlertTriangle,
            title: "Release notes are temporarily unavailable",
            description: "The current deployment is still shown above. Please try again shortly or view the release history on GitHub.",
            className: "text-amber-700 dark:text-amber-300",
        },
    }[changelog_status];
    const StatusIcon = status.icon;

    const content = (
        <>
            <Head title="What's new" />
            <main className={cn("flex flex-1 flex-col", layout === "portal" && "px-4 pb-12 sm:px-6 lg:px-8")}>
                <header className="border-border border-b py-8 sm:py-12">
                    <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                        <div className="max-w-2xl space-y-3">
                            <div className="text-primary flex items-center gap-2 text-sm font-semibold tracking-wide">
                                <IconHistory className="size-4" aria-hidden="true" />
                                Product updates
                            </div>
                            <h1 className="text-foreground text-4xl font-semibold tracking-tight sm:text-5xl">What&apos;s new</h1>
                            <p className="text-muted-foreground max-w-xl text-base leading-7">
                                A clear, chronological record of what has changed in KoAkademy.
                            </p>
                        </div>

                        {githubReleasesUrl && (
                            <Button variant="outline" size="sm" asChild className="active:scale-[0.98] motion-reduce:transform-none">
                                <a href={githubReleasesUrl} target="_blank" rel="noopener noreferrer">
                                    <IconCode className="size-4" aria-hidden="true" />
                                    View on GitHub
                                    <IconExternalLink className="size-3.5" aria-hidden="true" />
                                </a>
                            </Button>
                        )}
                    </div>
                </header>

                <section className="mx-auto w-full max-w-4xl py-7 sm:py-10" aria-labelledby="current-deployment-heading">
                    <div className="changelog-material border-border bg-card/80 flex flex-col gap-5 rounded-2xl border p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div className="flex min-w-0 items-start gap-3.5">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                                <IconCheck className="size-5" aria-hidden="true" />
                            </span>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 id="current-deployment-heading" className="text-foreground font-semibold">
                                        Current deployment
                                    </h2>
                                    <Badge variant="outline" className={cn("rounded-full", releaseTypeDetails.className)}>
                                        {releaseTypeDetails.label}
                                    </Badge>
                                    <Badge variant="outline" className="rounded-full">
                                        {developmentBuild ? "Development channel" : "Stable channel"}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground mt-1.5 text-sm leading-6">
                                    <span className="text-foreground font-mono font-semibold">v{currentVersion}</span>
                                    {deployedAt && <span> · Deployed {deployedAt}</span>}
                                </p>
                            </div>
                        </div>

                        <div className={cn("flex max-w-sm items-start gap-2 text-sm leading-6", status.className)}>
                            <StatusIcon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <div>
                                <p className="font-medium">{status.title}</p>
                                <p className="text-muted-foreground mt-0.5 text-xs leading-5">{status.description}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="mx-auto w-full max-w-4xl pb-8 sm:pb-12" aria-labelledby="release-history-heading">
                    <div className="mb-7 flex items-end justify-between gap-4">
                        <div>
                            <p className="text-primary text-sm font-semibold tracking-wide">Release history</p>
                            <h2 id="release-history-heading" className="text-foreground mt-1 text-2xl font-semibold tracking-tight">
                                Every update, in order
                            </h2>
                        </div>
                        {changelog.length > 0 && <p className="text-muted-foreground text-sm">{changelog.length} releases</p>}
                    </div>

                    {changelog.length === 0 ? (
                        <div className="border-border bg-muted/30 flex min-h-56 flex-col items-center justify-center rounded-2xl border border-dashed px-6 py-12 text-center">
                            <StatusIcon className={cn("mb-4 size-8", status.className)} aria-hidden="true" />
                            <h3 className="text-foreground text-base font-semibold">{status.title}</h3>
                            <p className="text-muted-foreground mt-2 max-w-md text-sm leading-6">{status.description}</p>
                            {githubReleasesUrl && (
                                <Button variant="outline" size="sm" asChild className="mt-5 active:scale-[0.98] motion-reduce:transform-none">
                                    <a href={githubReleasesUrl} target="_blank" rel="noopener noreferrer">
                                        View release history
                                        <IconExternalLink className="size-3.5" aria-hidden="true" />
                                    </a>
                                </Button>
                            )}
                        </div>
                    ) : (
                        <ol className="border-border/70 relative ml-3 space-y-8 border-l pl-7 sm:ml-5 sm:pl-10">
                            {changelog.map((entry, index) => {
                                const isCurrentRelease = entry.version === currentVersion;
                                const entryReleaseType = releaseTypeConfig[entry.type] ?? releaseTypeConfig.patch;
                                const changes = groupedChanges(entry.changes);

                                return (
                                    <li key={`${entry.version}-${entry.published_at ?? index}`} className="relative">
                                        <span
                                            className={cn(
                                                "border-background bg-muted absolute top-6 -left-[2.1rem] flex size-4 rounded-full border-4 sm:-left-[2.85rem]",
                                                isCurrentRelease && "bg-primary",
                                            )}
                                            aria-hidden="true"
                                        />
                                        <motion.article
                                            initial={reducedMotion ? false : { opacity: 0, y: 8 }}
                                            animate={reducedMotion ? { opacity: 1 } : { opacity: 1, y: 0 }}
                                            transition={{ type: "spring", bounce: 0, duration: 0.35, delay: Math.min(index * 0.02, 0.18) }}
                                            className={cn(
                                                "border-border bg-card/80 rounded-2xl border p-5 shadow-sm sm:p-6",
                                                isCurrentRelease && "border-primary/45 ring-primary/10 ring-2",
                                            )}
                                        >
                                            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="min-w-0">
                                                    <time
                                                        className="text-muted-foreground text-sm font-medium"
                                                        dateTime={entry.published_at ?? undefined}
                                                    >
                                                        {formatDate(entry.published_at ?? entry.date)}
                                                    </time>
                                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                                        <h3 className="text-foreground font-mono text-xl font-semibold tracking-tight">
                                                            v{entry.version}
                                                        </h3>
                                                        {isCurrentRelease && <Badge className="rounded-full">Current</Badge>}
                                                        {entry.prerelease && (
                                                            <Badge variant="outline" className="rounded-full">
                                                                Pre-release
                                                            </Badge>
                                                        )}
                                                        <Badge variant="outline" className={cn("rounded-full", entryReleaseType.className)}>
                                                            {entryReleaseType.label}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-foreground mt-3 text-base font-semibold sm:text-lg">{entry.title}</p>
                                                </div>

                                                {entry.github_url && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                        className="-ml-2 w-fit active:scale-[0.98] motion-reduce:transform-none sm:-mr-2 sm:ml-0"
                                                    >
                                                        <a href={entry.github_url} target="_blank" rel="noopener noreferrer">
                                                            View release
                                                            <IconExternalLink className="size-3.5" aria-hidden="true" />
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>

                                            {changes.length > 0 ? (
                                                <div className="mt-6 space-y-5">
                                                    {changes.map(({ type, changes: typedChanges }) => {
                                                        const config = typeConfig[type];
                                                        const ChangeIcon = config.icon;

                                                        return (
                                                            <section key={type} aria-label={config.label}>
                                                                <div className="flex items-center gap-2">
                                                                    <span
                                                                        className={cn(
                                                                            "flex size-7 items-center justify-center rounded-full",
                                                                            config.iconClass,
                                                                        )}
                                                                    >
                                                                        <ChangeIcon className="size-3.5" aria-hidden="true" />
                                                                    </span>
                                                                    <h4 className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                                                                        {config.label}
                                                                    </h4>
                                                                </div>
                                                                <ul className="mt-3 space-y-2.5 pl-1">
                                                                    {typedChanges.map((change, changeIndex) => (
                                                                        <li
                                                                            key={`${change.type}-${changeIndex}`}
                                                                            className="text-foreground flex gap-3 text-sm leading-6"
                                                                        >
                                                                            <span
                                                                                className="bg-border mt-2.5 size-1.5 shrink-0 rounded-full"
                                                                                aria-hidden="true"
                                                                            />
                                                                            <span>{change.description}</span>
                                                                        </li>
                                                                    ))}
                                                                </ul>
                                                            </section>
                                                        );
                                                    })}
                                                </div>
                                            ) : (
                                                <div className="text-muted-foreground mt-5 flex items-center gap-2 text-sm">
                                                    <IconTool className="size-4" aria-hidden="true" />
                                                    Detailed release notes are not available for this release.
                                                </div>
                                            )}
                                        </motion.article>
                                    </li>
                                );
                            })}
                        </ol>
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
