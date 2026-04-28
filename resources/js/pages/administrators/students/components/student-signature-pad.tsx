import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Tabs, TabsList, TabsTab, TabsPanel } from "@/components/ui/tabs";
import { useTheme } from "@/hooks/use-theme";
import { cn } from "@/lib/utils";
import { router } from "@inertiajs/react";
import { Eraser, ImageUp, PencilLine, Save, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import SignaturePad from "signature_pad";
import { toast } from "sonner";

interface StudentSignaturePadProps {
    studentId: number;
    signatureUrl: string | null;
}

const MODAL_CANVAS_HEIGHT = 220;

export function StudentSignaturePad({ studentId, signatureUrl }: StudentSignaturePadProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const signaturePadRef = useRef<SignaturePad | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [activeTab, setActiveTab] = useState<string>("draw");
    const [uploadFile, setUploadFile] = useState<File | null>(null);
    const [uploadPreview, setUploadPreview] = useState<string | null>(null);
    const [isDragOver, setIsDragOver] = useState(false);
    const { actualTheme } = useTheme();

    const initializePad = () => {
        const canvas = canvasRef.current;
        const wrapper = wrapperRef.current;

        if (!canvas || !wrapper || !isDialogOpen || activeTab !== "draw") {
            return;
        }

        const width = Math.max(wrapper.clientWidth, 300);

        if (width <= 0) {
            return;
        }

        const ratio = Math.max(window.devicePixelRatio || 1, 1);

        canvas.width = width * ratio;
        canvas.height = MODAL_CANVAS_HEIGHT * ratio;
        canvas.style.width = `${width}px`;
        canvas.style.height = `${MODAL_CANVAS_HEIGHT}px`;

        const context = canvas.getContext("2d");
        if (!context) {
            return;
        }

        context.scale(ratio, ratio);

        const dark = document.documentElement.classList.contains("dark");

        signaturePadRef.current = new SignaturePad(canvas, {
            minWidth: 1,
            maxWidth: 2.2,
            penColor: dark ? "rgb(255,255,255)" : "rgb(30,41,59)",
            backgroundColor: "rgba(255,255,255,0)",
        });
    };

    useEffect(() => {
        if (!isDialogOpen || activeTab !== "draw") {
            return;
        }

        const timer = setTimeout(() => {
            requestAnimationFrame(() => {
                initializePad();
            });
        }, 50);

        return () => {
            clearTimeout(timer);
            signaturePadRef.current?.off();
            signaturePadRef.current = null;
        };
    }, [isDialogOpen, actualTheme, activeTab]);

    useEffect(() => {
        if (!isDialogOpen || activeTab !== "draw") {
            return;
        }

        const handleResize = () => {
            const existingStrokeData = signaturePadRef.current?.toData() ?? [];

            requestAnimationFrame(() => {
                initializePad();

                if (existingStrokeData.length > 0 && signaturePadRef.current) {
                    signaturePadRef.current.fromData(existingStrokeData);
                }
            });
        };

        window.addEventListener("resize", handleResize);

        return () => {
            window.removeEventListener("resize", handleResize);
        };
    }, [isDialogOpen, actualTheme, activeTab]);

    const resetUploadState = () => {
        if (uploadPreview) {
            URL.revokeObjectURL(uploadPreview);
        }
        setUploadFile(null);
        setUploadPreview(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = "";
        }
    };

    const handleDialogClose = (open: boolean) => {
        setIsDialogOpen(open);
        if (!open) {
            resetUploadState();
            setActiveTab("draw");
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) {
            return;
        }

        processFile(file);
    };

    const processFile = (file: File) => {
        if (!file.type.startsWith("image/")) {
            toast.error("Please select a valid image file.");
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            toast.error("Image must be smaller than 5MB.");
            return;
        }

        if (uploadPreview) {
            URL.revokeObjectURL(uploadPreview);
        }

        setUploadFile(file);
        setUploadPreview(URL.createObjectURL(file));
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(false);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragOver(false);

        const file = e.dataTransfer.files[0];
        if (!file) {
            return;
        }

        setIsDialogOpen(true);
        setActiveTab("upload");
        processFile(file);
    };

    const handleRemoveFile = () => {
        resetUploadState();
    };

    const handleClear = () => {
        signaturePadRef.current?.clear();
    };

    const normalizeToDarkInk = (sourceCanvas: HTMLCanvasElement): HTMLCanvasElement => {
        const tempCanvas = document.createElement("canvas");
        tempCanvas.width = sourceCanvas.width;
        tempCanvas.height = sourceCanvas.height;

        const tempContext = tempCanvas.getContext("2d");
        if (!tempContext) {
            return sourceCanvas;
        }

        tempContext.drawImage(sourceCanvas, 0, 0);

        const imageData = tempContext.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
        const pixels = imageData.data;

        for (let i = 0; i < pixels.length; i += 4) {
            if (pixels[i + 3] > 0) {
                pixels[i] = 30;
                pixels[i + 1] = 41;
                pixels[i + 2] = 59;
            }
        }

        tempContext.putImageData(imageData, 0, 0);

        return tempCanvas;
    };

    const normalizeUploadToDarkInk = (file: File): Promise<HTMLCanvasElement> => {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const tempCanvas = document.createElement("canvas");
                tempCanvas.width = img.naturalWidth;
                tempCanvas.height = img.naturalHeight;

                const ctx = tempCanvas.getContext("2d");
                if (!ctx) {
                    resolve(tempCanvas);
                    return;
                }

                ctx.drawImage(img, 0, 0);
                const normalized = normalizeToDarkInk(tempCanvas);
                URL.revokeObjectURL(img.src);
                resolve(normalized);
            };
            img.src = URL.createObjectURL(file);
        });
    };

    const submitSignatureFile = (file: File, previewUrl: string | null) => {
        setSaving(true);

        const optimisticUrl = previewUrl ?? (signatureUrl ?? undefined);

        router.optimistic((props) => ({
            ...props,
            student: {
                ...props.student,
                signature_url: optimisticUrl,
            },
        })).post(
            route("administrators.students.signature.update", studentId),
            {
                signature: file,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setIsDialogOpen(false);
                    resetUploadState();
                    toast.success("Signature saved successfully.");
                },
                onError: (errors) => {
                    const signatureError = typeof errors.signature === "string" ? errors.signature : null;
                    toast.error(signatureError ?? "Failed to save signature.");
                },
                onFinish: () => {
                    setSaving(false);
                },
            },
        );
    };

    const handleSave = () => {
        if (activeTab === "upload") {
            if (!uploadFile) {
                toast.error("Please select an image to upload.");
                return;
            }

            setSaving(true);

            normalizeUploadToDarkInk(uploadFile).then((normalizedCanvas) => {
                normalizedCanvas.toBlob((blob) => {
                    if (!blob) {
                        setSaving(false);
                        toast.error("Unable to generate signature file.");
                        return;
                    }

                    const signatureFile = new File([blob], `student-${studentId}-signature.png`, { type: "image/png" });
                    submitSignatureFile(signatureFile, uploadPreview);
                }, "image/png");
            });
            return;
        }

        const signaturePad = signaturePadRef.current;
        const canvas = canvasRef.current;

        if (!signaturePad || !canvas) {
            toast.error("Signature pad is not ready yet.");
            return;
        }

        if (signaturePad.isEmpty()) {
            toast.error("Please provide a signature before saving.");
            return;
        }

        setSaving(true);

        const normalizedCanvas = normalizeToDarkInk(canvas);

        normalizedCanvas.toBlob((blob) => {
            if (!blob) {
                setSaving(false);
                toast.error("Unable to generate signature file.");
                return;
            }

            const signatureFile = new File([blob], `student-${studentId}-signature.png`, { type: "image/png" });

            const optimisticUrl = normalizedCanvas.toDataURL("image/png");
            submitSignatureFile(signatureFile, optimisticUrl);
        }, "image/png");
    };

    return (
        <div
            className="w-full"
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
        >
            <button
                type="button"
                onClick={() => setIsDialogOpen(true)}
                className={cn(
                    "group w-full text-left transition-colors",
                    isDragOver && "bg-primary/5",
                )}
            >
                {signatureUrl ? (
                    <div className="relative">
                        <div className="bg-muted/20 flex h-[72px] items-end justify-center px-4 pb-1.5 transition-colors group-hover:bg-muted/40">
                            <img
                                src={signatureUrl}
                                alt="Student signature"
                                className="h-12 max-w-[180px] object-contain dark:invert"
                            />
                        </div>
                        <div className="border-foreground/20 border-b" />
                        <span className="text-muted-foreground mt-1 inline-flex items-center gap-1 text-[10px] transition-colors group-hover:text-primary">
                            <PencilLine className="h-3 w-3" />
                            Click to update
                        </span>
                    </div>
                ) : (
                    <div className="relative">
                        <div className="flex h-[72px] items-center justify-center transition-colors group-hover:bg-muted/20">
                            <span className="text-muted-foreground/60 text-xs transition-colors group-hover:text-muted-foreground">
                                No signature — click to add
                            </span>
                        </div>
                        <div className="border-dashed border-foreground/15 border-b" />
                        <span className="text-muted-foreground mt-1 inline-flex items-center gap-1 text-[10px] transition-colors group-hover:text-primary">
                            <PencilLine className="h-3 w-3" />
                            Add signature
                        </span>
                    </div>
                )}
            </button>

            <Dialog open={isDialogOpen} onOpenChange={handleDialogClose}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Student Signature</DialogTitle>
                        <DialogDescription>
                            Draw or upload an image of the student&apos;s signature.
                        </DialogDescription>
                    </DialogHeader>

                    <Tabs value={activeTab} onValueChange={setActiveTab}>
                        <TabsList className="w-full">
                            <TabsTab value="draw" className="flex-1 gap-1.5">
                                <PencilLine className="h-3.5 w-3.5" />
                                Draw
                            </TabsTab>
                            <TabsTab value="upload" className="flex-1 gap-1.5">
                                <ImageUp className="h-3.5 w-3.5" />
                                Upload
                            </TabsTab>
                        </TabsList>

                        <TabsPanel value="draw" className="mt-3">
                            <div
                                ref={wrapperRef}
                                className="min-w-[300px] overflow-hidden rounded-lg border bg-white dark:bg-zinc-900"
                            >
                                <canvas
                                    ref={canvasRef}
                                    className="block touch-none"
                                    style={{ height: MODAL_CANVAS_HEIGHT }}
                                />
                            </div>
                        </TabsPanel>

                        <TabsPanel value="upload" className="mt-3">
                            {uploadPreview ? (
                                <div
                                    onDragOver={handleDragOver}
                                    onDragLeave={handleDragLeave}
                                    onDrop={handleDrop}
                                    className="relative min-w-[300px] overflow-hidden rounded-lg border bg-white dark:bg-zinc-900"
                                >
                                    <div className="flex h-[220px] items-center justify-center p-4">
                                        <img
                                            src={uploadPreview}
                                            alt="Upload preview"
                                            className="h-full max-w-full object-contain"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleRemoveFile}
                                        className="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70"
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            ) : (
                                <div
                                    onDragOver={handleDragOver}
                                    onDragLeave={handleDragLeave}
                                    onDrop={handleDrop}
                                    onClick={() => fileInputRef.current?.click()}
                                    className={cn(
                                        "flex h-[220px] w-full min-w-[300px] cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed bg-muted/20 text-muted-foreground transition-colors",
                                        isDragOver
                                            ? "border-primary/50 bg-primary/5 text-primary"
                                            : "border-muted-foreground/25 hover:border-muted-foreground/40 hover:bg-muted/30",
                                    )}
                                >
                                    <ImageUp className="h-8 w-8" />
                                    <span className="text-sm font-medium">
                                        {isDragOver ? "Drop image here" : "Click or drag an image to upload"}
                                    </span>
                                    <span className="text-xs">PNG, JPG, or SVG up to 5MB</span>
                                </div>
                            )}
                            <input
                                ref={fileInputRef}
                                type="file"
                                className="hidden"
                                accept="image/*"
                                onChange={handleFileChange}
                            />
                        </TabsPanel>
                    </Tabs>

                    <DialogFooter className="gap-2 sm:justify-between">
                        {activeTab === "draw" && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleClear}
                                disabled={saving}
                            >
                                <Eraser className="mr-1.5 h-3.5 w-3.5" />
                                Clear
                            </Button>
                        )}
                        {activeTab === "upload" && <div />}

                        <Button
                            type="button"
                            size="sm"
                            onClick={handleSave}
                            disabled={saving}
                        >
                            <Save className="mr-1.5 h-3.5 w-3.5" />
                            {saving ? "Saving..." : "Save"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
