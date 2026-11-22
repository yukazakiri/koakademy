import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { test } from "node:test";

const root = new URL("../../", import.meta.url);

function validateYamlNoDuplicateKeys(yamlText) {
    const documents = yamlText.split(/^---\s*$/m);
    for (const doc of documents) {
        const scopes = new Map();
        const lines = doc.split(/\r?\n/);
        for (let i = 0; i < lines.length; i++) {
            const rawLine = lines[i];
            if (/^\s*(?:#.*)?$/.test(rawLine)) continue;
            if (/^\t/.test(rawLine)) {
                throw new Error(`Tab indentation is not allowed in YAML (line ${i + 1})`);
            }
            const indentMatch = rawLine.match(/^( *)/);
            const indent = indentMatch ? indentMatch[1].length : 0;
            const trimmed = rawLine.trim();

            for (const level of [...scopes.keys()]) {
                if (level > indent) {
                    scopes.delete(level);
                }
            }

            if (trimmed.startsWith("- ")) {
                for (const level of [...scopes.keys()]) {
                    if (level >= indent) {
                        scopes.delete(level);
                    }
                }
                continue;
            }

            const keyMatch = trimmed.match(/^([A-Za-z0-9_.-]+|"[^"]+"|'[^']+')\s*:(?:\s+.*)?$/);
            if (keyMatch) {
                const key = keyMatch[1];
                let keysAtIndent = scopes.get(indent);
                if (!keysAtIndent) {
                    keysAtIndent = new Set();
                    scopes.set(indent, keysAtIndent);
                }
                if (keysAtIndent.has(key)) {
                    throw new Error(`Duplicate YAML key '${key}' at line ${i + 1}`);
                }
                keysAtIndent.add(key);
            }
        }
    }
}

test("YAML validator rejects duplicate keys at the same mapping level", () => {
    assert.throws(
        () =>
            validateYamlNoDuplicateKeys(
                "services:\n  app:\n    depends_on:\n      migrate:\n        condition: service_completed_successfully\n    depends_on:\n      postgres:\n        condition: service_healthy\n",
            ),
        /Duplicate YAML key 'depends_on'/,
    );
});

const yamlManifests = [
    "compose.production.yaml",
    "scripts/swarm-stack.yml",
    "scripts/swarm-stack-direct.yml",
];

for (const manifest of yamlManifests) {
    test(`${manifest} is valid YAML and includes app and scheduler services`, () => {
        const content = readFileSync(new URL(manifest, root), "utf8");
        assert.doesNotThrow(() => validateYamlNoDuplicateKeys(content));
        assert.match(content, /^  app:\s*$/m, `${manifest} must define an app service`);
        assert.match(content, /^  scheduler:\s*$/m, `${manifest} must define a scheduler service`);
        assert.match(
            content,
            /CONTAINER_MODE:\s*"?scheduler"?/,
            `${manifest} scheduler service must set CONTAINER_MODE=scheduler`,
        );
    });
}

test("scripts/swarm-stack-direct.yml does not default TRUSTED_PROXIES to wildcard", () => {
    const content = readFileSync(new URL("scripts/swarm-stack-direct.yml", root), "utf8");
    assert.ok(
        !/TRUSTED_PROXIES:\s*"\*"/.test(content),
        "Direct-access stack must not trust wildcard proxies on a public host port",
    );
});

for (const unit of [
    "deploy/systemd/koakademy-web.service",
    "deploy/systemd/koakademy-worker.service",
    "deploy/systemd/koakademy-ssr.service",
    "deploy/systemd/koakademy-scheduler.service",
    "deploy/systemd/koakademy-scheduler.timer",
]) {
    test(`${unit} exists for bare-metal systemd deployments`, () => {
        assert.ok(existsSync(new URL(unit, root)), `${unit} must exist`);
    });
}

const platformGuides = [
    "docs/src/content/docs/self-hosting/docker-compose.mdx",
    "docs/src/content/docs/self-hosting/dokploy.mdx",
    "docs/src/content/docs/self-hosting/coolify.mdx",
    "docs/src/content/docs/self-hosting/docker-swarm.mdx",
    "docs/src/content/docs/self-hosting/kubernetes.mdx",
    "docs/src/content/docs/self-hosting/openshift.mdx",
];

for (const guide of platformGuides) {
    test(`${guide} has valid YAML blocks, FrankenPHP image tags, and a scheduler workload`, () => {
        const content = readFileSync(new URL(guide, root), "utf8");
        assert.ok(
            /CONTAINER_MODE:\s*"?scheduler"?/.test(content) ||
                /name:\s*CONTAINER_MODE[\s\S]{0,80}?value:\s*"?scheduler"?/.test(content),
            `${guide} must define a scheduler workload`,
        );
        assert.ok(
            !content.includes("/var/lib/postgresql/data"),
            `${guide} must not mount PostgreSQL 18 at /var/lib/postgresql/data`,
        );
        assert.ok(
            !/ghcr\.io\/yukazakiri\/koakademy:(?:latest|v\d+\.\d+\.\d+)(?!-franken)\b/.test(content),
            `${guide} must use -franken image tags for FrankenPHP deployments`,
        );

        const blocks = [...content.matchAll(/```yaml\r?\n([\s\S]*?)```/g)];
        assert.ok(blocks.length > 0, `${guide} must contain at least one YAML manifest block`);
        for (const block of blocks) {
            assert.doesNotThrow(
                () => validateYamlNoDuplicateKeys(block[1]),
                `${guide} YAML block must be valid without duplicate keys`,
            );
        }
    });
}
