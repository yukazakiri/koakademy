import { Head, usePage } from "@inertiajs/react";

interface SeoProps {
    title?: string;
    description?: string;
    keywords?: string;
    image?: string;
    type?: string;
    robots?: string;
    canonical?: string;
    twitterCard?: string;
}

interface GeneralSettings {
    site_name?: string;
    site_description?: string;
    seo_title?: string;
    seo_keywords?: string;
    seo_metadata?: {
        robots?: string;
        og_title?: string;
        og_description?: string;
        og_image?: string;
        twitter_handle?: string;
        twitter_card?: string;
        canonical_url?: string;
    };
}

interface Branding {
    appName: string;
    appShortName: string;
}

export function SeoHead({ title, description, keywords, image, type = "website", robots, canonical, twitterCard }: SeoProps) {
    const { props } = usePage<{ branding?: Branding }>();
    const settings = (props.settings as GeneralSettings) || {};
    const url = window.location.href;

    // Merge page-specific props with global settings
    const siteName = props.branding?.appName || settings.site_name || "School Portal";
    const metaTitle = title ? `${title} | ${siteName}` : settings.seo_title || siteName;

    const metaDescription = description || settings.site_description || settings.seo_metadata?.og_description || "";
    const metaKeywords = keywords || settings.seo_keywords || "";
    // Determine Robots logic based on domain
    const hostname = typeof window !== "undefined" ? window.location.hostname : "";
    const isPortal = hostname.startsWith("portal.") || hostname.includes("portal");
    const isAdmin = hostname.startsWith("admin.") || hostname.includes("admin");

    // If it's portal or admin, default to noindex unless explicitly overridden by page props (unlikely)
    // Otherwise use global setting
    let effectiveRobots = robots || settings.seo_metadata?.robots || "index, follow";

    if (isPortal || isAdmin) {
        effectiveRobots = "noindex, nofollow";
    }

    const metaImage = image || settings.seo_metadata?.og_image || "/og-image.jpg";
    const metaCanonical = canonical || settings.seo_metadata?.canonical_url || url;
    const metaTwitterCard = twitterCard || settings.seo_metadata?.twitter_card || "summary_large_image";

    return (
        <Head>
            <title>{metaTitle}</title>
            <meta name="description" content={metaDescription} />
            <meta name="keywords" content={metaKeywords} />
            <meta name="robots" content={effectiveRobots} />
            <link rel="canonical" href={metaCanonical} />

            {/* Open Graph / Facebook */}
            <meta property="og:type" content={type} />
            <meta property="og:url" content={url} />
            <meta property="og:title" content={metaTitle} />
            <meta property="og:description" content={metaDescription} />
            <meta property="og:image" content={metaImage} />
            <meta property="og:site_name" content={siteName} />

            {/* Twitter */}
            <meta name="twitter:card" content={metaTwitterCard} />
            <meta name="twitter:url" content={url} />
            <meta name="twitter:title" content={metaTitle} />
            <meta name="twitter:description" content={metaDescription} />
            <meta name="twitter:image" content={metaImage} />
            {settings.seo_metadata?.twitter_handle && <meta name="twitter:site" content={settings.seo_metadata.twitter_handle} />}
        </Head>
    );
}
