import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { ClassPostEntry } from "@/types/class-detail-types";
import { IconLink, IconPaperclip } from "@tabler/icons-react";
import { useMemo } from "react";

interface ClassworkTabProps {
    classPosts: ClassPostEntry[];
}

const classPostTypeLabels: Record<string, { label: string; badge: string; intent: "stream" | "classwork" | "both" }> = {
    announcement: { label: "Announcement", badge: "bg-indigo-500/15 text-indigo-600", intent: "stream" },
    quiz: { label: "Quiz", badge: "bg-rose-500/15 text-rose-600", intent: "both" },
    assignment: { label: "Assignment", badge: "bg-amber-500/15 text-amber-600", intent: "both" },
    activity: { label: "Activity", badge: "bg-emerald-500/15 text-emerald-600", intent: "both" },
};

const statusLabels: Record<string, string> = {
    backlog: "Planned",
    in_progress: "In progress",
    review: "Needs review",
    blocked: "Needs help",
    done: "Completed",
};

export function ClassworkTab({ classPosts }: ClassworkTabProps) {
    const dateFormatter = useMemo(
        () =>
            new Intl.DateTimeFormat(undefined, {
                dateStyle: "medium",
                timeStyle: "short",
            }),
        [],
    );

    const classworkGroups = useMemo(() => {
        const groups: Record<string, ClassPostEntry[]> = {
            quiz: [],
            assignment: [],
            activity: [],
        };

        classPosts.forEach((post) => {
            if (post.type in groups) {
                groups[post.type].push(post);
            }
        });

        return groups;
    }, [classPosts]);

    return (
        <div className="space-y-4">
            {["quiz", "assignment", "activity"].map((type) => {
                const labelMeta = classPostTypeLabels[type];
                const posts = classworkGroups[type] ?? [];

                if (!posts.length) {
                    return null;
                }

                return (
                    <Card key={type} className="border-border/70 bg-card/90 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Badge className={cn("rounded-full px-2 py-0.5 text-[11px]", labelMeta.badge)}>{labelMeta.label}</Badge>
                                {labelMeta.label}
                            </CardTitle>
                            <CardDescription>Latest {labelMeta.label.toLowerCase()} posts</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {posts.map((post) => (
                                <div
                                    key={post.id}
                                    className="border-border/60 bg-background/60 hover:border-primary/30 rounded-xl border p-4 transition"
                                >
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p className="text-foreground text-sm font-semibold">{post.title}</p>
                                            {post.content && <p className="text-muted-foreground line-clamp-2 text-xs">{post.content}</p>}
                                            <div className="text-muted-foreground mt-2 flex flex-wrap items-center gap-2 text-xs">
                                                {post.status && (
                                                    <Badge variant="outline" className="h-5 px-2 text-[10px] font-normal">
                                                        {statusLabels[post.status] ?? post.status}
                                                    </Badge>
                                                )}
                                                {post.total_points ? <span>{post.total_points} pts</span> : null}
                                                {post.due_date ? <span>Due {new Date(post.due_date).toLocaleDateString()}</span> : null}
                                            </div>
                                        </div>
                                        {post.created_at && (
                                            <span className="text-muted-foreground text-xs">{dateFormatter.format(new Date(post.created_at))}</span>
                                        )}
                                    </div>
                                    {(post.attachments?.length ?? 0) > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {post.attachments.map((attachment, attachmentIndex) => (
                                                <a
                                                    key={`${post.id}-${attachmentIndex}`}
                                                    href={attachment.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="border-border/60 bg-background/70 text-primary hover:border-primary/30 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs transition"
                                                >
                                                    {attachment.kind === "file" ? (
                                                        <IconPaperclip className="size-3.5" />
                                                    ) : (
                                                        <IconLink className="size-3.5" />
                                                    )}
                                                    {attachment.name}
                                                </a>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                );
            })}

            {!["quiz", "assignment", "activity"].some((type) => (classworkGroups[type] ?? []).length) && (
                <Card className="border-border/70 bg-card/90 shadow-sm">
                    <CardContent className="text-muted-foreground p-8 text-center">No classwork posted yet.</CardContent>
                </Card>
            )}
        </div>
    );
}
