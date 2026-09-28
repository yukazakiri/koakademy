import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { router } from "@inertiajs/react";
import axios from "axios";
import { AlertTriangle, CheckCircle2, Globe, Loader2, Plug, Plus, RefreshCw, Save, Trash2 } from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";

import type { McpDiscoveredTool, McpServerConfig } from "./types";

const AGENT_KEYS = [
    { key: "admin_executive", label: "Executive Copilot" },
    { key: "registrar_auditor", label: "Registrar Auditor" },
    { key: "bursar_finance", label: "Bursar Finance" },
    { key: "campus_support", label: "Campus Support" },
] as const;

interface Draft {
    name: string;
    description: string;
    transport: "web" | "local";
    url: string;
    command: string;
    command_arguments: string;
    auth_type: "none" | "bearer" | "oauth";
    token: string;
    enabled_tools: string[];
    allowed_agents: string[];
    is_active: boolean;
    timeout_seconds: number;
    cache_ttl_seconds: number;
}

const emptyDraft = (): Draft => ({
    name: "",
    description: "",
    transport: "web",
    url: "",
    command: "",
    command_arguments: "",
    auth_type: "none",
    token: "",
    enabled_tools: [],
    allowed_agents: [],
    is_active: true,
    timeout_seconds: 15,
    cache_ttl_seconds: 300,
});

function draftFrom(server: McpServerConfig): Draft {
    return {
        name: server.name,
        description: server.description ?? "",
        transport: server.transport,
        url: server.url ?? "",
        command: server.command ?? "",
        command_arguments: (server.command_arguments ?? []).join(" "),
        auth_type: server.auth_type,
        token: "",
        enabled_tools: [...server.enabled_tools],
        allowed_agents: [...server.allowed_agents],
        is_active: server.is_active,
        timeout_seconds: server.timeout_seconds,
        cache_ttl_seconds: server.cache_ttl_seconds,
    };
}

export default function McpServersSection({ servers, canUpdate }: { servers: McpServerConfig[]; canUpdate: boolean }) {
    const [editingId, setEditingId] = React.useState<number | "new" | null>(null);
    const [draft, setDraft] = React.useState<Draft>(emptyDraft);
    const [discovered, setDiscovered] = React.useState<McpDiscoveredTool[]>([]);
    const [busy, setBusy] = React.useState(false);
    const [discovering, setDiscovering] = React.useState(false);

    const startNew = () => {
        setDraft(emptyDraft());
        setDiscovered([]);
        setEditingId("new");
    };

    const startEdit = (server: McpServerConfig) => {
        setDraft(draftFrom(server));
        setDiscovered([]);
        setEditingId(server.id);
    };

    const patch = (changes: Partial<Draft>) => setDraft((current) => ({ ...current, ...changes }));

    const toggleTool = (tool: string) =>
        setDraft((current) => ({
            ...current,
            enabled_tools: current.enabled_tools.includes(tool)
                ? current.enabled_tools.filter((name) => name !== tool)
                : [...current.enabled_tools, tool],
        }));

    const toggleAgent = (agent: string) =>
        setDraft((current) => ({
            ...current,
            allowed_agents: current.allowed_agents.includes(agent)
                ? current.allowed_agents.filter((name) => name !== agent)
                : [...current.allowed_agents, agent],
        }));

    /**
     * Ask the server what it offers. Failures are shown inline rather than
     * thrown away, because an unreachable integration is the most common
     * configuration problem and the reason matters.
     */
    const discover = async () => {
        if (editingId === "new") {
            toast.error("Save the server first, then discover its tools.");

            return;
        }

        setDiscovering(true);

        try {
            const response = await axios.post(
                route("administrators.system-management.ai.mcp-servers.discover", editingId),
            );
            setDiscovered(response.data.tools ?? []);
            toast.success(response.data.message);
        } catch (error: any) {
            setDiscovered([]);
            toast.error(error?.response?.data?.message ?? "Could not reach that server.");
        } finally {
            setDiscovering(false);
        }
    };

    const save = async () => {
        setBusy(true);

        const payload = {
            name: draft.name,
            description: draft.description || null,
            transport: draft.transport,
            url: draft.transport === "web" ? draft.url : null,
            command: draft.transport === "local" ? draft.command : null,
            command_arguments:
                draft.transport === "local"
                    ? draft.command_arguments.split(/\s+/).filter(Boolean)
                    : [],
            auth_type: draft.auth_type,
            token: draft.token || null,
            enabled_tools: draft.enabled_tools,
            allowed_agents: draft.allowed_agents,
            is_active: draft.is_active,
            timeout_seconds: Number(draft.timeout_seconds),
            cache_ttl_seconds: Number(draft.cache_ttl_seconds),
        };

        try {
            if (editingId === "new") {
                await axios.post(route("administrators.system-management.ai.mcp-servers.store"), payload);
            } else {
                await axios.put(route("administrators.system-management.ai.mcp-servers.update", editingId), payload);
            }
            toast.success("MCP server saved.");
            setEditingId(null);
            router.reload({ only: ["mcp_servers"] });
        } catch (error: any) {
            toast.error(error?.response?.data?.message ?? "Could not save the server.");
        } finally {
            setBusy(false);
        }
    };

    const destroy = async (server: McpServerConfig) => {
        if (!confirm(`Remove the MCP server "${server.name}"?`)) return;
        try {
            await axios.delete(route("administrators.system-management.ai.mcp-servers.destroy", server.id));
            toast.success("MCP server removed.");
            router.reload({ only: ["mcp_servers"] });
        } catch {
            toast.error("Could not remove the server.");
        }
    };

    return (
        <Card className="border-sky-500/20">
            <CardHeader className="pb-4">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base font-semibold">
                            <Plug className="size-4 text-sky-500" />
                            External MCP Servers
                        </CardTitle>
                        <CardDescription>
                            Connect third-party Model Context Protocol servers so the AI assistants can use them. A server
                            exposes nothing until you tick its tools and assign it to an assistant.
                        </CardDescription>
                    </div>
                    {canUpdate && (
                        <Button type="button" size="sm" variant="outline" onClick={startNew} className="gap-2">
                            <Plus className="size-4" />
                            Add server
                        </Button>
                    )}
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>These servers receive whatever the AI sends them</AlertTitle>
                    <AlertDescription>
                        An enabled tool can be handed student names, numbers, LRNs, grades, or financial figures. Only
                        connect servers you trust, enable the fewest tools that work, and prefer aggregate questions over
                        per-student lookups. Tools that a server does not mark read-only always require an approval click
                        before they run.
                    </AlertDescription>
                </Alert>

                {servers.length === 0 && editingId === null && (
                    <p className="text-sm text-muted-foreground">
                        No external MCP servers are configured. The assistants currently use only KoAkademy's own tools.
                    </p>
                )}

                {servers.map((server) => (
                    <div key={server.id} className="rounded-lg border p-4 space-y-2">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <Globe className="size-4 text-muted-foreground" />
                                <span className="font-medium">{server.name}</span>
                                <Badge variant={server.is_active ? "default" : "secondary"}>
                                    {server.is_active ? "Active" : "Disabled"}
                                </Badge>
                                {server.last_connected_at && <CheckCircle2 className="size-4 text-emerald-500" />}
                            </div>
                            {canUpdate && (
                                <div className="flex items-center gap-2">
                                    <Button type="button" size="sm" variant="outline" onClick={() => startEdit(server)}>
                                        Edit
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => destroy(server)}
                                        aria-label={`Remove ${server.name}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            )}
                        </div>

                        <p className="text-xs text-muted-foreground">
                            {server.description || server.url || server.command}
                        </p>

                        <div className="flex flex-wrap gap-2 text-xs">
                            <Badge variant="outline">{server.enabled_tools.length} tools enabled</Badge>
                            <Badge variant="outline">
                                {server.allowed_agents.length === 0
                                    ? "No assistant assigned"
                                    : server.allowed_agents
                                          .map((key) => AGENT_KEYS.find((agent) => agent.key === key)?.label ?? key)
                                          .join(", ")}
                            </Badge>
                            {server.exposes_nothing && (
                                <Badge variant="secondary">Exposes nothing until tools are ticked</Badge>
                            )}
                            {server.last_error && <Badge variant="destructive">Last error recorded</Badge>}
                        </div>
                    </div>
                ))}

                {editingId !== null && (
                    <div className="rounded-lg border border-sky-500/40 p-4 space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="mcp-name">Name</Label>
                                <Input
                                    id="mcp-name"
                                    value={draft.name}
                                    onChange={(event) => patch({ name: event.target.value })}
                                    placeholder="github"
                                />
                                <p className="text-[11px] text-muted-foreground">
                                    Lowercase letters, numbers, hyphens and underscores. Also used as the MCP client key.
                                </p>
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="mcp-transport">Transport</Label>
                                <Select
                                    value={draft.transport}
                                    onValueChange={(value) => patch({ transport: value as Draft["transport"] })}
                                >
                                    <SelectTrigger id="mcp-transport">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="web">Remote (HTTP)</SelectItem>
                                        <SelectItem value="local">Local command (stdio)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="mcp-description">Description</Label>
                            <Textarea
                                id="mcp-description"
                                value={draft.description}
                                onChange={(event) => patch({ description: event.target.value })}
                                rows={2}
                            />
                        </div>

                        {draft.transport === "web" ? (
                            <div className="space-y-1.5">
                                <Label htmlFor="mcp-url">Server URL</Label>
                                <Input
                                    id="mcp-url"
                                    value={draft.url}
                                    onChange={(event) => patch({ url: event.target.value })}
                                    placeholder="https://mcp.example.com"
                                />
                            </div>
                        ) : (
                            <div className="space-y-1.5">
                                <Label htmlFor="mcp-command">Command and arguments</Label>
                                <Input
                                    id="mcp-command"
                                    value={draft.command}
                                    onChange={(event) => patch({ command: event.target.value })}
                                    placeholder="php"
                                />
                                <Input
                                    aria-label="Command arguments"
                                    value={draft.command_arguments}
                                    onChange={(event) => patch({ command_arguments: event.target.value })}
                                    placeholder="artisan mcp:start"
                                />
                            </div>
                        )}

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="mcp-auth">Authentication</Label>
                                <Select
                                    value={draft.auth_type}
                                    onValueChange={(value) => patch({ auth_type: value as Draft["auth_type"] })}
                                >
                                    <SelectTrigger id="mcp-auth">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None</SelectItem>
                                        <SelectItem value="bearer">Bearer token</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {draft.auth_type === "bearer" && (
                                <div className="space-y-1.5">
                                    <Label htmlFor="mcp-token">Bearer token</Label>
                                    <Input
                                        id="mcp-token"
                                        type="password"
                                        value={draft.token}
                                        onChange={(event) => patch({ token: event.target.value })}
                                        placeholder={editingId === "new" ? "Paste the token" : "Leave blank to keep the stored token"}
                                        autoComplete="off"
                                    />
                                    <p className="text-[11px] text-muted-foreground">
                                        Stored encrypted and never shown again. Leave blank on edit to keep the current
                                        token.
                                    </p>
                                </div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>Assistants that may use this server</Label>
                            <div className="flex flex-wrap gap-3">
                                {AGENT_KEYS.map((agent) => (
                                    <label key={agent.key} className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={draft.allowed_agents.includes(agent.key)}
                                            onChange={() => toggleAgent(agent.key)}
                                        />
                                        {agent.label}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label>Tools this server may expose</Label>
                                {editingId !== "new" && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={discover}
                                        disabled={discovering}
                                        className="gap-2"
                                    >
                                        {discovering ? <Loader2 className="size-4 animate-spin" /> : <RefreshCw className="size-4" />}
                                        Discover tools
                                    </Button>
                                )}
                            </div>

                            {editingId === "new" ? (
                                <p className="text-xs text-muted-foreground">
                                    Save the server, then use Discover tools to list what it offers.
                                </p>
                            ) : discovered.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    {draft.enabled_tools.length} tool(s) currently allowed. Discover to review the full list.
                                </p>
                            ) : (
                                <div className="max-h-64 space-y-2 overflow-y-auto rounded border p-3">
                                    {discovered.map((tool) => (
                                        <label key={tool.name} className="flex items-start gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={draft.enabled_tools.includes(tool.name)}
                                                onChange={() => toggleTool(tool.name)}
                                            />
                                            <span>
                                                <span className="font-medium">{tool.name}</span>
                                                {tool.description && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {tool.description}
                                                    </span>
                                                )}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="mcp-timeout">Timeout (seconds)</Label>
                                <Input
                                    id="mcp-timeout"
                                    type="number"
                                    min={1}
                                    max={120}
                                    value={draft.timeout_seconds}
                                    onChange={(event) => patch({ timeout_seconds: Number(event.target.value) })}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="mcp-cache">Tool list cache (seconds)</Label>
                                <Input
                                    id="mcp-cache"
                                    type="number"
                                    min={0}
                                    max={3600}
                                    value={draft.cache_ttl_seconds}
                                    onChange={(event) => patch({ cache_ttl_seconds: Number(event.target.value) })}
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Switch
                                id="mcp-active"
                                checked={draft.is_active}
                                onCheckedChange={(checked) => patch({ is_active: checked })}
                            />
                            <Label htmlFor="mcp-active">Active</Label>
                        </div>

                        {draft.enabled_tools.length === 0 && (
                            <Alert>
                                <AlertTriangle className="size-4" />
                                <AlertDescription>
                                    With no tools ticked this server stays connected but the AI cannot call it. That is the
                                    safe default for a new integration.
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="flex gap-2">
                            <Button type="button" onClick={save} disabled={busy} className="gap-2">
                                {busy ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                                Save server
                            </Button>
                            <Button type="button" variant="outline" onClick={() => setEditingId(null)}>
                                Cancel
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
