
import { cn } from "@/lib/utils";
import { AnimatePresence, motion } from "framer-motion";
import {
    Children,
    cloneElement,
    ReactElement,
    useEffect,
    useState,
    useId,
} from "react";

type AnimatedBackgroundProps = {
    children:
    | ReactElement<{ "data-id": string }>[]
    | ReactElement<{ "data-id": string }>;
    defaultValue?: string;
    onValueChange?: (newActiveId: string | null) => void;
    className?: string;
    transition?: any;
    enableHover?: boolean;
};

export default function AnimatedBackground({
    children,
    defaultValue,
    onValueChange,
    className,
    transition,
    enableHover = false,
}: AnimatedBackgroundProps) {
    const [activeId, setActiveId] = useState<string | null>(null);
    const uniqueId = useId();

    const handleSetActiveId = (id: string | null) => {
        setActiveId(id);

        if (onValueChange) {
            onValueChange(id);
        }
    };

    useEffect(() => {
        if (defaultValue !== undefined) {
            setActiveId(defaultValue);
        }
    }, [defaultValue]);

    return (
        <div className="relative w-full">
            {/* We removed the explicit LayoutGroup here as it may cause conflicts with other nested layout groups if not careful. 
          Usually a single LayoutGroup or none (for local layoutId) is fine. 
          Using a unique prefix for layoutId helps scope it. */}
            {Children.map(children, (child: any, index) => {
                const id = child.props["data-id"];

                const interactionProps = enableHover
                    ? {
                        onMouseEnter: () => handleSetActiveId(id),
                        onMouseLeave: () => handleSetActiveId(null),
                    }
                    : {
                        onClick: () => handleSetActiveId(id),
                    };

                return cloneElement(
                    child,
                    {
                        key: index,
                        className: cn("relative z-10", child.props.className),
                        ...interactionProps,
                    },
                    <>
                        <AnimatePresence initial={false}>
                            {activeId === id && (
                                <motion.div
                                    layoutId={`background-${uniqueId}`}
                                    className={cn("absolute inset-0 z-0 bg-sidebar-accent", className)}
                                    transition={
                                        transition ?? {
                                            type: "spring",
                                            bounce: 0.2,
                                            duration: 0.3,
                                        }
                                    }
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    exit={{ opacity: 0 }}
                                />
                            )}
                        </AnimatePresence>
                        {child.props.children}
                    </>
                );
            })}
        </div>
    );
}
