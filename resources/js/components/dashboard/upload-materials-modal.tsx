import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { router } from "@inertiajs/react";
import { IconPaperclip, IconSparkles, IconTrash, IconUpload } from "@tabler/icons-react";
import { FormEvent, useMemo, useRef, useState } from "react";
import { toast } from "sonner";

interface ClassOption {
    id: number | string;
    subject_code: string;
    subject_title: string;
    section: string;
}

interface UploadMaterialsModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classes: ClassOption[];
}

const postTypeOptions = [
    { value: "announcement", label: "Announcement", badge: "bg-indigo-500/15 text-indigo-600" },
    { value: "assignment", label: "Material", badge: "bg-amber-500/15 text-amber-600" },
    { value: "activity", label: "Activity", badge: "bg-emerald-500/15 text-emerald-600" },
    { value: "quiz", label: "Quiz", badge: "bg-rose-500/15 text-rose-600" },
];

export function UploadMaterialsModal({ open, onOpenChange, classes }: UploadMaterialsModalProps) {
    const [selectedClassId, setSelectedClassId] = useState<string>("");
    const [postType, setPostType] = useState<string>("announcement");
    const [title, setTitle] = useState<string>("");
    const [content, setContent] = useState<string>("");
    const [files, setFiles] = useState<File[]>([]);
    const [filePreviews, setFilePreviews] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [titleTouched, setTitleTouched] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const selectedClass = classes.find((c) => c.id.toString() === selectedClassId);

    // Auto-generate title
    const autoTitle = useMemo(() => {
        const typeLabel = postTypeOptions.find((t) => t.value === postType)?.label ?? "Post";
        const dateLabel = new Intl.DateTimeFormat("en-US", { month: "short", day: "numeric" }).format(new Date());
        const snippet =
            content
                .split("\n")
                .map((l) => l.trim())
                .filter(Boolean)[0] || "";
        const snippetText = snippet ? ` — ${snippet.slice(0, 40)}${snippet.length > 40 ? "…" : ""}` : "";
        const classLabel = selectedClass ? `${selectedClass.subject_code} • Section ${selectedClass.section}` : "";

        return `${typeLabel} • ${classLabel} • ${dateLabel}${snippetText}`;
    }, [postType, content, selectedClass]);

    // Update title when not touched
    if (!titleTouched && title !== autoTitle && selectedClassId) {
        setTitle(autoTitle);
    }

    const handleFilesSelected = (event: React.ChangeEvent<HTMLInputElement>) => {
        if (!event.target.files) return;

        const selectedFiles = Array.from(event.target.files);
        const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

        const validFiles: File[] = [];
        const oversizedFiles: string[] = [];

        selectedFiles.forEach((file) => {
            if (file.size > MAX_FILE_SIZE) {
                oversizedFiles.push(`${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)`);
            } else {
                validFiles.push(file);

                if (file.type.startsWith("image/")) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const result = e.target?.result as string;
                        setFilePreviews((prev) => ({ ...prev, [file.name]: result }));
                    };
                    reader.readAsDataURL(file);
                }
            }
        });

        if (oversizedFiles.length > 0) {
            toast.error(`File(s) too large: ${oversizedFiles.join(", ")}. Max 50MB per file.`);
        }

        if (validFiles.length > 0) {
            setFiles((prev) => [...prev, ...validFiles]);
        }

        event.target.value = "";
    };

    const handleRemoveFile = (index: number) => {
        const fileToRemove = files[index];
        setFiles((prev) => prev.filter((_, i) => i !== index));

        if (fileToRemove && filePreviews[fileToRemove.name]) {
            setFilePreviews((prev) => {
                const newPreviews = { ...prev };
                delete newPreviews[fileToRemove.name];
                return newPreviews;
            });
        }
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!selectedClassId) {
            toast.error("Please select a class");
            return;
        }

        if (!title.trim()) {
            toast.error("Title is required");
            return;
        }

        setIsSubmitting(true);

        const formData = new FormData();
        formData.append("title", title);
        formData.append("content", content);
        formData.append("type", postType);

        files.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });

        router.post(`/faculty/classes/${selectedClassId}/posts`, formData, {
            forceFormData: true,
            onSuccess: () => {
                toast.success("Post created successfully!");
                handleClose();
            },
            onError: (errors) => {
                console.error("Post submission errors:", errors);
                toast.error("Failed to create post. Please try again.");
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const handleClose = () => {
        setSelectedClassId("");
        setPostType("announcement");
        setTitle("");
        setContent("");
        setFiles([]);
        setFilePreviews({});
        setTitleTouched(false);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        Upload Materials
                        {selectedClass && (
                            <Badge variant="secondary" className="ml-2">
                                {selectedClass.subject_code} - {selectedClass.section}
                            </Badge>
                        )}
                    </DialogTitle>
                    <DialogDescription>Create a post to share materials, announcements, or activities with your class.</DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6 py-4">
                    {/* Class Selector */}
                    <div className="space-y-2">
                        <Label>Select Class</Label>
                        <Select
                            value={selectedClassId}
                            onValueChange={(v) => {
                                setSelectedClassId(v);
                                setTitleTouched(false);
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Choose a class..." />
                            </SelectTrigger>
                            <SelectContent>
                                {classes.map((classItem) => (
                                    <SelectItem key={classItem.id} value={classItem.id.toString()}>
                                        {classItem.subject_code} - {classItem.section} ({classItem.subject_title})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Post Type Selector */}
                    <div className="space-y-2">
                        <Label>Post Type</Label>
                        <div className="flex flex-wrap gap-2">
                            {postTypeOptions.map((type) => (
                                <Button
                                    key={type.value}
                                    type="button"
                                    variant={postType === type.value ? "default" : "outline"}
                                    size="sm"
                                    className="rounded-full"
                                    onClick={() => {
                                        setPostType(type.value);
                                        setTitleTouched(false);
                                    }}
                                >
                                    {type.label}
                                </Button>
                            ))}
                        </div>
                    </div>

                    {/* Title */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label>Title</Label>
                            {!titleTouched && (
                                <Badge variant="secondary" className="text-xs">
                                    <IconSparkles className="mr-1 size-3" />
                                    Auto
                                </Badge>
                            )}
                        </div>
                        <Input
                            placeholder="Post title"
                            value={title}
                            onFocus={() => setTitleTouched(true)}
                            onChange={(e) => {
                                setTitleTouched(true);
                                setTitle(e.target.value);
                            }}
                        />
                    </div>

                    {/* Content */}
                    <div className="space-y-2">
                        <Label>Content</Label>
                        <Textarea
                            placeholder="Share updates, instructions, or materials..."
                            value={content}
                            onChange={(e) => setContent(e.target.value)}
                            rows={5}
                        />
                    </div>

                    {/* File Upload */}
                    <div className="space-y-2">
                        <Label>Attachments (optional)</Label>
                        <div className="rounded-lg border border-dashed p-4">
                            <input ref={fileInputRef} type="file" multiple className="hidden" onChange={handleFilesSelected} />
                            <Button type="button" variant="outline" size="sm" className="w-full" onClick={() => fileInputRef.current?.click()}>
                                <IconUpload className="mr-2 size-4" />
                                Choose Files
                            </Button>
                            <p className="text-muted-foreground mt-2 text-center text-xs">Max 50MB per file. Supports images, documents, videos.</p>

                            {files.length > 0 && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {files.map((file, index) => {
                                        const isImage = file.type.startsWith("image/");
                                        const preview = filePreviews[file.name];

                                        return (
                                            <div
                                                key={`${file.name}-${index}`}
                                                className="group bg-muted/40 flex items-center gap-2 rounded-full border px-2 py-1 text-xs"
                                            >
                                                {isImage && preview ? (
                                                    <div className="bg-muted h-6 w-6 overflow-hidden rounded-md border">
                                                        <img src={preview} alt={file.name} className="h-full w-full object-cover" />
                                                    </div>
                                                ) : (
                                                    <IconPaperclip className="text-muted-foreground size-3" />
                                                )}
                                                <span className="max-w-[120px] truncate">{file.name}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => handleRemoveFile(index)}
                                                    className="hover:bg-destructive hover:text-destructive-foreground rounded-full p-0.5 opacity-0 transition group-hover:opacity-100"
                                                >
                                                    <IconTrash className="size-3" />
                                                </button>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Submit Button */}
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={handleClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={!selectedClassId || isSubmitting}>
                            {isSubmitting ? "Posting..." : "Share with Class"}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
