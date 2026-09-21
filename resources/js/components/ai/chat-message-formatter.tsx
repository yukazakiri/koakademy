import {
    Loader,
    Reasoning,
    ReasoningContent,
    ReasoningTrigger,
    Source,
    SourceContent,
    SourceTrigger,
    Steps,
    StepsContent,
    StepsItem,
    StepsTrigger,
    Tool,
} from "@/components/prompt-kit";
import { Button } from "@/components/ui/button";
import { Response } from "@/components/ui/response";
import { Download, FileText, Loader2 } from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import { AnalyticsChartRenderer, ChartArtifact } from "./analytics-chart-renderer";
import { DocumentArtifact, DocumentDownloadCard } from "./document-download-card";
import type { CitationSource, ToolInvocation } from "./use-ai-chat";

interface ChatMessageFormatterProps {
    content: string;
    reasoning?: string;
    toolCalls?: ToolInvocation[];
    sources?: CitationSource[];
    isStreaming?: boolean;
}

/**
 * Enhanced Chat Message Formatter using:
 * - ElevenLabs UI Response component (streamdown) for reliable streaming markdown rendering
 * - Prompt-Kit Steps & Tool components for tool executions
 * - Prompt-Kit Reasoning component for model thought processes
 * - Prompt-Kit Source component for verifiable citations
 * - Interactive Recharts visualizations & Downloadable Document cards
 *
 * @see https://ui.elevenlabs.io/docs/components/response
 */
export function ChatMessageFormatter({
    content,
    reasoning,
    toolCalls,
    sources,
    isStreaming = false,
}: ChatMessageFormatterProps) {
    const trimmedContent = (content || "").trim();
    const [exportingPdf, setExportingPdf] = React.useState(false);

    const { blocks: parsedBlocks, renderedDocIds, renderedChartKeys } = React.useMemo(() => {
        const docIds = new Set<string>();
        const chartKeys = new Set<string>();

        if (!trimmedContent) {
            return { blocks: [], renderedDocIds: docIds, renderedChartKeys: chartKeys };
        }

        const blocks: React.ReactNode[] = [];
        // Regex matches ```json:chart, ```chart, ```json:document, ```document, ```json, or generic ``` codeblocks
        const codeBlockRegex = /```(?:json:(chart|document)|(chart|document)|json)?\s*([\s\S]*?)```/g;
        let lastIndex = 0;
        let match: RegExpExecArray | null;

        while ((match = codeBlockRegex.exec(content)) !== null) {
            const blockStart = match.index;
            const blockEnd = codeBlockRegex.lastIndex;

            // Render preceding text chunk via Response component
            if (blockStart > lastIndex) {
                const textChunk = content.substring(lastIndex, blockStart).trim();
                if (textChunk) {
                    blocks.push(
                        <Response key={`text_${lastIndex}`} className="leading-relaxed text-sm">
                            {textChunk}
                        </Response>
                    );
                }
            }

            const explicitType = match[1] || match[2];
            const body = match[3].trim();

            let handled = false;

            try {
                const parsed = JSON.parse(body);

                if (explicitType === "chart" || parsed._type === "chart_artifact" || parsed.chart_type) {
                    const key = parsed.title || parsed.chart_type || `chart_${blockStart}`;
                    chartKeys.add(key);
                    blocks.push(
                        <AnalyticsChartRenderer
                            key={`chart_${blockStart}`}
                            chart={parsed as ChartArtifact}
                        />
                    );
                    handled = true;
                } else if (explicitType === "document" || parsed._type === "document_artifact" || parsed.document_id) {
                    if (parsed.document_id) {
                        docIds.add(parsed.document_id);
                    }
                    blocks.push(
                        <DocumentDownloadCard
                            key={`doc_${blockStart}`}
                            document={parsed as DocumentArtifact}
                        />
                    );
                    handled = true;
                }
            } catch {
                // Not valid JSON, keep as standard code block
            }

            if (!handled) {
                blocks.push(
                    <Response key={`code_${blockStart}`} className="leading-relaxed text-sm">
                        {match[0]}
                    </Response>
                );
            }

            lastIndex = blockEnd;
        }

        // Render trailing text chunk
        if (lastIndex < content.length) {
            const tail = content.substring(lastIndex).trim();
            if (tail) {
                // Check if raw tail is a JSON artifact directly
                if (tail.startsWith("{") && tail.endsWith("}")) {
                    try {
                        const parsed = JSON.parse(tail);
                        if (parsed._type === "chart_artifact" || parsed.chart_type) {
                            const key = parsed.title || parsed.chart_type || `chart_tail_${lastIndex}`;
                            chartKeys.add(key);
                            blocks.push(<AnalyticsChartRenderer key={`chart_tail_${lastIndex}`} chart={parsed} />);
                            return { blocks, renderedDocIds: docIds, renderedChartKeys: chartKeys };
                        }
                        if (parsed._type === "document_artifact" || parsed.document_id) {
                            if (parsed.document_id) {
                                docIds.add(parsed.document_id);
                            }
                            blocks.push(<DocumentDownloadCard key={`doc_tail_${lastIndex}`} document={parsed} />);
                            return { blocks, renderedDocIds: docIds, renderedChartKeys: chartKeys };
                        }
                    } catch {
                        // Fallback to text
                    }
                }

                blocks.push(
                    <Response key={`tail_${lastIndex}`} className="leading-relaxed text-sm">
                        {tail}
                    </Response>
                );
            }
        }

        // Check if raw full content is an unbracketed or standalone JSON artifact
        if (blocks.length === 0 && trimmedContent.startsWith("{") && trimmedContent.endsWith("}")) {
            try {
                const parsed = JSON.parse(trimmedContent);
                if (parsed._type === "chart_artifact" || parsed.chart_type) {
                    const key = parsed.title || parsed.chart_type || "single_chart";
                    chartKeys.add(key);
                    return {
                        blocks: [<AnalyticsChartRenderer key="single_chart" chart={parsed} />],
                        renderedDocIds: docIds,
                        renderedChartKeys: chartKeys,
                    };
                }
                if (parsed._type === "document_artifact" || parsed.document_id) {
                    if (parsed.document_id) {
                        docIds.add(parsed.document_id);
                    }
                    return {
                        blocks: [<DocumentDownloadCard key="single_doc" document={parsed} />],
                        renderedDocIds: docIds,
                        renderedChartKeys: chartKeys,
                    };
                }
            } catch {
                // Fallback to text
            }
        }

        const finalBlocks = blocks.length > 0
            ? blocks
            : [
                  <Response key="root_content" className="leading-relaxed text-sm">
                      {content}
                  </Response>,
              ];

        return { blocks: finalBlocks, renderedDocIds: docIds, renderedChartKeys: chartKeys };
    }, [content, trimmedContent]);

    // Extract any document or chart artifacts produced by toolCalls that were not explicitly echoed in the text content
    const toolArtifacts = React.useMemo(() => {
        if (!toolCalls || toolCalls.length === 0) {
            return [];
        }

        const items: React.ReactNode[] = [];

        for (const tool of toolCalls) {
            if (tool.state !== "output-available" || !tool.output) {
                continue;
            }

            let outputObj = tool.output;
            if (typeof outputObj === "string") {
                try {
                    outputObj = JSON.parse(outputObj);
                } catch {
                    continue;
                }
            }

            if (!outputObj || typeof outputObj !== "object") {
                continue;
            }

            // Document artifact check
            if (
                outputObj._type === "document_artifact" ||
                outputObj.document_id ||
                tool.toolName === "GenerateAdministrativeDocumentTool"
            ) {
                const docId = outputObj.document_id;
                if (!docId || !renderedDocIds.has(docId)) {
                    if (docId) renderedDocIds.add(docId);
                    items.push(
                        <DocumentDownloadCard
                            key={`tool_doc_${tool.id}_${docId || "doc"}`}
                            document={outputObj as DocumentArtifact}
                        />
                    );
                }
            }
            // Chart artifact check
            else if (
                outputObj._type === "chart_artifact" ||
                outputObj.chart_type ||
                tool.toolName === "GenerateAnalyticsChartTool"
            ) {
                const key = outputObj.title || tool.id;
                if (!renderedChartKeys.has(key)) {
                    renderedChartKeys.add(key);
                    items.push(
                        <AnalyticsChartRenderer
                            key={`tool_chart_${tool.id}`}
                            chart={outputObj as ChartArtifact}
                        />
                    );
                }
            }
        }

        return items;
    }, [toolCalls, renderedDocIds, renderedChartKeys]);

    // Show on-demand export when content contains structured report/memo text and no document card is rendered yet
    const canExportPdf = React.useMemo(() => {
        if (isStreaming || !content || content.length < 40) return false;
        if (renderedDocIds.size > 0 || toolArtifacts.length > 0) return false;

        const hasHeaders = /^#{1,4}\s+/m.test(content);
        const hasReportKeywords = /(?:memo|report|circular|summary|curriculum|clearance|policy|assessment|transcript)/i.test(content);

        return hasHeaders || hasReportKeywords;
    }, [isStreaming, content, renderedDocIds.size, toolArtifacts.length]);

    const handleExportMessageAsPdf = async () => {
        if (!content || exportingPdf) return;
        setExportingPdf(true);

        try {
            const headingMatch = content.match(/^#+\s*(.+)$/m);
            const title = headingMatch ? headingMatch[1].trim() : "Administrative_Report";

            const res = await fetch("/administrators/ai/export-document", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN":
                        (window.document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
                },
                body: JSON.stringify({
                    title,
                    format: "pdf",
                    content,
                }),
            });

            if (res.ok) {
                const blob = await res.blob();
                const blobUrl = window.URL.createObjectURL(blob);
                const a = window.document.createElement("a");
                a.href = blobUrl;
                a.download = `${title.replace(/[^\w\-]/g, "_")}.pdf`;
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

    return (
        <div className="space-y-3 text-sm text-foreground">
            {/* Prompt-Kit Reasoning Thought Process */}
            {hasReasoning && (
                <Reasoning className="border border-border/60 bg-muted/20 rounded-xl overflow-hidden p-2.5">
                    <ReasoningTrigger className="text-xs font-medium text-muted-foreground hover:text-foreground">
                        Thought Process & Reasoning
                    </ReasoningTrigger>
                    <ReasoningContent className="mt-2 text-xs font-mono text-muted-foreground/90 whitespace-pre-wrap border-t border-border/40 pt-2 max-h-48 overflow-y-auto">
                        {reasoning}
                    </ReasoningContent>
                </Reasoning>
            )}

            {/* Prompt-Kit Steps for Tool Invocations */}
            {hasTools && (
                <Steps defaultOpen={false} className="border border-border/60 bg-muted/20 rounded-xl overflow-hidden p-2.5">
                    <StepsTrigger
                        leftIcon={<Loader variant="dots" size="sm" className="text-primary" />}
                        className="text-xs font-medium text-muted-foreground hover:text-foreground"
                    >
                        <span>Executed {toolCalls!.length} {toolCalls!.length === 1 ? "tool step" : "tool steps"}</span>
                    </StepsTrigger>
                    <StepsContent className="mt-2 space-y-1.5 border-t border-border/40 pt-2">
                        {toolCalls!.map((tool) => (
                            <StepsItem key={tool.id}>
                                <Tool
                                    toolPart={{
                                        type: tool.toolName,
                                        state: tool.state,
                                        input: tool.input,
                                        output: typeof tool.output === "object" ? tool.output : tool.output ? { result: tool.output } : undefined,
                                        toolCallId: tool.id,
                                        errorText: tool.errorText,
                                    }}
                                />
                            </StepsItem>
                        ))}
                    </StepsContent>
                </Steps>
            )}

            {/* Formatted Markdown and Visual Artifacts */}
            {hasContent ? (
                parsedBlocks
            ) : isStreaming && !hasReasoning && !hasTools ? (
                /* Inline thinking indicator when message is loading before first token */
                <div className="flex items-center gap-2 py-0.5 text-muted-foreground">
                    <Loader variant="dots" size="sm" className="text-primary" />
                    <span className="text-xs font-medium text-muted-foreground/80 animate-pulse">Thinking...</span>
                </div>
            ) : null}

            {/* Visual Artifacts extracted from Tool Invocations (Documents or Charts) */}
            {toolArtifacts.length > 0 && (
                <div className="space-y-2 pt-1">
                    {toolArtifacts}
                </div>
            )}

            {/* 1-Click Action to Download Report as PDF */}
            {canExportPdf && (
                <div className="pt-2 flex items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={handleExportMessageAsPdf}
                        disabled={exportingPdf}
                        className="h-8 text-xs font-medium gap-1.5 border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-500/10 rounded-xl"
                    >
                        {exportingPdf ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                            <FileText className="size-3.5 text-rose-500" />
                        )}
                        <span>{exportingPdf ? "Generating PDF..." : "Download as PDF"}</span>
                        <Download className="size-3 opacity-60 ml-0.5" />
                    </Button>
                </div>
            )}

            {/* Prompt-Kit Verified Sources & Citations */}
            {sources && sources.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5 pt-2 border-t border-border/40">
                    <span className="text-[10.5px] font-semibold text-muted-foreground uppercase tracking-wider mr-1">
                        Sources:
                    </span>
                    {sources.map((s, i) => (
                        <Source key={i} href={s.url}>
                            <SourceTrigger
                                label={s.title}
                                showFavicon={true}
                                className="text-xs h-6 px-2.5 bg-muted/40 hover:bg-muted font-sans"
                            />
                            <SourceContent
                                title={s.title}
                                description={s.url}
                            />
                        </Source>
                    ))}
                </div>
            )}
        </div>
    );
}

export default ChatMessageFormatter;
