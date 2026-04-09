import AdminLayout from "@/components/administrators/admin-layout";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { ArrowLeft, Download, FileText, Image as ImageIcon, Paperclip, Save, Send } from "lucide-react";
import { useRef } from "react";
import { toast } from "sonner";
import { HelpTicket } from "./columns";

// Declare route globally
declare const route: any;

interface PageProps {
    ticket: HelpTicket & {
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
    };
}

export default function HelpTicketShow({ ticket }: PageProps) {
    const { auth } = usePage<any>().props;
    const user = auth.user;
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { data, setData, put, processing, isDirty } = useForm({
        status: ticket.status,
        priority: ticket.priority,
    });

    const replyForm = useForm({
        message: "",
        attachments: [] as File[],
    });

    const updateTicket = (e: React.FormEvent) => {
        e.preventDefault();
        put(route("administrators.help-tickets.update", ticket.id), {
            onSuccess: () => {
                toast.success("Ticket updated successfully");
            },
            onError: () => {
                toast.error("Failed to update ticket");
            },
        });
    };

    const submitReply = (e: React.FormEvent) => {
        e.preventDefault();
        replyForm.post(route("administrators.help-tickets.reply", ticket.id), {
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

    return (
        <AdminLayout user={user} title={`Ticket #${ticket.id}`}>
            <Head title={`Ticket #${ticket.id}`} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={route("administrators.help-tickets.index")}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Ticket #${ticket.id}</h2>
                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                            <span>{new Date(ticket.created_at).toLocaleString()}</span>
                            <span>•</span>
                            <Badge variant="outline" className="capitalize">
                                {ticket.type}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="space-y-6 md:col-span-2">
                        {/* Original Ticket */}
                        <Card>
                            <CardHeader className="flex flex-row items-start gap-4">
                                <Avatar className="mt-1">
                                    <AvatarImage src={ticket.user?.avatar_url || ""} />
                                    <AvatarFallback>{ticket.user?.name?.slice(0, 2).toUpperCase() || "U"}</AvatarFallback>
                                </Avatar>
                                <div className="grid gap-1">
                                    <CardTitle className="text-base">{ticket.subject}</CardTitle>
                                    <CardDescription>
                                        <span className="text-foreground font-semibold">{ticket.user?.name}</span> opened this ticket
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm leading-relaxed whitespace-pre-wrap">{ticket.message}</p>

                                {ticket.attachments && ticket.attachments.length > 0 && (
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {ticket.attachments.map((file, idx) => (
                                            <a
                                                key={idx}
                                                href={file.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="bg-background hover:bg-accent flex items-center gap-2 rounded-md border p-2 text-sm transition-colors"
                                            >
                                                {file.type.startsWith("image/") ? (
                                                    <ImageIcon className="h-4 w-4 text-blue-500" />
                                                ) : (
                                                    <FileText className="h-4 w-4 text-orange-500" />
                                                )}
                                                <span className="max-w-[200px] truncate">{file.name}</span>
                                                <Download className="text-muted-foreground ml-1 h-3 w-3" />
                                            </a>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <div className="relative">
                            <div className="absolute inset-0 flex items-center">
                                <span className="w-full border-t" />
                            </div>
                            <div className="relative flex justify-center text-xs uppercase">
                                <span className="bg-background text-muted-foreground px-2">Discussion</span>
                            </div>
                        </div>

                        {/* Replies */}
                        <div className="space-y-4">
                            {ticket.replies.map((reply) => (
                                <Card key={reply.id} className={reply.user_id === user.id ? "border-primary/20 bg-primary/5" : ""}>
                                    <CardHeader className="flex flex-row items-center gap-4 py-4">
                                        <Avatar className="h-8 w-8">
                                            <AvatarImage src={reply.user.avatar_url || ""} />
                                            <AvatarFallback>{reply.user.name.slice(0, 2).toUpperCase()}</AvatarFallback>
                                        </Avatar>
                                        <div className="flex flex-1 items-center justify-between">
                                            <div className="grid gap-0.5">
                                                <div className="text-sm leading-none font-medium">
                                                    {reply.user.name}
                                                    {reply.user_id === user.id && (
                                                        <span className="text-muted-foreground ml-2 text-xs font-normal">(You)</span>
                                                    )}
                                                </div>
                                                <div className="text-muted-foreground text-xs">{new Date(reply.created_at).toLocaleString()}</div>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="py-4 pt-0">
                                        <p className="text-sm leading-relaxed whitespace-pre-wrap">{reply.message}</p>

                                        {reply.attachments && reply.attachments.length > 0 && (
                                            <div className="mt-4 grid gap-2">
                                                <p className="text-muted-foreground text-xs font-medium">Attachments:</p>
                                                <div className="flex flex-wrap gap-2">
                                                    {reply.attachments.map((file, idx) => (
                                                        <a
                                                            key={idx}
                                                            href={file.url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="bg-background hover:bg-accent flex items-center gap-2 rounded-md border p-2 text-sm transition-colors"
                                                        >
                                                            {file.type.startsWith("image/") ? (
                                                                <ImageIcon className="h-4 w-4 text-blue-500" />
                                                            ) : (
                                                                <FileText className="h-4 w-4 text-orange-500" />
                                                            )}
                                                            <span className="max-w-[200px] truncate">{file.name}</span>
                                                            <Download className="text-muted-foreground ml-1 h-3 w-3" />
                                                        </a>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        {/* Reply Input */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm font-medium">Add a reply</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitReply} className="space-y-4">
                                    <Textarea
                                        placeholder="Type your response here..."
                                        value={replyForm.data.message}
                                        onChange={(e) => replyForm.setData("message", e.target.value)}
                                        className="min-h-[120px]"
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
                                                Attach Files
                                            </Button>
                                            <Input type="file" multiple className="hidden" ref={fileInputRef} onChange={handleFileChange} />
                                        </div>
                                        <Button type="submit" disabled={replyForm.processing || !replyForm.data.message}>
                                            <Send className="mr-2 h-4 w-4" />
                                            Send Reply
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Management</CardTitle>
                                <CardDescription>Update ticket status</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={updateTicket} className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="status">Status</Label>
                                        <Select value={data.status} onValueChange={(val: any) => setData("status", val)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="open">Open</SelectItem>
                                                <SelectItem value="in_progress">In Progress</SelectItem>
                                                <SelectItem value="resolved">Resolved</SelectItem>
                                                <SelectItem value="closed">Closed</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="priority">Priority</Label>
                                        <Select value={data.priority} onValueChange={(val: any) => setData("priority", val)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="low">Low</SelectItem>
                                                <SelectItem value="medium">Medium</SelectItem>
                                                <SelectItem value="high">High</SelectItem>
                                                <SelectItem value="critical">Critical</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <Button type="submit" className="w-full" disabled={processing || !isDirty}>
                                        <Save className="mr-2 h-4 w-4" />
                                        Save Changes
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>User Details</CardTitle>
                            </CardHeader>
                            <CardContent className="flex items-center gap-4">
                                <Avatar className="h-12 w-12">
                                    <AvatarImage src={ticket.user?.avatar_url || ""} />
                                    <AvatarFallback>{ticket.user?.name?.slice(0, 2).toUpperCase() || "U"}</AvatarFallback>
                                </Avatar>
                                <div>
                                    <div className="font-medium">{ticket.user?.name || "Unknown User"}</div>
                                    <div className="text-muted-foreground text-sm">{ticket.user?.email}</div>
                                </div>
                            </CardContent>
                            <CardFooter>
                                {ticket.user_id && (
                                    <Button variant="outline" className="w-full" asChild>
                                        <Link href={route("administrators.users.edit", ticket.user_id)}>View Profile</Link>
                                    </Button>
                                )}
                            </CardFooter>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
