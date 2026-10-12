import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";

export interface LogRetentionPolicy {
    enabled: boolean;
    retention_days: number;
    frequency: "daily" | "weekly";
    time: string;
}

interface Props {
    source: string;
    policy: LogRetentionPolicy;
    onChange: (policy: LogRetentionPolicy) => void;
    errors: Partial<Record<keyof LogRetentionPolicy, string>>;
    disabled: boolean;
}

export function LogRetentionPolicyFields({ source, policy, onChange, errors, disabled }: Props) {
    return (
        <FieldGroup>
            <Field orientation="horizontal">
                <FieldLabel htmlFor={`${source}-enabled`}>Scheduled cleanup</FieldLabel>
                <Switch
                    id={`${source}-enabled`}
                    checked={policy.enabled}
                    onCheckedChange={(enabled) => onChange({ ...policy, enabled })}
                    disabled={disabled}
                />
            </Field>
            <div className="grid min-w-0 gap-4 sm:grid-cols-3">
                <Field data-invalid={Boolean(errors.retention_days)}>
                    <FieldLabel htmlFor={`${source}-days`}>Delete older than (days)</FieldLabel>
                    <Input
                        id={`${source}-days`}
                        type="number"
                        min={1}
                        max={3650}
                        value={policy.retention_days}
                        aria-invalid={Boolean(errors.retention_days)}
                        onChange={(event) => onChange({ ...policy, retention_days: Number(event.target.value) })}
                        disabled={disabled}
                    />
                    <FieldError>{errors.retention_days}</FieldError>
                </Field>
                <Field data-invalid={Boolean(errors.frequency)}>
                    <FieldLabel htmlFor={`${source}-frequency`}>Frequency</FieldLabel>
                    <Select
                        value={policy.frequency}
                        onValueChange={(frequency) => onChange({ ...policy, frequency: frequency as LogRetentionPolicy["frequency"] })}
                        disabled={disabled}
                    >
                        <SelectTrigger id={`${source}-frequency`} className="w-full" aria-invalid={Boolean(errors.frequency)}>
                            <SelectValue>{policy.frequency === "daily" ? "Daily" : "Weekly (Sunday)"}</SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="daily">Daily</SelectItem>
                            <SelectItem value="weekly">Weekly (Sunday)</SelectItem>
                        </SelectContent>
                    </Select>
                    <FieldError>{errors.frequency}</FieldError>
                </Field>
                <Field data-invalid={Boolean(errors.time)}>
                    <FieldLabel htmlFor={`${source}-time`}>Run at</FieldLabel>
                    <Input
                        id={`${source}-time`}
                        type="time"
                        value={policy.time}
                        aria-invalid={Boolean(errors.time)}
                        onChange={(event) => onChange({ ...policy, time: event.target.value })}
                        disabled={disabled}
                    />
                    <FieldError>{errors.time}</FieldError>
                </Field>
            </div>
            <FieldDescription>Only records older than this retention period are removed automatically.</FieldDescription>
        </FieldGroup>
    );
}
