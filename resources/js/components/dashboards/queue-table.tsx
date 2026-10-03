import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import { Inbox, Table2 } from "lucide-react";
import type { DeskTable } from "./types";

type QueueTableProps = {
    table: DeskTable;
    /** Message shown when the desk returned no rows. */
    emptyMessage?: string;
};

function isStatusColumn(columnKey: string): boolean {
    const key = columnKey.toLowerCase();
    return key.includes("status") || key.includes("severity") || key.includes("state") || key.includes("stage") || key.includes("priority");
}

function renderStatusBadge(val: unknown) {
    if (val === null || val === undefined || val === "") {
        return <span className="text-muted-foreground">—</span>;
    }
    const str = String(val);
    const lower = str.toLowerCase();

    let variant: "success-light" | "warning-light" | "destructive-light" | "info-light" | "primary-light" | "outline" = "outline";

    if (
        lower.includes("active") ||
        lower.includes("approved") ||
        lower.includes("paid") ||
        lower.includes("complete") ||
        lower.includes("settled") ||
        lower.includes("regular") ||
        lower.includes("open") ||
        lower.includes("verified") ||
        lower.includes("cleared")
    ) {
        variant = "success-light";
    } else if (
        lower.includes("pending") ||
        lower.includes("probation") ||
        lower.includes("part-time") ||
        lower.includes("review") ||
        lower.includes("process") ||
        lower.includes("wait") ||
        lower.includes("medium")
    ) {
        variant = "warning-light";
    } else if (
        lower.includes("inactive") ||
        lower.includes("unpaid") ||
        lower.includes("overdue") ||
        lower.includes("cancel") ||
        lower.includes("unassigned") ||
        lower.includes("suspend") ||
        lower.includes("drop") ||
        lower.includes("reject") ||
        lower.includes("high") ||
        lower.includes("urgent") ||
        lower.includes("hold")
    ) {
        variant = "destructive-light";
    } else if (lower.includes("assign") || lower.includes("enroll") || lower.includes("draft") || lower.includes("low")) {
        variant = "info-light";
    }

    return (
        <Badge variant={variant} size="sm" radius="full" className="px-2.5 py-0.5 text-[11px] font-medium capitalize">
            {str}
        </Badge>
    );
}

/**
 * Small status tables inside a desk.
 *
 * High-contrast table with clean column headers, row hover highlighting,
 * right-aligned tabular-nums for numeric columns, ReUI status pill badges, and
 * positive empty state.
 */
export function QueueTable({ table, emptyMessage = "No records to display yet." }: QueueTableProps) {
    return (
        <Frame variant="default" spacing="default" className="shadow-xs">
            <FramePanel className="bg-card">
                <FrameHeader className="border-border/40 border-b pb-3 sm:pb-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <IconTile variant="soft" size="default" className="text-primary">
                                <Table2 className="size-4" />
                            </IconTile>
                            <div className="space-y-0.5">
                                <div className="flex items-center gap-2">
                                    <FrameTitle className="text-foreground text-base font-semibold tracking-tight">{table.title}</FrameTitle>
                                    <Badge variant="outline" size="sm" radius="full" className="font-mono text-[10px] tabular-nums">
                                        {table.rows.length} {table.rows.length === 1 ? "entry" : "entries"}
                                    </Badge>
                                </div>
                                {table.description ? (
                                    <FrameDescription className="text-muted-foreground text-xs leading-normal">{table.description}</FrameDescription>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </FrameHeader>

                <div>
                    {table.rows.length === 0 ? (
                        <div className="border-border/80 bg-muted/20 m-4 flex flex-col items-center justify-center rounded-xl border border-dashed px-4 py-8 text-center sm:m-5">
                            <IconTile variant="outline" size="lg" className="text-muted-foreground/70 mb-3">
                                <Inbox className="size-5" />
                            </IconTile>
                            <p className="text-foreground text-sm font-semibold">No records to display</p>
                            <p className="text-muted-foreground mt-1 max-w-sm text-xs">{emptyMessage}</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table className="w-full">
                                <TableHeader className="bg-muted/30 border-border/60 border-b">
                                    <TableRow className="hover:bg-transparent">
                                        {table.columns.map((column) => (
                                            <TableHead
                                                key={column.key}
                                                className={cn(
                                                    "text-foreground/70 py-3 text-[11px] font-semibold tracking-wider uppercase",
                                                    column.align === "end" ? "text-right" : "text-left",
                                                )}
                                            >
                                                {column.label}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {table.rows.map((row, index) => (
                                        <TableRow
                                            key={String(row.id ?? row.key ?? index)}
                                            className="border-border/40 hover:bg-muted/35 transition-colors duration-150"
                                        >
                                            {table.columns.map((column) => {
                                                const rawValue = row[column.key];
                                                const isStatus = isStatusColumn(column.key);

                                                return (
                                                    <TableCell
                                                        key={column.key}
                                                        className={cn(
                                                            "py-3 text-xs sm:text-sm",
                                                            column.align === "end" ? "text-right font-mono font-medium tabular-nums" : "text-left",
                                                        )}
                                                    >
                                                        {isStatus ? renderStatusBadge(rawValue) : String(rawValue ?? "—")}
                                                    </TableCell>
                                                );
                                            })}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </div>
            </FramePanel>
        </Frame>
    );
}
