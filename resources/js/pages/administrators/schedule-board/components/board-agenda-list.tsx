import { AlertTriangle, MapPin, Monitor, Plus, User } from "lucide-react";
import { canonicalDeliveryMode, deliveryModeShortLabel, isOnlineDeliveryMode } from "../../classes/lib/class-defaults";
import { formatTimeRange } from "../../scheduling/lib/time-geometry";
import type { BoardSessionItem, DirectoryCategory } from "../lib/board-entities";

type BoardAgendaListProps = {
    days: string[];
    sessions: BoardSessionItem[];
    category: DirectoryCategory;
    onSelectSession: (item: BoardSessionItem) => void;
    onAddOnDay: (day: string) => void;
};

const ACCENT_DOTS = [
    "bg-sky-500",
    "bg-violet-500",
    "bg-emerald-500",
    "bg-amber-500",
    "bg-teal-500",
    "bg-indigo-500",
    "bg-cyan-500",
];

function dotColor(code: string): string {
    let hash = 0;
    for (let i = 0; i < code.length; i++) hash = (hash * 31 + code.charCodeAt(i)) | 0;
    return ACCENT_DOTS[Math.abs(hash) % ACCENT_DOTS.length];
}

export default function BoardAgendaList({
    days,
    sessions,
    category,
    onSelectSession,
    onAddOnDay,
}: BoardAgendaListProps) {
    return (
        <div className="flex-1 space-y-5 overflow-y-auto px-5 py-5">
            {days.map((day) => {
                const dayItems = sessions
                    .filter((item) => item.session.day_of_week === day)
                    .sort((a, b) => a.startMin - b.startMin);

                return (
                    <section key={day} className="space-y-2">
                        <div className="flex items-center justify-between">
                            <div className="flex items-baseline gap-2.5">
                                <h3 className="text-sm font-semibold tracking-tight">{day}</h3>
                                <span className="text-muted-foreground text-xs tabular-nums">
                                    {dayItems.length === 0
                                        ? "No classes"
                                        : `${dayItems.length} ${dayItems.length === 1 ? "session" : "sessions"}`}
                                </span>
                            </div>
                            <button
                                type="button"
                                onClick={() => onAddOnDay(day)}
                                className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium transition-colors"
                            >
                                <Plus className="size-3.5" /> Add
                            </button>
                        </div>

                        {dayItems.length === 0 ? (
                            <div className="border-border/50 text-muted-foreground/70 rounded-xl border border-dashed px-4 py-3 text-xs">
                                Open day — click Add to schedule a class on {day}.
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {dayItems.map((item) => {
                                    const mode = canonicalDeliveryMode(
                                        item.session.delivery_mode ?? item.classItem.delivery_mode,
                                    );
                                    const online = isOnlineDeliveryMode(mode);
                                    const hasOverlap = item.conflicts.length > 0;

                                    return (
                                        <button
                                            key={item.key}
                                            type="button"
                                            onClick={() => onSelectSession(item)}
                                            className={`bg-background hover:bg-muted/40 group flex w-full flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left transition-all active:scale-[0.995] ${
                                                hasOverlap
                                                    ? "border-rose-400/50 hover:border-rose-500/60"
                                                    : "border-border/60 hover:border-border"
                                            }`}
                                        >
                                            <div className="flex min-w-0 items-center gap-3.5">
                                                <span
                                                    className={`h-9 w-1 shrink-0 rounded-full ${
                                                        hasOverlap ? "bg-rose-500" : dotColor(item.classItem.subject_code)
                                                    }`}
                                                />
                                                <div className="w-[146px] shrink-0 text-xs font-semibold tabular-nums">
                                                    {formatTimeRange(item.startMin, item.endMin)}
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-semibold tracking-tight">
                                                            {item.classItem.subject_code}
                                                        </span>
                                                        <span className="text-muted-foreground text-xs">
                                                            Sec {item.classItem.section}
                                                        </span>
                                                    </div>
                                                    <p className="text-muted-foreground truncate text-xs">
                                                        {item.classItem.subject_title}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="flex flex-wrap items-center gap-2.5 text-xs">
                                                {category !== "rooms" && (
                                                    <span className="bg-muted/60 text-muted-foreground inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1">
                                                        {online ? (
                                                            <Monitor className="size-3.5" />
                                                        ) : (
                                                            <MapPin className="size-3.5" />
                                                        )}
                                                        <span>
                                                            {online
                                                                ? deliveryModeShortLabel(mode)
                                                                : (item.session.room ?? "No room")}
                                                        </span>
                                                    </span>
                                                )}

                                                {category !== "teachers" && item.classItem.faculty_name && (
                                                    <span className="text-muted-foreground inline-flex items-center gap-1.5">
                                                        <User className="size-3.5" />
                                                        <span>{item.classItem.faculty_name}</span>
                                                    </span>
                                                )}

                                                {hasOverlap && (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-rose-500/12 px-2.5 py-1 text-[11px] font-semibold text-rose-700 dark:text-rose-300">
                                                        <AlertTriangle className="size-3" />
                                                        Overlap
                                                    </span>
                                                )}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </section>
                );
            })}
        </div>
    );
}
