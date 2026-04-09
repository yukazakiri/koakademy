import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, Calendar, Edit, Upload } from "lucide-react";
import { toast } from "sonner";
import { route } from "ziggy-js";

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
    tags: string[] | null;
    audiences: string[] | null;
    channels: string[] | null;
    cta: { text: string; url?: string } | null;
    activation_window: { start: string; end: string } | null;
}

import { urlForImage } from "@/lib/sanity-utils";

interface ShowSanityContentProps {
    auth: { user: User };
    content: SanityContent;
    sanityConfig: {
        projectId: string;
        dataset: string;
    };
}

export default function AdministratorSanityContentShow({ auth, content, sanityConfig }: ShowSanityContentProps) {
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

    const getStatusColor = (status: string) => {
        const colors = {
            published: "bg-green-500/10 text-green-700 dark:text-green-400",
            draft: "bg-gray-500/10 text-gray-700 dark:text-gray-400",
            scheduled: "bg-blue-500/10 text-blue-700 dark:text-blue-400",
            archived: "bg-orange-500/10 text-orange-700 dark:text-orange-400",
        };
        return colors[status as keyof typeof colors] || colors.draft;
    };

    const getPostKindColor = (kind: string) => {
        const colors = {
            news: "bg-blue-500/10 text-blue-700 dark:text-blue-400",
            story: "bg-purple-500/10 text-purple-700 dark:text-purple-400",
            announcement: "bg-yellow-500/10 text-yellow-700 dark:text-yellow-400",
            alert: "bg-red-500/10 text-red-700 dark:text-red-400",
        };
        return colors[kind as keyof typeof colors] || colors.news;
    };

    const renderPortableText = (portableText: any) => {
        if (!portableText) return null;

        try {
            const parsed = typeof portableText === "string" ? JSON.parse(portableText) : portableText;

            if (!Array.isArray(parsed)) {
                return <p>{String(portableText)}</p>;
            }

            return parsed.map((block: any, index: number) => {
                if (block._type === "block") {
                    const text = block.children?.map((child: any) => child.text || "").join("") || "";

                    switch (block.style) {
                        case "h1":
                            return (
                                <h1 key={block._key || index} className="mt-6 mb-4 text-2xl font-bold">
                                    {text}
                                </h1>
                            );
                        case "h2":
                            return (
                                <h2 key={block._key || index} className="mt-5 mb-3 text-xl font-bold">
                                    {text}
                                </h2>
                            );
                        case "h3":
                            return (
                                <h3 key={block._key || index} className="mt-4 mb-2 text-lg font-bold">
                                    {text}
                                </h3>
                            );
                        case "blockquote":
                            return (
                                <blockquote key={block._key || index} className="border-primary my-4 border-l-4 pl-4 italic">
                                    {text}
                                </blockquote>
                            );
                        default:
                            return (
                                <p key={block._key || index} className="my-2">
                                    {text}
                                </p>
                            );
                    }
                }

                if (block._type === "image" && block.asset) {
                    const imageUrl = urlForImage(block, sanityConfig.projectId, sanityConfig.dataset);
                    if (imageUrl) {
                        return (
                            <figure key={block._key || index} className="my-6">
                                <img src={imageUrl} alt={block.alt || content.title} className="mx-auto max-h-[500px] w-auto rounded-lg shadow-md" />
                                {block.caption && <figcaption className="text-muted-foreground mt-2 text-center text-sm">{block.caption}</figcaption>}
                            </figure>
                        );
                    }
                }

                return null;
            });
        } catch {
            return <p>{String(portableText)}</p>;
        }
    };

    const isAlert = content.post_kind === "alert" || content.post_kind === "announcement";

    return (
        <AdminLayout user={auth.user} title="View Post">
            <Head title={content.title} />

            <div className="mx-auto flex max-w-4xl flex-col gap-6">
                {/* Header */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="icon">
                            <Link href={route("administrators.sanity-content.index")}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">{content.title}</h1>
                            <p className="text-muted-foreground text-sm">View post details</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline">
                            <Link href={route("administrators.sanity-content.edit", content.id)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                        {content.sanity_id && (
                            <Button onClick={syncToSanity} variant="outline">
                                <Upload className="mr-2 h-4 w-4" />
                                Push to Sanity
                            </Button>
                        )}
                    </div>
                </div>

                {/* Content */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className={getPostKindColor(content.post_kind)}>{content.post_kind}</Badge>
                            <Badge className={getStatusColor(content.status)}>{content.status}</Badge>
                            {content.featured && (
                                <Badge variant="secondary" className="gap-1">
                                    ⭐ Featured
                                </Badge>
                            )}
                            {content.priority && content.priority !== "normal" && <Badge variant="destructive">{content.priority}</Badge>}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Basic Info */}
                        <div className="space-y-4">
                            <div>
                                <h3 className="text-muted-foreground mb-1 text-sm font-medium">Slug</h3>
                                <p className="font-mono text-sm">{content.slug}</p>
                            </div>

                            {content.excerpt && (
                                <div>
                                    <h3 className="text-muted-foreground mb-1 text-sm font-medium">Excerpt</h3>
                                    <p className="text-sm">{content.excerpt}</p>
                                </div>
                            )}

                            {content.content && (
                                <div>
                                    <h3 className="text-muted-foreground mb-1 text-sm font-medium">Content</h3>
                                    <div className="prose prose-sm dark:prose-invert max-w-none">{renderPortableText(content.content)}</div>
                                </div>
                            )}

                            {content.content_focus && (
                                <div>
                                    <h3 className="text-muted-foreground mb-1 text-sm font-medium">Content Focus</h3>
                                    <p className="text-sm">{content.content_focus}</p>
                                </div>
                            )}
                        </div>

                        {/* Metadata */}
                        <div className="grid grid-cols-1 gap-4 border-t pt-4 sm:grid-cols-2">
                            {content.published_at && (
                                <div className="flex items-center gap-2">
                                    <Calendar className="text-muted-foreground h-4 w-4" />
                                    <div>
                                        <h3 className="text-muted-foreground text-xs font-medium">Published</h3>
                                        <p className="text-sm">{new Date(content.published_at).toLocaleString()}</p>
                                    </div>
                                </div>
                            )}
                            {content.sanity_id && (
                                <div>
                                    <h3 className="text-muted-foreground text-xs font-medium">Sanity ID</h3>
                                    <p className="font-mono text-sm">{content.sanity_id}</p>
                                </div>
                            )}
                        </div>

                        {/* Tags & Audiences */}
                        {(content.tags || content.audiences) && (
                            <div className="space-y-3 border-t pt-4">
                                {content.tags && content.tags.length > 0 && (
                                    <div>
                                        <h3 className="text-muted-foreground mb-2 text-sm font-medium">Tags</h3>
                                        <div className="flex flex-wrap gap-2">
                                            {content.tags.map((tag: string, idx: number) => (
                                                <Badge key={idx} variant="outline">
                                                    {tag}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {content.audiences && content.audiences.length > 0 && (
                                    <div>
                                        <h3 className="text-muted-foreground mb-2 text-sm font-medium">Audiences</h3>
                                        <div className="flex flex-wrap gap-2">
                                            {content.audiences.map((audience: string, idx: number) => (
                                                <Badge key={idx} variant="outline">
                                                    {audience}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Alert-specific fields */}
                        {isAlert && (
                            <div className="space-y-4 border-t pt-4">
                                <h3 className="text-sm font-semibold">Alert Information</h3>

                                {content.channels && content.channels.length > 0 && (
                                    <div>
                                        <h3 className="text-muted-foreground mb-2 text-sm font-medium">Channels</h3>
                                        <div className="flex flex-wrap gap-2">
                                            {content.channels.map((channel: string, idx: number) => (
                                                <Badge key={idx} variant="secondary">
                                                    {channel}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {content.cta && (
                                    <div>
                                        <h3 className="text-muted-foreground mb-1 text-sm font-medium">Call to Action</h3>
                                        <div className="flex items-center gap-2">
                                            {content.cta.url ? (
                                                <a
                                                    href={content.cta.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-sm text-blue-600 hover:underline"
                                                >
                                                    {content.cta.text || content.cta.url}
                                                </a>
                                            ) : (
                                                <p className="text-sm">{content.cta.text}</p>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {content.activation_window && (
                                    <div>
                                        <h3 className="text-muted-foreground mb-1 text-sm font-medium">Activation Window</h3>
                                        <div className="space-y-1 text-sm">
                                            {content.activation_window.start && (
                                                <p>Start: {new Date(content.activation_window.start).toLocaleString()}</p>
                                            )}
                                            {content.activation_window.end && <p>End: {new Date(content.activation_window.end).toLocaleString()}</p>}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
