import { Badge } from "@/components/reui/badge";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { Coins, ExternalLink, Eye, Laptop, MailCheck, RotateCcw, Smartphone, Sparkles } from "lucide-react";
import { useMemo, useState } from "react";
import type { Action, WorkflowStep } from "../../types";

export interface EmailTemplateConfig {
    notification: "assessment" | "official_enrollment" | "academic_verified" | "status_update" | "custom";
    subject?: string;
    headline?: string;
    custom_body?: string;
    button_label?: string;
    button_url?: string;
    include_details?: boolean;
    include_subjects?: boolean;
    include_tuition?: boolean;
}

const TEMPLATE_PRESETS: Record<
    string,
    {
        label: string;
        notification: EmailTemplateConfig["notification"];
        subject: string;
        headline: string;
        custom_body: string;
        button_label: string;
        include_details: boolean;
        include_subjects: boolean;
        include_tuition: boolean;
        desc: string;
    }
> = {
    status_update: {
        label: "Status Progression Notice",
        notification: "status_update",
        subject: "Enrollment Status Update: {{ status }} | {{ organization_name }}",
        headline: "Application Status Update",
        custom_body:
            "Hello {{ student_name }},\n\nYour enrollment application has advanced to the following milestone in our admissions pipeline. Please review your updated status and next steps below.",
        button_label: "View Application Status",
        include_details: true,
        include_subjects: false,
        include_tuition: false,
        desc: "Dispatched whenever the applicant's status changes at this workflow milestone.",
    },
    academic_verified: {
        label: "Academic Clearance Notice",
        notification: "academic_verified",
        subject: "Academic Clearance Approved - Subjects & Fees | {{ organization_name }}",
        headline: "Enrollment Clearance Approved",
        custom_body:
            "Dear {{ student_name }},\n\nWe are pleased to inform you that your subject enrollment and curriculum evaluation have been officially verified and approved by the Academic Department.\n\nPlease proceed to settle your downpayment to finalize your matriculation.",
        button_label: "View Cleared Subjects & Pay",
        include_details: true,
        include_subjects: true,
        include_tuition: true,
        desc: "Includes approved curriculum subject list and tuition fee breakdown.",
    },
    assessment: {
        label: "Official Enrollment Certificate (PDF)",
        notification: "assessment",
        subject: "Enrollment Confirmed & Assessment Certificate | {{ organization_name }}",
        headline: "Official Matriculation Confirmed",
        custom_body:
            "Congratulations {{ student_name }}!\n\nYour enrollment has been successfully completed and approved. You are now officially enrolled for {{ school_year }} · Semester {{ semester }}.\n\nYour official Certificate of Matriculation is attached as a PDF to this email.",
        button_label: "Access Student Portal",
        include_details: true,
        include_subjects: true,
        include_tuition: true,
        desc: "Final confirmation with student portal credentials and attached assessment PDF.",
    },
    custom: {
        label: "Custom Admissions Message",
        notification: "custom",
        subject: "Important Update Regarding Your Application | {{ organization_name }}",
        headline: "Notice from Admissions Office",
        custom_body:
            "Dear {{ student_name }},\n\nWe have an important announcement regarding your admission record for {{ program }}.\n\nPlease check the instructions below and log in to your account.",
        button_label: "Open Student Portal",
        include_details: true,
        include_subjects: false,
        include_tuition: false,
        desc: "Completely customizable subject, headline, and message body.",
    },
};

const VARIABLE_TOKENS = [
    { token: "{{ student_name }}", label: "Student Name", sample: "Maria Santos" },
    { token: "{{ status }}", label: "Enrollment Status", sample: "Interview Scheduled" },
    { token: "{{ step_name }}", label: "Workflow Stage", sample: "Interview Stage" },
    { token: "{{ school_year }}", label: "School Year", sample: "2026-2027" },
    { token: "{{ semester }}", label: "Semester", sample: "1" },
    { token: "{{ program }}", label: "Course / Program", sample: "BSCS" },
    { token: "{{ portal_url }}", label: "Portal URL", sample: "http://localhost:8000/student" },
    { token: "{{ organization_name }}", label: "School Name", sample: "KOAKADEMY" },
];

interface EmailTemplateEditorDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    step: WorkflowStep | null;
    action: Action | null;
    onSave: (updatedConfig: Record<string, unknown>) => void;
}

export function EmailTemplateEditorDialog({ open, onOpenChange, step, action, onSave }: EmailTemplateEditorDialogProps) {
    const initialConfig = (action?.configuration ?? {}) as Partial<EmailTemplateConfig>;

    const initialPresetKey = (initialConfig.notification as string) in TEMPLATE_PRESETS ? (initialConfig.notification as string) : "status_update";

    const presetDef = TEMPLATE_PRESETS[initialPresetKey] ?? TEMPLATE_PRESETS.status_update;

    const [presetKey, setPresetKey] = useState<string>(initialPresetKey);
    const [notificationType, setNotificationType] = useState<EmailTemplateConfig["notification"]>(
        initialConfig.notification ?? presetDef.notification,
    );
    const [subject, setSubject] = useState<string>(initialConfig.subject ?? presetDef.subject);
    const [headline, setHeadline] = useState<string>(initialConfig.headline ?? presetDef.headline);
    const [customBody, setCustomBody] = useState<string>(initialConfig.custom_body ?? presetDef.custom_body);
    const [buttonLabel, setButtonLabel] = useState<string>(initialConfig.button_label ?? presetDef.button_label);
    const [buttonUrl, setButtonUrl] = useState<string>(initialConfig.button_url ?? "");
    const [includeDetails, setIncludeDetails] = useState<boolean>(initialConfig.include_details ?? presetDef.include_details);
    const [includeSubjects, setIncludeSubjects] = useState<boolean>(initialConfig.include_subjects ?? presetDef.include_subjects);
    const [includeTuition, setIncludeTuition] = useState<boolean>(initialConfig.include_tuition ?? presetDef.include_tuition);

    const [previewDevice, setPreviewDevice] = useState<"desktop" | "mobile">("desktop");

    // Apply preset
    const applyPreset = (key: string) => {
        const p = TEMPLATE_PRESETS[key];
        if (!p) return;
        setPresetKey(key);
        setNotificationType(p.notification);
        setSubject(p.subject);
        setHeadline(p.headline);
        setCustomBody(p.custom_body);
        setButtonLabel(p.button_label);
        setIncludeDetails(p.include_details);
        setIncludeSubjects(p.include_subjects);
        setIncludeTuition(p.include_tuition);
    };

    // Insert token into subject or body
    const insertToken = (token: string, target: "subject" | "body" | "headline") => {
        if (target === "subject") {
            setSubject((prev) => `${prev} ${token}`);
        } else if (target === "headline") {
            setHeadline((prev) => `${prev} ${token}`);
        } else {
            setCustomBody((prev) => `${prev} ${token}`);
        }
    };

    // Live interpolation for preview
    const sampleTokens: Record<string, string> = {
        student_name: "Maria Santos",
        status: step?.status || "Under Review",
        step_name: step?.label || "Admissions Milestone",
        school_year: "2026-2027",
        semester: "1",
        program: "BSCS",
        portal_url: buttonUrl.trim() || "http://localhost:8000/student",
        organization_name: "KOAKADEMY",
    };

    const previewSubject = useMemo(() => {
        let res = subject;
        Object.entries(sampleTokens).forEach(([k, v]) => {
            res = res.replaceAll(`{{ ${k} }}`, v).replaceAll(`{{${k}}}`, v);
        });
        return res;
    }, [subject, sampleTokens]);

    const previewHeadline = useMemo(() => {
        let res = headline;
        Object.entries(sampleTokens).forEach(([k, v]) => {
            res = res.replaceAll(`{{ ${k} }}`, v).replaceAll(`{{${k}}}`, v);
        });
        return res;
    }, [headline, sampleTokens]);

    const previewBody = useMemo(() => {
        let res = customBody;
        Object.entries(sampleTokens).forEach(([k, v]) => {
            res = res.replaceAll(`{{ ${k} }}`, v).replaceAll(`{{${k}}}`, v);
        });
        return res;
    }, [customBody, sampleTokens]);

    const handleSave = () => {
        const payload: Record<string, unknown> = {
            notification: notificationType,
            subject: subject.trim(),
            headline: headline.trim(),
            custom_body: customBody.trim(),
            button_label: buttonLabel.trim(),
            button_url: buttonUrl.trim() || undefined,
            include_details: includeDetails,
            include_subjects: includeSubjects,
            include_tuition: includeTuition,
        };
        onSave(payload);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="bg-background flex h-[92vh] max-h-[900px] w-[95vw] max-w-6xl flex-col overflow-hidden p-0">
                {/* Header with Title and Presets */}
                <DialogHeader className="border-border/50 bg-card/60 flex shrink-0 flex-row items-center justify-between border-b p-4 px-6">
                    <div className="flex items-center gap-3">
                        <IconTile variant="soft" size="default" className="text-primary">
                            <MailCheck className="size-5" />
                        </IconTile>
                        <div>
                            <div className="flex items-center gap-2">
                                <DialogTitle className="text-foreground text-base font-bold">Interactive Email Template Editor</DialogTitle>
                                <Badge variant="primary-light" size="sm" className="font-mono text-[10px]">
                                    {step ? step.label : "Workflow Action"}
                                </Badge>
                            </div>
                            <DialogDescription className="text-muted-foreground mt-0.5 text-xs">
                                Customize the automated email dispatched to students when this milestone is reached.
                            </DialogDescription>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="bg-muted/30 border-border/50 flex items-center gap-1.5 rounded-lg border p-1">
                            <Label className="text-muted-foreground px-2 text-[11px] font-medium">Preset:</Label>
                            <Select value={presetKey} onValueChange={applyPreset}>
                                <SelectTrigger className="bg-background h-7 w-[210px] text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(TEMPLATE_PRESETS).map(([k, p]) => (
                                        <SelectItem key={k} value={k} className="text-xs">
                                            {p.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-muted-foreground hover:text-foreground h-8 gap-1.5 text-xs"
                            onClick={() => applyPreset(presetKey)}
                            title="Reset to default preset values"
                        >
                            <RotateCcw className="size-3.5" /> Reset
                        </Button>
                    </div>
                </DialogHeader>

                {/* 2-Column Responsive Split Workspace */}
                <div className="grid min-h-0 flex-1 grid-cols-1 overflow-hidden lg:grid-cols-12">
                    {/* Left Column: Editor Controls (5 cols) */}
                    <div className="border-border/50 bg-card/20 space-y-5 overflow-y-auto border-r p-5 text-xs lg:col-span-5">
                        {/* Token Variables Insertion Bar */}
                        <div className="border-primary/20 bg-primary/5 space-y-2 rounded-xl border p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-foreground flex items-center gap-1.5 text-[11px] font-semibold">
                                    <Sparkles className="text-primary size-3.5" /> Dynamic Student Tokens
                                </span>
                                <span className="text-muted-foreground text-[10px]">Click token to insert</span>
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {VARIABLE_TOKENS.map((item) => (
                                    <button
                                        key={item.token}
                                        type="button"
                                        onClick={() => insertToken(item.token, "body")}
                                        className="bg-background border-border/70 hover:border-primary hover:text-primary inline-flex cursor-pointer items-center gap-1 rounded-md border px-2 py-0.5 font-mono text-[10px] shadow-2xs transition-colors"
                                        title={`Insert ${item.token} (e.g. ${item.sample})`}
                                    >
                                        <span>{item.token}</span>
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Compose Form */}
                        <div className="space-y-4">
                            {/* Subject Line */}
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <Label className="text-foreground text-xs font-semibold">Email Subject Line</Label>
                                    <button
                                        type="button"
                                        onClick={() => insertToken("{{ status }}", "subject")}
                                        className="text-primary cursor-pointer text-[10px] hover:underline"
                                    >
                                        + Add Status
                                    </button>
                                </div>
                                <Input
                                    value={subject}
                                    onChange={(e) => setSubject(e.target.value)}
                                    placeholder="e.g. Enrollment Status Update: {{ status }}"
                                    className="bg-background h-8.5 text-xs font-medium"
                                />
                            </div>

                            {/* Headline */}
                            <div className="space-y-1.5">
                                <Label className="text-foreground text-xs font-semibold">Banner / Card Headline</Label>
                                <Input
                                    value={headline}
                                    onChange={(e) => setHeadline(e.target.value)}
                                    placeholder="e.g. Application Status Update"
                                    className="bg-background h-8.5 text-xs font-medium"
                                />
                            </div>

                            {/* Custom Message Body */}
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <Label className="text-foreground text-xs font-semibold">Message Body (Markdown & Tokens)</Label>
                                    <button
                                        type="button"
                                        onClick={() => insertToken("{{ student_name }}", "body")}
                                        className="text-primary cursor-pointer text-[10px] hover:underline"
                                    >
                                        + Add Name
                                    </button>
                                </div>
                                <Textarea
                                    value={customBody}
                                    onChange={(e) => setCustomBody(e.target.value)}
                                    rows={5}
                                    placeholder="Type customized instructions or guidance for the applicant..."
                                    className="bg-background resize-y text-xs leading-relaxed font-normal"
                                />
                                <p className="text-muted-foreground text-[10px]">
                                    Supports Markdown formatting, line breaks, and all student tokens above.
                                </p>
                            </div>

                            {/* Action Button CTA */}
                            <div className="border-border/60 bg-muted/15 space-y-3 rounded-xl border p-3.5">
                                <Label className="text-foreground block text-xs font-semibold">Call-To-Action Button</Label>

                                <div className="grid grid-cols-2 gap-2.5">
                                    <div className="space-y-1">
                                        <Label className="text-muted-foreground text-[10px] font-medium">Button Text</Label>
                                        <Input
                                            value={buttonLabel}
                                            onChange={(e) => setButtonLabel(e.target.value)}
                                            placeholder="View Application Status"
                                            className="bg-background h-8 text-xs"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-muted-foreground text-[10px] font-medium">URL Override (Optional)</Label>
                                        <Input
                                            value={buttonUrl}
                                            onChange={(e) => setButtonUrl(e.target.value)}
                                            placeholder="Default (Portal link)"
                                            className="bg-background h-8 text-xs"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Component Section Toggles */}
                            <div className="border-border/60 bg-muted/15 space-y-2.5 rounded-xl border p-3.5">
                                <Label className="text-foreground block text-xs font-semibold">Email Sections & Data Tables</Label>

                                <div className="border-border/30 flex items-center justify-between border-b py-1">
                                    <div>
                                        <span className="text-foreground block text-xs font-medium">Application Details Table</span>
                                        <span className="text-muted-foreground text-[10px]">Shows status, stage, term, and course.</span>
                                    </div>
                                    <Switch checked={includeDetails} onCheckedChange={setIncludeDetails} />
                                </div>

                                <div className="border-border/30 flex items-center justify-between border-b py-1">
                                    <div>
                                        <span className="text-foreground block text-xs font-medium">Curriculum Subjects List</span>
                                        <span className="text-muted-foreground text-[10px]">Tables approved subject codes and units.</span>
                                    </div>
                                    <Switch checked={includeSubjects} onCheckedChange={setIncludeSubjects} />
                                </div>

                                <div className="flex items-center justify-between py-1">
                                    <div>
                                        <span className="text-foreground block text-xs font-medium">Tuition & Downpayment Summary</span>
                                        <span className="text-muted-foreground text-[10px]">Renders financial balance and cashier downpayment.</span>
                                    </div>
                                    <Switch checked={includeTuition} onCheckedChange={setIncludeTuition} />
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Live Interactive Email Preview (7 cols) */}
                    <div className="bg-muted/20 flex min-h-0 flex-col lg:col-span-7">
                        {/* Preview Device & Settings Bar */}
                        <div className="border-border/50 bg-card/40 flex shrink-0 items-center justify-between border-b p-3 px-5">
                            <div className="flex items-center gap-2">
                                <span className="text-foreground flex items-center gap-1.5 text-xs font-semibold">
                                    <Eye className="text-primary size-3.5" /> Live Email Preview
                                </span>
                                <Badge variant="outline" className="font-mono text-[10px]">
                                    {previewDevice.toUpperCase()}
                                </Badge>
                            </div>

                            <div className="bg-background border-border/60 flex items-center gap-1 rounded-lg border p-0.5">
                                <Button
                                    type="button"
                                    variant={previewDevice === "desktop" ? "secondary" : "ghost"}
                                    size="sm"
                                    className="h-6.5 gap-1 px-2 text-xs"
                                    onClick={() => setPreviewDevice("desktop")}
                                    title="View as Desktop email client"
                                >
                                    <Laptop className="size-3" /> Desktop
                                </Button>
                                <Button
                                    type="button"
                                    variant={previewDevice === "mobile" ? "secondary" : "ghost"}
                                    size="sm"
                                    className="h-6.5 gap-1 px-2 text-xs"
                                    onClick={() => setPreviewDevice("mobile")}
                                    title="View as Mobile email client"
                                >
                                    <Smartphone className="size-3" /> Mobile
                                </Button>
                            </div>
                        </div>

                        {/* Subject Header Simulation */}
                        <div className="border-border/40 bg-background/50 text-muted-foreground flex shrink-0 items-center gap-2 border-b p-3 px-5 font-mono text-xs">
                            <span className="text-foreground shrink-0 font-semibold">Subject:</span>
                            <span className="text-foreground truncate font-medium">{previewSubject}</span>
                        </div>

                        {/* Visual Email Render Canvas */}
                        <div className="flex min-h-0 flex-1 items-start justify-center overflow-y-auto p-6">
                            <div
                                className={cn(
                                    "bg-background border-border/70 text-foreground w-full overflow-hidden rounded-2xl border shadow-lg transition-all duration-300",
                                    previewDevice === "desktop" ? "max-w-[560px]" : "max-w-[360px]",
                                )}
                            >
                                {/* Email Top School Banner */}
                                <div className="border-primary from-muted/30 to-background space-y-1 border-b-2 bg-gradient-to-b p-6 text-center">
                                    <div className="mb-2 flex justify-center">
                                        <div className="bg-primary/10 border-primary/20 text-primary flex size-11 items-center justify-center rounded-xl border font-mono font-bold">
                                            KOA
                                        </div>
                                    </div>
                                    <h3 className="text-foreground text-base font-bold tracking-tight">KOAKADEMY ADMISSIONS</h3>
                                    <p className="text-muted-foreground text-[11px]">Official Student Journey & Matriculation Notice</p>
                                </div>

                                {/* Email Body Content */}
                                <div className="space-y-5 p-6 text-xs">
                                    {/* Headline Banner */}
                                    <div className="border-primary border-l-4 py-0.5 pl-3">
                                        <span className="text-muted-foreground block text-[10px] font-bold tracking-wider uppercase">
                                            Enrollment Notification
                                        </span>
                                        <h2 className="text-foreground mt-0.5 text-base font-bold">{previewHeadline}</h2>
                                    </div>

                                    <div className="text-foreground/90 space-y-1.5 leading-relaxed">
                                        <p className="font-semibold">Dear Maria Santos,</p>
                                        <p className="text-muted-foreground leading-relaxed whitespace-pre-line">{previewBody}</p>
                                    </div>

                                    {/* Highlighted Status Callout Panel */}
                                    <div className="border-primary/30 bg-primary/5 space-y-1 rounded-xl border p-3.5 shadow-2xs">
                                        <div className="flex items-center justify-between">
                                            <span className="text-primary text-[10px] font-bold tracking-wider uppercase">Active Status</span>
                                            <Badge variant="primary-light" size="sm" className="text-[10px] font-semibold">
                                                {step?.status || "Under Review"}
                                            </Badge>
                                        </div>
                                        <div className="text-foreground text-sm font-bold">Stage: {step?.label || "Admissions Milestone"}</div>
                                    </div>

                                    {/* Application Details Table */}
                                    {includeDetails && (
                                        <div className="space-y-2">
                                            <span className="text-foreground block text-[11px] font-bold tracking-wider uppercase">
                                                Application Details
                                            </span>
                                            <div className="border-border/60 bg-card/40 overflow-hidden rounded-xl border">
                                                <table className="w-full text-left text-[11px]">
                                                    <tbody>
                                                        <tr className="border-border/40 border-b">
                                                            <td className="text-muted-foreground bg-muted/20 w-1/3 p-2.5 font-semibold">Status</td>
                                                            <td className="text-foreground p-2.5 font-medium">{step?.status || "Under Review"}</td>
                                                        </tr>
                                                        <tr className="border-border/40 border-b">
                                                            <td className="text-muted-foreground bg-muted/20 p-2.5 font-semibold">Stage</td>
                                                            <td className="text-foreground p-2.5 font-medium">{step?.label || "Verification"}</td>
                                                        </tr>
                                                        <tr className="border-border/40 border-b">
                                                            <td className="text-muted-foreground bg-muted/20 p-2.5 font-semibold">Academic Term</td>
                                                            <td className="text-foreground p-2.5 font-medium">2026-2027 · Semester 1</td>
                                                        </tr>
                                                        <tr>
                                                            <td className="text-muted-foreground bg-muted/20 p-2.5 font-semibold">Program</td>
                                                            <td className="text-foreground p-2.5 font-medium">BSCS (Computer Science)</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}

                                    {/* Approved Curriculum Subjects Table */}
                                    {includeSubjects && (
                                        <div className="space-y-2">
                                            <span className="text-foreground block text-[11px] font-bold tracking-wider uppercase">
                                                Approved Curriculum Subjects
                                            </span>
                                            <div className="border-border/60 bg-card/40 overflow-hidden rounded-xl border">
                                                <table className="w-full text-left text-[11px]">
                                                    <thead>
                                                        <tr className="border-border/40 bg-muted/30 text-muted-foreground border-b font-semibold">
                                                            <th className="p-2">Code</th>
                                                            <th className="p-2">Title</th>
                                                            <th className="p-2 text-right">Units</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr className="border-border/30 border-b">
                                                            <td className="p-2 font-mono font-semibold">CS101</td>
                                                            <td className="p-2">Intro to Programming</td>
                                                            <td className="p-2 text-right font-medium">3.0</td>
                                                        </tr>
                                                        <tr className="border-border/30 border-b">
                                                            <td className="p-2 font-mono font-semibold">MATH101</td>
                                                            <td className="p-2">Discrete Mathematics</td>
                                                            <td className="p-2 text-right font-medium">3.0</td>
                                                        </tr>
                                                        <tr>
                                                            <td className="p-2 font-mono font-semibold">ENG101</td>
                                                            <td className="p-2">Purposive Communication</td>
                                                            <td className="p-2 text-right font-medium">3.0</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}

                                    {/* Tuition & Downpayment Summary Panel */}
                                    {includeTuition && (
                                        <div className="space-y-2 rounded-xl border border-amber-500/30 bg-amber-500/5 p-3.5">
                                            <div className="flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-amber-600 uppercase dark:text-amber-400">
                                                <Coins className="size-3.5" /> Financial Summary
                                            </div>
                                            <div className="grid grid-cols-2 gap-2 text-[11px]">
                                                <div>
                                                    <span className="text-muted-foreground block text-[10px]">Total Tuition:</span>
                                                    <span className="text-foreground font-mono font-bold">₱ 18,500.00</span>
                                                </div>
                                                <div>
                                                    <span className="text-muted-foreground block text-[10px]">Downpayment Due:</span>
                                                    <span className="font-mono font-bold text-emerald-600 dark:text-emerald-400">₱ 3,500.00</span>
                                                </div>
                                            </div>
                                            <p className="text-muted-foreground border-t border-amber-500/20 pt-1 text-[10px] leading-tight">
                                                Please visit the Cashier's Office within 3 to 5 business days to finalize settlement.
                                            </p>
                                        </div>
                                    )}

                                    {/* Call to action Button */}
                                    <div className="flex justify-center pt-2">
                                        <button
                                            type="button"
                                            className="bg-primary text-primary-foreground hover:bg-primary/90 flex cursor-pointer items-center gap-1.5 rounded-xl px-6 py-2.5 text-xs font-semibold shadow-md transition-all"
                                        >
                                            <span>{buttonLabel || "Visit Student Portal"}</span>
                                            <ExternalLink className="size-3" />
                                        </button>
                                    </div>

                                    {/* Footer */}
                                    <div className="border-border/50 text-muted-foreground space-y-1 border-t pt-6 text-center text-[10px]">
                                        <p>KOAKADEMY · Office of Admissions & Registrar</p>
                                        <p>Need assistance? Contact admissions@koakademy.edu</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Modal Footer */}
                <DialogFooter className="border-border/50 bg-card/60 flex shrink-0 items-center justify-between border-t p-4 px-6 sm:justify-between">
                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                        <span className="size-2 rounded-full bg-emerald-500" />
                        <span>Changes apply immediately to this workflow sub-action.</span>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => onOpenChange(false)} className="h-8.5 text-xs">
                            Cancel
                        </Button>
                        <Button type="button" size="sm" onClick={handleSave} className="h-8.5 gap-1.5 text-xs font-semibold shadow-xs">
                            <MailCheck className="size-4" /> Save & Apply Template
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
