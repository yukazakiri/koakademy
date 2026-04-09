import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Slider } from "@/components/ui/slider";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { ClassPostAttachment, ClassPostEntry, LinkPreview } from "@/types/class-detail-types";
import { router, useForm } from "@inertiajs/react";
import { IconDownload, IconEye, IconLink, IconPaperclip, IconPlus, IconSparkles, IconTrash, IconUpload } from "@tabler/icons-react";
import { DragEvent, FormEvent, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";

interface StreamTabProps {
    classData: {
        id: number;
        subject_code: string;
        section: string;
    };
    currentFaculty: {
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

const postTypeOptions = ["announcement", "quiz", "assignment", "activity"] as const;

export function StreamTab({ classData, currentFaculty, classPosts }: StreamTabProps) {
    const [editingPostId, setEditingPostId] = useState<number | null>(null);
    const [deletingPostId, setDeletingPostId] = useState<number | null>(null);
    const [isLinkDialogOpen, setIsLinkDialogOpen] = useState(false);
    const [linkUrl, setLinkUrl] = useState("");
    const [linkPreview, setLinkPreview] = useState<LinkPreview | null>(null);
    const [isDraggingFiles, setIsDraggingFiles] = useState(false);
    const [filePreviews, setFilePreviews] = useState<{ [key: string]: string }>({});
    const [titleTouched, setTitleTouched] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const dateFormatter = useMemo(
        () =>
            new Intl.DateTimeFormat(undefined, {
                dateStyle: "medium",
                timeStyle: "short",
            }),
        [],
    );

    const todayLabel = new Date().toISOString().slice(0, 10);

    const postForm = useForm<{
        title: string;
        content: string;
        type: string;
        status: "backlog" | "in_progress" | "review" | "done" | "blocked";
        priority: "low" | "medium" | "high";
        start_date: string;
        due_date: string;
        progress_percent: number;
        total_points: number;
        assigned_faculty_id: string | null;
        attachments: ClassPostAttachment[];
        files: File[];
    }>({
        title: "",
        content: "",
        type: "announcement",
        status: "backlog",
        priority: "medium",
        start_date: todayLabel,
        due_date: todayLabel,
        progress_percent: 0,
        total_points: 100,
        assigned_faculty_id: currentFaculty.id,
        attachments: [],
        files: [],
    });

    // Auto-generate title
    const autoTitle = useMemo(() => {
        const label = classPostTypeLabels[postForm.data.type as keyof typeof classPostTypeLabels]?.label ?? "Post";
        const dateLabel = new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric" }).format(new Date());
        const snippet = (postForm.data.content || "")
            .split("\n")
            .map((line) => line.trim())
            .filter(Boolean)[0];
        const snippetText = snippet ? ` — ${snippet.slice(0, 60)}${snippet.length > 60 ? "…" : ""}` : "";
        const sectionLabel = classData.section ? ` • Section ${classData.section}` : "";

        return `${label} • ${classData.subject_code}${sectionLabel} • ${dateLabel}${snippetText}`;
    }, [postForm.data.content, postForm.data.type, classData.section, classData.subject_code]);

    useEffect(() => {
        if (titleTouched) {
            return;
        }

        if (postForm.data.title !== autoTitle) {
            postForm.setData("title", autoTitle);
        }
    }, [autoTitle, postForm, postForm.data.title, titleTouched]);

    const handleEditPost = (post: ClassPostEntry) => {
        setEditingPostId(post.id);
        setTitleTouched(true);
        setLinkUrl("");
        postForm.setData((data) => ({
            ...data,
            title: post.title || "",
            content: post.content || "",
            type: post.type || "announcement",
            status: (post.status as typeof data.status) || "backlog",
            priority: (post.priority as typeof data.priority) || "medium",
            start_date: post.start_date || todayLabel,
            due_date: post.due_date || todayLabel,
            progress_percent: post.progress_percent ?? 0,
            total_points: post.total_points ?? 100,
            assigned_faculty_id: post.assigned_faculty_id ?? currentFaculty.id,
            attachments: post.attachments || [],
            files: [],
        }));
    };

    const handleCancelEdit = () => {
        setEditingPostId(null);
        setTitleTouched(false);
        postForm.reset();
        setLinkUrl("");
    };

    const handleDeletePost = (postId: number) => {
        setDeletingPostId(postId);
        router.delete(`/faculty/classes/${classData.id}/posts/${postId}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Post deleted");
            },
            onError: () => {
                toast.error("Unable to delete post");
            },
            onFinish: () => setDeletingPostId(null),
        });
    };

    const handleRemoveAttachment = (index: number) => {
        postForm.setData(
            "attachments",
            postForm.data.attachments.filter((_, attachmentIndex) => attachmentIndex !== index),
        );
    };

    const MAX_FILE_SIZE_MB = 50;
    const MAX_FILE_SIZE = MAX_FILE_SIZE_MB * 1024 * 1024;

    const handleFilesSelected = (event: React.ChangeEvent<HTMLInputElement>) => {
        if (!event.target.files) return;

        const files = Array.from(event.target.files);
        const validFiles: File[] = [];
        const oversizedFiles: string[] = [];

        files.forEach((file) => {
            if (file.size > MAX_FILE_SIZE) {
                oversizedFiles.push(`${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)`);
            } else {
                validFiles.push(file);

                // Generate preview for image files
                if (file.type.startsWith("image/")) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const result = e.target?.result as string;
                        setFilePreviews((prev) => ({
                            ...prev,
                            [file.name]: result,
                        }));
                    };
                    reader.readAsDataURL(file);
                }
            }
        });

        if (oversizedFiles.length > 0) {
            toast.error(`File(s) too large: ${oversizedFiles.join(", ")}. Max size is ${MAX_FILE_SIZE_MB}MB per file.`, { duration: 5000 });
        }

        if (validFiles.length > 0) {
            postForm.setData("files", [...postForm.data.files, ...validFiles]);
        }

        event.target.value = "";
    };

    const handleRemoveFile = (index: number) => {
        const fileToRemove = postForm.data.files[index];
        postForm.setData(
            "files",
            postForm.data.files.filter((_, fileIndex) => fileIndex !== index),
        );

        // Remove preview if it exists
        if (fileToRemove && filePreviews[fileToRemove.name]) {
            setFilePreviews((prev) => {
                const newPreviews = { ...prev };
                delete newPreviews[fileToRemove.name];
                return newPreviews;
            });
        }
    };

    const handleSubmitPost = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!postForm.data.title.trim()) {
            toast.error("Title is required");
            return;
        }

        if (postForm.data.type !== "announcement" && postForm.data.total_points <= 0) {
            toast.error("Total points must be greater than 0");
            return;
        }

        // Calculate total size
        const totalSize = postForm.data.files.reduce((sum, file) => sum + file.size, 0);
        const totalSizeMB = totalSize / (1024 * 1024);

        if (totalSizeMB > MAX_FILE_SIZE_MB) {
            toast.error(`Total file size (${totalSizeMB.toFixed(2)} MB) exceeds ${MAX_FILE_SIZE_MB}MB limit. Please reduce file sizes.`, {
                duration: 6000,
            });
            return;
        }

        const isEditing = editingPostId !== null;
        const url = isEditing ? `/faculty/classes/${classData.id}/posts/${editingPostId}` : `/faculty/classes/${classData.id}/posts`;

        // Build FormData manually to handle file uploads with proper method spoofing
        const formData = new FormData();
        formData.append("title", postForm.data.title);
        formData.append("content", postForm.data.content);
        formData.append("type", postForm.data.type);
        formData.append("status", postForm.data.status);
        formData.append("priority", postForm.data.priority);
        formData.append("progress_percent", String(postForm.data.progress_percent));
        if (postForm.data.start_date) {
            formData.append("start_date", postForm.data.start_date);
        }
        if (postForm.data.due_date) {
            formData.append("due_date", postForm.data.due_date);
        }
        if (postForm.data.type !== "announcement") {
            formData.append("total_points", String(postForm.data.total_points));
        }
        formData.append("assigned_faculty_id", postForm.data.assigned_faculty_id ?? "");

        // Add attachments as array items in FormData format
        postForm.data.attachments.forEach((attachment, index) => {
            formData.append(`attachments[${index}][name]`, attachment.name);
            formData.append(`attachments[${index}][url]`, attachment.url);
            formData.append(`attachments[${index}][kind]`, attachment.kind);
        });

        // Add files to FormData
        postForm.data.files.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });

        // Add _method field for PUT request spoofing when editing
        if (isEditing) {
            formData.append("_method", "PUT");
        }

        router.post(url, formData, {
            forceFormData: true,
            onSuccess: () => {
                toast.success(isEditing ? "Post updated" : "Post published successfully!");
                postForm.reset();
                setFilePreviews({});
                setTitleTouched(false);
                setEditingPostId(null);
            },
            onError: (errors) => {
                console.error("Post submission errors:", errors);
                const errorMessage = errors?.message || errors?.error || "Unable to publish post. Please try again.";
                toast.error(errorMessage, {
                    duration: 6000,
                });
            },
        });
    };

    const handleDragOver = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = "copy";
        setIsDraggingFiles(true);
    };

    const handleDragLeave = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDraggingFiles(false);
    };

    const handleDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDraggingFiles(false);
        if (!event.dataTransfer.files?.length) return;

        const files = Array.from(event.dataTransfer.files);
        const validFiles: File[] = [];
        const oversizedFiles: string[] = [];

        files.forEach((file) => {
            if (file.size > MAX_FILE_SIZE) {
                oversizedFiles.push(`${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)`);
            } else {
                validFiles.push(file);

                // Generate preview for image files
                if (file.type.startsWith("image/")) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const result = e.target?.result as string;
                        setFilePreviews((prev) => ({
                            ...prev,
                            [file.name]: result,
                        }));
                    };
                    reader.readAsDataURL(file);
                }
            }
        });

        if (oversizedFiles.length > 0) {
            toast.error(`File(s) too large: ${oversizedFiles.join(", ")}. Max size is ${MAX_FILE_SIZE_MB}MB per file.`, { duration: 5000 });
        }

        if (validFiles.length > 0) {
            postForm.setData("files", [...postForm.data.files, ...validFiles]);
        }
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
                <CardContent className="space-y-5 p-6">
                    <div className="flex items-center gap-3">
                        <div className="border-border/50 text-primary flex size-12 items-center justify-center rounded-full border">
                            {classData.subject_code.slice(0, 2).toUpperCase()}
                        </div>
                        <div>
                            <p className="text-muted-foreground text-xs tracking-[0.3em] uppercase">New post</p>
                            <p className="text-muted-foreground text-sm">Announcements stay in Stream; Quiz/Material/Activity also go to Classwork</p>
                        </div>
                    </div>
                    {editingPostId !== null && (
                        <div className="flex items-center justify-between rounded-lg border border-amber-200/60 bg-amber-50/70 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                            <span className="font-medium">Editing post #{editingPostId}</span>
                            <Button size="sm" variant="ghost" onClick={handleCancelEdit}>
                                Cancel edit
                            </Button>
                        </div>
                    )}
                    <form className="space-y-4" onSubmit={handleSubmitPost}>
                        <div className="border-border/70 bg-card/70 space-y-4 rounded-2xl border p-4">
                            <div className="flex flex-wrap items-center gap-2">
                                {postTypeOptions.map((type) => {
                                    const labelMeta = classPostTypeLabels[type];
                                    const isActive = postForm.data.type === type;

                                    return (
                                        <Button
                                            key={type}
                                            type="button"
                                            variant={isActive ? "default" : "outline"}
                                            size="sm"
                                            className={cn("rounded-full", !isActive && "bg-background/80")}
                                            onClick={() => {
                                                postForm.setData("type", type);
                                                setTitleTouched(false);

                                                if (type === "announcement") {
                                                    postForm.setData((data) => ({
                                                        ...data,
                                                        status: "backlog",
                                                        priority: "medium",
                                                        start_date: todayLabel,
                                                        due_date: todayLabel,
                                                        progress_percent: 0,
                                                        total_points: 0,
                                                        assigned_faculty_id: currentFaculty.id,
                                                    }));
                                                }
                                            }}
                                        >
                                            {labelMeta.label}
                                        </Button>
                                    );
                                })}
                                <Badge variant="outline" className="ml-auto text-[11px] tracking-[0.2em] uppercase">
                                    Stream
                                </Badge>
                            </div>

                            <div className="border-border/70 bg-background/80 space-y-3 rounded-xl border p-3">
                                <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-2 text-[12px]">
                                    <span className="flex items-center gap-1">
                                        <IconSparkles className="text-primary size-4" />
                                        Title auto-fills from type, date, and first sentence. Edit anytime.
                                    </span>
                                    <Badge variant="secondary" className="rounded-full px-2 py-0.5 text-[11px]">
                                        Auto
                                    </Badge>
                                </div>
                                <Input
                                    placeholder="Auto-generated post title"
                                    value={postForm.data.title}
                                    onFocus={() => setTitleTouched(true)}
                                    onChange={(event) => {
                                        setTitleTouched(true);
                                        postForm.setData("title", event.target.value);
                                    }}
                                    className="border-none bg-transparent px-0 shadow-none focus-visible:ring-0"
                                />

                                {(postForm.data.type === "quiz" || postForm.data.type === "assignment" || postForm.data.type === "activity") && (
                                    <div className="border-border/70 bg-background/70 space-y-4 rounded-xl border p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p className="text-foreground text-sm font-semibold">Activity planning</p>
                                                <p className="text-muted-foreground text-xs">
                                                    Set a stage, priority, and dates to help beginners stay organized.
                                                </p>
                                            </div>
                                            <Badge variant="secondary" className="rounded-full px-2 py-0.5 text-[11px]">
                                                Step 2
                                            </Badge>
                                        </div>

                                        <div className="grid gap-3 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="post-status">Stage</Label>
                                                <Select
                                                    value={postForm.data.status}
                                                    onValueChange={(value) => {
                                                        const status = value as typeof postForm.data.status;
                                                        postForm.setData("status", status);
                                                        if (status === "done") {
                                                            postForm.setData("progress_percent", 100);
                                                        }
                                                    }}
                                                >
                                                    <SelectTrigger id="post-status" className="w-full">
                                                        <SelectValue placeholder="Choose a stage" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="backlog">Planned</SelectItem>
                                                        <SelectItem value="in_progress">In progress</SelectItem>
                                                        <SelectItem value="review">Needs review</SelectItem>
                                                        <SelectItem value="blocked">Needs help</SelectItem>
                                                        <SelectItem value="done">Completed</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="post-priority">Priority</Label>
                                                <Select
                                                    value={postForm.data.priority}
                                                    onValueChange={(value) => {
                                                        postForm.setData("priority", value as typeof postForm.data.priority);
                                                    }}
                                                >
                                                    <SelectTrigger id="post-priority" className="w-full">
                                                        <SelectValue placeholder="Choose priority" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="low">Low</SelectItem>
                                                        <SelectItem value="medium">Medium</SelectItem>
                                                        <SelectItem value="high">High</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="post-start">Start date</Label>
                                                <Input
                                                    id="post-start"
                                                    type="date"
                                                    value={postForm.data.start_date}
                                                    onChange={(event) => postForm.setData("start_date", event.target.value)}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="post-due">Due date</Label>
                                                <Input
                                                    id="post-due"
                                                    type="date"
                                                    value={postForm.data.due_date}
                                                    onChange={(event) => postForm.setData("due_date", event.target.value)}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="post-points">Total points</Label>
                                                <Input
                                                    id="post-points"
                                                    type="number"
                                                    min={0}
                                                    required
                                                    value={postForm.data.total_points}
                                                    onChange={(event) => {
                                                        const value = Number(event.target.value);
                                                        postForm.setData("total_points", Number.isNaN(value) ? 0 : value);
                                                    }}
                                                />
                                                <p className="text-muted-foreground text-xs">Set the maximum score for this task.</p>
                                            </div>

                                            <div className="space-y-2 md:col-span-2">
                                                <div className="flex items-center justify-between">
                                                    <Label htmlFor="post-progress">Progress</Label>
                                                    <span className="text-muted-foreground text-xs">{postForm.data.progress_percent}% complete</span>
                                                </div>
                                                <Slider
                                                    id="post-progress"
                                                    value={[postForm.data.progress_percent]}
                                                    onValueChange={(value) => {
                                                        postForm.setData("progress_percent", value[0] ?? 0);
                                                    }}
                                                />
                                            </div>

                                            <div className="space-y-2 md:col-span-2">
                                                <Label htmlFor="post-assignee">Assigned to</Label>
                                                <Select
                                                    value={postForm.data.assigned_faculty_id ? "self" : "unassigned"}
                                                    onValueChange={(value) => {
                                                        const nextValue = value === "self" ? currentFaculty.id : null;
                                                        postForm.setData("assigned_faculty_id", nextValue);
                                                    }}
                                                >
                                                    <SelectTrigger id="post-assignee" className="w-full">
                                                        <SelectValue placeholder="Assign this activity" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="self">You ({currentFaculty.name})</SelectItem>
                                                        <SelectItem value="unassigned">Unassigned</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <p className="text-muted-foreground text-xs">
                                                    Assigning to yourself keeps responsibilities clear for beginners.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div
                                    className={cn(
                                        "bg-background/70 rounded-lg border border-dashed p-3 transition-all",
                                        isDraggingFiles ? "border-primary/70 bg-primary/5" : "border-border/70 hover:border-primary/40",
                                    )}
                                    onDragOver={handleDragOver}
                                    onDragLeave={handleDragLeave}
                                    onDrop={handleDrop}
                                >
                                    <Textarea
                                        value={postForm.data.content}
                                        onChange={(event) => postForm.setData("content", event.target.value)}
                                        placeholder="Share updates, instructions, or next steps..."
                                        rows={5}
                                        className="min-h-[120px] resize-none border-none bg-transparent px-0 shadow-none focus-visible:ring-0"
                                    />

                                    {(postForm.data.attachments.length > 0 || postForm.data.files.length > 0) && (
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {postForm.data.attachments.map((attachment, attachmentIndex) => (
                                                <div
                                                    key={`${attachment.url}-${attachmentIndex}`}
                                                    className="group border-border/60 bg-muted/40 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs"
                                                >
                                                    <IconLink className="text-muted-foreground size-3.5" />
                                                    <span className="max-w-[160px] truncate">{attachment.name}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleRemoveAttachment(attachmentIndex)}
                                                        className="text-muted-foreground hover:text-foreground opacity-0 transition group-hover:opacity-100"
                                                    >
                                                        <IconTrash className="size-3.5" />
                                                    </button>
                                                </div>
                                            ))}
                                            {postForm.data.files.map((file, fileIndex) => {
                                                const isImage = file.type.startsWith("image/");
                                                const preview = filePreviews[file.name];

                                                return (
                                                    <div
                                                        key={`${file.name}-${fileIndex}`}
                                                        className="group border-border/60 bg-muted/40 flex items-center gap-2 rounded-full border px-2 py-1 text-xs"
                                                    >
                                                        {isImage && preview ? (
                                                            <div className="border-border/60 bg-muted h-8 w-8 overflow-hidden rounded-md border">
                                                                <img src={preview} alt={file.name} className="h-full w-full object-cover" />
                                                            </div>
                                                        ) : (
                                                            <IconPaperclip className="text-muted-foreground size-4" />
                                                        )}
                                                        <div className="min-w-0">
                                                            <p className="truncate font-medium">{file.name}</p>
                                                            <p className="text-muted-foreground text-[10px]">
                                                                {(file.size / (1024 * 1024)).toFixed(2)} MB
                                                            </p>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleRemoveFile(fileIndex)}
                                                            className="hover:bg-destructive hover:text-destructive-foreground rounded-full p-1 opacity-0 transition group-hover:opacity-100"
                                                        >
                                                            <IconTrash className="size-3.5" />
                                                        </button>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}

                                    <div className="mt-3 grid gap-2 sm:grid-cols-[1.2fr_auto_auto] sm:items-center">
                                        <div className="relative">
                                            <IconLink className="text-muted-foreground pointer-events-none absolute top-1/2 left-2 size-4 -translate-y-1/2" />
                                            <Input
                                                placeholder="Paste a link to attach"
                                                value={linkUrl}
                                                onChange={(e) => {
                                                    const value = e.target.value;
                                                    setLinkUrl(value);

                                                    if (value.match(/^https?:\/\//)) {
                                                        try {
                                                            const url = new URL(value);
                                                            setLinkPreview({
                                                                host: url.hostname.replace(/^www\./, ""),
                                                                href: url.href,
                                                            });
                                                        } catch {
                                                            setLinkPreview(null);
                                                        }
                                                    } else {
                                                        setLinkPreview(null);
                                                    }
                                                }}
                                                className="bg-muted/40 w-full border-none pr-24 pl-8 shadow-none focus-visible:ring-0"
                                            />
                                            <div className="absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1">
                                                {linkPreview && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-8 px-2"
                                                        onClick={() => {
                                                            postForm.setData("attachments", [
                                                                ...postForm.data.attachments,
                                                                {
                                                                    name: linkPreview.host,
                                                                    url: linkPreview.href,
                                                                    kind: "link" as const,
                                                                },
                                                            ]);
                                                            setLinkUrl("");
                                                            setLinkPreview(null);
                                                        }}
                                                    >
                                                        <IconPlus className="size-4" />
                                                    </Button>
                                                )}
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-8 px-2"
                                                    onClick={() => setIsLinkDialogOpen(true)}
                                                >
                                                    More
                                                </Button>
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="justify-center"
                                            onClick={() => fileInputRef.current?.click()}
                                        >
                                            <IconUpload className="mr-2 size-4" />
                                            Upload
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" onClick={() => setIsLinkDialogOpen(true)}>
                                            <IconLink className="mr-2 size-4" />
                                            Add Link
                                        </Button>
                                    </div>

                                    <p className="text-muted-foreground mt-2 text-[11px]">
                                        Max {MAX_FILE_SIZE_MB}MB per file and total. Drag files anywhere in this box to attach.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Link Dialog */}
                        <Dialog open={isLinkDialogOpen} onOpenChange={setIsLinkDialogOpen}>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Add Link</DialogTitle>
                                    <DialogDescription>Enter a URL to attach to your post</DialogDescription>
                                </DialogHeader>
                                <div className="space-y-4 py-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="link-url">URL</Label>
                                        <Input
                                            id="link-url"
                                            placeholder="https://example.com"
                                            value={linkUrl}
                                            onChange={(e) => {
                                                const value = e.target.value;
                                                setLinkUrl(value);

                                                if (value.match(/^https?:\/\//)) {
                                                    try {
                                                        const url = new URL(value);
                                                        setLinkPreview({
                                                            host: url.hostname.replace(/^www\./, ""),
                                                            href: url.href,
                                                        });
                                                    } catch {
                                                        setLinkPreview(null);
                                                    }
                                                } else {
                                                    setLinkPreview(null);
                                                }
                                            }}
                                        />
                                    </div>
                                    {linkPreview && (
                                        <div className="border-border/60 bg-background/80 rounded-lg border p-3">
                                            <div className="flex items-center gap-3">
                                                <div className="border-border/60 bg-muted/50 text-muted-foreground flex size-10 items-center justify-center rounded-md border">
                                                    <IconLink className="size-5" />
                                                </div>
                                                <div className="flex-1 space-y-0.5">
                                                    <p className="text-foreground line-clamp-1 text-sm font-semibold">{linkPreview.host}</p>
                                                    <p className="text-muted-foreground line-clamp-1 text-xs">{linkPreview.href}</p>
                                                </div>
                                                <Button
                                                    size="sm"
                                                    onClick={() => {
                                                        postForm.setData("attachments", [
                                                            ...postForm.data.attachments,
                                                            {
                                                                name: linkPreview.host,
                                                                url: linkPreview.href,
                                                                kind: "link" as const,
                                                            },
                                                        ]);
                                                        setLinkUrl("");
                                                        setLinkPreview(null);
                                                    }}
                                                >
                                                    Add
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button variant="outline">Cancel</Button>
                                    </DialogClose>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>

                        {/* Live URL Preview (Embed) */}
                        {linkPreview && !postForm.data.attachments.some((a) => a.url === linkPreview.href) && (
                            <div className="border-border/60 bg-card animate-in fade-in-50 slide-in-from-top-2 rounded-lg border p-4">
                                <div className="flex items-start gap-3">
                                    <div className="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-md">
                                        <IconLink className="size-5" />
                                    </div>
                                    <div className="flex-1 space-y-1">
                                        <p className="text-foreground text-sm font-semibold">{linkPreview.host}</p>
                                        <p className="text-muted-foreground line-clamp-2 text-xs">{linkPreview.href}</p>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-auto p-1 text-xs"
                                            onClick={() => {
                                                postForm.setData("attachments", [
                                                    ...postForm.data.attachments,
                                                    {
                                                        name: linkPreview.host,
                                                        url: linkPreview.href,
                                                        kind: "link" as const,
                                                    },
                                                ]);
                                                setLinkUrl("");
                                                setLinkPreview(null);
                                            }}
                                        >
                                            Add to attachments
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}

                        <input ref={fileInputRef} type="file" multiple className="hidden" onChange={handleFilesSelected} />

                        <div className="flex justify-end">
                            {editingPostId !== null && (
                                <Button type="button" variant="outline" size="sm" className="rounded-full" onClick={handleCancelEdit}>
                                    Cancel
                                </Button>
                            )}
                            <Button type="submit" size="sm" className="rounded-full" disabled={postForm.processing}>
                                {postForm.processing
                                    ? editingPostId !== null
                                        ? "Updating..."
                                        : "Posting..."
                                    : editingPostId !== null
                                      ? "Update post"
                                      : "Share with class"}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card className="border-border/70 bg-card/90 shadow-sm">
                {streamPosts.length === 0 ? (
                    <Card className="border-border/70 bg-card/90 shadow-sm">
                        <CardContent className="text-muted-foreground p-6 text-center text-sm">
                            No posts yet. Share an announcement or activity to get things started.
                        </CardContent>
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
                                    {/* Discord-style Hover Actions */}
                                    <div className="bg-background border-border/60 absolute top-2 right-4 hidden items-center gap-1 rounded-lg border p-1 shadow-sm group-hover:flex">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="text-muted-foreground hover:text-foreground h-7 w-7 rounded-md"
                                            onClick={() => handleEditPost(post)}
                                        >
                                            <IconSparkles className="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="text-destructive/70 hover:text-destructive hover:bg-destructive/10 h-7 w-7 rounded-md"
                                            disabled={deletingPostId === post.id}
                                            onClick={() => handleDeletePost(post.id)}
                                        >
                                            <IconTrash className="size-4" />
                                        </Button>
                                    </div>

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
                                                <span className="text-foreground font-semibold">Teacher</span>
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
        </div>
    );
}
