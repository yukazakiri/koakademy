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
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";
import {
    Loader2,
    MessageSquare,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from "lucide-react";
import * as React from "react";
import {
    ConversationGroup,
    ConversationItem,
    groupConversationsByDate,
} from "./ai-constants";

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
        <aside
            className={cn(
                "flex flex-col h-full bg-sidebar/95 border-r border-sidebar-border select-none",
                className
            )}
        >
            {/* Top Action & Search */}
            <div className="p-3 border-b border-sidebar-border flex flex-col gap-2.5">
                <Button
                    type="button"
                    onClick={onNewChat}
                    className="w-full justify-start gap-2 shadow-xs bg-primary text-primary-foreground hover:bg-primary/90 font-medium h-9 text-xs"
                >
                    <Plus className="size-4" />
                    <span>New chat</span>
                </Button>

                <div className="relative">
                    <Search className="absolute left-2.5 top-2.5 size-3.5 text-muted-foreground pointer-events-none" />
                    <Input
                        type="text"
                        placeholder="Search chats..."
                        value={searchQuery}
                        onChange={(e) => onSearchChange(e.target.value)}
                        className="pl-8 pr-7 h-8 text-xs bg-background/50 border-sidebar-border focus-visible:ring-1"
                    />
                    {searchQuery && (
                        <button
                            type="button"
                            onClick={() => onSearchChange("")}
                            className="absolute right-2 top-2 text-muted-foreground hover:text-foreground"
                        >
                            <X className="size-3.5" />
                        </button>
                    )}
                </div>
            </div>

            {/* Conversation History List */}
            <ScrollArea className="flex-1 px-2 py-3">
                {isLoading && conversations.length === 0 ? (
                    <div className="flex flex-col items-center justify-center p-8 text-center text-muted-foreground">
                        <Loader2 className="size-5 animate-spin mb-2" />
                        <span className="text-xs">Loading conversations...</span>
                    </div>
                ) : grouped.length === 0 ? (
                    <div className="flex flex-col items-center justify-center p-8 text-center text-muted-foreground">
                        <MessageSquare className="size-6 mb-2 opacity-40" />
                        <span className="text-xs font-medium">
                            {searchQuery ? "No matching chats found" : "No conversation history yet"}
                        </span>
                        <p className="text-[11px] text-muted-foreground/80 mt-1 max-w-[180px]">
                            {searchQuery
                                ? "Try searching for a different keyword"
                                : "Start a new conversation to begin receiving AI institutional intelligence."}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {grouped.map((group: ConversationGroup) => (
                            <div key={group.label} className="space-y-1">
                                <h4 className="px-2 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">
                                    {group.label}
                                </h4>
                                <div className="space-y-0.5">
                                    {group.items.map((conv) => {
                                        const isActive = conv.id === activeConversationId;
                                        return (
                                            <div
                                                key={conv.id}
                                                onClick={() => onSelectConversation(conv.id)}
                                                className={cn(
                                                    "group relative flex items-center justify-between gap-2 px-2.5 py-2 rounded-lg text-xs font-medium transition-colors cursor-pointer",
                                                    isActive
                                                        ? "bg-sidebar-accent text-sidebar-accent-foreground font-semibold"
                                                        : "text-sidebar-foreground/80 hover:bg-sidebar-accent/50 hover:text-sidebar-foreground"
                                                )}
                                            >
                                                <div className="flex items-center gap-2 min-w-0 flex-1">
                                                    <MessageSquare
                                                        className={cn(
                                                            "size-3.5 shrink-0",
                                                            isActive
                                                                ? "text-primary"
                                                                : "text-muted-foreground group-hover:text-foreground"
                                                        )}
                                                    />
                                                    <span className="truncate">{conv.title || "Untitled Conversation"}</span>
                                                </div>

                                                {/* Actions dropdown */}
                                                <div
                                                    className={cn(
                                                        "flex items-center opacity-0 group-hover:opacity-100 transition-opacity",
                                                        isActive && "opacity-100"
                                                    )}
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-6 text-muted-foreground hover:text-foreground rounded"
                                                            >
                                                                <MoreHorizontal className="size-3.5" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" className="w-36 text-xs">
                                                            <DropdownMenuItem
                                                                onClick={(e) => handleOpenRename(conv, e)}
                                                                className="gap-2 cursor-pointer"
                                                            >
                                                                <Pencil className="size-3.5 text-muted-foreground" />
                                                                <span>Rename</span>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onClick={(e) => handleOpenDelete(conv, e)}
                                                                className="gap-2 text-destructive focus:text-destructive cursor-pointer"
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
                    </div>
                )}
            </ScrollArea>

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
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEditingConv(null)}
                            disabled={isRenaming}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={handleConfirmRename}
                            disabled={isRenaming || !renameTitle.trim()}
                        >
                            {isRenaming && <Loader2 className="size-3.5 animate-spin mr-1.5" />}
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
                            This will permanently delete &ldquo;{deletingConv?.title}&rdquo; and all of its messages. This
                            action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isDeleting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmDelete}
                            disabled={isDeleting}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {isDeleting && <Loader2 className="size-3.5 animate-spin mr-1.5" />}
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </aside>
    );
}

export default AiConversationSidebar;
