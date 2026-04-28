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
import { FolderOpen, Plus, Search, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";

declare const route: any;

interface CategoryItem {
    id: number;
    name: string;
    description?: string | null;
    color: string;
    books_count: number;
}

interface Props {
    user: User;
    categories: CategoryItem[];
    filters: {
        search?: string | null;
    };
    flash?: {
        type: string;
        message: string;
    };
}

export default function LibraryCategoriesIndex({ user, categories, filters, flash }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [deleteTarget, setDeleteTarget] = useState<CategoryItem | null>(null);

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
        router.get(route("administrators.library.categories.index"), { search: term || null }, { preserveState: true, replace: true });
    }, 300);

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route("administrators.library.categories.destroy", deleteTarget.id), {
            preserveState: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AdminLayout user={user} title="Library Categories">
            <Head title="Administrators • Library Categories" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-violet-500/10 to-amber-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600">
                                <FolderOpen className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle>Category Collection</CardTitle>
                                <CardDescription>Organize titles by genre or subject.</CardDescription>
                            </div>
                        </div>
                        <Button asChild className="gap-2">
                            <Link href={route("administrators.library.categories.create")}>
                                <Plus className="h-4 w-4" />
                                Add Category
                            </Link>
                        </Button>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Category List</CardTitle>
                            <CardDescription>Search and maintain catalog tags.</CardDescription>
                        </div>
                        <div className="relative w-full md:w-72">
                            <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                            <Input
                                placeholder="Search categories..."
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
                                    <TableHead>Category</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Books</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {categories.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={4} className="text-muted-foreground h-24 text-center text-sm">
                                            No categories found.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    categories.map((category) => (
                                        <TableRow key={category.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: category.color }} />
                                                    <span className="text-foreground font-medium">{category.name}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">{category.description ?? "—"}</TableCell>
                                            <TableCell>
                                                <Badge variant="secondary" className="text-xs">
                                                    {category.books_count}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={route("administrators.library.categories.edit", category.id)}>Edit</Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() => setDeleteTarget(category)}
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
                        <AlertDialogTitle>Delete category?</AlertDialogTitle>
                        <AlertDialogDescription>This will remove "{deleteTarget?.name}" from the catalog.</AlertDialogDescription>
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
