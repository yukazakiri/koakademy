import { FilePreview } from "@/components/class/file-preview";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { ClassPostEntry } from "@/types/class-detail-types";
import { router, useForm } from "@inertiajs/react";
import { IconCalendar, IconCheck, IconFileUpload, IconPaperclip, IconStar, IconX } from "@tabler/icons-react";
import { ChangeEvent, FormEvent, useRef, useState } from "react";
import { toast } from "sonner";

interface StudentSubmissionDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classId: number;
    post: ClassPostEntry | null;
}

const MAX_FILE_SIZE_MB = 50;
const MAX_FILE_SIZE = MAX_FILE_SIZE_MB * 1024 * 1024;

export function StudentSubmissionDialog({ open, onOpenChange, classId, post }: StudentSubmissionDialogProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [files, setFiles] = useState<File[]>([]);

    const form = useForm({
        content: "",
    });

    const handleFilesSelected = (event: ChangeEvent<HTMLInputElement>) => {
        if (!event.target.files) {
            return;
        }

        const incomingFiles = Array.from(event.target.files);
        const oversizedFiles = incomingFiles.filter((file) => file.size > MAX_FILE_SIZE);
        const validFiles = incomingFiles.filter((file) => file.size <= MAX_FILE_SIZE);

        if (oversizedFiles.length > 0) {
            const names = oversizedFiles.map((file) => file.name).join(", ");
            toast.error(`These files exceed ${MAX_FILE_SIZE_MB}MB: ${names}`);
        }

        if (validFiles.length > 0) {
            setFiles((prev) => [...prev, ...validFiles]);
        }

        event.target.value = "";
    };

    const handleRemoveFile = (index: number) => {
        setFiles((prev) => prev.filter((_, i) => i !== index));
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!post) {
            return;
        }

        const totalUploadSize = files.reduce((size, file) => size + file.size, 0);
        if (totalUploadSize > MAX_FILE_SIZE) {
            toast.error(`Total attachment size must be ${MAX_FILE_SIZE_MB}MB or less.`);
            return;
        }

        const formData = new FormData();
        formData.append("content", form.data.content);

        files.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });

        router.post(`/student/classes/${classId}/posts/${post.id}/submit`, formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Assignment submitted successfully.");
                form.reset();
                setFiles([]);
                onOpenChange(false);
            },
            onError: (errors) => {
                if (errors.error) {
                    toast.error(errors.error);
                } else {
                    toast.error("Failed to submit assignment. Please try again.");
                }
            },
        });
    };

    const isOverdue = post?.due_date ? new Date(post.due_date) < new Date() : false;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="text-lg font-semibold">Submit Assignment</DialogTitle>
                    <DialogDescription className="text-muted-foreground">{post?.title}</DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Assignment Info */}
                    <div className="bg-muted/50 rounded-lg border p-3">
                        <div className="flex flex-wrap items-center gap-2 text-xs">
                            {post?.total_points && (
                                <div className="flex items-center gap-1">
                                    <IconStar className="text-primary size-3.5" />
                                    <span className="font-medium">{post.total_points} points</span>
                                </div>
                            )}
                            {post?.due_date && (
                                <div className={`flex items-center gap-1 ${isOverdue ? "text-destructive" : ""}`}>
                                    <IconCalendar className="size-3.5" />
                                    <span>
                                        Due {new Date(post.due_date).toLocaleDateString()}
                                        {isOverdue && " (Overdue)"}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Content */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Your Response</label>
                        <Textarea
                            value={form.data.content}
                            onChange={(e) => form.setData("content", e.target.value)}
                            placeholder="Write your answer or comments here..."
                            rows={4}
                            className="resize-none"
                        />
                    </div>

                    {/* File Upload */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Attachments</label>

                        {files.length > 0 && (
                            <div className="space-y-2">
                                {files.map((file, index) => (
                                    <FilePreview
                                        key={`submit-${file.name}-${index}`}
                                        name={file.name}
                                        size={file.size}
                                        kind="file"
                                        file={file}
                                        onRemove={() => handleRemoveFile(index)}
                                    />
                                ))}
                            </div>
                        )}

                        <Button
                            type="button"
                            variant="outline"
                            className="w-full"
                            onClick={() => fileInputRef.current?.click()}
                        >
                            <IconPaperclip className="mr-2 size-4" />
                            {files.length > 0 ? "Add more files" : "Attach files"}
                        </Button>
                        <input ref={fileInputRef} type="file" multiple className="hidden" onChange={handleFilesSelected} />
                    </div>

                    <Separator />

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <IconFileUpload className="mr-2 size-4" />
                            {form.processing ? "Submitting..." : "Submit Assignment"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
