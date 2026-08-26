import AdminLayout from "@/components/administrators/admin-layout";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import type { FormEventHandler } from "react";
import { UserForm, type UserFormData } from "./user-form";

declare const route: (name: string, ...parameters: unknown[]) => string;

interface PageProps {
    roles: Record<string, string>;
    schools: { id: number; name: string }[];
    departments: { id: number; name: string; school_id: number }[];
    permissions: { id: number; name: string }[];
    user: User;
}

export default function UserCreate({ roles, schools, departments, permissions, user }: PageProps) {
    const { data, setData, post, processing, errors } = useForm<UserFormData>({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        role: "",
        school_id: "",
        department_id: "",
        faculty_id_number: "",
        record_id: "",
        roles: [],
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route("administrators.users.store"));
    };

    return (
        <AdminLayout user={user} title="Create User">
            <Head title="Create User" />

            <main className="mx-auto flex w-full max-w-7xl flex-col gap-5">
                <header className="space-y-3">
                    <Link
                        href={route("administrators.users.index")}
                        className="text-muted-foreground hover:text-foreground focus-visible:ring-ring/45 inline-flex min-h-11 items-center gap-2 rounded-md text-sm transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <ArrowLeft aria-hidden="true" className="size-4" />
                        Users
                    </Link>
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-xs font-semibold tracking-[0.16em] uppercase">User management / New account</p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Create user</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm">Set up identity, organization, and access in one focused form.</p>
                    </div>
                </header>

                <UserForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    roles={roles}
                    schools={schools}
                    departments={departments}
                    permissions={permissions}
                    mode="create"
                    processing={processing}
                    onSubmit={submit}
                />
            </main>
        </AdminLayout>
    );
}
