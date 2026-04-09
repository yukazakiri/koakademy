import AdminLayout from "@/components/administrators/admin-layout";
import { RichTextEditor } from "@/components/editors/rich-text-editor";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { uploadImageToSanity } from "@/lib/sanity-upload";
import { urlForImage } from "@/lib/sanity-utils";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    CheckCircle2,
    ChevronDown,
    Eye,
    HelpCircle,
    Image as ImageIcon,
    ImagePlus,
    Link as LinkIcon,
    Loader2,
    PanelRight,
    Save,
    Star,
    Tags,
    Upload,
    Users,
    X,
} from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

interface FeaturedImage {
    assetId?: string | null;
    url?: string | null;
    alt?: string | null;
    caption?: string | null;
    credit?: string | null;
    filename?: string | null;
}

interface SanityContent {
    id: number;
    sanity_id: string | null;
    title: string;
    slug: string;
    excerpt: string | null;
    content: string | any;
    content_focus: string | null;
    status: string;
    post_kind: string;
    priority: string | null;
    published_at: string | null;
    featured: boolean;
    featured_image: FeaturedImage | null;
    tags: string[] | null;
    audiences: string[] | null;
    channels: string[] | null;
    cta: { text: string; url?: string } | null;
    activation_window: { start: string; end: string } | null;
}

interface EditSanityContentProps {
    auth: { user: User };
    content: SanityContent;
    sanityConfig: {
        projectId: string;
        dataset: string;
    };
}

function HelpTip({ children }: { children: React.ReactNode }) {
    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <HelpCircle className="text-muted-foreground/60 hover:text-muted-foreground h-3.5 w-3.5 cursor-help" />
                </TooltipTrigger>
                <TooltipContent side="top" className="max-w-[200px] text-xs">
                    {children}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

export default function AdministratorSanityContentEdit({ auth, content, sanityConfig }: EditSanityContentProps) {
    const convertPortableTextToHTML = (portableText: any): string => {
        if (!portableText) return "";

        try {
            const parsed = typeof portableText === "string" ? JSON.parse(portableText) : portableText;

            if (Array.isArray(parsed)) {
                return parsed
                    .map((block: any) => {
                        if (block._type === "block" && block.children) {
                            const text = block.children.map((child: any) => child.text || "").join("");
                            const style = block.style || "normal";

                            if (style === "h1") return `<h1>${text}</h1>`;
                            if (style === "h2") return `<h2>${text}</h2>`;
                            if (style === "h3") return `<h3>${text}</h3>`;
                            if (style === "blockquote") return `<blockquote>${text}</blockquote>`;
                            return `<p>${text}</p>`;
                        }

                        if (block._type === "image" && block.asset) {
                            const imageUrl = urlForImage(block, sanityConfig.projectId, sanityConfig.dataset);
                            if (imageUrl) {
                                return `<img src="${imageUrl}" alt="${block.alt || ""}" class="rounded-lg max-w-full h-auto my-4 shadow-md" />`;
                            }
                        }

                        return "";
                    })
                    .filter(Boolean)
                    .join("");
            }

            return String(portableText);
        } catch {
            return String(portableText);
        }
    };

    const { data, setData, put, processing, errors } = useForm({
        title: content.title || "",
        slug: content.slug || "",
        excerpt: content.excerpt || "",
        post_kind: content.post_kind || "news",
        content: convertPortableTextToHTML(content.content),
        content_focus: content.content_focus || "",
        status: content.status || "draft",
        priority: content.priority || "normal",
        featured: content.featured || false,
        featured_image: content.featured_image || (null as FeaturedImage | null),
        published_at: content.published_at ? new Date(content.published_at).toISOString().slice(0, 16) : "",
        tags: content.tags ? content.tags.join(", ") : "",
        audiences: content.audiences ? content.audiences.join(", ") : "",
        channels: content.channels ? content.channels.join(", ") : "",
        cta_text: content.cta?.text || "",
        cta_url: content.cta?.url || "",
        activation_start: content.activation_window?.start ? new Date(content.activation_window.start).toISOString().slice(0, 16) : "",
        activation_end: content.activation_window?.end ? new Date(content.activation_window.end).toISOString().slice(0, 16) : "",
    });

    const [imageUploading, setImageUploading] = useState(false);
    const [dragActive, setDragActive] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const slugManuallyEdited = useRef(true); // For edit, assume slug was intentionally set

    useEffect(() => {
        if (data.title && !content.slug && !slugManuallyEdited.current) {
            const slug = data.title
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-|-$/g, "");
            setData("slug", slug);
        }
    }, [data.title]);

    const handleSlugChange = (value: string) => {
        slugManuallyEdited.current = true;
        setData("slug", value);
    };

    const handleImageUpload = useCallback(
        async (file: File) => {
            if (!file) return;

            // Validate file type
            if (!file.type.startsWith("image/")) {
                toast.error("Please select a valid image file");
                return;
            }

            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                toast.error("Image size must be less than 10MB");
                return;
            }

            setImageUploading(true);
            const toastId = toast.loading("Uploading image to Sanity...");

            try {
                const result = await uploadImageToSanity(file);

                if (result && result.url) {
                    setData("featured_image", {
                        assetId: result.assetId,
                        url: result.url,
                        alt: result.alt || "",
                        caption: data.featured_image?.caption || "",
                        credit: data.featured_image?.credit || "",
                        filename: result.filename,
                    });

                    toast.success("Image uploaded successfully!", { id: toastId });
                } else {
                    toast.error("Failed to upload image", { id: toastId });
                }
            } catch (error) {
                console.error("Upload error:", error);
                toast.error("Failed to upload image", { id: toastId });
            } finally {
                setImageUploading(false);
            }
        },
        [data.featured_image, setData],
    );

    const handleDrag = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === "dragenter" || e.type === "dragover") {
            setDragActive(true);
        } else if (e.type === "dragleave") {
            setDragActive(false);
        }
    }, []);

    const handleDrop = useCallback(
        (e: React.DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setDragActive(false);

            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                handleImageUpload(e.dataTransfer.files[0]);
            }
        },
        [handleImageUpload],
    );

    const handleFileSelect = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            if (e.target.files && e.target.files[0]) {
                handleImageUpload(e.target.files[0]);
            }
        },
        [handleImageUpload],
    );

    const removeImage = useCallback(() => {
        setData("featured_image", null);
        if (fileInputRef.current) {
            fileInputRef.current.value = "";
        }
    }, [setData]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route("administrators.sanity-content.update", content.id), {
            onSuccess: () => toast.success("Post updated successfully!"),
            onError: () => toast.error("Failed to update post"),
        });
    };

    const syncToSanity = () => {
        router.post(
            route("administrators.sanity-content.sync-to-sanity", content.id),
            {},
            {
                preserveState: true,
                onSuccess: () => toast.success("Successfully synced to Sanity!"),
                onError: () => toast.error("Failed to sync to Sanity"),
            },
        );
    };

    const isAlert = data.post_kind === "alert" || data.post_kind === "announcement";

    const getStatusConfig = (status: string) => {
        const configs = {
            published: { color: "bg-emerald-500/15 text-emerald-600 border-emerald-500/20", label: "Published" },
            draft: { color: "bg-slate-500/15 text-slate-600 border-slate-500/20", label: "Draft" },
            scheduled: { color: "bg-blue-500/15 text-blue-600 border-blue-500/20", label: "Scheduled" },
            archived: { color: "bg-amber-500/15 text-amber-600 border-amber-500/20", label: "Archived" },
        };
        return configs[status as keyof typeof configs] || configs.draft;
    };

    const statusConfig = getStatusConfig(data.status);

    return (
        <AdminLayout user={auth.user} title="Edit Post">
            <Head title={`Edit - ${content.title}`} />

            <form onSubmit={submit} className="bg-background relative flex h-[calc(100vh-65px)] flex-col overflow-hidden">
                {/* Immersive Header */}
                <header className="bg-background/80 sticky top-0 z-20 flex items-center justify-between border-b px-6 py-3 backdrop-blur-md">
                    <div className="flex items-center gap-4">
                        <Button asChild variant="ghost" size="icon" className="text-muted-foreground hover:text-foreground">
                            <Link href={route("administrators.sanity-content.index")}>
                                <ArrowLeft className="h-5 w-5" />
                            </Link>
                        </Button>
                        <div className="flex flex-col">
                            <div className="flex items-center gap-2">
                                <h1 className="max-w-[300px] truncate text-lg font-semibold tracking-tight">{data.title || "Untitled Post"}</h1>
                                {content.sanity_id && (
                                    <Badge variant="secondary" className="h-5 px-1.5 text-[10px] font-normal">
                                        Synced
                                    </Badge>
                                )}
                            </div>
                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                <span className={cn("flex items-center gap-1.5", data.status === "published" ? "text-emerald-600" : "")}>
                                    <div
                                        className={cn(
                                            "h-1.5 w-1.5 rounded-full",
                                            data.status === "published"
                                                ? "bg-emerald-500"
                                                : data.status === "scheduled"
                                                  ? "bg-blue-500"
                                                  : "bg-slate-400",
                                        )}
                                    />
                                    {data.status.charAt(0).toUpperCase() + data.status.slice(1)}
                                </span>
                                <span>•</span>
                                <span>{data.post_kind.charAt(0).toUpperCase() + data.post_kind.slice(1)}</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* Quick Actions Toolbar */}
                        <div className="bg-muted/50 mr-2 flex items-center rounded-lg border p-1">
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setData("featured", !data.featured)}
                                            className={cn(
                                                "h-8 w-8 p-0 transition-all",
                                                data.featured
                                                    ? "text-amber-500 hover:bg-amber-100 hover:text-amber-600 dark:hover:bg-amber-950"
                                                    : "text-muted-foreground",
                                            )}
                                        >
                                            <Star className={cn("h-4 w-4", data.featured && "fill-current")} />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Toggle Featured</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>

                            <Separator orientation="vertical" className="mx-1 h-4" />

                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button type="button" variant="ghost" size="sm" className="h-8 gap-2 text-xs font-medium">
                                        {data.priority.charAt(0).toUpperCase() + data.priority.slice(1)} Priority
                                        <ChevronDown className="h-3 w-3 opacity-50" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel>Set Priority</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {["low", "normal", "high", "critical"].map((p) => (
                                        <DropdownMenuItem key={p} onClick={() => setData("priority", p)} className="gap-2">
                                            {data.priority === p && <CheckCircle2 className="text-primary h-3.5 w-3.5" />}
                                            <span className={cn("ml-auto capitalize", data.priority !== p && "pl-5")}>{p}</span>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <Button asChild variant="ghost" size="sm" className="hidden sm:flex">
                            <Link href={route("administrators.sanity-content.show", content.id)}>
                                <Eye className="mr-2 h-4 w-4" />
                                Preview
                            </Link>
                        </Button>

                        {content.sanity_id && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button onClick={syncToSanity} variant="ghost" size="icon" className="hidden sm:flex">
                                            <Upload className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Push to Sanity</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => setSidebarOpen(!sidebarOpen)}
                            className={cn("hidden lg:flex", !sidebarOpen && "text-muted-foreground")}
                        >
                            <PanelRight className="h-5 w-5" />
                        </Button>

                        <Separator orientation="vertical" className="mx-2 h-6" />

                        <Button type="submit" disabled={processing} className="min-w-[130px] gap-2">
                            {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                            Save Changes
                        </Button>
                    </div>
                </header>

                <div className="flex flex-1 overflow-hidden">
                    {/* Main Content Area - Scrollable */}
                    <div className="scrollbar-thin flex-1 overflow-y-auto">
                        <div className="mx-auto max-w-4xl space-y-8 px-6 py-8 pb-32">
                            {/* Document Header Section */}
                            <div className="space-y-6">
                                {/* Featured Image - Integrated Banner Style */}
                                {!isAlert && (
                                    <div className="group border-muted-foreground/20 hover:border-muted-foreground/40 bg-muted/5 relative flex min-h-[200px] flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed transition-colors">
                                        {data.featured_image?.url ? (
                                            <>
                                                <img src={data.featured_image.url} alt="Cover" className="h-[300px] w-full object-cover" />
                                                <div className="absolute top-4 right-4 flex gap-2 opacity-0 transition-opacity group-hover:opacity-100">
                                                    <Button type="button" size="sm" variant="secondary" onClick={() => fileInputRef.current?.click()}>
                                                        <ImagePlus className="mr-2 h-4 w-4" /> Change Cover
                                                    </Button>
                                                    <Button type="button" size="sm" variant="destructive" onClick={removeImage}>
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                                {/* Image Metadata Overlay */}
                                                <div className="absolute inset-x-0 bottom-0 flex gap-4 bg-black/60 p-3 text-sm text-white opacity-0 backdrop-blur-sm transition-opacity group-hover:opacity-100">
                                                    <div className="flex-1">
                                                        <input
                                                            value={data.featured_image.alt || ""}
                                                            onChange={(e) =>
                                                                setData("featured_image", { ...data.featured_image!, alt: e.target.value })
                                                            }
                                                            placeholder="Alt text (required)"
                                                            className="w-full border-b border-white/30 bg-transparent px-1 py-0.5 placeholder:text-white/50 focus:border-white focus:outline-none"
                                                        />
                                                    </div>
                                                    <div className="flex-1">
                                                        <input
                                                            value={data.featured_image.caption || ""}
                                                            onChange={(e) =>
                                                                setData("featured_image", { ...data.featured_image!, caption: e.target.value })
                                                            }
                                                            placeholder="Caption (optional)"
                                                            className="w-full border-b border-white/30 bg-transparent px-1 py-0.5 placeholder:text-white/50 focus:border-white focus:outline-none"
                                                        />
                                                    </div>
                                                </div>
                                            </>
                                        ) : (
                                            <div
                                                className="absolute inset-0 flex cursor-pointer flex-col items-center justify-center"
                                                onClick={() => fileInputRef.current?.click()}
                                                onDragEnter={handleDrag}
                                                onDragLeave={handleDrag}
                                                onDragOver={handleDrag}
                                                onDrop={handleDrop}
                                            >
                                                <div className="bg-muted mb-4 flex h-16 w-16 items-center justify-center rounded-full transition-transform group-hover:scale-110">
                                                    <ImageIcon className="text-muted-foreground h-8 w-8" />
                                                </div>
                                                <h3 className="text-lg font-medium">Add a cover image</h3>
                                                <p className="text-muted-foreground mt-1 text-sm">Drag and drop or click to upload</p>
                                            </div>
                                        )}
                                        <input ref={fileInputRef} type="file" accept="image/*" onChange={handleFileSelect} className="hidden" />
                                    </div>
                                )}

                                {/* Title Input - Medium Style */}
                                <div className="space-y-2">
                                    <Textarea
                                        id="title"
                                        value={data.title}
                                        onChange={(e) => setData("title", e.target.value)}
                                        placeholder="Post Title"
                                        className="placeholder:text-muted-foreground/40 min-h-[60px] resize-none border-none bg-transparent p-0 text-4xl leading-tight font-bold shadow-none focus-visible:ring-0 md:text-5xl"
                                        rows={1}
                                        onInput={(e) => {
                                            const target = e.target as HTMLTextAreaElement;
                                            target.style.height = "auto";
                                            target.style.height = `${target.scrollHeight}px`;
                                        }}
                                    />
                                    {errors.title && <p className="text-destructive text-sm font-medium">{errors.title}</p>}

                                    {/* Slug & Metadata Row */}
                                    <div className="text-muted-foreground flex items-center gap-4 pl-1 font-mono text-sm">
                                        <div className="group flex items-center gap-2">
                                            <LinkIcon className="h-3 w-3" />
                                            <span>/posts/</span>
                                            <input
                                                value={data.slug}
                                                onChange={(e) => handleSlugChange(e.target.value)}
                                                className="hover:border-border focus:border-primary min-w-[200px] border-b border-transparent bg-transparent transition-colors focus:outline-none"
                                            />
                                        </div>
                                        {data.slug && (
                                            <span className="bg-muted text-muted-foreground rounded-sm px-1.5 py-0.5 text-xs">
                                                {data.slug.length} chars
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* Excerpt */}
                                <div className="relative">
                                    <Textarea
                                        id="excerpt"
                                        value={data.excerpt}
                                        onChange={(e) => setData("excerpt", e.target.value)}
                                        placeholder="Add a short excerpt or summary..."
                                        className="text-muted-foreground min-h-[80px] resize-none border-none bg-transparent p-0 text-lg italic shadow-none focus-visible:ring-0"
                                        maxLength={320}
                                    />
                                    <span className="text-muted-foreground/50 absolute right-0 bottom-0 text-xs tabular-nums">
                                        {data.excerpt.length}/320
                                    </span>
                                </div>
                            </div>

                            <Separator className="my-8" />

                            {/* Main Editor */}
                            <div className="min-h-[500px]">
                                <RichTextEditor
                                    content={data.content}
                                    onChange={(value) => setData("content", value)}
                                    placeholder="Tell your story..."
                                    className="border-none px-0 shadow-none"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Right Sidebar - Collapsible */}
                    <div
                        className={cn(
                            "bg-muted/10 scrollbar-thin w-[350px] overflow-y-auto border-l transition-all duration-300 ease-in-out",
                            !sidebarOpen && "w-0 border-none opacity-0",
                        )}
                    >
                        <div className="space-y-8 p-6">
                            {/* Publish Widget */}
                            <div className="space-y-4">
                                <h3 className="text-muted-foreground flex items-center gap-2 text-sm font-semibold tracking-wide uppercase">
                                    <Calendar className="h-4 w-4" /> Publishing
                                </h3>
                                <div className="bg-background grid gap-4 rounded-lg border p-4 shadow-sm">
                                    <div className="space-y-2">
                                        <Label className="text-xs">Status</Label>
                                        <Select
                                            value={data.status}
                                            onValueChange={(value) => {
                                                setData((previousData) => ({
                                                    ...previousData,
                                                    status: value,
                                                    published_at:
                                                        value === "published" && !previousData.published_at
                                                            ? new Date().toISOString().slice(0, 16)
                                                            : previousData.published_at,
                                                }));
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="draft">Draft</SelectItem>
                                                <SelectItem value="published">Published</SelectItem>
                                                <SelectItem value="scheduled">Scheduled</SelectItem>
                                                <SelectItem value="archived">Archived</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-xs">Post Kind</Label>
                                        <Select value={data.post_kind} onValueChange={(value) => setData("post_kind", value)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="news">News</SelectItem>
                                                <SelectItem value="story">Story</SelectItem>
                                                <SelectItem value="announcement">Announcement</SelectItem>
                                                <SelectItem value="alert">Alert</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-xs">Publish Date</Label>
                                        <Input
                                            type="datetime-local"
                                            value={data.published_at}
                                            onChange={(e) => setData("published_at", e.target.value)}
                                            className="text-sm"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Organization */}
                            <div className="space-y-4">
                                <h3 className="text-muted-foreground flex items-center gap-2 text-sm font-semibold tracking-wide uppercase">
                                    <Tags className="h-4 w-4" /> Organization
                                </h3>
                                <div className="bg-background grid gap-4 rounded-lg border p-4 shadow-sm">
                                    <div className="space-y-2">
                                        <Label className="text-xs">Content Focus</Label>
                                        <Select value={data.content_focus} onValueChange={(value) => setData("content_focus", value)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select focus..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="news">News</SelectItem>
                                                <SelectItem value="research">Research</SelectItem>
                                                <SelectItem value="student-life">Student Life</SelectItem>
                                                <SelectItem value="athletics">Athletics</SelectItem>
                                                <SelectItem value="press">Press</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-xs">Tags</Label>
                                        <Input
                                            value={data.tags}
                                            onChange={(e) => setData("tags", e.target.value)}
                                            placeholder="comma, separated, tags"
                                            className="text-sm"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Distribution */}
                            <div className="space-y-4">
                                <h3 className="text-muted-foreground flex items-center gap-2 text-sm font-semibold tracking-wide uppercase">
                                    <Users className="h-4 w-4" /> Distribution
                                </h3>
                                <div className="bg-background grid gap-4 rounded-lg border p-4 shadow-sm">
                                    <div className="space-y-2">
                                        <Label className="text-xs">Audiences</Label>
                                        <Input
                                            value={data.audiences}
                                            onChange={(e) => setData("audiences", e.target.value)}
                                            placeholder="students, faculty..."
                                            className="text-sm"
                                        />
                                    </div>

                                    {isAlert && (
                                        <div className="space-y-2">
                                            <Label className="text-xs">Channels</Label>
                                            <Input
                                                value={data.channels}
                                                onChange={(e) => setData("channels", e.target.value)}
                                                placeholder="email, sms..."
                                                className="text-sm"
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Alert Specifics */}
                            {isAlert && (
                                <div className="space-y-4">
                                    <h3 className="flex items-center gap-2 text-sm font-semibold tracking-wide text-orange-600 uppercase">
                                        <AlertCircle className="h-4 w-4" /> Alert Details
                                    </h3>
                                    <div className="grid gap-4 rounded-lg border border-orange-200 bg-orange-50/50 p-4 shadow-sm dark:bg-orange-950/20">
                                        <div className="space-y-2">
                                            <Label className="text-xs">CTA Label</Label>
                                            <Input
                                                value={data.cta_text}
                                                onChange={(e) => setData("cta_text", e.target.value)}
                                                placeholder="Learn More"
                                                className="text-sm"
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label className="text-xs">CTA URL</Label>
                                            <Input
                                                value={data.cta_url}
                                                onChange={(e) => setData("cta_url", e.target.value)}
                                                placeholder="https://..."
                                                className="text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}
