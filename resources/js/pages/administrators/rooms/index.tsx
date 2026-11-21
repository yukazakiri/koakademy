import AdminLayout from "@/components/administrators/admin-layout";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, router, useForm } from "@inertiajs/react";
import { CheckCircle2, DoorOpen, Edit, Plus, Power, Search, Trash2, XCircle } from "lucide-react";
import { useEffect, useMemo, useState, type FormEvent } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";
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

interface RoomItem {
    id: number;
    school_id: number | null;
    name: string;
    class_code: string | null;
    type: string;
    capacity: number;
    building: string | null;
    is_active: boolean;
    school: SchoolOption | null;
    classes_count: number;
    schedules_count: number;
    created_at: string | null;
}

interface Props {
    user: User;
    rooms: RoomItem[];
    stats: {
        total: number;
        active: number;
        inactive: number;
        total_capacity: number;
    };
    schools: SchoolOption[];
    roomTypes: RoomTypeOption[];
    filters: {
        search?: string | null;
        status?: string | null;
        type?: string | null;
    };
    flash?: {
        type: string;
        message: string;
    };
}

const defaultFormData = {
    school_id: "none",
    name: "",
    class_code: "",
    type: "lecture",
    capacity: "40",
    building: "",
    is_active: true,
};

export default function RoomsIndex({ user, rooms, stats, schools, roomTypes, filters, flash }: Props) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingRoom, setEditingRoom] = useState<RoomItem | null>(null);
    const [deletingRoom, setDeletingRoom] = useState<RoomItem | null>(null);

    const createForm = useForm({ ...defaultFormData });
    const editForm = useForm({ ...defaultFormData });

    const roomTypeMap = useMemo(() => {
        const map: Record<string, string> = {};
        roomTypes.forEach((item) => {
            map[item.value] = item.label;
        });
        return map;
    }, [roomTypes]);

    useEffect(() => {
        if (!flash?.message) return;
        if (flash.type === "success") {
            toast.success(flash.message);
        } else if (flash.type === "error") {
            toast.error(flash.message);
        } else {
            toast.message(flash.message);
        }
    }, [flash]);

    const handleSearch = useDebouncedCallback((term: string) => {
        router.get(
            route("administrators.rooms.index"),
            { search: term || null, status: filters.status, type: filters.type },
            { preserveState: true, replace: true },
        );
    }, 300);

    const openCreate = () => {
        createForm.reset();
        createForm.clearErrors();
        setIsCreateOpen(true);
    };

    const openEdit = (room: RoomItem) => {
        editForm.setData({
            school_id: room.school_id ? String(room.school_id) : "none",
            name: room.name,
            class_code: room.class_code ?? "",
            type: room.type || "lecture",
            capacity: String(room.capacity ?? 40),
            building: room.building ?? "",
            is_active: room.is_active,
        });
        editForm.clearErrors();
        setEditingRoom(room);
    };

    const handleCreateSubmit = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route("administrators.rooms.store"), {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const handleEditSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!editingRoom) return;
        editForm.put(route("administrators.rooms.update", editingRoom.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingRoom(null);
                editForm.reset();
            },
        });
    };

    const handleToggleStatus = (room: RoomItem) => {
        router.patch(
            route("administrators.rooms.toggle-status", room.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const handleDelete = () => {
        if (!deletingRoom) return;
        router.delete(route("administrators.rooms.destroy", deletingRoom.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setDeletingRoom(null),
        });
    };

    const handleStatusFilter = (value: string) => {
        router.get(
            route("administrators.rooms.index"),
            {
                search: search.trim() || null,
                status: value === "all" ? null : value,
                type: filters.type,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleTypeFilter = (value: string) => {
        router.get(
            route("administrators.rooms.index"),
            {
                search: search.trim() || null,
                status: filters.status,
                type: value === "all" ? null : value,
            },
            { preserveState: true, replace: true },
        );
    };

    const statCards = useMemo(
        () => [
            { label: "Total Rooms", value: stats.total, hint: "All classrooms and facilities." },
            { label: "Active Rooms", value: stats.active, hint: "Available for class scheduling." },
            { label: "Inactive Rooms", value: stats.inactive, hint: "Temporarily unavailable rooms." },
            { label: "Active Capacity", value: `${stats.total_capacity.toLocaleString()} seats`, hint: "Combined seats across active rooms." },
        ],
        [stats],
    );

    const RoomFormFields = ({ form, isEdit }: { form: typeof createForm; isEdit?: boolean }) => (
        <div className="grid gap-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-name" : "create-name"}>Room Name</Label>
                    <Input
                        id={isEdit ? "edit-name" : "create-name"}
                        value={form.data.name}
                        onChange={(e) => form.setData("name", e.target.value)}
                        placeholder="e.g. Room 101, Computer Lab A"
                    />
                    {form.errors.name && <p className="text-destructive text-xs">{form.errors.name}</p>}
                </div>
                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-class-code" : "create-class-code"}>Class Code</Label>
                    <Input
                        id={isEdit ? "edit-class-code" : "create-class-code"}
                        value={form.data.class_code}
                        onChange={(e) => form.setData("class_code", e.target.value)}
                        placeholder="e.g. R-101, CL-101"
                    />
                    {form.errors.class_code && <p className="text-destructive text-xs">{form.errors.class_code}</p>}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-type" : "create-type"}>Room Type</Label>
                    <Select
                        value={form.data.type}
                        onValueChange={(value) => form.setData("type", value)}
                    >
                        <SelectTrigger id={isEdit ? "edit-type" : "create-type"}>
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
                    <Label htmlFor={isEdit ? "edit-capacity" : "create-capacity"}>Capacity (Seats)</Label>
                    <Input
                        id={isEdit ? "edit-capacity" : "create-capacity"}
                        type="number"
                        min={1}
                        max={5000}
                        value={form.data.capacity}
                        onChange={(e) => form.setData("capacity", e.target.value)}
                        placeholder="40"
                    />
                    {form.errors.capacity && <p className="text-destructive text-xs">{form.errors.capacity}</p>}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-building" : "create-building"}>Building / Location</Label>
                    <Input
                        id={isEdit ? "edit-building" : "create-building"}
                        value={form.data.building}
                        onChange={(e) => form.setData("building", e.target.value)}
                        placeholder="e.g. Main Building, 2nd Floor"
                    />
                    {form.errors.building && <p className="text-destructive text-xs">{form.errors.building}</p>}
                </div>

                <div className="space-y-2">
                    <Label htmlFor={isEdit ? "edit-school" : "create-school"}>School</Label>
                    <Select
                        value={form.data.school_id}
                        onValueChange={(value) => form.setData("school_id", value)}
                    >
                        <SelectTrigger id={isEdit ? "edit-school" : "create-school"}>
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
            </div>

            <div className="bg-muted/30 border-border/50 flex items-center gap-3 rounded-lg border p-3">
                <Checkbox
                    id={isEdit ? "edit-is-active" : "create-is-active"}
                    checked={form.data.is_active}
                    onCheckedChange={(checked) => form.setData("is_active", Boolean(checked))}
                />
                <div className="flex flex-col">
                    <Label htmlFor={isEdit ? "edit-is-active" : "create-is-active"} className="cursor-pointer select-none">
                        Room is active
                    </Label>
                    <span className="text-muted-foreground text-xs">
                        When disabled, this room will not appear in class assignment and scheduling options.
                    </span>
                </div>
            </div>
            {form.errors.is_active && <p className="text-destructive text-xs">{form.errors.is_active}</p>}
        </div>
    );

    return (
        <AdminLayout user={user} title="Rooms">
            <Head title="Administrators • Rooms" />

            <div className="flex flex-col gap-6">
                <Card className="via-background border-0 bg-gradient-to-br from-blue-500/10 to-emerald-500/10">
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600">
                                <DoorOpen className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle>Room & Facility Management</CardTitle>
                                <CardDescription>Manage classrooms, laboratories, capacities, and availability for scheduling.</CardDescription>
                            </div>
                        </div>
                        <Button onClick={openCreate} className="gap-2">
                            <Plus className="h-4 w-4" />
                            Add Room
                        </Button>
                    </CardHeader>
                </Card>

                <div className="grid gap-3 md:grid-cols-4">
                    {statCards.map((stat) => (
                        <Card key={stat.label}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">{stat.label}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1">
                                <div className="text-2xl font-semibold tracking-tight">{stat.value}</div>
                                <div className="text-muted-foreground text-xs">{stat.hint}</div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Rooms</CardTitle>
                            <CardDescription>View and configure rooms, capacities, and scheduling availability.</CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="relative w-full md:w-72">
                                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                <Input
                                    placeholder="Search rooms, codes, or buildings..."
                                    value={search}
                                    onChange={(e) => {
                                        const value = e.target.value;
                                        setSearch(value);
                                        handleSearch(value);
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <Select value={filters.type ?? "all"} onValueChange={handleTypeFilter}>
                                <SelectTrigger className="w-44">
                                    <SelectValue placeholder="Room Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    {roomTypes.map((typeOption) => (
                                        <SelectItem key={typeOption.value} value={typeOption.value}>
                                            {typeOption.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={filters.status ?? "all"} onValueChange={handleStatusFilter}>
                                <SelectTrigger className="w-36">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Statuses</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Room</TableHead>
                                    <TableHead>Class Code</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Capacity</TableHead>
                                    <TableHead>Building</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Active Classes</TableHead>
                                    <TableHead>Scheduled Sessions</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rooms.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={9} className="text-muted-foreground h-24 text-center text-sm">
                                            No rooms found.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    rooms.map((room) => (
                                        <TableRow key={room.id}>
                                            <TableCell>
                                                <div className="flex flex-col">
                                                    <span className="font-medium">{room.name}</span>
                                                    {room.school?.name && (
                                                        <span className="text-muted-foreground text-xs">{room.school.name}</span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {room.class_code ? (
                                                    <Badge variant="outline" className="font-mono text-xs">
                                                        {room.class_code}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground text-xs">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary" className="text-xs">
                                                    {roomTypeMap[room.type] ?? room.type}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {room.capacity} seats
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {room.building ?? "—"}
                                            </TableCell>
                                            <TableCell>
                                                {room.is_active ? (
                                                    <Badge variant="default" className="gap-1">
                                                        <CheckCircle2 className="h-3 w-3" /> Active
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary" className="gap-1">
                                                        <XCircle className="h-3 w-3" /> Inactive
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className="text-xs">
                                                    {room.classes_count}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className="text-xs">
                                                    {room.schedules_count}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className={
                                                            room.is_active
                                                                ? "text-amber-600 hover:bg-amber-500/10 h-8 w-8"
                                                                : "text-emerald-600 hover:bg-emerald-500/10 h-8 w-8"
                                                        }
                                                        onClick={() => handleToggleStatus(room)}
                                                        title={room.is_active ? "Deactivate Room" : "Activate Room"}
                                                    >
                                                        <Power className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-primary hover:bg-primary/10 h-8 w-8"
                                                        onClick={() => openEdit(room)}
                                                        title="Edit"
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:bg-destructive/10 h-8 w-8"
                                                        onClick={() => setDeletingRoom(room)}
                                                        title="Delete"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Create Modal */}
            <Dialog open={isCreateOpen} onOpenChange={(open) => !open && setIsCreateOpen(false)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Add Room</DialogTitle>
                        <DialogDescription>Create a new classroom, laboratory, or facility for scheduling.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        <RoomFormFields form={createForm} />
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                {createForm.processing ? "Creating..." : "Create Room"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Modal */}
            <Dialog open={!!editingRoom} onOpenChange={(open) => !open && setEditingRoom(null)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Edit Room</DialogTitle>
                        <DialogDescription>Update room details, capacity, and scheduling availability.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleEditSubmit} className="space-y-4">
                        <RoomFormFields form={editForm} isEdit />
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditingRoom(null)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                {editForm.processing ? "Saving..." : "Save Changes"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <AlertDialog open={!!deletingRoom} onOpenChange={(open) => !open && setDeletingRoom(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete room?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will remove "{deletingRoom?.name}" from the system. Any linked classes or schedules will have their room assignment cleared. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
