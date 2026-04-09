import * as React from "react"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Download, FileText, FileSpreadsheet } from "lucide-react"

interface Column {
  key: string
  label: string
}

interface BulkExportButtonProps {
  data: any[]
  columns: Column[]
  filename?: string
  disabled?: boolean
}

export function BulkExportButton({ data, columns, filename = "export", disabled = false }: BulkExportButtonProps) {
  const handleExportCSV = () => {
    if (!data.length) return

    const headers = columns.map((c) => c.label).join(",")
    const csvData = data.map((row) =>
      columns.map((col) => {
        let val = row[col.key]
        if (val === null || val === undefined) val = ""
        // Escape quotes and wrap in quotes if there's a comma
        const stringVal = String(val).replace(/"/g, '""')
        return `"${stringVal}"`
      }).join(",")
    )

    const csvContent = [headers, ...csvData].join("\n")
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" })
    const url = URL.createObjectURL(blob)
    const link = document.createElement("a")
    link.href = url
    link.setAttribute("download", `${filename}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  const handleExportPDF = () => {
    if (!data.length) return

    // Create a printable window
    const printWindow = window.open("", "_blank")
    if (!printWindow) return

    const htmlContent = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>${filename}</title>
        <style>
          body { font-family: system-ui, -apple-system, sans-serif; padding: 20px; color: #333; }
          .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
          h1 { font-size: 24px; margin: 0 0 5px 0; text-transform: capitalize; }
          .meta { color: #666; font-size: 14px; }
          table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
          th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
          th { background-color: #f9fafb; font-weight: 600; text-transform: uppercase; font-size: 11px; }
          tr:nth-child(even) { background-color: #fcfcfc; }
          @media print {
            body { padding: 0; }
            button { display: none; }
            @page { margin: 1cm; size: landscape; }
          }
        </style>
      </head>
      <body>
        <div class="header">
            <div>
                <h1>${filename.replace(/-/g, ' ')}</h1>
                <div class="meta">Generated on: ${new Date().toLocaleString()} &bull; Total Records: ${data.length}</div>
            </div>
            <button onclick="window.print()" style="padding: 8px 16px; background: #000; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Print / Save PDF</button>
        </div>
        <table>
          <thead>
            <tr>
              ${columns.map((c) => `<th>${c.label}</th>`).join("")}
            </tr>
          </thead>
          <tbody>
            ${data.map((row) => `
              <tr>
                ${columns.map((c) => {
                  let val = row[c.key]
                  if (val === null || val === undefined) val = "—"
                  return `<td>${String(val)}</td>`
                }).join("")}
              </tr>
            `).join("")}
          </tbody>
        </table>
        <script>
            setTimeout(() => {
                window.print();
            }, 500);
        </script>
      </body>
      </html>
    `

    printWindow.document.write(htmlContent)
    printWindow.document.close()
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm" className="gap-2" disabled={disabled || data.length === 0}>
          <Download className="h-4 w-4" />
          Export ({data.length})
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onClick={handleExportCSV}>
          <FileSpreadsheet className="mr-2 h-4 w-4" />
          Export as CSV
        </DropdownMenuItem>
        <DropdownMenuItem onClick={handleExportPDF}>
          <FileText className="mr-2 h-4 w-4" />
          Export as PDF (Printable)
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
