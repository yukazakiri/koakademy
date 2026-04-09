import AdminLayout from "@/components/administrators/admin-layout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import type { User } from "@/types/user";
import { Head } from "@inertiajs/react";
import { columns, type DocumentStudent } from "./columns";
import { DataTable } from "./data-table";

export default function DocumentList({
    auth,
    students,
    filters,
}: {
    auth: { user: User };
    students: {
        data: DocumentStudent[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
        from: number;
        to: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search?: string };
}) {
    return (
        <AdminLayout user={auth.user}>
            <Head title="Student Documents" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Student Documents</h1>
                        <p className="text-muted-foreground">Manage admission and requirement documents across all students.</p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Students</CardTitle>
                        <CardDescription>Select a student to manage their documents.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={columns}
                            data={students.data}
                            pagination={{
                                current_page: students.current_page,
                                last_page: students.last_page,
                                per_page: students.per_page,
                                total: students.total,
                                next_page_url: students.next_page_url,
                                prev_page_url: students.prev_page_url,
                                from: students.from,
                                to: students.to,
                            }}
                            filters={filters}
                            routeName="administrators.students.documents.list"
                            searchPlaceholder="Search by name or ID..."
                        />
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
