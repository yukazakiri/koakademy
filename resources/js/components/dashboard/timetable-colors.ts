const cache = new Map<string, TimetableColorTokens>();

export interface TimetableColorTokens {
    fill: string;
    border: string;
    glow: string;
    badgeBg: string;
    badgeText: string;
    chipBg: string;
    chipBorder: string;
    chipText: string;
}

export function getTimetableColorTokens(key: string): TimetableColorTokens {
    if (cache.has(key)) {
        return cache.get(key)!;
    }

    let hash = 0;
    for (let i = 0; i < key.length; i++) {
        hash = key.charCodeAt(i) + ((hash << 5) - hash);
    }

    const hue = Math.abs(hash) % 360;
    const base = `hsl(${hue} 70% 60%)`;
    const border = `hsl(${hue} 65% 45%)`;
    const badgeBg = `hsl(${hue} 70% 55%)`;

    const tokens: TimetableColorTokens = {
        fill: `color-mix(in srgb, ${base} 22%, hsl(var(--background)))`,
        border,
        glow: `0 6px 18px ${base}33`,
        badgeBg,
        badgeText: "hsl(var(--background))",
        chipBg: `color-mix(in srgb, ${base} 12%, hsl(var(--muted)))`,
        chipBorder: `color-mix(in srgb, ${border} 50%, hsl(var(--border)))`,
        chipText: "hsl(var(--foreground))",
    };

    cache.set(key, tokens);

    return tokens;
}
