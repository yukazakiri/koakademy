import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import { readFileSync } from "node:fs";
import { test } from "node:test";

const root = new URL("../../", import.meta.url);
const lock = JSON.parse(readFileSync(new URL("patches.lock.json", root), "utf8"));

for (const dockerfile of ["docker/Dockerfile", "docker/Dockerfile.franken"]) {
    test(`${dockerfile} includes locked local patches before Composer install`, () => {
        const contents = readFileSync(new URL(dockerfile, root), "utf8");
        const install = contents.indexOf("RUN composer install");
        const lockCopy = contents.indexOf("COPY composer.json composer.lock patches.lock.json ./");
        const patchCopy = contents.indexOf("COPY patches/ ./patches/");
        assert.ok(lockCopy >= 0 && lockCopy < install, "patch lock must be copied before install");
        assert.ok(patchCopy >= 0 && patchCopy < install, "patch files must be copied before install");

        for (const patches of Object.values(lock.patches)) {
            for (const patch of patches) {
                if (!patch.url.startsWith("patches/")) continue;
                const actual = createHash("sha256").update(readFileSync(new URL(patch.url, root))).digest("hex");
                assert.equal(actual, patch.sha256, `${patch.url} must match its lock checksum`);
            }
        }
    });

    test(`${dockerfile} enforces optional native frontend bindings during npm ci before building assets`, () => {
        const contents = readFileSync(new URL(dockerfile, root), "utf8");
        const npmCi = contents.indexOf("RUN npm ci");
        const npmBuild = contents.indexOf("RUN WAYFINDER_GENERATE=false npm run build");
        assert.ok(npmCi >= 0 && npmCi < npmBuild, "npm ci must run before npm run build");

        const installBlock = contents.slice(npmCi, npmBuild);
        assert.match(installBlock, /--include=dev/, "npm ci must explicitly include dev dependencies");
        assert.match(installBlock, /--include=optional/, "npm ci must explicitly include optional native dependencies");
        assert.match(installBlock, /req\("lightningcss"\)/, "npm ci layer must verify lightningcss native binding");
        assert.match(installBlock, /req\("@tailwindcss\/oxide"\)/, "npm ci layer must verify @tailwindcss/oxide native binding");
        assert.match(installBlock, /req\("rolldown"\)/, "npm ci layer must verify rolldown native binding");
    });
}
