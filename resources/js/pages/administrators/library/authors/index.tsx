import AdminLayout from "@/components/administrators/admin-layout";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { Feather, Plus, Search, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";

declare const route: any;

interface AuthorItem {
    id: number;
    name: string;
    nationality?: string | null;
    birth_date?: string | null;
    books_count: number;
}

interface Props {
    user: User;
    authors: AuthorItem[];
    filters: {
        search?: string | null;
    };
    flash?: {
        type: string;
        message: string;
    };
}

export default function LibraryAuthorsIndex({ user, authors, filters, flash }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [deleteTarget, setDeleteTarget] = useState<AuthorItem | null>(null);

    useEffect(() => {
        if (!flash?.message) return;
        if (flash.type === "success") {
            toast.success(flash.message);
        } else if (flash.type === "error") {
            toast.error(flash.message);
        } else {
            toast.message(flash.message);
        }
    }, [flash]);

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(route("administrators.library.authors.index"), { search: term || null }, { preserveState: true, replace: true });
    }, 300);

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route("administrators.library.authors.destroy", deleteTarget.id), {
            preserveState: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout user={user} title="Library Authors">
            <Head title="Administrators • Library Authors" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-sky-500/10 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-1">
                            <div className="flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600">
                                    <Feather className="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle>Author Directory</CardTitle>
                                    <CardDescription>Maintain author details for catalog searches.</CardDescription>
                                </div>
                            </div>
                        </div>
                        <Button asChild className="gap-2">
                            <Link href={route("administrators.library.authors.create")}>
                                <Plus className="h-4 w-4" />
                                Add Author
                            </Link>
                        </Button>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Author List</CardTitle>
                            <CardDescription>Search authors by name or nationality.</CardDescription>
                        </div>
                        <div className="relative w-full md:w-72">
                            <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                            <Input
                                placeholder="Search authors..."
                                value={search}
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setSearch(value);
                                    handleSearch(value);
                                }}
                                className="pl-9"
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Author</TableHead>
                                    <TableHead>Nationality</TableHead>
                                    <TableHead>Birth Date</TableHead>
                                    <TableHead>Books</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {authors.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-muted-foreground h-24 text-center text-sm">
                                            No authors found.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    authors.map((author) => (
                                        <TableRow key={author.id}>
                                            <TableCell className="text-foreground font-medium">{author.name}</TableCell>
                                            <TableCell className="text-muted-foreground text-sm">{author.nationality ?? "—"}</TableCell>
                                            <TableCell className="text-muted-foreground text-sm">{author.birth_date ?? "—"}</TableCell>
                                            <TableCell>
                                                <Badge variant="secondary" className="text-xs">
                                                    {author.books_count}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={route("administrators.library.authors.edit", author.id)}>Edit</Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() => setDeleteTarget(author)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete author?</AlertDialogTitle>
                        <AlertDialogDescription>Removing "{deleteTarget?.name}" will also hide associated books.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmDelete}>Delete</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
