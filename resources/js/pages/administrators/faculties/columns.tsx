import { Badge } from "@/components/reui/badge";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import type { ColumnDef } from "@tanstack/react-table";
import {
    AlertTriangle,
    BookOpen,
    Building2,
    CheckCircle2,
    Copy,
    Edit,
    ExternalLink,
    Eye,
    KeyRound,
    Layers,
    Mail,
    MoreHorizontal,
    Trash2,
    XCircle,
} from "lucide-react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import { DataTableColumnHeader } from "./data-table-column-header";

export type FacultyRow = {
    id: string;
    faculty_id_number: string | null;
    name: string;
    first_name?: string;
    last_name?: string;
    email: string;
    department: string | null;
    status: string | null;
    avatar_url: string | null;
    classes_count?: number;
    current_classes_count: number;
    created_at?: string;
    profile_completion: {
        completed: number;
        total: number;
        percent: number;
        missing: string[];
    };
    portal_account: {
        status: "linked" | "needs_repair" | "not_linked";
        label: string;
        role_label: string | null;
        needs_repair: boolean;
    };
    workload_summary: {
        current_classes_count: number;
        level: "needs_classes" | "light" | "balanced" | "heavy";
        label: string;
    };
    filament: {
        view_url: string;
        edit_url: string;
    };
};

function statusBadgeConfig(status: string | null | undefined): {
    label: string;
    variant: "success-light" | "warning-light" | "invert-light";
    dotClassName: string;
} {
    if (status === "active") {
        return {
            label: "Active",
            variant: "success-light",
            dotClassName: "bg-success",
        };
    }
    if (status === "on_leave") {
        return {
            label: "On Leave",
            variant: "warning-light",
            dotClassName: "bg-warning",
        };
    }
    return {
        label: status === "inactive" ? "Inactive" : status || "Unknown",
        variant: "invert-light",
        dotClassName: "bg-muted-foreground",
    };
}

function workloadBadgeConfig(level: FacultyRow["workload_summary"]["level"]): {
    label: string;
    variant: "destructive-light" | "warning-light" | "success-light" | "info-light";
    icon: typeof BookOpen;
} {
    if (level === "needs_classes") {
        return {
            label: "No Load",
            variant: "destructive-light",
            icon: AlertTriangle,
        };
    }
    if (level === "light") {
        return {
            label: "Light Load",
            variant: "warning-light",
            icon: BookOpen,
        };
    }
    if (level === "balanced") {
        return {
            label: "Balanced",
            variant: "success-light",
            icon: CheckCircle2,
        };
    }
    return {
        label: "Heavy Load",
        variant: "info-light",
        icon: Layers,
    };
}

function portalBadgeConfig(status: FacultyRow["portal_account"]["status"]): {
    label: string;
    variant: "success-light" | "warning-light" | "invert-light";
    icon: typeof KeyRound;
} {
    if (status === "linked") {
        return {
            label: "Linked",
            variant: "success-light",
            icon: KeyRound,
        };
    }
    if (status === "needs_repair") {
        return {
            label: "Needs Repair",
            variant: "warning-light",
            icon: AlertTriangle,
        };
    }
    return {
        label: "Not Linked",
        variant: "invert-light",
        icon: XCircle,
    };
}

interface GetColumnsProps {
    onDelete: (id: string, name: string) => void;
}

export const getColumns = ({ onDelete }: GetColumnsProps): ColumnDef<FacultyRow>[] => [
    {
        id: "select",
        header: ({ table }) => (
            <Checkbox
                checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate")}
                onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                aria-label="Select all"
                className="translate-y-[2px]"
            />
        ),
        cell: ({ row }) => (
            <Checkbox
                checked={row.getIsSelected()}
                onCheckedChange={(value) => row.toggleSelected(!!value)}
                aria-label="Select row"
                className="translate-y-[2px]"
            />
        ),
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: "name",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Faculty" />,
        cell: ({ row }) => {
            const faculty = row.original;
            const status = statusBadgeConfig(faculty.status);

            const copyToClipboard = (text: string, label: string) => {
                navigator.clipboard.writeText(text);
                toast.success(`${label} copied to clipboard`);
            };

            return (
                <div className="flex min-w-64 items-center gap-3">
                    <div className="relative shrink-0">
                        <Avatar className="h-10 w-10 border shadow-xs">
                            <AvatarImage src={faculty.avatar_url ?? undefined} alt={faculty.name} />
                            <AvatarFallback className="text-xs font-semibold">{(faculty.name || "?").slice(0, 2).toUpperCase()}</AvatarFallback>
                        </Avatar>
                        <span
                            className={cn("ring-background absolute -right-0.5 -bottom-0.5 h-3 w-3 rounded-full ring-2", status.dotClassName)}
                            title={`Status: ${status.label}`}
                        />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-1.5">
                            <button
                                type="button"
                                onClick={() => router.visit(route("administrators.faculties.show", faculty.id))}
                                className="cursor-pointer truncate text-left text-sm font-semibold hover:underline"
                            >
                                {faculty.name}
                            </button>
                            {faculty.faculty_id_number ? (
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <span
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    copyToClipboard(faculty.faculty_id_number ?? "", "Faculty ID");
                                                }}
                                                className="text-muted-foreground hover:text-foreground hover:bg-muted inline-flex cursor-pointer items-center gap-0.5 rounded px-1 font-mono text-[11px]"
                                            >
                                                #{faculty.faculty_id_number}
                                                <Copy className="h-2.5 w-2.5" />
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent side="top">Click to copy ID</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            ) : null}
                        </div>
                        <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
                            <Mail className="h-3 w-3 shrink-0" />
                            <span className="truncate">{faculty.email}</span>
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    copyToClipboard(faculty.email, "Email");
                                }}
                                className="hover:text-foreground p-0.5"
                                title="Copy email"
                            >
                                <Copy className="h-2.5 w-2.5" />
                            </button>
                        </div>
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: "department",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Department" />,
        cell: ({ row }) => {
            const dept = row.original.department;
            if (!dept) {
                return <span className="text-muted-foreground text-xs italic">No department</span>;
            }

            return (
                <div className="flex items-center gap-1.5 text-xs font-medium">
                    <Building2 className="text-muted-foreground h-3.5 w-3.5 shrink-0" />
                    <span className="max-w-44 truncate">{dept}</span>
                </div>
            );
        },
    },
    {
        accessorKey: "status",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
        cell: ({ row }) => {
            const config = statusBadgeConfig(row.original.status);

            return (
                <Badge variant={config.variant} size="sm" radius="full" className="gap-1.5">
                    <span className={cn("h-1.5 w-1.5 rounded-full", config.dotClassName)} />
                    {config.label}
                </Badge>
            );
        },
    },
    {
        accessorKey: "current_classes_count",
        header: ({ column }) => <DataTableColumnHeader column={column} title="Teaching Load" />,
        cell: ({ row }) => {
            const count = row.original.current_classes_count;
            const config = workloadBadgeConfig(row.original.workload_summary.level);
            const Icon = config.icon;

            return (
                <Badge variant={config.variant} size="sm" radius="full" className="gap-1 whitespace-nowrap">
                    <Icon className="h-3 w-3" />
                    <span className="font-semibold tabular-nums">{count}</span>
                    <span>
                        {count === 1 ? "class" : "classes"} ({config.label})
                    </span>
                </Badge>
            );
        },
    },
    {
        accessorKey: "portal_account",
        header: "Portal Access",
        cell: ({ row }) => {
            const portal = row.original.portal_account;
            const config = portalBadgeConfig(portal.status);
            const Icon = config.icon;

            return (
                <div className="flex flex-col gap-0.5">
                    <Badge variant={config.variant} size="sm" radius="full" className="w-fit gap-1 whitespace-nowrap">
                        <Icon className="h-3 w-3" />
                        <span>{config.label}</span>
                    </Badge>
                    {portal.role_label ? <span className="text-muted-foreground max-w-32 truncate text-[11px]">{portal.role_label}</span> : null}
                </div>
            );
        },
    },
    {
        accessorKey: "profile_completion",
        header: "Profile Quality",
        cell: ({ row }) => {
            const completion = row.original.profile_completion;
            const percent = completion.percent;

            const barColor = percent >= 80 ? "bg-success" : percent >= 50 ? "bg-warning" : "bg-destructive";

            return (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger>
                            <div className="min-w-28 cursor-help">
                                <div className="flex items-center justify-between text-xs font-medium">
                                    <span className="tabular-nums">{percent}%</span>
                                    <span className="text-muted-foreground text-[10px] tabular-nums">
                                        {completion.completed}/{completion.total}
                                    </span>
                                </div>
                                <div className="bg-muted mt-1 h-1.5 overflow-hidden rounded-full">
                                    <div
                                        className={cn("h-full rounded-full transition-all duration-300", barColor)}
                                        style={{ width: `${percent}%` }}
                                    />
                                </div>
                            </div>
                        </TooltipTrigger>
                        <TooltipContent side="top" className="text-xs">
                            {completion.missing.length > 0 ? (
                                <div className="space-y-0.5">
                                    <div className="font-semibold">Missing fields:</div>
                                    <div className="text-muted-foreground">{completion.missing.join(", ")}</div>
                                </div>
                            ) : (
                                <div className="text-success font-medium">Profile 100% complete</div>
                            )}
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            );
        },
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const faculty = row.original;

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button size="sm" variant="ghost" className="hover:bg-muted h-8 w-8 p-0">
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-48">
                        <DropdownMenuLabel>Faculty Actions</DropdownMenuLabel>
                        <DropdownMenuItem onClick={() => router.visit(route("administrators.faculties.show", faculty.id))}>
                            <Eye className="text-muted-foreground mr-2 h-4 w-4" /> View Details
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => router.visit(route("administrators.faculties.edit", faculty.id))}>
                            <Edit className="text-muted-foreground mr-2 h-4 w-4" /> Edit Profile
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={() => {
                                navigator.clipboard.writeText(faculty.email);
                                toast.success("Email copied to clipboard");
                            }}
                        >
                            <Copy className="text-muted-foreground mr-2 h-4 w-4" /> Copy Email
                        </DropdownMenuItem>
                        {faculty.faculty_id_number ? (
                            <DropdownMenuItem
                                onClick={() => {
                                    navigator.clipboard.writeText(faculty.faculty_id_number ?? "");
                                    toast.success("Faculty ID copied to clipboard");
                                }}
                            >
                                <Copy className="text-muted-foreground mr-2 h-4 w-4" /> Copy Faculty ID
                            </DropdownMenuItem>
                        ) : null}
                        <DropdownMenuItem onClick={() => window.open(faculty.filament.edit_url, "_blank")}>
                            <ExternalLink className="text-muted-foreground mr-2 h-4 w-4" /> Open in Filament
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            className="text-destructive focus:bg-destructive/10 focus:text-destructive dark:focus:bg-destructive/20"
                            onClick={() => onDelete(faculty.id, faculty.name)}
                        >
                            <Trash2 className="mr-2 h-4 w-4" /> Delete Faculty
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
