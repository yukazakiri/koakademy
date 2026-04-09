import React, { createContext, useContext, useEffect, useState } from "react";
import { flushSync } from "react-dom";

import { themes, type ColorTheme } from "@/conf/themes";

export type { ColorTheme };

type Theme = "dark" | "light" | "system";

type ThemeProviderProps = {
    children: React.ReactNode;
    defaultTheme?: Theme;
    defaultColorTheme?: ColorTheme;
    storageKey?: string;
    colorStorageKey?: string;
};

type ThemeProviderState = {
    theme: Theme;
    setTheme: (theme: Theme) => void;
    setThemeWithViewTransition: (theme: Theme, e?: React.MouseEvent | React.TouchEvent) => void;
    colorTheme: ColorTheme;
    setColorTheme: (theme: ColorTheme) => void;
    actualTheme: "dark" | "light";
};

const initialState: ThemeProviderState = {
    theme: "system",
    setTheme: () => null,
    setThemeWithViewTransition: () => null,
    colorTheme: "default",
    setColorTheme: () => null,
    actualTheme: "light",
};

const ThemeProviderContext = createContext<ThemeProviderState>(initialState);

export function ThemeProvider({
    children,
    defaultTheme = "system",
    defaultColorTheme = "default",
    storageKey = "ui-theme",
    colorStorageKey = "ui-color-theme",
    ...props
}: ThemeProviderProps) {
    const [theme, setTheme] = useState<Theme>(() => (localStorage.getItem(storageKey) as Theme) || defaultTheme);
    const [colorTheme, setColorTheme] = useState<ColorTheme>(() => (localStorage.getItem(colorStorageKey) as ColorTheme) || defaultColorTheme);

    const [actualTheme, setActualTheme] = useState<"dark" | "light">("light");

    const applyThemeToDOM = (t: Theme) => {
        const root = window.document.documentElement;
        root.classList.remove("light", "dark");

        if (t === "system") {
            const systemTheme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";

            root.classList.add(systemTheme);
            return systemTheme;
        } else {
            root.classList.add(t);
            return t;
        }
    };

    useEffect(() => {
        const effectiveTheme = applyThemeToDOM(theme);
        setActualTheme(effectiveTheme as "dark" | "light");
    }, [theme]);

    useEffect(() => {
        const root = window.document.documentElement;

        // Dynamically remove all theme classes defined in config
        themes.forEach((t) => {
            root.classList.remove(`theme-${t.id}`);
        });

        root.classList.add(`theme-${colorTheme}`);
    }, [colorTheme]);

    const setThemeWithViewTransition = (newTheme: Theme, e?: React.MouseEvent | React.TouchEvent) => {
        const doc = document as any;

        if (!doc.startViewTransition || !e) {
            setThemeState(newTheme);
            return;
        }

        let x, y;
        if ("touches" in e) {
            const touch = e.touches[0];
            x = touch.clientX;
            y = touch.clientY;
        } else {
            x = (e as React.MouseEvent).clientX;
            y = (e as React.MouseEvent).clientY;
        }

        const endRadius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));

        const transition = doc.startViewTransition(() => {
            document.documentElement.classList.add("no-transitions");
        });

        const oldBgColor = getComputedStyle(document.documentElement).getPropertyValue("--background").trim();

        transition.ready.then(() => {
            const clipPath = [`circle(0px at ${x}px ${y}px)`, `circle(${endRadius}px at ${x}px ${y}px)`];

            flushSync(() => {
                applyThemeToDOM(newTheme);
            });

            const newBgColor = getComputedStyle(document.documentElement).getPropertyValue("--background").trim();

            const oldRoot = document.querySelector("::view-transition-old(root)") as HTMLElement;
            const newRoot = document.querySelector("::view-transition-new(root)") as HTMLElement;

            if (oldRoot) {
                oldRoot.style.background = `hsl(${oldBgColor})`;
                oldRoot.style.animation = "none";
            }
            if (newRoot) {
                newRoot.style.background = `hsl(${newBgColor})`;
            }

            document.documentElement.animate(
                {
                    clipPath: clipPath,
                },
                {
                    duration: 400,
                    easing: "ease-in-out",
                    pseudoElement: "::view-transition-new(root)",
                },
            );
        });

        transition.finished.then(() => {
            document.documentElement.classList.remove("no-transitions");
            setThemeState(newTheme);
        });
    };

    const setThemeState = (theme: Theme) => {
        localStorage.setItem(storageKey, theme);
        setTheme(theme);
    };

    const value = {
        theme,
        setTheme: setThemeState,
        setThemeWithViewTransition,
        colorTheme,
        setColorTheme: (theme: ColorTheme) => {
            localStorage.setItem(colorStorageKey, theme);
            setColorTheme(theme);
        },
        actualTheme,
    };

    return (
        <ThemeProviderContext.Provider {...props} value={value}>
            {children}
        </ThemeProviderContext.Provider>
    );
}

export const useTheme = () => {
    const context = useContext(ThemeProviderContext);

    if (context === undefined) throw new Error("useTheme must be used within a ThemeProvider");

    return context;
};
