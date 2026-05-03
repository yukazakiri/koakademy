import AdminLayout from "@/components/administrators/admin-layout";
import { AnnouncementBanner, type AnnouncementDisplayMode, type AnnouncementPriority, type AnnouncementType } from "@/components/announcement-banner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { useForm } from "@inertiajs/react";
import {
    IconAlertTriangle,
    IconBell,
    IconCalendar,
    IconCheck,
    IconClock,
    IconEdit,
    IconEye,
    IconInfoCircle,
    IconLoader2,
    IconNews,
    IconPlus,
    IconSpeakerphone,
    IconTrash,
    IconTrendingUp,
} from "@tabler/icons-react";
import { format } from "date-fns";
import { motion } from "framer-motion";
import { FormEventHandler, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Announcement {
    id: number;
    title: string;
    content: string;
    type: AnnouncementType;
    priority?: AnnouncementPriority;
    display_mode?: AnnouncementDisplayMode;
    requires_acknowledgment?: boolean;
    link?: string | null;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    creator?: {
        id: number;
        name: string;
    };
    created_at: string;
}

interface AnnouncementFormData {
    title: string;
    content: string;
    type: AnnouncementType;
    priority: AnnouncementPriority;
    display_mode: AnnouncementDisplayMode;
    requires_acknowledgment: boolean;
    link: string;
    is_active: boolean;
    starts_at: string;
    ends_at: string;
}

const typeConfig = {
    info: { icon: IconInfoCircle, color: "text-blue-500", bg: "bg-blue-50 dark:bg-blue-950/30" },
    success: { icon: IconCheck, color: "text-emerald-500", bg: "bg-emerald-50 dark:bg-emerald-950/30" },
    warning: { icon: IconAlertTriangle, color: "text-amber-500", bg: "bg-amber-50 dark:bg-amber-950/30" },
    danger: { icon: IconAlertTriangle, color: "text-red-500", bg: "bg-red-50 dark:bg-red-950/30" },
    maintenance: { icon: IconLoader2, color: "text-purple-500", bg: "bg-purple-50 dark:bg-purple-950/30" },
    enrollment: { icon: IconCalendar, color: "text-cyan-500", bg: "bg-cyan-50 dark:bg-cyan-950/30" },
    update: { icon: IconSpeakerphone, color: "text-indigo-500", bg: "bg-indigo-50 dark:bg-indigo-950/30" },
};

const displayModeOptions = [
    { value: "banner", label: "Top Banner", description: "Fixed banner at the top of the page" },
    { value: "toast", label: "Toast Notification", description: "Floating notification in bottom-right" },
    { value: "modal", label: "Modal Popup", description: "Centered modal that requires action" },
];

const priorityOptions = [
    { value: "urgent", label: "Urgent", description: "Shows with pulsing indicator" },
    { value: "high", label: "High", description: "High priority display" },
    { value: "medium", label: "Medium", description: "Normal priority" },
    { value: "low", label: "Low", description: "Low priority display" },
];

export default function AnnouncementIndex({ auth, announcements }: { auth: { user: User }; announcements: PaginatedData<Announcement> }) {
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [previewAnnouncement, setPreviewAnnouncement] = useState<Announcement | null>(null);
    const [showPreview, setShowPreview] = useState(false);

    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm<AnnouncementFormData>({
        title: "",
        content: "",
        type: "info",
        priority: "medium",
        display_mode: "banner",
        requires_acknowledgment: false,
        link: "",
        is_active: true,
        starts_at: "",
        ends_at: "",
    });

    const stats = {
        total: announcements.data.length,
        active: announcements.data.filter((a: Announcement) => a.is_active).length,
        scheduled: announcements.data.filter((a: Announcement) => a.starts_at && new Date(a.starts_at) > new Date()).length,
        requiresAck: announcements.data.filter((a: Announcement) => a.requires_acknowledgment).length,
    };

    const openCreateModal = () => {
        clearErrors();
        reset();
        setEditingId(null);
        setIsCreateModalOpen(true);
    };

    const openEditModal = (announcement: Announcement) => {
        clearErrors();
        setData({
            title: announcement.title,
            content: announcement.content,
            type: announcement.type,
            priority: announcement.priority || "medium",
            display_mode: announcement.display_mode || "banner",
            requires_acknowledgment: announcement.requires_acknowledgment || false,
            link: announcement.link || "",
            is_active: announcement.is_active,
            starts_at: announcement.starts_at ? new Date(announcement.starts_at).toISOString().slice(0, 16) : "",
            ends_at: announcement.ends_at ? new Date(announcement.ends_at).toISOString().slice(0, 16) : "",
        });
        setEditingId(announcement.id);
        setIsCreateModalOpen(true);
    };

    const handlePreview = (announcement: Announcement) => {
        setPreviewAnnouncement(announcement);
        setShowPreview(true);
    };

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this announcement?")) {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            destroy(route("administrators.announcements.destroy", id) as any, {
                onSuccess: () => toast.success("Announcement deleted successfully"),
                onError: () => toast.error("Failed to delete announcement"),
            });
        }
    };

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();

        if (editingId) {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            put(route("administrators.announcements.update", [editingId]) as any, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Announcement updated successfully");
                    setIsCreateModalOpen(false);
                    reset();
                },
                onError: (errors: Record<string, string>) => {
                    const firstError = Object.values(errors)[0];
                    toast.error(firstError || "Failed to update announcement");
                },
            });
        } else {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            post(route("administrators.announcements.store") as any, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Announcement created successfully");
                    setIsCreateModalOpen(false);
                    reset();
                },
                onError: (errors: Record<string, string>) => {
                    const firstError = Object.values(errors)[0];
                    toast.error(firstError || "Failed to create announcement");
                },
            });
        }
    };

    const getTypeColor = (type: string) => {
        const config = typeConfig[type as keyof typeof typeConfig];
        return config ? config.color : "text-gray-500";
    };

    const isScheduled = (startsAt: string | null): boolean => {
        return startsAt !== null && new Date(startsAt) > new Date();
    };

    const isExpired = (endsAt: string | null): boolean => {
        return endsAt !== null && new Date(endsAt) < new Date();
    };

    return (
        <AdminLayout user={auth.user} title="Announcements">
            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Announcements</h1>
                        <p className="text-muted-foreground">Manage and broadcast announcements to all users</p>
                    </div>
                    <Button onClick={openCreateModal}>
                        <IconPlus className="mr-2 size-4" />
                        Create Announcement
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Total Announcements</CardTitle>
                            <IconNews className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total}</div>
                            <p className="text-muted-foreground text-xs">All time</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Active Now</CardTitle>
                            <IconBell className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.active}</div>
                            <p className="text-muted-foreground text-xs">Currently displayed</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Scheduled</CardTitle>
                            <IconClock className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.scheduled}</div>
                            <p className="text-muted-foreground text-xs">Upcoming</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Require Ack.</CardTitle>
                            <IconCheck className="h-4 w-4 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.requiresAck}</div>
                            <p className="text-muted-foreground text-xs">Must be acknowledged</p>
                        </CardContent>
                    </Card>
                </div>

                <Tabs defaultValue="all" className="space-y-4">
                    <TabsList>
                        <TabsTrigger value="all">All</TabsTrigger>
                        <TabsTrigger value="active">Active</TabsTrigger>
                        <TabsTrigger value="scheduled">Scheduled</TabsTrigger>
                        <TabsTrigger value="expired">Expired</TabsTrigger>
                    </TabsList>

                    <TabsContent value="all" className="space-y-4">
                        <AnnouncementTable
                            announcements={announcements.data}
                            onEdit={openEditModal}
                            onDelete={handleDelete}
                            onPreview={handlePreview}
                            getTypeColor={getTypeColor}
                            isScheduled={isScheduled}
                            isExpired={isExpired}
                        />
                    </TabsContent>
                    <TabsContent value="active" className="space-y-4">
                        <AnnouncementTable
                            announcements={announcements.data.filter(
                                (a: Announcement) => a.is_active && !isScheduled(a.starts_at) && !isExpired(a.ends_at),
                            )}
                            onEdit={openEditModal}
                            onDelete={handleDelete}
                            onPreview={handlePreview}
                            getTypeColor={getTypeColor}
                            isScheduled={isScheduled}
                            isExpired={isExpired}
                        />
                    </TabsContent>
                    <TabsContent value="scheduled" className="space-y-4">
                        <AnnouncementTable
                            announcements={announcements.data.filter((a: Announcement) => isScheduled(a.starts_at))}
                            onEdit={openEditModal}
                            onDelete={handleDelete}
                            onPreview={handlePreview}
                            getTypeColor={getTypeColor}
                            isScheduled={isScheduled}
                            isExpired={isExpired}
                        />
                    </TabsContent>
                    <TabsContent value="expired" className="space-y-4">
                        <AnnouncementTable
                            announcements={announcements.data.filter(
                                (a: Announcement) => isExpired(a.ends_at) || (!a.is_active && !isScheduled(a.starts_at)),
                            )}
                            onEdit={openEditModal}
                            onDelete={handleDelete}
                            onPreview={handlePreview}
                            getTypeColor={getTypeColor}
                            isScheduled={isScheduled}
                            isExpired={isExpired}
                        />
                    </TabsContent>
                </Tabs>
            </div>

            <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[700px]">
                    <DialogHeader>
                        <DialogTitle>{editingId ? "Edit Announcement" : "Create Announcement"}</DialogTitle>
                        <DialogDescription>
                            {editingId ? "Update the details of this announcement below." : "Create a new announcement to broadcast to all users."}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-6 pt-4">
                        {Object.keys(errors).length > 0 && (
                            <div className="rounded-md border border-red-200 bg-red-50 p-3 dark:bg-red-950/20">
                                <p className="text-sm font-medium text-red-600">Please fix the following errors:</p>
                                <ul className="mt-1 list-inside list-disc text-sm text-red-600">
                                    {Object.entries(errors).map(([field, message]) => (
                                        <li key={field}>{message}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="title">
                                    Title <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => setData("title", e.target.value)}
                                    placeholder="e.g. System Maintenance Scheduled"
                                />
                                {errors.title && <p className="text-sm text-red-500">{errors.title}</p>}
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="type">
                                        Type <span className="text-red-500">*</span>
                                    </Label>
                                    <Select value={data.type} onValueChange={(v) => setData("type", v as AnnouncementType)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="info">Info</SelectItem>
                                            <SelectItem value="success">Success</SelectItem>
                                            <SelectItem value="warning">Warning</SelectItem>
                                            <SelectItem value="danger">Danger</SelectItem>
                                            <SelectItem value="maintenance">Maintenance</SelectItem>
                                            <SelectItem value="enrollment">Enrollment</SelectItem>
                                            <SelectItem value="update">Update</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="priority">Priority</Label>
                                    <Select value={data.priority} onValueChange={(v: AnnouncementPriority) => setData("priority", v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select priority" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {priorityOptions.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="display_mode">Display Mode</Label>
                                    <Select value={data.display_mode} onValueChange={(v: AnnouncementDisplayMode) => setData("display_mode", v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select display mode" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {displayModeOptions.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex items-center space-x-2 pt-6">
                                    <Switch
                                        id="requires_acknowledgment"
                                        checked={data.requires_acknowledgment}
                                        onCheckedChange={(c: boolean) => setData("requires_acknowledgment", c)}
                                    />
                                    <Label htmlFor="requires_acknowledgment" className="cursor-pointer">
                                        Requires Acknowledgment
                                    </Label>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="link">Link (optional)</Label>
                                <Input
                                    id="link"
                                    value={data.link}
                                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => setData("link", e.target.value)}
                                    placeholder="https://example.com/details"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="content">
                                    Content <span className="text-red-500">*</span>
                                </Label>
                                <Textarea
                                    id="content"
                                    value={data.content}
                                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData("content", e.target.value)}
                                    placeholder="Write your announcement message here..."
                                    rows={4}
                                />
                                <p className="text-muted-foreground text-xs">{data.content.length}/500 characters</p>
                                {errors.content && <p className="text-sm text-red-500">{errors.content}</p>}
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div className="flex items-center space-x-2">
                                    <Switch id="is_active" checked={data.is_active} onCheckedChange={(c: boolean) => setData("is_active", c)} />
                                    <Label htmlFor="is_active" className="cursor-pointer">
                                        Active
                                    </Label>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="starts_at">Start Time</Label>
                                    <Input
                                        id="starts_at"
                                        type="datetime-local"
                                        value={data.starts_at}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setData("starts_at", e.target.value)}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="ends_at">End Time</Label>
                                    <Input
                                        id="ends_at"
                                        type="datetime-local"
                                        value={data.ends_at}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setData("ends_at", e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>

                        <DialogFooter className="pt-4">
                            <Button type="button" variant="outline" onClick={() => setIsCreateModalOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? "Saving..." : editingId ? "Update Announcement" : "Create Announcement"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={showPreview} onOpenChange={setShowPreview}>
                <DialogContent className="sm:max-w-[600px]">
                    <DialogHeader>
                        <DialogTitle>Preview Announcement</DialogTitle>
                        <DialogDescription>This is how your announcement will appear to users</DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        {previewAnnouncement && (
                            <div className="rounded-lg border p-4">
                                <AnnouncementBanner
                                    announcements={[{ ...previewAnnouncement, is_active: true }]}
                                    displayMode={previewAnnouncement.display_mode || "banner"}
                                />
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowPreview(false)}>
                            Close Preview
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

function AnnouncementTable({
    announcements,
    onEdit,
    onDelete,
    onPreview,
    getTypeColor,
    isScheduled,
    isExpired,
}: {
    announcements: Announcement[];
    onEdit: (announcement: Announcement) => void;
    onDelete: (id: number) => void;
    onPreview: (announcement: Announcement) => void;
    getTypeColor: (type: string) => string;
    isScheduled: (startsAt: string | null) => boolean;
    isExpired: (endsAt: string | null) => boolean;
}) {
    if (announcements.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-12">
                    <IconNews className="text-muted-foreground mb-4 size-12 opacity-50" />
                    <p className="text-muted-foreground">No announcements found</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="grid gap-4">
            {announcements.map((announcement) => {
                const TypeIcon = typeConfig[announcement.type as keyof typeof typeConfig]?.icon || IconInfoCircle;
                const scheduled = isScheduled(announcement.starts_at);
                const expired = isExpired(announcement.ends_at);

                return (
                    <motion.div
                        key={announcement.id}
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        className="bg-card hover:bg-muted/50 rounded-lg border p-4 transition-colors"
                    >
                        <div className="flex items-start gap-4">
                            <div
                                className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-lg ${
                                    typeConfig[announcement.type as keyof typeof typeConfig]?.bg || "bg-gray-50"
                                }`}
                            >
                                <TypeIcon className={`h-6 w-6 ${getTypeColor(announcement.type) || "text-gray-500"}`} />
                            </div>

                            <div className="min-w-0 flex-1">
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <h3 className="font-semibold">{announcement.title}</h3>
                                        <p className="text-muted-foreground mt-1 line-clamp-2 text-sm">{announcement.content}</p>
                                    </div>
                                    <div className="flex shrink-0 gap-2">
                                        {announcement.priority === "urgent" && (
                                            <Badge variant="outline" className="border-red-500 bg-red-50 text-red-600 dark:bg-red-950/30">
                                                Urgent
                                            </Badge>
                                        )}
                                        {announcement.requires_acknowledgment && (
                                            <Badge variant="outline" className="border-purple-500 bg-purple-50 text-purple-600 dark:bg-purple-950/30">
                                                Requires Ack.
                                            </Badge>
                                        )}
                                    </div>
                                </div>

                                <div className="text-muted-foreground mt-3 flex flex-wrap items-center gap-3 text-xs">
                                    <span className="flex items-center gap-1">
                                        <IconBell className="h-3 w-3" />
                                        {announcement.type.charAt(0).toUpperCase() + announcement.type.slice(1)}
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <IconTrendingUp className="h-3 w-3" />
                                        {announcement.priority?.charAt(0).toUpperCase() + (announcement.priority?.slice(1) || "Medium")}
                                    </span>
                                    {scheduled ? (
                                        <span className="flex items-center gap-1 text-blue-600">
                                            <IconClock className="h-3 w-3" />
                                            Starts: {format(new Date(announcement.starts_at!), "MMM d, yyyy h:mm a")}
                                        </span>
                                    ) : expired ? (
                                        <span className="flex items-center gap-1 text-red-600">
                                            <IconClock className="h-3 w-3" />
                                            Expired
                                        </span>
                                    ) : announcement.is_active ? (
                                        <span className="flex items-center gap-1 text-green-600">
                                            <IconCheck className="h-3 w-3" />
                                            Active
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1 text-gray-600">
                                            <IconClock className="h-3 w-3" />
                                            Inactive
                                        </span>
                                    )}
                                    {announcement.starts_at || announcement.ends_at ? (
                                        <span className="flex items-center gap-1">
                                            <IconCalendar className="h-3 w-3" />
                                            {announcement.starts_at ? format(new Date(announcement.starts_at), "MMM d") : "Now"} -{" "}
                                            {announcement.ends_at ? format(new Date(announcement.ends_at), "MMM d") : "Forever"}
                                        </span>
                                    ) : null}
                                    <span className="text-muted-foreground">By {announcement.creator?.name || "System"}</span>
                                </div>
                            </div>

                            <div className="flex shrink-0 gap-1">
                                <Button variant="ghost" size="icon" onClick={() => onPreview(announcement)} className="h-8 w-8">
                                    <IconEye className="size-4" />
                                </Button>
                                <Button variant="ghost" size="icon" onClick={() => onEdit(announcement)} className="h-8 w-8">
                                    <IconEdit className="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => onDelete(announcement.id)}
                                    className="text-destructive hover:text-destructive h-8 w-8"
                                >
                                    <IconTrash className="size-4" />
                                </Button>
                            </div>
                        </div>
                    </motion.div>
                );
            })}
        </div>
    );
}
