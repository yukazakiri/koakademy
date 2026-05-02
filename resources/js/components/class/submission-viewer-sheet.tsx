import { FilePreview } from "@/components/class/file-preview";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { ClassPostEntry, ClassPostSubmissionEntry } from "@/types/class-detail-types";
import { router } from "@inertiajs/react";
import { IconCheck, IconClipboardList, IconClock, IconFileUpload, IconSchool, IconX } from "@tabler/icons-react";
import axios from "axios";
import { useEffect, useState } from "react";
import { toast } from "sonner";

interface SubmissionViewerSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classId: number;
    post: ClassPostEntry | null;
}

export function SubmissionViewerSheet({ open, onOpenChange, classId, post }: SubmissionViewerSheetProps) {
    const [submissions, setSubmissions] = useState<ClassPostSubmissionEntry[]>([]);
    const [loading, setLoading] = useState(false);
    const [gradingId, setGradingId] = useState<number | null>(null);
    const [gradePoints, setGradePoints] = useState<string>("");

    useEffect(() => {
        if (!open || !post) {
            return;
        }

        setLoading(true);
        axios
            .get(`/faculty/classes/${classId}/posts/${post.id}/submissions`)
            .then((response) => {
                setSubmissions(response.data.submissions ?? []);
            })
            .catch(() => {
                toast.error("Failed to load submissions");
            })
            .finally(() => {
                setLoading(false);
            });
    }, [open, post, classId]);

    const handleStartGrading = (submission: ClassPostSubmissionEntry) => {
        setGradingId(submission.id);
        setGradePoints(submission.points?.toString() ?? "");
    };

    const handleSubmitGrade = (submissionId: number) => {
        const points = Number(gradePoints);
        if (Number.isNaN(points) || points < 0) {
            toast.error("Enter a valid point value");
            return;
        }

        const maxPoints = post?.total_points ?? 100;
        if (points > maxPoints) {
            toast.error(`Points cannot exceed ${maxPoints}`);
            return;
        }

        router.post(
            `/faculty/classes/${classId}/posts/${post?.id}/submissions/${submissionId}/grade`,
            { points },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Grade saved");
                    setGradingId(null);
                    setSubmissions((prev) =>
                        prev.map((s) => (s.id === submissionId ? { ...s, points, status: "graded" } : s)),
                    );
                },
                onError: () => {
                    toast.error("Failed to save grade");
                },
            },
        );
    };

    const handleCancelGrading = () => {
        setGradingId(null);
        setGradePoints("");
    };

    const submittedCount = submissions.length;
    const gradedCount = submissions.filter((s) => s.status === "graded").length;
    const pendingCount = submittedCount - gradedCount;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:w-[640px]">
                {/* Header */}
                <SheetHeader className="space-y-3 border-b px-6 pt-6 pb-4">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0 flex-1">
                            <SheetTitle className="text-lg font-semibold">Assignment Submissions</SheetTitle>
                            <p className="text-muted-foreground mt-1 truncate text-sm">{post?.title}</p>
                        </div>
                        {post?.total_points && (
                            <Badge variant="secondary" className="shrink-0">
                                {post.total_points} pts
                            </Badge>
                        )}
                    </div>

                    {/* Stats */}
                    <div className="grid grid-cols-3 gap-2 pt-2">
                        <div className="bg-muted/50 flex items-center gap-2 rounded-lg border px-3 py-2">
                            <IconFileUpload className="text-primary size-4 shrink-0" />
                            <div>
                                <p className="text-lg font-semibold leading-none">{submittedCount}</p>
                                <p className="text-muted-foreground text-xs">Submitted</p>
                            </div>
                        </div>
                        <div className="bg-muted/50 flex items-center gap-2 rounded-lg border px-3 py-2">
                            <IconSchool className="text-emerald-600 size-4 shrink-0" />
                            <div>
                                <p className="text-lg font-semibold leading-none">{gradedCount}</p>
                                <p className="text-muted-foreground text-xs">Graded</p>
                            </div>
                        </div>
                        <div className="bg-muted/50 flex items-center gap-2 rounded-lg border px-3 py-2">
                            <IconClock className="text-amber-600 size-4 shrink-0" />
                            <div>
                                <p className="text-lg font-semibold leading-none">{pendingCount}</p>
                                <p className="text-muted-foreground text-xs">Pending</p>
                            </div>
                        </div>
                    </div>
                </SheetHeader>

                {/* Submissions List */}
                <ScrollArea className="flex-1">
                    <div className="space-y-3 p-6">
                        {loading ? (
                            <div className="flex flex-col items-center justify-center gap-3 py-16">
                                <div className="border-primary h-8 w-8 animate-spin rounded-full border-b-2" />
                                <p className="text-muted-foreground text-sm">Loading submissions...</p>
                            </div>
                        ) : submissions.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                                <div className="bg-muted flex size-16 items-center justify-center rounded-full">
                                    <IconClipboardList className="text-muted-foreground size-8" />
                                </div>
                                <div>
                                    <p className="text-lg font-medium">No submissions yet</p>
                                    <p className="text-muted-foreground mt-1 max-w-xs text-sm">
                                        Students haven't submitted anything for this assignment. Check back later.
                                    </p>
                                </div>
                            </div>
                        ) : (
                            submissions.map((submission) => (
                                <div
                                    key={submission.id}
                                    className="bg-card rounded-xl border p-4 shadow-sm transition-shadow hover:shadow-md"
                                >
                                    {/* Student Header */}
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-full font-semibold">
                                                {submission.student_name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="text-sm font-semibold">{submission.student_name}</p>
                                                    {submission.status === "graded" && (
                                                        <Badge variant="default" className="h-5 text-[10px]">
                                                            Graded
                                                        </Badge>
                                                    )}
                                                    {submission.status === "submitted" && (
                                                        <Badge variant="secondary" className="h-5 text-[10px]">
                                                            Submitted
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-muted-foreground text-xs">{submission.student_id}</p>
                                            </div>
                                        </div>

                                        {submission.points !== null &&
                                            submission.points !== undefined &&
                                            gradingId !== submission.id && (
                                                <Badge variant="outline" className="shrink-0 text-sm font-semibold">
                                                    {submission.points} / {post?.total_points ?? 100}
                                                </Badge>
                                            )}
                                    </div>

                                    {/* Submission Date */}
                                    {submission.submitted_at && (
                                        <div className="text-muted-foreground mt-2 flex items-center gap-1 text-xs">
                                            <IconClock className="size-3" />
                                            Submitted {new Date(submission.submitted_at).toLocaleString()}
                                        </div>
                                    )}

                                    {/* Content */}
                                    {submission.content && (
                                        <div className="bg-muted/40 mt-3 rounded-lg px-3 py-2">
                                            <p className="text-sm whitespace-pre-wrap">{submission.content}</p>
                                        </div>
                                    )}

                                    {/* Attachments */}
                                    {submission.attachments.length > 0 && (
                                        <div className="mt-3 space-y-2">
                                            {submission.attachments.map((attachment, index) => (
                                                <FilePreview
                                                    key={`submission-${submission.id}-${index}`}
                                                    name={attachment.name}
                                                    url={attachment.url}
                                                    kind={attachment.kind}
                                                />
                                            ))}
                                        </div>
                                    )}

                                    <Separator className="my-3" />

                                    {/* Grading Section */}
                                    {gradingId === submission.id ? (
                                        <div className="flex items-center gap-2">
                                            <div className="flex items-center gap-2">
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    max={post?.total_points ?? 100}
                                                    value={gradePoints}
                                                    onChange={(e) => setGradePoints(e.target.value)}
                                                    placeholder="Points"
                                                    className="w-24"
                                                />
                                                <span className="text-muted-foreground text-sm">
                                                    / {post?.total_points ?? 100}
                                                </span>
                                            </div>
                                            <Button type="button" size="sm" onClick={() => handleSubmitGrade(submission.id)}>
                                                <IconCheck className="mr-1 size-4" />
                                                Save
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={handleCancelGrading}
                                            >
                                                <IconX className="mr-1 size-4" />
                                                Cancel
                                            </Button>
                                        </div>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant={submission.status === "graded" ? "outline" : "default"}
                                            size="sm"
                                            onClick={() => handleStartGrading(submission)}
                                        >
                                            <IconSchool className="mr-1 size-4" />
                                            {submission.status === "graded" ? "Update Grade" : "Grade Submission"}
                                        </Button>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                </ScrollArea>
            </SheetContent>
        </Sheet>
    );
}
