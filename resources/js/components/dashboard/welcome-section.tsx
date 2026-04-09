import { cn } from "@/lib/utils";
import { IconBook2, IconCoffee, IconMoon, IconSparkles, IconSun } from "@tabler/icons-react";
import { motion } from "framer-motion";
import { useEffect, useState } from "react";

interface WelcomeSectionProps {
    name: string;
}

function getTimeOfDay(): "morning" | "afternoon" | "evening" {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return "morning";
    if (hour >= 12 && hour < 17) return "afternoon";
    return "evening";
}

function getTimeIcon(timeOfDay: "morning" | "afternoon" | "evening") {
    switch (timeOfDay) {
        case "morning":
            return IconSun;
        case "afternoon":
            return IconCoffee;
        case "evening":
            return IconMoon;
    }
}

function getTimeGradient(timeOfDay: "morning" | "afternoon" | "evening") {
    switch (timeOfDay) {
        case "morning":
            return "from-amber-400/20 via-orange-300/10 to-transparent";
        case "afternoon":
            return "from-sky-400/20 via-blue-300/10 to-transparent";
        case "evening":
            return "from-indigo-500/20 via-purple-400/10 to-transparent";
    }
}

const motivationalMessages = [
    "Ready to inspire minds today!",
    "Every lesson shapes a future.",
    "Teaching is a work of heart.",
    "Making a difference, one class at a time.",
    "Your impact goes beyond the classroom.",
    "Today is full of possibilities!",
];

export function WelcomeSection({ name }: WelcomeSectionProps) {
    const [timeOfDay, setTimeOfDay] = useState<"morning" | "afternoon" | "evening">("morning");
    const [message, setMessage] = useState("");

    useEffect(() => {
        setTimeOfDay(getTimeOfDay());
        setMessage(motivationalMessages[Math.floor(Math.random() * motivationalMessages.length)]);
    }, []);

    const TimeIcon = getTimeIcon(timeOfDay);
    const firstName = name.split(" ")[0];
    const greeting = timeOfDay === "morning" ? "Good morning" : timeOfDay === "afternoon" ? "Good afternoon" : "Good evening";

    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="relative overflow-hidden rounded-2xl"
        >
            <div className={cn("absolute inset-0 bg-gradient-to-r opacity-50", getTimeGradient(timeOfDay))} />

            <div className="relative px-6 py-8 sm:px-8 sm:py-10">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-4">
                        <motion.div
                            initial={{ scale: 0, rotate: -180 }}
                            animate={{ scale: 1, rotate: 0 }}
                            transition={{ type: "spring", stiffness: 200, damping: 15, delay: 0.2 }}
                            className="from-primary/20 to-primary/5 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br shadow-lg"
                        >
                            <TimeIcon className="text-primary h-7 w-7" />
                        </motion.div>

                        <div className="space-y-1">
                            <motion.div
                                initial={{ opacity: 0, x: -20 }}
                                animate={{ opacity: 1, x: 0 }}
                                transition={{ delay: 0.3 }}
                                className="flex items-center gap-2"
                            >
                                <h1 className="text-foreground text-2xl font-bold tracking-tight sm:text-3xl">
                                    {greeting}, <span className="text-primary">{firstName}</span>!
                                </h1>
                                <motion.div animate={{ rotate: [0, 15, -10, 20, -5, 0] }} transition={{ duration: 0.6, delay: 0.8 }}>
                                    <IconSparkles className="h-5 w-5 text-amber-500" />
                                </motion.div>
                            </motion.div>

                            <motion.p
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                transition={{ delay: 0.5 }}
                                className="text-muted-foreground text-sm sm:text-base"
                            >
                                {message}
                            </motion.p>
                        </div>
                    </div>

                    <motion.div
                        initial={{ opacity: 0, scale: 0.8 }}
                        animate={{ opacity: 1, scale: 1 }}
                        transition={{ delay: 0.6 }}
                        className="from-primary/10 via-primary/5 hidden items-center gap-3 rounded-xl bg-gradient-to-r to-transparent px-4 py-3 sm:flex"
                    >
                        <div className="bg-primary/20 flex h-10 w-10 items-center justify-center rounded-xl">
                            <IconBook2 className="text-primary h-5 w-5" />
                        </div>
                        <div className="text-sm">
                            <p className="text-foreground font-medium">Academic Year</p>
                            <p className="text-muted-foreground text-xs">2024-2025 • 2nd Semester</p>
                        </div>
                    </motion.div>
                </div>

                <motion.div
                    initial={{ scaleX: 0 }}
                    animate={{ scaleX: 1 }}
                    transition={{ delay: 0.7, duration: 0.8 }}
                    className="from-primary/40 mt-6 h-1 w-full origin-left rounded-full bg-gradient-to-r via-amber-400/30 to-transparent"
                />
            </div>
        </motion.div>
    );
}
