import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, useForm, router } from "@inertiajs/react";
import { route } from "ziggy-js";

interface ClassInfo { id: number; code: string; section: string; students: number }
interface Resource {
    id: number; title: string; description: string | null; type: string; url: string;
    visibility: string; downloads: number; class_code: string; class_id: number; created_at: string;
}
interface Props { user: User; classes: ClassInfo[]; resources: Resource[] }

export default function ResourcesPage({ user, classes, resources }: Props) {
    const form = useForm<{
        class_id: string; title: string; description: string; type: string; url: string; file: File | null; visibility: string;
    }>({
        class_id: "", title: "", description: "", type: "link", url: "", file: null, visibility: "students",
    });

    const submit = () => {
        form.post(route("faculty.resources.store"), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => form.reset("title", "description", "url", "file"),
        });
    };

    const remove = (id: number) => router.delete(route("faculty.resources.destroy", id), { preserveScroll: true });

    const download = (r: Resource) => {
        const href = r.type === "link" ? r.url : `/storage/${r.url}`;
        window.open(href, "_blank");
    };

    return (
        <FacultyLayout user={user}>
            <Head title="Resources" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">Resources</h1>
                    <p className="text-muted-foreground text-sm">Links and files shared with your classes.</p>
                </header>

                <Card>
                    <CardHeader><CardTitle className="text-base">Add resource</CardTitle></CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-1">
                            <Label>Class</Label>
                            <Select value={form.data.class_id} onValueChange={(v) => form.setData("class_id", v)}>
                                <SelectTrigger><SelectValue placeholder="Select class…" /></SelectTrigger>
                                <SelectContent>
                                    {classes.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.code} · {c.section}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            {form.errors.class_id && <p className="text-destructive text-xs">{form.errors.class_id}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData("type", v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="link">Link</SelectItem>
                                    <SelectItem value="file">File upload</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>Title</Label>
                            <Input value={form.data.title} onChange={(e) => form.setData("title", e.target.value)} placeholder="Week 1 reading" />
                            {form.errors.title && <p className="text-destructive text-xs">{form.errors.title}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Visibility</Label>
                            <Select value={form.data.visibility} onValueChange={(v) => form.setData("visibility", v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="students">Students</SelectItem>
                                    <SelectItem value="faculty_only">Only me</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        {form.data.type === "link" ? (
                            <div className="space-y-1 sm:col-span-2">
                                <Label>URL</Label>
                                <Input value={form.data.url} onChange={(e) => form.setData("url", e.target.value)} placeholder="https://…" />
                                {form.errors.url && <p className="text-destructive text-xs">{form.errors.url}</p>}
                            </div>
                        ) : (
                            <div className="space-y-1 sm:col-span-2">
                                <Label>File</Label>
                                <Input
                                    type="file"
                                    onChange={(e) => form.setData("file", (e.target as HTMLInputElement).files?.[0] ?? null)}
                                />
                                {form.errors.file && <p className="text-destructive text-xs">{form.errors.file}</p>}
                            </div>
                        )}
                        <div className="space-y-1 sm:col-span-2">
                            <Label>Description</Label>
                            <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} />
                        </div>
                        <div>
                            <Button onClick={submit} disabled={form.processing}>Add resource</Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Library ({resources.length})</CardTitle></CardHeader>
                    <CardContent className="space-y-2">
                        {resources.length === 0 && <p className="text-muted-foreground text-sm">No resources yet.</p>}
                        {resources.map((r) => (
                            <div key={r.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="truncate text-sm font-medium">{r.title}</p>
                                        <Badge variant="secondary" className="shrink-0 font-normal">{r.type}</Badge>
                                        {r.visibility === "faculty_only" && <Badge variant="outline" className="shrink-0 font-normal">private</Badge>}
                                    </div>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {r.class_code} · {r.created_at}{r.downloads > 0 ? ` · ${r.downloads} downloads` : ""}{r.description ? ` — ${r.description}` : ""}
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-1">
                                    <Button size="sm" variant="outline" onClick={() => download(r)}>Open</Button>
                                    <Button size="sm" variant="ghost" className="text-destructive" onClick={() => remove(r.id)}>✕</Button>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </main>
        </FacultyLayout>
    );
}
