import PortalLayout from "@/components/portal-layout";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import { User } from "@/types/user";
import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Download, FileText, Image as ImageIcon, Paperclip, Send } from "lucide-react";
import { useRef } from "react";
import { toast } from "sonner";

// Declare route globally
declare const route: any;

interface PageProps {
    ticket: {
        id: number;
        subject: string;
        message: string;
        status: "open" | "in_progress" | "resolved" | "closed";
        priority: string;
        created_at: string;
        type: string;
        attachments: Array<{
            name: string;
            url: string;
            type: string;
        }> | null;
        replies: Array<{
            id: number;
            user_id: number;
            message: string;
            created_at: string;
            attachments: Array<{
                name: string;
                url: string;
                type: string;
            }> | null;
            user: {
                id: number;
                name: string;
                avatar_url: string | null;
            };
        }>;
        user: {
            id: number;
            name: string;
            avatar_url: string | null;
        };
    };
    user: User;
}

export default function HelpTicketShow({ ticket, user }: PageProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);

    const replyForm = useForm({
        message: "",
        attachments: [] as File[],
    });

    const submitReply = (e: React.FormEvent) => {
        e.preventDefault();

        replyForm.post(route("help.reply", ticket.id), {
            forceFormData: true,
            onSuccess: () => {
                toast.success("Reply sent successfully");
                replyForm.reset();
                if (fileInputRef.current) {
                    fileInputRef.current.value = "";
                }
            },
            onError: () => {
                toast.error("Failed to send reply");
            },
        });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            replyForm.setData("attachments", Array.from(e.target.files));
        }
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case "open":
                return "text-blue-500 bg-blue-500/10 border-blue-500/20";
            case "in_progress":
                return "text-yellow-500 bg-yellow-500/10 border-yellow-500/20";
            case "resolved":
                return "text-green-500 bg-green-500/10 border-green-200 dark:border-green-800";
            case "closed":
                return "text-gray-500 bg-gray-500/10 border-gray-500/20";
            default:
                return "text-slate-500";
        }
    };

    return (
        <PortalLayout user={user}>
            <Head title={`Ticket #${ticket.id}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={route("help.index")}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Ticket #{ticket.id}</h1>
                        <div className="text-muted-foreground mt-1 flex items-center gap-3 text-sm">
                            <Badge variant="outline" className={`border capitalize ${getStatusColor(ticket.status)}`}>
                                {ticket.status.replace("_", " ")}
                            </Badge>
                            <span>•</span>
                            <span className="capitalize">{ticket.type}</span>
                            <span>•</span>
                            <span>{new Date(ticket.created_at).toLocaleDateString()}</span>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="space-y-6 md:col-span-2">
                        {/* Original Ticket */}
                        <Card>
                            <CardHeader className="flex flex-row items-start gap-4 space-y-0">
                                <Avatar className="h-10 w-10">
                                    <AvatarImage src={ticket.user?.avatar_url || ""} />
                                    <AvatarFallback>{ticket.user?.name?.slice(0, 2).toUpperCase() || "U"}</AvatarFallback>
                                </Avatar>
                                <div className="grid flex-1 gap-1">
                                    <div className="flex items-center justify-between">
                                        <div className="font-semibold">{ticket.user?.name}</div>
                                        <span className="text-muted-foreground text-xs">{new Date(ticket.created_at).toLocaleString()}</span>
                                    </div>
                                    <div className="text-foreground text-sm font-medium">{ticket.subject}</div>
                                </div>
                            </CardHeader>
                            <CardContent className="ml-14">
                                <p className="text-sm leading-relaxed whitespace-pre-wrap">{ticket.message}</p>

                                {ticket.attachments && ticket.attachments.length > 0 && (
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {ticket.attachments.map((file, idx) => (
                                            <a
                                                key={idx}
                                                href={file.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="bg-background hover:bg-accent flex items-center gap-2 rounded-md border p-2 text-xs transition-colors"
                                            >
                                                {file.type.startsWith("image/") ? (
                                                    <ImageIcon className="h-3.5 w-3.5 text-blue-500" />
                                                ) : (
                                                    <FileText className="h-3.5 w-3.5 text-orange-500" />
                                                )}
                                                <span className="max-w-[150px] truncate">{file.name}</span>
                                                <Download className="text-muted-foreground ml-1 h-3 w-3" />
                                            </a>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Replies */}
                        <div className="space-y-4">
                            {ticket.replies.map((reply) => (
                                <Card key={reply.id} className={reply.user_id === user.id ? "bg-primary/5 border-primary/20" : ""}>
                                    <CardHeader className="flex flex-row items-start gap-4 space-y-0 py-4">
                                        <Avatar className="h-8 w-8">
                                            <AvatarImage src={reply.user.avatar_url || ""} />
                                            <AvatarFallback>{reply.user.name.slice(0, 2).toUpperCase()}</AvatarFallback>
                                        </Avatar>
                                        <div className="grid flex-1 gap-1">
                                            <div className="flex items-center justify-between">
                                                <div className="text-sm font-medium">
                                                    {reply.user.name}
                                                    {reply.user_id === user.id && (
                                                        <span className="text-muted-foreground ml-2 text-xs font-normal">(You)</span>
                                                    )}
                                                </div>
                                                <span className="text-muted-foreground text-xs">{new Date(reply.created_at).toLocaleString()}</span>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="ml-12 py-0 pb-4">
                                        <p className="text-sm leading-relaxed whitespace-pre-wrap">{reply.message}</p>

                                        {reply.attachments && reply.attachments.length > 0 && (
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                {reply.attachments.map((file, idx) => (
                                                    <a
                                                        key={idx}
                                                        href={file.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="bg-background hover:bg-accent flex items-center gap-2 rounded-md border p-2 text-xs transition-colors"
                                                    >
                                                        {file.type.startsWith("image/") ? (
                                                            <ImageIcon className="h-3.5 w-3.5 text-blue-500" />
                                                        ) : (
                                                            <FileText className="h-3.5 w-3.5 text-orange-500" />
                                                        )}
                                                        <span className="max-w-[150px] truncate">{file.name}</span>
                                                        <Download className="text-muted-foreground ml-1 h-3 w-3" />
                                                    </a>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        {/* Reply Form */}
                        {ticket.status !== "closed" && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium">Post a reply</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={submitReply} className="space-y-4">
                                        <Textarea
                                            placeholder="Type your message..."
                                            value={replyForm.data.message}
                                            onChange={(e) => replyForm.setData("message", e.target.value)}
                                            className="min-h-[100px]"
                                        />

                                        {replyForm.data.attachments.length > 0 && (
                                            <div className="flex flex-wrap gap-2">
                                                {replyForm.data.attachments.map((file, idx) => (
                                                    <Badge key={idx} variant="secondary" className="gap-1">
                                                        <Paperclip className="h-3 w-3" />
                                                        {file.name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}

                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Button type="button" variant="outline" size="sm" onClick={() => fileInputRef.current?.click()}>
                                                    <Paperclip className="mr-2 h-4 w-4" />
                                                    Attach
                                                </Button>
                                                <Input type="file" multiple className="hidden" ref={fileInputRef} onChange={handleFileChange} />
                                            </div>
                                            <Button type="submit" disabled={replyForm.processing || !replyForm.data.message}>
                                                <Send className="mr-2 h-4 w-4" />
                                                Reply
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        )}

                        {ticket.status === "closed" && (
                            <div className="bg-muted/50 text-muted-foreground rounded-lg border p-4 text-center text-sm">
                                This ticket has been closed and cannot be replied to.
                            </div>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Ticket Info</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Status</span>
                                    <Badge variant="outline" className={getStatusColor(ticket.status)}>
                                        {ticket.status.replace("_", " ")}
                                    </Badge>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Priority</span>
                                    <span className="font-medium capitalize">{ticket.priority}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Created</span>
                                    <span>{new Date(ticket.created_at).toLocaleDateString()}</span>
                                </div>
                                <Separator />
                                <div className="pt-2">
                                    <span className="text-muted-foreground mb-2 block">Support Team</span>
                                    <p className="text-muted-foreground text-xs">
                                        Our support team typically responds within 24 hours. If this is urgent, please visit the IT office.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </PortalLayout>
    );
}
