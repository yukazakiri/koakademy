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
import { Response } from "@/components/ui/response";
import * as React from "react";
import { AnalyticsChartRenderer, ChartArtifact } from "./analytics-chart-renderer";
import { DocumentArtifact, DocumentDownloadCard } from "./document-download-card";
import type { CitationSource, ToolInvocation } from "./use-ai-chat";

interface ChatMessageFormatterProps {
    content: string;
    reasoning?: string;
    toolCalls?: ToolInvocation[];
    sources?: CitationSource[];
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
}: ChatMessageFormatterProps) {
    const trimmedContent = (content || "").trim();

    const parsedBlocks = React.useMemo(() => {
        if (!trimmedContent) {
            return [];
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
                    blocks.push(
                        <AnalyticsChartRenderer
                            key={`chart_${blockStart}`}
                            chart={parsed as ChartArtifact}
                        />
                    );
                    handled = true;
                } else if (explicitType === "document" || parsed._type === "document_artifact" || parsed.document_id) {
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
                            blocks.push(<AnalyticsChartRenderer key={`chart_tail_${lastIndex}`} chart={parsed} />);
                            return blocks;
                        }
                        if (parsed._type === "document_artifact" || parsed.document_id) {
                            blocks.push(<DocumentDownloadCard key={`doc_tail_${lastIndex}`} document={parsed} />);
                            return blocks;
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
                    return [<AnalyticsChartRenderer key="single_chart" chart={parsed} />];
                }
                if (parsed._type === "document_artifact" || parsed.document_id) {
                    return [<DocumentDownloadCard key="single_doc" document={parsed} />];
                }
            } catch {
                // Fallback to text
            }
        }

        return blocks.length > 0
            ? blocks
            : [
                  <Response key="root_content" className="leading-relaxed text-sm">
                      {content}
                  </Response>,
              ];
    }, [content, trimmedContent]);

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
            ) : !hasReasoning && !hasTools ? (
                /* Inline thinking indicator when message is loading before first token */
                <div className="flex items-center gap-2 py-0.5 text-muted-foreground">
                    <Loader variant="dots" size="sm" className="text-primary" />
                    <span className="text-xs font-medium text-muted-foreground/80 animate-pulse">Thinking...</span>
                </div>
            ) : null}

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
