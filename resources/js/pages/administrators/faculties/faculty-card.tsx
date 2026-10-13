import { Badge } from "@/components/reui/badge";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader } from "@/components/ui/card";
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
import { Link } from "@inertiajs/react";
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
    MoreVertical,
    Trash2,
    XCircle,
} from "lucide-react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import type { FacultyRow } from "./columns";

interface FacultyCardProps {
    faculty: FacultyRow;
    isSelected: boolean;
    onToggleSelect: (id: string) => void;
    onDelete: (id: string, name: string) => void;
}

export function FacultyCard({ faculty, isSelected, onToggleSelect, onDelete }: FacultyCardProps) {
    const copyToClipboard = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        toast.success(`${label} copied to clipboard`);
    };

    const statusStyle =
        faculty.status === "active"
            ? {
                  dot: "bg-success",
                  variant: "success-light" as const,
                  label: "Active",
              }
            : faculty.status === "on_leave"
              ? {
                    dot: "bg-warning",
                    variant: "warning-light" as const,
                    label: "On Leave",
                }
              : {
                    dot: "bg-muted-foreground",
                    variant: "invert-light" as const,
                    label: "Inactive",
                };

    const workloadStyle =
        faculty.workload_summary.level === "needs_classes"
            ? {
                  variant: "destructive-light" as const,
                  icon: AlertTriangle,
                  label: "No Load",
              }
            : faculty.workload_summary.level === "light"
              ? {
                    variant: "warning-light" as const,
                    icon: BookOpen,
                    label: "Light Load",
                }
              : faculty.workload_summary.level === "balanced"
                ? {
                      variant: "success-light" as const,
                      icon: CheckCircle2,
                      label: "Balanced",
                  }
                : {
                      variant: "info-light" as const,
                      icon: Layers,
                      label: "Heavy Load",
                  };

    const portalStyle =
        faculty.portal_account.status === "linked"
            ? {
                  variant: "success-light" as const,
                  icon: KeyRound,
                  label: "Portal Linked",
              }
            : faculty.portal_account.status === "needs_repair"
              ? {
                    variant: "warning-light" as const,
                    icon: AlertTriangle,
                    label: "Needs Repair",
                }
              : {
                    variant: "invert-light" as const,
                    icon: XCircle,
                    label: "No Portal",
                };

    const WorkloadIcon = workloadStyle.icon;
    const PortalIcon = portalStyle.icon;

    const completion = faculty.profile_completion;
    const compColor = completion.percent >= 80 ? "bg-success" : completion.percent >= 50 ? "bg-warning" : "bg-destructive";

    return (
        <Card
            className={cn(
                "group hover:border-primary/40 relative flex flex-col justify-between transition-colors",
                isSelected && "border-primary/80 ring-primary/80 bg-primary/[0.02] ring-1",
            )}
        >
            <div className="absolute top-3 left-3 z-10">
                <Checkbox checked={isSelected} onCheckedChange={() => onToggleSelect(faculty.id)} aria-label={`Select ${faculty.name}`} />
            </div>

            <div className="absolute top-3 right-3 z-10">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon-xs" className="text-muted-foreground hover:text-foreground h-7 w-7">
                            <MoreVertical className="h-3.5 w-3.5" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-48">
                        <DropdownMenuLabel>Faculty Options</DropdownMenuLabel>
                        <DropdownMenuItem asChild>
                            <Link href={route("administrators.faculties.show", faculty.id)}>
                                <Eye className="text-muted-foreground mr-2 h-4 w-4" /> View Profile
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={route("administrators.faculties.edit", faculty.id)}>
                                <Edit className="text-muted-foreground mr-2 h-4 w-4" /> Edit Profile
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onClick={() => copyToClipboard(faculty.email, "Email")}>
                            <Copy className="text-muted-foreground mr-2 h-4 w-4" /> Copy Email
                        </DropdownMenuItem>
                        {faculty.faculty_id_number ? (
                            <DropdownMenuItem onClick={() => copyToClipboard(faculty.faculty_id_number ?? "", "Faculty ID")}>
                                <Copy className="text-muted-foreground mr-2 h-4 w-4" /> Copy ID Number
                            </DropdownMenuItem>
                        ) : null}
                        <DropdownMenuItem onClick={() => window.open(faculty.filament.edit_url, "_blank")}>
                            <ExternalLink className="text-muted-foreground mr-2 h-4 w-4" /> Open Filament
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
            </div>

            <CardHeader className="pt-9 pb-3">
                <div className="flex flex-col items-center gap-2 text-center">
                    <div className="relative">
                        <Avatar className="border-background ring-border h-16 w-16 border-2 shadow-xs ring-1">
                            <AvatarImage src={faculty.avatar_url ?? undefined} alt={faculty.name} />
                            <AvatarFallback className="text-base font-bold">{(faculty.name || "?").slice(0, 2).toUpperCase()}</AvatarFallback>
                        </Avatar>
                        <span
                            className={cn("ring-background absolute right-0.5 bottom-0.5 h-3.5 w-3.5 rounded-full ring-2", statusStyle.dot)}
                            title={`Status: ${statusStyle.label}`}
                        />
                    </div>

                    <Link
                        href={route("administrators.faculties.show", faculty.id)}
                        className="line-clamp-1 text-base font-semibold tracking-tight hover:underline"
                    >
                        {faculty.name}
                    </Link>

                    <div className="flex flex-wrap items-center justify-center gap-1.5">
                        {faculty.faculty_id_number ? (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger>
                                        <span
                                            onClick={() => copyToClipboard(faculty.faculty_id_number ?? "", "Faculty ID")}
                                            className="text-muted-foreground hover:text-foreground bg-muted/60 inline-flex cursor-pointer items-center gap-1 rounded px-1.5 py-0.5 font-mono text-[11px] transition-colors"
                                        >
                                            #{faculty.faculty_id_number}
                                            <Copy className="h-2.5 w-2.5" />
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">Copy ID</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        ) : null}

                        <Badge variant={statusStyle.variant} size="xs" radius="full">
                            {statusStyle.label}
                        </Badge>
                    </div>

                    <div className="text-muted-foreground flex items-center justify-center gap-1.5 text-xs">
                        <Building2 className="h-3.5 w-3.5 shrink-0" />
                        <span className="max-w-56 truncate">{faculty.department || "Unassigned department"}</span>
                    </div>
                </div>
            </CardHeader>

            <CardContent className="flex flex-col gap-3 pb-3 text-xs">
                <div className="bg-muted/20 flex flex-col gap-2 rounded-md border p-2.5">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground">Teaching Load</span>
                        <Badge variant={workloadStyle.variant} size="xs" radius="full" className="gap-1">
                            <WorkloadIcon className="h-2.5 w-2.5" />
                            <span className="font-semibold tabular-nums">{faculty.current_classes_count}</span>
                            <span>
                                {faculty.current_classes_count === 1 ? "class" : "classes"} ({workloadStyle.label})
                            </span>
                        </Badge>
                    </div>

                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground">Portal Account</span>
                        <Badge variant={portalStyle.variant} size="xs" radius="full" className="gap-1">
                            <PortalIcon className="h-2.5 w-2.5" />
                            {portalStyle.label}
                        </Badge>
                    </div>
                </div>

                <div className="text-muted-foreground flex items-center justify-between">
                    <div className="flex max-w-[210px] items-center gap-1 truncate">
                        <Mail className="h-3 w-3 shrink-0" />
                        <span className="truncate">{faculty.email}</span>
                    </div>
                    <button
                        type="button"
                        onClick={() => copyToClipboard(faculty.email, "Email")}
                        className="hover:text-foreground p-0.5"
                        title="Copy email"
                    >
                        <Copy className="h-2.5 w-2.5" />
                    </button>
                </div>

                <div className="flex flex-col gap-1">
                    <div className="text-muted-foreground flex items-center justify-between text-[11px]">
                        <span>Profile Quality</span>
                        <span className="text-foreground font-medium tabular-nums">{completion.percent}%</span>
                    </div>
                    <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                        <div
                            className={cn("h-full rounded-full transition-all duration-300", compColor)}
                            style={{ width: `${completion.percent}%` }}
                        />
                    </div>
                </div>
            </CardContent>

            <CardFooter className="bg-muted/10 flex items-center justify-between gap-2 border-t p-2.5">
                <Link
                    href={route("administrators.faculties.show", faculty.id)}
                    className={buttonVariants({ variant: "outline", size: "sm", className: "flex-1 text-xs" })}
                >
                    <Eye className="mr-1.5 h-3.5 w-3.5" /> View
                </Link>
                <Link
                    href={route("administrators.faculties.edit", faculty.id)}
                    className={buttonVariants({ variant: "ghost", size: "sm", className: "flex-1 text-xs" })}
                >
                    <Edit className="mr-1.5 h-3.5 w-3.5" /> Edit
                </Link>
            </CardFooter>
        </Card>
    );
}
