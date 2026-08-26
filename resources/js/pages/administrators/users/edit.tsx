import AdminLayout from "@/components/administrators/admin-layout";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import type { FormEventHandler } from "react";
import { UserForm, type UserFormData } from "./user-form";

declare const route: (name: string, ...parameters: unknown[]) => string;

interface ExtendedUser {
    id: number;
    name: string;
    email: string;
    role: string;
    school_id: number | null;
    department_id: number | null;
    faculty_id_number: string | null;
    record_id: string | null;
    roles: { id: number; name: string }[];
}

interface PageProps {
    user: ExtendedUser;
    roles: Record<string, string>;
    schools: { id: number; name: string }[];
    departments: { id: number; name: string; school_id: number }[];
    permissions: { id: number; name: string }[];
    auth_user: User;
}

export default function UserEdit({ user, roles, schools, departments, permissions, auth_user }: PageProps) {
    const { data, setData, put, processing, errors, isDirty } = useForm<UserFormData>({
        name: user.name || "",
        email: user.email || "",
        password: "",
        password_confirmation: "",
        role: user.role || "",
        school_id: user.school_id?.toString() || "",
        department_id: user.department_id?.toString() || "",
        faculty_id_number: user.faculty_id_number || "",
        record_id: user.record_id || "",
        roles: user.roles.map((role) => role.id),
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        put(route("administrators.users.update", user.id));
    };

    return (
        <AdminLayout user={auth_user} title="Edit User">
            <Head title={`Edit User • ${user.name}`} />

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
                        <p className="text-muted-foreground text-xs font-semibold tracking-[0.16em] uppercase">User management / Account</p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Edit {user.name}</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm">
                            Update the account without losing sight of its access or organization.
                        </p>
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
                    mode="edit"
                    processing={processing}
                    isDirty={isDirty}
                    onSubmit={submit}
                />
            </main>
        </AdminLayout>
    );
}
