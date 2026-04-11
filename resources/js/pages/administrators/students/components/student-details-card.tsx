import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { router } from "@inertiajs/react";
import { useState } from "react";
import { Camera, Copy, Loader2, User as UserIcon } from "lucide-react";
import React, { useRef } from "react";
import { toast } from "sonner";
import type { StudentDetail } from "../types";
import { TextEntry } from "./text-entry";
import { Button } from "@/components/ui/button";
import { StudentSignaturePad } from "./student-signature-pad";

interface StudentDetailsCardProps {
    student: StudentDetail;
}

export function StudentDetailsCard({ student }: StudentDetailsCardProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const picture1x1 = student.documents?.picture_1x1 ?? null;
    const profilePhotoUrl =
        typeof picture1x1 === "string" && picture1x1.length > 0
            ? picture1x1.startsWith("http://") || picture1x1.startsWith("https://") || picture1x1.startsWith("/")
                ? picture1x1
                : `/storage/${picture1x1}`
            : null;

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        // Ensure file is an image and under 5MB
        if (!file.type.startsWith("image/")) {
            toast.error("Please select a valid image file.");
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            toast.error("Image must be smaller than 5MB.");
            return;
        }

        setProcessing(true);

        router.post(
            route("administrators.students.documents.fixed.update", student.id),
            {
                document_type: "picture_1x1",
                file: file,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Profile photo updated successfully!");
                },
                onError: (err) => {
                    toast.error(err.file || "Failed to upload photo. Please try again.");
                },
                onFinish: () => {
                    setProcessing(false);
                    if (fileInputRef.current) {
                        fileInputRef.current.value = "";
                    }
                },
            }
        );
    };

    const handleCopyName = () => {
        navigator.clipboard.writeText(student.name).then(() => {
            toast.success("Student name copied to clipboard!");
        });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle>Student Details</CardTitle>
                <Button variant="ghost" size="sm" onClick={handleCopyName} className="h-8 gap-2 text-xs">
                    <Copy className="h-3.5 w-3.5" />
                    Copy Name
                </Button>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:col-span-2">
                        <TextEntry label="Full Name" value={student.name} copyable />
                        <TextEntry label="Email" value={student.email} />
                        <TextEntry label="Phone" value={student.contacts?.personal_contact} />
                        <TextEntry label="Birth Date" value={student.birth_date} />
                    </div>
                    <div className="flex w-full flex-col items-center gap-5 md:items-end">
                        <div className="group relative h-32 w-32 cursor-pointer overflow-hidden rounded-full border-2 border-gray-200 bg-gray-100">
                            <input
                                type="file"
                                ref={fileInputRef}
                                className="hidden"
                                accept="image/*"
                                onChange={handleFileChange}
                                disabled={processing}
                            />

                            {processing ? (
                                <div className="flex h-full w-full items-center justify-center bg-gray-100/80">
                                    <Loader2 className="h-8 w-8 animate-spin text-primary" />
                                </div>
                            ) : profilePhotoUrl ? (
                                <img src={profilePhotoUrl} alt={student.name} className="h-full w-full object-cover transition-opacity group-hover:opacity-50" />
                            ) : (
                                <div className="flex h-full w-full items-center justify-center text-gray-400 transition-colors group-hover:text-primary">
                                    <UserIcon className="h-12 w-12" />
                                </div>
                            )}

                            {!processing && (
                                <div
                                    onClick={() => fileInputRef.current?.click()}
                                    className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
                                >
                                    <Camera className="h-8 w-8 text-white" />
                                </div>
                            )}
                        </div>

                        <div className="w-full max-w-[200px]">
                            <div className="text-muted-foreground mb-1 flex items-center gap-1.5 text-[10px] font-medium tracking-wider uppercase">
                                Signature
                            </div>
                            <StudentSignaturePad studentId={student.id} signatureUrl={student.signature_url} />
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
