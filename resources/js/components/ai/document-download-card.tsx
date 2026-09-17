import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
    Check,
    Copy,
    Download,
    FileSpreadsheet,
    FileText,
    FileType,
    Loader2,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";

export interface DocumentArtifact {
    document_id: string;
    title: string;
    filename: string;
    format: "pdf" | "csv" | "markdown" | string;
    category?: string;
    download_url?: string;
    summary?: string;
    file_size?: string;
    content?: string;
}

interface DocumentDownloadCardProps {
    document: DocumentArtifact;
    downloadEndpoint?: string;
}

export function DocumentDownloadCard({
    document,
    downloadEndpoint = "/administrators/ai/download-document",
}: DocumentDownloadCardProps) {
    const [downloading, setDownloading] = React.useState(false);
    const [copied, setCopied] = React.useState(false);

    const getIcon = () => {
        switch (document.format?.toLowerCase()) {
            case "pdf":
                return <FileText className="size-5 text-rose-500" />;
            case "csv":
                return <FileSpreadsheet className="size-5 text-emerald-500" />;
            default:
                return <FileType className="size-5 text-sky-500" />;
        }
    };

    const handleDownload = async () => {
        setDownloading(true);

        try {
            // First try direct server download URL
            const targetUrl = document.download_url || `${downloadEndpoint}/${document.document_id}`;

            // Check if direct server route responds
            const response = await fetch(targetUrl, {
                method: "GET",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (response.ok) {
                const blob = await response.blob();
                const blobUrl = window.URL.createObjectURL(blob);
                const a = window.document.createElement("a");
                a.href = blobUrl;
                a.download = document.filename || `${document.title}.${document.format || "pdf"}`;
                window.document.body.appendChild(a);
                a.click();
                window.document.body.removeChild(a);
                window.URL.revokeObjectURL(blobUrl);

                toast.success(`Downloaded "${document.filename || document.title}"`);
            } else if (document.content) {
                // Client-side fallback if cache expired but content is in payload
                const mimeType = document.format === "csv" ? "text/csv;charset=utf-8;" : "text/markdown;charset=utf-8;";
                const blob = new Blob([document.content], { type: mimeType });
                const blobUrl = URL.createObjectURL(blob);
                const a = window.document.createElement("a");
                a.href = blobUrl;
                a.download = document.filename;
                window.document.body.appendChild(a);
                a.click();
                window.document.body.removeChild(a);
                URL.revokeObjectURL(blobUrl);

                toast.success(`Exported "${document.filename}"`);
            } else {
                throw new Error("Unable to download document.");
            }
        } catch (error: any) {
            toast.error(error.message || "Download failed. Please try again.");
        } finally {
            setDownloading(false);
        }
    };

    const handleCopy = () => {
        if (document.content) {
            navigator.clipboard.writeText(document.content);
            setCopied(true);
            toast.success("Document text copied to clipboard.");
            setTimeout(() => setCopied(false), 2000);
        }
    };

    return (
        <Card className="border border-border/80 bg-background/95 shadow-sm overflow-hidden my-3 hover:border-primary/40 transition-colors">
            <CardContent className="p-3.5">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3 min-w-0">
                        <div className="p-2.5 rounded-xl border bg-muted/40 shrink-0 mt-0.5">
                            {getIcon()}
                        </div>
                        <div className="space-y-1 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <h5 className="text-xs font-semibold text-foreground truncate">
                                    {document.title}
                                </h5>
                                <Badge variant="secondary" className="text-[10px] uppercase font-mono px-1.5 py-0">
                                    {document.format}
                                </Badge>
                                {document.file_size && (
                                    <span className="text-[10px] text-muted-foreground font-mono">
                                        {document.file_size}
                                    </span>
                                )}
                            </div>
                            <p className="text-[11px] text-muted-foreground line-clamp-2 leading-relaxed">
                                {document.summary || "Official administrative artifact prepared by AI Copilot."}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-1.5 shrink-0">
                        {document.content && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 text-muted-foreground hover:text-foreground"
                                onClick={handleCopy}
                                title="Copy content"
                            >
                                {copied ? <Check className="size-3.5 text-emerald-500" /> : <Copy className="size-3.5" />}
                            </Button>
                        )}

                        <Button
                            type="button"
                            size="sm"
                            onClick={handleDownload}
                            disabled={downloading}
                            className="text-xs h-7.5 gap-1.5 px-3 bg-primary text-primary-foreground hover:bg-primary/90"
                        >
                            {downloading ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <Download className="size-3.5" />
                            )}
                            <span>Download</span>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
