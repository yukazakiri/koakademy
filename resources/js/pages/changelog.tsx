import PortalLayout from "@/components/portal-layout";
import {
    Timeline,
    TimelineContent,
    TimelineDate,
    TimelineHeader,
    TimelineIndicator,
    TimelineItem,
    TimelineSeparator,
    TimelineTitle,
} from "@/components/reui/timeline";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import { ChangelogEntry, VersionInfo } from "@/types/version";
import { Head } from "@inertiajs/react";
import {
    IconAlertCircle,
    IconBug,
    IconCalendar,
    IconCheck,
    IconClock,
    IconExternalLink,
    IconRocket,
    IconSearch,
    IconSparkles,
    IconTools,
} from "@tabler/icons-react";
import { useMemo, useState } from "react";

interface ChangelogProps {
    user: User;
    version: string;
    versionInfo?: VersionInfo;
    changelog: ChangelogEntry[];
}

type FilterType = "all" | "feature" | "fix" | "improvement" | "breaking" | "security";

const typeConfig = {
    feature: { label: "Feature", icon: IconRocket, color: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" },
    fix: { label: "Fix", icon: IconBug, color: "bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400" },
    improvement: { label: "Improvement", icon: IconSparkles, color: "bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400" },
    breaking: { label: "Breaking", icon: IconAlertCircle, color: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" },
    security: { label: "Security", icon: IconTools, color: "bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400" },
};

const versionTypeConfig = {
    major: { label: "Major", color: "bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400" },
    minor: { label: "Minor", color: "bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400" },
    patch: { label: "Patch", color: "bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-400" },
} as const;

export default function Changelog({ user, version, versionInfo, changelog }: ChangelogProps) {
    const [searchQuery, setSearchQuery] = useState("");
    const [activeFilter, setActiveFilter] = useState<FilterType>("all");

    const currentVersion = versionInfo?.version || version;

    const filteredChangelog = useMemo(() => {
        return changelog.filter((entry) => {
            const matchesSearch =
                searchQuery === "" ||
                entry.version.toLowerCase().includes(searchQuery.toLowerCase()) ||
                entry.changes.some((change) => change.description.toLowerCase().includes(searchQuery.toLowerCase()));

            if (activeFilter === "all") return matchesSearch;

            return matchesSearch && entry.changes.some((change) => change.type === activeFilter);
        });
    }, [changelog, searchQuery, activeFilter]);

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleDateString("en-US", {
            year: "numeric",
            month: "short",
            day: "numeric",
        });
    };

    const formatTimestamp = (timestamp: string | null) => {
        if (!timestamp) return null;
        return new Date(timestamp).toLocaleString("en-US", {
            year: "numeric",
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    };

    return (
        <PortalLayout
            user={{
                name: user.name,
                email: user.email,
                avatar: user.avatar,
                role: user.role,
            }}
        >
            <Head title="Changelog" />
            <main className="flex flex-1 flex-col gap-6 p-4 sm:px-6 sm:py-0">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-foreground text-2xl font-bold tracking-tight">Changelog</h1>
                        <p className="text-muted-foreground text-sm">Track the latest updates, improvements, and fixes.</p>
                    </div>

                    <Card className="w-full sm:w-72">
                        <CardHeader className="pb-2">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-sm font-medium">Current Version</CardTitle>
                                {versionInfo?.is_latest && (
                                    <Badge
                                        variant="outline"
                                        className="border-emerald-500 text-xs text-emerald-600 dark:border-emerald-400 dark:text-emerald-400"
                                    >
                                        <IconCheck className="mr-1 size-3" />
                                        Latest
                                    </Badge>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex items-baseline gap-2">
                                <span className="text-primary text-2xl font-bold">v{currentVersion}</span>
                                {versionInfo && (
                                    <Badge className={cn("text-xs", versionTypeConfig[versionInfo.release_type]?.color)}>
                                        {versionTypeConfig[versionInfo.release_type]?.label}
                                    </Badge>
                                )}
                            </div>
                            {versionInfo?.timestamp && (
                                <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                    <IconClock className="size-3.5" />
                                    {formatTimestamp(versionInfo.timestamp)}
                                </div>
                            )}
                            {versionInfo?.build_url && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-2 w-full"
                                    onClick={() => window.open(versionInfo.build_url!, "_blank")}
                                >
                                    <IconExternalLink className="mr-2 size-4" />
                                    View Build
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Tabs value={activeFilter} onValueChange={(v) => setActiveFilter(v as FilterType)}>
                        <TabsList>
                            <TabsTrigger value="all">All</TabsTrigger>
                            <TabsTrigger value="feature">Features</TabsTrigger>
                            <TabsTrigger value="fix">Fixes</TabsTrigger>
                            <TabsTrigger value="improvement">Improvements</TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <div className="relative w-full sm:w-64">
                        <IconSearch className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            placeholder="Search changes..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                </div>

                {/* Timeline */}
                {filteredChangelog.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                            <IconCalendar className="text-muted-foreground/50 mb-4 size-12" />
                            <p className="text-muted-foreground font-medium">No releases found</p>
                            <p className="text-muted-foreground/70 mt-1 text-sm">Try adjusting your search or filters</p>
                        </CardContent>
                    </Card>
                ) : (
                    <Timeline defaultValue={1} className="w-full">
                        {filteredChangelog.map((entry, index) => {
                            const isCurrentVersion = entry.version === currentVersion;

                            return (
                                <TimelineItem key={entry.version} step={index + 1}>
                                    <TimelineHeader>
                                        <TimelineSeparator />
                                        <TimelineIndicator className={isCurrentVersion ? "border-primary bg-primary" : ""} />
                                        <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                            <TimelineTitle className="flex items-center gap-2">
                                                <span className="font-mono text-lg">v{entry.version}</span>
                                                {isCurrentVersion && (
                                                    <Badge variant="default" className="text-xs">
                                                        Current
                                                    </Badge>
                                                )}
                                                <Badge className={cn("text-xs", versionTypeConfig[entry.type]?.color)}>
                                                    {versionTypeConfig[entry.type]?.label}
                                                </Badge>
                                            </TimelineTitle>
                                            <TimelineDate className="sm:text-right">{formatDate(entry.date)}</TimelineDate>
                                        </div>
                                    </TimelineHeader>
                                    <TimelineContent>
                                        <Card className={cn("mt-2", isCurrentVersion && "border-primary/50")}>
                                            <CardContent className="pt-4">
                                                <div className="space-y-2">
                                                    {entry.changes.length > 0 ? (
                                                        entry.changes
                                                            .filter((c) => activeFilter === "all" || c.type === activeFilter)
                                                            .map((change, changeIndex) => {
                                                                const config = typeConfig[change.type];
                                                                const Icon = config.icon;

                                                                return (
                                                                    <div key={changeIndex} className="flex items-start gap-3 rounded-lg border p-3">
                                                                        <span
                                                                            className={cn(
                                                                                "mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md",
                                                                                config.color,
                                                                            )}
                                                                        >
                                                                            <Icon className="size-3.5" />
                                                                        </span>
                                                                        <div className="flex-1">
                                                                            <span className={cn("text-xs font-medium", config.color)}>
                                                                                {config.label}
                                                                            </span>
                                                                            <p className="mt-0.5 text-sm">{change.description}</p>
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })
                                                    ) : (
                                                        <p className="text-muted-foreground py-2 text-sm italic">Release notes coming soon.</p>
                                                    )}
                                                </div>
                                                {entry.github_url && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="mt-3"
                                                        onClick={() => window.open(entry.github_url!, "_blank")}
                                                    >
                                                        <IconExternalLink className="mr-2 size-4" />
                                                        View on GitHub
                                                    </Button>
                                                )}
                                            </CardContent>
                                        </Card>
                                    </TimelineContent>
                                </TimelineItem>
                            );
                        })}
                    </Timeline>
                )}

                {/* Footer */}
                <div className="flex items-center justify-center border-t py-4">
                    <p className="text-muted-foreground text-sm">
                        View all releases on{" "}
                        <a
                            href="https://github.com/dccp-developers/DccpAdminV3/releases"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-primary font-medium hover:underline"
                        >
                            GitHub
                        </a>
                    </p>
                </div>
            </main>
        </PortalLayout>
    );
}
