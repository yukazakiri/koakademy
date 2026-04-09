import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect, type MultiSelectOption } from "@/components/ui/multi-select";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { TagInput } from "@/components/ui/tag-input";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { FileText, GraduationCap, ImageIcon, Save, UploadCloud } from "lucide-react";
import { useState, type FormEvent } from "react";

declare const route: any;

interface SelectOption {
    value: string | number;
    label: string;
}

interface ResearchPaperFormData {
    title: string;
    type: string;
    student_ids: string[];
    course_id: string;
    advisor_name: string;
    contributors: string;
    abstract: string;
    tags: string[];
    publication_year: string;
    document_url: string;
    document_upload: File | null;
    cover_image_upload: File | null;
    status: string;
    is_public: boolean;
    notes: string;
}

interface ResearchPaperRecord {
    id: number;
    title: string;
    type: string;
    student_ids: string[];
    course_id: number | null;
    advisor_name: string | null;
    contributors: string | null;
    abstract: string | null;
    tags?: string[];
    keywords?: string | null;
    publication_year: number | null;
    document_url: string | null;
    document_download_url?: string | null;
    status: string;
    is_public: boolean;
    notes: string | null;
    cover_image_url?: string | null;
}

interface Props {
    user: User;
    paper: ResearchPaperRecord | null;
    options: {
        students: MultiSelectOption[];
        courses: SelectOption[];
        types: SelectOption[];
        statuses: SelectOption[];
        tags: SelectOption[];
    };
}

export default function ResearchPaperEdit({ user, paper, options }: Props) {
    const form = useForm<ResearchPaperFormData>({
        title: paper?.title ?? "",
        type: paper?.type ?? "capstone",
        student_ids: paper?.student_ids ?? [],
        course_id: paper?.course_id ? String(paper.course_id) : "",
        advisor_name: paper?.advisor_name ?? "",
        contributors: paper?.contributors ?? "",
        abstract: paper?.abstract ?? "",
        tags: paper?.tags ?? [],
        publication_year: paper?.publication_year ? String(paper.publication_year) : String(new Date().getFullYear()),
        document_url: paper?.document_url ?? "",
        document_upload: null,
        cover_image_upload: null,
        status: paper?.status ?? "draft",
        is_public: paper?.is_public ?? false,
        notes: paper?.notes ?? "",
    });

    const [coverUploadPreview, setCoverUploadPreview] = useState<string | null>(null);
    const coverPreview = coverUploadPreview ?? paper?.cover_image_url ?? null;
    const documentDownloadUrl = paper?.document_download_url ?? null;

    const currentYear = new Date().getFullYear();
    const years = Array.from({ length: 50 }, (_, i) => String(currentYear - i));

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (paper) {
            form.transform((data) => ({
                ...data,
                _method: "put",
            }));

            form.post(route("administrators.library.research-papers.update", paper.id), {
                forceFormData: true,
            });
            return;
        }

        form.post(route("administrators.library.research-papers.store"), {
            forceFormData: true,
        });
    };

    return (
        <AdminLayout user={user} title={paper ? "Edit Research" : "Add Research"}>
            <Head title={`Administrators • ${paper ? "Edit" : "Add"} Research Paper`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-indigo-500/10 to-sky-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 shadow-sm">
                                <GraduationCap className="h-6 w-6" />
                            </div>
                            <div>
                                <CardTitle className="text-xl">{paper ? "Update Research Record" : "Add Research Paper"}</CardTitle>
                                <CardDescription>Archive capstones, theses, and student research.</CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route("administrators.library.research-papers.index")}>Cancel</Link>
                            </Button>
                            <Button type="submit" className="gap-2" disabled={form.processing}>
                                <Save className="h-4 w-4" />
                                {paper ? "Save changes" : "Create record"}
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Research Details</CardTitle>
                                <CardDescription>Capture the academic information and summary.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5 sm:grid-cols-2">
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        value={form.data.title}
                                        onChange={(event) => form.setData("title", event.target.value)}
                                        placeholder="Enter the full title of the research paper"
                                        className="text-lg font-medium"
                                    />
                                    {form.errors.title && <p className="text-destructive text-xs">{form.errors.title}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label>Type</Label>
                                    <Select value={form.data.type} onValueChange={(value) => form.setData("type", value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.types.map((type) => (
                                                <SelectItem key={type.value} value={String(type.value)}>
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.type && <p className="text-destructive text-xs">{form.errors.type}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label>Status</Label>
                                    <Select value={form.data.status} onValueChange={(value) => form.setData("status", value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.statuses.map((status) => (
                                                <SelectItem key={status.value} value={String(status.value)}>
                                                    {status.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.status && <p className="text-destructive text-xs">{form.errors.status}</p>}
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Students (Authors)</Label>
                                    <MultiSelect
                                        options={options.students}
                                        selected={form.data.student_ids}
                                        onChange={(selected) => form.setData("student_ids", selected)}
                                        placeholder="Select student authors"
                                        searchPlaceholder="Search students..."
                                    />
                                    {form.errors.student_ids && <p className="text-destructive text-xs">{form.errors.student_ids}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label>Course</Label>
                                    <Select value={form.data.course_id} onValueChange={(value) => form.setData("course_id", value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select course" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.courses.map((course) => (
                                                <SelectItem key={course.value} value={String(course.value)}>
                                                    {course.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.course_id && <p className="text-destructive text-xs">{form.errors.course_id}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="publication_year">Publication Year</Label>
                                    <Select value={form.data.publication_year} onValueChange={(value) => form.setData("publication_year", value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Year" />
                                        </SelectTrigger>
                                        <SelectContent className="max-h-60">
                                            {years.map((year) => (
                                                <SelectItem key={year} value={year}>
                                                    {year}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="advisor_name">Advisor Name</Label>
                                    <Input
                                        id="advisor_name"
                                        value={form.data.advisor_name}
                                        onChange={(event) => form.setData("advisor_name", event.target.value)}
                                        placeholder="e.g. Dr. John Doe"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="contributors">Other Contributors</Label>
                                    <Input
                                        id="contributors"
                                        value={form.data.contributors}
                                        onChange={(event) => form.setData("contributors", event.target.value)}
                                        placeholder="Optional co-advisors or panels"
                                    />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="abstract">Abstract</Label>
                                    <Textarea
                                        id="abstract"
                                        rows={6}
                                        value={form.data.abstract}
                                        onChange={(event) => form.setData("abstract", event.target.value)}
                                        placeholder="Brief summary of the research..."
                                        className="resize-y"
                                    />
                                </div>
                                <div className="space-y-2 sm:col-span-2">
                                    <Label>Keywords (Tags)</Label>
                                    <TagInput
                                        tags={form.data.tags}
                                        setTags={(tags) => form.setData("tags", tags)}
                                        suggestions={options.tags.map((t) => ({ value: String(t.value), label: t.label }))}
                                        placeholder="Add keywords..."
                                    />
                                    <p className="text-muted-foreground text-[0.8rem]">Press Enter to add a new tag.</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-6">
                        <Card className="overflow-hidden">
                            <CardHeader className="bg-muted/40 pb-4">
                                <CardTitle className="text-base">Cover Image</CardTitle>
                                <CardDescription>Visual representation of the paper</CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="bg-muted/20 relative flex aspect-[3/4] flex-col items-center justify-center border-b">
                                    {coverPreview ? (
                                        <img src={coverPreview} alt="Cover preview" className="absolute inset-0 h-full w-full object-cover" />
                                    ) : (
                                        <div className="text-muted-foreground flex flex-col items-center gap-2">
                                            <ImageIcon className="h-10 w-10 opacity-50" />
                                            <span className="text-sm">No cover image</span>
                                        </div>
                                    )}
                                </div>
                                <div className="p-4">
                                    <Label htmlFor="cover_image_upload" className="mb-2 block cursor-pointer">
                                        <div className="hover:bg-muted/50 flex items-center justify-center gap-2 rounded-md border border-dashed py-4 transition-colors">
                                            <UploadCloud className="h-4 w-4" />
                                            <span className="text-sm font-medium">Upload Cover</span>
                                        </div>
                                        <Input
                                            id="cover_image_upload"
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={(event) => {
                                                const file = event.target.files?.[0] || null;
                                                form.setData("cover_image_upload", file);
                                                setCoverUploadPreview(file ? URL.createObjectURL(file) : null);
                                            }}
                                        />
                                    </Label>
                                    {form.errors.cover_image_upload && (
                                        <p className="text-destructive mt-2 text-xs">{form.errors.cover_image_upload}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Document File</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="hover:bg-muted/50 rounded-lg border border-dashed p-4 text-center transition-colors">
                                    <Label htmlFor="document_upload" className="block cursor-pointer">
                                        <div className="flex flex-col items-center gap-2">
                                            <div className="bg-primary/10 rounded-full p-3">
                                                <FileText className="text-primary h-6 w-6" />
                                            </div>
                                            <div className="text-sm font-medium">
                                                {form.data.document_upload ? form.data.document_upload.name : "Upload PDF"}
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                {form.data.document_upload ? "Click to change" : "Max 10MB"}
                                            </div>
                                        </div>
                                        <Input
                                            id="document_upload"
                                            type="file"
                                            accept="application/pdf"
                                            className="hidden"
                                            onChange={(event) => {
                                                const file = event.target.files?.[0] || null;
                                                form.setData("document_upload", file);
                                            }}
                                        />
                                    </Label>
                                </div>
                                {form.errors.document_upload && <p className="text-destructive text-xs">{form.errors.document_upload}</p>}

                                <div className="space-y-2">
                                    <Label htmlFor="document_url" className="text-xs">
                                        Or External URL
                                    </Label>
                                    <Input
                                        id="document_url"
                                        value={form.data.document_url}
                                        onChange={(event) => form.setData("document_url", event.target.value)}
                                        placeholder="https://"
                                        className="h-8 text-xs"
                                    />
                                </div>

                                {documentDownloadUrl && !form.data.document_upload && (
                                    <Button variant="outline" size="sm" className="w-full gap-2" asChild>
                                        <a href={documentDownloadUrl} target="_blank" rel="noreferrer">
                                            <FileText className="h-4 w-4" />
                                            View Current PDF
                                        </a>
                                    </Button>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Visibility & Settings</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label className="text-base">Public</Label>
                                        <p className="text-muted-foreground text-xs">Visible in e-library</p>
                                    </div>
                                    <Switch checked={form.data.is_public} onCheckedChange={(value) => form.setData("is_public", value)} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Notes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Textarea
                                    id="notes"
                                    rows={4}
                                    value={form.data.notes}
                                    onChange={(event) => form.setData("notes", event.target.value)}
                                    placeholder="Internal remarks..."
                                    className="resize-none"
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}
