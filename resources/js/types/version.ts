export interface VersionData {
    version: string;
    image: string;
    commit: string;
    branch: string;
    timestamp: string;
    build_url: string;
    release_type: "major" | "minor" | "patch" | "feature" | "bugfix" | "chore" | "stable" | "edge";
    changelog: {
        current: string;
        previous: string;
    };
    metadata: {
        author: string;
        workflow: string;
        repository: string;
    };
}

export interface VersionInfo {
    version: string;
    release_type: "major" | "minor" | "patch" | "feature" | "bugfix" | "chore" | "stable" | "edge";
    commit: string | null;
    build_url: string | null;
    timestamp: string | null;
    is_latest: boolean;
}

export interface ChangelogEntry {
    title: string;
    version: string;
    date: string;
    published_at?: string | null;
    type: "major" | "minor" | "patch";
    prerelease: boolean;
    source: "github_release" | "build_metadata";
    changes: {
        type: "feature" | "fix" | "improvement" | "breaking" | "security";
        description: string;
    }[];
    github_url?: string | null;
}

export interface ChangelogProps {
    user: {
        name: string;
        email: string;
        avatar: string | null;
        role: string;
    };
    layout?: "admin" | "portal";
    version: string;
    versionInfo?: VersionInfo;
    changelog: ChangelogEntry[];
    changelog_status?: "live" | "stale" | "empty" | "unavailable";
    changelog_last_synced_at?: string | null;
    github_repo?: string | null;
}
