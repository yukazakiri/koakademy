import { usePage } from "@inertiajs/react";

export interface Branding {
    appName: string;
    appShortName: string;
    organizationName: string;
    organizationShortName: string;
    organizationAddress: string | null;
    supportEmail: string | null;
    supportPhone: string | null;
    tagline: string;
    copyrightText: string;
    themeColor: string;
    currency: string;
    authLayout: "card" | "split" | "minimal";
    logo: string;
    favicon: string;
}

const getInitialAppName = (): string => {
    if (typeof window !== "undefined") {
        const appName = (window as unknown as { appName?: string }).appName;
        if (typeof appName === "string" && appName.trim() !== "") {
            return appName.trim();
        }
    }
    return "Portal";
};

export const DEFAULT_BRANDING: Branding = {
    appName: getInitialAppName(),
    appShortName: "Portal",
    organizationName: getInitialAppName(),
    organizationShortName: "Portal",
    organizationAddress: null,
    supportEmail: null,
    supportPhone: null,
    tagline: "Your Campus, Your Connection",
    copyrightText: `${new Date().getFullYear()} All rights reserved.`,
    themeColor: "#0f172a",
    currency: "PHP",
    authLayout: "split",
    logo: "/logo.png",
    favicon: "/logo.png",
};

export function resolveBranding(branding?: Partial<Branding> | null): Branding {
    const orgName =
        branding?.organizationName && branding.organizationName.trim() !== ""
            ? branding.organizationName.trim()
            : branding?.appName && branding.appName.trim() !== ""
              ? branding.appName.trim()
              : DEFAULT_BRANDING.organizationName;

    const appName =
        branding?.appName && branding.appName.trim() !== ""
            ? branding.appName.trim()
            : DEFAULT_BRANDING.appName;

    const orgShortName =
        branding?.organizationShortName && branding.organizationShortName.trim() !== ""
            ? branding.organizationShortName.trim()
            : branding?.appShortName && branding.appShortName.trim() !== ""
              ? branding.appShortName.trim()
              : DEFAULT_BRANDING.organizationShortName;

    const appShortName =
        branding?.appShortName && branding.appShortName.trim() !== ""
            ? branding.appShortName.trim()
            : orgShortName;

    const currentYear = new Date().getFullYear();
    const fallbackCopyright = `${currentYear} ${orgName}. All rights reserved.`;

    return {
        appName,
        appShortName,
        organizationName: orgName,
        organizationShortName: orgShortName,
        organizationAddress: branding?.organizationAddress ?? DEFAULT_BRANDING.organizationAddress,
        supportEmail: branding?.supportEmail ?? DEFAULT_BRANDING.supportEmail,
        supportPhone: branding?.supportPhone ?? DEFAULT_BRANDING.supportPhone,
        tagline:
            branding?.tagline && branding.tagline.trim() !== ""
                ? branding.tagline.trim()
                : DEFAULT_BRANDING.tagline,
        copyrightText:
            branding?.copyrightText && branding.copyrightText.trim() !== ""
                ? branding.copyrightText.trim()
                : fallbackCopyright,
        themeColor: branding?.themeColor ?? DEFAULT_BRANDING.themeColor,
        currency: branding?.currency ?? DEFAULT_BRANDING.currency,
        authLayout: branding?.authLayout ?? DEFAULT_BRANDING.authLayout,
        logo:
            branding?.logo && branding.logo.trim() !== ""
                ? branding.logo.trim()
                : DEFAULT_BRANDING.logo,
        favicon:
            branding?.favicon && branding.favicon.trim() !== ""
                ? branding.favicon.trim()
                : DEFAULT_BRANDING.favicon,
    };
}

export function useBranding(): Branding {
    const { props } = usePage<{ branding?: Partial<Branding> | null }>();

    return resolveBranding(props.branding);
}
