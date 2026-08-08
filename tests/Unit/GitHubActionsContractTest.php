<?php

declare(strict_types=1);

function workflowContents(string $name): string
{
    return file_get_contents(base_path(".github/workflows/{$name}")) ?: '';
}

it('runs expensive validation once for pull requests and manual dispatches', function (): void {
    $ci = workflowContents('ci.yml');

    expect($ci)
        ->toContain("pull_request:\n    branches:\n      - master")
        ->toContain('workflow_dispatch:')
        ->toContain('permissions: {}')
        ->toContain('name: Classify pull request')
        ->toContain('name: Detect a trusted Release Please metadata update')
        ->toContain('pull.head.repo?.full_name === expectedRepository')
        ->toContain("pull.head.ref.startsWith('release-please--branches--master--components--')")
        ->toContain('changedFiles.size === allowedFiles.size')
        ->toContain('name: Validate Release Please metadata')
        ->toContain('needs.scope.outputs.release_only')
        ->toContain('php artisan test --parallel --compact')
        ->toContain('name: Conventional PR title')
        ->toContain('name: Validate normalized title')
        ->toContain('Waiting for title normalization')
        ->toContain('name: required')
        ->toContain('ini-values: memory_limit=1G')
        ->toContain('actionlint')
        ->toContain('zizmor')
        ->not->toContain("push:\n    branches:\n      - master")
        ->not->toContain('merge_group:')
        ->not->toContain('name: Container (')
        ->not->toContain('docker/build-push-action@')
        ->not->toContain('npm --prefix docs ci')
        ->not->toContain('name: Build Astro documentation')
        ->not->toContain('pull_request_target');
});

it('fast-tracks only exact Release Please metadata changes', function (): void {
    $ci = workflowContents('ci.yml');

    expect($ci)
        ->toContain("'.release-please-manifest.json'")
        ->toContain("'CHANGELOG.md'")
        ->toContain("'version.json'")
        ->toContain('manifest_version=')
        ->toContain('tracked_version=')
        ->toContain('"chore(release): release ${tracked_version}"')
        ->toContain('grep --fixed-strings --quiet "## [${tracked_version}]" CHANGELOG.md')
        ->toContain("if: needs.scope.outputs.release_only != 'true'");
});

it('leaves the Astro production build to the documentation deployment workflow', function (): void {
    $ci = workflowContents('ci.yml');
    $documentation = workflowContents('deploy-docs.yml');

    expect($ci)
        ->toContain('name: Check generated docs and local links')
        ->not->toContain('npm --prefix docs run build')
        ->and($documentation)
        ->toContain("paths:\n            - 'docs/**'")
        ->toContain('name: Build Astro site')
        ->toContain('uses: actions/upload-pages-artifact@')
        ->toContain('uses: actions/deploy-pages@');
});

it('normalizes pull request titles without executing contributor code', function (): void {
    $metadata = workflowContents('auto-label.yml');

    expect($metadata)
        ->toContain('pull_request_target:')
        ->toContain('types: [opened, edited, reopened, synchronize, ready_for_review]')
        ->toContain('pull-requests: write')
        ->toContain('name: Normalize pull request title')
        ->toContain('github.rest.pulls.listFiles')
        ->toContain('github.rest.pulls.update')
        ->toContain("type = 'fix'")
        ->toContain("type = 'feat'")
        ->toContain("type = 'ci'")
        ->toContain("type = 'docs'")
        ->toContain("type = 'test'")
        ->toContain("type = 'build'")
        ->toContain("type = 'chore'")
        ->not->toContain('actions/checkout@')
        ->not->toContain('pull.head.sha');
});

it('starts privileged delivery directly from a trusted master push', function (): void {
    $delivery = workflowContents('delivery.yml');

    expect($delivery)
        ->toContain("push:\n    branches:\n      - master")
        ->toContain("github.event_name == 'push'")
        ->toContain("github.ref == 'refs/heads/master'")
        ->toContain('github.event.repository.full_name == github.repository')
        ->toContain('SOURCE_SHA: ${{ github.event.after }}')
        ->toContain('[[ "${SOURCE_SHA}" == "${EXPECTED_SHA}" ]]')
        ->toContain('needs.trust.result')
        ->toContain('secrets.RELEASE_PLEASE_TOKEN')
        ->not->toContain('workflow_run');
});

it('publishes every automatic master commit to edge without a second build', function (): void {
    $delivery = workflowContents('delivery.yml');
    $promotion = mb_strpos($delivery, 'Promote verified channel aliases');
    $promotionWorkflow = mb_substr($delivery, $promotion);

    expect($delivery)
        ->toContain('mode: ${{ steps.plan.outputs.mode }}')
        ->toContain("channel=edge\n            publish=true")
        ->toContain('if [[ "${MODE}" == "automatic" ]]')
        ->toContain('promote_mutable_pair edge')
        ->toContain('if [[ "${CHANNEL}" == "stable" ]]')
        ->not->toContain('git diff-tree')
        ->not->toContain('relevant_pattern')
        ->and($promotion)->toBeInt()
        ->and($promotionWorkflow)->toContain('MODE: ${{ needs.publish.outputs.mode }}');
});

it('publishes immutable SHA images to both registries before moving channel aliases', function (): void {
    $delivery = workflowContents('delivery.yml');
    $immutableBuild = mb_strpos($delivery, 'Build and push immutable');
    $verification = mb_strpos($delivery, 'Verify registries, architectures, SBOM, and provenance');
    $promotion = mb_strpos($delivery, 'Promote verified channel aliases');

    expect($delivery)
        ->toContain('ghcr.io/${GITHUB_REPOSITORY}:sha-${SOURCE_SHA}')
        ->toContain('"${DOCKERHUB_IMAGE}:sha-${SOURCE_SHA}"')
        ->toContain('ghcr.io/${GITHUB_REPOSITORY}:sha-${SOURCE_SHA}-franken')
        ->toContain('"${DOCKERHUB_IMAGE}:sha-${SOURCE_SHA}-franken"')
        ->toContain('file: docker/Dockerfile.franken')
        ->toContain('provenance: mode=max')
        ->toContain('sbom: true')
        ->toContain('actions/attest@')
        ->toContain('"${RUNNER_TEMP}/crane" copy')
        ->toContain('promote_mutable_pair edge')
        ->toContain('promote_mutable_pair latest')
        ->toContain('promote_mutable_pair "${major}.${minor}"')
        ->toContain('promote_mutable_pair "${major}"')
        ->toContain('promote_mutable_franken_pair edge')
        ->toContain('promote_mutable_franken_pair latest')
        ->toContain('promote_mutable_franken_pair "${major}.${minor}"')
        ->toContain('promote_mutable_franken_pair "${major}"')
        ->toContain('--output version-franken.json')
        ->and($immutableBuild)->toBeInt()
        ->and($verification)->toBeGreaterThan($immutableBuild)
        ->and($promotion)->toBeGreaterThan($verification);
});

it('never routes image references through job outputs', function (): void {
    $delivery = workflowContents('delivery.yml');

    expect($delivery)
        ->not->toContain('outputs.ghcr_ref')
        ->not->toContain('outputs.dockerhub_ref')
        ->toContain('tags: ghcr.io/${{ github.repository }}:sha-${{ needs.publish.outputs.source_sha }}-${{ matrix.platform }}')
        ->toContain('tags: ghcr.io/${{ github.repository }}:sha-${{ needs.publish.outputs.source_sha }}-franken-${{ matrix.platform }}')
        ->toContain('TARGET_REF: ghcr.io/${{ github.repository }}:sha-${{ needs.publish.outputs.source_sha }}')
        ->toContain('EXPECTED_IMAGE: ghcr.io/${{ github.repository }}:sha-${{ needs.publish.outputs.source_sha }}');
});

it('keeps recovery tooling and provenance bound to the workflow that built the image', function (): void {
    $delivery = workflowContents('delivery.yml');
    $conditionalAttestations = mb_substr_count(
        $delivery,
        "if: needs.publish.outputs.build_required == 'true'\n        uses: actions/attest@",
    );
    $frankenConditionalAttestations = mb_substr_count(
        $delivery,
        "if: needs.publish.outputs.build_required_franken == 'true'\n        uses: actions/attest@",
    );

    expect($delivery)
        ->toContain('name: Load metadata generator from workflow revision')
        ->toContain('WORKFLOW_SHA: ${{ github.workflow_sha }}')
        ->toContain('"${RUNNER_TEMP}/generate-version-metadata.sh"')
        ->toContain('name: Restore metadata from immutable image')
        ->toContain('name: Restore metadata from immutable FrankenPHP image')
        ->toContain('"${RUNNER_TEMP}/crane" export')
        ->toContain('--platform linux/amd64')
        ->toContain('.metadata.channel == $channel')
        ->and($conditionalAttestations)->toBe(2)
        ->and($frankenConditionalAttestations)->toBe(2);
});

it('keeps latest stable-only and recovery unable to invent or move versions', function (): void {
    $delivery = workflowContents('delivery.yml');

    expect($delivery)
        ->toContain('if [[ ! "${RECOVERY_TAG}" =~ ^v[0-9]+\\.[0-9]+\\.[0-9]+$ ]]')
        ->toContain('Tag %s does not exist in %s.')
        ->toContain('Refusing to move immutable tag')
        ->toContain('if [[ "${MODE}" == "automatic" ]]')
        ->toContain('if [[ "${CHANNEL}" == "stable" ]]')
        ->toContain('A newer master commit exists')
        ->not->toContain('dev-latest')
        ->not->toContain('-dev.');
});

it('allows a reviewed manual version request while preserving existing tag recovery', function (): void {
    $delivery = workflowContents('delivery.yml');

    expect($delivery)
        ->toContain('description: Existing stable tag to recover (strict vX.Y.Z; exclusive with version)')
        ->toContain('description: New stable version to request (strict X.Y.Z; exclusive with tag)')
        ->toContain('Provide exactly one of tag (recovery) or version (new release).')
        ->toContain('Manual delivery must run from the master branch.')
        ->toContain('Version v%s already exists; use tag recovery instead.')
        ->toContain('must be newer than existing stable')
        ->toContain('mode=manual-release')
        ->toContain("needs.trust.outputs.mode == 'manual-release'")
        ->toContain('release-as: ${{ needs.trust.outputs.manual_version }}')
        ->toContain('Release Please processed the requested version; no container rebuild is needed.')
        ->not->toContain('gh api --method POST repos/${GITHUB_REPOSITORY}/git/refs');
});

it('keeps Release Please aligned with the tracked stable version and reviewed draft releases', function (): void {
    $config = json_decode(
        file_get_contents(base_path('release-please-config.json')) ?: '{}',
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $manifest = json_decode(
        file_get_contents(base_path('.release-please-manifest.json')) ?: '{}',
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $tracked = json_decode(
        file_get_contents(base_path('version.json')) ?: '{}',
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $changelogSections = array_column($config['changelog-sections'], null, 'type');

    expect($manifest['.'])
        ->toBe($tracked['version'])
        ->toMatch('/^[0-9]+\.[0-9]+\.[0-9]+$/')
        ->and($config['packages']['.']['release-type'])->toBe('php')
        ->and($config['include-v-in-tag'])->toBeTrue()
        ->and($config['include-component-in-tag'])->toBeFalse()
        ->and($config['draft'])->toBeTrue()
        ->and($config['force-tag-creation'])->toBeTrue()
        ->and(array_keys($changelogSections))->toContain(
            'feat',
            'fix',
            'build',
            'ci',
            'docs',
            'refactor',
            'test',
            'style',
            'chore',
        )
        ->and($changelogSections['ci']['hidden'])->toBeFalse()
        ->and($config['packages']['.']['extra-files'][0])
        ->toMatchArray([
            'type' => 'json',
            'path' => 'version.json',
            'jsonpath' => '$.version',
        ]);
});

it('removes commit prerelease workflows and keeps every third-party action SHA-pinned', function (): void {
    foreach ([
        'auto-release.yml',
        'docker-latest.yml',
        'manual-official-release.yml',
        'official-release-on-tag.yml',
    ] as $legacyWorkflow) {
        expect(base_path(".github/workflows/{$legacyWorkflow}"))->not->toBeFile();
    }

    $workflowPaths = glob(base_path('.github/workflows/*.yml')) ?: [];

    foreach ($workflowPaths as $workflowPath) {
        $workflow = file_get_contents($workflowPath) ?: '';
        preg_match_all('/^\s*-?\s*uses:\s*([^#\s]+)(?:\s+#.*)?$/m', $workflow, $matches);

        foreach ($matches[1] as $action) {
            expect($action)
                ->toMatch('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+@[0-9a-f]{40}$/');
        }
    }
});

it('groups Dependabot updates for GitHub Actions and Docker base images', function (): void {
    $dependabot = file_get_contents(base_path('.github/dependabot.yml')) ?: '';

    expect($dependabot)
        ->toContain('package-ecosystem: github-actions')
        ->toContain('github-actions:')
        ->toContain('package-ecosystem: docker')
        ->toContain('docker-base-images:');
});

it('keeps optional roadmap automation green when its dedicated token is unavailable', function (): void {
    $roadmap = workflowContents('project-roadmap-automation.yml');

    expect($roadmap)
        ->toContain('name: Validate roadmap credentials')
        ->toContain("core.setOutput('available', 'false')")
        ->toContain('[401, 403, 404].includes(status)')
        ->toContain("needs.credentials.outputs.available == 'true'")
        ->toContain('Roadmap automation is skipped')
        ->toContain('actions/add-to-project@5afcf98fcd03f1c2f92c3c83f58ae24323cc57fd # v2.0.0')
        ->toContain('actions/github-script@3a2844b7e9c422d3c10d287c895573f7108da1b3 # v9.0.0');
});
