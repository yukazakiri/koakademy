import { StudentSubmissionDialog } from "@/components/class/student-submission-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { ClassPostEntry } from "@/types/class-detail-types";
import { IconCheck, IconDownload, IconEye, IconFileUpload, IconLink, IconPaperclip } from "@tabler/icons-react";
import { useMemo, useState } from "react";

interface StudentStreamTabProps {
    classData: {
        id: number;
        subject_code: string;
        section: string;
    };
    teacher: {
        id: string | null;
        name: string;
        email?: string | null;
    };
    classPosts: ClassPostEntry[];
}

const classPostTypeLabels: Record<string, { label: string; badge: string; intent: "stream" | "classwork" | "both" }> = {
    announcement: { label: "Announcement", badge: "bg-indigo-500/15 text-indigo-600", intent: "stream" },
    quiz: { label: "Quiz", badge: "bg-rose-500/15 text-rose-600", intent: "both" },
    assignment: { label: "Assignment", badge: "bg-amber-500/15 text-amber-600", intent: "both" },
    activity: { label: "Activity", badge: "bg-emerald-500/15 text-emerald-600", intent: "both" },
};

export function StudentStreamTab({ classData, teacher, classPosts }: StudentStreamTabProps) {
    const [submittingPost, setSubmittingPost] = useState<ClassPostEntry | null>(null);
    const [submitDialogOpen, setSubmitDialogOpen] = useState(false);

    const dateFormatter = useMemo(
        () =>
            new Intl.DateTimeFormat(undefined, {
                dateStyle: "medium",
                timeStyle: "short",
            }),
        [],
    );

    const handleSubmitClick = (post: ClassPostEntry) => {
        setSubmittingPost(post);
        setSubmitDialogOpen(true);
    };

    const streamPosts = useMemo(() => {
        return classPosts
            .map((post) => ({
                ...post,
                meta: classPostTypeLabels[post.type] ?? {
                    label: post.type,
                    badge: "bg-muted text-muted-foreground",
                    intent: "stream",
                },
            }))
            .filter((post) => post.meta.intent === "stream" || post.meta.intent === "both");
    }, [classPosts]);

    return (
        <div className="space-y-6">
            <Card className="border-border/70 bg-card/90 shadow-sm">
                {streamPosts.length === 0 ? (
                    <Card className="border-border/70 bg-card/90 shadow-sm">
                        <CardContent className="text-muted-foreground p-6 text-center text-sm">No posts yet.</CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {streamPosts.map((post) => {
                            // Helper to check file types
                            const isImage = (name: string) => /\.(jpg|jpeg|png|gif|svg|webp)$/i.test(name);
                            const isVideo = (name: string) => /\.(mp4|webm|ogg)$/i.test(name);
                            const isYoutube = (url: string) => url.includes("youtube.com/watch") || url.includes("youtu.be");

                            // Group attachments
                            const mediaAttachments =
                                post.attachments?.filter(
                                    (a) => (a.kind === "file" && (isImage(a.name) || isVideo(a.name))) || (a.kind === "link" && isYoutube(a.url)),
                                ) ?? [];

                            const otherAttachments = post.attachments?.filter((a) => !mediaAttachments.includes(a)) ?? [];

                            return (
                                <div key={post.id} className="group hover:bg-muted/30 relative -mx-4 rounded-lg px-4 py-4 pl-4 transition-all">
                                    <div className="flex gap-4">
                                        {/* Avatar */}
                                        <div className="flex-none">
                                            <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-full">
                                                <span className="text-sm font-semibold">{classData.subject_code.slice(0, 1).toUpperCase()}</span>
                                            </div>
                                        </div>

                                        {/* Content */}
                                        <div className="flex-1 space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="text-foreground font-semibold">{teacher.name}</span>
                                                <span className="text-muted-foreground text-xs">
                                                    {post.created_at && dateFormatter.format(new Date(post.created_at))}
                                                </span>
                                                {post.meta.badge && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-muted-foreground ml-1 h-5 px-1.5 text-[10px] font-normal"
                                                    >
                                                        {post.meta.label}
                                                    </Badge>
                                                )}
                                                {post.status && (
                                                    <Badge variant="outline" className="text-muted-foreground h-5 px-1.5 text-[10px] font-normal">
                                                        {post.status === "backlog" && "Planned"}
                                                        {post.status === "in_progress" && "In progress"}
                                                        {post.status === "review" && "Needs review"}
                                                        {post.status === "blocked" && "Needs help"}
                                                        {post.status === "done" && "Completed"}
                                                    </Badge>
                                                )}
                                                {post.due_date && (
                                                    <span className="text-muted-foreground text-xs">
                                                        Due {new Date(post.due_date).toLocaleDateString()}
                                                    </span>
                                                )}
                                                {post.total_points ? (
                                                    <span className="text-muted-foreground text-xs">{post.total_points} pts</span>
                                                ) : null}
                                            </div>

                                            <div className="text-foreground/90 text-sm leading-relaxed whitespace-pre-wrap">
                                                {post.title}
                                                {post.content && <div className="text-muted-foreground mt-1">{post.content}</div>}
                                            </div>

                                            {/* Submit Button for Assignments */}
                                            {post.type === "assignment" && (
                                                <div className="mt-3">
                                                    {post.my_submission ? (
                                                        <div className="flex items-center gap-2">
                                                            <Badge
                                                                variant={post.my_submission.status === "graded" ? "default" : "secondary"}
                                                                className="h-6 text-xs"
                                                            >
                                                                <IconCheck className="mr-1 size-3" />
                                                                {post.my_submission.status === "graded"
                                                                    ? `Graded: ${post.my_submission.points ?? 0}/${post.total_points ?? 100}`
                                                                    : "Submitted"}
                                                            </Badge>
                                                            {post.my_submission.submitted_at && (
                                                                <span className="text-muted-foreground text-xs">
                                                                    {new Date(post.my_submission.submitted_at).toLocaleDateString()}
                                                                </span>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-8 text-xs"
                                                            onClick={() => handleSubmitClick(post)}
                                                        >
                                                            <IconFileUpload className="mr-1 size-3.5" />
                                                            Submit Assignment
                                                        </Button>
                                                    )}
                                                </div>
                                            )}

                                            {/* Media Attachments (Images, Videos, YouTube) */}
                                            {mediaAttachments.length > 0 && (
                                                <div
                                                    className={cn(
                                                        "mt-3 grid gap-2",
                                                        mediaAttachments.length > 1 ? "max-w-3xl grid-cols-2" : "max-w-xl",
                                                    )}
                                                >
                                                    {mediaAttachments.map((attachment, idx) => {
                                                        if (attachment.kind === "link" && isYoutube(attachment.url)) {
                                                            return (
                                                                <div
                                                                    key={idx}
                                                                    className={cn(
                                                                        "border-border/60 overflow-hidden rounded-xl border bg-black/5",
                                                                        mediaAttachments.length > 1 && "col-span-2",
                                                                    )}
                                                                >
                                                                    <div className="aspect-video w-full">
                                                                        <iframe
                                                                            width="100%"
                                                                            height="100%"
                                                                            src={`https://www.youtube.com/embed/${
                                                                                attachment.url.includes("v=")
                                                                                    ? attachment.url.split("v=")[1].split("&")[0]
                                                                                    : attachment.url.split("/").pop()
                                                                            }`}
                                                                            title="YouTube video player"
                                                                            frameBorder="0"
                                                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                                            allowFullScreen
                                                                        />
                                                                    </div>
                                                                    <div className="bg-card/50 p-2 backdrop-blur-sm">
                                                                        <a
                                                                            href={attachment.url}
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            className="flex items-center gap-2 text-xs font-medium text-blue-500 hover:underline"
                                                                        >
                                                                            <IconLink className="size-3.5" />
                                                                            {attachment.url}
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            );
                                                        }

                                                        if (attachment.kind === "file" && isVideo(attachment.name)) {
                                                            return (
                                                                <div
                                                                    key={idx}
                                                                    className={cn(
                                                                        "border-border/60 overflow-hidden rounded-xl border bg-black/5",
                                                                        mediaAttachments.length > 1 && "col-span-2",
                                                                    )}
                                                                >
                                                                    <video src={attachment.url} controls className="h-auto max-h-[400px] w-full" />
                                                                </div>
                                                            );
                                                        }

                                                        // Image
                                                        return (
                                                            <div
                                                                key={idx}
                                                                className="group/image border-border/60 bg-muted/30 relative overflow-hidden rounded-xl border"
                                                            >
                                                                <img
                                                                    src={attachment.url}
                                                                    alt={attachment.name}
                                                                    className="h-full max-h-[300px] w-full object-cover transition duration-300 group-hover/image:scale-105"
                                                                />
                                                                <div className="absolute inset-0 flex items-center justify-center bg-black/0 transition group-hover/image:bg-black/20">
                                                                    <div className="flex gap-2 opacity-0 transition group-hover/image:opacity-100">
                                                                        <a
                                                                            href={attachment.url}
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            className="flex items-center gap-2 rounded-full bg-black/60 px-3 py-1.5 text-xs font-medium text-white hover:bg-black/80"
                                                                        >
                                                                            <IconEye className="size-3.5" />
                                                                            View
                                                                        </a>
                                                                        <a
                                                                            href={attachment.url}
                                                                            download
                                                                            className="flex items-center gap-2 rounded-full bg-black/60 px-3 py-1.5 text-xs font-medium text-white hover:bg-black/80"
                                                                        >
                                                                            <IconDownload className="size-3.5" />
                                                                            Download
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            )}

                                            {/* Other Attachments (Files, Links) */}
                                            {otherAttachments.length > 0 && (
                                                <div className="mt-3 grid max-w-2xl grid-cols-1 gap-2 sm:grid-cols-2">
                                                    {otherAttachments.map((attachment, idx) => (
                                                        <div
                                                            key={idx}
                                                            className="border-border/60 bg-card/50 hover:bg-card hover:border-primary/30 group/attachment relative flex items-center gap-3 rounded-lg border p-3 transition"
                                                        >
                                                            <div className="bg-primary/10 text-primary group-hover/attachment:bg-primary/20 flex size-10 items-center justify-center rounded-md transition">
                                                                {attachment.kind === "file" ? (
                                                                    <IconPaperclip className="size-5" />
                                                                ) : (
                                                                    <IconLink className="size-5" />
                                                                )}
                                                            </div>
                                                            <div className="min-w-0 flex-1">
                                                                <a href={attachment.url} target="_blank" rel="noopener noreferrer" className="block">
                                                                    <p className="text-foreground truncate text-sm font-medium hover:underline">
                                                                        {attachment.name}
                                                                    </p>
                                                                    <p className="text-muted-foreground truncate text-xs">{attachment.url}</p>
                                                                </a>
                                                            </div>
                                                            {attachment.kind === "file" && (
                                                                <a
                                                                    href={attachment.url}
                                                                    download
                                                                    className="text-muted-foreground hover:text-foreground hover:bg-muted rounded-full p-2 transition"
                                                                    title="Download"
                                                                >
                                                                    <IconDownload className="size-4" />
                                                                </a>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </Card>

            <StudentSubmissionDialog
                open={submitDialogOpen}
                onOpenChange={setSubmitDialogOpen}
                classId={classData.id}
                post={submittingPost}
            />
        </div>
    );
}
