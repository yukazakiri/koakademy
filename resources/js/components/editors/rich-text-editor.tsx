import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { uploadImageToSanity } from "@/lib/sanity-upload";
import { cn } from "@/lib/utils";
import BubbleMenuExtension from "@tiptap/extension-bubble-menu";
import CharacterCount from "@tiptap/extension-character-count";
import Image from "@tiptap/extension-image";
import Link from "@tiptap/extension-link";
import Placeholder from "@tiptap/extension-placeholder";
import TextAlign from "@tiptap/extension-text-align";
import { EditorContent, useEditor } from "@tiptap/react";
import { BubbleMenu } from "@tiptap/react/menus";
import StarterKit from "@tiptap/starter-kit";
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    Code,
    Heading1,
    Heading2,
    Heading3,
    ImagePlus,
    Info,
    Italic,
    Link2,
    Link2Off,
    List,
    ListOrdered,
    Loader2,
    Quote,
    Redo,
    Trash2,
    Undo,
} from "lucide-react";
import { useCallback, useRef, useState } from "react";
import { toast } from "sonner";

interface RichTextEditorProps {
    content: string;
    onChange: (content: string) => void;
    placeholder?: string;
    className?: string;
}

export function RichTextEditor({ content, onChange, placeholder = "Start writing your amazing content...", className }: RichTextEditorProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [showImageDialog, setShowImageDialog] = useState(false);
    const [imageDetails, setImageDetails] = useState({ alt: "", title: "" });

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: {
                    levels: [1, 2, 3],
                },
            }),
            Placeholder.configure({
                placeholder,
            }),
            Link.configure({
                openOnClick: false,
                HTMLAttributes: {
                    class: "text-blue-600 underline hover:text-blue-700 transition-colors",
                },
            }),
            TextAlign.configure({
                types: ["heading", "paragraph"],
            }),
            Image.configure({
                HTMLAttributes: {
                    class: "rounded-lg max-w-full h-auto my-4 shadow-md transition-all",
                },
            }),
            CharacterCount,
            BubbleMenuExtension.configure({
                pluginKey: "bubbleMenu",
            }),
        ],
        content,
        onUpdate: ({ editor }) => {
            onChange(editor.getHTML());
        },
        editorProps: {
            attributes: {
                class: "prose prose-sm sm:prose-base dark:prose-invert max-w-none min-h-[400px] px-6 py-4 focus:outline-none",
            },
            handleDrop: (view, event, slice, moved) => {
                if (!moved && event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length > 0) {
                    const file = event.dataTransfer.files[0];
                    if (file.type.startsWith("image/")) {
                        event.preventDefault(); // Prevent default behavior

                        // Validate file size (10MB)
                        if (file.size > 10 * 1024 * 1024) {
                            toast.error("Image must be less than 10MB");
                            return true;
                        }

                        handleDropUpload(file, view, event);
                        return true; // Handled
                    }
                }
                return false; // Not handled
            },
        },
    });

    const openImageDetails = () => {
        if (!editor) return;
        const attrs = editor.getAttributes("image");
        setImageDetails({
            alt: attrs.alt || "",
            title: attrs.title || "",
        });
        setShowImageDialog(true);
    };

    const updateImageDetails = () => {
        if (!editor) return;
        editor.chain().focus().updateAttributes("image", imageDetails).run();
        setShowImageDialog(false);
        toast.success("Image details updated");
    };

    const deleteImage = () => {
        if (!editor) return;
        editor.chain().focus().deleteSelection().run();
        toast.success("Image deleted");
    };

    const handleDropUpload = async (file: File, view: any, event: any) => {
        const toastId = toast.loading("Uploading image to Sanity...");
        setIsUploading(true);

        try {
            const result = await uploadImageToSanity(file);

            if (result && result.url) {
                const { schema } = view.state;
                const coordinates = view.posAtCoords({ left: event.clientX, top: event.clientY });

                if (coordinates) {
                    const imageNode = schema.nodes.image.create({
                        src: result.url,
                        alt: result.alt,
                        title: result.filename,
                    });
                    const transaction = view.state.tr.insert(coordinates.pos, imageNode);
                    view.dispatch(transaction);
                } else {
                    // Fallback to selection
                    const imageNode = schema.nodes.image.create({
                        src: result.url,
                        alt: result.alt,
                        title: result.filename,
                    });
                    const transaction = view.state.tr.replaceSelectionWith(imageNode);
                    view.dispatch(transaction);
                }

                toast.success("Image uploaded successfully!", { id: toastId });
            } else {
                toast.error("Failed to upload image", { id: toastId });
            }
        } catch (error) {
            console.error("Upload error:", error);
            toast.error("Failed to upload image", { id: toastId });
        } finally {
            setIsUploading(false);
            // Do not dismiss generic toasts, only the specific one was handled above
        }
    };

    const uploadFile = async (file: File) => {
        if (!editor) return;

        const toastId = toast.loading("Uploading image to Sanity...");
        setIsUploading(true);

        try {
            const result = await uploadImageToSanity(file);

            if (result && result.url) {
                editor
                    .chain()
                    .focus()
                    .setImage({
                        src: result.url,
                        alt: result.alt,
                        title: result.filename,
                    })
                    .run();
                toast.success("Image uploaded successfully!", { id: toastId });
            } else {
                toast.error("Failed to upload image", { id: toastId });
            }
        } catch (error) {
            console.error("Upload error:", error);
            toast.error("Failed to upload image", { id: toastId });
        } finally {
            setIsUploading(false);
            // Do not dismiss
            // Reset file input
            if (fileInputRef.current) {
                fileInputRef.current.value = "";
            }
        }
    };

    const setLink = useCallback(() => {
        if (!editor) return;

        const previousUrl = editor.getAttributes("link").href;
        const url = window.prompt("🔗 Enter URL:", previousUrl);

        if (url === null) return;

        if (url === "") {
            editor.chain().focus().extendMarkRange("link").unsetLink().run();
            return;
        }

        editor.chain().focus().extendMarkRange("link").setLink({ href: url }).run();
    }, [editor]);

    const addImage = useCallback(async () => {
        if (!editor) return;
        fileInputRef.current?.click();
    }, [editor]);

    const handleImageUpload = useCallback(
        async (event: React.ChangeEvent<HTMLInputElement>) => {
            const file = event.target.files?.[0];
            if (!file || !editor) return;

            // Validate file type
            if (!file.type.startsWith("image/")) {
                toast.error("Please select an image file");
                return;
            }

            // Validate file size (10MB)
            if (file.size > 10 * 1024 * 1024) {
                toast.error("Image must be less than 10MB");
                return;
            }

            uploadFile(file);
        },
        [editor], // uploadFile depends on editor, so this is fine
    );

    if (!editor) return null;

    return (
        <div className={cn("overflow-hidden rounded-xl border-2 shadow-sm", className)}>
            <Dialog open={showImageDialog} onOpenChange={setShowImageDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Image Details</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="img-alt">Alt Text</Label>
                            <Input
                                id="img-alt"
                                value={imageDetails.alt}
                                onChange={(e) => setImageDetails((prev) => ({ ...prev, alt: e.target.value }))}
                                placeholder="Describe the image for screen readers"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="img-title">Title</Label>
                            <Input
                                id="img-title"
                                value={imageDetails.title}
                                onChange={(e) => setImageDetails((prev) => ({ ...prev, title: e.target.value }))}
                                placeholder="Tooltip text when hovering"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowImageDialog(false)}>
                            Cancel
                        </Button>
                        <Button onClick={updateImageDetails}>Save Changes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {editor && (
                <BubbleMenu
                    editor={editor}
                    shouldShow={({ editor }) => editor.isActive("image")}
                    className="bg-background flex items-center gap-1 rounded-lg border p-1 shadow-lg"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().setTextAlign("left").run()}
                        className={cn("h-8 w-8 p-0", editor.isActive({ textAlign: "left" }) && "bg-muted")}
                        title="Align Left"
                    >
                        <AlignLeft className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().setTextAlign("center").run()}
                        className={cn("h-8 w-8 p-0", editor.isActive({ textAlign: "center" }) && "bg-muted")}
                        title="Align Center"
                    >
                        <AlignCenter className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().setTextAlign("right").run()}
                        className={cn("h-8 w-8 p-0", editor.isActive({ textAlign: "right" }) && "bg-muted")}
                        title="Align Right"
                    >
                        <AlignRight className="h-4 w-4" />
                    </Button>
                    <Separator orientation="vertical" className="mx-1 h-6" />
                    <Button type="button" variant="ghost" size="sm" onClick={openImageDetails} className="h-8 w-8 p-0" title="Image Details">
                        <Info className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={deleteImage}
                        className="text-destructive hover:text-destructive hover:bg-destructive/10 h-8 w-8 p-0"
                        title="Remove Image"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </BubbleMenu>
            )}

            {/* Hidden file input */}
            <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={handleImageUpload} />

            {/* Toolbar */}
            <div className="from-muted/70 to-muted/50 flex flex-wrap items-center gap-1 border-b-2 bg-gradient-to-r p-3 backdrop-blur-sm">
                {/* Text Formatting */}
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant={editor.isActive("bold") ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().toggleBold().run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Bold (Ctrl+B)"
                    >
                        <Bold className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant={editor.isActive("italic") ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().toggleItalic().run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Italic (Ctrl+I)"
                    >
                        <Italic className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant={editor.isActive("code") ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().toggleCode().run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Code"
                    >
                        <Code className="h-4 w-4" />
                    </Button>
                </div>

                <Separator orientation="vertical" className="mx-1 h-9" />

                {/* Headings */}
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant={editor.isActive("heading", { level: 1 }) ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Heading 1"
                    >
                        <Heading1 className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant={editor.isActive("heading", { level: 2 }) ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Heading 2"
                    >
                        <Heading2 className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant={editor.isActive("heading", { level: 3 }) ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Heading 3"
                    >
                        <Heading3 className="h-4 w-4" />
                    </Button>
                </div>

                <Separator orientation="vertical" className="mx-1 h-9" />

                {/* Lists */}
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant={editor.isActive("bulletList") ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().toggleBulletList().run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Bullet List"
                    >
                        <List className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant={editor.isActive("orderedList") ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().toggleOrderedList().run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Numbered List"
                    >
                        <ListOrdered className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant={editor.isActive("blockquote") ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().toggleBlockquote().run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Quote"
                    >
                        <Quote className="h-4 w-4" />
                    </Button>
                </div>

                <Separator orientation="vertical" className="mx-1 h-9" />

                {/* Alignment */}
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant={editor.isActive({ textAlign: "left" }) ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().setTextAlign("left").run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Align Left"
                    >
                        <AlignLeft className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant={editor.isActive({ textAlign: "center" }) ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().setTextAlign("center").run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Align Center"
                    >
                        <AlignCenter className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant={editor.isActive({ textAlign: "right" }) ? "default" : "ghost"}
                        size="sm"
                        onClick={() => editor.chain().focus().setTextAlign("right").run()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Align Right"
                    >
                        <AlignRight className="h-4 w-4" />
                    </Button>
                </div>

                <Separator orientation="vertical" className="mx-1 h-9" />

                {/* Media */}
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant={editor.isActive("link") ? "default" : "ghost"}
                        size="sm"
                        onClick={setLink}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Add Link"
                    >
                        {editor.isActive("link") ? <Link2Off className="h-4 w-4" /> : <Link2 className="h-4 w-4" />}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={addImage}
                        disabled={isUploading}
                        className="h-9 w-9 bg-gradient-to-r from-green-500/10 to-blue-500/10 p-0 transition-all hover:scale-110 hover:from-green-500/20 hover:to-blue-500/20"
                        title="Upload Image"
                    >
                        {isUploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <ImagePlus className="h-4 w-4" />}
                    </Button>
                </div>

                <Separator orientation="vertical" className="mx-1 h-9" />

                {/* History */}
                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().undo().run()}
                        disabled={!editor.can().undo()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Undo (Ctrl+Z)"
                    >
                        <Undo className="h-4 w-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => editor.chain().focus().redo().run()}
                        disabled={!editor.can().redo()}
                        className="h-9 w-9 p-0 transition-all hover:scale-110"
                        title="Redo (Ctrl+Y)"
                    >
                        <Redo className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            {/* Editor */}
            <EditorContent editor={editor} className="from-background to-muted/10 bg-gradient-to-br" />

            {/* Character count footer */}
            <div className="bg-muted/30 text-muted-foreground flex items-center justify-between border-t px-4 py-2 text-xs">
                <span>{editor.storage.characterCount?.characters() || 0} characters</span>
                <span className="text-muted-foreground/60 text-xs">Tip: Drag & drop images or click the 📷 icon to upload</span>
            </div>
        </div>
    );
}
