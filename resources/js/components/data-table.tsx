import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";
import { IconDotsVertical, IconEye, IconPencil } from "@tabler/icons-react";
import { z } from "zod";

// Define the schema for our data (matching what we expect)
export const classDataSchema = z.object({
    id: z.string().or(z.number()),
    subject_code: z.string(),
    subject_title: z.string(),
    section: z.string(),
    room: z.string().optional(),
    room_id: z.union([z.string(), z.number()]).optional(),
    faculty_id: z.union([z.string(), z.number()]).optional(),
    maximum_slots: z.union([z.string(), z.number()]).optional(),
    semester: z.string().optional(),
    school_year: z.string().optional(),
    classification: z.string().optional(),
    strand_id: z.union([z.string(), z.number()]).optional(),
    subject_id: z.union([z.string(), z.number()]).optional(),
    schedule: z.string().optional(),
    students_count: z.number().optional(),
    faculty_name: z.string().optional(),
    accent_color: z.string().optional(),
    schedules: z.array(z.any()).optional(),
    settings: z.any().optional(),
});

export type ClassData = z.infer<typeof classDataSchema>;

export function DataTable({ data, onEdit }: { data: ClassData[]; onEdit?: (item: ClassData) => void }) {
    if (!data?.length) {
        return <div className="border-border/60 bg-card/80 text-muted-foreground rounded-xl border p-6 text-center">No upcoming classes found.</div>;
    }

    return (
        <Card className="border-border/60 bg-card/90 overflow-hidden border shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="bg-muted/50 text-muted-foreground text-xs uppercase">
                        <tr>
                            <th className="px-4 py-3 font-medium">Subject</th>
                            <th className="px-4 py-3 font-medium">Section</th>
                            <th className="hidden px-4 py-3 font-medium sm:table-cell">Schedule</th>
                            <th className="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-border/60 divide-y">
                        {data.map((item) => {
                            const settings = (item.settings ?? {}) as Record<string, unknown>;
                            const settingsAccent = typeof settings.accent_color === "string" ? settings.accent_color : null;
                            const rawAccent = settingsAccent || item.accent_color || "hsl(var(--primary))";
                            const accentClass = rawAccent.includes("bg-") ? rawAccent : null;
                            const accentStyle = accentClass ? undefined : { backgroundColor: rawAccent };

                            return (
                                <tr key={item.id} className="group hover:bg-muted/30 transition-colors">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <div className={cn("h-8 w-1 rounded-full", accentClass ?? "bg-primary")} style={accentStyle} />
                                            <div>
                                                <div className="text-foreground font-medium">{item.subject_title}</div>
                                                <div className="text-muted-foreground text-xs">{item.subject_code}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="outline" className="font-normal">
                                            {item.section}
                                        </Badge>
                                    </td>
                                    <td className="text-muted-foreground hidden px-4 py-3 sm:table-cell">
                                        {item.schedule || "TBA"} <br />
                                        <span className="text-xs">{item.room}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" className="h-8 w-8">
                                                    <IconDotsVertical className="size-4" />
                                                    <span className="sr-only">Open menu</span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem onClick={() => onEdit?.(item)}>
                                                    <IconEye className="mr-2 size-4" />
                                                    View Details
                                                </DropdownMenuItem>
                                                <DropdownMenuItem>
                                                    <IconPencil className="mr-2 size-4" />
                                                    Edit Class
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}
