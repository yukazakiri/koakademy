import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { router } from "@inertiajs/react";
import { useState } from "react";
import { Camera, Copy, Loader2, User as UserIcon } from "lucide-react";
import React, { useRef } from "react";
import { toast } from "sonner";
import type { StudentDetail } from "../types";
import { TextEntry } from "./text-entry";
import { Button } from "@/components/ui/button";

interface StudentDetailsCardProps {
    student: StudentDetail;
}

export function StudentDetailsCard({ student }: StudentDetailsCardProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);

    
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
                    <div className="flex justify-center md:justify-end">
                        <div className="relative group h-32 w-32 overflow-hidden rounded-full border-2 border-gray-200 bg-gray-100 cursor-pointer">
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
                            ) : student.documents?.picture_1x1 ? (
                                <img
                                    src={`/storage/${student.documents.picture_1x1}`}
                                    alt={student.name}
                                    className="h-full w-full object-cover transition-opacity group-hover:opacity-50"
                                />
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
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
