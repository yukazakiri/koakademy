import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useFeatureFlags } from "@/hooks/use-feature-flags";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import { Camera, Copy, ImageUp, Loader2, Trash2, User as UserIcon } from "lucide-react";
import React, { useCallback, useRef, useState } from "react";
import { toast } from "sonner";
import type { StudentDetail } from "../types";
import { StudentInlineEdit } from "./student-inline-edit";
import { StudentSignaturePad } from "./student-signature-pad";
import { TextEntry } from "./text-entry";

interface StudentDetailsCardProps {
    student: StudentDetail;
}

export function StudentDetailsCard({ student }: StudentDetailsCardProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const [isDragOver, setIsDragOver] = useState(false);
    const [confirmRemovePhoto, setConfirmRemovePhoto] = useState(false);
    const flags = useFeatureFlags();

    const signatureEnabled = flags.studentSignaturePad === true;
    const avatarUploadEnabled = flags.studentAvatarUpload === true;

    const picture1x1 = student.documents?.picture_1x1 ?? null;
    const profilePhotoUrl =
        typeof picture1x1 === "string" && picture1x1.length > 0
            ? picture1x1.startsWith("http://") || picture1x1.startsWith("https://") || picture1x1.startsWith("/")
                ? picture1x1
                : `/storage/${picture1x1}`
            : null;

    const uploadFile = useCallback(
        (file: File) => {
            if (!file.type.startsWith("image/")) {
                toast.error("Please select a valid image file.");
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                toast.error("Image must be smaller than 5MB.");
                return;
            }

            setProcessing(true);

            const optimisticUrl = URL.createObjectURL(file);

            router
                .optimistic((props) => {
                    const documents = { ...(props.student as StudentDetail).documents };
                    documents.picture_1x1 = optimisticUrl;

                    return {
                        ...props,
                        student: {
                            ...(props.student as StudentDetail),
                            documents,
                        },
                    };
                })
                .post(
                    route("administrators.students.documents.fixed.update", student.id),
                    {
                        document_type: "picture_1x1",
                        file: file,
                    },
                    {
                        forceFormData: true,
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            toast.success("Profile photo updated successfully!");
                        },
                        onError: (err) => {
                            toast.error(err.file || "Failed to upload photo. Please try again.");
                        },
                        onFinish: () => {
                            setProcessing(false);
                            URL.revokeObjectURL(optimisticUrl);
                            if (fileInputRef.current) {
                                fileInputRef.current.value = "";
                            }
                        },
                    },
                );
        },
        [student.id],
    );

    const removePhoto = useCallback(() => {
        setProcessing(true);

        router
            .optimistic((props) => {
                const documents = { ...(props.student as StudentDetail).documents };
                documents.picture_1x1 = null;

                return {
                    ...props,
                    student: {
                        ...(props.student as StudentDetail),
                        documents,
                    },
                };
            })
            .delete(route("administrators.students.documents.fixed.destroy", [student.id, "picture_1x1"]), {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setConfirmRemovePhoto(false);
                    toast.success("Profile photo removed successfully!");
                },
                onError: () => {
                    toast.error("Failed to remove profile photo. Please try again.");
                },
                onFinish: () => {
                    setProcessing(false);
                },
            });
    }, [student.id]);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        uploadFile(file);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(false);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(false);

        const file = e.dataTransfer.files[0];
        if (!file) return;
        uploadFile(file);
    };

    const handleCopyName = () => {
        navigator.clipboard.writeText(student.name).then(() => {
            toast.success("Student name copied to clipboard!");
        });
    };

    const hasRightColumn = avatarUploadEnabled || signatureEnabled;

    return (
        <Card className="overflow-hidden">
            <CardHeader className="flex flex-row items-center justify-between border-b pb-3">
                <div>
                    <CardTitle className="text-base">Student Details</CardTitle>
                    <p className="text-muted-foreground mt-0.5 text-xs">Personal information and identification</p>
                </div>
                <Button variant="ghost" size="sm" onClick={handleCopyName} className="h-8 gap-2 text-xs">
                    <Copy className="h-3.5 w-3.5" />
                    Copy Name
                </Button>
            </CardHeader>
            <CardContent className="p-6">
                <div className={cn("grid gap-6", hasRightColumn ? "grid-cols-1 md:grid-cols-3" : "grid-cols-1 sm:grid-cols-2")}>
                    <div className={cn("grid grid-cols-1 gap-5 sm:grid-cols-2", hasRightColumn && "md:col-span-2")}>
                        <TextEntry label="Full Name" value={student.name} copyable />
                        <TextEntry label="Email" value={student.email} />
                        <TextEntry label="Phone" value={student.contacts?.personal_contact} />
                        <TextEntry label="Birth Date" value={student.birth_date} />

                        <StudentInlineEdit student={student} />
                    </div>

                    {hasRightColumn && (
                        <div className="flex w-full flex-col items-center gap-6 md:items-end">
                            {avatarUploadEnabled && (
                                <div className="flex flex-col items-center">
                                    <div
                                        onDragOver={avatarUploadEnabled ? handleDragOver : undefined}
                                        onDragLeave={avatarUploadEnabled ? handleDragLeave : undefined}
                                        onDrop={avatarUploadEnabled ? handleDrop : undefined}
                                        className={cn(
                                            "group relative h-28 w-28 overflow-hidden rounded-full transition-all",
                                            processing
                                                ? "ring-primary/30 pointer-events-none ring-2 ring-offset-2"
                                                : isDragOver
                                                  ? "ring-primary ring-2 ring-offset-2"
                                                  : "ring-muted-foreground/15 hover:ring-primary/40 ring-2 ring-offset-2",
                                        )}
                                    >
                                        <input
                                            type="file"
                                            ref={fileInputRef}
                                            className="hidden"
                                            accept="image/*"
                                            onChange={handleFileChange}
                                            disabled={processing}
                                        />

                                        {processing ? (
                                            <div className="bg-muted flex h-full w-full flex-col items-center justify-center gap-1">
                                                <Loader2 className="text-primary h-6 w-6 animate-spin" />
                                                <span className="text-primary text-[9px] font-medium">Updating</span>
                                            </div>
                                        ) : profilePhotoUrl ? (
                                            <img
                                                src={profilePhotoUrl}
                                                alt={student.name}
                                                className="h-full w-full object-cover transition-all group-hover:scale-105 group-hover:brightness-75"
                                            />
                                        ) : (
                                            <div className="bg-muted text-muted-foreground group-hover:text-primary flex h-full w-full items-center justify-center transition-colors">
                                                <UserIcon className="h-10 w-10" />
                                            </div>
                                        )}

                                        {!processing && (
                                            <div
                                                onClick={() => fileInputRef.current?.click()}
                                                className="absolute inset-0 flex flex-col items-center justify-center gap-0.5 bg-black/50 opacity-0 transition-opacity group-hover:opacity-100"
                                            >
                                                {isDragOver ? (
                                                    <>
                                                        <ImageUp className="h-6 w-6 text-white" />
                                                        <span className="text-[9px] font-medium text-white">Drop here</span>
                                                    </>
                                                ) : (
                                                    <>
                                                        <Camera className="h-5 w-5 text-white" />
                                                        <span className="text-[9px] font-medium text-white">Change</span>
                                                    </>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    {profilePhotoUrl && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive mt-1.5 h-6 gap-1 px-2 text-[11px]"
                                            onClick={() => setConfirmRemovePhoto(true)}
                                            disabled={processing}
                                        >
                                            <Trash2 className="h-3 w-3" />
                                            Remove photo
                                        </Button>
                                    )}

                                    <Dialog open={confirmRemovePhoto} onOpenChange={setConfirmRemovePhoto}>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>Remove Profile Photo</DialogTitle>
                                                <DialogDescription>
                                                    Are you sure you want to remove the profile photo for {student.name}? The image file will be permanently deleted.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter>
                                                <Button type="button" variant="outline" onClick={() => setConfirmRemovePhoto(false)}>
                                                    Cancel
                                                </Button>
                                                <Button type="button" variant="destructive" onClick={removePhoto} disabled={processing}>
                                                    Remove Photo
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            )}

                            {signatureEnabled && (
                                <div className="w-full max-w-[220px]">
                                    <div className="text-muted-foreground mb-1.5 flex items-center gap-1.5 text-[10px] font-medium tracking-wider uppercase">
                                        Signature
                                    </div>
                                    <StudentSignaturePad studentId={student.id} signatureUrl={student.signature_url} />
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
