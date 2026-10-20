"use client";

import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/reui/badge";
import { IconTile } from "@/components/reui/icon-tile";
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useOnlinePresence } from "@/contexts/online-presence-context";
import { create, destroy, impersonate, resetPassword, index as usersIndex, verifyEmail } from "@/routes/administrators/users";
import { update as updateAccess } from "@/routes/administrators/users/access";
import { destroy as revokeSessions } from "@/routes/administrators/users/sessions";
import type { User } from "@/types/user";
import { Head, Link, router, usePoll } from "@inertiajs/react";
import { Archive, ChevronDown, CirclePause, Clock3, Plus, ShieldCheck, Users } from "lucide-react";
import { useMemo, useState } from "react";
import { AnalyticsData, UserAnalytics } from "./analytics";
import { UserDirectoryGrid, type DirectoryUser } from "./user-directory-grid";

export type ActionType = "delete" | "impersonate" | "verify" | "reset_password" | "suspend" | "reactivate" | "revoke";

type Queue = "all" | "active" | "suspended" | "unverified" | "security" | "recent" | "deleted";

interface PageProps {
    can_create: boolean;
    users: { data: DirectoryUser[]; total: number };
    analytics: AnalyticsData;
    online_user_ids: number[];
    options: { roles: string[]; schools: { id: number; name: string }[]; departments: { id: number; name: string }[] };
    filters: { trashed?: string };
    user: User;
}

const QUEUES: { key: Queue; label: string; hint: string; icon: typeof Users; tone: string }[] = [
    { key: "all", label: "All accounts", hint: "Every account in the directory", icon: Users, tone: "text-primary" },
    { key: "recent", label: "Recently online", hint: "Observed presence in the last 15 minutes", icon: Clock3, tone: "text-emerald-600" },
    { key: "security", label: "MFA needs review", hint: "Challenges off or no method configured", icon: ShieldCheck, tone: "text-amber-600" },
    { key: "suspended", label: "Suspended", hint: "Sign in blocked until reactivated", icon: CirclePause, tone: "text-rose-600" },
    { key: "deleted", label: "Deleted accounts", hint: "Archived accounts, kept for records", icon: Archive, tone: "text-muted-foreground" },
];

const QUICK_VIEWS: { key: Queue; label: string }[] = [
    { key: "active", label: "Active" },
    { key: "unverified", label: "Unverified email" },
];

export default function UserIndex({ users, analytics, online_user_ids, options, filters, user, can_create }: PageProps) {
    const presence = useOnlinePresence();
    const liveOnlineUserIds = presence.isReady ? presence.onlineUserIds : online_user_ids;
    const [queue, setQueue] = useState<Queue>("all");

    const liveAnalytics = useMemo(
        () => ({
            ...analytics,
            online_users: liveOnlineUserIds.length,
            online_rate: analytics.total_users > 0 ? Number(((liveOnlineUserIds.length / analytics.total_users) * 100).toFixed(1)) : 0,
        }),
        [analytics, liveOnlineUserIds],
    );

    usePoll(30000, { only: ["users", "analytics", "online_user_ids"] });

    const [actionState, setActionState] = useState<{ type: ActionType | null; userId: number | null; userName: string | null }>({
        type: null,
        userId: null,
        userName: null,
    });
    const [reason, setReason] = useState("");
    const [processing, setProcessing] = useState(false);
    const [actionError, setActionError] = useState("");

    const counts = useMemo(
        () => ({
            all: users.data.length,
            active: users.data.filter((account) => !account.suspended_at).length,
            suspended: users.data.filter((account) => !!account.suspended_at).length,
            unverified: users.data.filter((account) => !account.email_verified_at).length,
            security: users.data.filter((account) => !account.mfa_configured || !account.security_two_factor_enabled).length,
            recent: users.data.filter((account) => liveOnlineUserIds.includes(account.id) && !account.suspended_at).length,
            deleted: 0,
        }),
        [users.data, liveOnlineUserIds],
    );

    const viewingDeleted = filters.trashed === "only";

    const visible = useMemo(() => {
        if (queue === "active") return users.data.filter((account) => !account.suspended_at);
        if (queue === "suspended") return users.data.filter((account) => !!account.suspended_at);
        if (queue === "unverified") return users.data.filter((account) => !account.email_verified_at);
        if (queue === "security") return users.data.filter((account) => !account.mfa_configured || !account.security_two_factor_enabled);
        if (queue === "recent") return users.data.filter((account) => liveOnlineUserIds.includes(account.id) && !account.suspended_at);
        return users.data;
    }, [queue, users.data, liveOnlineUserIds]);

    const closeDialog = () => {
        if (processing) return;
        setActionState({ type: null, userId: null, userName: null });
        setActionError("");
        setReason("");
    };

    const openDialog = (type: ActionType, userId: number, userName: string) => {
        setReason("");
        setActionError("");
        setActionState({ type, userId, userName });
    };

    const confirmAction = () => {
        const { type, userId } = actionState;
        if (!userId || !type || processing) return;
        setProcessing(true);
        setActionError("");
        const options = {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
            onError: (errors: Record<string, string>) => setActionError(Object.values(errors).join(" ")),
            onFinish: () => setProcessing(false),
        };
        if (type === "delete") router.delete(destroy.url(userId), options);
        if (type === "impersonate") router.post(impersonate.url(userId), {}, options);
        if (type === "verify") router.put(verifyEmail.url(userId), {}, options);
        if (type === "reset_password") router.post(resetPassword.url(userId), {}, options);
        if (type === "revoke") router.delete(revokeSessions.url(userId), options);
        if (type === "suspend" || type === "reactivate") {
            router.put(updateAccess.url(userId), { suspended: type === "suspend", reason }, options);
        }
    };

    const actionCopy: Record<ActionType, [string, string]> = {
        delete: ["Archive account", "Removes the account from the active directory. Related records are retained."],
        impersonate: ["Impersonate user", "Opens a session with this user's access. The action is audited."],
        verify: ["Mark email verified", "Overrides email verification. Only continue after confirming mailbox ownership."],
        reset_password: ["Send password reset", "Sends password reset instructions to this account's email address."],
        suspend: ["Suspend account", "Blocks sign in and revokes all browser sessions and API tokens. A reason is required."],
        reactivate: ["Reactivate account", "Allows a fresh sign in. Previous sessions and API tokens stay revoked. A reason is required."],
        revoke: ["Revoke all access", "Signs out all browser sessions and removes all API tokens. The user can sign in again unless suspended."],
    };
    const requiresReason = actionState.type === "suspend" || actionState.type === "reactivate";

    return (
        <AdminLayout user={user} title="User Management">
            <Head title="Administrators • Users" />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-2">
                        <p className="text-muted-foreground text-xs font-semibold tracking-widest uppercase">People & access</p>
                        <h1 className="text-3xl font-semibold tracking-tight text-balance">User management</h1>
                        <p className="text-muted-foreground max-w-2xl text-sm text-pretty">
                            Search every account, check its protection, and act without leaving the directory.
                        </p>
                    </div>
                    {can_create && (
                        <Button render={<Link href={create.url()} />} nativeButton={false} className="h-9 gap-2 px-3">
                            <Plus aria-hidden="true" className="size-4" />
                            Create account
                        </Button>
                    )}
                </header>

                <div className="grid items-start gap-5 lg:grid-cols-[15rem_minmax(0,1fr)]">
                    <aside className="lg:sticky lg:top-20">
                        <nav aria-label="Account review queues" className="flex flex-col gap-1.5">
                            {QUEUES.map((item) => {
                                const Icon = item.icon;
                                const active = queue === item.key;
                                return (
                                    <button
                                        key={item.key}
                                        type="button"
                                        aria-pressed={active}
                                        onClick={() => {
                                            if (item.key === "deleted") {
                                                router.get(
                                                    usersIndex.url(),
                                                    { trashed: "only" },
                                                    { preserveScroll: true, only: ["users", "analytics", "online_user_ids", "filters"] },
                                                );
                                                return;
                                            }
                                            if (viewingDeleted) {
                                                router.get(
                                                    usersIndex.url(),
                                                    {},
                                                    { preserveScroll: true, only: ["users", "analytics", "online_user_ids", "filters"] },
                                                );
                                            }
                                            setQueue(item.key);
                                        }}
                                        className={`group flex w-full items-center gap-3 rounded-xl border p-2.5 text-left transition-colors duration-200 ease-[cubic-bezier(0.32,0.72,0,1)] focus-visible:outline-2 focus-visible:outline-offset-2 ${
                                            active ? "border-primary/40 bg-primary/5" : "hover:bg-muted/60 border-transparent"
                                        }`}
                                    >
                                        <IconTile variant="soft" size="lg" className={item.tone}>
                                            <Icon aria-hidden="true" />
                                        </IconTile>
                                        <span className="flex min-w-0 flex-1 flex-col">
                                            <span className="truncate text-sm font-medium">{item.label}</span>
                                            <span className="text-muted-foreground text-xs leading-tight text-pretty">{item.hint}</span>
                                        </span>
                                        <Badge variant="outline" size="sm" radius="full" className="tabular-nums">
                                            {item.key === "deleted" ? (liveAnalytics.trashed_users ?? 0) : counts[item.key]}
                                        </Badge>
                                    </button>
                                );
                            })}
                        </nav>

                        <div className="mt-4 flex flex-wrap gap-2 border-t pt-4">
                            {QUICK_VIEWS.map((view) => (
                                <button
                                    key={view.key}
                                    type="button"
                                    aria-pressed={queue === view.key}
                                    onClick={() => setQueue(view.key)}
                                    className={`rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors focus-visible:outline-2 ${
                                        queue === view.key
                                            ? "bg-foreground text-background"
                                            : "text-muted-foreground hover:bg-muted hover:text-foreground"
                                    }`}
                                >
                                    {view.label}
                                    <span className="ml-1.5 tabular-nums opacity-70">{counts[view.key]}</span>
                                </button>
                            ))}
                        </div>
                    </aside>

                    {viewingDeleted ? (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm">
                            <p>
                                <span className="font-medium">Deleted accounts.</span> Archived accounts keep their records and cannot sign in.
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    router.get(
                                        usersIndex.url(),
                                        {},
                                        { preserveScroll: true, only: ["users", "analytics", "online_user_ids", "filters"] },
                                    );
                                    setQueue("all");
                                }}
                            >
                                Back to active accounts
                            </Button>
                        </div>
                    ) : null}

                    <section aria-label="User directory" className="bg-card min-w-0 rounded-xl border p-4 shadow-xs sm:p-5">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                                <h2 className="font-semibold">{QUEUES.find((item) => item.key === queue)?.label ?? "All accounts"}</h2>
                                <Badge variant="secondary" size="sm" radius="full">
                                    {visible.length} shown
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-xs">Live indicators refresh every 30 seconds</p>
                        </div>
                        <UserDirectoryGrid users={visible} options={options} onlineUserIds={liveOnlineUserIds} onAction={openDialog} />
                    </section>
                </div>

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

                <AlertDialog
                    open={actionState.type !== null}
                    onOpenChange={(open) => {
                        if (!open) closeDialog();
                    }}
                >
                    <AlertDialogContent className="sm:max-w-md">
                        <AlertDialogHeader>
                            <AlertDialogTitle>{actionState.type ? actionCopy[actionState.type][0] : "Account action"}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {actionState.userName}. {actionState.type ? actionCopy[actionState.type][1] : ""}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        {requiresReason ? (
                            <label className="flex flex-col gap-2 text-sm font-medium">
                                Reason
                                <Input
                                    value={reason}
                                    onChange={(event) => setReason(event.target.value)}
                                    maxLength={500}
                                    placeholder="Explain this access change"
                                    disabled={processing}
                                />
                            </label>
                        ) : null}
                        {actionError ? (
                            <p role="alert" className="text-destructive text-sm">
                                {actionError}
                            </p>
                        ) : null}
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={processing}>Cancel</AlertDialogCancel>
                            <Button
                                disabled={processing || (requiresReason && !reason.trim())}
                                onClick={confirmAction}
                                variant={
                                    actionState.type === "suspend" || actionState.type === "delete" || actionState.type === "revoke"
                                        ? "destructive"
                                        : "default"
                                }
                            >
                                {processing ? "Working…" : "Confirm change"}
                            </Button>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AdminLayout>
    );
}
