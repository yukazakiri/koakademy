"use client";

import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/reui/badge";
import { IconTile } from "@/components/reui/icon-tile";
import {
    Timeline,
    TimelineContent,
    TimelineDate,
    TimelineHeader,
    TimelineIndicator,
    TimelineItem,
    TimelineSeparator,
    TimelineTitle,
} from "@/components/reui/timeline";
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
import { edit, index, show } from "@/routes/administrators/users";
import { update as updateAccess } from "@/routes/administrators/users/access";
import { destroy as revokeSessions } from "@/routes/administrators/users/sessions";
import { destroy as destroyToken } from "@/routes/administrators/users/tokens";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, CircleSlash, KeyRound, KeyRoundIcon, MonitorSmartphone, Pencil, ShieldCheck, Smartphone, UserRound } from "lucide-react";
import { useState } from "react";

interface Account {
    id: number;
    name: string;
    email: string;
    role: string;
    school: string | null;
    department: string | null;
    roles: string[];
    email_verified_at: string | null;
    created_at: string | null;
    last_login_at: string | null;
    last_active_at: string | null;
    recently_active: boolean;
    login_challenges_enabled: boolean;
    authenticator_configured: boolean;
    email_authentication: boolean;
    passkeys_count: number;
    suspended_at: string | null;
    suspension_reason: string | null;
}

interface SecurityLog {
    id: number;
    event: string;
    outcome: string;
    browser: string;
    platform: string;
    actor: string;
    reason: string | null;
    created_at: string | null;
}

interface Props {
    can_manage_access: boolean;
    sessions: { supported: boolean; data: { reference: string; browser: string; platform: string; last_active_at: string }[] };
    tokens: { id: number; name: string; abilities: string[]; last_used_at: string | null; expires_at: string | null }[];
    account: Account;
    user: User;
    logs: { data: SecurityLog[]; current_page: number; last_page: number; total: number };
    filters: { event?: string; outcome?: string };
    event_options: string[];
}

const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: "medium", timeStyle: "short" });

function date(value: string | null) {
    return value ? dateFormatter.format(new Date(value)) : "Not recorded";
}

function label(value: string) {
    return value.replaceAll("_", " ");
}

function initials(name: string) {
    return name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
}

export default function UserDetails({ account, user, logs, filters, event_options, can_manage_access, sessions, tokens }: Props) {
    const [action, setAction] = useState<{ type: "suspend" | "reactivate" | "sessions" | "token"; reference?: string; token?: number } | null>(null);
    const [reason, setReason] = useState("");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");

    function confirm() {
        if (!action) return;
        setBusy(true);
        setError("");
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setAction(null);
                setReason("");
            },
            onError: (errors: Record<string, string>) => setError(Object.values(errors).join(" ")),
            onFinish: () => setBusy(false),
        };
        if (action.type === "suspend" || action.type === "reactivate") {
            router.put(updateAccess.url(account.id), { suspended: action.type === "suspend", reason }, options);
        } else if (action.type === "token" && action.token) {
            router.delete(destroyToken.url({ user: account.id, token: action.token }), options);
        } else {
            router.delete(revokeSessions.url(account.id), { ...options, data: { reference: action.reference || null } });
        }
    }

    function filter() {
        router.get(show.url(account.id), { event: filters.event || "", outcome: filters.outcome || "" }, { preserveScroll: true });
    }

    function page(number: number) {
        router.get(show.url(account.id), { ...filters, page: number }, { preserveScroll: true });
    }

    const methods = [account.authenticator_configured && "Authenticator", account.email_authentication && "Email code"].filter(Boolean);
    const mfaReady = account.login_challenges_enabled && methods.length > 0;

    const signals = [
        {
            label: "Last sign-in",
            value: account.last_login_at ? date(account.last_login_at) : "Never recorded",
            icon: KeyRoundIcon,
            tone: "text-primary",
        },
        {
            label: "Last active",
            value: account.last_active_at ? date(account.last_active_at) : "Never recorded",
            icon: UserRound,
            tone: account.recently_active ? "text-emerald-600" : "text-muted-foreground",
        },
        {
            label: "Email",
            value: account.email_verified_at ? "Verified" : "Unverified",
            icon: ShieldCheck,
            tone: account.email_verified_at ? "text-emerald-600" : "text-amber-600",
        },
        {
            label: "Login challenges",
            value: mfaReady ? methods.join(" + ") : account.login_challenges_enabled ? "No method configured" : "Disabled",
            icon: ShieldCheck,
            tone: mfaReady ? "text-emerald-600" : "text-amber-600",
        },
        {
            label: "Passkeys",
            value: `${account.passkeys_count} registered`,
            icon: Smartphone,
            tone: account.passkeys_count > 0 ? "text-primary" : "text-muted-foreground",
        },
        {
            label: "Account created",
            value: date(account.created_at),
            icon: UserRound,
            tone: "text-muted-foreground",
        },
    ];

    return (
        <AdminLayout user={user} title="View user">
            <Head title={`${account.name} • User details`} />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
                <header className="bg-card flex flex-col gap-4 rounded-2xl border p-5 shadow-xs sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex min-w-0 items-start gap-4">
                        <div className="bg-primary/10 text-primary flex size-12 shrink-0 items-center justify-center rounded-xl text-base font-semibold">
                            {initials(account.name)}
                        </div>
                        <div className="flex min-w-0 flex-col gap-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-semibold tracking-tight text-balance">{account.name}</h1>
                                {account.suspended_at ? (
                                    <Badge variant="destructive-light" size="sm" radius="full">
                                        Suspended
                                    </Badge>
                                ) : (
                                    <Badge variant="success-light" size="sm" radius="full">
                                        Active
                                    </Badge>
                                )}
                                <Badge variant="outline" size="sm" radius="full">
                                    {account.role}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-sm break-all">{account.email}</p>
                            <p className="text-muted-foreground text-xs">
                                Account #{account.id} · {account.school || "No school"} · {account.department || "No department"}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button render={<Link href={edit.url(account.id)} />} nativeButton={false} variant="outline" size="sm">
                            <Pencil aria-hidden="true" className="size-3.5" />
                            Edit
                        </Button>
                        {can_manage_access ? (
                            <>
                                <Button
                                    variant={account.suspended_at ? "outline" : "destructive"}
                                    size="sm"
                                    onClick={() => setAction({ type: account.suspended_at ? "reactivate" : "suspend" })}
                                >
                                    <CircleSlash aria-hidden="true" className="size-3.5" />
                                    {account.suspended_at ? "Reactivate" : "Suspend"}
                                </Button>
                                <Button variant="outline" size="sm" onClick={() => setAction({ type: "sessions" })}>
                                    <KeyRound aria-hidden="true" className="size-3.5" />
                                    Revoke access
                                </Button>
                            </>
                        ) : null}
                        <Link
                            href={index.url()}
                            className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm underline underline-offset-4 focus-visible:outline-2"
                        >
                            <ArrowLeft aria-hidden="true" className="size-3.5" />
                            Directory
                        </Link>
                    </div>
                </header>

                {account.suspended_at ? (
                    <div className="border-destructive/30 bg-destructive/5 flex flex-col gap-1 rounded-xl border p-4 text-sm">
                        <p className="font-medium">Access suspended {date(account.suspended_at)}</p>
                        {account.suspension_reason ? <p className="text-muted-foreground">{account.suspension_reason}</p> : null}
                        <p className="text-muted-foreground text-xs">
                            Sign in and existing sessions stay blocked until the account is reactivated. Reactivation requires a fresh sign in.
                        </p>
                    </div>
                ) : null}

                <section aria-label="Account signals" className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {signals.map((signal) => {
                        const Icon = signal.icon;
                        return (
                            <div key={signal.label} className="bg-card flex flex-col gap-2 rounded-xl border p-3 shadow-xs">
                                <div className="flex items-center gap-2">
                                    <IconTile variant="soft" size="sm" className={signal.tone}>
                                        <Icon aria-hidden="true" />
                                    </IconTile>
                                    <span className="text-muted-foreground text-xs">{signal.label}</span>
                                </div>
                                <p className="text-sm leading-snug font-medium text-pretty">{signal.value}</p>
                            </div>
                        );
                    })}
                </section>

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <section aria-label="Security history" className="bg-card rounded-xl border p-5 shadow-xs">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="font-semibold">Security history</h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Recorded authentication and administration events for this account.
                                </p>
                            </div>
                            <Badge variant="secondary" size="sm" radius="full">
                                {logs.total} events
                            </Badge>
                        </div>

                        <form onSubmit={filter} className="mb-5 flex flex-wrap items-end gap-2">
                            <label className="flex min-w-40 flex-1 flex-col gap-1.5 text-xs font-medium">
                                Event
                                <select
                                    name="event"
                                    defaultValue={filters.event || ""}
                                    className="bg-background h-9 rounded-md border px-2.5 text-sm font-normal"
                                >
                                    <option value="">All events</option>
                                    {event_options.map((event) => (
                                        <option key={event} value={event}>
                                            {label(event)}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="flex min-w-36 flex-col gap-1.5 text-xs font-medium">
                                Outcome
                                <select
                                    name="outcome"
                                    defaultValue={filters.outcome || ""}
                                    className="bg-background h-9 rounded-md border px-2.5 text-sm font-normal"
                                >
                                    <option value="">All outcomes</option>
                                    <option value="success">Success</option>
                                    <option value="failure">Failure</option>
                                </select>
                            </label>
                            <Button type="submit" variant="outline" size="sm">
                                Filter
                            </Button>
                        </form>

                        {logs.data.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-8 text-center">
                                <p className="font-medium">No recorded events</p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Nothing matches these filters. Activity before tracking started cannot be reconstructed.
                                </p>
                            </div>
                        ) : (
                            <Timeline className="mt-2">
                                {logs.data.map((log, index) => (
                                    <TimelineItem key={log.id} step={index + 1}>
                                        <TimelineHeader>
                                            <TimelineIndicator
                                                className={
                                                    log.outcome === "failure"
                                                        ? "border-destructive/40 bg-destructive/10"
                                                        : "border-emerald-500/40 bg-emerald-500/10"
                                                }
                                            />
                                            <TimelineDate>{date(log.created_at)}</TimelineDate>
                                            <TimelineTitle className="first-letter:uppercase">{label(log.event)}</TimelineTitle>
                                        </TimelineHeader>
                                        <TimelineContent className="ms-6 flex flex-wrap items-center gap-2 text-xs">
                                            <Badge
                                                variant={log.outcome === "failure" ? "destructive-light" : "success-light"}
                                                size="sm"
                                                radius="full"
                                            >
                                                {log.outcome}
                                            </Badge>
                                            <span>
                                                {log.browser || "Unknown device"} · {log.platform || "Unknown platform"}
                                            </span>
                                            <span className="text-muted-foreground">by {log.actor}</span>
                                            {log.reason ? <span className="text-muted-foreground italic">{log.reason}</span> : null}
                                        </TimelineContent>
                                        <TimelineSeparator />
                                    </TimelineItem>
                                ))}
                            </Timeline>
                        )}

                        <nav aria-label="Security history pages" className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                            <p className="text-muted-foreground text-sm">
                                Page {logs.current_page} of {Math.max(1, logs.last_page)}
                            </p>
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" disabled={logs.current_page <= 1} onClick={() => page(logs.current_page - 1)}>
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={logs.current_page >= logs.last_page}
                                    onClick={() => page(logs.current_page + 1)}
                                >
                                    Next
                                </Button>
                            </div>
                        </nav>
                    </section>

                    <div className="flex flex-col gap-5">
                        <section aria-label="Account and access" className="bg-card rounded-xl border p-5 shadow-xs">
                            <h2 className="mb-3 font-semibold">Access</h2>
                            <dl className="flex flex-col gap-3 text-sm">
                                {[
                                    ["System role", account.role],
                                    ["Permission roles", account.roles.join(", ") || "None assigned"],
                                    ["School", account.school || "Not assigned"],
                                    ["Department", account.department || "Not assigned"],
                                ].map(([name, value]) => (
                                    <div key={name}>
                                        <dt className="text-muted-foreground text-xs">{name}</dt>
                                        <dd className="mt-0.5 font-medium break-words">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        <section aria-label="Browser sessions" className="bg-card rounded-xl border p-5 shadow-xs">
                            <div className="mb-3 flex items-center justify-between gap-2">
                                <h2 className="font-semibold">Browser sessions</h2>
                                <IconTile variant="outline" size="sm">
                                    <MonitorSmartphone aria-hidden="true" />
                                </IconTile>
                            </div>
                            {!sessions.supported ? (
                                <p className="text-muted-foreground text-sm">
                                    Individual sessions need database session storage. Revoke all access still works.
                                </p>
                            ) : sessions.data.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No unexpired database sessions recorded.</p>
                            ) : (
                                <ul className="flex flex-col gap-2">
                                    {sessions.data.map((session) => (
                                        <li key={session.reference} className="flex items-center justify-between gap-3 rounded-lg border p-3">
                                            <div className="min-w-0 text-sm">
                                                <p className="truncate font-medium">
                                                    {session.browser} / {session.platform}
                                                </p>
                                                <p className="text-muted-foreground text-xs">Last active {date(session.last_active_at)}</p>
                                            </div>
                                            {can_manage_access ? (
                                                <Button
                                                    variant="ghost"
                                                    size="xs"
                                                    onClick={() => setAction({ type: "sessions", reference: session.reference })}
                                                >
                                                    Revoke
                                                </Button>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section aria-label="API tokens" className="bg-card rounded-xl border p-5 shadow-xs">
                            <div className="mb-3 flex items-center justify-between gap-2">
                                <h2 className="font-semibold">API tokens</h2>
                                <Badge variant="outline" size="sm" radius="full">
                                    {tokens.length}
                                </Badge>
                            </div>
                            {tokens.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No API tokens recorded.</p>
                            ) : (
                                <ul className="flex flex-col gap-2">
                                    {tokens.map((token) => (
                                        <li key={token.id} className="flex items-center justify-between gap-3 rounded-lg border p-3">
                                            <div className="min-w-0 text-sm">
                                                <p className="truncate font-medium">{token.name}</p>
                                                <p className="text-muted-foreground truncate text-xs">
                                                    {token.abilities.join(", ")} · expires {token.expires_at ? date(token.expires_at) : "never"}
                                                </p>
                                            </div>
                                            {can_manage_access ? (
                                                <Button variant="ghost" size="xs" onClick={() => setAction({ type: "token", token: token.id })}>
                                                    Revoke
                                                </Button>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                </div>

                <AlertDialog
                    open={action !== null}
                    onOpenChange={(open) => {
                        if (!open && !busy) {
                            setAction(null);
                            setError("");
                        }
                    }}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Confirm account access change</AlertDialogTitle>
                            <AlertDialogDescription>
                                This affects {account.name}. Suspension or revoking all access signs out browser sessions and removes API tokens.
                                Individual revocations affect only the selected credential.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        {action?.type === "suspend" || action?.type === "reactivate" ? (
                            <label className="flex flex-col gap-2 text-sm font-medium">
                                Reason
                                <Input
                                    value={reason}
                                    onChange={(event) => setReason(event.target.value)}
                                    maxLength={500}
                                    placeholder="Explain this access change"
                                    disabled={busy}
                                />
                            </label>
                        ) : null}
                        {error ? (
                            <p role="alert" className="text-destructive text-sm">
                                {error}
                            </p>
                        ) : null}
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={busy}>Cancel</AlertDialogCancel>
                            <Button
                                disabled={busy || ((action?.type === "suspend" || action?.type === "reactivate") && !reason.trim())}
                                onClick={confirm}
                            >
                                {busy ? "Working…" : "Confirm change"}
                            </Button>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AdminLayout>
    );
}
