import { Response } from "@/components/ui/response";
import { Brain } from "lucide-react";
import * as React from "react";
import { AnalyticsChartRenderer, ChartArtifact } from "./analytics-chart-renderer";
import { DocumentArtifact, DocumentDownloadCard } from "./document-download-card";

interface ChatMessageFormatterProps {
    content: string;
    reasoning?: string;
}

/**
 * Enhanced Chat Message Formatter using ElevenLabs UI Response component
 * for reliable streaming markdown rendering, while seamlessly extracting and
 * rendering interactive Recharts artifacts and downloadable documents.
 *
 * @see https://ui.elevenlabs.io/docs/components/response
 */
export function ChatMessageFormatter({ content, reasoning }: ChatMessageFormatterProps) {
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
        <div className="space-y-2 text-sm text-foreground">
            {reasoning && (
                <details className="rounded-xl border border-border/60 bg-muted/20 px-3 py-2 text-xs group">
                    <summary className="cursor-pointer font-medium text-muted-foreground hover:text-foreground flex items-center gap-1.5 select-none transition-colors">
                        <Brain className="size-3.5 text-indigo-500 shrink-0" />
                        <span>Reasoning & Thought Process</span>
                        <span className="text-[10px] opacity-60 ml-auto group-open:hidden">Click to expand</span>
                    </summary>
                    <div className="mt-2 text-[11px] leading-relaxed text-muted-foreground/90 whitespace-pre-wrap font-mono border-t border-border/40 pt-2 max-h-48 overflow-y-auto">
                        {reasoning}
                    </div>
                </details>
            )}

            {parsedBlocks}
        </div>
    );
}

export default ChatMessageFormatter;
