import { Button } from "@/components/ui/button";
import { Download, ExternalLink, FileText, RotateCw, ZoomIn, ZoomOut } from "lucide-react";
import { useState } from "react";

interface DocumentPreviewViewerProps {
    previewUrl: string | null;
    downloadUrl: string | null;
    fileName: string | null;
    mimeType?: string | null;
    title?: string;
    className?: string;
}

function isImageFile(mimeType?: string | null, fileName?: string | null): boolean {
    if (mimeType?.startsWith("image/")) return true;
    return /\.(png|jpe?g|webp|gif|bmp)$/i.test(fileName ?? "");
}

function isPdfFile(mimeType?: string | null, fileName?: string | null): boolean {
    if (mimeType === "application/pdf") return true;
    return /\.pdf$/i.test(fileName ?? "");
}

export function DocumentPreviewViewer({
    previewUrl,
    downloadUrl,
    fileName,
    mimeType,
    title,
    className = "h-[480px]",
}: DocumentPreviewViewerProps) {
    const [zoom, setZoom] = useState(1);
    const [rotation, setRotation] = useState(0);

    if (!previewUrl) {
        return (
            <div
                className={`border-border/70 bg-muted/20 flex flex-col items-center justify-center rounded-xl border border-dashed p-8 text-center ${className}`}
            >
                <FileText className="text-muted-foreground/50 mb-3 size-10" />
                <p className="text-foreground text-sm font-medium">No digital file attached</p>
                <p className="text-muted-foreground mt-1 max-w-xs text-xs">
                    Upload a scan, drag and drop a file into the slot, or mark a physical copy on file.
                </p>
            </div>
        );
    }

    const isImage = isImageFile(mimeType, fileName);
    const isPdf = isPdfFile(mimeType, fileName);

    return (
        <div className={`border-border/70 bg-card flex flex-col overflow-hidden rounded-xl border ${className}`}>
            <div className="border-border/70 bg-muted/30 flex items-center justify-between gap-2 border-b px-3 py-2">
                <div className="min-w-0">
                    <p className="truncate text-xs font-semibold">{title || fileName || "Document Preview"}</p>
                    {fileName && title && <p className="text-muted-foreground truncate text-[11px]">{fileName}</p>}
                </div>
                <div className="flex shrink-0 items-center gap-1">
                    {isImage && (
                        <>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                onClick={() => setZoom((z) => Math.max(0.5, Number((z - 0.25).toFixed(2))))}
                                title="Zoom out"
                            >
                                <ZoomOut className="size-3.5" />
                            </Button>
                            <span className="text-muted-foreground w-10 text-center font-mono text-[11px] tabular-nums">
                                {Math.round(zoom * 100)}%
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                onClick={() => setZoom((z) => Math.min(2.5, Number((z + 0.25).toFixed(2))))}
                                title="Zoom in"
                            >
                                <ZoomIn className="size-3.5" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                onClick={() => setRotation((r) => (r + 90) % 360)}
                                title="Rotate 90°"
                            >
                                <RotateCw className="size-3.5" />
                            </Button>
                        </>
                    )}
                    <Button
                        render={<a href={previewUrl} target="_blank" rel="noreferrer" />}
                        nativeButton={false}
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        title="Open in new tab"
                    >
                        <ExternalLink className="size-3.5" />
                    </Button>
                    {downloadUrl && (
                        <Button
                            render={<a href={downloadUrl} />}
                            nativeButton={false}
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            title="Download original file"
                        >
                            <Download className="size-3.5" />
                        </Button>
                    )}
                </div>
            </div>

            <div className="bg-muted/15 relative flex flex-1 items-center justify-center overflow-auto p-3">
                {isImage ? (
                    <img
                        src={previewUrl}
                        alt={fileName || "Student document"}
                        className="max-h-full max-w-full rounded-md object-contain shadow-xs transition-transform duration-150"
                        style={{ transform: `scale(${zoom}) rotate(${rotation}deg)` }}
                    />
                ) : isPdf ? (
                    <iframe
                        src={`${previewUrl}#toolbar=1&navpanes=0`}
                        title={fileName || "PDF Document"}
                        className="h-full w-full rounded-md border-0 bg-white"
                    />
                ) : (
                    <div className="flex flex-col items-center justify-center p-6 text-center">
                        <FileText className="text-muted-foreground mb-2 size-10" />
                        <p className="text-sm font-medium">{fileName || "Attached File"}</p>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Inline browser preview is unavailable for this file format.
                        </p>
                        {downloadUrl && (
                            <Button
                                render={<a href={downloadUrl} />}
                                nativeButton={false}
                                size="sm"
                                className="mt-4 gap-1.5"
                            >
                                <Download className="size-3.5" />
                                Download File
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
