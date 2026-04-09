import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { BookOpen, Save } from "lucide-react";
import { useState, type FormEvent } from "react";

declare const route: any;

interface SelectOption {
    value: string | number;
    label: string;
}

interface BookFormData {
    title: string;
    isbn: string;
    author_id: string;
    category_id: string;
    publisher: string;
    publication_year: string;
    pages: string;
    description: string;
    cover_image: string;
    cover_image_upload: File | null;
    total_copies: string;
    available_copies: string;
    location: string;
    status: string;
}

interface BookRecord {
    id: number;
    title: string;
    isbn: string | null;
    author_id: number;
    category_id: number;
    publisher: string | null;
    publication_year: number | null;
    pages: number | null;
    description: string | null;
    cover_image: string | null;
    cover_image_url?: string | null;
    total_copies: number;
    available_copies: number;
    location: string | null;
    status: string;
}

interface Props {
    user: User;
    book: BookRecord | null;
    options: {
        authors: SelectOption[];
        categories: SelectOption[];
        statuses: SelectOption[];
    };
}

export default function LibraryBookEdit({ user, book, options }: Props) {
    const form = useForm<BookFormData>({
        title: book?.title ?? "",
        isbn: book?.isbn ?? "",
        author_id: book?.author_id ? String(book.author_id) : "",
        category_id: book?.category_id ? String(book.category_id) : "",
        publisher: book?.publisher ?? "",
        publication_year: book?.publication_year ? String(book.publication_year) : "",
        pages: book?.pages ? String(book.pages) : "",
        description: book?.description ?? "",
        cover_image: book?.cover_image ?? "",
        cover_image_upload: null,
        total_copies: book?.total_copies ? String(book.total_copies) : "1",
        available_copies: book?.available_copies ? String(book.available_copies) : "",
        location: book?.location ?? "",
        status: book?.status ?? "available",
    });

    const [coverUploadPreview, setCoverUploadPreview] = useState<string | null>(null);
    const coverPreview = coverUploadPreview ?? (form.data.cover_image ? form.data.cover_image : book?.cover_image_url ? book.cover_image_url : null);

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (book) {
            form.put(route("administrators.library.books.update", book.id), {
                forceFormData: true,
            });
            return;
        }

        form.post(route("administrators.library.books.store"), {
            forceFormData: true,
        });
    };

    return (
        <AdminLayout user={user} title={book ? "Edit Book" : "Add Book"}>
            <Head title={`Administrators • ${book ? "Edit" : "Add"} Book`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-emerald-500/10 to-sky-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-1">
                            <div className="flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600">
                                    <BookOpen className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>{book ? "Update Catalog Entry" : "Add New Book"}</CardTitle>
                                    <CardDescription>Keep book metadata, copies, and availability up to date.</CardDescription>
                                </div>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route("administrators.library.books.index")}>Back to books</Link>
                            </Button>
                            <Button type="submit" className="gap-2" disabled={form.processing}>
                                <Save className="h-4 w-4" />
                                {book ? "Save changes" : "Create book"}
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Book Details</CardTitle>
                            <CardDescription>Core catalog information for this book.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="title">Title</Label>
                                <Input id="title" value={form.data.title} onChange={(event) => form.setData("title", event.target.value)} />
                                {form.errors.title && <p className="text-destructive text-xs">{form.errors.title}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="isbn">ISBN</Label>
                                <Input id="isbn" value={form.data.isbn} onChange={(event) => form.setData("isbn", event.target.value)} />
                                {form.errors.isbn && <p className="text-destructive text-xs">{form.errors.isbn}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Author</Label>
                                <Select value={form.data.author_id} onValueChange={(value) => form.setData("author_id", value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select author" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.authors.map((author) => (
                                            <SelectItem key={author.value} value={String(author.value)}>
                                                {author.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.author_id && <p className="text-destructive text-xs">{form.errors.author_id}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Category</Label>
                                <Select value={form.data.category_id} onValueChange={(value) => form.setData("category_id", value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.categories.map((category) => (
                                            <SelectItem key={category.value} value={String(category.value)}>
                                                {category.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.category_id && <p className="text-destructive text-xs">{form.errors.category_id}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="publisher">Publisher</Label>
                                <Input
                                    id="publisher"
                                    value={form.data.publisher}
                                    onChange={(event) => form.setData("publisher", event.target.value)}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="publication_year">Publication Year</Label>
                                <Input
                                    id="publication_year"
                                    type="number"
                                    value={form.data.publication_year}
                                    onChange={(event) => form.setData("publication_year", event.target.value)}
                                />
                                {form.errors.publication_year && <p className="text-destructive text-xs">{form.errors.publication_year}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="pages">Pages</Label>
                                <Input
                                    id="pages"
                                    type="number"
                                    value={form.data.pages}
                                    onChange={(event) => form.setData("pages", event.target.value)}
                                />
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

                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Inventory</CardTitle>
                                <CardDescription>Control availability and shelf details.</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="total_copies">Total Copies</Label>
                                    <Input
                                        id="total_copies"
                                        type="number"
                                        value={form.data.total_copies}
                                        onChange={(event) => form.setData("total_copies", event.target.value)}
                                    />
                                    {form.errors.total_copies && <p className="text-destructive text-xs">{form.errors.total_copies}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="available_copies">Available Copies</Label>
                                    <Input
                                        id="available_copies"
                                        type="number"
                                        value={form.data.available_copies}
                                        onChange={(event) => form.setData("available_copies", event.target.value)}
                                    />
                                    {form.errors.available_copies && <p className="text-destructive text-xs">{form.errors.available_copies}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="location">Shelf Location</Label>
                                    <Input
                                        id="location"
                                        value={form.data.location}
                                        onChange={(event) => form.setData("location", event.target.value)}
                                    />
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
                                <div className="space-y-2">
                                    <Label htmlFor="cover_image">Cover Image URL</Label>
                                    <Input
                                        id="cover_image"
                                        value={form.data.cover_image}
                                        onChange={(event) => form.setData("cover_image", event.target.value)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="cover_image_upload">Cover Image Upload</Label>
                                    <Input
                                        id="cover_image_upload"
                                        type="file"
                                        accept="image/*"
                                        onChange={(event) => {
                                            const file = event.target.files?.[0] || null;
                                            form.setData("cover_image_upload", file);
                                            setCoverUploadPreview(file ? URL.createObjectURL(file) : null);
                                        }}
                                    />
                                    {form.errors.cover_image_upload && <p className="text-destructive text-xs">{form.errors.cover_image_upload}</p>}
                                </div>
                                {coverPreview && (
                                    <div className="overflow-hidden rounded-lg border">
                                        <img src={coverPreview} alt="Book cover preview" className="h-40 w-full object-cover" />
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Catalog Tips</CardTitle>
                                <CardDescription>Keep entries consistent across the library.</CardDescription>
                            </CardHeader>
                            <CardContent className="text-muted-foreground space-y-2 text-sm">
                                <p>Use a consistent ISBN format for easier search and reporting.</p>
                                <p>Match available copies with active borrow records to avoid mismatches.</p>
                                <p>Add a shelf location to speed up staff retrieval.</p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}
