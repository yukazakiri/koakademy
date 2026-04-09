export interface VersionData {
    version: string;
    image: string;
    commit: string;
    branch: string;
    timestamp: string;
    build_url: string;
    release_type: "major" | "minor" | "patch";
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
    release_type: "major" | "minor" | "patch";
    commit: string | null;
    build_url: string | null;
    timestamp: string | null;
    is_latest: boolean;
}

export interface ChangelogEntry {
    version: string;
    date: string;
    type: "major" | "minor" | "patch";
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
    version: string;
    versionInfo?: VersionInfo;
    changelog: ChangelogEntry[];
}
