import { useBranding } from "@/lib/branding";
import { escapeHtml } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Download, FileSpreadsheet, Printer, RotateCcw } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";

type ExportValue = boolean | number | string | null | undefined;
type ExportSortOrder = "current" | "name_asc" | "name_desc";

export interface ExportColumn<TData> {
    key: string;
    label: string;
    defaultSelected?: boolean;
    getValue: (row: TData) => ExportValue;
}

interface BulkExportButtonProps<TData> {
    data: TData[];
    columns: ExportColumn<TData>[];
    filename?: string;
    title?: string;
    disabled?: boolean;
    getSortValue: (row: TData) => string;
    getSortTieBreaker?: (row: TData) => string;
}

const sortLabels: Record<ExportSortOrder, string> = {
    current: "Current table order",
    name_asc: "Name A-Z",
    name_desc: "Name Z-A",
};

export { escapeHtml };

function displayValue(value: ExportValue, emptyValue = ""): string {
    if (value === null || value === undefined || value === "") {
        return emptyValue;
    }

    if (typeof value === "boolean") {
        return value ? "Yes" : "No";
    }

    return String(value);
}

function csvValue(value: ExportValue): string {
    let text = displayValue(value);

    // Prevent spreadsheet applications from evaluating exported values as formulas.
    if (/^[=+\-@]/.test(text)) {
        text = `'${text}`;
    }

    return `"${text.replace(/"/g, '""')}"`;
}

function downloadBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 0);
}

export function BulkExportButton<TData>({
    data,
    columns,
    filename = "export",
    title = "Selected records",
    disabled = false,
    getSortValue,
    getSortTieBreaker,
}: BulkExportButtonProps<TData>) {
    const branding = useBranding();
    const defaultColumnKeys = useMemo(
        () => columns.filter((column) => column.defaultSelected !== false).map((column) => column.key),
        [columns],
    );
    const [open, setOpen] = useState(false);
    const [selectedColumnKeys, setSelectedColumnKeys] = useState<string[]>(defaultColumnKeys);
    const [sortOrder, setSortOrder] = useState<ExportSortOrder>("current");

    const selectedColumns = columns.filter((column) => selectedColumnKeys.includes(column.key));
    const allColumnsSelected = selectedColumns.length === columns.length;
    const isDefaultConfiguration =
        sortOrder === "current" &&
        selectedColumnKeys.length === defaultColumnKeys.length &&
        defaultColumnKeys.every((key) => selectedColumnKeys.includes(key));
    const canExport = data.length > 0 && selectedColumns.length > 0;

    const orderedData = useMemo(() => {
        if (sortOrder === "current") {
            return data;
        }

        const collator = new Intl.Collator("en", { numeric: true, sensitivity: "base" });
        const direction = sortOrder === "name_asc" ? 1 : -1;

        return data
            .map((row, index) => ({ index, row }))
            .sort((left, right) => {
                const nameComparison = collator.compare(getSortValue(left.row), getSortValue(right.row));
                if (nameComparison !== 0) {
                    return nameComparison * direction;
                }

                if (getSortTieBreaker) {
                    const tieComparison = collator.compare(getSortTieBreaker(left.row), getSortTieBreaker(right.row));
                    if (tieComparison !== 0) {
                        return tieComparison * direction;
                    }
                }

                return left.index - right.index;
            })
            .map(({ row }) => row);
    }, [data, getSortTieBreaker, getSortValue, sortOrder]);

    const toggleColumn = (key: string, checked: boolean): void => {
        setSelectedColumnKeys((current) => {
            if (checked) {
                return columns.filter((column) => current.includes(column.key) || column.key === key).map((column) => column.key);
            }

            return current.filter((columnKey) => columnKey !== key);
        });
    };

    const handleExportCsv = (): void => {
        if (!canExport) return;

        const headers = selectedColumns.map((column) => csvValue(column.label)).join(",");
        const rows = orderedData.map((row) => selectedColumns.map((column) => csvValue(column.getValue(row))).join(","));
        const content = `\uFEFF${[headers, ...rows].join("\r\n")}`;

        downloadBlob(new Blob([content], { type: "text/csv;charset=utf-8" }), `${filename}-${new Date().toISOString().slice(0, 10)}.csv`);
    };

    const handlePrint = (): void => {
        if (!canExport) return;

        const printWindow = window.open("", "_blank");
        if (!printWindow) {
            toast.error("The print window was blocked. Allow pop-ups and try again.");
            return;
        }

        printWindow.opener = null;
        const orientation = selectedColumns.length <= 4 ? "portrait" : "landscape";
        const organizationAddress = branding.organizationAddress
            ? `<div class="organization-address">${escapeHtml(branding.organizationAddress)}</div>`
            : "";
        const headerCells = selectedColumns.map((column) => `<th>${escapeHtml(column.label)}</th>`).join("");
        const bodyRows = orderedData
            .map(
                (row) =>
                    `<tr>${selectedColumns
                        .map((column) => `<td>${escapeHtml(displayValue(column.getValue(row), "—"))}</td>`)
                        .join("")}</tr>`,
            )
            .join("");

        printWindow.document.write(`
            <!doctype html>
            <html lang="en">
                <head>
                    <meta charset="utf-8" />
                    <meta name="viewport" content="width=device-width, initial-scale=1" />
                    <title>${escapeHtml(title)}</title>
                    <style>
                        * { box-sizing: border-box; }
                        body { margin: 0; padding: 24px; color: #111827; background: #fff; font-family: Inter, Arial, sans-serif; }
                        .header { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; margin-bottom: 20px; }
                        .organization { color: #111827; font-size: 13px; font-weight: 700; }
                        .organization-address { max-width: 440px; margin-top: 3px; color: #4b5563; font-size: 10px; line-height: 1.4; }
                        h1 { margin: 14px 0 0; font-size: 22px; line-height: 1.2; }
                        .meta { margin-top: 6px; color: #4b5563; font-size: 10px; line-height: 1.5; }
                        .print-button { border: 0; border-radius: 4px; padding: 9px 14px; color: #fff; background: #111827; cursor: pointer; font-size: 12px; font-weight: 600; }
                        table { width: 100%; border-collapse: collapse; table-layout: auto; font-size: 9px; }
                        thead { display: table-header-group; }
                        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
                        th { color: #111827; background: #f3f4f6; font-size: 8px; font-weight: 700; text-transform: uppercase; }
                        tbody tr:nth-child(even) { background: #f9fafb; }
                        tr { break-inside: avoid; page-break-inside: avoid; }
                        @media print {
                            body { padding: 0; }
                            .print-button { display: none; }
                            @page { size: A4 ${orientation}; margin: 10mm; }
                        }
                    </style>
                </head>
                <body>
                    <header class="header">
                        <div>
                            <div class="organization">${escapeHtml(branding.organizationName)}</div>
                            ${organizationAddress}
                            <h1>${escapeHtml(title)}</h1>
                            <div class="meta">
                                Generated ${escapeHtml(new Date().toLocaleString())}<br />
                                ${orderedData.length} ${orderedData.length === 1 ? "student" : "students"} · ${escapeHtml(sortLabels[sortOrder])}
                            </div>
                        </div>
                        <button type="button" class="print-button" onclick="window.print()">Print / Save PDF</button>
                    </header>
                    <table>
                        <thead><tr>${headerCells}</tr></thead>
                        <tbody>${bodyRows}</tbody>
                    </table>
                    <script>window.addEventListener("load", () => setTimeout(() => window.print(), 250));</script>
                </body>
            </html>
        `);
        printWindow.document.close();
    };

    return (
        <>
            <Button variant="outline" size="sm" className="gap-2" disabled={disabled || data.length === 0} onClick={() => setOpen(true)}>
                <Download className="size-4" />
                Export ({data.length})
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[calc(100vh-2rem)] overflow-hidden p-0 sm:max-w-2xl">
                    <DialogHeader className="border-b px-5 py-4 text-left">
                        <DialogTitle>Export selected students</DialogTitle>
                        <DialogDescription>
                            Choose the columns and order for {data.length} selected {data.length === 1 ? "student" : "students"}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid min-h-0 gap-5 overflow-y-auto px-5 py-1 sm:grid-cols-[minmax(0,1fr)_14rem]">
                        <section aria-labelledby="export-columns-heading">
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <h3 id="export-columns-heading" className="text-sm font-semibold">
                                        Columns
                                    </h3>
                                    <p className="text-muted-foreground text-xs">{selectedColumns.length} selected</p>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setSelectedColumnKeys(columns.map((column) => column.key))}
                                        disabled={allColumnsSelected}
                                    >
                                        Select all
                                    </Button>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setSelectedColumnKeys([])} disabled={selectedColumns.length === 0}>
                                        Clear
                                    </Button>
                                </div>
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2">
                                {columns.map((column) => {
                                    const checked = selectedColumnKeys.includes(column.key);

                                    return (
                                        <Label
                                            key={column.key}
                                            htmlFor={`export-column-${column.key}`}
                                            className="border-border hover:bg-muted/40 flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2.5"
                                        >
                                            <Checkbox
                                                id={`export-column-${column.key}`}
                                                checked={checked}
                                                onCheckedChange={(value) => toggleColumn(column.key, value === true)}
                                            />
                                            <span className="text-sm font-medium">{column.label}</span>
                                        </Label>
                                    );
                                })}
                            </div>
                        </section>

                        <aside className="space-y-5">
                            <div className="space-y-2">
                                <Label htmlFor="export-sort-order">Record order</Label>
                                <Select value={sortOrder} onValueChange={(value) => setSortOrder(value as ExportSortOrder)}>
                                    <SelectTrigger id="export-sort-order" className="w-full">
                                        <SelectValue placeholder="Choose order" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="current">Current table order</SelectItem>
                                        <SelectItem value="name_asc">Name A-Z</SelectItem>
                                        <SelectItem value="name_desc">Name Z-A</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="bg-muted/40 rounded-md p-3 text-xs leading-5">
                                <div className="text-foreground font-medium">Export summary</div>
                                <div className="text-muted-foreground mt-1">
                                    {data.length} records<br />
                                    {selectedColumns.length} columns<br />
                                    {sortLabels[sortOrder]}
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="w-full justify-start"
                                onClick={() => {
                                    setSelectedColumnKeys(defaultColumnKeys);
                                    setSortOrder("current");
                                }}
                                disabled={isDefaultConfiguration}
                            >
                                <RotateCcw className="size-4" />
                                Reset defaults
                            </Button>
                        </aside>
                    </div>

                    {selectedColumns.length === 0 && <p className="text-destructive px-5 text-xs">Select at least one column to export.</p>}

                    <DialogFooter className="border-t px-5 py-4">
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="button" variant="outline" onClick={handleExportCsv} disabled={!canExport}>
                            <FileSpreadsheet className="size-4" />
                            Download CSV
                        </Button>
                        <Button type="button" onClick={handlePrint} disabled={!canExport}>
                            <Printer className="size-4" />
                            Print / Save PDF
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
