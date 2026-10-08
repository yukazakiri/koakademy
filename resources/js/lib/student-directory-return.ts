export function studentDirectoryReturnUrl(pageUrl: string, indexUrl: string): string {
    const baseUrl = "https://student-directory.invalid";

    try {
        const returnTo = new URL(pageUrl, baseUrl).searchParams.get("return_to");

        // Only root-relative targets may supply a query; the generated index defines the allowed path.
        if (!returnTo?.startsWith("/") || returnTo.startsWith("//") || returnTo.includes("\\")) {
            return indexUrl;
        }

        if (Array.from(returnTo).some((character) => character.charCodeAt(0) <= 32 || character.charCodeAt(0) === 127)) {
            return indexUrl;
        }

        decodeURI(returnTo);

        const target = new URL(returnTo, baseUrl);
        const index = new URL(indexUrl, baseUrl);

        if (target.origin !== baseUrl || target.pathname !== index.pathname) {
            return indexUrl;
        }

        return target.pathname + target.search;
    } catch {
        return indexUrl;
    }
}
