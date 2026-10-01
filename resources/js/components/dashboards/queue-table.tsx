import { FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { DeskTable } from "./types";

type QueueTableProps = {
    table: DeskTable;
    /** Message shown when the desk returned no rows. */
    emptyMessage?: string;
};

/**
 * Small status tables inside a desk.
 *
 * Intentionally not ReUI `data-grid`: that component requires TanStack Table v9 while the
 * project is on v8 across 35 existing files, and desk queues are 5-12 rows that need neither
 * virtualization nor spreadsheet editing. This reuses the vendored shadcn table instead.
 */
export function QueueTable({ table, emptyMessage = "No records yet." }: QueueTableProps) {
    return (
        <FramePanel>
            <FrameHeader>
                <FrameTitle>{table.title}</FrameTitle>
                {table.description ? <FrameDescription>{table.description}</FrameDescription> : null}
            </FrameHeader>

            <div className="px-5 pb-5">
                {table.rows.length === 0 ? (
                    <p className="text-muted-foreground py-6 text-center text-sm">{emptyMessage}</p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {table.columns.map((column) => (
                                    <TableHead key={column.key} className={column.align === "end" ? "text-right" : undefined}>
                                        {column.label}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {table.rows.map((row, index) => (
                                <TableRow key={String(row.id ?? row.key ?? index)}>
                                    {table.columns.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className={column.align === "end" ? "text-right tabular-nums" : undefined}
                                        >
                                            {String(row[column.key] ?? "—")}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </FramePanel>
    );
}