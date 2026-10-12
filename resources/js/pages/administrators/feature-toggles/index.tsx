import { saveGroup } from "@/actions/App/Http/Controllers/AdministratorFeatureToggleController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/reui/badge";
import { DataGrid, DataGridContainer, dataGridFeatures, type DataGridFeatures } from "@/components/reui/data-grid/data-grid";
import { DataGridPagination } from "@/components/reui/data-grid/data-grid-pagination";
import { DataGridScrollArea } from "@/components/reui/data-grid/data-grid-scroll-area";
import { DataGridTable } from "@/components/reui/data-grid/data-grid-table";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Head, useForm } from "@inertiajs/react";
import { useTable, type ColumnDef } from "@tanstack/react-table-v9";
import { ArrowUpRight, ShieldCheck, Users, X } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import { FeatureInspector } from "./inspector";
import type { AuditEntry, BetaGroup, FeatureUser, ManagedFeature } from "./types";
import { portalFeatureUrl } from "./types";
import { UserPicker } from "./user-picker";

export interface FeaturePageProps {
    features: ManagedFeature[];
    roles: { value: string; label: string }[];
    schools: { id: number; name: string }[];
    groups: BetaGroup[];
    audit: AuditEntry[];
}

function releaseStatus(feature: ManagedFeature): { label: string; variant: "warning-light" | "destructive-light" | "info-light" | "success-light" } {
    const policy = feature.policy;
    if (policy.mode === "legacy") return { label: "Legacy preserved", variant: "warning-light" };
    if (!policy.enabled) return { label: "Disabled", variant: "destructive-light" };
    if (feature.release_status === "scheduled") return { label: "Scheduled", variant: "info-light" };
    if (feature.release_status === "retired") return { label: "Retired", variant: "destructive-light" };
    if (policy.strategy === "percentage") return { label: `${policy.percentage}% rollout`, variant: "success-light" };
    if (policy.strategy === "targeted") return { label: "Targets only", variant: "info-light" };
    return feature.catalog.default_enabled
        ? { label: "Role defaults", variant: "success-light" }
        : { label: "Opt-in / targets", variant: "info-light" };
}

export default function FeatureTogglesIndex(props: FeaturePageProps) {
    const { features, roles, schools, groups, audit } = props;
    const [search, setSearch] = useState("");
    const [tier, setTier] = useState("all");
    const [selectedKey, setSelectedKey] = useState(features[0]?.key ?? "");
    const [groupDialog, setGroupDialog] = useState(false);
    const [members, setMembers] = useState<FeatureUser[]>([]);
    const groupForm = useForm({ id: null as number | null, name: "", description: "", school_id: null as number | null, user_ids: [] as number[] });
    const selected = features.find((feature) => feature.key === selectedKey);
    const visible = useMemo(
        () =>
            features.filter(
                (feature) =>
                    (tier === "all" || feature.catalog.tier === tier) &&
                    `${feature.name} ${feature.key} ${feature.category}`.toLowerCase().includes(search.toLowerCase()),
            ),
        [features, search, tier],
    );
    const columns = useMemo<ColumnDef<DataGridFeatures, ManagedFeature>[]>(
        () => [
            {
                accessorKey: "name",
                header: "Feature",
                size: 260,
                cell: ({ row }) => (
                    <Button
                        variant="ghost"
                        className="aria-[current=true]:bg-muted w-full justify-start"
                        aria-current={selectedKey === row.original.key ? "true" : undefined}
                        onClick={() => setSelectedKey(row.original.key)}
                    >
                        <span className="truncate text-left">{row.original.name}</span>
                        <ArrowUpRight />
                    </Button>
                ),
            },
            { accessorKey: "category", header: "Destination", size: 110 },
            {
                id: "release",
                header: "Release",
                size: 140,
                cell: ({ row }) => {
                    const status = releaseStatus(row.original);
                    return <Badge variant={status.variant}>{status.label}</Badge>;
                },
            },
            {
                accessorKey: "assignment_count",
                header: "Targets",
                size: 70,
                cell: ({ row }) => <span className="tabular-nums">{row.original.assignment_count}</span>,
            },
        ],
        [selectedKey],
    );
    const table = useTable({
        features: dataGridFeatures,
        columns,
        data: visible,
        getRowId: (row) => row.key,
        initialState: { pagination: { pageIndex: 0, pageSize: 10 } },
    });

    const openGroup = (group?: BetaGroup) => {
        groupForm.setData({
            id: group?.id ?? null,
            name: group?.name ?? "",
            description: group?.description ?? "",
            school_id: group?.school_id ?? null,
            user_ids: group?.members.map((user) => user.id) ?? [],
        });
        groupForm.clearErrors();
        setMembers(group?.members ?? []);
        setGroupDialog(true);
    };

    return (
        <AdminLayout title="Feature access">
            <Head title="Feature access" />
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-2">
                        <h1 className="text-2xl font-semibold tracking-tight">Feature access</h1>
                        <Badge variant="outline">Local control</Badge>
                    </div>
                    <p className="text-muted-foreground text-sm">Release features to the right people. Keep existing access intact.</p>
                </div>
                <Button size="sm" onClick={() => openGroup()}>
                    <Users />
                    New beta group
                </Button>
            </header>
            <div className="text-muted-foreground flex flex-wrap items-center gap-4 text-xs">
                <span>{features.length} features</span>
                <span>{features.filter((feature) => feature.catalog.tier === "built_in").length} built-ins</span>
                <span>{features.filter((feature) => feature.policy.mode === "legacy").length} legacy policies preserved</span>
                <span className="flex items-center gap-1">
                    <ShieldCheck className="size-4" />
                    Permissions still enforced
                </span>
            </div>
            <div className="grid min-w-0 items-start gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(340px,0.8fr)]">
                <div className="flex min-w-0 flex-col gap-6">
                    <Frame spacing="default">
                        <FramePanel className="p-0">
                            <FrameHeader>
                                <FrameTitle>Feature catalog</FrameTitle>
                                <FrameDescription>Built-in defaults and controlled releases</FrameDescription>
                            </FrameHeader>
                            <div className="flex flex-wrap gap-2 px-4 pb-4">
                                <Input
                                    aria-label="Search features"
                                    placeholder="Search features"
                                    className="min-w-0 flex-1"
                                    value={search}
                                    onChange={(event) => {
                                        setSearch(event.target.value);
                                        table.setPageIndex(0);
                                    }}
                                />
                                <Select
                                    value={tier}
                                    onValueChange={(value) => {
                                        setTier(value);
                                        table.setPageIndex(0);
                                    }}
                                >
                                    <SelectTrigger size="sm" aria-label="Feature tier">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All tiers</SelectItem>
                                        <SelectItem value="built_in">Built-in</SelectItem>
                                        <SelectItem value="beta">Beta</SelectItem>
                                        <SelectItem value="restricted">Restricted</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <DataGrid
                                table={table}
                                recordCount={visible.length}
                                tableLayout={{ dense: true }}
                                emptyMessage={
                                    <div className="flex flex-col items-center gap-2">
                                        <p>No matching features.</p>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setSearch("");
                                                setTier("all");
                                            }}
                                        >
                                            Clear filters
                                        </Button>
                                    </div>
                                }
                            >
                                <DataGridContainer>
                                    <DataGridScrollArea orientation="horizontal">
                                        <DataGridTable />
                                    </DataGridScrollArea>
                                </DataGridContainer>
                                <DataGridPagination />
                            </DataGrid>
                        </FramePanel>
                    </Frame>
                    <Frame spacing="default">
                        <FramePanel>
                            <FrameHeader>
                                <FrameTitle>Beta groups</FrameTitle>
                                <FrameDescription>Named cohorts, separate from permission roles</FrameDescription>
                            </FrameHeader>
                            <div className="flex flex-col gap-3">
                                {groups.length === 0 ? (
                                    <div className="flex flex-col items-start gap-2 text-sm">
                                        <p className="text-muted-foreground">No beta groups yet.</p>
                                        <Button variant="outline" size="sm" onClick={() => openGroup()}>
                                            Create group
                                        </Button>
                                    </div>
                                ) : (
                                    groups.map((group) => (
                                        <div key={group.id} className="flex items-center justify-between gap-3">
                                            <div className="flex min-w-0 flex-col gap-0.5">
                                                <span className="truncate text-sm font-medium">{group.name}</span>
                                                <span className="text-muted-foreground text-xs">
                                                    {group.members.length} members ·{" "}
                                                    {schools.find((school) => school.id === group.school_id)?.name ?? "All schools"}
                                                </span>
                                            </div>
                                            <Button size="sm" variant="outline" onClick={() => openGroup(group)}>
                                                Edit
                                            </Button>
                                        </div>
                                    ))
                                )}
                            </div>
                        </FramePanel>
                    </Frame>
                </div>
                {selected ? (
                    <FeatureInspector
                        key={`${selected.key}:${selected.policy.revision}:${selected.assignment_count}`}
                        feature={selected}
                        roles={roles}
                        schools={schools}
                        groups={groups}
                    />
                ) : (
                    <p className="text-muted-foreground text-sm">Select a feature to manage its release.</p>
                )}
            </div>
            <Frame spacing="default">
                <FramePanel>
                    <FrameHeader>
                        <FrameTitle>Recent changes</FrameTitle>
                        <FrameDescription>Policy and cohort changes recorded locally</FrameDescription>
                    </FrameHeader>
                    <div className="flex flex-col gap-3">
                        {audit.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No managed policy changes yet. Saved changes appear here.</p>
                        ) : (
                            audit.map((entry) => (
                                <div key={entry.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span>
                                        {entry.action.replaceAll("_", " ")}{" "}
                                        <span className="text-muted-foreground">{entry.feature_key ?? "Beta group"}</span>
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        {entry.actor ?? "Removed user"} · {entry.created_at}
                                    </span>
                                </div>
                            ))
                        )}
                    </div>
                </FramePanel>
            </Frame>
            <Dialog open={groupDialog} onOpenChange={setGroupDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{groupForm.data.id ? "Edit beta group" : "New beta group"}</DialogTitle>
                        <DialogDescription>Membership grants no permissions on its own.</DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            groupForm.post(portalFeatureUrl(saveGroup.url()), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setGroupDialog(false);
                                    toast.success("Beta group saved");
                                },
                            });
                        }}
                        className="flex flex-col gap-4"
                    >
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="group-name">Group name</Label>
                            <Input id="group-name" value={groupForm.data.name} onChange={(event) => groupForm.setData("name", event.target.value)} />
                            {groupForm.errors.name && (
                                <p role="alert" className="text-destructive text-xs">
                                    {groupForm.errors.name}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="group-description">Description</Label>
                            <Input
                                id="group-description"
                                value={groupForm.data.description}
                                onChange={(event) => groupForm.setData("description", event.target.value)}
                            />
                        </div>
                        <Select
                            value={String(groupForm.data.school_id ?? "all")}
                            onValueChange={(value) => groupForm.setData("school_id", value === "all" ? null : Number(value))}
                        >
                            <SelectTrigger aria-label="Group school">
                                <SelectValue placeholder="School" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All schools</SelectItem>
                                {schools.map((school) => (
                                    <SelectItem key={school.id} value={String(school.id)}>
                                        {school.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <UserPicker
                            label="Add member"
                            onSelect={(user) => {
                                if (members.some((member) => member.id === user.id)) return;
                                const next = [...members, user];
                                setMembers(next);
                                groupForm.setData(
                                    "user_ids",
                                    next.map((member) => member.id),
                                );
                            }}
                        />
                        <div className="flex max-h-40 flex-col gap-1 overflow-y-auto">
                            {members.map((user) => (
                                <div key={user.id} className="flex items-center justify-between gap-2 text-sm">
                                    <span className="truncate">{user.name}</span>
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        aria-label={`Remove ${user.name}`}
                                        onClick={() => {
                                            const next = members.filter((member) => member.id !== user.id);
                                            setMembers(next);
                                            groupForm.setData(
                                                "user_ids",
                                                next.map((member) => member.id),
                                            );
                                        }}
                                    >
                                        <X />
                                    </Button>
                                </div>
                            ))}
                        </div>
                        {groupForm.errors.user_ids && (
                            <p role="alert" className="text-destructive text-xs">
                                {groupForm.errors.user_ids}
                            </p>
                        )}
                        <DialogFooter>
                            <Button variant="outline" type="button" onClick={() => setGroupDialog(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={groupForm.processing}>
                                {groupForm.processing ? "Saving…" : "Save group"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
