export type ColorTheme =
    | "default"
    | "amber"
    | "amethyst"
    | "resolve-a-i-app"
    | "notebook"
    | "offworld"
    | "ghibli-studio"
    | "slack"
    | "vs-code"
    | "caffeine";

export interface ThemeConfig {
    id: ColorTheme;
    name: string;
    description: string;
    font: string;
    colors: {
        primary: string;
        secondary: string;
        accent: string;
    };
}

export const themes: ThemeConfig[] = [
    {
        id: "default",
        name: "Default",
        description: "The classic look.",
        font: "Inter",
        colors: {
            primary: "oklch(0.3012 0 0)",
            secondary: "oklch(0.8647 0.0201 87.5232)",
            accent: "oklch(0.9169 0.0175 99.6160)",
        },
    },
    {
        id: "resolve-a-i-app",
        name: "Resolveai App",
        description: "Imported from tweakcn.",
        font: "Inter",
        colors: {
            primary: "oklch(0.2972 0.0398 246.6002)",
            secondary: "oklch(0.9670 0.0029 264.5419)",
            accent: "oklch(0.9119 0.0222 243.8174)",
        },
    },
    {
        id: "notebook",
        name: "Notebook",
        description: "Imported from tweakcn.",
        font: "Architects Daughter",
        colors: {
            primary: "oklch(0.4891 0 0)",
            secondary: "oklch(0.9006 0 0)",
            accent: "oklch(0.9354 0.0456 94.8549)",
        },
    },
    {
        id: "offworld",
        name: "Offworld",
        description: "Imported from tweakcn.",
        font: "Geist",
        colors: {
            primary: "oklch(0.2178 0 0)",
            secondary: "oklch(0.9067 0 0)",
            accent: "oklch(0.9340 0 0)",
        },
    },
    {
        id: "ghibli-studio",
        name: "Ghibli Studio",
        description: "Imported from tweakcn.",
        font: "var(--font-sans)",
        colors: {
            primary: "oklch(0.71 0.10 111.96)",
            secondary: "oklch(0.88 0.05 83.32)",
            accent: "oklch(0.86 0.05 85.12)",
        },
    },
    {
        id: "slack",
        name: "Slack",
        description: "Imported from tweakcn.",
        font: "var(--font-sans)",
        colors: {
            primary: "oklch(0.37 0.14 323.40)",
            secondary: "oklch(0.96 0.01 311.36)",
            accent: "oklch(0.88 0.02 323.34)",
        },
    },
    {
        id: "vs-code",
        name: "Vs Code",
        description: "Imported from tweakcn.",
        font: "var(--font-sans)",
        colors: {
            primary: "oklch(0.71 0.15 239.15)",
            secondary: "oklch(0.91 0.03 229.20)",
            accent: "oklch(0.88 0.02 235.72)",
        },
    },
    {
        id: "caffeine",
        name: "Caffeine",
        description: "Imported from tweakcn.",
        font: "ui-sans-serif",
        colors: {
            primary: "oklch(0.4341 0.0392 41.9938)",
            secondary: "oklch(0.9200 0.0651 74.3695)",
            accent: "oklch(0.9310 0 0)",
        },
    },
];
