export type ReadinessClass = {
    id: number;
    subject_code: string;
    section: string;
    faculty_name: string | null;
    delivery_mode?: "face_to_face" | "online";
    required_hours?: number;
    schedules: { start_time: string; end_time: string; room_id?: number | null; status?: string }[];
};

export function scheduleMinutes(value: string): number {
    const match = value.trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
    if (!match) return 0;
    let hour = Number(match[1]);
    if (match[3]) hour = (hour % 12) + (match[3].toUpperCase() === "PM" ? 12 : 0);
    return hour * 60 + Number(match[2]);
}

export function scheduledHours(schedules: ReadinessClass["schedules"]): number {
    return (
        schedules.reduce((total, schedule) => total + Math.max(0, scheduleMinutes(schedule.end_time) - scheduleMinutes(schedule.start_time)), 0) / 60
    );
}

export function getSchedulingReadiness(classes: ReadinessClass[]) {
    return classes.map((item) => {
        const hours = scheduledHours(item.schedules);
        const required = item.required_hours ?? 3;
        const warnings: string[] = [];
        if (item.schedules.length === 0) warnings.push("No sessions scheduled");
        else if (hours < required) warnings.push(`${Number(hours.toFixed(2))} of ${required} weekly hours`);
        if (!item.faculty_name) warnings.push("Instructor not assigned");
        if (item.delivery_mode !== "online" && item.schedules.some((schedule) => !schedule.room_id))
            warnings.push("Room not assigned to every session");
        return { ...item, scheduled_hours: hours, warnings };
    });
}
