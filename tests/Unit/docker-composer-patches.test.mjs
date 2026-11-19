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
}
