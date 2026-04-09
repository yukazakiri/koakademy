import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { Palette, Save } from "lucide-react";
import type { FormEvent } from "react";

declare const route: any;

interface CategoryFormData {
    name: string;
    description: string;
    color: string;
}

interface CategoryRecord {
    id: number;
    name: string;
    description: string | null;
    color: string | null;
}

interface Props {
    user: User;
    category: CategoryRecord | null;
}

export default function LibraryCategoryEdit({ user, category }: Props) {
    const form = useForm<CategoryFormData>({
        name: category?.name ?? "",
        description: category?.description ?? "",
        color: category?.color ?? "#6366f1",
    });

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (category) {
            form.put(route("administrators.library.categories.update", category.id));
            return;
        }

        form.post(route("administrators.library.categories.store"));
    };

    return (
        <AdminLayout user={user} title={category ? "Edit Category" : "Add Category"}>
            <Head title={`Administrators • ${category ? "Edit" : "Add"} Category`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-violet-500/10 to-amber-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600">
                                <Palette className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle>{category ? "Update Category" : "Create Category"}</CardTitle>
                                <CardDescription>Standardize colors and groupings.</CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route("administrators.library.categories.index")}>Back to categories</Link>
                            </Button>
                            <Button type="submit" className="gap-2" disabled={form.processing}>
                                <Save className="h-4 w-4" />
                                {category ? "Save changes" : "Create category"}
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Category Details</CardTitle>
                        <CardDescription>Keep descriptions short for quick scanning.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="name">Category Name</Label>
                            <Input id="name" value={form.data.name} onChange={(event) => form.setData("name", event.target.value)} />
                            {form.errors.name && <p className="text-destructive text-xs">{form.errors.name}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="color">Color</Label>
                            <div className="flex items-center gap-3">
                                <Input
                                    id="color"
                                    type="color"
                                    value={form.data.color}
                                    onChange={(event) => form.setData("color", event.target.value)}
                                    className="h-10 w-14 p-1"
                                />
                                <Input
                                    value={form.data.color}
                                    onChange={(event) => form.setData("color", event.target.value)}
                                    className="font-mono"
                                />
                            </div>
                            {form.errors.color && <p className="text-destructive text-xs">{form.errors.color}</p>}
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                rows={4}
                                value={form.data.description}
                                onChange={(event) => form.setData("description", event.target.value)}
                            />
                        </div>
                    </CardContent>
                </Card>
            </form>
        </AdminLayout>
    );
}
