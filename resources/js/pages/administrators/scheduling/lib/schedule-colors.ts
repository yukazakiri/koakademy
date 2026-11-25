import { isAsynchronousOnlineMode, isSynchronousOnlineMode, type DeliveryMode } from "../../classes/lib/class-defaults";
import type { ClassScheduleData, ScheduleEntry } from "../../components/scheduling-types";

export type ColorScheme = "subject" | "delivery" | "status" | "health";

export type BlockColorStyle = {
    surface: string;
    accentBar: string;
    badge: string;
};

// Harmonious, readable palette tokens supporting both light & dark modes
const SUBJECT_PALETTE: BlockColorStyle[] = [
    {
        surface: "bg-sky-500/[0.09] hover:bg-sky-500/[0.14] border-sky-500/25 text-sky-950 dark:bg-sky-500/15 dark:text-sky-100 dark:border-sky-400/30",
        accentBar: "bg-sky-500",
        badge: "bg-sky-500/15 text-sky-700 dark:text-sky-300",
    },
    {
        surface: "bg-violet-500/[0.09] hover:bg-violet-500/[0.14] border-violet-500/25 text-violet-950 dark:bg-violet-500/15 dark:text-violet-100 dark:border-violet-400/30",
        accentBar: "bg-violet-500",
        badge: "bg-violet-500/15 text-violet-700 dark:text-violet-300",
    },
    {
        surface: "bg-emerald-500/[0.09] hover:bg-emerald-500/[0.14] border-emerald-500/25 text-emerald-950 dark:bg-emerald-500/15 dark:text-emerald-100 dark:border-emerald-400/30",
        accentBar: "bg-emerald-500",
        badge: "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300",
    },
    {
        surface: "bg-amber-500/[0.10] hover:bg-amber-500/[0.16] border-amber-500/30 text-amber-950 dark:bg-amber-500/15 dark:text-amber-100 dark:border-amber-400/30",
        accentBar: "bg-amber-500",
        badge: "bg-amber-500/15 text-amber-800 dark:text-amber-300",
    },
    {
        surface: "bg-teal-500/[0.09] hover:bg-teal-500/[0.14] border-teal-500/25 text-teal-950 dark:bg-teal-500/15 dark:text-teal-100 dark:border-teal-400/30",
        accentBar: "bg-teal-500",
        badge: "bg-teal-500/15 text-teal-700 dark:text-teal-300",
    },
    {
        surface: "bg-indigo-500/[0.09] hover:bg-indigo-500/[0.14] border-indigo-500/25 text-indigo-950 dark:bg-indigo-500/15 dark:text-indigo-100 dark:border-indigo-400/30",
        accentBar: "bg-indigo-500",
        badge: "bg-indigo-500/15 text-indigo-700 dark:text-indigo-300",
    },
    {
        surface: "bg-rose-500/[0.08] hover:bg-rose-500/[0.13] border-rose-500/25 text-rose-950 dark:bg-rose-500/15 dark:text-rose-100 dark:border-rose-400/30",
        accentBar: "bg-rose-500",
        badge: "bg-rose-500/15 text-rose-700 dark:text-rose-300",
    },
    {
        surface: "bg-cyan-500/[0.09] hover:bg-cyan-500/[0.14] border-cyan-500/25 text-cyan-950 dark:bg-cyan-500/15 dark:text-cyan-100 dark:border-cyan-400/30",
        accentBar: "bg-cyan-500",
        badge: "bg-cyan-500/15 text-cyan-700 dark:text-cyan-300",
    },
];

function hashString(input: string): number {
    let hash = 0;
    for (let i = 0; i < input.length; i++) {
        hash = (hash * 31 + input.charCodeAt(i)) | 0;
    }
    return Math.abs(hash);
}

export function resolveBlockColor(
    classItem: ClassScheduleData,
    session: ScheduleEntry,
    scheme: ColorScheme,
    hasConflict: boolean,
    isOvercrowded: boolean,
): BlockColorStyle {
    // Conflict always wins visually with crisp destructive framing
    if (hasConflict) {
        return {
            surface:
                "bg-red-500/[0.11] hover:bg-red-500/[0.17] border-red-500/50 text-red-950 dark:bg-red-500/20 dark:text-red-50 dark:border-red-400/60",
            accentBar: "bg-red-500",
            badge: "bg-red-500/20 text-red-700 dark:text-red-200",
        };
    }

    const mode: DeliveryMode = session.delivery_mode ?? classItem.delivery_mode ?? "face_to_face";

    if (scheme === "delivery") {
        if (isSynchronousOnlineMode(mode)) {
            return {
                surface:
                    "bg-violet-500/[0.10] hover:bg-violet-500/[0.16] border-violet-500/30 text-violet-950 dark:bg-violet-500/15 dark:text-violet-100 dark:border-violet-400/35",
                accentBar: "bg-violet-500",
                badge: "bg-violet-500/15 text-violet-700 dark:text-violet-300",
            };
        }
        if (isAsynchronousOnlineMode(mode)) {
            return {
                surface:
                    "bg-cyan-500/[0.10] hover:bg-cyan-500/[0.16] border-cyan-500/30 text-cyan-950 dark:bg-cyan-500/15 dark:text-cyan-100 dark:border-cyan-400/35",
                accentBar: "bg-cyan-500",
                badge: "bg-cyan-500/15 text-cyan-700 dark:text-cyan-300",
            };
        }
        return {
            surface:
                "bg-emerald-500/[0.09] hover:bg-emerald-500/[0.14] border-emerald-500/25 text-emerald-950 dark:bg-emerald-500/15 dark:text-emerald-100 dark:border-emerald-400/30",
            accentBar: "bg-emerald-500",
            badge: "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300",
        };
    }

    if (scheme === "status") {
        if (session.status === "draft") {
            return {
                surface:
                    "bg-amber-500/[0.10] hover:bg-amber-500/[0.15] border-amber-500/40 border-dashed text-amber-950 dark:bg-amber-500/15 dark:text-amber-100 dark:border-amber-400/45",
                accentBar: "bg-amber-500",
                badge: "bg-amber-500/20 text-amber-800 dark:text-amber-300",
            };
        }
        return {
            surface:
                "bg-emerald-500/[0.08] hover:bg-emerald-500/[0.13] border-emerald-500/25 text-emerald-950 dark:bg-emerald-500/15 dark:text-emerald-100 dark:border-emerald-400/30",
            accentBar: "bg-emerald-500",
            badge: "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300",
        };
    }

    if (scheme === "health") {
        if (isOvercrowded || (!isAsynchronousOnlineMode(mode) && !isSynchronousOnlineMode(mode) && !session.room_id)) {
            return {
                surface:
                    "bg-amber-500/[0.11] hover:bg-amber-500/[0.17] border-amber-500/40 text-amber-950 dark:bg-amber-500/15 dark:text-amber-100 dark:border-amber-400/40",
                accentBar: "bg-amber-500",
                badge: "bg-amber-500/20 text-amber-800 dark:text-amber-200",
            };
        }
        return {
            surface:
                "bg-emerald-500/[0.08] hover:bg-emerald-500/[0.13] border-emerald-500/25 text-emerald-950 dark:bg-emerald-500/15 dark:text-emerald-100 dark:border-emerald-400/30",
            accentBar: "bg-emerald-500",
            badge: "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300",
        };
    }

    const index = hashString(`${classItem.subject_code}:${classItem.section}`) % SUBJECT_PALETTE.length;
    return SUBJECT_PALETTE[index];
}
