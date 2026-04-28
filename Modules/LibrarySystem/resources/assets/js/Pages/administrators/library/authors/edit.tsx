import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { Feather, Save } from "lucide-react";
import type { FormEvent } from "react";

declare const route: any;

interface AuthorFormData {
    name: string;
    biography: string;
    birth_date: string;
    nationality: string;
}

interface AuthorRecord {
    id: number;
    name: string;
    biography: string | null;
    birth_date: string | null;
    nationality: string | null;
}

interface Props {
    user: User;
    author: AuthorRecord | null;
}

export default function LibraryAuthorEdit({ user, author }: Props) {
    const form = useForm<AuthorFormData>({
        name: author?.name ?? "",
        biography: author?.biography ?? "",
        birth_date: author?.birth_date ?? "",
        nationality: author?.nationality ?? "",
    });

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (author) {
            form.put(route("administrators.library.authors.update", author.id));
            return;
        }

        form.post(route("administrators.library.authors.store"));
    };

    return (
        <AdminLayout user={user} title={author ? "Edit Author" : "Add Author"}>
            <Head title={`Administrators • ${author ? "Edit" : "Add"} Author`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-sky-500/10 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600">
                                <Feather className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle>{author ? "Update Author" : "Add New Author"}</CardTitle>
                                <CardDescription>Document contributors for the catalog.</CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route("administrators.library.authors.index")}>Back to authors</Link>
                            </Button>
                            <Button type="submit" className="gap-2" disabled={form.processing}>
                                <Save className="h-4 w-4" />
                                {author ? "Save changes" : "Create author"}
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Author Profile</CardTitle>
                        <CardDescription>Store biography and background for search results.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="name">Author Name</Label>
                            <Input id="name" value={form.data.name} onChange={(event) => form.setData("name", event.target.value)} />
                            {form.errors.name && <p className="text-destructive text-xs">{form.errors.name}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="nationality">Nationality</Label>
                            <Input
                                id="nationality"
                                value={form.data.nationality}
                                onChange={(event) => form.setData("nationality", event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="birth_date">Birth Date</Label>
                            <Input
                                id="birth_date"
                                type="date"
                                value={form.data.birth_date}
                                onChange={(event) => form.setData("birth_date", event.target.value)}
                            />
                            {form.errors.birth_date && <p className="text-destructive text-xs">{form.errors.birth_date}</p>}
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="biography">Biography</Label>
                            <Textarea
                                id="biography"
                                rows={5}
                                value={form.data.biography}
                                onChange={(event) => form.setData("biography", event.target.value)}
                            />
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AdminLayout>
    );
}
