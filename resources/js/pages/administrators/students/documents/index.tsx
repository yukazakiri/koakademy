import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { AlertCircle, ChevronLeft, File, FileText, Image as ImageIcon, Trash2, Upload } from "lucide-react";
import React, { useState } from "react";
import { toast } from "sonner";

// eslint-disable-next-line @typescript-eslint/no-explicit-any
declare let route: any;

const FIXED_DOCUMENT_TYPES = [
    { key: "picture_1x1", label: "1x1 Picture", accept: "image/*" },
    { key: "birth_certificate", label: "Birth Certificate", accept: "image/*,application/pdf" },
    { key: "form_137", label: "Form 137 (SF10)", accept: "image/*,application/pdf" },
    { key: "form_138", label: "Form 138 (Report Card)", accept: "image/*,application/pdf" },
    { key: "good_moral_cert", label: "Good Moral Certificate", accept: "image/*,application/pdf" },
    { key: "transfer_credentials", label: "Transfer Credentials", accept: "image/*,application/pdf" },
    { key: "transcript_records", label: "Transcript of Records", accept: "image/*,application/pdf" },
];

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function UploadFixedDialog({ student, docType, label, accept }: any) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        document_type: docType,
        file: null as File | null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route("administrators.students.documents.fixed.update", student.id), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
                toast.success("Document uploaded successfully");
            },
            onError: (err) => {
                toast.error(err.file || "Failed to upload document");
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Upload className="mr-2 h-4 w-4" />
                    Upload
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Upload {label}</DialogTitle>
                    <DialogDescription>Please upload a clear copy. Supported formats: PDF, JPEG, PNG. Max 5MB.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="file">Select File</Label>
                        <Input id="file" type="file" accept={accept} onChange={(e) => setData("file", e.target.files ? e.target.files[0] : null)} />
                        {errors.file && <p className="text-destructive text-sm">{errors.file}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !data.file}>
                            Upload Document
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function UploadDynamicDialog({ student }: any) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        type: "",
        file: null as File | null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route("administrators.students.documents.dynamic.store", student.id), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
                toast.success("Document uploaded successfully");
            },
            onError: (err) => {
                toast.error(err.file || err.type || "Failed to upload document");
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Upload className="mr-2 h-4 w-4" />
                    Upload Other Document
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Upload Additional Document</DialogTitle>
                    <DialogDescription>Upload medical records, certificates, assessments, etc.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="type">Document Type / Description</Label>
                        <Input
                            id="type"
                            placeholder="e.g. Medical Certificate, Assessment Exam"
                            value={data.type}
                            onChange={(e) => setData("type", e.target.value)}
                        />
                        {errors.type && <p className="text-destructive text-sm">{errors.type}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="file">Select File</Label>
                        <Input id="file" type="file" onChange={(e) => setData("file", e.target.files ? e.target.files[0] : null)} />
                        {errors.file && <p className="text-destructive text-sm">{errors.file}</p>}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !data.file || !data.type}>
                            Upload Document
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function DeleteDynamicDialog({ student, resource }: any) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const handleDelete = () => {
        setProcessing(true);
        router.delete(route("administrators.students.documents.dynamic.destroy", [student.id, resource.id]), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                toast.success("Document deleted successfully");
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" className="text-destructive hover:bg-destructive/10">
                    <Trash2 className="h-4 w-4" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Document</DialogTitle>
                    <DialogDescription>Are you sure you want to delete this document? This action cannot be undone.</DialogDescription>
                </DialogHeader>
                <div className="py-4">
                    <div className="bg-muted rounded-md p-4">
                        <p className="text-foreground font-medium">{resource.file_name}</p>
                        <p className="text-muted-foreground text-sm">{resource.type}</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button type="button" variant="destructive" onClick={handleDelete} disabled={processing}>
                        Delete Document
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const formatBytes = (bytes: number) => {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export default function StudentDocuments({ auth, student, fixed_documents, dynamic_documents }: any) {
    return (
        <AdminLayout user={auth.user}>
            <Head title={`Documents - ${student.full_name}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="icon" asChild>
                        <Link href={route("administrators.students.show", student.id)}>
                            <ChevronLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Student Documents</h1>
                        <p className="text-muted-foreground">
                            {student.full_name} ({student.student_id})
                        </p>
                    </div>
                </div>

                <Tabs defaultValue="required" className="w-full">
                    <TabsList>
                        <TabsTrigger value="required">Required Documents</TabsTrigger>
                        <TabsTrigger value="additional">Additional Documents</TabsTrigger>
                    </TabsList>

                    <TabsContent value="required" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Required Documents</CardTitle>
                                <CardDescription>Manage standard admission and enrollment requirements.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Document Type</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {FIXED_DOCUMENT_TYPES.map((doc) => {
                                            const hasFile = fixed_documents && fixed_documents[doc.key];
                                            return (
                                                <TableRow key={doc.key}>
                                                    <TableCell className="font-medium">
                                                        <div className="flex items-center gap-2">
                                                            {doc.key === "picture_1x1" ? (
                                                                <ImageIcon className="text-muted-foreground h-4 w-4" />
                                                            ) : (
                                                                <FileText className="text-muted-foreground h-4 w-4" />
                                                            )}
                                                            {doc.label}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        {hasFile ? (
                                                            <Badge variant="default" className="bg-green-500 hover:bg-green-600">
                                                                Submitted
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="secondary" className="text-muted-foreground">
                                                                Missing
                                                            </Badge>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex justify-end gap-2">
                                                            {hasFile && (
                                                                <Button variant="outline" size="sm" asChild>
                                                                    <a
                                                                        href={fixed_documents[doc.key]}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                    >
                                                                        View
                                                                    </a>
                                                                </Button>
                                                            )}
                                                            <UploadFixedDialog
                                                                student={student}
                                                                docType={doc.key}
                                                                label={doc.label}
                                                                accept={doc.accept}
                                                            />
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="additional" className="mt-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>Additional Documents</CardTitle>
                                    <CardDescription>Upload and manage other relevant student files.</CardDescription>
                                </div>
                                <UploadDynamicDialog student={student} />
                            </CardHeader>
                            <CardContent>
                                {dynamic_documents?.length > 0 ? (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>File Name</TableHead>
                                                <TableHead>Type/Description</TableHead>
                                                <TableHead>Size</TableHead>
                                                <TableHead>Uploaded</TableHead>
                                                <TableHead className="text-right">Actions</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                                            {dynamic_documents.map((doc: any) => (
                                                <TableRow key={doc.id}>
                                                    <TableCell className="font-medium">
                                                        <div className="flex items-center gap-2">
                                                            <File className="text-muted-foreground h-4 w-4" />
                                                            <span className="max-w-[200px] truncate" title={doc.file_name}>
                                                                {doc.file_name}
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline">{doc.type}</Badge>
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground text-sm">{formatBytes(doc.file_size)}</TableCell>
                                                    <TableCell className="text-muted-foreground text-sm">
                                                        {new Date(doc.created_at).toLocaleDateString()}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <Button variant="ghost" size="icon" asChild>
                                                                <a href={`/storage/${doc.file_path}`} target="_blank" rel="noopener noreferrer">
                                                                    <FileText className="h-4 w-4" />
                                                                </a>
                                                            </Button>
                                                            <DeleteDynamicDialog student={student} resource={doc} />
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                ) : (
                                    <div className="text-muted-foreground flex flex-col items-center justify-center py-12 text-center">
                                        <AlertCircle className="text-muted-foreground/50 mb-4 h-12 w-12" />
                                        <h3 className="text-foreground text-lg font-medium">No additional documents</h3>
                                        <p className="mt-2 text-sm">You haven't uploaded any additional documents for this student yet.</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AdminLayout>
    );
}
