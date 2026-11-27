import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Download, ExternalLink, FileText, RotateCw, ZoomIn, ZoomOut } from "lucide-react";
import { useEffect, useRef, useState } from "react";

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
    const [naturalSize, setNaturalSize] = useState<{ width: number; height: number } | null>(null);
    const [viewportSize, setViewportSize] = useState<{ width: number; height: number } | null>(null);
    const viewportRef = useRef<HTMLDivElement | null>(null);
    const imgRef = useRef<HTMLImageElement | null>(null);

    useEffect(() => {
        setZoom(1);
        setRotation(0);
        const img = imgRef.current;
        if (img && img.complete && img.naturalWidth > 0 && img.naturalHeight > 0) {
            setNaturalSize({ width: img.naturalWidth, height: img.naturalHeight });
        } else {
            setNaturalSize(null);
        }
    }, [previewUrl]);

    useEffect(() => {
        const container = viewportRef.current;
        if (!container) return;

        const updateSize = () => {
            const style = window.getComputedStyle(container);
            const paddingX = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
            const paddingY = (parseFloat(style.paddingTop) || 0) + (parseFloat(style.paddingBottom) || 0);
            const width = Math.max(0, container.clientWidth - paddingX);
            const height = Math.max(0, container.clientHeight - paddingY);
            setViewportSize((prev) =>
                prev && prev.width === width && prev.height === height ? prev : { width, height },
            );
        };

        updateSize();
        if (typeof ResizeObserver === "undefined") return;

        const observer = new ResizeObserver(updateSize);
        observer.observe(container);
        return () => observer.disconnect();
    }, [previewUrl]);

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

    let baseWidth: number | null = null;
    let baseHeight: number | null = null;
    let boxWidth: number | null = null;
    let boxHeight: number | null = null;

    if (
        isImage &&
        naturalSize &&
        viewportSize &&
        naturalSize.width > 0 &&
        naturalSize.height > 0 &&
        viewportSize.width > 0 &&
        viewportSize.height > 0
    ) {
        const fitScale = Math.min(
            viewportSize.width / naturalSize.width,
            viewportSize.height / naturalSize.height,
            1,
        );
        baseWidth = Math.max(1, Math.floor(naturalSize.width * fitScale));
        baseHeight = Math.max(1, Math.floor(naturalSize.height * fitScale));
        const isQuarterTurn = rotation % 180 !== 0;
        boxWidth = Math.ceil((isQuarterTurn ? baseHeight : baseWidth) * zoom);
        boxHeight = Math.ceil((isQuarterTurn ? baseWidth : baseHeight) * zoom);
    }

    const hasMeasuredBounds = baseWidth !== null && baseHeight !== null && boxWidth !== null && boxHeight !== null;

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

            <div ref={viewportRef} className="bg-muted/15 relative flex flex-1 overflow-auto p-3">
                {isImage ? (
                    <div
                        className="relative m-auto flex shrink-0 items-center justify-center"
                        style={
                            hasMeasuredBounds
                                ? { width: `${boxWidth}px`, height: `${boxHeight}px` }
                                : undefined
                        }
                    >
                        <img
                            ref={imgRef}
                            src={previewUrl}
                            alt={fileName || "Student document"}
                            onLoad={(e) => {
                                const target = e.currentTarget;
                                if (target.naturalWidth > 0 && target.naturalHeight > 0) {
                                    setNaturalSize({ width: target.naturalWidth, height: target.naturalHeight });
                                }
                            }}
                            className={cn(
                                "rounded-md shadow-xs motion-safe:transition-transform motion-safe:duration-150 motion-reduce:transition-none",
                                hasMeasuredBounds ? "max-h-none max-w-none shrink-0" : "max-h-full max-w-full object-contain",
                            )}
                            style={
                                hasMeasuredBounds
                                    ? {
                                          width: `${baseWidth}px`,
                                          height: `${baseHeight}px`,
                                          transform: `scale(${zoom}) rotate(${rotation}deg)`,
                                      }
                                    : { transform: `scale(${zoom}) rotate(${rotation}deg)` }
                            }
                        />
                    </div>
                ) : isPdf ? (
                    <iframe
                        src={`${previewUrl}#toolbar=1&navpanes=0`}
                        title={fileName || "PDF Document"}
                        className="h-full w-full rounded-md border-0 bg-white"
                    />
                ) : (
                    <div className="m-auto flex flex-col items-center justify-center p-6 text-center">
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
