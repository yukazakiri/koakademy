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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useOnlinePresence } from "@/contexts/online-presence-context";
import type { User } from "@/types/user";
import { Head, Link, router, usePoll } from "@inertiajs/react";
import { ChevronDown, KeyRound, Plus, ShieldCheck, Trash2, UserCog } from "lucide-react";
import { useMemo, useState } from "react";
import { AnalyticsData, UserAnalytics } from "./analytics";
import { createColumns, ExtendedUser } from "./columns";
import { DataTable } from "./data-table";
import { OnlineUsersWidget } from "./online-users-widget";

// Declare route globally to avoid TS errors
declare const route: (name: string, ...parameters: unknown[]) => string;

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
    user: User;
}

type ActionType = "delete" | "impersonate" | "verify" | "reset_password";

export default function UserIndex({ users, analytics, online_user_ids, options, user }: PageProps) {
    const presence = useOnlinePresence();
    const liveOnlineUserIds = presence.isReady ? presence.onlineUserIds : online_user_ids;
    const liveAnalytics = useMemo(
        () => ({
            ...analytics,
            online_users: liveOnlineUserIds.length,
            online_rate: analytics.total_users > 0 ? Number(((liveOnlineUserIds.length / analytics.total_users) * 100).toFixed(1)) : 0,
        }),
        [analytics, liveOnlineUserIds],
    );

    usePoll(30000, {
        only: ["analytics", "online_user_ids"],
    });

    const [actionState, setActionState] = useState<{
        type: ActionType | null;
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

    const columns = createColumns({ onAction: openDialog, onlineUserIds: liveOnlineUserIds });

    return (
        <AdminLayout user={user} title="User Management">
            <Head title="Administrators • Users" />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-5">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-muted-foreground text-xs font-semibold tracking-[0.16em] uppercase">Administration / Access</p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">User management</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm">Review accounts, access levels, and organization assignments.</p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href={route("administrators.users.create")}>
                            <Plus aria-hidden="true" />
                            Add user
                        </Link>
                    </Button>
                </header>

                <UserAnalytics stats={liveAnalytics} />

                <section className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
                    <Card className="min-w-0">
                        <CardHeader className="border-b py-4">
                            <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle className="text-base">All users</CardTitle>
                                    <p className="text-muted-foreground mt-1 text-sm">Search and filter the current account directory.</p>
                                </div>
                                <span className="text-muted-foreground text-sm tabular-nums">
                                    {users.total.toLocaleString("en-US")} {users.total === 1 ? "account" : "accounts"}
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <DataTable columns={columns} data={users.data} options={options} />
                        </CardContent>
                    </Card>

                    <OnlineUsersWidget users={users.data} onlineUserIds={liveOnlineUserIds} />
                </section>

                <details className="group bg-card rounded-xl border shadow-xs">
                    <summary className="hover:bg-muted/40 focus-visible:ring-ring/45 flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 transition-colors outline-none focus-visible:ring-2 focus-visible:ring-inset [&::-webkit-details-marker]:hidden">
                        <div>
                            <h2 className="font-medium">Account analytics</h2>
                            <p className="text-muted-foreground mt-1 text-sm">Registration, verification, and role coverage.</p>
                        </div>
                        <ChevronDown
                            aria-hidden="true"
                            className="text-muted-foreground size-5 shrink-0 transition-transform group-open:rotate-180"
                        />
                    </summary>
                    <div className="border-t p-4">
                        <UserAnalytics stats={liveAnalytics} detailed />
                    </div>
                </details>

                {/* Action confirmation dialog */}
                <AlertDialog open={!!actionState.type} onOpenChange={closeDialog}>
                    <AlertDialogContent className="sm:max-w-md">
                        <AlertDialogHeader>
                            <AlertDialogTitle className="flex items-center gap-2">
                                {actionState.type === "delete" && <Trash2 aria-hidden="true" className="text-destructive size-5" />}
                                {actionState.type === "impersonate" && <UserCog aria-hidden="true" className="size-5 text-amber-600" />}
                                {actionState.type === "verify" && <ShieldCheck aria-hidden="true" className="size-5 text-emerald-600" />}
                                {actionState.type === "reset_password" && <KeyRound aria-hidden="true" className="size-5 text-blue-600" />}

                                {actionState.type === "delete" && "Delete user"}
                                {actionState.type === "impersonate" && "Impersonate user"}
                                {actionState.type === "verify" && "Verify email"}
                                {actionState.type === "reset_password" && "Reset password"}
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                {actionState.type === "delete" && (
                                    <>
                                        Delete <span className="text-foreground font-medium">{actionState.userName}</span>? This removes the account
                                        and its associated data.
                                    </>
                                )}
                                {actionState.type === "impersonate" && (
                                    <>
                                        Start a session as <span className="text-foreground font-medium">{actionState.userName}</span>? You will have
                                        full access to the user&apos;s account.
                                    </>
                                )}
                                {actionState.type === "verify" && (
                                    <>
                                        Mark <span className="text-foreground font-medium">{actionState.userName}</span>&apos;s email address as
                                        verified?
                                    </>
                                )}
                                {actionState.type === "reset_password" && (
                                    <>
                                        Send a password reset link to <span className="text-foreground font-medium">{actionState.userName}</span>?
                                    </>
                                )}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={(event) => {
                                    event.preventDefault();
                                    confirmAction();
                                }}
                                className={actionState.type === "delete" ? "bg-destructive text-destructive-foreground hover:bg-destructive/90" : ""}
                            >
                                {actionState.type === "delete" && "Delete user"}
                                {actionState.type === "impersonate" && "Start session"}
                                {actionState.type === "verify" && "Verify email"}
                                {actionState.type === "reset_password" && "Send link"}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AdminLayout>
    );
}
