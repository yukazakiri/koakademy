import { Button } from "@/components/ui/button";
import {
    IconFile,
    IconFileTypePdf,
    IconFileTypeDoc,
    IconFileTypeXls,
    IconFileTypePpt,
    IconFileTypeTxt,
    IconFileZip,
    IconFileMusic,
    IconFileCode,
    IconPhoto,
    IconVideo,
    IconLink,
    IconX,
    IconEye,
} from "@tabler/icons-react";
import { useMemo, useState } from "react";

interface FilePreviewProps {
    name: string;
    url?: string;
    size?: number;
    kind: "file" | "link";
    file?: File;
    onRemove?: () => void;
}

const getFileIcon = (name: string) => {
    const lower = name.toLowerCase();
    if (/\.(jpg|jpeg|png|gif|svg|webp|bmp|ico)$/i.test(lower)) return IconPhoto;
    if (/\.pdf$/i.test(lower)) return IconFileTypePdf;
    if (/\.(doc|docx|odt)$/i.test(lower)) return IconFileTypeDoc;
    if (/\.(xls|xlsx|ods|csv)$/i.test(lower)) return IconFileTypeXls;
    if (/\.(ppt|pptx|odp)$/i.test(lower)) return IconFileTypePpt;
    if (/\.(txt|rtf|md)$/i.test(lower)) return IconFileTypeTxt;
    if (/\.(zip|rar|7z|tar|gz)$/i.test(lower)) return IconFileZip;
    if (/\.(mp3|wav|ogg|flac|aac|m4a)$/i.test(lower)) return IconFileMusic;
    if (/\.(mp4|webm|ogg|mov|avi|mkv)$/i.test(lower)) return IconVideo;
    if (/\.(js|ts|jsx|tsx|php|py|java|cpp|c|h|html|css|json|xml|sql)$/i.test(lower)) return IconFileCode;
    return IconFile;
};

const isImage = (name: string): boolean => /\.(jpg|jpeg|png|gif|svg|webp|bmp|ico)$/i.test(name);
const isVideo = (name: string): boolean => /\.(mp4|webm|ogg|mov|avi|mkv)$/i.test(name);
const isPdf = (name: string): boolean => /\.pdf$/i.test(name);

export function FilePreview({ name, url, size, kind, file, onRemove }: FilePreviewProps) {
    const [previewError, setPreviewError] = useState(false);

    const objectUrl = useMemo(() => {
        if (file && (isImage(file.name) || isVideo(file.name) || isPdf(file.name))) {
            return URL.createObjectURL(file);
        }
        return undefined;
    }, [file]);

    const displayUrl = objectUrl || url || "";
    const FileIcon = getFileIcon(name);
    const isImageFile = isImage(name);
    const isVideoFile = isVideo(name);
    const isPdfFile = isPdf(name);
    const canPreview = isImageFile || isVideoFile || isPdfFile;

    const formattedSize = size ? `${(size / (1024 * 1024)).toFixed(2)} MB` : undefined;

    return (
        <div className="bg-muted/40 group/file flex w-full min-w-0 max-w-full items-start gap-3 overflow-hidden rounded-lg border px-3 py-2.5">
            {/* Preview thumbnail */}
            <div className="relative shrink-0">
                {canPreview && displayUrl && !previewError ? (
                    <div className="border-border/60 relative size-14 overflow-hidden rounded-lg border bg-black/5">
                        {isImageFile && (
                            <img
                                src={displayUrl}
                                alt={name}
                                className="h-full w-full object-cover"
                                onError={() => setPreviewError(true)}
                            />
                        )}
                        {isPdfFile && (
                            <>
                                <object
                                    data={displayUrl}
                                    type="application/pdf"
                                    className="h-full w-full"
                                    onError={() => setPreviewError(true)}
                                >
                                    <div className="flex h-full w-full items-center justify-center">
                                        <IconFileTypePdf className="text-primary size-6" />
                                    </div>
                                </object>
                                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/40 to-transparent p-1">
                                    <span className="text-[10px] font-medium text-white">PDF</span>
                                </div>
                            </>
                        )}
                        {isVideoFile && (
                            <>
                                <video
                                    src={displayUrl}
                                    className="h-full w-full object-cover"
                                    preload="metadata"
                                    onError={() => setPreviewError(true)}
                                />
                                <div className="absolute inset-0 flex items-center justify-center">
                                    <div className="flex size-6 items-center justify-center rounded-full bg-black/50">
                                        <IconVideo className="size-3 text-white" />
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                ) : (
                    <div className="border-border/60 bg-primary/5 flex size-14 items-center justify-center rounded-lg border">
                        {kind === "link" ? (
                            <IconLink className="text-primary size-6" />
                        ) : (
                            <FileIcon className="text-primary size-6" />
                        )}
                    </div>
                )}
            </div>

            {/* File info */}
            <div className="min-w-0 flex-1 overflow-hidden pt-0.5">
                <p className="truncate text-sm font-medium" title={name}>
                    {name.length > 40 ? `${name.slice(0, 37)}...${name.split(".").pop() || ""}` : name}
                </p>
                <p className="text-muted-foreground text-xs">
                    {kind === "link" ? "Link" : formattedSize || getFileTypeLabel(name)}
                </p>
            </div>

            {/* Actions */}
            <div className="flex shrink-0 items-center gap-1">
                {displayUrl && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => window.open(displayUrl, "_blank")}
                        title="Open preview"
                    >
                        <IconEye className="size-4" />
                    </Button>
                )}
                {onRemove && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={onRemove}
                        title="Remove"
                    >
                        <IconX className="size-4" />
                    </Button>
                )}
            </div>
        </div>
    );
}

function getFileTypeLabel(name: string): string {
    const lower = name.toLowerCase();
    if (/\.pdf$/i.test(lower)) return "PDF Document";
    if (/\.(doc|docx|odt)$/i.test(lower)) return "Word Document";
    if (/\.(xls|xlsx|ods|csv)$/i.test(lower)) return "Spreadsheet";
    if (/\.(ppt|pptx|odp)$/i.test(lower)) return "Presentation";
    if (/\.(txt|rtf|md)$/i.test(lower)) return "Text Document";
    if (/\.(zip|rar|7z|tar|gz)$/i.test(lower)) return "Archive";
    if (/\.(mp3|wav|ogg|flac|aac|m4a)$/i.test(lower)) return "Audio";
    if (/\.(mp4|webm|ogg|mov|avi|mkv)$/i.test(lower)) return "Video";
    if (/\.(js|ts|jsx|tsx|php|py|java|cpp|c|h|html|css|json|xml|sql)$/i.test(lower)) return "Code File";
    return "File";
}
