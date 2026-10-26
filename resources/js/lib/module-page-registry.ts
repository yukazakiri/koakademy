/**
 * Match the server's discovered directory; package names need not match module names.
 */
export function modulePageExists(pagePaths: string[], moduleName: string, inertiaPage: string, modulePath?: string | null): boolean {
    const directory = modulePath ? `/${modulePath.replaceAll("\\", "/")}/` : `/Modules/${moduleName}/`;
    const suffix = `/resources/assets/js/Pages/${inertiaPage}.tsx`;

    return pagePaths.some((path) => {
        const normalized = path.replaceAll("\\", "/");
        return normalized.includes(directory) && normalized.endsWith(suffix);
    });
}
