import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";
import { Loader2, MessageSquare, MoreHorizontal, Pencil, Plus, Search, Trash2, X } from "lucide-react";
import * as React from "react";
import { ConversationGroup, ConversationItem, groupConversationsByDate } from "./ai-constants";

interface AiConversationSidebarProps {
    conversations: ConversationItem[];
    activeConversationId?: string;
    onSelectConversation: (id: string) => void;
    onNewChat: () => void;
    onRenameConversation: (id: string, newTitle: string) => Promise<void>;
    onDeleteConversation: (id: string) => Promise<void>;
    searchQuery: string;
    onSearchChange: (query: string) => void;
    isLoading?: boolean;
    hasMore?: boolean;
    isLoadingMore?: boolean;
    onLoadMore?: () => void;
    totalConversations?: number;
    newChatPlacement?: "top" | "bottom";
    className?: string;
}

export function AiConversationSidebar({
    conversations,
    activeConversationId,
    onSelectConversation,
    onNewChat,
    onRenameConversation,
    onDeleteConversation,
    searchQuery,
    onSearchChange,
    isLoading = false,
    hasMore = false,
    isLoadingMore = false,
    onLoadMore,
    newChatPlacement = "top",
    className,
}: AiConversationSidebarProps) {
    const [editingConv, setEditingConv] = React.useState<ConversationItem | null>(null);
    const [renameTitle, setRenameTitle] = React.useState("");
    const [isRenaming, setIsRenaming] = React.useState(false);

    const [deletingConv, setDeletingConv] = React.useState<ConversationItem | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    const filteredConversations = React.useMemo(() => {
        if (!searchQuery.trim()) return conversations;
        const q = searchQuery.toLowerCase();
        return conversations.filter((c) => c.title.toLowerCase().includes(q));
    }, [conversations, searchQuery]);

    const grouped = React.useMemo(() => {
        return groupConversationsByDate(filteredConversations);
    }, [filteredConversations]);

    const handleOpenRename = (conv: ConversationItem, e?: React.MouseEvent) => {
        e?.stopPropagation();
        setEditingConv(conv);
        setRenameTitle(conv.title);
    };

    const handleConfirmRename = async () => {
        if (!editingConv || !renameTitle.trim() || isRenaming) return;
        setIsRenaming(true);
        try {
            await onRenameConversation(editingConv.id, renameTitle.trim());
            setEditingConv(null);
        } finally {
            setIsRenaming(false);
        }
    };

    const handleOpenDelete = (conv: ConversationItem, e?: React.MouseEvent) => {
        e?.stopPropagation();
        setDeletingConv(conv);
    };

    const handleConfirmDelete = async () => {
        if (!deletingConv || isDeleting) return;
        setIsDeleting(true);
        try {
            await onDeleteConversation(deletingConv.id);
            setDeletingConv(null);
        } finally {
            setIsDeleting(false);
        }
    };

    return (
        <aside className={cn("bg-sidebar/95 border-sidebar-border flex h-full flex-col border-r select-none", className)}>
            {/* Search stays fixed while the conversation list scrolls. */}
            <div className="border-sidebar-border flex flex-col gap-2.5 border-b p-3">
                {newChatPlacement === "top" && (
                    <Button
                        type="button"
                        onClick={onNewChat}
                        className="bg-primary text-primary-foreground hover:bg-primary/90 h-9 w-full justify-start gap-2 text-xs font-medium shadow-xs"
                    >
                        <Plus className="size-4" />
                        <span>New chat</span>
                    </Button>
                )}

                <div className="relative">
                    <Search className="text-muted-foreground pointer-events-none absolute top-2.5 left-2.5 size-3.5" />
                    <Input
                        type="text"
                        placeholder="Search chats..."
                        value={searchQuery}
                        onChange={(e) => onSearchChange(e.target.value)}
                        className="bg-background/50 border-sidebar-border h-8 pr-7 pl-8 text-xs focus-visible:ring-1"
                    />
                    {searchQuery && (
                        <button
                            type="button"
                            onClick={() => onSearchChange("")}
                            className="text-muted-foreground hover:text-foreground absolute top-2 right-2"
                            aria-label="Clear chat search"
                        >
                            <X className="size-3.5" />
                        </button>
                    )}
                </div>
            </div>

            {/* Conversation History List */}
            <ScrollArea className="flex-1 px-2 py-3">
                {isLoading && conversations.length === 0 ? (
                    <div className="text-muted-foreground flex flex-col items-center justify-center p-8 text-center">
                        <Loader2 className="mb-2 size-5 animate-spin" />
                        <span className="text-xs">Loading conversations...</span>
                    </div>
                ) : grouped.length === 0 ? (
                    <div className="text-muted-foreground flex flex-col items-center justify-center p-8 text-center">
                        <MessageSquare className="mb-2 size-6 opacity-40" />
                        <span className="text-xs font-medium">{searchQuery ? "No matching chats found" : "No conversation history yet"}</span>
                        <p className="text-muted-foreground/80 mt-1 max-w-[180px] text-[11px]">
                            {searchQuery
                                ? "Try searching for a different keyword"
                                : "Start a new conversation to begin receiving AI institutional intelligence."}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {grouped.map((group: ConversationGroup) => (
                            <div key={group.label} className="space-y-1">
                                <h4 className="text-muted-foreground px-2 text-[10px] font-semibold tracking-wider uppercase">{group.label}</h4>
                                <div className="space-y-0.5">
                                    {group.items.map((conv) => {
                                        const isActive = conv.id === activeConversationId;
                                        return (
                                            <div
                                                key={conv.id}
                                                className={cn(
                                                    "group relative flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-xs font-medium transition-colors",
                                                    isActive
                                                        ? "bg-sidebar-accent text-sidebar-accent-foreground font-semibold"
                                                        : "text-sidebar-foreground/80 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground",
                                                )}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() => onSelectConversation(conv.id)}
                                                    aria-current={isActive ? "page" : undefined}
                                                    className="focus-visible:ring-sidebar-ring flex min-w-0 flex-1 items-center gap-2 text-left focus-visible:ring-2 focus-visible:outline-none"
                                                >
                                                    <MessageSquare
                                                        className={cn(
                                                            "size-3.5 shrink-0",
                                                            isActive ? "text-primary" : "text-muted-foreground group-hover:text-foreground",
                                                        )}
                                                    />
                                                    <span className="truncate">{conv.title || "Untitled Conversation"}</span>
                                                </button>

                                                {/* Actions dropdown */}
                                                <div
                                                    className={cn(
                                                        "flex items-center opacity-0 transition-opacity group-hover:opacity-100",
                                                        isActive && "opacity-100",
                                                    )}
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-muted-foreground hover:text-foreground size-6 rounded"
                                                                aria-label={`Manage ${conv.title || "Untitled Conversation"}`}
                                                            >
                                                                <MoreHorizontal className="size-3.5" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" className="w-36 text-xs">
                                                            <DropdownMenuItem
                                                                onClick={(e) => handleOpenRename(conv, e)}
                                                                className="cursor-pointer gap-2"
                                                            >
                                                                <Pencil className="text-muted-foreground size-3.5" />
                                                                <span>Rename</span>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onClick={(e) => handleOpenDelete(conv, e)}
                                                                className="text-destructive focus:text-destructive cursor-pointer gap-2"
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                                <span>Delete</span>
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}

                        {hasMore && (
                            <div className="px-1 pt-2 pb-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={onLoadMore}
                                    disabled={isLoadingMore}
                                    className="text-muted-foreground hover:text-foreground border-sidebar-border h-8 w-full border-dashed text-xs shadow-none"
                                >
                                    {isLoadingMore ? (
                                        <>
                                            <Loader2 className="mr-1.5 size-3.5 animate-spin" />
                                            <span>Loading older chats...</span>
                                        </>
                                    ) : (
                                        <span>Load older chats</span>
                                    )}
                                </Button>
                            </div>
                        )}
                    </div>
                )}
            </ScrollArea>

            {newChatPlacement === "bottom" && (
                <div className="border-sidebar-border border-t p-3">
                    <Button type="button" onClick={onNewChat} className="bg-foreground text-background hover:bg-foreground/90 h-10 w-full gap-2">
                        <Plus className="size-4" />
                        <span>New chat</span>
                    </Button>
                </div>
            )}

            {/* Rename Dialog */}
            <Dialog open={!!editingConv} onOpenChange={(open) => !open && setEditingConv(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Rename conversation</DialogTitle>
                    </DialogHeader>
                    <div className="py-2">
                        <Input
                            value={renameTitle}
                            onChange={(e) => setRenameTitle(e.target.value)}
                            placeholder="Conversation title"
                            onKeyDown={(e) => {
                                if (e.key === "Enter") {
                                    e.preventDefault();
                                    handleConfirmRename();
                                }
                            }}
                            autoFocus
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setEditingConv(null)} disabled={isRenaming}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={handleConfirmRename} disabled={isRenaming || !renameTitle.trim()}>
                            {isRenaming && <Loader2 className="mr-1.5 size-3.5 animate-spin" />}
                            Save
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Alert Dialog */}
            <AlertDialog open={!!deletingConv} onOpenChange={(open) => !open && setDeletingConv(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete conversation?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete &ldquo;{deletingConv?.title}&rdquo; and all of its messages. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isDeleting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmDelete}
                            disabled={isDeleting}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {isDeleting && <Loader2 className="mr-1.5 size-3.5 animate-spin" />}
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </aside>
    );
}

export default AiConversationSidebar;
