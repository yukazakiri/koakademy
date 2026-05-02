import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { ClassPostEntry, StudentEntry } from "@/types/class-detail-types";
import { IconEdit, IconEye, IconLink, IconPaperclip } from "@tabler/icons-react";
import { useMemo, useState } from "react";
import { AssignmentComposerDialog } from "@/components/class/assignment-composer-dialog";
import { SubmissionViewerSheet } from "@/components/class/submission-viewer-sheet";

interface ClassworkTabProps {
    classId?: number;
    classCode?: string;
    classSection?: string;
    currentFacultyId?: string | null;
    classPosts: ClassPostEntry[];
    students?: StudentEntry[];
    isStudentView?: boolean;
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

export function ClassworkTab({
    classId,
    classCode,
    classSection,
    currentFacultyId,
    classPosts,
    students = [],
    isStudentView = false,
}: ClassworkTabProps) {
    const [viewingPost, setViewingPost] = useState<ClassPostEntry | null>(null);
    const [viewingSubmissions, setViewingSubmissions] = useState(false);
    const [editingPost, setEditingPost] = useState<ClassPostEntry | null>(null);
    const [editOpen, setEditOpen] = useState(false);

    const dateFormatter = useMemo(
        () =>
            new Intl.DateTimeFormat(undefined, {
                dateStyle: "medium",
                timeStyle: "short",
            }),
        [],
    );

    const handleViewSubmissions = (post: ClassPostEntry) => {
        setViewingPost(post);
        setViewingSubmissions(true);
    };

    const handleEditPost = (post: ClassPostEntry) => {
        setEditingPost(post);
        setEditOpen(true);
    };

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
                                            {post.assignment?.instruction && (
                                                <p className="text-muted-foreground mt-1 line-clamp-2 text-xs">{post.assignment.instruction}</p>
                                            )}
                                            <div className="text-muted-foreground mt-2 flex flex-wrap items-center gap-2 text-xs">
                                                {post.status && (
                                                    <Badge variant="outline" className="h-5 px-2 text-[10px] font-normal">
                                                        {statusLabels[post.status] ?? post.status}
                                                    </Badge>
                                                )}
                                                {post.total_points ? <span>{post.total_points} pts</span> : null}
                                                {post.due_date ? <span>Due {new Date(post.due_date).toLocaleDateString()}</span> : null}
                                                {post.assignment && (
                                                    <>
                                                        <span>
                                                            {post.assignment.audience_mode === "all_students"
                                                                ? "All students"
                                                                : `${post.assignment.assigned_student_ids.length} students`}
                                                        </span>
                                                        <span>{post.assignment.rubric.length} criteria</span>
                                                    </>
                                                )}
                                            </div>
                                            {post.type === "assignment" && !isStudentView && (
                                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-7 text-xs"
                                                        onClick={() => handleViewSubmissions(post)}
                                                    >
                                                        <IconEye className="mr-1 size-3.5" />
                                                        Submissions
                                                        {post.submission_count !== undefined && post.submission_count > 0 && (
                                                            <Badge variant="secondary" className="ml-1 h-4 px-1.5 text-[10px]">
                                                                {post.submission_count}
                                                            </Badge>
                                                        )}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-muted-foreground hover:text-foreground h-7 text-xs"
                                                        onClick={() => handleEditPost(post)}
                                                    >
                                                        <IconEdit className="mr-1 size-3.5" />
                                                        Edit
                                                    </Button>
                                                </div>
                                            )}
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

            {!isStudentView && (
                <>
                    <SubmissionViewerSheet
                        open={viewingSubmissions}
                        onOpenChange={setViewingSubmissions}
                        classId={classId ?? 0}
                        post={viewingPost}
                    />

                    {editingPost && (
                        <AssignmentComposerDialog
                            classId={classId ?? 0}
                            classCode={classCode ?? ""}
                            classSection={classSection ?? ""}
                            currentFacultyId={currentFacultyId ?? null}
                            students={students}
                            mode="edit"
                            post={editingPost}
                            open={editOpen}
                            onOpenChange={setEditOpen}
                        />
                    )}
                </>
            )}
        </div>
    );
}
