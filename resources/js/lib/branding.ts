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
    logo: string;
    favicon: string;
}

export const DEFAULT_BRANDING: Branding = {
    appName: "KoAkademy",
    appShortName: "KOA",
    organizationName: "KoAkademy",
    organizationShortName: "KOA",
    organizationAddress: null,
    supportEmail: null,
    supportPhone: null,
    tagline: "Your Campus, Your Connection",
    copyrightText: `${new Date().getFullYear()} KoAkademy. All rights reserved.`,
    themeColor: "#0f172a",
    currency: "PHP",
    logo: "/web-app-manifest-192x192.png",
    favicon: "/web-app-manifest-192x192.png",
};

export function resolveBranding(branding?: Partial<Branding> | null): Branding {
    return {
        appName: branding?.appName ?? DEFAULT_BRANDING.appName,
        appShortName: branding?.appShortName ?? DEFAULT_BRANDING.appShortName,
        organizationName: branding?.organizationName ?? DEFAULT_BRANDING.organizationName,
        organizationShortName: branding?.organizationShortName ?? DEFAULT_BRANDING.organizationShortName,
        organizationAddress: branding?.organizationAddress ?? DEFAULT_BRANDING.organizationAddress,
        supportEmail: branding?.supportEmail ?? DEFAULT_BRANDING.supportEmail,
        supportPhone: branding?.supportPhone ?? DEFAULT_BRANDING.supportPhone,
        tagline: branding?.tagline ?? DEFAULT_BRANDING.tagline,
        copyrightText: branding?.copyrightText ?? DEFAULT_BRANDING.copyrightText,
        themeColor: branding?.themeColor ?? DEFAULT_BRANDING.themeColor,
        currency: branding?.currency ?? DEFAULT_BRANDING.currency,
        logo: branding?.logo ?? DEFAULT_BRANDING.logo,
        favicon: branding?.favicon ?? DEFAULT_BRANDING.favicon,
    };
}

export function useBranding(): Branding {
    const { props } = usePage<{ branding?: Partial<Branding> | null }>();

    return resolveBranding(props.branding);
}
