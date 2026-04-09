"use client";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { IconChevronRight, IconMenu, IconSchool, IconSearch, IconSparkles } from "@tabler/icons-react";
import { useEffect, useRef, useState } from "react";

interface Branding {
    appName: string;
    organizationName: string;
    organizationShortName: string;
}

interface DocSection {
    id: string;
    title: string;
    children?: DocPageNav[];
    type: "category";
}

interface DocPageNav {
    id: string;
    title: string;
    type: "page";
}

interface TocItem {
    id: string;
    title: string;
    level: number;
}

interface SEOData {
    title: string;
    description: string;
    keywords: string;
    canonical: string;
    og: {
        title: string;
        description: string;
        type: string;
        url: string;
        image: string | null;
        site_name: string;
        locale: string;
    };
    twitter: {
        card: string;
        title: string;
        description: string;
        image: string | null;
    };
    structured_data: Record<string, unknown>;
}

interface PageProps {
    slug: string;
    type: "guide" | "api";
    branding?: Branding;
    page: {
        title: string;
        description: string;
        content: string;
        tableOfContents: TocItem[];
    };
    navigation: DocSection[];
    seo: SEOData;
    [key: string]: unknown;
}

export default function DocsIndex() {
    const { props } = usePage<PageProps>();
    const appName = props.branding?.appName || "School Portal";
    const organizationName = props.branding?.organizationName || "University";
    const orgShortName = props.branding?.organizationShortName || "UNI";
    const seo = props.seo;

    const { slug, type, page, navigation } = props;
    const [searchQuery, setSearchQuery] = useState("");
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [activeHeading, setActiveHeading] = useState<string>("");
    const tocRef = useRef<HTMLDivElement>(null);

    const allPages = navigation.flatMap((section) => section.children?.map((child) => ({ ...child, sectionTitle: section.title })) || []);

    const filteredPages = searchQuery ? allPages.filter((p) => p.title.toLowerCase().includes(searchQuery.toLowerCase())) : [];

    const handleNavClick = (newSlug: string) => {
        setMobileMenuOpen(false);
        router.visit(`/docs/v1/${newSlug}`);
    };

    const handleTypeChange = (newType: "guide" | "api") => {
        const target = newType === "guide" ? "introduction" : "api-overview";
        router.visit(`/docs/v1/${target}`);
    };

    const toc = page.tableOfContents || [];

    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setActiveHeading(entry.target.id);
                    }
                });
            },
            { rootMargin: "-100px 0px -80% 0px" },
        );

        document.querySelectorAll("h2[id], h3[id]").forEach((heading) => {
            observer.observe(heading);
        });

        return () => observer.disconnect();
    }, [page.content]);

    const scrollToHeading = (id: string) => {
        const element = document.getElementById(id);
        if (element) {
            element.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    };

    return (
        <>
            <Head>
                {/* Primary Meta Tags */}
                <title>{seo.title}</title>
                <meta name="title" content={seo.title} />
                <meta name="description" content={seo.description} />
                <meta name="keywords" content={seo.keywords} />
                <link rel="canonical" href={seo.canonical} />

                {/* Open Graph / Facebook */}
                <meta property="og:type" content={seo.og.type} />
                <meta property="og:url" content={seo.og.url} />
                <meta property="og:title" content={seo.og.title} />
                <meta property="og:description" content={seo.og.description} />
                <meta property="og:site_name" content={seo.og.site_name} />
                <meta property="og:locale" content={seo.og.locale} />
                {seo.og.image && <meta property="og:image" content={seo.og.image} />}
                {seo.og.image && <meta property="og:image:width" content="1200" />}
                {seo.og.image && <meta property="og:image:height" content="630" />}

                {/* Twitter */}
                <meta property="twitter:card" content={seo.twitter.card} />
                <meta property="twitter:url" content={seo.og.url} />
                <meta property="twitter:title" content={seo.twitter.title} />
                <meta property="twitter:description" content={seo.twitter.description} />
                {seo.twitter.image && <meta property="twitter:image" content={seo.twitter.image} />}

                {/* Structured Data */}
                <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(seo.structured_data) }} />
            </Head>

            <div className="bg-background text-foreground min-h-screen font-sans">
                <header className="bg-background/95 supports-[backdrop-filter]:bg-background/60 sticky top-0 z-50 w-full border-b backdrop-blur">
                    <div className="container mx-auto flex h-14 max-w-[1600px] items-center px-4">
                        <div className="mr-6 hidden items-center gap-6 md:flex">
                            <Link href="/" className="flex items-center gap-2 text-lg font-semibold">
                                <IconSchool className="size-5" />
                                <span>{orgShortName} Docs</span>
                            </Link>

                            <nav className="flex items-center gap-5 text-sm font-medium">
                                <button
                                    onClick={() => handleTypeChange("guide")}
                                    className={cn(
                                        "hover:text-foreground transition-colors",
                                        type === "guide" ? "text-foreground" : "text-muted-foreground",
                                    )}
                                >
                                    Documentation
                                </button>
                                <button
                                    onClick={() => handleTypeChange("api")}
                                    className={cn(
                                        "hover:text-foreground transition-colors",
                                        type === "api" ? "text-foreground" : "text-muted-foreground",
                                    )}
                                >
                                    API Reference
                                </button>
                                <Link href="/help" className="text-muted-foreground hover:text-foreground transition-colors">
                                    Help Center
                                </Link>
                            </nav>
                        </div>

                        <Sheet open={mobileMenuOpen} onOpenChange={setMobileMenuOpen}>
                            <SheetTrigger asChild className="md:hidden">
                                <Button variant="ghost" size="icon" className="mr-2">
                                    <IconMenu className="size-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="w-[300px] pr-0">
                                <div className="mb-6 flex items-center gap-2 px-2">
                                    <IconSchool className="size-6" />
                                    <span className="text-lg font-semibold">{orgShortName} Docs</span>
                                </div>
                                <div className="mb-4 flex gap-2 px-2">
                                    <Button
                                        variant={type === "guide" ? "secondary" : "ghost"}
                                        size="sm"
                                        className="flex-1"
                                        onClick={() => handleTypeChange("guide")}
                                    >
                                        Guide
                                    </Button>
                                    <Button
                                        variant={type === "api" ? "secondary" : "ghost"}
                                        size="sm"
                                        className="flex-1"
                                        onClick={() => handleTypeChange("api")}
                                    >
                                        API
                                    </Button>
                                </div>
                                <ScrollArea className="h-[calc(100vh-12rem)]">
                                    <DocsNav sections={navigation} currentSlug={slug} onSelect={handleNavClick} />
                                </ScrollArea>
                            </SheetContent>
                        </Sheet>

                        <div className="flex flex-1 items-center justify-end gap-4">
                            <div className="relative w-full max-w-[300px]">
                                <IconSearch className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input
                                    placeholder="Search docs..."
                                    className="bg-muted/50 h-9 w-full pl-9"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                />
                                {searchQuery && filteredPages.length > 0 && (
                                    <div className="bg-popover absolute top-full z-50 mt-1 w-full rounded-md border p-1 shadow-md">
                                        {filteredPages.slice(0, 5).map((p) => (
                                            <button
                                                key={p.id}
                                                onClick={() => {
                                                    handleNavClick(p.id);
                                                    setSearchQuery("");
                                                }}
                                                className="hover:bg-accent flex w-full flex-col items-start rounded-sm px-3 py-2 text-sm"
                                            >
                                                <span className="font-medium">{p.title}</span>
                                                <span className="text-muted-foreground text-xs">{p.sectionTitle}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <Button variant="outline" size="sm" className="hidden h-9 gap-2 sm:flex">
                                <IconSparkles className="size-4" />
                                Ask AI
                            </Button>
                        </div>
                    </div>
                </header>

                <div className="container mx-auto max-w-[1600px] px-4">
                    <div className="flex gap-8">
                        <aside className="hidden shrink-0 py-6 md:block md:w-60 lg:w-64">
                            <div className="sticky top-14 max-h-[calc(100vh-3.5rem)] overflow-y-auto">
                                <DocsNav sections={navigation} currentSlug={slug} onSelect={handleNavClick} />
                            </div>
                        </aside>

                        <main className="min-w-0 flex-1 py-6">
                            <div className="text-muted-foreground mb-6 flex items-center gap-2 text-sm">
                                <span className="capitalize">{type}</span>
                                <IconChevronRight className="size-4" />
                                <span>{page.title}</span>
                            </div>

                            <div className="space-y-6">
                                <div>
                                    <h1 className="text-3xl font-bold tracking-tight lg:text-4xl">{page.title}</h1>
                                    {page.description && <p className="text-muted-foreground mt-3 text-lg leading-relaxed">{page.description}</p>}
                                </div>

                                <Separator />

                                <MarkdownContent content={page.content} />
                            </div>
                        </main>

                        <aside className="hidden shrink-0 py-6 xl:block xl:w-64">
                            <div className="sticky top-14 max-h-[calc(100vh-3.5rem)] overflow-y-auto">
                                {toc.length > 0 && (
                                    <div className="bg-card rounded-lg border">
                                        <div className="border-b px-4 py-3">
                                            <h4 className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">On This Page</h4>
                                        </div>
                                        <div className="relative" ref={tocRef}>
                                            <nav className="space-y-1 px-4 py-3">
                                                {toc.map((item) => (
                                                    <button
                                                        key={item.id}
                                                        onClick={() => scrollToHeading(item.id)}
                                                        className={cn(
                                                            "relative block w-full py-1.5 text-left text-sm transition-colors",
                                                            activeHeading === item.id
                                                                ? "text-foreground font-medium"
                                                                : "text-muted-foreground hover:text-foreground",
                                                        )}
                                                    >
                                                        {activeHeading === item.id && (
                                                            <span className="bg-primary absolute top-1/2 -left-4 h-1.5 w-1 -translate-y-1/2 rounded-full transition-all duration-300" />
                                                        )}
                                                        {item.title}
                                                    </button>
                                                ))}
                                            </nav>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </>
    );
}

function DocsNav({ sections, currentSlug, onSelect }: { sections: DocSection[]; currentSlug: string; onSelect: (slug: string) => void }) {
    return (
        <div className="w-full space-y-6 pr-4">
            {sections.map((section) => (
                <div key={section.id}>
                    <h4 className="mb-2 text-sm font-semibold">{section.title}</h4>
                    {section.children && (
                        <div className="space-y-1">
                            {section.children.map((child) => (
                                <button
                                    key={child.id}
                                    onClick={() => onSelect(child.id)}
                                    className={cn(
                                        "flex w-full items-center rounded-md px-3 py-2 text-sm transition-colors",
                                        currentSlug === child.id
                                            ? "bg-primary/10 text-primary font-medium"
                                            : "text-muted-foreground hover:bg-accent hover:text-foreground",
                                    )}
                                >
                                    {child.title}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

function highlightCode(code: string): React.ReactNode[] {
    const lines = code.split("\n");

    const getTokenColor = (type: string) => {
        switch (type) {
            case "keyword":
                return "text-violet-400";
            case "string":
                return "text-emerald-400";
            case "comment":
                return "text-muted-foreground italic";
            case "number":
                return "text-orange-400";
            case "function":
                return "text-blue-400";
            case "variable":
                return "text-pink-400";
            default:
                return "text-foreground";
        }
    };

    return lines.map((line, lineIndex) => {
        const tokens: { start: number; end: number; type: string; value: string }[] = [];

        const stringRegex = /("(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'|`(?:[^`\\]|\\.)*`)/g;
        let match;
        while ((match = stringRegex.exec(line)) !== null) {
            tokens.push({ start: match.index, end: match.index + match[0].length, type: "string", value: match[0] });
        }

        const commentRegex = /(\/\/.*$|#.*$)/gm;
        while ((match = commentRegex.exec(line)) !== null) {
            if (!tokens.some((t) => t.start < match!.index + match![0].length && t.end > match!.index)) {
                tokens.push({ start: match.index, end: match.index + match[0].length, type: "comment", value: match[0] });
            }
        }

        const keywordRegex =
            /\b(const|let|var|function|async|await|return|if|else|for|while|class|import|from|export|default|try|catch|throw|new|this|typeof|instanceof|public|private|protected|static|void|int|string|bool|array|true|false|null|undefined)\b/g;
        while ((match = keywordRegex.exec(line)) !== null) {
            if (!tokens.some((t) => t.start < match!.index + match![0].length && t.end > match!.index)) {
                tokens.push({ start: match.index, end: match.index + match[0].length, type: "keyword", value: match[0] });
            }
        }

        const numberRegex = /\b(\d+\.?\d*)\b/g;
        while ((match = numberRegex.exec(line)) !== null) {
            if (!tokens.some((t) => t.start < match!.index + match![0].length && t.end > match!.index)) {
                tokens.push({ start: match.index, end: match.index + match[0].length, type: "number", value: match[0] });
            }
        }

        const phpVarRegex = /(\$[a-zA-Z_][a-zA-Z0-9_]*)/g;
        while ((match = phpVarRegex.exec(line)) !== null) {
            if (!tokens.some((t) => t.start < match!.index + match![0].length && t.end > match!.index)) {
                tokens.push({ start: match.index, end: match.index + match[0].length, type: "variable", value: match[0] });
            }
        }

        const funcRegex = /\b([a-zA-Z_][a-zA-Z0-9_]*)(?=\s*\()/g;
        while ((match = funcRegex.exec(line)) !== null) {
            if (!tokens.some((t) => t.start < match!.index + match![0].length && t.end > match!.index)) {
                tokens.push({ start: match.index, end: match.index + match[0].length, type: "function", value: match[0] });
            }
        }

        tokens.sort((a, b) => a.start - b.start);

        const result: React.ReactNode[] = [];
        let pos = 0;

        for (const token of tokens) {
            if (token.start > pos) {
                result.push(<span key={`text-${pos}`}>{line.slice(pos, token.start)}</span>);
            }
            result.push(
                <span key={`token-${token.start}`} className={getTokenColor(token.type)}>
                    {token.value}
                </span>,
            );
            pos = token.end;
        }

        if (pos < line.length) {
            result.push(<span key={`text-end`}>{line.slice(pos)}</span>);
        }

        return <div key={lineIndex}>{result.length > 0 ? result : "\u00A0"}</div>;
    });
}

function CodeBlock({ code, language }: { code: string; language: string }) {
    const lang = language.toLowerCase();
    const langLabels: Record<string, string> = {
        javascript: "JavaScript",
        js: "JavaScript",
        php: "PHP",
        python: "Python",
        py: "Python",
        json: "JSON",
        bash: "Bash",
        curl: "cURL",
    };

    const label = langLabels[lang] || lang.toUpperCase();

    return (
        <div className="group bg-card relative my-6 overflow-hidden rounded-lg border shadow-sm">
            <div className="bg-muted/50 flex items-center justify-between border-b px-4 py-2">
                <div className="flex items-center gap-2">
                    <div className="flex gap-1.5">
                        <div className="h-3 w-3 rounded-full bg-red-400/80"></div>
                        <div className="h-3 w-3 rounded-full bg-yellow-400/80"></div>
                        <div className="h-3 w-3 rounded-full bg-green-400/80"></div>
                    </div>
                    <span className="text-muted-foreground ml-2 text-xs font-medium">{label}</span>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 text-xs opacity-0 transition-opacity group-hover:opacity-100"
                    onClick={() => navigator.clipboard.writeText(code)}
                >
                    Copy
                </Button>
            </div>
            <pre className="overflow-x-auto bg-zinc-950 p-4 font-mono text-sm leading-relaxed">
                <code className="text-zinc-100">{highlightCode(code)}</code>
            </pre>
        </div>
    );
}

function MarkdownContent({ content }: { content: string }) {
    const lines = content.split("\n");
    const elements: React.ReactNode[] = [];

    let inCodeBlock = false;
    let codeBlockLines: string[] = [];
    let codeBlockLang = "";
    let inTable = false;
    let tableRows: string[] = [];

    lines.forEach((line, index) => {
        // Skip HTML comments (used for title-nav markers)
        if (line.trim().startsWith("<!--") && line.trim().includes("-->")) {
            return;
        }

        if (line.startsWith("```")) {
            if (!inCodeBlock) {
                inCodeBlock = true;
                codeBlockLang = line.slice(3).trim();
                codeBlockLines = [];
            } else {
                elements.push(<CodeBlock key={index} code={codeBlockLines.join("\n")} language={codeBlockLang} />);
                inCodeBlock = false;
                codeBlockLines = [];
            }
            return;
        }

        if (inCodeBlock) {
            codeBlockLines.push(line);
            return;
        }

        // Tables
        if (line.includes("|") && line.trim().startsWith("|")) {
            if (!inTable) {
                inTable = true;
                tableRows = [];
            }
            if (!line.match(/^\|\s*[-:]?\s*\|/)) {
                tableRows.push(line);
            }
            return;
        } else if (inTable) {
            const headers = tableRows[0]?.split("|").filter((c: string) => c.trim()) || [];
            const rows = tableRows.slice(1);

            elements.push(
                <div key={index} className="my-6 overflow-hidden rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    {headers.map((h: string, i: number) => (
                                        <th key={i} className="border-b px-4 py-3 text-left font-semibold">
                                            {h.trim()}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.map((row, ri) => (
                                    <tr key={ri} className="hover:bg-muted/30">
                                        {row
                                            .split("|")
                                            .filter((c: string) => c.trim())
                                            .map((cell: string, ci: number) => (
                                                <td key={ci} className="border-b px-4 py-3">
                                                    {parseInlineStyles(cell.trim())}
                                                </td>
                                            ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>,
            );
            inTable = false;
            tableRows = [];
        }

        // Custom MDX components
        if (line.includes("<Warning>") || line.includes("<Note>")) {
            const warningMatch = line.match(/<Warning>(.*?)<\/Warning>/s);
            const noteMatch = line.match(/<Note>(.*?)<\/Note>/s);

            if (warningMatch) {
                elements.push(
                    <div key={index} className="my-4 rounded-lg border-l-4 border-amber-500 bg-amber-50 p-4 dark:bg-amber-950/20">
                        <div className="flex items-start gap-3">
                            <svg className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                            <p className="text-sm text-amber-900 dark:text-amber-200">{parseInlineStyles(warningMatch[1])}</p>
                        </div>
                    </div>,
                );
                return;
            }
            if (noteMatch) {
                elements.push(
                    <div key={index} className="my-4 rounded-lg border-l-4 border-blue-500 bg-blue-50 p-4 dark:bg-blue-950/20">
                        <div className="flex items-start gap-3">
                            <svg className="mt-0.5 h-5 w-5 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <p className="text-sm text-blue-900 dark:text-blue-200">{parseInlineStyles(noteMatch[1])}</p>
                        </div>
                    </div>,
                );
                return;
            }
        }

        // Headers
        if (line.startsWith("# ")) {
            return;
        }
        if (line.startsWith("## ")) {
            const text = line.replace("## ", "").trim();
            const id = text
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/(^-|-$)/g, "");
            elements.push(
                <h2 key={index} id={id} className="mt-10 mb-4 scroll-m-20 border-b pb-2 text-2xl font-bold tracking-tight first:mt-0">
                    {text}
                </h2>,
            );
            return;
        }
        if (line.startsWith("### ")) {
            const text = line.replace("### ", "").trim();
            const id = text
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/(^-|-$)/g, "");
            elements.push(
                <h3 key={index} id={id} className="mt-8 mb-3 scroll-m-20 text-xl font-semibold tracking-tight">
                    {text}
                </h3>,
            );
            return;
        }

        // Lists
        if (line.trim().startsWith("- ") || line.trim().startsWith("* ")) {
            const text = line.trim().substring(2);
            const parsedText = parseInlineStyles(text);
            elements.push(
                <li key={index} className="ml-6 list-disc">
                    {parsedText}
                </li>,
            );
            return;
        }

        // Numbered Lists
        if (/^\d+\.\s/.test(line.trim())) {
            const text = line.trim().replace(/^\d+\.\s/, "");
            const parsedText = parseInlineStyles(text);
            elements.push(
                <li key={index} className="ml-6 list-decimal">
                    {parsedText}
                </li>,
            );
            return;
        }

        // Empty lines
        if (line.trim() === "") {
            return;
        }

        // Paragraphs
        if (!line.startsWith("#") && !line.startsWith("-") && !line.startsWith("*")) {
            const parsedText = parseInlineStyles(line);
            elements.push(
                <p key={index} className="leading-7 [&:not(:first-child)]:mt-6">
                    {parsedText}
                </p>,
            );
        }
    });

    return <div className="space-y-1">{elements}</div>;
}

function parseInlineStyles(text: string): React.ReactNode[] {
    const parts = text.split(/(\*\*.*?\*\*|`.*?`|\[.*?\]\(.*?\))/g);
    return parts.map((part, i) => {
        if (part.startsWith("**") && part.endsWith("**")) {
            return (
                <strong key={i} className="font-semibold">
                    {part.slice(2, -2)}
                </strong>
            );
        }
        if (part.startsWith("`") && part.endsWith("`")) {
            return (
                <code key={i} className="bg-muted rounded px-1.5 py-0.5 font-mono text-sm">
                    {part.slice(1, -1)}
                </code>
            );
        }
        const linkMatch = part.match(/\[(.*?)\]\((.*?)\)/);
        if (linkMatch) {
            const [, linkText, url] = linkMatch;
            const cleanUrl = url.replace(/\.mdx$/, "");
            return (
                <a key={i} href={cleanUrl} className="text-primary underline-offset-4 hover:underline">
                    {linkText}
                </a>
            );
        }
        return part;
    });
}
