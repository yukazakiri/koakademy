<?php

declare(strict_types=1);

function workflowContents(string $name): string
{
    return file_get_contents(base_path(".github/workflows/{$name}")) ?: '';
}

it('runs one fork-safe CI workflow for master, pull requests, merge queues, and dispatches', function (): void {
    $ci = workflowContents('ci.yml');

    expect($ci)
        ->toContain("push:\n    branches:\n      - master")
        ->toContain("pull_request:\n    branches:\n      - master")
        ->toContain('merge_group:')
        ->toContain('workflow_dispatch:')
        ->toContain('permissions: {}')
        ->toContain('name: Conventional PR title')
        ->toContain('name: Validate normalized title')
        ->toContain('Waiting for title normalization')
        ->toContain('name: required')
        ->toContain('ini-values: memory_limit=1G')
        ->toContain('push: false')
        ->toContain('linux/amd64')
        ->toContain('linux/arm64')
        ->toContain('ubuntu-24.04-arm')
        ->toContain('actionlint')
        ->toContain('zizmor')
        ->not->toContain('pull_request_target');
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

it('gates privileged delivery on a successful trusted CI push from master', function (): void {
    $delivery = workflowContents('delivery.yml');

    expect($delivery)
        ->toContain("workflows:\n      - CI")
        ->toContain("github.event.workflow_run.event == 'push'")
        ->toContain("github.event.workflow_run.conclusion == 'success'")
        ->toContain("github.event.workflow_run.head_branch == 'master'")
        ->toContain('github.event.workflow_run.head_repository.full_name == github.repository')
        ->toContain('needs.trust.result')
        ->toContain('secrets.RELEASE_PLEASE_TOKEN');
});

it('publishes immutable SHA images to both registries before moving channel aliases', function (): void {
    $delivery = workflowContents('delivery.yml');
    $immutableBuild = mb_strpos($delivery, 'Build and push immutable multi-platform images');
    $verification = mb_strpos($delivery, 'Verify registries, architectures, SBOM, and provenance');
    $promotion = mb_strpos($delivery, 'Promote verified channel aliases');

    expect($delivery)
        ->toContain('ghcr.io/${GITHUB_REPOSITORY}:sha-${SOURCE_SHA}')
        ->toContain('${{ vars.DOCKERHUB_IMAGE }}:sha-${SOURCE_SHA}')
        ->toContain('provenance: mode=max')
        ->toContain('sbom: true')
        ->toContain('actions/attest@')
        ->toContain('"${RUNNER_TEMP}/crane" copy')
        ->toContain('promote_mutable_pair edge')
        ->toContain('promote_mutable_pair latest')
        ->toContain('promote_mutable_pair "${major}.${minor}"')
        ->toContain('promote_mutable_pair "${major}"')
        ->and($immutableBuild)->toBeInt()
        ->and($verification)->toBeGreaterThan($immutableBuild)
        ->and($promotion)->toBeGreaterThan($verification);
});

it('keeps recovery tooling and provenance bound to the workflow that built the image', function (): void {
    $delivery = workflowContents('delivery.yml');
    $conditionalAttestations = mb_substr_count(
        $delivery,
        "if: steps.existing.outputs.build_required == 'true'\n        uses: actions/attest@",
    );

    expect($delivery)
        ->toContain('name: Load metadata generator from workflow revision')
        ->toContain('WORKFLOW_SHA: ${{ github.workflow_sha }}')
        ->toContain('"${RUNNER_TEMP}/generate-version-metadata.sh"')
        ->toContain('name: Restore metadata from reused immutable image')
        ->toContain('"${RUNNER_TEMP}/crane" export')
        ->toContain('--platform linux/amd64')
        ->toContain('.metadata.channel == $channel')
        ->and($conditionalAttestations)->toBe(2);
});

it('keeps latest stable-only and recovery unable to invent or move versions', function (): void {
    $delivery = workflowContents('delivery.yml');

    expect($delivery)
        ->toContain('if [[ ! "${RECOVERY_TAG}" =~ ^v[0-9]+\\.[0-9]+\\.[0-9]+$ ]]')
        ->toContain('Tag %s does not exist in %s.')
        ->toContain('Refusing to move immutable tag')
        ->toContain('if [[ "${CHANNEL}" == "edge" ]]')
        ->toContain('A newer master commit exists')
        ->not->toContain('dev-latest')
        ->not->toContain('-dev.');
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

    expect($manifest['.'])
        ->toBe($tracked['version'])
        ->toMatch('/^[0-9]+\.[0-9]+\.[0-9]+$/')
        ->and($config['packages']['.']['release-type'])->toBe('php')
        ->and($config['include-v-in-tag'])->toBeTrue()
        ->and($config['include-component-in-tag'])->toBeFalse()
        ->and($config['draft'])->toBeTrue()
        ->and($config['force-tag-creation'])->toBeTrue()
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
