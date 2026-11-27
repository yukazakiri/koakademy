import type { CrudRoomOption, CrudScheduleBlock } from "../crud/types";

type Row = { client_key: string; class_id: number | null; section: string; faculty_id: string | null; maximum_slots: number; room_id: number | null; schedules: CrudScheduleBlock[] };
type LectureBand = { days: string[]; start_time: string; end_time: string };
type LabBand = { day: string; start_time: string; end_time: string };
type Reservation = { owner: string; day: string; start: string; end: string; room: number | null; faculty: string | null };

function minutes(value: string): number {
    const [hour, minute] = value.split(":").map(Number);
    return hour * 60 + minute;
}

function overlaps(aStart: string, aEnd: string, bStart: string, bEnd: string): boolean {
    return minutes(aStart) < minutes(bEnd) && minutes(aEnd) > minutes(bStart);
}

function online(mode: string): boolean {
    return mode === "online" || mode === "online_asynchronous" || mode === "online_synchronous";
}

/** Reserve all original schedules until their own replacement is ready. */
export function autoStagger<T extends Row>(rows: T[], blocking: Set<string>, rooms: CrudRoomOption[], mode: string, needsLab: boolean, lectureBands: LectureBand[], labBands: LabBand[]) {
    let reservations: Reservation[] = rows.flatMap((row) => row.schedules.map((slot) => ({
        owner: row.client_key, day: slot.day_of_week, start: slot.start_time, end: slot.end_time,
        room: online(slot.delivery_mode) ? null : slot.room_id, faculty: row.faculty_id,
    })));
    const skipped: string[] = [];
    let shifted = 0;
    const nextRows = rows.map((row, index) => {
        if (row.class_id !== null && row.schedules.length > 0 && !blocking.has(row.client_key)) return row;
        const free = (day: string, start: string, end: string, room: number | null, faculty: string | null) =>
            !reservations.some((entry) => entry.owner !== row.client_key && entry.day === day && overlaps(entry.start, entry.end, start, end)
                && ((room !== null && entry.room === room) || (faculty !== null && entry.faculty === faculty)));
        const pickRoom = (days: string[], start: string, end: string, preferLab: boolean): number | null => {
            if (online(mode) || rooms.length === 0) return null;
            const sorted = [...rooms].sort((a, b) => {
                const aLab = `${a.type ?? ""} ${a.label}`.toLowerCase().includes("lab");
                const bLab = `${b.type ?? ""} ${b.label}`.toLowerCase().includes("lab");
                if (aLab !== bLab) return preferLab ? (aLab ? -1 : 1) : (aLab ? 1 : -1);
                const aFits = !a.capacity || a.capacity >= row.maximum_slots;
                const bFits = !b.capacity || b.capacity >= row.maximum_slots;
                if (aFits !== bFits) return aFits ? -1 : 1;
                return (a.capacity ?? 999) - (b.capacity ?? 999);
            });
            const found = sorted.find((room) => days.every((day) => free(day, start, end, Number(room.id), null)));
            return found ? Number(found.id) : null;
        };

        let replacement: CrudScheduleBlock[] | null = null;
        let selectedRoom: number | null = null;
        for (let attempt = 0; attempt < lectureBands.length && !replacement; attempt++) {
            const lecture = lectureBands[(index + attempt) % lectureBands.length];
            if (!lecture.days.every((day) => free(day, lecture.start_time, lecture.end_time, null, row.faculty_id))) continue;
            const room = !online(mode) && row.room_id !== null && lecture.days.every((day) => free(day, lecture.start_time, lecture.end_time, row.room_id, null))
                ? row.room_id : pickRoom(lecture.days, lecture.start_time, lecture.end_time, false);
            if (!online(mode) && rooms.length > 0 && room === null) continue;
            const slots: CrudScheduleBlock[] = lecture.days.map((day) => ({ day_of_week: day, start_time: lecture.start_time, end_time: lecture.end_time, room_id: room, delivery_mode: mode, component_type: "lecture" }));
            if (needsLab) {
                let labSlot: CrudScheduleBlock | null = null;
                for (let labAttempt = 0; labAttempt < labBands.length; labAttempt++) {
                    const lab = labBands[(index + labAttempt) % labBands.length];
                    if (!free(lab.day, lab.start_time, lab.end_time, null, row.faculty_id)) continue;
                    if (lecture.days.includes(lab.day) && overlaps(lecture.start_time, lecture.end_time, lab.start_time, lab.end_time)) continue;
                    const labRoom = pickRoom([lab.day], lab.start_time, lab.end_time, true);
                    if (!online(mode) && rooms.length > 0 && labRoom === null) continue;
                    labSlot = { day_of_week: lab.day, start_time: lab.start_time, end_time: lab.end_time, room_id: labRoom, delivery_mode: mode, component_type: "lab" };
                    break;
                }
                if (!labSlot) continue;
                slots.push(labSlot);
            }
            replacement = slots;
            selectedRoom = room;
        }
        if (!replacement) {
            skipped.push(row.section || "(unnamed)");
            return row;
        }
        reservations = reservations.filter((entry) => entry.owner !== row.client_key);
        reservations.push(...replacement.map((slot) => ({ owner: row.client_key, day: slot.day_of_week, start: slot.start_time, end: slot.end_time, room: slot.room_id, faculty: row.faculty_id })));
        shifted++;
        return { ...row, room_id: online(mode) ? null : (selectedRoom ?? row.room_id), schedules: replacement };
    });
    return { rows: nextRows, skipped, shifted };
}
