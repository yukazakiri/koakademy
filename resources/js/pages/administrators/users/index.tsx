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
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Head, Link, router } from "@inertiajs/react";
import { KeyRound, Plus, ShieldCheck, Trash2, UserCog } from "lucide-react";
import { useState } from "react";
import { AnalyticsData, UserAnalytics } from "./analytics";
import { createColumns, ExtendedUser } from "./columns";
import { DataTable } from "./data-table";
import { OnlineUsersWidget } from "./online-users-widget";

// Declare route globally to avoid TS errors
declare const route: any;

interface PageProps {
    users: {
        data: ExtendedUser[];
        total: number;
    };
    analytics: AnalyticsData;
    online_user_ids: number[];
    filters: {
        search?: string;
        role?: string;
        school_id?: string;
        department_id?: string;
        email_verified?: string;
        trashed?: string;
    };
    options: {
        roles: string[];
        schools: { id: number; name: string }[];
        departments: { id: number; name: string }[];
    };
    flash?: {
        type: string;
        message: string;
    };
    user: any;
}

type ActionType = "delete" | "impersonate" | "verify" | "reset_password" | null;

export default function UserIndex({ users, analytics, online_user_ids, filters, options, user }: PageProps) {
    const [actionState, setActionState] = useState<{
        type: ActionType;
        userId: number | null;
        userName: string | null;
    }>({ type: null, userId: null, userName: null });

    const confirmAction = () => {
        const { type, userId } = actionState;
        if (!userId || !type) return;

        switch (type) {
            case "delete":
                router.delete(route("administrators.users.destroy", userId), {
                    onFinish: () => closeDialog(),
                });
                break;
            case "impersonate":
                router.post(
                    route("administrators.users.impersonate", userId),
                    {},
                    {
                        onFinish: () => closeDialog(),
                    },
                );
                break;
            case "verify":
                router.put(
                    route("administrators.users.verify-email", userId),
                    {},
                    {
                        onFinish: () => closeDialog(),
                    },
                );
                break;
            case "reset_password":
                router.post(
                    route("administrators.users.reset-password", userId),
                    {},
                    {
                        onFinish: () => closeDialog(),
                    },
                );
                break;
        }
    };

    const openDialog = (type: ActionType, userId: number, userName: string) => {
        setActionState({ type, userId, userName });
    };

    const closeDialog = () => {
        setActionState({ type: null, userId: null, userName: null });
    };

    const columns = createColumns({ onAction: openDialog, onlineUserIds: online_user_ids });

    return (
        <AdminLayout user={user} title="User Management">
            <Head title="Administrators • Users" />

            <div className="flex flex-col gap-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Users</h2>
                        <p className="text-muted-foreground">Manage system users, roles, and permissions.</p>
                    </div>
                    <Button asChild>
                        <Link href={route("administrators.users.create")}>
                            <Plus className="mr-2 h-4 w-4" /> Add User
                        </Link>
                    </Button>
                </div>

                <UserAnalytics stats={analytics} />

                <OnlineUsersWidget users={users.data} onlineUserIds={online_user_ids} />

                <Card>
                    <CardHeader>
                        <CardTitle>All Users</CardTitle>
                        <CardDescription>
                            A comprehensive list of all users in the system. Use the search and filters to find specific users.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable columns={columns} data={users.data} options={options} />
                    </CardContent>
                </Card>

                {/* Action Confirmation Dialogs */}
                <AlertDialog open={!!actionState.type} onOpenChange={closeDialog}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle className="flex items-center gap-2">
                                {actionState.type === "delete" && <Trash2 className="h-5 w-5 text-red-500" />}
                                {actionState.type === "impersonate" && <UserCog className="h-5 w-5 text-amber-500" />}
                                {actionState.type === "verify" && <ShieldCheck className="h-5 w-5 text-emerald-500" />}
                                {actionState.type === "reset_password" && <KeyRound className="h-5 w-5 text-blue-500" />}

                                {actionState.type === "delete" && "Delete User"}
                                {actionState.type === "impersonate" && "Impersonate User"}
                                {actionState.type === "verify" && "Verify Email"}
                                {actionState.type === "reset_password" && "Reset Password"}
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                {actionState.type === "delete" && (
                                    <>
                                        Are you sure you want to delete <span className="text-foreground font-semibold">{actionState.userName}</span>?
                                        This action cannot be undone and will remove all associated data.
                                    </>
                                )}
                                {actionState.type === "impersonate" && (
                                    <>
                                        You are about to log in as <span className="text-foreground font-semibold">{actionState.userName}</span>. You
                                        will have full access to their account. To return to your account, use the "Stop Impersonating" button in the
                                        top bar.
                                    </>
                                )}
                                {actionState.type === "verify" && (
                                    <>
                                        Manually verify the email address for{" "}
                                        <span className="text-foreground font-semibold">{actionState.userName}</span>? This will allow them to access
                                        features requiring email verification.
                                    </>
                                )}
                                {actionState.type === "reset_password" && (
                                    <>
                                        Send a password reset link to <span className="text-foreground font-semibold">{actionState.userName}</span>?
                                        They will receive an email with instructions to set a new password.
                                    </>
                                )}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={(e) => {
                                    e.preventDefault();
                                    confirmAction();
                                }}
                                className={
                                    actionState.type === "delete"
                                        ? "bg-red-600 hover:bg-red-700"
                                        : actionState.type === "impersonate"
                                          ? "bg-amber-600 hover:bg-amber-700"
                                          : ""
                                }
                            >
                                {actionState.type === "delete" && "Delete User"}
                                {actionState.type === "impersonate" && "Start Impersonation"}
                                {actionState.type === "verify" && "Confirm Verification"}
                                {actionState.type === "reset_password" && "Send Reset Link"}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AdminLayout>
    );
}
