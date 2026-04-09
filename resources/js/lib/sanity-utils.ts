export function urlForImage(source: any, projectId: string, dataset: string) {
    if (!source?.asset?._ref) {
        return null;
    }

    const ref = source.asset._ref;
    // Asset ID format: image-<hash>-<width>x<height>-<extension>
    // Example: image-00a3b1cffe0859def94993c4a8f73d5f47ae87d9-1280x640-jpg

    const pattern = /^image-([a-f0-9]+)-(\d+x\d+)-(\w+)$/;
    const match = ref.match(pattern);

    if (!match) {
        return null;
    }

    const [, hash, dimensions, extension] = match;

    return `https://cdn.sanity.io/images/${projectId}/${dataset}/${hash}-${dimensions}.${extension}`;
}
