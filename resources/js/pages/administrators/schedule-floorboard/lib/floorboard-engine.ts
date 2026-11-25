import { isOnlineDeliveryMode } from "../../classes/lib/class-defaults";
import type {
    ClassScheduleData,
    RoomOption,
    ScheduleConflict,
    ScheduleEntry,
} from "../../components/scheduling-types";
import { parseTimeToMinutes } from "../../scheduling/lib/time-geometry";

export type TimeWaveId = "morning" | "midday" | "afternoon" | "evening";

export type TimeWave = {
    id: TimeWaveId;
    label: string;
    rangeLabel: string;
    startMin: number;
    endMin: number;
    defaultStartApi: string;
    defaultEndApi: string;
};

export const CAMPUS_WAVES: TimeWave[] = [
    {
        id: "morning",
        label: "Morning",
        rangeLabel: "7:00 – 10:30 AM",
        startMin: 7 * 60,
        endMin: 10 * 60 + 30,
        defaultStartApi: "08:00",
        defaultEndApi: "09:30",
    },
    {
        id: "midday",
        label: "Midday",
        rangeLabel: "10:30 AM – 1:30 PM",
        startMin: 10 * 60 + 30,
        endMin: 13 * 60 + 30,
        defaultStartApi: "11:00",
        defaultEndApi: "12:30",
    },
    {
        id: "afternoon",
        label: "Afternoon",
        rangeLabel: "1:30 – 5:00 PM",
        startMin: 13 * 60 + 30,
        endMin: 17 * 60,
        defaultStartApi: "14:00",
        defaultEndApi: "15:30",
    },
    {
        id: "evening",
        label: "Evening",
        rangeLabel: "5:00 – 9:00 PM",
        startMin: 17 * 60,
        endMin: 21 * 60,
        defaultStartApi: "17:30",
        defaultEndApi: "19:00",
    },
];

export type RoomTicket = {
    key: string;
    classItem: ClassScheduleData;
    session: ScheduleEntry;
    startMin: number;
    endMin: number;
    hasRoomConflict: boolean;
    hasAnyConflict: boolean;
    conflictReasons: string[];
};

export type FloorRoomCardData = {
    room: RoomOption;
    status: "open" | "occupied" | "double_booked";
    tickets: RoomTicket[];
    competingCount: number;
    peakStudents: number;
    capacity: number;
    overcrowded: boolean;
};

export type WaveSummary = {
    wave: TimeWave;
    openRoomsCount: number;
    occupiedRoomsCount: number;
    doubleBookedRoomsCount: number;
    totalTickets: number;
};

export function buildFloorboardForDayWave(params: {
    day: string;
    wave: TimeWave;
    rooms: RoomOption[];
    classes: ClassScheduleData[];
    conflicts: ScheduleConflict[];
}): {
    roomCards: FloorRoomCardData[];
    onlineTickets: RoomTicket[];
    unassignedTickets: RoomTicket[];
    openRooms: RoomOption[];
} {
    const { day, wave, rooms, classes, conflicts } = params;

    const conflictsByScheduleId = new Map<number, ScheduleConflict[]>();
    for (const c of conflicts) {
        for (const sid of [c.schedule_1_id, c.schedule_2_id]) {
            if (typeof sid === "number") {
                const list = conflictsByScheduleId.get(sid) ?? [];
                list.push(c);
                conflictsByScheduleId.set(sid, list);
            }
        }
    }

    const byRoomId = new Map<number, RoomTicket[]>();
    for (const room of rooms) byRoomId.set(room.id, []);

    const onlineTickets: RoomTicket[] = [];
    const unassignedTickets: RoomTicket[] = [];

    for (const classItem of classes) {
        classItem.schedules.forEach((session, idx) => {
            if (session.day_of_week !== day) return;
            const startMin = parseTimeToMinutes(session.start_time);
            const endMin = Math.max(startMin + 30, parseTimeToMinutes(session.end_time));
            // Overlaps this wave window
            if (startMin >= wave.endMin || endMin <= wave.startMin) return;

            const sessionConflicts = session.id !== undefined ? (conflictsByScheduleId.get(session.id) ?? []) : [];
            const conflictKinds = new Set<string>();
            for (const c of sessionConflicts) {
                for (const k of c.conflict_types ?? [c.conflict_type]) {
                    conflictKinds.add(k);
                }
            }

            const ticket: RoomTicket = {
                key: `${classItem.id}:${session.id ?? idx}`,
                classItem,
                session,
                startMin,
                endMin,
                hasRoomConflict: conflictKinds.has("room"),
                hasAnyConflict: sessionConflicts.length > 0,
                conflictReasons: [...conflictKinds],
            };

            const online = isOnlineDeliveryMode(session.delivery_mode ?? classItem.delivery_mode);
            if (online) {
                onlineTickets.push(ticket);
            } else if (session.room_id != null && byRoomId.has(Number(session.room_id))) {
                byRoomId.get(Number(session.room_id))!.push(ticket);
            } else {
                unassignedTickets.push(ticket);
            }
        });
    }

    const roomCards: FloorRoomCardData[] = rooms.map((room) => {
        const tickets = (byRoomId.get(room.id) ?? []).sort((a, b) => a.startMin - b.startMin);
        // Check exact pairwise overlap among classes inside this room during this wave
        const competingKeys = new Set<string>();
        for (let i = 0; i < tickets.length; i++) {
            for (let j = i + 1; j < tickets.length; j++) {
                if (tickets[i].startMin < tickets[j].endMin && tickets[i].endMin > tickets[j].startMin) {
                    competingKeys.add(tickets[i].key);
                    competingKeys.add(tickets[j].key);
                }
            }
        }
        const competingCount = competingKeys.size;
        const status: FloorRoomCardData["status"] =
            tickets.length === 0 ? "open" : competingCount >= 2 ? "double_booked" : "occupied";

        const peakStudents = tickets.reduce((max, t) => Math.max(max, t.classItem.student_count ?? 0), 0);
        const capacity = room.capacity ?? 40;
        const overcrowded = capacity > 0 && peakStudents > capacity;

        return {
            room,
            status,
            tickets,
            competingCount,
            peakStudents,
            capacity,
            overcrowded,
        };
    });

    // Sort: double-booked first so admins spot competing rooms immediately, then occupied, then open
    const statusOrder = { double_booked: 0, occupied: 1, open: 2 };
    roomCards.sort(
        (a, b) => statusOrder[a.status] - statusOrder[b.status] || a.room.name.localeCompare(b.room.name),
    );

    const openRooms = roomCards.filter((c) => c.status === "open").map((c) => c.room);

    return {
        roomCards,
        onlineTickets,
        unassignedTickets,
        openRooms,
    };
}

export function buildWaveSummariesForDay(params: {
    day: string;
    rooms: RoomOption[];
    classes: ClassScheduleData[];
    conflicts: ScheduleConflict[];
}): WaveSummary[] {
    return CAMPUS_WAVES.map((wave) => {
        const built = buildFloorboardForDayWave({ ...params, wave });
        return {
            wave,
            openRoomsCount: built.roomCards.filter((r) => r.status === "open").length,
            occupiedRoomsCount: built.roomCards.filter((r) => r.status === "occupied").length,
            doubleBookedRoomsCount: built.roomCards.filter((r) => r.status === "double_booked").length,
            totalTickets: built.roomCards.reduce((sum, r) => sum + r.tickets.length, 0),
        };
    });
}
