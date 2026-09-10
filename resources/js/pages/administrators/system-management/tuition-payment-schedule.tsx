import { updateTuitionPaymentSchedule } from "@/actions/App/Http/Controllers/AdministratorSystemManagementController";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import { useForm } from "@inertiajs/react";
import { Calculator, CheckCircle2, Info, Loader2, Percent, Save, ShieldAlert } from "lucide-react";
import { toast } from "sonner";

import SystemManagementLayout from "./layout";
import type { SystemManagementPageProps, TuitionPaymentScheduleProfile, TuitionPaymentScheduleSettings } from "./types";

const studentTypes = [
    { value: "college", label: "College Level" },
    { value: "shs", label: "Senior High School" },
    { value: "tesda", label: "TESDA Programs" },
    { value: "dhrt", label: "DHRT Vocational" },
] as const;

const paymentTerms = ["prelim", "midterm", "finals"] as const;

export default function TuitionPaymentSchedulePage({
    user,
    access,
    tuition_payment_schedule_settings: settings,
}: SystemManagementPageProps) {
    const form = useForm<TuitionPaymentScheduleSettings>(settings);

    const setProfile = (studentType: keyof TuitionPaymentScheduleSettings["profiles"], profile: TuitionPaymentScheduleProfile) => {
        form.setData("profiles", { ...form.data.profiles, [studentType]: profile });
    };

    const submit = () => {
        form.put(updateTuitionPaymentSchedule.url(), {
            preserveScroll: true,
            onSuccess: () => toast.success("Tuition payment schedule updated."),
            onError: () => toast.error("Review the payment schedule values."),
        });
    };

    return (
        <SystemManagementLayout
            user={user}
            access={access}
            activeSection="tuition_payment_schedule"
            heading="Tuition Payment Schedule"
            description="Configure installment apportionment rules, percentage distribution, and rounding remainders by academic level."
        >
            <div className="space-y-6">
                <Alert className="border-border/60 bg-muted/30">
                    <Info className="size-4 text-primary" />
                    <AlertTitle className="text-sm font-semibold">Active enrollments remain untouched</AlertTitle>
                    <AlertDescription className="text-xs text-muted-foreground">
                        Updated installment formulas apply only to newly created assessment plans or when finance recalculates schedules.
                    </AlertDescription>
                </Alert>

                <Tabs defaultValue="college" className="space-y-4">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border/40 pb-3">
                        <TabsList className="h-9 p-1 bg-muted/50">
                            {studentTypes.map((type) => (
                                <TabsTrigger key={type.value} value={type.value} className="text-xs px-3">
                                    {type.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        <Button
                            onClick={submit}
                            disabled={form.processing || !access.sections.tuition_payment_schedule?.can_update}
                            className="h-8 gap-1.5 text-xs self-start sm:self-auto"
                        >
                            {form.processing ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                            <span>Save Schedules</span>
                        </Button>
                    </div>

                    {studentTypes.map((type) => {
                        const profile = form.data.profiles[type.value];
                        const percentageTotal =
                            profile.percentages.prelim + profile.percentages.midterm + profile.percentages.finals;
                        const isBalanced = Math.abs(percentageTotal - 100) < 0.001;

                        return (
                            <TabsContent key={type.value} value={type.value} className="mt-0">
                                <Card className="border-border/60 bg-card/70 shadow-xs backdrop-blur-xs">
                                    <CardHeader className="flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-border/40 pb-4">
                                        <div className="flex items-start gap-3">
                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary mt-0.5">
                                                <Calculator className="size-5" />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <CardTitle className="text-base font-semibold">
                                                        {type.label} Installment Formula
                                                    </CardTitle>
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            isBalanced
                                                                ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-[11px]"
                                                                : "border-destructive/30 bg-destructive/10 text-destructive text-[11px]"
                                                        }
                                                    >
                                                        {percentageTotal}% Allocated
                                                    </Badge>
                                                </div>
                                                <CardDescription className="text-xs mt-0.5">
                                                    Partition net tuition balance across Prelim, Midterm, and Final examination periods.
                                                </CardDescription>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2.5 rounded-lg border border-border/50 bg-background/50 px-3 py-1.5 self-start sm:self-center">
                                            <Label htmlFor={`${type.value}-enabled`} className="text-xs font-medium cursor-pointer">
                                                Active Formula
                                            </Label>
                                            <Switch
                                                id={`${type.value}-enabled`}
                                                checked={profile.enabled}
                                                onCheckedChange={(enabled) => setProfile(type.value, { ...profile, enabled })}
                                                disabled={!access.sections.tuition_payment_schedule?.can_update}
                                            />
                                        </div>
                                    </CardHeader>

                                    <CardContent className="pt-5 space-y-6">
                                        {/* Percentage Apportionment */}
                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-xs font-semibold text-foreground uppercase tracking-wider">
                                                    Term Distribution Percentages
                                                </Label>
                                                <span
                                                    className={cn(
                                                        "text-xs font-mono font-medium",
                                                        isBalanced ? "text-emerald-600 dark:text-emerald-400" : "text-destructive",
                                                    )}
                                                >
                                                    {isBalanced ? "Formula balanced (100%)" : "Must total exactly 100%"}
                                                </span>
                                            </div>

                                            <div className="grid gap-4 sm:grid-cols-3">
                                                {paymentTerms.map((term) => (
                                                    <div key={term} className="space-y-1.5 rounded-xl border border-border/50 bg-background/50 p-3">
                                                        <Label
                                                            htmlFor={`${type.value}-${term}`}
                                                            className="text-xs font-semibold capitalize text-foreground"
                                                        >
                                                            {term} Portion
                                                        </Label>
                                                        <div className="relative">
                                                            <Input
                                                                id={`${type.value}-${term}`}
                                                                type="number"
                                                                min="0"
                                                                max="100"
                                                                step="0.01"
                                                                value={profile.percentages[term]}
                                                                onChange={(event) =>
                                                                    setProfile(type.value, {
                                                                        ...profile,
                                                                        percentages: {
                                                                            ...profile.percentages,
                                                                            [term]: Number(event.target.value),
                                                                        },
                                                                    })
                                                                }
                                                                className="pr-8 font-mono text-sm"
                                                            />
                                                            <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs font-mono text-muted-foreground">
                                                                %
                                                            </span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        {/* Rounding & Remainder Controls */}
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-3 rounded-xl border border-border/50 bg-background/40 p-4">
                                                <div>
                                                    <Label className="text-xs font-semibold text-foreground">
                                                        Rounded Terms
                                                    </Label>
                                                    <p className="text-[11px] text-muted-foreground mt-0.5">
                                                        Apply clean currency rounding increments to selected terms.
                                                    </p>
                                                </div>

                                                <div className="flex flex-wrap gap-4 pt-1">
                                                    {paymentTerms.map((term) => {
                                                        const checked = profile.rounded_terms.includes(term);
                                                        const isRemainder = profile.remainder_term === term;

                                                        return (
                                                            <label
                                                                key={term}
                                                                className="flex items-center gap-2 text-xs font-medium capitalize cursor-pointer"
                                                            >
                                                                <Checkbox
                                                                    checked={checked}
                                                                    disabled={
                                                                        isRemainder ||
                                                                        !access.sections.tuition_payment_schedule?.can_update
                                                                    }
                                                                    onCheckedChange={(value) =>
                                                                        setProfile(type.value, {
                                                                            ...profile,
                                                                            rounded_terms: value
                                                                                ? [...profile.rounded_terms, term]
                                                                                : profile.rounded_terms.filter((item) => item !== term),
                                                                        })
                                                                    }
                                                                />
                                                                <span>{term}</span>
                                                                {isRemainder && (
                                                                    <span className="text-[10px] text-muted-foreground">(remainder)</span>
                                                                )}
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                            </div>

                                            <div className="space-y-2 rounded-xl border border-border/50 bg-background/40 p-4">
                                                <div>
                                                    <Label className="text-xs font-semibold text-foreground">
                                                        Exact Remainder Period
                                                    </Label>
                                                    <p className="text-[11px] text-muted-foreground mt-0.5">
                                                        Absorbs remaining pesos and centavos to guarantee total matches assessment ledger.
                                                    </p>
                                                </div>

                                                <Select
                                                    value={profile.remainder_term}
                                                    onValueChange={(value) => {
                                                        const remainder = value as TuitionPaymentScheduleProfile["remainder_term"];
                                                        setProfile(type.value, {
                                                            ...profile,
                                                            remainder_term: remainder,
                                                            rounded_terms: profile.rounded_terms.filter((term) => term !== remainder),
                                                        });
                                                    }}
                                                >
                                                    <SelectTrigger className="h-8.5 text-xs capitalize bg-background">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {paymentTerms.map((term) => (
                                                            <SelectItem key={term} value={term} className="capitalize text-xs">
                                                                {term}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        {/* Rounding Mode & Currency Increment */}
                                        <div className="grid gap-4 md:grid-cols-3">
                                            <div className="space-y-1.5">
                                                <Label htmlFor={`${type.value}-increment`} className="text-xs font-semibold">
                                                    Rounding Step Increment
                                                </Label>
                                                <Input
                                                    id={`${type.value}-increment`}
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    value={profile.rounding_increment}
                                                    onChange={(event) =>
                                                        setProfile(type.value, {
                                                            ...profile,
                                                            rounding_increment: Number(event.target.value),
                                                        })
                                                    }
                                                    className="font-mono text-sm"
                                                />
                                                <p className="text-[11px] text-muted-foreground">
                                                    E.g. 100 rounds amounts to even hundreds (₱1,500.00).
                                                </p>
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-semibold">Rounding Method</Label>
                                                <Select
                                                    value={profile.rounding_mode}
                                                    onValueChange={(value) =>
                                                        setProfile(type.value, {
                                                            ...profile,
                                                            rounding_mode: value as TuitionPaymentScheduleProfile["rounding_mode"],
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger className="h-9 text-xs">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="nearest" className="text-xs">
                                                            Nearest increment
                                                        </SelectItem>
                                                        <SelectItem value="down" className="text-xs">
                                                            Always round down (Floor)
                                                        </SelectItem>
                                                        <SelectItem value="up" className="text-xs">
                                                            Always round up (Ceiling)
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <p className="text-[11px] text-muted-foreground">
                                                    Direction applied to non-remainder terms.
                                                </p>
                                            </div>

                                            <div className="flex flex-col justify-between rounded-xl border border-border/50 bg-muted/30 p-3.5">
                                                <span className="text-[10px] uppercase font-mono tracking-wider text-muted-foreground">
                                                    Ledger Verification
                                                </span>
                                                <div className="my-1">
                                                    <span
                                                        className={cn(
                                                            "text-xl font-bold font-mono",
                                                            isBalanced ? "text-emerald-600 dark:text-emerald-400" : "text-destructive",
                                                        )}
                                                    >
                                                        {percentageTotal}%
                                                    </span>
                                                    <span className="text-xs text-muted-foreground ml-1.5">total partition</span>
                                                </div>
                                                <p className="text-[11px] text-muted-foreground capitalize truncate">
                                                    {profile.remainder_term} guarantees ledger zero-remainder.
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        );
                    })}
                </Tabs>
            </div>
        </SystemManagementLayout>
    );
}
