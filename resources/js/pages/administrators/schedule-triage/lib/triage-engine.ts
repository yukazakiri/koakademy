import type { ClassScheduleData, ConflictKind, ScheduleConflict, ScheduleEntry } from "../../components/scheduling-types";

export type TriageCategory = "room" | "faculty" | "section" | "missing_time";

export type TriageInvolvedSession = {
    classItem: ClassScheduleData;
    session: ScheduleEntry;
    startMin: number;
    endMin: number;
};

export type TriageIssue = {
    id: string;
    category: TriageCategory;
    headline: string;
    subline: string;
    day: string;
    windowStartMin: number;
    windowEndMin: number;
    impactCount: number; // how many raw conflicts this root cause represents
    sessions: TriageInvolvedSession[];
    missingClass?: ClassScheduleData;
    missingHoursLabel?: string;
};

export type TriageSummary = {
    issues: TriageIssue[];
    rawConflictsCount: number;
    rootIssuesCount: number;
    cleanClassesCount: number;
    totalClassesCount: number;
    healthScorePct: number;
    countsByCategory: Record<TriageCategory, number>;
};

function parseMin(value: string | null | undefined): number {
    if (!value) return 8 * 60;
    const trimmed = value.trim();
    const ampm = trimmed.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)$/i);
    if (ampm) {
        let h = Number(ampm[1]);
        const m = Number(ampm[2]);
        const mer = ampm[3].toUpperCase();
        if (mer === "PM" && h < 12) h += 12;
        if (mer === "AM" && h === 12) h = 0;
        return h * 60 + m;
    }
    const tf = trimmed.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
    if (tf) return Number(tf[1]) * 60 + Number(tf[2]);
    return 8 * 60;
}

function formatMin12(totalMinutes: number): string {
    const clamped = Math.max(0, Math.min(24 * 60, Math.round(totalMinutes)));
    const h = Math.floor(clamped / 60);
    const m = clamped % 60;
    const mer = h >= 12 && h < 24 ? "PM" : "AM";
    const h12 = h % 12 === 0 ? 12 : h % 12;
    return `${h12}:${String(m).padStart(2, "0")} ${mer}`;
}

function partOfDay(startMin: number): string {
    if (startMin < 12 * 60) return "morning";
    if (startMin < 17 * 60) return "afternoon";
    return "evening";
}

function scheduledHoursOf(schedules: ScheduleEntry[]): number {
    let mins = 0;
    for (const s of schedules) {
        const start = parseMin(s.start_time);
        const end = parseMin(s.end_time);
        if (end > start) mins += end - start;
    }
    return Math.round((mins / 60) * 100) / 100;
}

/**
 * Collapses hundreds of pairwise schedule conflicts into a finite, high-signal
 * Root-Cause Triage queue ordered by highest impact first.
 */
export function buildTriageQueue(
    classes: ClassScheduleData[],
    conflicts: ScheduleConflict[],
): TriageSummary {
    const sessionLookup = new Map<number, TriageInvolvedSession>();
    for (const cls of classes) {
        for (const s of cls.schedules) {
            if (s.id !== undefined) {
                const startMin = parseMin(s.start_time);
                const endMin = Math.max(startMin + 30, parseMin(s.end_time));
                sessionLookup.set(s.id, { classItem: cls, session: s, startMin, endMin });
            }
        }
    }

    // Group conflicts into root-cause buckets by (primaryKind, resourceKey, day, 2-hour wave bucket)
    type Bucket = {
        id: string;
        category: ConflictKind;
        resourceLabel: string;
        day: string;
        sessionMap: Map<number, TriageInvolvedSession>;
        rawCount: number;
    };

    const buckets = new Map<string, Bucket>();

    for (const c of conflicts) {
        const kinds = c.conflict_types && c.conflict_types.length > 0 ? c.conflict_types : [c.conflict_type];
        // Prioritize room -> faculty -> section for root-cause ownership
        const primary: ConflictKind = kinds.includes("room")
            ? "room"
            : kinds.includes("faculty")
              ? "faculty"
              : "section";

        const s1 = c.schedule_1_id !== undefined ? sessionLookup.get(c.schedule_1_id) : undefined;
        const s2 = c.schedule_2_id !== undefined ? sessionLookup.get(c.schedule_2_id) : undefined;
        if (!s1 || !s2) continue;

        const overlapAnchorMin = Math.min(s1.startMin, s2.startMin);
        const timeCluster = Math.floor(overlapAnchorMin / 90); // group contiguous overlap cluster

        let resourceKey = "";
        let resourceLabel = "";
        if (primary === "room") {
            const roomName = c.class_1.room ?? c.class_2.room ?? "Physical Room";
            resourceKey = `room:${c.room_id ?? roomName}`;
            resourceLabel = roomName;
        } else if (primary === "faculty") {
            const facultyName = c.class_1.faculty ?? c.class_2.faculty ?? "Instructor";
            resourceKey = `faculty:${c.faculty_id ?? facultyName}`;
            resourceLabel = facultyName;
        } else {
            const cohort = `${s1.classItem.courses || "Cohort"} · ${s1.classItem.grade_level ?? ""} · Sec ${s1.classItem.section}`.trim();
            resourceKey = `section:${cohort}`;
            resourceLabel = cohort;
        }

        const bucketId = `${primary}|${resourceKey}|${c.day}|${timeCluster}`;
        let bucket = buckets.get(bucketId);
        if (!bucket) {
            bucket = {
                id: bucketId,
                category: primary,
                resourceLabel,
                day: c.day,
                sessionMap: new Map(),
                rawCount: 0,
            };
            buckets.set(bucketId, bucket);
        }
        bucket.rawCount++;
        bucket.sessionMap.set(s1.session.id!, s1);
        bucket.sessionMap.set(s2.session.id!, s2);
    }

    const issues: TriageIssue[] = [];

    for (const bucket of buckets.values()) {
        const sessions = [...bucket.sessionMap.values()].sort((a, b) => a.startMin - b.startMin);
        const windowStartMin = Math.min(...sessions.map((s) => s.startMin));
        const windowEndMin = Math.max(...sessions.map((s) => s.endMin));
        const timeSpan = `${formatMin12(windowStartMin)} – ${formatMin12(windowEndMin)}`;
        const dayPeriod = `${bucket.day} ${partOfDay(windowStartMin)}`;

        let headline = "";
        let subline = "";
        if (bucket.category === "room") {
            headline = `${bucket.resourceLabel} is double-booked on ${dayPeriod}`;
            subline = `${sessions.length} classes competing for the same room (${timeSpan})`;
        } else if (bucket.category === "faculty") {
            headline = `${bucket.resourceLabel} is double-booked on ${dayPeriod}`;
            subline = `Assigned to ${sessions.length} overlapping classes (${timeSpan})`;
        } else {
            headline = `${bucket.resourceLabel} has overlapping classes on ${dayPeriod}`;
            subline = `${sessions.length} classes collide for the same student group (${timeSpan})`;
        }

        issues.push({
            id: bucket.id,
            category: bucket.category,
            headline,
            subline,
            day: bucket.day,
            windowStartMin,
            windowEndMin,
            impactCount: bucket.rawCount,
            sessions,
        });
    }

    // Add missing-time classes as low-stress triage items
    for (const cls of classes) {
        const scheduled = scheduledHoursOf(cls.schedules);
        const required = cls.required_hours ?? 3;
        if (cls.schedules.length === 0 || scheduled < required) {
            const missing = Math.max(0.5, Math.round((required - scheduled) * 10) / 10);
            issues.push({
                id: `missing:${cls.id}`,
                category: "missing_time",
                headline: `${cls.subject_code} (Sec ${cls.section}) needs ${missing}h more`,
                subline: `${cls.subject_title} · Currently ${scheduled} of ${required} weekly hours scheduled`,
                day: "Any day",
                windowStartMin: 8 * 60,
                windowEndMin: 9 * 60 + 30,
                impactCount: 1,
                sessions: [],
                missingClass: cls,
                missingHoursLabel: `${scheduled}/${required}h`,
            });
        }
    }

    // Sort highest impact first (so resolving 1 issue clears the most conflicts right away)
    issues.sort((a, b) => {
        if (a.category === "missing_time" && b.category !== "missing_time") return 1;
        if (b.category === "missing_time" && a.category !== "missing_time") return -1;
        return b.impactCount - a.impactCount || b.sessions.length - a.sessions.length || a.headline.localeCompare(b.headline);
    });

    const conflictingClassIds = new Set<number>();
    for (const c of conflicts) {
        if (c.class_1.id !== undefined) conflictingClassIds.add(c.class_1.id);
        if (c.class_2.id !== undefined) conflictingClassIds.add(c.class_2.id);
    }
    const cleanClassesCount = classes.filter(
        (cls) => !conflictingClassIds.has(cls.id) && cls.schedules.length > 0 && scheduledHoursOf(cls.schedules) >= (cls.required_hours ?? 3),
    ).length;

    const countsByCategory: Record<TriageCategory, number> = {
        room: issues.filter((i) => i.category === "room").length,
        faculty: issues.filter((i) => i.category === "faculty").length,
        section: issues.filter((i) => i.category === "section").length,
        missing_time: issues.filter((i) => i.category === "missing_time").length,
    };

    const healthScorePct =
        classes.length === 0 ? 100 : Math.round((cleanClassesCount / classes.length) * 100);

    return {
        issues,
        rawConflictsCount: conflicts.length,
        rootIssuesCount: issues.length,
        cleanClassesCount,
        totalClassesCount: classes.length,
        healthScorePct,
        countsByCategory,
    };
}
