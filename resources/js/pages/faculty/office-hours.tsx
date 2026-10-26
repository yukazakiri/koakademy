import FacultyLayout from "@/components/faculty/faculty-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { User } from "@/types/user";
import { Head, useForm, router } from "@inertiajs/react";
import { route } from "ziggy-js";

interface Slot {
    id: number; day: string; start: string; end: string; mode: string;
    location: string | null; capacity: number; is_active: boolean;
    bookings: { id: number; student: string; topic: string | null }[];
}
interface Upcoming { id: number; student: string; day: string | null; time: string | null; topic: string | null; status: string }
interface Props { user: User; slots: Slot[]; upcoming: Upcoming[] }

const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

export default function OfficeHoursPage({ user, slots, upcoming }: Props) {
    const form = useForm<{ day_of_week: string; start_time: string; end_time: string; mode: string; location: string; capacity: string }>({
        day_of_week: "Monday", start_time: "13:00", end_time: "15:00", mode: "in_person", location: "", capacity: "1",
    });

    const saveSlot = () => form.post(route("faculty.office-hours.slots.store"), {
        preserveScroll: true,
        onSuccess: () => form.reset("location"),
    });

    const toggle = (id: number) => router.post(route("faculty.office-hours.slots.toggle", id), {}, { preserveScroll: true });
    const remove = (id: number) => router.delete(route("faculty.office-hours.slots.destroy", id), { preserveScroll: true });
    const setBookingStatus = (id: number, status: string) =>
        router.patch(route("faculty.office-hours.bookings.update", id), { status }, { preserveScroll: true });

    return (
        <FacultyLayout user={user}>
            <Head title="Office Hours" />
            <main className="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <header className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">Office Hours</h1>
                    <p className="text-muted-foreground text-sm">Weekly consultation slots and student bookings.</p>
                </header>

                <Card>
                    <CardHeader><CardTitle className="text-base">Add weekly slot</CardTitle></CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-6">
                        <div className="space-y-1 sm:col-span-2">
                            <Label>Day</Label>
                            <Select value={form.data.day_of_week} onValueChange={(v) => form.setData("day_of_week", v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>{days.map((d) => <SelectItem key={d} value={d}>{d}</SelectItem>)}</SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>Start</Label>
                            <Input type="time" value={form.data.start_time} onChange={(e) => form.setData("start_time", e.target.value)} />
                        </div>
                        <div className="space-y-1">
                            <Label>End</Label>
                            <Input type="time" value={form.data.end_time} onChange={(e) => form.setData("end_time", e.target.value)} />
                            {form.errors.end_time && <p className="text-destructive text-xs">{form.errors.end_time}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Mode</Label>
                            <Select value={form.data.mode} onValueChange={(v) => form.setData("mode", v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="in_person">In person</SelectItem>
                                    <SelectItem value="online">Online</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>Capacity</Label>
                            <Input type="number" min={1} max={50} value={form.data.capacity} onChange={(e) => form.setData("capacity", e.target.value)} />
                        </div>
                        <div className="space-y-1 sm:col-span-5">
                            <Label>Location / meeting link</Label>
                            <Input value={form.data.location} onChange={(e) => form.setData("location", e.target.value)} placeholder="Office 304 or Zoom link" />
                        </div>
                        <div className="flex items-end sm:col-span-1">
                            <Button onClick={saveSlot} disabled={form.processing} className="w-full">Add</Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {days.filter((d) => slots.some((s) => s.day === d)).map((day) => (
                        <Card key={day}>
                            <CardHeader className="pb-2"><CardTitle className="text-sm">{day}</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                {slots.filter((s) => s.day === day).map((slot) => (
                                    <div key={slot.id} className="rounded-lg border p-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold">{slot.start}–{slot.end}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {slot.mode === "online" ? "Online" : "In person"}{slot.location ? ` · ${slot.location}` : ""} · cap {slot.bookings.length}/{slot.capacity}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Button size="sm" variant="ghost" onClick={() => toggle(slot.id)}>{slot.is_active ? "Pause" : "Resume"}</Button>
                                                <Button size="sm" variant="ghost" className="text-destructive" onClick={() => remove(slot.id)}>✕</Button>
                                            </div>
                                        </div>
                                        {!slot.is_active && <Badge variant="outline" className="mt-2">paused</Badge>}
                                        {slot.bookings.map((b) => (
                                            <div key={b.id} className="bg-muted/40 mt-2 rounded-md px-2 py-1.5 text-xs">
                                                <span className="font-medium">{b.student}</span>{b.topic ? ` — ${b.topic}` : ""}
                                            </div>
                                        ))}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                    {slots.length === 0 && (
                        <Card className="md:col-span-2 xl:col-span-3">
                            <CardContent className="text-muted-foreground p-8 text-center text-sm">No slots configured yet.</CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardHeader><CardTitle className="text-base">Upcoming bookings</CardTitle></CardHeader>
                    <CardContent className="space-y-2">
                        {upcoming.length === 0 && <p className="text-muted-foreground text-sm">No bookings yet.</p>}
                        {upcoming.map((b) => (
                            <div key={b.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3">
                                <div>
                                    <p className="text-sm font-medium">{b.student}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {b.day} {b.time}{b.topic ? ` · ${b.topic}` : ""}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant="secondary" className="font-normal">{b.status}</Badge>
                                    {b.status === "booked" && (
                                        <>
                                            <Button size="sm" variant="outline" onClick={() => setBookingStatus(b.id, "completed")}>Mark done</Button>
                                            <Button size="sm" variant="ghost" className="text-destructive" onClick={() => setBookingStatus(b.id, "cancelled")}>Cancel</Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </main>
        </FacultyLayout>
    );
}
