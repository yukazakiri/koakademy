import PortalLayout from "@/components/portal-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { User } from "@/types/user";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { motion } from "framer-motion";
import { AlertCircle, BookOpen, CheckCircle2, Clock, HelpCircle, History, LifeBuoy, MessageSquarePlus, Paperclip, Send } from "lucide-react";
import { FormEventHandler, useRef } from "react";
import { toast } from "sonner";

interface HelpTicket {
    id: number;
    type: string;
    subject: string;
    message: string;
    status: string;
    priority: string;
    created_at: string;
}

interface Props {
    user: {
        name: string;
        email: string;
        avatar: string | null;
        role: any;
    };
    tickets: HelpTicket[];
    submit_url: string;
}

export default function HelpIndex({ user, tickets, submit_url }: Props) {
    const { props } = usePage<{ branding?: { supportEmail?: string | null } }>();
    const supportEmail = props.branding?.supportEmail || "support@koakademy.edu";
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        type: "support",
        subject: "",
        message: "",
        priority: "low",
        attachments: [] as File[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(submit_url, {
            forceFormData: true,
            onSuccess: () => {
                reset();
                if (fileInputRef.current) {
                    fileInputRef.current.value = "";
                }
                toast.success("Ticket submitted successfully", {
                    description: "Our support team will get back to you shortly.",
                });
            },
            onError: () => {
                toast.error("Failed to submit ticket", {
                    description: "Please check your input and try again.",
                });
            },
        });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            setData("attachments", Array.from(e.target.files));
        }
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case "open":
                return "bg-blue-500/10 text-blue-500 border-blue-200 dark:border-blue-800";
            case "resolved":
                return "bg-green-500/10 text-green-500 border-green-200 dark:border-green-800";
            case "closed":
                return "bg-gray-500/10 text-gray-500 border-gray-200 dark:border-gray-800";
            default:
                return "bg-slate-500/10 text-slate-500 border-slate-200 dark:border-slate-800";
        }
    };

    const getPriorityIcon = (priority: string) => {
        switch (priority) {
            case "high":
                return <AlertCircle className="h-4 w-4 text-red-500" />;
            case "medium":
                return <Clock className="h-4 w-4 text-orange-500" />;
            case "low":
                return <CheckCircle2 className="h-4 w-4 text-green-500" />;
            default:
                return <HelpCircle className="h-4 w-4 text-slate-500" />;
        }
    };

    const getPriorityLabel = (priority: string) => {
        return (
            <span
                className={`flex items-center gap-1 text-xs font-medium capitalize ${
                    priority === "high" ? "text-red-500" : priority === "medium" ? "text-orange-500" : "text-green-500"
                }`}
            >
                {getPriorityIcon(priority)}
                {priority} Priority
            </span>
        );
    };

    return (
        <PortalLayout user={user as User}>
            <Head title="Help & Support" />

            <div className="mx-auto max-w-6xl space-y-8">
                {/* Hero Section */}
                <div className="from-primary/10 via-primary/5 to-background relative overflow-hidden rounded-3xl border bg-gradient-to-br p-8 shadow-sm md:p-12">
                    <div className="relative z-10 flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
                        <div className="max-w-2xl space-y-4">
                            <div className="bg-background/50 text-muted-foreground inline-flex items-center rounded-full border px-3 py-1 text-sm backdrop-blur-sm">
                                <LifeBuoy className="mr-2 h-3.5 w-3.5" />
                                Student Support Center
                            </div>
                            <h1 className="text-foreground text-3xl font-bold tracking-tight md:text-4xl">
                                How can we help you today, {user.name.split(" ")[0]}?
                            </h1>
                            <p className="text-muted-foreground text-lg">
                                Need assistance with your courses, account, or have a suggestion? Create a ticket below and we'll resolve it as soon
                                as possible.
                            </p>
                        </div>
                        <div className="hidden md:block">
                            <div className="bg-primary/10 flex h-32 w-32 animate-pulse items-center justify-center rounded-full">
                                <LifeBuoy className="text-primary h-16 w-16" />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid gap-8 lg:grid-cols-12">
                    {/* Main Content - Tabs */}
                    <div className="lg:col-span-8">
                        <Tabs defaultValue="submit" className="w-full">
                            <TabsList className="bg-muted/50 mb-6 grid w-full grid-cols-2 rounded-xl p-1">
                                <TabsTrigger
                                    value="submit"
                                    className="data-[state=active]:bg-background rounded-lg py-3 transition-all data-[state=active]:shadow-sm"
                                >
                                    <MessageSquarePlus className="mr-2 h-4 w-4" />
                                    New Ticket
                                </TabsTrigger>
                                <TabsTrigger
                                    value="history"
                                    className="data-[state=active]:bg-background rounded-lg py-3 transition-all data-[state=active]:shadow-sm"
                                >
                                    <History className="mr-2 h-4 w-4" />
                                    My Tickets ({tickets.length})
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="submit" className="mt-0">
                                <Card className="bg-card/50 border-none shadow-md backdrop-blur-sm">
                                    <CardHeader>
                                        <CardTitle>Submit a Request</CardTitle>
                                        <CardDescription>Please provide as much detail as possible so we can assist you better.</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <form onSubmit={submit} className="space-y-6">
                                            <div className="grid gap-6 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label htmlFor="type">Ticket Type</Label>
                                                    <Select value={data.type} onValueChange={(val) => setData("type", val)}>
                                                        <SelectTrigger id="type" className="h-11">
                                                            <SelectValue placeholder="Select type" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="support">
                                                                <div className="flex items-center">
                                                                    <LifeBuoy className="mr-2 h-4 w-4 text-blue-500" />
                                                                    <span>General Support</span>
                                                                </div>
                                                            </SelectItem>
                                                            <SelectItem value="issue">
                                                                <div className="flex items-center">
                                                                    <AlertCircle className="mr-2 h-4 w-4 text-red-500" />
                                                                    <span>Report an Issue</span>
                                                                </div>
                                                            </SelectItem>
                                                            <SelectItem value="recommendation">
                                                                <div className="flex items-center">
                                                                    <Send className="mr-2 h-4 w-4 text-purple-500" />
                                                                    <span>Suggestion / Feedback</span>
                                                                </div>
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    {errors.type && <p className="text-sm text-red-500">{errors.type}</p>}
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="priority">Priority Level</Label>
                                                    <Select value={data.priority} onValueChange={(val) => setData("priority", val)}>
                                                        <SelectTrigger id="priority" className="h-11">
                                                            <SelectValue placeholder="Select priority" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="low">
                                                                <div className="flex items-center">
                                                                    <CheckCircle2 className="mr-2 h-4 w-4 text-green-500" />
                                                                    <span>Low - General Question</span>
                                                                </div>
                                                            </SelectItem>
                                                            <SelectItem value="medium">
                                                                <div className="flex items-center">
                                                                    <Clock className="mr-2 h-4 w-4 text-orange-500" />
                                                                    <span>Medium - Needs Attention</span>
                                                                </div>
                                                            </SelectItem>
                                                            <SelectItem value="high">
                                                                <div className="flex items-center">
                                                                    <AlertCircle className="mr-2 h-4 w-4 text-red-500" />
                                                                    <span>High - Critical Issue</span>
                                                                </div>
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    {errors.priority && <p className="text-sm text-red-500">{errors.priority}</p>}
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="subject">Subject</Label>
                                                <Input
                                                    id="subject"
                                                    value={data.subject}
                                                    onChange={(e) => setData("subject", e.target.value)}
                                                    placeholder="Briefly describe the issue..."
                                                    className="h-11"
                                                />
                                                {errors.subject && <p className="text-sm text-red-500">{errors.subject}</p>}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="message">Detailed Description</Label>
                                                <Textarea
                                                    id="message"
                                                    value={data.message}
                                                    onChange={(e) => setData("message", e.target.value)}
                                                    placeholder="Please describe what happened, what you expected to happen, and any steps to reproduce the issue..."
                                                    className="min-h-[200px] resize-y"
                                                />
                                                {errors.message && <p className="text-sm text-red-500">{errors.message}</p>}
                                                <p className="text-muted-foreground text-right text-xs">{data.message.length} characters</p>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Attachments (Optional)</Label>
                                                <div className="flex items-center gap-4">
                                                    <Button type="button" variant="outline" onClick={() => fileInputRef.current?.click()}>
                                                        <Paperclip className="mr-2 h-4 w-4" />
                                                        Attach Files
                                                    </Button>
                                                    <Input type="file" multiple className="hidden" ref={fileInputRef} onChange={handleFileChange} />
                                                    <div className="flex flex-wrap gap-2">
                                                        {data.attachments.map((file, idx) => (
                                                            <Badge key={idx} variant="secondary">
                                                                {file.name}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                </div>
                                                {errors.attachments && <p className="text-sm text-red-500">{errors.attachments}</p>}
                                            </div>

                                            <div className="flex items-center justify-between pt-4">
                                                <p className="text-muted-foreground text-sm">We typically respond within 24 hours.</p>
                                                <Button type="submit" disabled={processing} size="lg" className="min-w-[150px]">
                                                    {processing ? (
                                                        <>
                                                            <Clock className="mr-2 h-4 w-4 animate-spin" />
                                                            Submitting...
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Send className="mr-2 h-4 w-4" />
                                                            Submit Ticket
                                                        </>
                                                    )}
                                                </Button>
                                            </div>
                                        </form>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="history" className="mt-0">
                                <Card className="bg-card/50 border-none shadow-md backdrop-blur-sm">
                                    <CardHeader>
                                        <CardTitle>Ticket History</CardTitle>
                                        <CardDescription>Track the status of your previous requests</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        {tickets.length === 0 ? (
                                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                                <div className="bg-muted mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                                                    <MessageSquarePlus className="text-muted-foreground h-8 w-8" />
                                                </div>
                                                <h3 className="text-foreground text-lg font-medium">No tickets found</h3>
                                                <p className="text-muted-foreground mt-2 max-w-xs">
                                                    You haven't submitted any support tickets yet. Once you do, they'll appear here.
                                                </p>
                                            </div>
                                        ) : (
                                            <ScrollArea className="h-[600px] pr-4">
                                                <div className="space-y-4">
                                                    {tickets.map((ticket, index) => (
                                                        <Link key={ticket.id} href={route("help.show", ticket.id)} className="block">
                                                            <motion.div
                                                                initial={{ opacity: 0, y: 10 }}
                                                                animate={{ opacity: 1, y: 0 }}
                                                                transition={{ delay: index * 0.05 }}
                                                                className="bg-card hover:bg-accent/50 flex flex-col justify-between gap-4 rounded-xl border p-4 transition-colors sm:flex-row sm:items-center"
                                                            >
                                                                <div className="space-y-1">
                                                                    <div className="flex items-center gap-2">
                                                                        <Badge
                                                                            variant="outline"
                                                                            className={`border-0 ${getStatusColor(ticket.status)}`}
                                                                        >
                                                                            {ticket.status}
                                                                        </Badge>
                                                                        <span className="text-muted-foreground text-xs">#{ticket.id}</span>
                                                                        <span className="text-muted-foreground text-xs">
                                                                            • {new Date(ticket.created_at).toLocaleDateString()}
                                                                        </span>
                                                                    </div>
                                                                    <h4 className="text-foreground font-semibold">{ticket.subject}</h4>
                                                                    <p className="text-muted-foreground line-clamp-1 text-sm">{ticket.message}</p>
                                                                </div>
                                                                <div className="flex shrink-0 items-center gap-4">
                                                                    {getPriorityLabel(ticket.priority)}
                                                                    <div className="bg-border hidden h-8 w-px sm:block" />
                                                                    <div className="text-muted-foreground min-w-[80px] text-sm capitalize">
                                                                        {ticket.type}
                                                                    </div>
                                                                </div>
                                                            </motion.div>
                                                        </Link>
                                                    ))}
                                                </div>
                                            </ScrollArea>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </div>

                    {/* Sidebar - Quick Info */}
                    <div className="space-y-6 lg:col-span-4">
                        <Card className="bg-primary text-primary-foreground border-none">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <HelpCircle className="h-5 w-5" />
                                    Quick Help
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm opacity-90">
                                    Before submitting a ticket, check our FAQ section for immediate answers to common questions.
                                </p>
                                <Button variant="secondary" className="group w-full justify-between">
                                    Browse FAQ
                                    <BookOpen className="h-4 w-4 transition-transform group-hover:translate-x-1" />
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Contact Info</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <div className="space-y-1">
                                    <p className="font-medium">IT Support Office</p>
                                    <p className="text-muted-foreground">Building A, Room 302</p>
                                </div>
                                <div className="space-y-1">
                                    <p className="font-medium">Email Us</p>
                                    <p className="text-muted-foreground">{supportEmail}</p>
                                </div>
                                <div className="space-y-1">
                                    <p className="font-medium">Office Hours</p>
                                    <p className="text-muted-foreground">Mon-Fri: 8:00 AM - 5:00 PM</p>
                                    <p className="text-muted-foreground">Sat: 8:00 AM - 12:00 PM</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="bg-muted/30">
                            <CardHeader>
                                <CardTitle className="text-lg">Common Topics</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {["Enrollment", "Grades", "Account Access", "WiFi", "ID Card", "Schedule"].map((tag) => (
                                        <Badge
                                            key={tag}
                                            variant="secondary"
                                            className="hover:bg-primary hover:text-primary-foreground cursor-pointer transition-colors"
                                        >
                                            {tag}
                                        </Badge>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </PortalLayout>
    );
}
