import { useCallback, useEffect, useState } from "react";
import type { DeliveryMode } from "../../classes/lib/class-defaults";

export type ScheduleBlockSnapshot = {
    schedule_id: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number | null;
    delivery_mode: DeliveryMode;
    component_type?: string;
    status?: string;
};

export type HistoryEntry = {
    label: string;
    before: ScheduleBlockSnapshot[];
    after: ScheduleBlockSnapshot[];
};

const MAX_HISTORY = 30;

export function useScheduleHistory(onApplySnapshots: (snapshots: ScheduleBlockSnapshot[], label: string) => Promise<void>) {
    const [past, setPast] = useState<HistoryEntry[]>([]);
    const [future, setFuture] = useState<HistoryEntry[]>([]);
    const [replaying, setReplaying] = useState(false);

    const record = useCallback((entry: HistoryEntry) => {
        if (entry.before.length === 0 || entry.after.length === 0) return;
        setPast((current) => [...current.slice(-(MAX_HISTORY - 1)), entry]);
        setFuture([]);
    }, []);

    const undo = useCallback(async () => {
        if (replaying || past.length === 0) return;
        const target = past[past.length - 1];
        setReplaying(true);
        try {
            await onApplySnapshots(target.before, `Undid: ${target.label}`);
            setPast((current) => current.slice(0, -1));
            setFuture((current) => [...current, target]);
        } finally {
            setReplaying(false);
        }
    }, [onApplySnapshots, past, replaying]);

    const redo = useCallback(async () => {
        if (replaying || future.length === 0) return;
        const target = future[future.length - 1];
        setReplaying(true);
        try {
            await onApplySnapshots(target.after, `Redid: ${target.label}`);
            setFuture((current) => current.slice(0, -1));
            setPast((current) => [...current, target]);
        } finally {
            setReplaying(false);
        }
    }, [future, onApplySnapshots, replaying]);

    const clear = useCallback(() => {
        setPast([]);
        setFuture([]);
    }, []);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            if (target && (target.tagName === "INPUT" || target.tagName === "TEXTAREA" || target.isContentEditable)) {
                return;
            }
            const meta = event.ctrlKey || event.metaKey;
            if (!meta) return;

            if (event.key.toLowerCase() === "z" && !event.shiftKey) {
                if (past.length > 0) {
                    event.preventDefault();
                    void undo();
                }
            } else if ((event.key.toLowerCase() === "z" && event.shiftKey) || event.key.toLowerCase() === "y") {
                if (future.length > 0) {
                    event.preventDefault();
                    void redo();
                }
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [past.length, future.length, undo, redo]);

    return {
        canUndo: past.length > 0 && !replaying,
        canRedo: future.length > 0 && !replaying,
        undoLabel: past[past.length - 1]?.label ?? null,
        redoLabel: future[future.length - 1]?.label ?? null,
        replaying,
        record,
        undo,
        redo,
        clear,
    };
}
