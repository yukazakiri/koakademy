import { useEffect, useRef } from "react";
import { createPortal } from "react-dom";

export type FloatingMenuPosition = {
    x: number;
    y: number;
};

interface FloatingContextMenuProps {
    position: FloatingMenuPosition | null;
    onClose: () => void;
    children: React.ReactNode;
}

export function FloatingContextMenu({ position, onClose, children }: FloatingContextMenuProps) {
    const menuRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (!position) return;

        const handlePointerDown = (event: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
                onClose();
            }
        };
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === "Escape") {
                onClose();
            }
        };
        const handleScroll = () => onClose();

        window.addEventListener("mousedown", handlePointerDown);
        window.addEventListener("keydown", handleKeyDown);
        window.addEventListener("scroll", handleScroll, true);

        return () => {
            window.removeEventListener("mousedown", handlePointerDown);
            window.removeEventListener("keydown", handleKeyDown);
            window.removeEventListener("scroll", handleScroll, true);
        };
    }, [position, onClose]);

    if (!position || typeof document === "undefined") return null;

    const menuWidth = 232;
    const menuEstHeight = 290;
    const left = Math.max(8, Math.min(position.x, window.innerWidth - menuWidth - 8));
    const top = Math.max(8, Math.min(position.y, window.innerHeight - menuEstHeight - 8));

    return createPortal(
        <div
            ref={menuRef}
            role="menu"
            style={{ left: `${left}px`, top: `${top}px`, width: `${menuWidth}px` }}
            onContextMenu={(e) => e.preventDefault()}
            className="bg-popover text-popover-foreground border-border/80 animate-in fade-in-0 zoom-in-95 fixed z-50 overflow-hidden rounded-xl border p-1 shadow-xl duration-100"
        >
            {children}
        </div>,
        document.body,
    );
}

export function FloatingMenuLabel({
    title,
    subtitle,
}: {
    title: string;
    subtitle?: string;
}) {
    return (
        <div className="px-2.5 py-1.5">
            <p className="truncate text-xs font-semibold leading-tight">{title}</p>
            {subtitle && (
                <p className="text-muted-foreground mt-0.5 truncate text-[10.5px] tabular-nums">
                    {subtitle}
                </p>
            )}
        </div>
    );
}

export function FloatingMenuSeparator() {
    return <div className="bg-border/70 -mx-1 my-1 h-px" />;
}

export function FloatingMenuItem({
    icon,
    label,
    destructive = false,
    onSelect,
}: {
    icon: React.ReactNode;
    label: string;
    destructive?: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            role="menuitem"
            onClick={onSelect}
            className={`flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition-colors outline-none ${
                destructive
                    ? "text-destructive hover:bg-destructive/10 focus:bg-destructive/10"
                    : "text-foreground hover:bg-accent focus:bg-accent"
            }`}
        >
            <span className={destructive ? "text-destructive" : "text-muted-foreground"}>{icon}</span>
            <span className="truncate">{label}</span>
        </button>
    );
}
