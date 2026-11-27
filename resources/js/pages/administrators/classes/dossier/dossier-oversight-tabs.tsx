import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { edit } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import {
    Activity,
    ArrowUpRight,
    CalendarCheck2,
    CheckCircle2,
    FileText,
    History,
    Lock,
    Megaphone,
    Palette,
    Pencil,
    ShieldCheck,
    Sliders,
} from "lucide-react";
import type {
    ClassPolicySettings,
    DossierActivityEntry,
    DossierAttendanceSession,
    DossierPost,
} from "../crud/types";
import type { ClassShowData } from "../record/types";

export const POLICY_TOGGLES: ReadonlyArray<{
    key: keyof ClassPolicySettings;
    label: string;
    shortLabel: string;
    description: string;
}> = [
    {
        key: "enable_announcements",
        label: "Announcements",
        shortLabel: "Announcements",
        description: "Allow faculty and administrators to post class updates and advisories.",
    },
    {
        key: "enable_grade_visibility",
        label: "Grade Visibility",
        shortLabel: "Grade Visibility",
        description: "Allow enrolled students to view their published grades in the portal.",
    },
    {
        key: "enable_attendance_tracking",
        label: "Attendance Tracking",
        shortLabel: "Attendance",
        description: "Record and monitor session-by-session student attendance.",
    },
    {
        key: "enable_online_resources",
        label: "Online Resources",
        shortLabel: "Online Resources",
        description: "Expose digital handouts, slides, and reference materials to students.",
    },
    {
        key: "allow_late_submissions",
        label: "Late Submissions",
        shortLabel: "Late Submissions",
        description: "Accept coursework turned in after the posted deadline.",
    },
    {
        key: "enable_discussion_board",
        label: "Discussion Board",
        shortLabel: "Discussion Board",
        description: "Enable threaded class discussions and Q&A topics.",
    },
    {
        key: "notify_students_on_schedule_changes",
        label: "Schedule Change Notices",
        shortLabel: "Schedule Notices",
        description: "Queue notifications to enrolled students whenever times or rooms change.",
    },
];

export function formatShortDate(value: string | null | undefined): string {
    if (!value) return "—";
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" });
}

export function formatShortDateTime(value: string | null | undefined): string {
    if (!value) return "—";
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString(undefined, {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    });
}

function humanizeLabel(value: string | null | undefined, fallback = "General"): string {
    if (!value) return fallback;
    return value
        .replaceAll("_", " ")
        .replaceAll("-", " ")
        .replace(/\b\w/g, (ch) => ch.toUpperCase());
}

/* ------------------------------------------------------------------ */
/* Attendance Tab                                                      */
/* ------------------------------------------------------------------ */

export function DossierAttendanceTab({
    sessions,
    averageRate,
    sessionsExpected,
}: {
    sessions: DossierAttendanceSession[];
    averageRate: number | null;
    sessionsExpected: number | null;
}) {
    return (
        <section className="bg-card overflow-hidden rounded-xl border">
            <div className="bg-muted/30 flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-5">
                <div className="space-y-0.5">
                    <div className="flex items-center gap-2">
                        <CalendarCheck2 className="text-primary size-4" aria-hidden="true" />
                        <h2 className="text-sm font-semibold">Attendance Sessions</h2>
                        {averageRate !== null && (
                            <Badge variant="outline" className="tabular-nums">
                                {Math.round(averageRate)}% avg
                            </Badge>
                        )}
                    </div>
                    <p className="text-muted-foreground text-xs">
                        {sessions.length} {sessions.length === 1 ? "session" : "sessions"} recorded
                        {sessionsExpected ? ` of ${sessionsExpected} expected` : ""}.
                    </p>
                </div>
            </div>

            {sessions.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
                    <CalendarCheck2 className="text-muted-foreground/60 size-8" aria-hidden="true" />
                    <p className="text-sm font-medium">No attendance sessions recorded yet</p>
                    <p className="text-muted-foreground max-w-sm text-xs">
                        Attendance summaries will appear here once faculty or administrators log class sessions.
                    </p>
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader className="bg-muted/30">
                            <TableRow>
                                <TableHead className="px-4 text-xs">Date</TableHead>
                                <TableHead className="px-4 text-xs">Title</TableHead>
                                <TableHead className="px-3 text-right text-xs">Present</TableHead>
                                <TableHead className="px-3 text-right text-xs">Absent</TableHead>
                                <TableHead className="px-3 text-right text-xs">Late</TableHead>
                                <TableHead className="px-3 text-right text-xs">Excused</TableHead>
                                <TableHead className="px-3 text-right text-xs">Rate</TableHead>
                                <TableHead className="px-4 text-right text-xs">State</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sessions.map((session) => {
                                const total = session.present + session.absent + session.late + session.excused;
                                const rate = total > 0 ? Math.round(((session.present + session.late) / total) * 100) : null;

                                return (
                                    <TableRow key={session.id}>
                                        <TableCell className="px-4 py-3 text-xs font-medium whitespace-nowrap tabular-nums">
                                            {formatShortDate(session.date)}
                                        </TableCell>
                                        <TableCell className="px-4 py-3 text-sm font-medium">
                                            {session.title || `Session #${session.id}`}
                                        </TableCell>
                                        <TableCell className="px-3 py-3 text-right text-xs font-medium text-emerald-700 tabular-nums dark:text-emerald-400">
                                            {session.present}
                                        </TableCell>
                                        <TableCell className="px-3 py-3 text-right text-xs font-medium text-rose-700 tabular-nums dark:text-rose-400">
                                            {session.absent}
                                        </TableCell>
                                        <TableCell className="px-3 py-3 text-right text-xs font-medium text-amber-700 tabular-nums dark:text-amber-400">
                                            {session.late}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground px-3 py-3 text-right text-xs tabular-nums">
                                            {session.excused}
                                        </TableCell>
                                        <TableCell className="px-3 py-3 text-right">
                                            {rate !== null ? (
                                                <Badge
                                                    variant="outline"
                                                    className={cn(
                                                        "text-xs tabular-nums",
                                                        rate >= 85
                                                            ? "border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                            : rate >= 70
                                                              ? "border-amber-600/30 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                              : "border-rose-600/30 bg-rose-500/10 text-rose-700 dark:text-rose-300",
                                                    )}
                                                >
                                                    {rate}%
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="px-4 py-3 text-right">
                                            {session.is_locked ? (
                                                <Badge variant="outline" className="gap-1 text-xs">
                                                    <Lock className="size-3" aria-hidden="true" />
                                                    Locked
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary" className="text-xs">
                                                    Open
                                                </Badge>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
            )}
        </section>
    );
}

/* ------------------------------------------------------------------ */
/* Posts Tab                                                           */
/* ------------------------------------------------------------------ */

export function DossierPostsTab({ posts }: { posts: DossierPost[] }) {
    return (
        <section className="bg-card overflow-hidden rounded-xl border">
            <div className="bg-muted/30 flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-5">
                <div className="space-y-0.5">
                    <div className="flex items-center gap-2">
                        <Megaphone className="text-primary size-4" aria-hidden="true" />
                        <h2 className="text-sm font-semibold">Class Stream &amp; Posts</h2>
                        <Badge variant="outline" className="tabular-nums">
                            {posts.length}
                        </Badge>
                    </div>
                    <p className="text-muted-foreground text-xs">Announcements, learning materials, and coursework posted to this section.</p>
                </div>
            </div>

            {posts.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
                    <FileText className="text-muted-foreground/60 size-8" aria-hidden="true" />
                    <p className="text-sm font-medium">No posts published yet</p>
                    <p className="text-muted-foreground max-w-sm text-xs">
                        Faculty announcements, handouts, and assignment posts will appear in this stream.
                    </p>
                </div>
            ) : (
                <ul className="divide-y">
                    {posts.map((post) => (
                        <li key={post.id} className="hover:bg-muted/25 flex flex-col gap-1.5 px-4 py-3.5 transition-colors sm:px-5">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex min-w-0 items-center gap-2">
                                    <span className="truncate text-sm font-semibold">{post.title}</span>
                                    <Badge variant="secondary" className="shrink-0 text-[11px]">
                                        {humanizeLabel(post.type, "Post")}
                                    </Badge>
                                </div>
                                <span className="text-muted-foreground shrink-0 text-xs tabular-nums">
                                    {formatShortDateTime(post.created_at)}
                                </span>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Posted by <span className="text-foreground font-medium">{post.author || "System"}</span>
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/* ------------------------------------------------------------------ */
/* Settings Tab                                                        */
/* ------------------------------------------------------------------ */

export function DossierSettingsTab({
    classItem,
    policyState,
    savingPolicyKey,
    canUpdate,
    onTogglePolicy,
}: {
    classItem: ClassShowData;
    policyState: ClassPolicySettings;
    savingPolicyKey: keyof ClassPolicySettings | null;
    canUpdate: boolean;
    onTogglePolicy: (key: keyof ClassPolicySettings, value: boolean) => void;
}) {
    const isEditable = canUpdate && !classItem.is_archived;
    const customSettings = Object.entries(classItem.settings?.custom ?? {});
    const accentColor = classItem.settings?.accent_color || "#3b82f6";
    const bgColor = classItem.settings?.background_color || "#ffffff";
    const themeName = humanizeLabel(classItem.settings?.theme, "Default");
    const courses =
        classItem.associated_courses && classItem.associated_courses !== "N/A"
            ? classItem.associated_courses
                  .split(",")
                  .map((c) => c.trim())
                  .filter(Boolean)
            : [];

    return (
        <div className="flex flex-col gap-5">
            {/* Live 7-Toggle Policy Grid */}
            <section className="bg-card overflow-hidden rounded-xl border" aria-labelledby="dossier-policies-heading">
                <div className="bg-muted/30 flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-5">
                    <div className="space-y-0.5">
                        <div className="flex items-center gap-2">
                            <Sliders className="text-primary size-4" aria-hidden="true" />
                            <h2 id="dossier-policies-heading" className="text-sm font-semibold">
                                Live Class Policies
                            </h2>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {isEditable
                                ? "Changes save immediately and sync to student and faculty portals."
                                : "Read-only policy snapshot for this class."}
                        </p>
                    </div>
                    {isEditable && (
                        <Badge variant="outline" className="gap-1 border-emerald-600/30 bg-emerald-500/10 text-xs text-emerald-700 dark:text-emerald-300">
                            <CheckCircle2 className="size-3" aria-hidden="true" />
                            Instant Save
                        </Badge>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 sm:p-5">
                    {POLICY_TOGGLES.map((policy) => {
                        const checked = Boolean(policyState[policy.key]);
                        const saving = savingPolicyKey === policy.key;

                        return (
                            <div
                                key={policy.key}
                                className={cn(
                                    "flex items-start justify-between gap-3 rounded-lg border p-3.5 transition-colors",
                                    checked ? "border-primary/25 bg-primary/[0.03]" : "bg-muted/20 border-border",
                                )}
                            >
                                <div className="min-w-0 space-y-1">
                                    <label
                                        htmlFor={`policy-switch-${policy.key}`}
                                        className="block cursor-pointer text-sm leading-snug font-medium"
                                    >
                                        {policy.label}
                                    </label>
                                    <p className="text-muted-foreground text-xs leading-relaxed">{policy.description}</p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2 pt-0.5">
                                    <Switch
                                        id={`policy-switch-${policy.key}`}
                                        checked={checked}
                                        disabled={!isEditable || saving}
                                        onCheckedChange={(next) => onTogglePolicy(policy.key, next)}
                                        aria-label={policy.label}
                                    />
                                </div>
                            </div>
                        );
                    })}
                </div>
            </section>

            {/* Appearance, Banner & Academic Metadata */}
            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <section className="bg-card overflow-hidden rounded-xl border">
                    <div className="bg-muted/30 flex items-center justify-between gap-2 border-b px-4 py-3">
                        <div className="flex items-center gap-2">
                            <Palette className="text-primary size-4" aria-hidden="true" />
                            <h3 className="text-sm font-semibold">Banner &amp; Appearance</h3>
                        </div>
                        {isEditable && (
                            <Button
                                variant="outline"
                                size="sm"
                                nativeButton={false}
                                render={<Link href={edit.url(classItem.id, { query: { step: "settings" } })} />}
                            >
                                <Pencil className="size-3.5" aria-hidden="true" />
                                Edit Banner &amp; Appearance
                            </Button>
                        )}
                    </div>
                    <div className="space-y-4 p-4">
                        {classItem.settings?.banner_image_url ? (
                            <div className="overflow-hidden rounded-lg border">
                                <img
                                    src={classItem.settings.banner_image_url}
                                    alt={`${classItem.record_title} banner`}
                                    className="h-32 w-full object-cover"
                                />
                            </div>
                        ) : (
                            <div
                                className="flex h-24 flex-col justify-end rounded-lg border p-3.5 text-white shadow-inner"
                                style={{
                                    background: `linear-gradient(135deg, ${accentColor}, #1e293b)`,
                                }}
                            >
                                <span className="font-mono text-[11px] opacity-85">{classItem.subject_code}</span>
                                <span className="truncate text-sm font-semibold">{classItem.record_title}</span>
                            </div>
                        )}

                        <div className="grid grid-cols-3 gap-3 text-xs">
                            <div className="bg-muted/30 rounded-lg border p-2.5">
                                <span className="text-muted-foreground block text-[11px]">Theme</span>
                                <span className="mt-0.5 block font-semibold">{themeName}</span>
                            </div>
                            <div className="bg-muted/30 rounded-lg border p-2.5">
                                <span className="text-muted-foreground block text-[11px]">Accent Color</span>
                                <span className="mt-1 inline-flex items-center gap-1.5 font-mono font-medium">
                                    <span className="size-3 rounded-full border" style={{ backgroundColor: accentColor }} />
                                    {accentColor}
                                </span>
                            </div>
                            <div className="bg-muted/30 rounded-lg border p-2.5">
                                <span className="text-muted-foreground block text-[11px]">Background</span>
                                <span className="mt-1 inline-flex items-center gap-1.5 font-mono font-medium">
                                    <span className="size-3 rounded-full border" style={{ backgroundColor: bgColor }} />
                                    {bgColor}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="bg-card overflow-hidden rounded-xl border">
                    <div className="bg-muted/30 flex items-center justify-between gap-2 border-b px-4 py-3">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="text-primary size-4" aria-hidden="true" />
                            <h3 className="text-sm font-semibold">Academic Metadata &amp; Links</h3>
                        </div>
                    </div>
                    <div className="space-y-4 p-4 text-sm">
                        <div className="space-y-1.5">
                            <span className="text-muted-foreground text-xs font-medium">Subjects</span>
                            {classItem.subjects?.length ? (
                                <div className="flex flex-wrap gap-1.5">
                                    {classItem.subjects.map((subj) => (
                                        <Badge key={subj} variant="outline" className="h-auto whitespace-normal">
                                            {subj}
                                        </Badge>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-xs">No subjects listed</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <span className="text-muted-foreground text-xs font-medium">
                                {classItem.classification === "shs" ? "Track & Strand" : "Associated Courses"}
                            </span>
                            {courses.length > 0 ? (
                                <div className="flex flex-wrap gap-1.5">
                                    {courses.map((course) => (
                                        <Badge key={course} variant="secondary" className="h-auto whitespace-normal">
                                            {course}
                                        </Badge>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-xs">Not specified</p>
                            )}
                        </div>

                        {customSettings.length > 0 && (
                            <div className="space-y-2 border-t pt-3">
                                <span className="text-muted-foreground text-xs font-medium">Custom Metadata</span>
                                <dl className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    {customSettings.map(([k, v]) => (
                                        <div key={k} className="bg-muted/25 rounded-md border px-2.5 py-2 text-xs">
                                            <dt className="text-muted-foreground">{humanizeLabel(k)}</dt>
                                            <dd className="mt-0.5 font-medium break-words">{String(v)}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        )}

                        {classItem.filament?.view_url && (
                            <div className="border-t pt-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    nativeButton={false}
                                    render={<a href={classItem.filament.view_url} target="_blank" rel="noreferrer" />}
                                >
                                    Open in Admin Panel
                                    <ArrowUpRight className="size-3.5" aria-hidden="true" />
                                </Button>
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------ */
/* Audit Log Tab                                                       */
/* ------------------------------------------------------------------ */

function auditLogBadge(log: string): { label: string; className: string } {
    if (log === "class_enrollments") {
        return {
            label: "Enrollments",
            className: "border-sky-600/30 bg-sky-500/10 text-sky-700 dark:text-sky-300",
        };
    }
    if (log === "class_posts") {
        return {
            label: "Posts",
            className: "border-violet-600/30 bg-violet-500/10 text-violet-700 dark:text-violet-300",
        };
    }
    if (log === "classes") {
        return {
            label: "Class",
            className: "border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
        };
    }
    return {
        label: humanizeLabel(log, "Activity"),
        className: "border-border bg-muted text-muted-foreground",
    };
}

export function DossierAuditTab({ activity }: { activity: DossierActivityEntry[] }) {
    return (
        <section className="bg-card overflow-hidden rounded-xl border">
            <div className="bg-muted/30 flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-5">
                <div className="space-y-0.5">
                    <div className="flex items-center gap-2">
                        <History className="text-primary size-4" aria-hidden="true" />
                        <h2 className="text-sm font-semibold">Audit &amp; Activity Log</h2>
                        <Badge variant="outline" className="tabular-nums">
                            {activity.length}
                        </Badge>
                    </div>
                    <p className="text-muted-foreground text-xs">
                        Chronological record of class edits, enrollment actions, and governance events.
                    </p>
                </div>
            </div>

            {activity.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
                    <Activity className="text-muted-foreground/60 size-8" aria-hidden="true" />
                    <p className="text-sm font-medium">No audit events recorded yet</p>
                    <p className="text-muted-foreground max-w-sm text-xs">
                        Changes to capacity, faculty, schedules, grades, and enrollments will appear here.
                    </p>
                </div>
            ) : (
                <ol className="divide-y">
                    {activity.map((entry) => {
                        const badge = auditLogBadge(entry.log);
                        return (
                            <li
                                key={entry.id}
                                className="hover:bg-muted/20 flex flex-col gap-1.5 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-5"
                            >
                                <div className="flex min-w-0 items-start gap-2.5">
                                    <Badge variant="outline" className={cn("mt-0.5 shrink-0 text-[11px]", badge.className)}>
                                        {badge.label}
                                    </Badge>
                                    <div className="min-w-0 space-y-0.5">
                                        <p className="text-sm leading-snug">{entry.description}</p>
                                        <p className="text-muted-foreground text-xs">
                                            By <span className="text-foreground font-medium">{entry.actor || "System"}</span>
                                        </p>
                                    </div>
                                </div>
                                <time className="text-muted-foreground shrink-0 text-xs whitespace-nowrap tabular-nums">
                                    {formatShortDateTime(entry.at)}
                                </time>
                            </li>
                        );
                    })}
                </ol>
            )}
        </section>
    );
}
