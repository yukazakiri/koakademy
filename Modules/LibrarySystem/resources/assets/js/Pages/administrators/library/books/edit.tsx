import {
    destroy as destroyDigitalEdition,
    store as storeDigitalEdition,
    update as updateDigitalEdition,
} from "@/actions/Modules/LibrarySystem/Http/Controllers/AdministratorDigitalEditionController";
import { store as storeBook, update as updateBook } from "@/actions/Modules/LibrarySystem/Http/Controllers/AdministratorLibraryBookController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { index as libraryBooksIndex } from "@/routes/administrators/library/books";
import { read as readDigitalBook } from "@/routes/library/books";
import type { User } from "@/types/user";
import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import {
    ArrowLeft,
    BookOpen,
    BookPlus,
    CheckCircle2,
    Download,
    FileCheck2,
    FileText,
    Loader2,
    Save,
    ShieldCheck,
    Trash2,
    UploadCloud,
} from "lucide-react";
import { useRef, useState, type FormEvent } from "react";
import { toast } from "sonner";
import { CatalogIdentifierField, CatalogRelationField, type CatalogOption } from "./components/catalog-entry-controls";

interface BookFormData {
    title: string;
    isbn: string;
    call_number: string;
    accession_number: string;
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
    call_number: string | null;
    accession_number: string | null;
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
    digital_edition: DigitalEditionRecord | null;
    can_manage_digital_edition: boolean;
}

interface DigitalEditionRecord {
    id: number;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    status: "draft" | "published";
    downloads_allowed: boolean;
    rights_basis: string | null;
    rights_holder: string | null;
    license_url: string | null;
    rights_notes: string | null;
    rights_expires_at: string | null;
    uploaded_at: string | null;
    published_at: string | null;
}

interface Props {
    user: User;
    book: BookRecord | null;
    options: {
        authors: CatalogOption[];
        categories: CatalogOption[];
        statuses: CatalogOption[];
        digital_rights_bases: CatalogOption[];
    };
}

interface BookPageProps {
    errors?: Record<string, unknown>;
}

interface BookFormError {
    field: string;
    message: string;
}

function normalizeBookError(value: unknown): string | null {
    if (Array.isArray(value)) {
        const firstMessage = value.find((message): message is string => typeof message === "string" && message.trim() !== "");

        return firstMessage ?? null;
    }

    return typeof value === "string" && value.trim() !== "" ? value : null;
}

function getBookFormErrors(errors: object): BookFormError[] {
    return Object.entries(errors).flatMap(([field, value]) => {
        const message = normalizeBookError(value);

        return message ? [{ field, message }] : [];
    });
}

export default function LibraryBookEdit({ user, book, options }: Props) {
    const form = useForm<BookFormData>({
        title: book?.title ?? "",
        isbn: book?.isbn ?? "",
        call_number: book?.call_number ?? "",
        accession_number: book?.accession_number ?? "",
        author_id: book?.author_id ? String(book.author_id) : "",
        category_id: book?.category_id ? String(book.category_id) : "",
        publisher: book?.publisher ?? "",
        publication_year: book?.publication_year ? String(book.publication_year) : "",
        pages: book?.pages ? String(book.pages) : "",
        description: book?.description ?? "",
        cover_image: book?.cover_image ?? "",
        cover_image_upload: null,
        total_copies: book ? String(book.total_copies) : "1",
        available_copies: book ? String(book.available_copies) : "1",
        location: book?.location ?? "",
        status: book?.status ?? "available",
    });

    const page = usePage<BookPageProps>();
    const [coverUploadPreview, setCoverUploadPreview] = useState<string | null>(null);
    const [bookErrors, setBookErrors] = useState<Record<string, unknown>>({});
    const errorSummaryRef = useRef<HTMLDivElement | null>(null);
    const coverPreview = coverUploadPreview ?? (form.data.cover_image ? form.data.cover_image : book?.cover_image_url ? book.cover_image_url : null);
    const HeaderIcon = book ? BookOpen : BookPlus;
    const pageErrors = page.props.errors ?? {};
    const visibleBookErrors = Object.keys(pageErrors).length > 0 ? pageErrors : bookErrors;
    const bookFormErrors = getBookFormErrors(visibleBookErrors);
    const bookError = (field: string) => normalizeBookError(visibleBookErrors[field]);

    const handleTotalCopiesChange = (value: string) => {
        const availableCopiesFollowTotal = !book && form.data.available_copies === form.data.total_copies;

        form.setData("total_copies", value);

        if (availableCopiesFollowTotal) {
            form.setData("available_copies", value);
        }
    };

    const handleRequestError = (errors: object) => {
        setBookErrors(errors);
        form.setError(errors);
        const errorCount = getBookFormErrors(errors).length;

        toast.error(book ? "Book could not be updated." : "Book could not be created.", {
            description:
                errorCount > 0 ? "Review the highlighted fields and try again." : "Please try again or contact support if the problem continues.",
        });

        window.setTimeout(() => {
            errorSummaryRef.current?.focus({ preventScroll: true });
            errorSummaryRef.current?.scrollIntoView({ behavior: "smooth", block: "center" });
        }, 0);
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const requestOptions = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setBookErrors({});
                toast.success(book ? "Book updated successfully." : "Book created successfully.", { id: "library-book-save" });
            },
            onError: handleRequestError,
            onHttpException: () => {
                toast.error(book ? "Book could not be updated." : "Book could not be created.", {
                    description: "The server returned an unexpected response. Please try again.",
                });

                return false;
            },
            onNetworkError: () => {
                toast.error(book ? "Book could not be updated." : "Book could not be created.", {
                    description: "The connection was interrupted. Check your network and try again.",
                });

                return false;
            },
        };

        if (book) {
            form.transform((data) => ({ ...data, _method: "put" }));
            form.post(updateBook.url(book.id), requestOptions);
            return;
        }

        form.post(storeBook.url(), requestOptions);
    };

    return (
        <AdminLayout user={user} title={book ? "Edit Book" : "Add Book"}>
            <Head title={`Administrators • ${book ? "Edit" : "Add"} Book`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6" data-testid="book-form">
                {bookFormErrors.length > 0 && (
                    <div
                        ref={errorSummaryRef}
                        id="book-form-errors"
                        data-testid="book-form-errors"
                        role="alert"
                        tabIndex={-1}
                        className="border-destructive/30 bg-destructive/10 text-destructive rounded-2xl border p-4 outline-none"
                    >
                        <p className="font-semibold">Review the highlighted fields before saving.</p>
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">
                            {bookFormErrors.map(({ field, message }) => (
                                <li key={field}>
                                    <a className="underline underline-offset-2" href={`#${field}`}>
                                        {message}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
                <Card className="to-background relative overflow-hidden border-0 bg-gradient-to-br from-emerald-500/12 via-sky-500/5 shadow-xs ring-1 ring-emerald-700/10">
                    <div className="pointer-events-none absolute -top-20 -right-16 size-52 rounded-full bg-emerald-400/10 blur-3xl" />
                    <CardHeader className="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-start gap-3.5">
                            <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/15">
                                <HeaderIcon className="size-5" />
                            </div>
                            <div className="space-y-1">
                                <p className="text-xs font-semibold tracking-[0.16em] text-emerald-700 uppercase dark:text-emerald-300">
                                    {book ? "Catalog maintenance" : "Fast catalog entry"}
                                </p>
                                <CardTitle className="text-xl text-balance">{book ? "Update catalog entry" : "Create a book record"}</CardTitle>
                                <CardDescription className="max-w-2xl text-pretty">
                                    Search existing catalog data, quick-add missing authors or categories, and finish the record without leaving this
                                    page.
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" asChild className="min-h-10 transition-transform active:scale-[0.96]">
                                <Link href={libraryBooksIndex.url()} prefetch>
                                    <ArrowLeft className="size-4" />
                                    Back to books
                                </Link>
                            </Button>
                            <Button
                                nativeButton
                                type="submit"
                                size="lg"
                                className="transition-transform active:scale-[0.96]"
                                disabled={form.processing}
                                data-testid="book-submit"
                            >
                                {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                                {form.processing ? (book ? "Saving…" : "Creating…") : book ? "Save changes" : "Create book"}
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(19rem,1fr)]">
                    <Card className="rounded-2xl shadow-xs">
                        <CardHeader className="bg-muted/25 border-b pb-4">
                            <div className="flex items-start gap-3">
                                <span className="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold">
                                    1
                                </span>
                                <div>
                                    <CardTitle>Identity and classification</CardTitle>
                                    <CardDescription className="text-pretty">
                                        Start with the searchable relationships, then reuse or enter catalog identifiers.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-5 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(event) => form.setData("title", event.target.value)}
                                    placeholder="Enter the title as it appears on the book"
                                    className="h-11 rounded-xl"
                                    autoFocus={!book}
                                    aria-invalid={Boolean(bookError("title"))}
                                />
                                {bookError("title") && (
                                    <p id="title-error" className="text-destructive text-xs">
                                        {bookError("title")}
                                    </p>
                                )}
                            </div>

                            <CatalogRelationField
                                id="author_id"
                                kind="author"
                                options={options.authors}
                                value={form.data.author_id}
                                onValueChange={(value) => form.setData("author_id", value)}
                                error={bookError("author_id") ?? undefined}
                            />

                            <CatalogRelationField
                                id="category_id"
                                kind="category"
                                options={options.categories}
                                value={form.data.category_id}
                                onValueChange={(value) => form.setData("category_id", value)}
                                error={bookError("category_id") ?? undefined}
                            />

                            <CatalogIdentifierField
                                id="isbn"
                                kind="isbn"
                                value={form.data.isbn}
                                onValueChange={(value) => form.setData("isbn", value)}
                                error={bookError("isbn") ?? undefined}
                            />

                            <CatalogIdentifierField
                                id="call_number"
                                kind="call_number"
                                value={form.data.call_number}
                                onValueChange={(value) => form.setData("call_number", value)}
                                error={bookError("call_number") ?? undefined}
                            />

                            <div className="space-y-2">
                                <Label htmlFor="accession_number">Accession Number</Label>
                                <Input
                                    id="accession_number"
                                    value={form.data.accession_number}
                                    onChange={(event) => form.setData("accession_number", event.target.value)}
                                    placeholder="Copy-specific accession code"
                                    className="h-11 rounded-xl"
                                    aria-invalid={Boolean(bookError("accession_number"))}
                                />
                                <p className="text-muted-foreground text-xs text-pretty">Use a unique code for this physical catalog record.</p>
                                {bookError("accession_number") && <p className="text-destructive text-xs">{bookError("accession_number")}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="publisher">Publisher</Label>
                                <Input
                                    id="publisher"
                                    value={form.data.publisher}
                                    onChange={(event) => form.setData("publisher", event.target.value)}
                                    placeholder="Publisher name"
                                    className="h-11 rounded-xl"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="publication_year">Publication Year</Label>
                                <Input
                                    id="publication_year"
                                    type="number"
                                    value={form.data.publication_year}
                                    onChange={(event) => form.setData("publication_year", event.target.value)}
                                    placeholder="YYYY"
                                    className="h-11 rounded-xl"
                                    aria-invalid={Boolean(bookError("publication_year"))}
                                />
                                {bookError("publication_year") && <p className="text-destructive text-xs">{bookError("publication_year")}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="pages">Pages</Label>
                                <Input
                                    id="pages"
                                    type="number"
                                    value={form.data.pages}
                                    onChange={(event) => form.setData("pages", event.target.value)}
                                    placeholder="Number of pages"
                                    className="h-11 rounded-xl"
                                />
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    rows={4}
                                    value={form.data.description}
                                    onChange={(event) => form.setData("description", event.target.value)}
                                    placeholder="Optional summary or catalog notes"
                                    className="rounded-xl"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-6">
                        <Card className="rounded-2xl shadow-xs">
                            <CardHeader className="bg-muted/25 border-b pb-4">
                                <div className="flex items-start gap-3">
                                    <span className="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold">
                                        2
                                    </span>
                                    <div>
                                        <CardTitle>Inventory and cover</CardTitle>
                                        <CardDescription className="text-pretty">
                                            Set ready-to-use copy defaults and shelf information.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="total_copies">Total Copies</Label>
                                    <Input
                                        id="total_copies"
                                        type="number"
                                        value={form.data.total_copies}
                                        onChange={(event) => handleTotalCopiesChange(event.target.value)}
                                        className="h-10 rounded-xl tabular-nums"
                                        aria-invalid={Boolean(bookError("total_copies"))}
                                    />
                                    {bookError("total_copies") && <p className="text-destructive text-xs">{bookError("total_copies")}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="available_copies">Available Copies</Label>
                                    <Input
                                        id="available_copies"
                                        type="number"
                                        value={form.data.available_copies}
                                        onChange={(event) => form.setData("available_copies", event.target.value)}
                                        className="h-10 rounded-xl tabular-nums"
                                        aria-invalid={Boolean(bookError("available_copies"))}
                                    />
                                    {bookError("available_copies") && <p className="text-destructive text-xs">{bookError("available_copies")}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="location">Shelf Location</Label>
                                    <Input
                                        id="location"
                                        value={form.data.location}
                                        onChange={(event) => form.setData("location", event.target.value)}
                                        placeholder="e.g. Main Library · A-12"
                                        className="h-10 rounded-xl"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="status">Status</Label>
                                    <Select value={form.data.status} onValueChange={(value) => form.setData("status", value)}>
                                        <SelectTrigger id="status" className="h-10 rounded-xl" aria-invalid={Boolean(bookError("status"))}>
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
                                    {bookError("status") && <p className="text-destructive text-xs">{bookError("status")}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="cover_image">Cover Image URL</Label>
                                    <Input
                                        id="cover_image"
                                        value={form.data.cover_image}
                                        onChange={(event) => form.setData("cover_image", event.target.value)}
                                        placeholder="https://…"
                                        className="h-10 rounded-xl"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="cover_image_upload">Cover Image Upload</Label>
                                    <Input
                                        id="cover_image_upload"
                                        type="file"
                                        accept="image/*"
                                        className="h-10 rounded-xl"
                                        aria-invalid={Boolean(bookError("cover_image_upload"))}
                                        onChange={(event) => {
                                            const file = event.target.files?.[0] || null;
                                            form.setData("cover_image_upload", file);
                                            setCoverUploadPreview(file ? URL.createObjectURL(file) : null);
                                        }}
                                    />
                                    {bookError("cover_image_upload") && <p className="text-destructive text-xs">{bookError("cover_image_upload")}</p>}
                                </div>
                                {coverPreview && (
                                    <div className="bg-muted/40 overflow-hidden rounded-xl p-2">
                                        <img
                                            src={coverPreview}
                                            alt="Book cover preview"
                                            className="h-40 w-full rounded-lg object-cover outline -outline-offset-1 outline-black/10 dark:outline-white/10"
                                        />
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="rounded-2xl shadow-xs">
                            <CardHeader>
                                <CardTitle>Fast-entry checklist</CardTitle>
                                <CardDescription>Small choices that keep the catalog easy to search.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                {[
                                    "Reuse an existing ISBN or call number when the catalog already has it.",
                                    "Quick-add missing authors and categories without abandoning this form.",
                                    "Add a shelf location so staff can retrieve the book immediately.",
                                ].map((tip) => (
                                    <div key={tip} className="text-muted-foreground flex items-start gap-2.5">
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        <p className="text-pretty">{tip}</p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>

            {book && <DigitalEditionSection book={book} rightsBases={options.digital_rights_bases} />}
        </AdminLayout>
    );
}

interface DigitalEditionFormData {
    pdf: File | null;
    status: "draft" | "published";
    downloads_allowed: boolean;
    rights_basis: string;
    rights_holder: string;
    license_url: string;
    rights_notes: string;
    rights_expires_at: string;
    rights_confirmed: boolean;
}

function DigitalEditionSection({ book, rightsBases }: { book: BookRecord; rightsBases: CatalogOption[] }) {
    const edition = book.digital_edition;
    const form = useForm<DigitalEditionFormData>({
        pdf: null,
        status: edition?.status ?? "draft",
        downloads_allowed: edition?.downloads_allowed ?? false,
        rights_basis: edition?.rights_basis ?? "",
        rights_holder: edition?.rights_holder ?? "",
        license_url: edition?.license_url ?? "",
        rights_notes: edition?.rights_notes ?? "",
        rights_expires_at: edition?.rights_expires_at ?? "",
        rights_confirmed: false,
    });

    if (!book.can_manage_digital_edition) {
        return edition ? (
            <Card>
                <CardHeader>
                    <CardTitle>Digital Edition</CardTitle>
                    <CardDescription>A digital edition exists, but your account cannot change its publication settings.</CardDescription>
                </CardHeader>
            </Card>
        ) : null;
    }

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (form.data.pdf) {
            form.transform((data) => ({
                ...data,
                status: "draft",
                downloads_allowed: false,
                rights_confirmed: false,
            }));
            form.post(storeDigitalEdition.url(book.id), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => form.setData("pdf", null),
            });
            return;
        }

        if (edition) {
            form.put(updateDigitalEdition.url(book.id), {
                preserveScroll: true,
            });
        }
    };

    const removeEdition = () => {
        if (!window.confirm(`Remove the digital edition of “${book.title}”? This cannot be undone.`)) return;

        router.delete(destroyDigitalEdition.url(book.id), {
            preserveScroll: true,
        });
    };

    const publishing = form.data.status === "published" && !form.data.pdf;

    return (
        <form onSubmit={handleSubmit} className="mt-6">
            <Card className="overflow-hidden border-amber-700/20">
                <CardHeader className="border-b bg-amber-50/70 dark:bg-amber-950/10">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="flex gap-3">
                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-700/10 text-amber-700 dark:text-amber-300">
                                <BookOpen className="size-5" />
                            </div>
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <CardTitle>Digital Edition</CardTitle>
                                    <Badge variant={edition?.status === "published" ? "default" : "secondary"}>
                                        {edition?.status === "published" ? "Published" : edition ? "Draft" : "Not uploaded"}
                                    </Badge>
                                </div>
                                <CardDescription className="mt-1">
                                    Upload a rights-cleared PDF for authenticated online reading. New files default to draft and downloads stay off
                                    unless explicitly allowed.
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {edition?.status === "published" && (
                                <Button type="button" variant="outline" asChild>
                                    <Link href={readDigitalBook.url(book.id)}>
                                        <BookOpen className="size-4" />
                                        Preview reader
                                    </Link>
                                </Button>
                            )}
                            {edition && (
                                <Button type="button" variant="destructive" onClick={removeEdition}>
                                    <Trash2 className="size-4" />
                                    Remove edition
                                </Button>
                            )}
                        </div>
                    </div>
                </CardHeader>

                <CardContent className="grid gap-7 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.85fr)] lg:p-6">
                    <div className="space-y-6">
                        <div className="space-y-3">
                            <Label htmlFor="digital_pdf">{edition ? "Replace PDF" : "PDF file"}</Label>
                            <label
                                htmlFor="digital_pdf"
                                className="border-border hover:border-primary/50 hover:bg-muted/30 flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border border-dashed p-8 text-center transition-colors"
                            >
                                <div className="bg-primary/10 text-primary flex size-12 items-center justify-center rounded-2xl">
                                    <UploadCloud className="size-6" />
                                </div>
                                <div>
                                    <p className="font-medium">
                                        {form.data.pdf ? form.data.pdf.name : edition ? "Choose a replacement PDF" : "Choose a PDF to upload"}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs">PDF only, up to 90 MB. Files are stored privately.</p>
                                </div>
                            </label>
                            <Input
                                id="digital_pdf"
                                type="file"
                                accept=".pdf,application/pdf"
                                className="sr-only"
                                onChange={(event) => form.setData("pdf", event.target.files?.[0] ?? null)}
                            />
                            {form.errors.pdf && <p className="text-destructive text-sm">{form.errors.pdf}</p>}
                        </div>

                        {edition && (
                            <div className="bg-muted/35 grid gap-3 rounded-2xl border p-4 sm:grid-cols-3">
                                <FileMetric icon={FileText} label="Current file" value={edition.original_name} />
                                <FileMetric icon={Download} label="File size" value={formatBytes(edition.size_bytes)} />
                                <FileMetric
                                    icon={FileCheck2}
                                    label="Uploaded"
                                    value={edition.uploaded_at ? new Date(edition.uploaded_at).toLocaleDateString() : "Unknown"}
                                />
                            </div>
                        )}

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Publication status</Label>
                                <Select
                                    disabled={Boolean(form.data.pdf) || !edition}
                                    value={form.data.pdf || !edition ? "draft" : form.data.status}
                                    onValueChange={(value) => form.setData("status", value as "draft" | "published")}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="draft">Draft — staff only</SelectItem>
                                        <SelectItem value="published">Published — authenticated users</SelectItem>
                                    </SelectContent>
                                </Select>
                                {(form.data.pdf || !edition) && (
                                    <p className="text-muted-foreground text-xs leading-5">
                                        Every new or replacement file is saved as a draft. Publish it in a separate reviewed step after the upload
                                        completes.
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Rights basis</Label>
                                <Select value={form.data.rights_basis || undefined} onValueChange={(value) => form.setData("rights_basis", value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select documented rights" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {rightsBases.map((basis) => (
                                            <SelectItem key={basis.value} value={String(basis.value)}>
                                                {basis.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.rights_basis && <p className="text-destructive text-sm">{form.errors.rights_basis}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="rights_holder">Rights holder</Label>
                                <Input
                                    id="rights_holder"
                                    value={form.data.rights_holder}
                                    onChange={(event) => form.setData("rights_holder", event.target.value)}
                                    placeholder="Author, publisher, or KoAkademy"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="license_url">License or permission URL</Label>
                                <Input
                                    id="license_url"
                                    type="url"
                                    value={form.data.license_url}
                                    onChange={(event) => form.setData("license_url", event.target.value)}
                                    placeholder="https://…"
                                />
                                {form.errors.license_url && <p className="text-destructive text-sm">{form.errors.license_url}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="rights_expires_at">Rights expiration</Label>
                                <Input
                                    id="rights_expires_at"
                                    type="date"
                                    value={form.data.rights_expires_at}
                                    onChange={(event) => form.setData("rights_expires_at", event.target.value)}
                                />
                                <p className="text-muted-foreground text-xs">Leave blank when rights do not expire.</p>
                            </div>

                            <div className="flex items-start gap-3 rounded-xl border p-4">
                                <Checkbox
                                    id="downloads_allowed"
                                    checked={form.data.downloads_allowed}
                                    onCheckedChange={(checked) => form.setData("downloads_allowed", checked === true)}
                                />
                                <div>
                                    <Label htmlFor="downloads_allowed">Allow PDF downloads</Label>
                                    <p className="text-muted-foreground mt-1 text-xs leading-5">
                                        Keep disabled unless the documented rights explicitly permit downloadable copies.
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="rights_notes">Rights and review notes</Label>
                                <Textarea
                                    id="rights_notes"
                                    rows={4}
                                    value={form.data.rights_notes}
                                    onChange={(event) => form.setData("rights_notes", event.target.value)}
                                    placeholder="Record the permission source, license limits, librarian review, or takedown considerations."
                                />
                            </div>
                        </div>
                    </div>

                    <div className="space-y-5">
                        <div className="rounded-2xl border border-amber-700/20 bg-amber-50/70 p-5 dark:bg-amber-950/10">
                            <div className="flex items-center gap-2 text-amber-900 dark:text-amber-100">
                                <ShieldCheck className="size-5" />
                                <h3 className="font-serif text-lg font-semibold">Publication attestation</h3>
                            </div>
                            <div className="text-muted-foreground mt-3 space-y-3 text-sm leading-6">
                                <p>Only publish files that KoAkademy owns or is authorized to reproduce and communicate digitally.</p>
                                <p>
                                    Physical ownership, educational purpose, or disabling the download button does not itself grant digital
                                    distribution rights.
                                </p>
                            </div>
                            {publishing && (
                                <div className="mt-5 flex items-start gap-3 rounded-xl border border-amber-800/20 bg-white/60 p-4 dark:bg-black/20">
                                    <Checkbox
                                        id="rights_confirmed"
                                        checked={form.data.rights_confirmed}
                                        onCheckedChange={(checked) => form.setData("rights_confirmed", checked === true)}
                                    />
                                    <Label htmlFor="rights_confirmed" className="text-sm leading-6">
                                        I reviewed the documentation and confirm KoAkademy has the right to provide this PDF to authenticated users
                                        under the selected terms.
                                    </Label>
                                </div>
                            )}
                            {form.errors.rights_confirmed && <p className="text-destructive mt-2 text-sm">{form.errors.rights_confirmed}</p>}
                        </div>

                        <Button type="submit" className="w-full gap-2" size="lg" disabled={form.processing || (!edition && !form.data.pdf)}>
                            <Save className="size-4" />
                            {form.processing
                                ? "Saving digital edition…"
                                : form.data.pdf
                                  ? edition
                                      ? "Replace PDF as draft"
                                      : "Upload PDF as draft"
                                  : "Save publication settings"}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>
    );
}

function FileMetric({ icon: Icon, label, value }: { icon: typeof FileText; label: string; value: string }) {
    return (
        <div className="flex min-w-0 gap-2">
            <Icon className="text-muted-foreground mt-0.5 size-4 shrink-0" />
            <div className="min-w-0">
                <p className="text-muted-foreground text-[10px] font-semibold tracking-[0.12em] uppercase">{label}</p>
                <p className="mt-1 truncate text-sm font-medium" title={value}>
                    {value}
                </p>
            </div>
        </div>
    );
}

function formatBytes(bytes: number): string {
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
