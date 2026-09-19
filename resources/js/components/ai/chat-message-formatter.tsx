import {
    Reasoning,
    ReasoningContent,
    ReasoningTrigger,
    Source,
    SourceContent,
    SourceTrigger,
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
 * - ElevenLabs UI Response component for streaming markdown typography
 * - Prompt-Kit Reasoning component for thought processes
 * - Prompt-Kit Tool component for tool execution inspection
 * - Prompt-Kit Source component for verifiable citations
 * - Interactive Recharts visualizations & Downloadable Document cards
 *
 * @see https://ui.elevenlabs.io/docs/components/response
 * @see https://www.prompt-kit.com/docs/
 */
export function ChatMessageFormatter({
    content,
    reasoning,
    toolCalls,
    sources,
}: ChatMessageFormatterProps) {
    const parsedBlocks = React.useMemo(() => {
        if (!content || !content.trim()) {
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

            // Render preceding text chunk via ElevenLabs UI Response component
            if (blockStart > lastIndex) {
                const textChunk = content.substring(lastIndex, blockStart).trim();
                if (textChunk) {
                    blocks.push(
                        <Response key={`text_${lastIndex}`} className="leading-relaxed">
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
                // Render standard code block via Response component
                blocks.push(
                    <Response key={`code_${blockStart}`} className="leading-relaxed">
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
                    <Response key={`tail_${lastIndex}`} className="leading-relaxed">
                        {tail}
                    </Response>
                );
            }
        }

        // Check if raw full content is an unbracketed or standalone JSON artifact
        if (blocks.length === 0 && content.trim().startsWith("{") && content.trim().endsWith("}")) {
            try {
                const parsed = JSON.parse(content.trim());
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
                  <Response key="root_content" className="leading-relaxed">
                      {content}
                  </Response>,
              ];
    }, [content]);

    return (
        <div className="space-y-3 text-sm text-foreground">
            {/* Prompt-Kit Reasoning Thought Process */}
            {reasoning && (
                <Reasoning className="border border-border/60 bg-muted/20 rounded-xl overflow-hidden p-2.5">
                    <ReasoningTrigger className="text-xs font-medium text-muted-foreground hover:text-foreground">
                        Thought Process & Reasoning
                    </ReasoningTrigger>
                    <ReasoningContent className="mt-2 text-xs font-mono text-muted-foreground/90 whitespace-pre-wrap border-t border-border/40 pt-2 max-h-48 overflow-y-auto">
                        {reasoning}
                    </ReasoningContent>
                </Reasoning>
            )}

            {/* Prompt-Kit Tool Invocations */}
            {toolCalls && toolCalls.length > 0 && (
                <div className="space-y-1.5">
                    {toolCalls.map((tool) => (
                        <Tool
                            key={tool.id}
                            toolPart={{
                                type: tool.toolName,
                                state: tool.state,
                                input: tool.input,
                                output: typeof tool.output === "object" ? tool.output : tool.output ? { result: tool.output } : undefined,
                                toolCallId: tool.id,
                                errorText: tool.errorText,
                            }}
                        />
                    ))}
                </div>
            )}

            {/* Formatted Markdown and Visual Artifacts */}
            {parsedBlocks}

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
