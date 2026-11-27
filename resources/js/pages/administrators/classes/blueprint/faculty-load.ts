type Meeting = { start_time: string; end_time: string };
type Section = { class_id: number | null; faculty_id: string | null; schedules: Meeting[] };

function hours(meetings: Meeting[]): number {
    return meetings.reduce((sum, meeting) => {
        const parse = (time: string): number => {
            const [hour, minute] = time.split(':').map(Number);
            return hour * 60 + minute;
        };
        const start = parse(meeting.start_time);
        const end = parse(meeting.end_time);
        return Number.isFinite(start) && Number.isFinite(end) && end > start ? sum + (end - start) / 60 : sum;
    }, 0);
}

/** Net change to the server's current term load; removed rows are not changed on the server. */
export function facultyLoadDeltas(staged: Section[], persisted: Section[]): Map<string, number> {
    const originalById = new Map(persisted.filter((row) => row.class_id !== null).map((row) => [row.class_id, row]));
    const deltas = new Map<string, number>();
    const add = (id: string | null, amount: number) => {
        if (id !== null) deltas.set(String(id), (deltas.get(String(id)) ?? 0) + amount);
    };
    for (const row of staged) {
        const previous = row.class_id !== null ? originalById.get(row.class_id) : undefined;
        if (previous) add(previous.faculty_id, -hours(previous.schedules));
        add(row.faculty_id, hours(row.schedules));
    }
    return deltas;
}
