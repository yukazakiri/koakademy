"use client";

import { CodeBlock } from "@/components/agents/code-block";
import { ThinkingShimmer } from "@/components/agents/loading-states/thinking-shimmer";
import {
    StreamingResponse,
    type StreamingResponseFeedback,
} from "@/components/agents/streaming-response";
import { Button } from "@/components/ui/button";
import { Response } from "@/components/ui/response";
import { cn } from "@/lib/utils";
import {
    BookOpen,
    Brain,
    Check,
    ChevronDown,
    Copy,
    Download,
    FileText,
    Link2,
    Loader2,
    ShieldAlert,
    TriangleAlert,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import {
    AnalyticsChartRenderer,
    chartArtifactKey,
    parseChartArtifact,
} from "./analytics-chart-renderer";
import {
    DocumentDownloadCard,
    parseDocumentArtifact,
    readArtifactBlob,
} from "./document-download-card";
import type { CitationSource, ToolInvocation } from "./use-ai-chat";

interface ChatMessageFormatterProps {
    content: string;
    reasoning?: string;
    toolCalls?: ToolInvocation[];
    sources?: CitationSource[];
    isStreaming?: boolean;
    onRetry?: () => void;
    feedback?: StreamingResponseFeedback;
    onFeedbackChange?: (feedback: StreamingResponseFeedback) => void;
    className?: string;
}

function formatToolIdentifier(name: string): string {
    return name
        .replace(/Tool$/, "")
        .replace(/([a-z0-9])([A-Z])/g, "$1_$2")
        .replace(/[-\s]+/g, "_")
        .toLowerCase();
}

function getToolStatus(tool: ToolInvocation): "running" | "success" | "failed" | "denied" {
    if (tool.state === "input-streaming" || tool.state === "input-available") {
        return "running";
    }

    let outputObj: Record<string, unknown> | null = null;
    if (typeof tool.output === "object" && tool.output !== null) {
        outputObj = tool.output as Record<string, unknown>;
    } else if (typeof tool.output === "string") {
        try {
            outputObj = JSON.parse(tool.output);
        } catch {
            outputObj = null;
        }
    }

    const errorStr = (tool.errorText || "").toLowerCase();
    const isDenied =
        Boolean(outputObj?.denied) ||
        errorStr.includes("denied") ||
        errorStr.includes("not permitted") ||
        errorStr.includes("unauthorized") ||
        errorStr.includes("permission");

    if (isDenied) {
        return "denied";
    }

    if (
        tool.state === "output-error" ||
        Boolean(outputObj?.error) ||
        (tool.errorText && tool.errorText.trim().length > 0)
    ) {
        return "failed";
    }

    return "success";
}

/**
 * Minimalist Tool Call matching user design:
 * ↻ lookup_plans ⌄ (running)
 * ✓ lookup_plans ⌄ (success)
 * ⚠ lookup_plans · failed ⌄ (failed)
 * ⛨ lookup_plans · denied ⌄ (denied)
 */
function MinimalistToolCall({ tool }: { tool: ToolInvocation }) {
    const [isOpen, setIsOpen] = React.useState(false);
    const [copied, setCopied] = React.useState(false);
    const status = getToolStatus(tool);
    const identifier = formatToolIdentifier(tool.toolName);

    const serializedOutput = React.useMemo(() => {
        if (typeof tool.output === "object" && tool.output !== null) {
            return JSON.stringify(tool.output, null, 2);
        }
        return (
            tool.output ||
            tool.errorText ||
            (tool.input ? JSON.stringify(tool.input, null, 2) : "")
        );
    }, [tool.output, tool.errorText, tool.input]);

    const handleCopy = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (!serializedOutput) return;
        navigator.clipboard.writeText(serializedOutput);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
        toast.success("Output copied");
    };

    return (
        <div className="font-mono text-xs select-none">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className={cn(
                    "inline-flex items-center gap-2 cursor-pointer transition-colors py-0.5 group",
                    status === "failed" && "text-red-400 hover:text-red-300",
                    status === "denied" && "text-amber-400 hover:text-amber-300",
                    (status === "running" || status === "success") &&
                        "text-neutral-400 hover:text-neutral-200"
                )}
            >
                {status === "running" && (
                    <Loader2 className="size-3.5 animate-spin text-neutral-400 shrink-0" />
                )}
                {status === "success" && (
                    <Check className="size-3.5 text-neutral-400 shrink-0" />
                )}
                {status === "failed" && (
                    <TriangleAlert className="size-3.5 text-red-500 shrink-0" />
                )}
                {status === "denied" && (
                    <ShieldAlert className="size-3.5 text-amber-500 shrink-0" />
                )}

                <span>
                    {identifier}
                    {status === "failed" && " · failed"}
                    {status === "denied" && " · denied"}
                </span>

                <ChevronDown
                    className={cn(
                        "size-3.5 transition-transform opacity-70 group-hover:opacity-100",
                        status === "failed" && "text-red-400",
                        status === "denied" && "text-amber-400",
                        (status === "running" || status === "success") &&
                            "text-neutral-500",
                        isOpen && "rotate-180"
                    )}
                />
            </button>

            {isOpen && serializedOutput && (
                <div className="relative mt-1 mb-2 ml-5 p-2.5 rounded-lg bg-neutral-950/80 border border-neutral-850 text-[11px] font-mono text-neutral-300 overflow-x-auto max-h-56">
                    <button
                        type="button"
                        onClick={handleCopy}
                        className="absolute top-2 right-2 text-neutral-400 hover:text-white p-1 rounded hover:bg-neutral-800"
                        title="Copy tool payload"
                    >
                        {copied ? (
                            <Check className="size-3 text-emerald-400" />
                        ) : (
                            <Copy className="size-3" />
                        )}
                    </button>
                    <pre className="whitespace-pre-wrap break-all pr-6">
                        {serializedOutput}
                    </pre>
                </div>
            )}
        </div>
    );
}

/**
 * Minimalist Reasoning matching user design:
 * ↻ Reasoning... ⌄ (running)
 * 🧠 Reasoning ⌄ (completed)
 */
function MinimalistReasoning({
    reasoning,
    isStreaming = false,
}: {
    reasoning?: string;
    isStreaming?: boolean;
}) {
    const [isOpen, setIsOpen] = React.useState(false);
    const hasReasoning = Boolean(reasoning && reasoning.trim().length > 0);

    return (
        <div className="text-xs select-none">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="inline-flex items-center gap-2 cursor-pointer text-neutral-400 hover:text-neutral-200 transition-colors py-1 group"
            >
                {isStreaming && !hasReasoning ? (
                    <Loader2 className="size-3.5 animate-spin text-neutral-400 shrink-0" />
                ) : (
                    <Brain className="size-3.5 text-neutral-400 shrink-0" />
                )}

                <span className="font-sans">
                    {isStreaming && !hasReasoning ? "Reasoning..." : "Reasoning"}
                </span>

                <ChevronDown
                    className={cn(
                        "size-3.5 text-neutral-500 transition-transform opacity-70 group-hover:opacity-100",
                        isOpen && "rotate-180"
                    )}
                />
            </button>

            {isOpen && hasReasoning && (
                <div className="pl-5 pr-2 py-1 my-1 text-xs leading-relaxed text-neutral-400 font-sans border-l border-neutral-800/80 whitespace-pre-wrap">
                    {reasoning}
                </div>
            )}
        </div>
    );
}

/**
 * Minimalist Sources matching user design:
 * 📖 3 sources ⌃
 * 🔗 Billing overview stripe.com
 * 🔗 Usage-based pricing, explained paddle.com
 * 📄 Q3 pricing research pricing-research.pdf
 */
function MinimalistSources({ sources }: { sources: CitationSource[] }) {
    const [isOpen, setIsOpen] = React.useState(true);
    if (!sources || sources.length === 0) return null;

    return (
        <div className="text-xs space-y-1.5 select-none my-1">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="inline-flex items-center gap-2 cursor-pointer text-neutral-400 hover:text-neutral-200 transition-colors py-0.5 group"
            >
                <BookOpen className="size-3.5 text-neutral-400 shrink-0" />
                <span className="font-sans font-medium text-neutral-300">
                    {sources.length}{" "}
                    {sources.length === 1 ? "source" : "sources"}
                </span>
                <ChevronDown
                    className={cn(
                        "size-3.5 text-neutral-500 transition-transform opacity-70 group-hover:opacity-100",
                        isOpen && "rotate-180"
                    )}
                />
            </button>

            {isOpen && (
                <div className="space-y-1 pl-1">
                    {sources.map((s, idx) => {
                        let domain = "";
                        try {
                            domain = new URL(s.url).hostname.replace(
                                /^www\./,
                                ""
                            );
                        } catch {
                            domain = "";
                        }
                        const isDoc =
                            s.url.toLowerCase().endsWith(".pdf") ||
                            (s.title &&
                                s.title.toLowerCase().endsWith(".pdf")) ||
                            s.url.includes("document");
                        const Icon = isDoc ? FileText : Link2;

                        return (
                            <div
                                key={idx}
                                className="flex items-center gap-2 text-xs py-0.5"
                            >
                                <Icon className="size-3.5 text-neutral-400 shrink-0" />
                                <a
                                    href={s.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="underline underline-offset-2 text-neutral-200 hover:text-white transition-colors truncate max-w-sm"
                                    title={s.title || s.url}
                                >
                                    {s.title || domain || "Source"}
                                </a>
                                {domain && !isDoc && (
                                    <span className="text-neutral-500 text-xs shrink-0">
                                        {domain}
                                    </span>
                                )}
                                {isDoc && (
                                    <span className="text-neutral-500 text-xs shrink-0 font-mono">
                                        {domain ||
                                            (s.title &&
                                            s.title.endsWith(".pdf")
                                                ? s.title
                                                : "document.pdf")}
                                    </span>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function normalizeCodeLang(
    lang?: string
): "bash" | "diff" | "json" | "text" | "tsx" | "typescript" {
    if (!lang) return "text";
    const lower = lang.toLowerCase().trim();
    if (["json", "jsonc"].includes(lower)) return "json";
    if (["bash", "sh", "zsh", "shell"].includes(lower)) return "bash";
    if (["diff", "patch"].includes(lower)) return "diff";
    if (["tsx", "jsx"].includes(lower)) return "tsx";
    if (
        [
            "typescript",
            "ts",
            "javascript",
            "js",
            "php",
            "sql",
            "html",
            "css",
            "python",
            "py",
        ].includes(lower)
    ) {
        return "typescript";
    }
    return "text";
}

/**
 * Chat Message Formatter using @beui/chat-app design system:
 * - StreamingResponse with status, feedback thumbs, and sources disclosure
 * - Shiki-highlighted CodeBlock with copy, line numbers, and filename
 * - AgentActivity and ThinkingShimmer for thought processes
 * - ToolResult for structured tool execution transparency
 * - Interactive AnalyticsChartRenderer & DocumentDownloadCard artifacts
 */
export function ChatMessageFormatter({
    content,
    reasoning,
    toolCalls,
    sources,
    isStreaming = false,
    onRetry,
    feedback,
    onFeedbackChange,
    className,
}: ChatMessageFormatterProps) {
    const trimmedContent = (content || "").trim();
    const [exportingPdf, setExportingPdf] = React.useState(false);

    // Parsing owns its sets; consumers only read them and deduplicate in fresh local sets.
    const {
        blocks: parsedBlocks,
        renderedDocIds,
        renderedChartKeys,
    } = React.useMemo(() => {
        const docIds = new Set<string>();
        const chartKeys = new Set<string>();
        const blocks: React.ReactNode[] = [];

        const appendArtifact = (value: unknown, key: string, explicitType?: string): boolean => {
            const chart = explicitType !== "document" ? parseChartArtifact(value) : null;
            if (chart) {
                const artifactKey = chartArtifactKey(chart);
                if (!chartKeys.has(artifactKey)) {
                    chartKeys.add(artifactKey);
                    blocks.push(<AnalyticsChartRenderer key={key} chart={chart} />);
                }
                return true;
            }
            const document = explicitType !== "chart" ? parseDocumentArtifact(value) : null;
            if (document) {
                if (!docIds.has(document.document_id)) {
                    docIds.add(document.document_id);
                    blocks.push(<DocumentDownloadCard key={key} document={document} />);
                }
                return true;
            }
            return false;
        };

        if (!trimmedContent) {
            return { blocks, renderedDocIds: docIds, renderedChartKeys: chartKeys };
        }

        const codeBlockRegex =
            /```(?:json:(chart|document)|(chart|document)|json)?\s*([\s\S]*?)```/g;
        let lastIndex = 0;
        let match: RegExpExecArray | null;

        while ((match = codeBlockRegex.exec(content)) !== null) {
            const blockStart = match.index;
            if (blockStart > lastIndex) {
                const textChunk = content.substring(lastIndex, blockStart).trim();
                if (textChunk) {
                    blocks.push(<Response key={`text_${lastIndex}`} className="text-sm leading-relaxed">{textChunk}</Response>);
                }
            }

            const explicitType = match[1] || match[2];
            const body = match[3].trim();
            let handled = false;
            try {
                handled = appendArtifact(JSON.parse(body), `artifact_${blockStart}`, explicitType);
            } catch {
                // Invalid JSON remains visible as a standard code block.
            }

            if (!handled) {
                const rawLang = explicitType || match[0].match(/^```(\w+)/)?.[1] || "typescript";
                const codeLang = normalizeCodeLang(rawLang);
                const codeTitle = body.match(/^(?:\/\/\s*|#\s*)?(?:filename|file|title):\s*([^\n]+)/i)?.[1]?.trim();
                blocks.push(
                    <div key={`code_${blockStart}`} className="not-prose my-2.5 w-full">
                        <CodeBlock
                            code={body}
                            language={codeLang}
                            filename={codeTitle || `${codeLang} snippet`}
                            status={isStreaming ? "streaming" : "complete"}
                            showLineNumbers={body.split("\n").length > 1}
                        />
                    </div>
                );
            }
            lastIndex = codeBlockRegex.lastIndex;
        }

        const tail = content.substring(lastIndex).trim();
        if (tail) {
            let handled = false;
            try {
                handled = appendArtifact(JSON.parse(tail), `artifact_tail_${lastIndex}`);
            } catch {
                // Plain text and malformed artifacts remain visible as prose.
            }
            if (!handled) {
                blocks.push(<Response key={`tail_${lastIndex}`} className="text-sm leading-relaxed">{tail}</Response>);
            }
        }

        return { blocks, renderedDocIds: docIds, renderedChartKeys: chartKeys };
    }, [content, trimmedContent, isStreaming]);

    const toolArtifacts = React.useMemo(() => {
        const items: React.ReactNode[] = [];
        const docIds = new Set(renderedDocIds);
        const chartKeys = new Set(renderedChartKeys);

        for (const tool of toolCalls ?? []) {
            if (tool.state !== "output-available" || !tool.output) continue;
            let output: unknown = tool.output;
            if (typeof output === "string") {
                try {
                    output = JSON.parse(output);
                } catch {
                    continue;
                }
            }

            const document = parseDocumentArtifact(output);
            if (document) {
                if (!docIds.has(document.document_id)) {
                    docIds.add(document.document_id);
                    items.push(<DocumentDownloadCard key={`tool_doc_${tool.id}`} document={document} />);
                }
                continue;
            }
            const chart = parseChartArtifact(output);
            if (chart) {
                const key = chartArtifactKey(chart);
                if (!chartKeys.has(key)) {
                    chartKeys.add(key);
                    items.push(<AnalyticsChartRenderer key={`tool_chart_${tool.id}`} chart={chart} />);
                }
            }
        }

        return items;
    }, [toolCalls, renderedDocIds, renderedChartKeys]);

    // PDF on-demand export for formal reports
    const canExportPdf = React.useMemo(() => {
        if (isStreaming || !content || content.length < 40) return false;
        if (renderedDocIds.size > 0 || toolArtifacts.length > 0) return false;
        const hasHeaders = /^#{1,4}\s+/m.test(content);
        const hasReportKeywords =
            /(?:memo|report|circular|summary|curriculum|clearance|policy|assessment|transcript)/i.test(
                content
            );
        return hasHeaders || hasReportKeywords;
    }, [isStreaming, content, renderedDocIds.size, toolArtifacts.length]);

    const handleExportMessageAsPdf = async () => {
        if (!content || exportingPdf) return;
        setExportingPdf(true);

        try {
            const headingMatch = content.match(/^#+\s*(.+)$/m);
            const title = headingMatch
                ? headingMatch[1].trim()
                : "Administrative_Report";

            const res = await fetch("/administrators/ai/export-document", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN":
                        (
                            window.document.querySelector(
                                'meta[name="csrf-token"]'
                            ) as HTMLMetaElement
                        )?.content || "",
                },
                body: JSON.stringify({
                    title,
                    format: "pdf",
                    content,
                }),
            });

            if (res.ok) {
                const blob = await readArtifactBlob(res, "pdf");
                const blobUrl = window.URL.createObjectURL(blob);
                const a = window.document.createElement("a");
                a.href = blobUrl;
                a.download = `${title.replace(/[^\w-]/g, "_")}.pdf`;
                window.document.body.appendChild(a);
                a.click();
                window.document.body.removeChild(a);
                window.URL.revokeObjectURL(blobUrl);
                toast.success(`Downloaded "${title}.pdf"`);
            } else {
                throw new Error("Failed to export PDF.");
            }
        } catch {
            toast.error("Failed to export PDF. Please try again.");
        } finally {
            setExportingPdf(false);
        }
    };

    const hasContent = parsedBlocks.length > 0;
    const hasReasoning = Boolean(reasoning && reasoning.trim());
    const hasTools = Boolean(toolCalls && toolCalls.length > 0);
    const isEmptyCompleted =
        !isStreaming &&
        !hasContent &&
        !hasReasoning &&
        !hasTools &&
        toolArtifacts.length === 0;

    return (
        <div className={cn("space-y-3 text-sm text-foreground", className)}>
            {/* Fallback for empty completed turn */}
            {isEmptyCompleted && (
                <div className="flex items-start gap-2.5 rounded-2xl border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive">
                    <div className="shrink-0 pt-0.5">⚠️</div>
                    <div>
                        <p className="font-semibold">
                            Assistant stopped without generating text.
                        </p>
                        <p className="text-destructive/90 mt-0.5">
                            Request preserved — tap Retry or change model.
                        </p>
                    </div>
                </div>
            )}

            {/* Minimalist Sources matching Image 3 */}
            {sources && sources.length > 0 && (
                <MinimalistSources sources={sources} />
            )}

            {/* Minimalist Reasoning matching Image 2 */}
            {(hasReasoning || (isStreaming && !hasContent && !hasTools)) && (
                <MinimalistReasoning
                    reasoning={reasoning}
                    isStreaming={isStreaming && !hasContent}
                />
            )}

            {/* Minimalist Tool Invocations matching Image 1 */}
            {hasTools && (
                <div className="space-y-1 my-1">
                    {toolCalls!.map((tool) => (
                        <MinimalistToolCall key={tool.id} tool={tool} />
                    ))}
                </div>
            )}

            {/* Streaming Text Output with StreamingResponse Container */}
            {hasContent ? (
                <StreamingResponse
                    status={isStreaming ? "streaming" : "complete"}
                    copyText={content}
                    sources={[]}
                    onRetry={onRetry}
                    feedback={feedback}
                    onFeedbackChange={onFeedbackChange}
                    className="w-full"
                >
                    <div className="space-y-2.5">{parsedBlocks}</div>
                </StreamingResponse>
            ) : isStreaming && !hasReasoning && !hasTools ? (
                /* Animated shimmer while waiting for first token */
                <div className="flex items-center gap-2 py-1 text-xs text-muted-foreground">
                    <ThinkingShimmer duration={1.6}>
                        Analyzing campus data and formulating response…
                    </ThinkingShimmer>
                </div>
            ) : null}

            {/* Extracted Artifacts (Charts & Documents) */}
            {toolArtifacts.length > 0 && (
                <div className="space-y-2 pt-1">{toolArtifacts}</div>
            )}

            {/* 1-Click Action to Download Report as PDF */}
            {canExportPdf && (
                <div className="flex items-center gap-2 pt-1">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={handleExportMessageAsPdf}
                        disabled={exportingPdf}
                        className="h-8 rounded-xl border-rose-500/30 text-xs font-medium text-rose-600 hover:bg-rose-500/10 dark:text-rose-400 gap-1.5"
                    >
                        {exportingPdf ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                            <FileText className="size-3.5 text-rose-500" />
                        )}
                        <span>
                            {exportingPdf
                                ? "Generating PDF…"
                                : "Download as PDF"}
                        </span>
                        <Download className="ml-0.5 size-3 opacity-60" />
                    </Button>
                </div>
            )}
        </div>
    );
}

export default ChatMessageFormatter;
