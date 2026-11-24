import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from "@/components/ui/sheet";
import { show as showClass } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Check,
    Clock,
    Loader2,
    MapPin,
    Monitor,
    Pencil,
    Sparkles,
    Trash2,
    User,
    Users,
} from "lucide-react";
import { useEffect, useState } from "react";
import {
    canonicalDeliveryMode,
    isOnlineDeliveryMode,
    type DeliveryMode,
} from "../../classes/lib/class-defaults";
import type {
    ResolutionSuggestions,
    RoomOption,
    SuggestedRoom,
    SuggestedSlot,
} from "../../components/scheduling-types";
import { formatMinutes12h, minutesToApiTime, type CanvasBlock } from "../lib/canvas-engine";

export type InlineBlockPatch = {
    day_of_week: string;
    start_time: string;
    end_time: string;
    delivery_mode: DeliveryMode;
    room_id: number | null;
};

interface BlockPopoverProps {
    block: CanvasBlock | null;
    initialMode?: "view" | "edit";
    contextTab?: "rooms" | "students" | "teachers";
    readOnly?: boolean;
    rooms: RoomOption[];
    days: string[];
    suggestions?: ResolutionSuggestions;
    loadingSuggestions?: boolean;
    onClose: () => void;
    onSaveEdit: (block: CanvasBlock, patch: InlineBlockPatch) => Promise<void>;
    onDelete?: (block: CanvasBlock) => Promise<void> | void;
    onApplyRoom?: (block: CanvasBlock, room: SuggestedRoom) => Promise<void>;
    onApplySlot?: (block: CanvasBlock, slot: SuggestedSlot) => Promise<void>;
    onConvertOnline?: (block: CanvasBlock) => Promise<void>;
}

export default function BlockPopover({
    block,
    initialMode = "view",
    contextTab = "rooms",
    readOnly = false,
    rooms,
    days,
    suggestions,
    loadingSuggestions = false,
    onClose,
    onSaveEdit,
    onDelete,
    onApplyRoom,
    onApplySlot,
    onConvertOnline,
}: BlockPopoverProps) {
    const [sheetMode, setSheetMode] = useState<"view" | "edit">(initialMode);
    const [applyingKey, setApplyingKey] = useState<string | null>(null);
    const [savingEdit, setSavingEdit] = useState(false);

    // Inline edit form state (inside the Sliding Sheet — zero center modal!)
    const [day, setDay] = useState("Monday");
    const [startTime, setStartTime] = useState("08:00");
    const [endTime, setEndTime] = useState("09:30");
    const [deliveryMode, setDeliveryMode] = useState<DeliveryMode>("face_to_face");
    const [roomId, setRoomId] = useState<string>("none");

    useEffect(() => {
        if (!block) return;
        setSheetMode(readOnly ? "view" : initialMode);
        setDay(block.day);
        setStartTime(minutesToApiTime(block.startMin));
        setEndTime(minutesToApiTime(block.endMin));
        setDeliveryMode(
            canonicalDeliveryMode(block.session.delivery_mode ?? block.classItem.delivery_mode),
        );
        setRoomId(block.session.room_id != null ? String(block.session.room_id) : "none");
    }, [block?.key, block?.day, block?.startMin, block?.endMin, block?.session.room_id, initialMode, readOnly]);

    const handleRun = async (key: string, fn: () => Promise<void>) => {
        setApplyingKey(key);
        try {
            await fn();
            onClose();
        } finally {
            setApplyingKey(null);
        }
    };

    const isOnline = isOnlineDeliveryMode(deliveryMode);
    const validTimes = Boolean(startTime && endTime && endTime > startTime);

    const handleSubmitEdit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!block || !validTimes || savingEdit) return;
        setSavingEdit(true);
        try {
            await onSaveEdit(block, {
                day_of_week: day,
                start_time: startTime,
                end_time: endTime,
                delivery_mode: deliveryMode,
                room_id: isOnline || roomId === "none" ? null : Number(roomId),
            });
            onClose();
        } finally {
            setSavingEdit(false);
        }
    };

    return (
        <Sheet open={Boolean(block)} onOpenChange={(open) => !open && onClose()}>
            <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-md">
                {block && (
                    <>
                        {/* Clean Sliding Sheet Header */}
                        <SheetHeader
                            className={`border-b px-5 py-4 pr-11 text-left ${
                                block.hasConflict
                                    ? "border-rose-500/20 bg-rose-500/[0.06]"
                                    : "border-border/60 bg-muted/20"
                            }`}
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                {sheetMode === "edit" && (
                                    <button
                                        type="button"
                                        onClick={() => setSheetMode("view")}
                                        className="text-muted-foreground hover:text-foreground hover:bg-muted -ml-1 inline-flex size-6 items-center justify-center rounded-md transition-colors"
                                        aria-label="Back to details"
                                    >
                                        <ArrowLeft className="size-3.5" />
                                    </button>
                                )}
                                <SheetTitle className="text-base font-bold tracking-tight">
                                    {block.classItem.subject_code}
                                </SheetTitle>
                                <Badge variant="secondary" className="h-5 text-[11px]">
                                    Sec {block.classItem.section}
                                </Badge>
                                {block.hasConflict && (
                                    <Badge className="h-5 gap-1 bg-rose-600 text-[10px] text-white">
                                        <AlertTriangle className="size-3" />
                                        Conflict
                                    </Badge>
                                )}
                            </div>
                            <SheetDescription className="text-muted-foreground line-clamp-2 text-xs">
                                {sheetMode === "edit"
                                    ? `Editing session details · ${block.classItem.subject_title}`
                                    : block.classItem.subject_title}
                            </SheetDescription>
                        </SheetHeader>

                        {sheetMode === "edit" && !readOnly ? (
                            <form onSubmit={handleSubmitEdit} className="flex min-h-0 flex-1 flex-col">
                                <div className="flex-1 space-y-4 overflow-y-auto p-5">
                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">Day of week</Label>
                                        <Select value={day} onValueChange={setDay}>
                                            <SelectTrigger className="h-9 rounded-xl text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {days.map((d) => (
                                                    <SelectItem key={d} value={d}>
                                                        {d}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="sheet-start-time" className="text-xs font-medium">
                                                Start time
                                            </Label>
                                            <Input
                                                id="sheet-start-time"
                                                type="time"
                                                value={startTime}
                                                onChange={(e) => setStartTime(e.target.value)}
                                                className="h-9 rounded-xl text-xs tabular-nums"
                                                required
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="sheet-end-time" className="text-xs font-medium">
                                                End time
                                            </Label>
                                            <Input
                                                id="sheet-end-time"
                                                type="time"
                                                value={endTime}
                                                onChange={(e) => setEndTime(e.target.value)}
                                                className="h-9 rounded-xl text-xs tabular-nums"
                                                required
                                            />
                                        </div>
                                    </div>
                                    {!validTimes && (
                                        <p className="text-destructive text-[11px]">
                                            End time must be later than start time.
                                        </p>
                                    )}

                                    <div className="space-y-1.5">
                                        <Label className="text-xs font-medium">Delivery mode</Label>
                                        <Select
                                            value={deliveryMode}
                                            onValueChange={(v) => setDeliveryMode(v as DeliveryMode)}
                                        >
                                            <SelectTrigger className="h-9 rounded-xl text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="face_to_face">Face-to-Face (On-Campus)</SelectItem>
                                                <SelectItem value="online_synchronous">Online (Synchronous)</SelectItem>
                                                <SelectItem value="online_asynchronous">Online (Asynchronous)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {!isOnline && (
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-medium">Assigned room</Label>
                                            <Select value={roomId} onValueChange={setRoomId}>
                                                <SelectTrigger className="h-9 rounded-xl text-xs">
                                                    <SelectValue placeholder="Choose a room" />
                                                </SelectTrigger>
                                                <SelectContent className="max-h-64">
                                                    <SelectItem value="none">No room assigned</SelectItem>
                                                    {rooms.map((r) => (
                                                        <SelectItem key={r.id} value={String(r.id)}>
                                                            {r.name} ({r.capacity ?? 40} seats)
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}
                                </div>

                                <SheetFooter className="border-border/60 flex-row items-center justify-between border-t px-5 py-3.5">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-8 rounded-lg text-xs"
                                        onClick={() => setSheetMode("view")}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={!validTimes || savingEdit}
                                        className="h-8 gap-1.5 rounded-lg text-xs"
                                    >
                                        {savingEdit ? (
                                            <Loader2 className="size-3.5 animate-spin" />
                                        ) : (
                                            <Check className="size-3.5" />
                                        )}
                                        Save changes
                                    </Button>
                                </SheetFooter>
                            </form>
                        ) : (
                            <>
                                {/* Scrollable Sheet View Body */}
                                <div className="flex-1 space-y-5 overflow-y-auto p-5">
                                    {/* Essential Metadata Grid */}
                                    <div className="grid grid-cols-2 gap-3 text-xs">
                                        <div className="bg-muted/25 border-border/50 flex items-start gap-2.5 rounded-xl border p-3">
                                            <Clock className="text-muted-foreground mt-0.5 size-3.5 shrink-0" />
                                            <div>
                                                <p className="font-semibold">{block.day}</p>
                                                <p className="text-muted-foreground mt-0.5 text-[11px] tabular-nums">
                                                    {formatMinutes12h(block.startMin)} – {formatMinutes12h(block.endMin)}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="bg-muted/25 border-border/50 flex items-start gap-2.5 rounded-xl border p-3">
                                            {block.isOnline ? (
                                                <Monitor className="text-muted-foreground mt-0.5 size-3.5 shrink-0" />
                                            ) : (
                                                <MapPin className="text-muted-foreground mt-0.5 size-3.5 shrink-0" />
                                            )}
                                            <div className="min-w-0">
                                                <p className="truncate font-semibold">
                                                    {block.isOnline ? "Online" : block.session.room ?? "No room"}
                                                </p>
                                                <p className="text-muted-foreground mt-0.5 text-[11px] tabular-nums">
                                                    {block.isOnline
                                                        ? "Virtual session"
                                                        : block.seatCapacity > 0
                                                          ? `${block.seatCount} / ${block.seatCapacity} seats`
                                                          : `${block.seatCount} students`}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="bg-muted/25 border-border/50 flex items-start gap-2.5 rounded-xl border p-3">
                                            <User className="text-muted-foreground mt-0.5 size-3.5 shrink-0" />
                                            <div className="min-w-0">
                                                <p className="truncate font-semibold">
                                                    {block.classItem.faculty_name ?? "Unassigned"}
                                                </p>
                                                <p className="text-muted-foreground mt-0.5 text-[11px]">Instructor</p>
                                            </div>
                                        </div>

                                        <div className="bg-muted/25 border-border/50 flex items-start gap-2.5 rounded-xl border p-3">
                                            <Users className="text-muted-foreground mt-0.5 size-3.5 shrink-0" />
                                            <div className="min-w-0">
                                                <p className="truncate font-semibold">
                                                    {block.classItem.courses || "General"}
                                                </p>
                                                <p className="text-muted-foreground mt-0.5 truncate text-[11px]">
                                                    {block.classItem.grade_level ?? block.classItem.year_levels?.[0] ?? "Term cohort"}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Tab-Aware 1-Click Smart Fixes */}
                                    {!readOnly && block.hasConflict && (
                                        <div className="border-border/60 bg-muted/20 space-y-2.5 rounded-2xl border p-3.5">
                                            <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                                <Sparkles className="text-primary size-3.5" />
                                                Quick 1-Click Fixes
                                            </p>

                                            {loadingSuggestions ? (
                                                <div className="text-muted-foreground flex items-center gap-2 py-3 text-xs">
                                                    <Loader2 className="size-3.5 animate-spin" />
                                                    Finding open rooms &amp; conflict-free times…
                                                </div>
                                            ) : (
                                                <div className="space-y-1.5">
                                                    {contextTab === "rooms" &&
                                                        suggestions?.available_rooms.slice(0, 2).map((room) => (
                                                            <button
                                                                key={`room-${room.room_id}`}
                                                                type="button"
                                                                disabled={applyingKey !== null}
                                                                onClick={() =>
                                                                    onApplyRoom &&
                                                                    void handleRun(`room-${room.room_id}`, () =>
                                                                        onApplyRoom(block, room),
                                                                    )
                                                                }
                                                                className="bg-background hover:bg-emerald-500/10 hover:border-emerald-500/40 flex w-full items-center justify-between gap-2 rounded-xl border px-3.5 py-2.5 text-left text-xs transition-colors"
                                                            >
                                                                <div className="min-w-0">
                                                                    <p className="truncate font-semibold">Move to {room.name}</p>
                                                                    <p className="text-muted-foreground truncate text-[11px]">
                                                                        Same time · Cap {room.capacity}
                                                                    </p>
                                                                </div>
                                                                {applyingKey === `room-${room.room_id}` ? (
                                                                    <Loader2 className="size-3.5 shrink-0 animate-spin" />
                                                                ) : (
                                                                    <ArrowRight className="text-muted-foreground size-3.5 shrink-0" />
                                                                )}
                                                            </button>
                                                        ))}

                                                    {suggestions?.available_slots
                                                        .slice(0, contextTab === "rooms" ? 2 : 3)
                                                        .map((slot) => (
                                                            <button
                                                                key={`slot-${slot.day_of_week}-${slot.start_time}`}
                                                                type="button"
                                                                disabled={applyingKey !== null}
                                                                onClick={() =>
                                                                    onApplySlot &&
                                                                    void handleRun(
                                                                        `slot-${slot.day_of_week}-${slot.start_time}`,
                                                                        () => onApplySlot(block, slot),
                                                                    )
                                                                }
                                                                className="bg-background hover:bg-muted/60 flex w-full items-center justify-between gap-2 rounded-xl border px-3.5 py-2.5 text-left text-xs transition-colors"
                                                            >
                                                                <div className="min-w-0">
                                                                    <p className="truncate font-semibold">
                                                                        Move to {slot.day_of_week} · {slot.time_range}
                                                                    </p>
                                                                    <p className="text-muted-foreground truncate text-[11px]">
                                                                        {slot.room_name ?? "Open slot"}
                                                                    </p>
                                                                </div>
                                                                {applyingKey ===
                                                                `slot-${slot.day_of_week}-${slot.start_time}` ? (
                                                                    <Loader2 className="size-3.5 shrink-0 animate-spin" />
                                                                ) : (
                                                                    <ArrowRight className="text-muted-foreground size-3.5 shrink-0" />
                                                                )}
                                                            </button>
                                                        ))}

                                                    {suggestions?.can_convert_to_online && onConvertOnline && (
                                                        <button
                                                            type="button"
                                                            disabled={applyingKey !== null}
                                                            onClick={() =>
                                                                void handleRun("online", () => onConvertOnline(block))
                                                            }
                                                            className="bg-background hover:bg-muted/60 flex w-full items-center justify-between gap-2 rounded-xl border px-3.5 py-2.5 text-left text-xs transition-colors"
                                                        >
                                                            <div className="min-w-0">
                                                                <p className="truncate font-semibold">
                                                                    Switch session to Online
                                                                </p>
                                                                <p className="text-muted-foreground truncate text-[11px]">
                                                                    Frees the physical room immediately
                                                                </p>
                                                            </div>
                                                            {applyingKey === "online" ? (
                                                                <Loader2 className="size-3.5 shrink-0 animate-spin" />
                                                            ) : (
                                                                <ArrowRight className="text-muted-foreground size-3.5 shrink-0" />
                                                            )}
                                                        </button>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {/* Direct Class Record Link */}
                                    <Link
                                        href={showClass.url(block.classItem.id)}
                                        className="border-border/60 hover:bg-muted/40 flex items-center justify-between rounded-xl border px-3.5 py-2.5 text-xs font-medium transition-colors"
                                    >
                                        <span>Open full class record &amp; roster</span>
                                        <ArrowUpRight className="text-muted-foreground size-4" />
                                    </Link>
                                </div>

                                {/* Sticky Sliding Sheet Footer */}
                                {!readOnly && (
                                    <SheetFooter className="border-border/60 flex-row items-center justify-between border-t px-5 py-3.5">
                                        {onDelete && block.session.id !== undefined ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="text-muted-foreground hover:text-destructive h-8 gap-1.5 rounded-lg text-xs"
                                                onClick={() => {
                                                    void onDelete(block);
                                                    onClose();
                                                }}
                                            >
                                                <Trash2 className="size-3.5" />
                                                Remove
                                            </Button>
                                        ) : (
                                            <span />
                                        )}

                                        <Button
                                            type="button"
                                            size="sm"
                                            className="h-8 gap-1.5 rounded-lg text-xs"
                                            onClick={() => setSheetMode("edit")}
                                        >
                                            <Pencil className="size-3" />
                                            Edit details
                                        </Button>
                                    </SheetFooter>
                                )}
                            </>
                        )}
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
