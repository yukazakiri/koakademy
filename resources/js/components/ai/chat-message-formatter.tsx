import * as React from "react";
import { AnalyticsChartRenderer, ChartArtifact } from "./analytics-chart-renderer";
import { DocumentArtifact, DocumentDownloadCard } from "./document-download-card";
import { InteractiveTable } from "./interactive-table";

interface ChatMessageFormatterProps {
    content: string;
}

export function ChatMessageFormatter({ content }: ChatMessageFormatterProps) {
    // 1. Detect if entire content or parts contain JSON artifacts
    const parsedBlocks = React.useMemo(() => {
        const blocks: React.ReactNode[] = [];
        let remaining = content;

        // Extract JSON codeblocks: ```json:chart ... ``` or ```json:document ... ```
        const jsonBlockRegex = /```(?:json:(chart|document)|json)\s*([\s\S]*?)```/g;
        let lastIndex = 0;
        let match: RegExpExecArray | null;

        while ((match = jsonBlockRegex.exec(content)) !== null) {
            const blockStart = match.index;
            const blockEnd = jsonBlockRegex.lastIndex;

            // Render text before codeblock
            if (blockStart > lastIndex) {
                const textChunk = content.substring(lastIndex, blockStart);
                blocks.push(renderTextChunk(textChunk, `text_${lastIndex}`));
            }

            const formatType = match[1];
            const jsonText = match[2].trim();

            try {
                const parsed = JSON.parse(jsonText);

                if (formatType === "chart" || parsed._type === "chart_artifact" || parsed.chart_type) {
                    blocks.push(
                        <AnalyticsChartRenderer
                            key={`chart_${blockStart}`}
                            chart={parsed as ChartArtifact}
                        />
                    );
                } else if (formatType === "document" || parsed._type === "document_artifact" || parsed.document_id) {
                    blocks.push(
                        <DocumentDownloadCard
                            key={`doc_${blockStart}`}
                            document={parsed as DocumentArtifact}
                        />
                    );
                } else {
                    blocks.push(
                        <pre key={`code_${blockStart}`} className="p-3 my-2 rounded-lg bg-muted text-[11px] font-mono overflow-x-auto">
                            <code>{jsonText}</code>
                        </pre>
                    );
                }
            } catch {
                blocks.push(
                    <pre key={`code_${blockStart}`} className="p-3 my-2 rounded-lg bg-muted text-[11px] font-mono overflow-x-auto">
                        <code>{jsonText}</code>
                    </pre>
                );
            }

            lastIndex = blockEnd;
        }

        // Render remaining text after last code block
        if (lastIndex < content.length) {
            const tail = content.substring(lastIndex);
            blocks.push(renderTextChunk(tail, `tail_${lastIndex}`));
        }

        // Check if raw text is entirely a JSON artifact
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
                // Ignore and fall back to regular text
            }
        }

        return blocks.length > 0 ? blocks : [renderTextChunk(content, "root")];
    }, [content]);

    return <div className="space-y-2">{parsedBlocks}</div>;
}

function renderTextChunk(text: string, keyPrefix: string): React.ReactNode {
    if (!text.trim()) return null;

    // Check for Markdown table: consecutive lines with pipe '|'
    const lines = text.split("\n");
    const elements: React.ReactNode[] = [];
    let currentParagraph: string[] = [];
    let currentTableLines: string[] = [];

    const flushParagraph = (idx: number) => {
        if (currentParagraph.length > 0) {
            const pText = currentParagraph.join("\n").trim();
            if (pText) {
                elements.push(
                    <p key={`${keyPrefix}_p_${idx}`} className="whitespace-pre-wrap leading-relaxed">
                        {renderInlineFormatting(pText)}
                    </p>
                );
            }
            currentParagraph = [];
        }
    };

    const flushTable = (idx: number) => {
        if (currentTableLines.length >= 2) {
            const [headerLine, separatorLine, ...rowLines] = currentTableLines;
            const headers = headerLine
                .split("|")
                .map((h) => h.trim())
                .filter(Boolean);

            const rows = rowLines
                .filter((l) => l.includes("|"))
                .map((l) =>
                    l
                        .split("|")
                        .map((c) => c.trim())
                        .filter((c, i, arr) => !(i === 0 && c === "") && !(i === arr.length - 1 && c === ""))
                );

            if (headers.length > 0 && rows.length > 0) {
                elements.push(
                    <InteractiveTable
                        key={`${keyPrefix}_tbl_${idx}`}
                        headers={headers}
                        rows={rows}
                    />
                );
            }
            currentTableLines = [];
        } else if (currentTableLines.length > 0) {
            currentParagraph.push(...currentTableLines);
            currentTableLines = [];
        }
    };

    lines.forEach((line, index) => {
        const trimmed = line.trim();
        if (trimmed.startsWith("|") && trimmed.endsWith("|")) {
            flushParagraph(index);
            currentTableLines.push(trimmed);
        } else {
            if (currentTableLines.length > 0) {
                flushTable(index);
            }
            currentParagraph.push(line);
        }
    });

    flushParagraph(lines.length);
    flushTable(lines.length);

    return <React.Fragment key={keyPrefix}>{elements}</React.Fragment>;
}

function renderInlineFormatting(text: string): React.ReactNode {
    // Bold: **text**
    const parts = text.split(/(\*\*.*?\*\*)/g);

    return parts.map((part, i) => {
        if (part.startsWith("**") && part.endsWith("**")) {
            return (
                <strong key={i} className="font-semibold text-foreground">
                    {part.slice(2, -2)}
                </strong>
            );
        }
        return part;
    });
}
