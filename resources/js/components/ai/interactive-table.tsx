import { Button } from "@/components/ui/button";
import { Check, Copy, Download, Table as TableIcon } from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

interface InteractiveTableProps {
    headers: string[];
    rows: string[][];
    caption?: string;
}

export function InteractiveTable({ headers, rows, caption }: InteractiveTableProps) {
    const [copied, setCopied] = React.useState(false);

    const exportCsv = () => {
        const csvContent =
            headers.map((h) => `"${h}"`).join(",") +
            "\n" +
            rows.map((row) => row.map((cell) => `"${cell.replace(/"/g, '""')}"`).join(",")).join("\n");

        const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `table_export_${Date.now()}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        toast.success("Table exported as CSV.");
    };

    const copyMarkdown = () => {
        const mdHeader = `| ${headers.join(" | ")} |\n| ${headers.map(() => "---").join(" | ")} |\n`;
        const mdRows = rows.map((r) => `| ${r.join(" | ")} |`).join("\n");
        navigator.clipboard.writeText(mdHeader + mdRows);
        setCopied(true);
        toast.success("Table copied to clipboard.");
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="my-3 rounded-xl border bg-background/95 overflow-hidden shadow-xs">
            <div className="flex items-center justify-between px-3.5 py-2 border-b bg-muted/20 text-xs font-semibold text-muted-foreground">
                <span className="flex items-center gap-1.5 text-foreground">
                    <TableIcon className="size-3.5 text-indigo-500" />
                    {caption || "Data Summary Table"}
                </span>

                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-6.5 text-[11px] px-2 gap-1"
                        onClick={copyMarkdown}
                        title="Copy as markdown"
                    >
                        {copied ? <Check className="size-3 text-emerald-500" /> : <Copy className="size-3" />}
                        Copy
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-6.5 text-[11px] px-2 gap-1 text-primary"
                        onClick={exportCsv}
                        title="Export CSV"
                    >
                        <Download className="size-3" />
                        CSV
                    </Button>
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-xs text-left border-collapse">
                    <thead className="bg-muted/40 font-medium text-foreground/80 border-b">
                        <tr>
                            {headers.map((h, i) => (
                                <th key={i} className="px-3.5 py-2 font-semibold">
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border/60 font-mono text-[11px]">
                        {rows.map((row, rIdx) => (
                            <tr
                                key={rIdx}
                                className={rIdx % 2 === 0 ? "bg-background" : "bg-muted/15"}
                            >
                                {row.map((cell, cIdx) => (
                                    <td key={cIdx} className="px-3.5 py-2 tabular-nums">
                                        {cell}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
