import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { DoorOpen, Save } from "lucide-react";
import type { FormEvent } from "react";
import { route } from "ziggy-js";

declare const route: any;

interface SchoolOption {
    id: number;
    name: string;
}

interface RoomTypeOption {
    value: string;
    label: string;
}

interface RoomRecord {
    id: number;
    school_id: number | null;
    name: string;
    class_code: string | null;
    type: string;
    capacity: number;
    building: string | null;
    is_active: boolean;
}

interface Props {
    user: User;
    room: RoomRecord | null;
    schools: SchoolOption[];
    roomTypes: RoomTypeOption[];
}

export default function RoomEdit({ user, room, schools, roomTypes }: Props) {
    const form = useForm({
        school_id: room?.school_id ? String(room.school_id) : "none",
        name: room?.name ?? "",
        class_code: room?.class_code ?? "",
        type: room?.type ?? "lecture",
        capacity: String(room?.capacity ?? 40),
        building: room?.building ?? "",
        is_active: room?.is_active ?? true,
    });

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (room) {
            form.put(route("administrators.rooms.update", room.id), {
                preserveScroll: true,
            });
            return;
        }

        form.post(route("administrators.rooms.store"), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout user={user} title={room ? "Edit Room" : "Add Room"}>
            <Head title={`Administrators • ${room ? "Edit" : "Add"} Room`} />

            <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-blue-500/10 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600">
                                <DoorOpen className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle>{room ? "Update Room" : "Create Room"}</CardTitle>
                                <CardDescription>Manage room details, capacity, and scheduling availability.</CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" asChild>
                                <Link href={route("administrators.rooms.index")}>Back to rooms</Link>
                            </Button>
                            <Button type="submit" className="gap-2" disabled={form.processing}>
                                <Save className="h-4 w-4" />
                                {room ? "Save changes" : "Create room"}
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Room Details</CardTitle>
                        <CardDescription>Fill in the room information below.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="name">Room Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData("name", e.target.value)}
                                placeholder="e.g. Room 101, Computer Lab A"
                            />
                            {form.errors.name && <p className="text-destructive text-xs">{form.errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="class_code">Class Code</Label>
                            <Input
                                id="class_code"
                                value={form.data.class_code}
                                onChange={(e) => form.setData("class_code", e.target.value)}
                                placeholder="e.g. R-101, CL-101"
                            />
                            {form.errors.class_code && <p className="text-destructive text-xs">{form.errors.class_code}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="type">Room Type</Label>
                            <Select
                                value={form.data.type}
                                onValueChange={(value) => form.setData("type", value)}
                            >
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="Select room type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roomTypes.map((typeOption) => (
                                        <SelectItem key={typeOption.value} value={typeOption.value}>
                                            {typeOption.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.type && <p className="text-destructive text-xs">{form.errors.type}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="capacity">Capacity (Seats)</Label>
                            <Input
                                id="capacity"
                                type="number"
                                min={1}
                                max={5000}
                                value={form.data.capacity}
                                onChange={(e) => form.setData("capacity", e.target.value)}
                                placeholder="40"
                            />
                            {form.errors.capacity && <p className="text-destructive text-xs">{form.errors.capacity}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="building">Building / Location</Label>
                            <Input
                                id="building"
                                value={form.data.building}
                                onChange={(e) => form.setData("building", e.target.value)}
                                placeholder="e.g. Main Building, 2nd Floor"
                            />
                            {form.errors.building && <p className="text-destructive text-xs">{form.errors.building}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="school">School</Label>
                            <Select
                                value={form.data.school_id}
                                onValueChange={(value) => form.setData("school_id", value)}
                            >
                                <SelectTrigger id="school">
                                    <SelectValue placeholder="Default / Current School" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Default / Current School</SelectItem>
                                    {schools.map((school) => (
                                        <SelectItem key={school.id} value={String(school.id)}>
                                            {school.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.school_id && <p className="text-destructive text-xs">{form.errors.school_id}</p>}
                        </div>

                        <div className="bg-muted/30 border-border/50 flex items-center gap-3 rounded-lg border p-3 sm:col-span-2">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) => form.setData("is_active", Boolean(checked))}
                            />
                            <Label htmlFor="is_active" className="cursor-pointer select-none">
                                Room is active
                            </Label>
                        </div>
                        {form.errors.is_active && <p className="text-destructive text-xs sm:col-span-2">{form.errors.is_active}</p>}
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-3">
                    <Button variant="outline" asChild>
                        <Link href={route("administrators.rooms.index")}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        <Save className="mr-2 h-4 w-4" />
                        {form.processing ? "Saving..." : room ? "Save Changes" : "Create Room"}
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
