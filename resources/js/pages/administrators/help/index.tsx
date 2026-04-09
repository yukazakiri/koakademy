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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Head, router, usePage } from "@inertiajs/react";
import { AlertCircle, CheckCircle2, Clock, Trash2 } from "lucide-react";
import { useState } from "react";
import { HelpTicket, createColumns } from "./columns";
import { DataTable } from "./data-table";

// Declare route globally
declare const route: any;

interface PageProps {
    tickets: {
        data: HelpTicket[];
        total: number;
    };
    stats: {
        total: number;
        open: number;
        resolved: number;
        high_priority: number;
    };
}

export default function HelpTicketIndex({ tickets, stats }: PageProps) {
    const { auth } = usePage<any>().props;
    const user = auth.user;

    const [actionState, setActionState] = useState<{
        type: "delete" | "view" | null;
        ticket: HelpTicket | null;
    }>({ type: null, ticket: null });

    const confirmAction = () => {
        const { type, ticket } = actionState;
        if (!ticket || !type) return;

        if (type === "delete") {
            router.delete(route("administrators.help-tickets.destroy", ticket.id), {
                onFinish: () => closeDialog(),
            });
        }
    };

    const openDialog = (type: "delete" | "view", ticket: HelpTicket) => {
        if (type === "view") {
            router.get(route("administrators.help-tickets.show", ticket.id));
            return;
        }
        setActionState({ type, ticket });
    };

    const closeDialog = () => {
        setActionState({ type: null, ticket: null });
    };

    const columns = createColumns({ onAction: openDialog });

    return (
        <AdminLayout user={user} title="Help Desk">
            <Head title="Administrators • Help Desk" />

            <div className="flex flex-col gap-6">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">Help Desk</h2>
                    <p className="text-muted-foreground">Manage support tickets, issues, and recommendations from students and faculty.</p>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Tickets</CardTitle>
                            <Clock className="text-muted-foreground h-4 w-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total}</div>
                            <p className="text-muted-foreground text-xs">All time submissions</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Open Tickets</CardTitle>
                            <AlertCircle className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.open}</div>
                            <p className="text-muted-foreground text-xs">Requires attention</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">High Priority</CardTitle>
                            <AlertCircle className="h-4 w-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.high_priority}</div>
                            <p className="text-muted-foreground text-xs">Critical open issues</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Resolved</CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.resolved}</div>
                            <p className="text-muted-foreground text-xs">Successfully closed</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent Tickets</CardTitle>
                        <CardDescription>A list of recent support tickets. Use filters to narrow down the list.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DataTable columns={columns} data={tickets.data} />
                    </CardContent>
                </Card>

                {/* Delete Confirmation Dialog */}
                <AlertDialog open={actionState.type === "delete"} onOpenChange={closeDialog}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle className="flex items-center gap-2">
                                <Trash2 className="h-5 w-5 text-red-500" />
                                Delete Ticket
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to delete ticket #{actionState.ticket?.id}? This action cannot be undone.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={(e) => {
                                    e.preventDefault();
                                    confirmAction();
                                }}
                                className="bg-red-600 hover:bg-red-700"
                            >
                                Delete Ticket
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AdminLayout>
    );
}
