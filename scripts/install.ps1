[CmdletBinding()]
param()

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$Repository = 'yukazakiri/koakademy'
$RustFsRepository = 'rustfs/rustfs'
$InstallerLabel = 'com.koakademy.managed-by=swarm-installer'
$NetworkName = 'koakademy-network'
$AppService = 'koakademy-app'
$PostgresService = 'koakademy-postgres'
$RedisService = 'koakademy-redis'
$GotenbergService = 'koakademy-gotenberg'
$RustFsService = 'koakademy-rustfs'
$AppVolume = 'koakademy-app-storage'
$PostgresVolume = 'koakademy-postgres-data'
$RedisVolume = 'koakademy-redis-data'
$RustFsVolume = 'koakademy-rustfs-data'
$AppKeySecret = 'koakademy-app-key'
$DbPasswordSecret = 'koakademy-db-password'
$RedisPasswordSecret = 'koakademy-redis-password'
$S3AccessKeySecret = 'koakademy-s3-access-key'
$S3SecretKeySecret = 'koakademy-s3-secret-key'
$AppEntrypointConfig = 'koakademy-app-entrypoint-v1'
$RedisEntrypointConfig = 'koakademy-redis-entrypoint-v1'
$StorageInitConfig = 'koakademy-storage-init-v1'
$PostgresImage = if ($env:KOAKADEMY_POSTGRES_IMAGE) { $env:KOAKADEMY_POSTGRES_IMAGE } else { 'postgres:18-alpine' }
$RedisImage = if ($env:KOAKADEMY_REDIS_IMAGE) { $env:KOAKADEMY_REDIS_IMAGE } else { 'redis:8-alpine' }
$GotenbergImage = if ($env:KOAKADEMY_GOTENBERG_IMAGE) { $env:KOAKADEMY_GOTENBERG_IMAGE } else { 'gotenberg/gotenberg:8' }
$AwsCliImage = if ($env:KOAKADEMY_AWS_CLI_IMAGE) { $env:KOAKADEMY_AWS_CLI_IMAGE } else { 'amazon/aws-cli:2' }
$AlpineImage = if ($env:KOAKADEMY_ALPINE_IMAGE) { $env:KOAKADEMY_ALPINE_IMAGE } else { 'alpine:3.22' }

$AppPort = if ($env:KOAKADEMY_APP_PORT) { [int]$env:KOAKADEMY_APP_PORT } else { 8000 }
$RustFsPort = if ($env:KOAKADEMY_RUSTFS_PORT) { [int]$env:KOAKADEMY_RUSTFS_PORT } else { 9000 }
$AppUrl = $env:KOAKADEMY_APP_URL
$StorageMode = $env:KOAKADEMY_STORAGE
$KoAkademyVersion = $env:KOAKADEMY_VERSION
$RustFsVersion = $env:RUSTFS_VERSION
$CurrentNode = ''
$AppHost = ''
$SessionSecureCookie = 'false'
$AwsAccessKeyValue = ''
$AwsSecretKeyValue = ''
$AwsRegionValue = ''
$AwsBucketValue = ''
$AwsEndpointValue = ''
$AwsUrlValue = ''
$AwsPathStyleValue = 'true'
$TempEnvFile = ''

function Write-Info {
    param([Parameter(Mandatory)][string]$Message)
    Write-Host "==> $Message"
}

function Write-WarningMessage {
    param([Parameter(Mandatory)][string]$Message)
    Write-Warning $Message
}

function Stop-Installer {
    param([Parameter(Mandatory)][string]$Message)
    throw $Message
}

function Invoke-Docker {
    param(
        [Parameter(Mandatory)]
        [string[]]$Arguments,
        [switch]$AllowFailure,
        [switch]$DiscardOutput
    )

    if ($DiscardOutput) {
        & docker @Arguments *> $null
    } else {
        & docker @Arguments
    }

    $exitCode = $LASTEXITCODE
    if (-not $AllowFailure -and $exitCode -ne 0) {
        Stop-Installer "Docker command failed: docker $($Arguments -join ' ')"
    }

}

function Get-DockerOutput {
    param(
        [Parameter(Mandatory)]
        [string[]]$Arguments,
        [switch]$AllowFailure
    )

    $output = & docker @Arguments 2>$null
    $exitCode = $LASTEXITCODE
    if (-not $AllowFailure -and $exitCode -ne 0) {
        Stop-Installer "Docker command failed: docker $($Arguments -join ' ')"
    }

    return ($output | Out-String).Trim()
}

function Test-DockerObject {
    param(
        [Parameter(Mandatory)][ValidateSet('service', 'secret', 'config', 'network', 'volume')]
        [string]$Type,
        [Parameter(Mandatory)][string]$Name
    )

    & docker $Type inspect $Name *> $null
    return $LASTEXITCODE -eq 0
}

function Test-ServiceManaged {
    param([Parameter(Mandatory)][string]$Name)

    return (Test-DockerObjectManaged -Type service -Name $Name)
}

function Test-DockerObjectManaged {
    param(
        [Parameter(Mandatory)][ValidateSet('service', 'secret', 'config', 'network', 'volume')]
        [string]$Type,
        [Parameter(Mandatory)][string]$Name
    )

    $template = if ($Type -in @('network', 'volume')) {
        '{{index .Labels "com.koakademy.managed-by"}}'
    } else {
        '{{index .Spec.Labels "com.koakademy.managed-by"}}'
    }
    $managedBy = Get-DockerOutput -Arguments @(
        $Type, 'inspect', '--format', $template, $Name
    ) -AllowFailure

    return $managedBy -eq 'swarm-installer'
}

function Assert-Port {
    param([Parameter(Mandatory)][int]$Port, [Parameter(Mandatory)][string]$Name)
    if ($Port -lt 1 -or $Port -gt 65535) {
        Stop-Installer "$Name must be between 1 and 65535."
    }
}

function Assert-Tag {
    param([Parameter(Mandatory)][string]$Tag, [Parameter(Mandatory)][string]$Name)
    if ($Tag -notmatch '^v?[0-9]+\.[0-9]+\.[0-9]+([.-][A-Za-z0-9][A-Za-z0-9.-]*)?$') {
        Stop-Installer "$Name '$Tag' is not a safe container tag."
    }
}

function ConvertTo-HttpUri {
    param(
        [Parameter(Mandatory)][string]$Value,
        [Parameter(Mandatory)][string]$Name,
        [switch]$OriginOnly
    )

    $uri = $null
    if (-not [Uri]::TryCreate($Value, [UriKind]::Absolute, [ref]$uri)) {
        Stop-Installer "$Name must be an absolute HTTP or HTTPS URL."
    }

    if ($uri.Scheme -notin @('http', 'https') -or [string]::IsNullOrWhiteSpace($uri.Host)) {
        Stop-Installer "$Name must use HTTP or HTTPS and include a hostname or IPv4 address."
    }

    if (-not [string]::IsNullOrEmpty($uri.UserInfo)) {
        Stop-Installer "$Name must not contain embedded credentials."
    }

    if ($uri.Host.Contains(':')) {
        Stop-Installer "$Name does not currently support IPv6 literals."
    }

    if ($OriginOnly -and (
        $uri.AbsolutePath -ne '/' -or
        -not [string]::IsNullOrEmpty($uri.Query) -or
        -not [string]::IsNullOrEmpty($uri.Fragment)
    )) {
        Stop-Installer "$Name must contain only the scheme, hostname/IP, and optional port."
    }

    return $uri
}

function Assert-Bucket {
    param([Parameter(Mandatory)][string]$Bucket)
    if ($Bucket.Length -lt 3 -or $Bucket.Length -gt 63 -or
        $Bucket -notmatch '^[a-z0-9][a-z0-9.-]*[a-z0-9]$') {
        Stop-Installer 'S3 bucket names must be 3-63 lowercase letters, digits, dots, or hyphens.'
    }
}

function Assert-Region {
    param([Parameter(Mandatory)][string]$Region)
    if ($Region -notmatch '^[A-Za-z0-9-]+$') {
        Stop-Installer 'S3 region contains unsupported characters.'
    }
}

function Assert-SingleLine {
    param(
        [AllowEmptyString()]
        [Parameter(Mandatory)][string]$Value,
        [Parameter(Mandatory)][string]$Name
    )

    if ($Value.Contains("`r") -or $Value.Contains("`n")) {
        Stop-Installer "$Name must not contain line breaks."
    }
}

function Read-Value {
    param(
        [Parameter(Mandatory)][string]$Message,
        [string]$Default = ''
    )

    $prompt = if ($Default) { "$Message [$Default]" } else { $Message }
    $value = Read-Host $prompt
    if ([string]::IsNullOrWhiteSpace($value)) {
        return $Default
    }

    return $value.Trim()
}

function Read-SecretValue {
    param([Parameter(Mandatory)][string]$Message)

    $secureValue = Read-Host $Message -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureValue)
    try {
        $value = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }

    if ([string]::IsNullOrEmpty($value)) {
        Stop-Installer "$Message cannot be empty."
    }

    return $value
}

function Read-YesNo {
    param(
        [Parameter(Mandatory)][string]$Message,
        [bool]$Default = $true
    )

    $suffix = if ($Default) { '[Y/n]' } else { '[y/N]' }
    $answer = (Read-Host "$Message $suffix").Trim()
    if (-not $answer) {
        return $Default
    }

    return $answer -match '^[Yy]$'
}

function New-RandomHex {
    param([int]$Bytes = 32)

    $random = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $buffer = New-Object byte[] $Bytes
        $random.GetBytes($buffer)
    } finally {
        $random.Dispose()
    }

    return (($buffer | ForEach-Object { $_.ToString('x2') }) -join '')
}

function New-AppKey {
    $random = [Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $buffer = New-Object byte[] 32
        $random.GetBytes($buffer)
    } finally {
        $random.Dispose()
    }

    return "base64:$([Convert]::ToBase64String($buffer))"
}

function Get-GitHubTags {
    param([Parameter(Mandatory)][string]$RepositoryName)

    $tags = [Collections.Generic.List[string]]::new()
    $headers = @{ Accept = 'application/vnd.github+json' }
    if ($env:GITHUB_TOKEN) {
        $headers.Authorization = "Bearer $($env:GITHUB_TOKEN)"
    }

    for ($page = 1; $page -le 10; $page++) {
        try {
            $response = Invoke-RestMethod `
                -Headers $headers `
                -Uri "https://api.github.com/repos/$RepositoryName/tags?per_page=100&page=$page" `
                -TimeoutSec 15
        } catch {
            break
        }

        if (-not $response -or $response.Count -eq 0) {
            break
        }

        foreach ($tag in $response) {
            if ($tag.name) {
                $tags.Add([string]$tag.name)
            }
        }

        if ($response.Count -lt 100) {
            break
        }
    }

    if ($tags.Count -eq 0) {
        try {
            $feed = (Invoke-WebRequest `
                -Uri "https://github.com/$RepositoryName/tags.atom" `
                -UseBasicParsing `
                -TimeoutSec 15).Content
            $matches = [regex]::Matches(
                $feed,
                '<id>tag:github\.com,2008:Repository/[0-9]+/([^<]+)</id>'
            )
            foreach ($match in $matches) {
                $tags.Add([Net.WebUtility]::HtmlDecode($match.Groups[1].Value))
            }
        } catch {
            # The resolver reports a single actionable error when both sources fail.
        }
    }

    return $tags
}

function Get-LatestPublishedGitHubReleaseTag {
    param([Parameter(Mandatory)][string]$RepositoryName)

    $headers = @{ Accept = 'application/vnd.github+json' }
    if ($env:GITHUB_TOKEN) {
        $headers.Authorization = "Bearer $($env:GITHUB_TOKEN)"
    }

    try {
        $release = Invoke-RestMethod `
            -Headers $headers `
            -Uri "https://api.github.com/repos/$RepositoryName/releases/latest" `
            -TimeoutSec 15
    } catch {
        return ''
    }

    if (-not $release -or $release.draft -or $release.prerelease) {
        return ''
    }

    return [string]$release.tag_name
}

function Resolve-KoAkademyVersion {
    if ($KoAkademyVersion) {
        if ($KoAkademyVersion -ceq 'edge') {
            Write-Warning 'KOAKADEMY_VERSION=edge selects the unsupported rolling channel.'
            Write-Warning 'The edge image can change after every green master build; pin an exact vX.Y.Z tag for production.'
            return $KoAkademyVersion
        }
        Assert-Tag -Tag $KoAkademyVersion -Name 'KOAKADEMY_VERSION'
        return $KoAkademyVersion
    }

    Write-Info 'Detecting the latest published stable KoAkademy release from GitHub...'
    $candidate = Get-LatestPublishedGitHubReleaseTag -RepositoryName $Repository

    if (-not $candidate -or $candidate -notmatch '^v?[0-9]+\.[0-9]+\.[0-9]+$') {
        Stop-Installer 'No published stable KoAkademy release could be resolved. The repository must be public, or set KOAKADEMY_VERSION explicitly.'
    }

    Assert-Tag -Tag $candidate -Name 'Resolved KoAkademy version'
    return $candidate
}

function Resolve-RustFsVersion {
    if ($RustFsVersion) {
        Assert-Tag -Tag $RustFsVersion -Name 'RUSTFS_VERSION'
        return ($RustFsVersion -replace '^v', '')
    }

    Write-Info 'Detecting the latest non-preview RustFS tag from GitHub...'
    $tags = Get-GitHubTags -RepositoryName $RustFsRepository
    $candidates = @($tags |
        Where-Object { $_ -match '^v?[0-9]+\.[0-9]+\.[0-9]+$' } |
        Sort-Object { [Version]($_ -replace '^v', '') } -Descending)

    if ($candidates.Count -eq 0) {
        $candidates = @($tags |
            Where-Object { $_ -match '^v?([0-9]+\.[0-9]+\.[0-9]+)-beta\.([0-9]+)$' } |
            Sort-Object `
                { [Version](($_ -replace '^v', '') -replace '-beta\.[0-9]+$', '') },
                { [int]([regex]::Match($_, 'beta\.([0-9]+)$').Groups[1].Value) } `
                -Descending)
    }

    if ($candidates.Count -eq 0) {
        Stop-Installer 'No stable or non-preview RustFS tag could be resolved. Set RUSTFS_VERSION explicitly.'
    }

    foreach ($candidate in $candidates) {
        Assert-Tag -Tag $candidate -Name 'Resolved RustFS version'
        $imageTag = $candidate -replace '^v', ''
        & docker manifest inspect "rustfs/rustfs:$imageTag" *> $null
        if ($LASTEXITCODE -eq 0) {
            return $imageTag
        }
        Write-WarningMessage "Skipping RustFS $candidate`: its Docker image is not published yet."
    }

    Stop-Installer 'GitHub returned RustFS tags, but none has a published rustfs/rustfs image.'
}

function Get-AccessHost {
    try {
        $privateIp = Get-NetIPAddress -AddressFamily IPv4 -AddressState Preferred |
            Where-Object {
                $_.IPAddress -notmatch '^(127\.|169\.254\.)' -and
                $_.InterfaceAlias -notmatch 'Docker|vEthernet'
            } |
            Select-Object -ExpandProperty IPAddress -First 1
        if ($privateIp) {
            return $privateIp
        }
    } catch {
        # Get-NetIPAddress is unavailable on non-Windows PowerShell.
    }

    return 'localhost'
}

function Initialize-SwarmManager {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        Stop-Installer 'Docker is required. Install and start Docker Desktop before running this command.'
    }

    Invoke-Docker -Arguments @('version') -DiscardOutput
    $osType = Get-DockerOutput -Arguments @('info', '--format', '{{.OSType}}')
    $architecture = Get-DockerOutput -Arguments @('info', '--format', '{{.Architecture}}')

    if ($osType -ne 'linux') {
        Stop-Installer "KoAkademy requires Docker Desktop's Linux container engine."
    }
    if ($architecture -notin @('x86_64', 'amd64', 'aarch64', 'arm64')) {
        Stop-Installer 'The published KoAkademy image supports linux/amd64 and linux/arm64.'
    }

    $swarmState = Get-DockerOutput -Arguments @('info', '--format', '{{.Swarm.LocalNodeState}}')
    if ($swarmState -eq 'inactive') {
        Write-Info 'Initializing a single-node Docker Swarm...'
        Invoke-Docker -Arguments @('swarm', 'init') -DiscardOutput
    } elseif ($swarmState -eq 'active') {
        Write-Info 'Docker Swarm is already active; preserving the existing cluster.'
    } else {
        Stop-Installer "Docker Swarm is in unsupported state '$swarmState'."
    }

    $controlAvailable = Get-DockerOutput -Arguments @('info', '--format', '{{.Swarm.ControlAvailable}}')
    if ($controlAvailable -ne 'true') {
        Stop-Installer 'Run the installer on a Docker Swarm manager node.'
    }

    $script:CurrentNode = Get-DockerOutput -Arguments @('info', '--format', '{{.Name}}')
    if ($CurrentNode -notmatch '^[A-Za-z0-9][A-Za-z0-9_.-]*$') {
        Stop-Installer 'Docker node name contains unsupported characters.'
    }
}

function Assert-Image {
    param(
        [Parameter(Mandatory)][string]$Image,
        [Parameter(Mandatory)][string]$Name
    )

    Write-Info "Pulling $Name image $Image..."
    Invoke-Docker -Arguments @('pull', $Image)
}

function Get-ServiceReplicaStatus {
    param([Parameter(Mandatory)][string]$Name)

    # Swarm's name filter is prefix-only (not regex). Exact-match after filtering.
    $rows = Get-DockerOutput -Arguments @(
        'service', 'ls', '--filter', "name=$Name", '--format', '{{.Name}} {{.Replicas}}'
    ) -AllowFailure

    foreach ($row in ($rows -split "`r?`n")) {
        if ([string]::IsNullOrWhiteSpace($row)) {
            continue
        }
        $parts = $row.Trim() -split '\s+', 2
        if ($parts.Count -eq 2 -and $parts[0] -eq $Name) {
            return $parts[1]
        }
    }

    return ''
}

function Get-ServiceLatestTaskField {
    param(
        [Parameter(Mandatory)][string]$Name,
        [Parameter(Mandatory)][string]$Field
    )

    $value = Get-DockerOutput -Arguments @(
        'service', 'ps', '--no-trunc', '--format', "{{.$Field}}", $Name
    ) -AllowFailure

    return (($value -split "`r?`n") | Where-Object { $_ } | Select-Object -First 1)
}

function Test-ServiceReady {
    param([Parameter(Mandatory)][string]$Name)

    $replicas = Get-ServiceReplicaStatus -Name $Name
    if ($replicas -notmatch '^([0-9]+)/([0-9]+)$') {
        return $false
    }

    return ([int]$Matches[1] -ge 1 -and $Matches[1] -eq $Matches[2])
}

function Test-InstallationDetected {
    foreach ($name in @($AppService, $PostgresService, $RedisService, $GotenbergService, $RustFsService)) {
        if ((Test-DockerObject -Type service -Name $name) -and (Test-ServiceManaged -Name $name)) {
            return $true
        }
    }

    return $false
}

function Get-ExpectedServices {
    $services = [System.Collections.Generic.List[string]]::new()
    $services.Add($PostgresService) | Out-Null
    $services.Add($RedisService) | Out-Null
    $services.Add($GotenbergService) | Out-Null
    if ($StorageMode -eq 'rustfs' -or (Test-DockerObject -Type service -Name $RustFsService)) {
        $services.Add($RustFsService) | Out-Null
    }
    $services.Add($AppService) | Out-Null
    return $services
}

function Get-ServiceImage {
    param([Parameter(Mandatory)][string]$Name)

    return (Get-DockerOutput -Arguments @(
        'service', 'inspect', '--format', '{{.Spec.TaskTemplate.ContainerSpec.Image}}', $Name
    ) -AllowFailure)
}

function Get-ImageWithoutDigest {
    param([AllowEmptyString()][string]$Image)

    if ([string]::IsNullOrWhiteSpace($Image)) {
        return ''
    }

    return ($Image -split '@')[0]
}

function Get-ImageTag {
    param([AllowEmptyString()][string]$Image)

    $image = Get-ImageWithoutDigest -Image $Image
    if ([string]::IsNullOrWhiteSpace($image) -or -not $image.Contains(':')) {
        return ''
    }

    return $image.Substring($image.LastIndexOf(':') + 1)
}

function Get-ServicePublishedPort {
    param(
        [Parameter(Mandatory)][string]$Name,
        [Parameter(Mandatory)][int]$TargetPort
    )

    return (Get-DockerOutput -Arguments @(
        'service', 'inspect', '--format',
        "{{range .Endpoint.Spec.Ports}}{{if eq .TargetPort $TargetPort}}{{.PublishedPort}}{{end}}{{end}}",
        $Name
    ) -AllowFailure)
}

function Get-ServiceEnvValue {
    param(
        [Parameter(Mandatory)][string]$Name,
        [Parameter(Mandatory)][string]$Key
    )

    $envBlock = Get-DockerOutput -Arguments @(
        'service', 'inspect', '--format',
        '{{range .Spec.TaskTemplate.ContainerSpec.Env}}{{println .}}{{end}}',
        $Name
    ) -AllowFailure

    foreach ($line in ($envBlock -split "`r?`n")) {
        if ($line -like "$Key=*") {
            return $line.Substring($Key.Length + 1)
        }
    }

    return ''
}

function Remove-ServiceIfPresent {
    param([Parameter(Mandatory)][string]$Name)

    if (-not (Test-DockerObject -Type service -Name $Name)) {
        return
    }

    Write-Info "Removing service $Name..."
    Invoke-Docker -Arguments @('service', 'rm', $Name) -DiscardOutput
    for ($waited = 0; $waited -lt 60; $waited++) {
        if (-not (Test-DockerObject -Type service -Name $Name)) {
            return
        }
        Start-Sleep -Seconds 1
    }

    Stop-Installer "Service $Name could not be removed."
}

function Write-ServiceStatusLine {
    param([Parameter(Mandatory)][string]$Name)

    if (-not (Test-DockerObject -Type service -Name $Name)) {
        Write-Host "  ${Name}: missing"
        return $false
    }

    $replicas = Get-ServiceReplicaStatus -Name $Name
    $state = Get-ServiceLatestTaskField -Name $Name -Field 'CurrentState'
    if (Test-ServiceReady -Name $Name) {
        Write-Host "  ${Name}: healthy ($replicas)"
        return $true
    }

    Write-Host "  ${Name}: unhealthy (replicas=$(if ($replicas) { $replicas } else { 'n/a' }) state=$(if ($state) { $state } else { 'unknown' }))"
    return $false
}

function Write-InstallationStatus {
    Write-Host ''
    Write-Host 'Service status:'
    $healthy = $true
    foreach ($name in (Get-ExpectedServices)) {
        if (-not (Write-ServiceStatusLine -Name $name)) {
            $healthy = $false
        }
    }

    return $healthy
}

function Initialize-RuntimeFromExisting {
    if ((Test-DockerObject -Type service -Name $AppService) -and (Test-ServiceManaged -Name $AppService)) {
        if (-not $AppUrl) {
            $script:AppUrl = Get-ServiceEnvValue -Name $AppService -Key 'APP_URL'
        }

        $published = Get-ServicePublishedPort -Name $AppService -TargetPort 8000
        if ($published -match '^[0-9]+$') {
            $script:AppPort = [int]$published
        }

        $image = Get-ImageWithoutDigest -Image (Get-ServiceImage -Name $AppService)
        if (-not $KoAkademyVersion -and $image) {
            $script:KoAkademyVersion = Get-ImageTag -Image $image
        }

        if (-not $AwsEndpointValue) {
            $script:AwsEndpointValue = Get-ServiceEnvValue -Name $AppService -Key 'AWS_ENDPOINT'
        }
        if (-not $AwsBucketValue) {
            $script:AwsBucketValue = Get-ServiceEnvValue -Name $AppService -Key 'AWS_BUCKET'
        }
        if (-not $AwsRegionValue) {
            $script:AwsRegionValue = Get-ServiceEnvValue -Name $AppService -Key 'AWS_DEFAULT_REGION'
        }
        if (-not $AwsUrlValue) {
            $script:AwsUrlValue = Get-ServiceEnvValue -Name $AppService -Key 'AWS_URL'
        }
        $pathStyle = Get-ServiceEnvValue -Name $AppService -Key 'AWS_USE_PATH_STYLE_ENDPOINT'
        if ($pathStyle) {
            $script:AwsPathStyleValue = $pathStyle
        }
    }

    if ((Test-DockerObject -Type service -Name $RustFsService) -and (Test-ServiceManaged -Name $RustFsService)) {
        if (-not $StorageMode) {
            $script:StorageMode = 'rustfs'
        }
        $image = Get-ImageWithoutDigest -Image (Get-ServiceImage -Name $RustFsService)
        if (-not $RustFsVersion -and $image) {
            $script:RustFsVersion = Get-ImageTag -Image $image
        }
        $published = Get-ServicePublishedPort -Name $RustFsService -TargetPort 9000
        if ($published -match '^[0-9]+$') {
            $script:RustFsPort = [int]$published
        }
    } elseif (-not $StorageMode -and (Test-DockerObject -Type service -Name $AppService)) {
        $script:StorageMode = 'external'
    }

    if (Test-DockerObject -Type service -Name $PostgresService) {
        $image = Get-ImageWithoutDigest -Image (Get-ServiceImage -Name $PostgresService)
        if ($image) { $script:PostgresImage = $image }
    }
    if (Test-DockerObject -Type service -Name $RedisService) {
        $image = Get-ImageWithoutDigest -Image (Get-ServiceImage -Name $RedisService)
        if ($image) { $script:RedisImage = $image }
    }
    if (Test-DockerObject -Type service -Name $GotenbergService) {
        $image = Get-ImageWithoutDigest -Image (Get-ServiceImage -Name $GotenbergService)
        if ($image) { $script:GotenbergImage = $image }
    }
}

function Initialize-Runtime {
    if (Test-InstallationDetected) {
        Write-Info 'Existing KoAkademy deployment detected; checking service health...'
        Initialize-RuntimeFromExisting
    }

    if (-not $AppUrl) {
        Initialize-ApplicationUrl
    } else {
        $script:AppUrl = $AppUrl.TrimEnd('/')
        $uri = ConvertTo-HttpUri -Value $AppUrl -Name 'Application URL' -OriginOnly
        $script:AppHost = $uri.Host
        Assert-Port -Port $AppPort -Name 'KOAKADEMY_APP_PORT'
        if ($uri.Scheme -eq 'https') {
            $script:SessionSecureCookie = 'true'
        } else {
            $script:SessionSecureCookie = 'false'
        }
    }

    if (-not $StorageMode) {
        Initialize-Storage
        return
    }

    switch ($StorageMode) {
        'rustfs' {
            Assert-Port -Port $RustFsPort -Name 'KOAKADEMY_RUSTFS_PORT'
            $script:RustFsVersion = Resolve-RustFsVersion
            if (-not $AwsBucketValue) {
                $script:AwsBucketValue = if ($env:KOAKADEMY_S3_BUCKET) { $env:KOAKADEMY_S3_BUCKET } else { 'koakademy' }
            }
            Assert-Bucket -Bucket $AwsBucketValue
            if (-not $AwsRegionValue) {
                $script:AwsRegionValue = if ($env:KOAKADEMY_S3_REGION) { $env:KOAKADEMY_S3_REGION } else { 'us-east-1' }
            }
            if (-not $AwsEndpointValue) {
                $script:AwsEndpointValue = "http://${RustFsService}:9000"
            }
            if (-not $AwsUrlValue) {
                $script:AwsUrlValue = if ($env:KOAKADEMY_S3_PUBLIC_URL) { $env:KOAKADEMY_S3_PUBLIC_URL } else { "http://${AppHost}:${RustFsPort}/${AwsBucketValue}" }
            }
            ConvertTo-HttpUri -Value $AwsUrlValue -Name 'RustFS public object URL' | Out-Null
            $script:AwsPathStyleValue = 'true'

            if ($AppUrl.StartsWith('https://') -and $AwsUrlValue.StartsWith('http://')) {
                Stop-Installer "HTTPS KoAkademy cannot use an HTTP RustFS public URL. Set KOAKADEMY_S3_PUBLIC_URL to an HTTPS edge for port $RustFsPort."
            }

            if (-not (Test-DockerObject -Type secret -Name $S3AccessKeySecret)) {
                if (-not $AwsAccessKeyValue) {
                    $script:AwsAccessKeyValue = "koa$(New-RandomHex -Bytes 12)"
                }
                if (-not $AwsSecretKeyValue) {
                    $script:AwsSecretKeyValue = New-RandomHex
                }
            }
        }
        'external' {
            if (-not $AwsEndpointValue -or -not $AwsBucketValue -or -not $AwsUrlValue) {
                Initialize-Storage
            } else {
                ConvertTo-HttpUri -Value $AwsEndpointValue -Name 'S3 endpoint' | Out-Null
                Assert-Bucket -Bucket $AwsBucketValue
                if (-not $AwsRegionValue) { $script:AwsRegionValue = 'auto' }
                Assert-Region -Region $AwsRegionValue
                ConvertTo-HttpUri -Value $AwsUrlValue -Name 'S3 public object URL' | Out-Null
                if (-not $AwsPathStyleValue) { $script:AwsPathStyleValue = 'false' }

                if (-not (Test-DockerObject -Type secret -Name $S3AccessKeySecret)) {
                    if ($env:KOAKADEMY_S3_ACCESS_KEY) {
                        $script:AwsAccessKeyValue = $env:KOAKADEMY_S3_ACCESS_KEY
                    } else {
                        $script:AwsAccessKeyValue = Read-Value -Message 'S3 access key ID'
                    }
                    if ([string]::IsNullOrWhiteSpace($AwsAccessKeyValue)) {
                        Stop-Installer 'S3 access key ID cannot be empty.'
                    }

                    if ($env:KOAKADEMY_S3_SECRET_KEY) {
                        $script:AwsSecretKeyValue = $env:KOAKADEMY_S3_SECRET_KEY
                    } else {
                        $script:AwsSecretKeyValue = Read-SecretValue -Message 'S3 secret access key'
                    }
                }
            }
        }
        default {
            Stop-Installer "KOAKADEMY_STORAGE must be 'rustfs' or 'external'."
        }
    }
}

function New-SecretIfMissing {
    param(
        [Parameter(Mandatory)][string]$Name,
        [AllowEmptyString()]
        [Parameter(Mandatory)][string]$Value
    )

    if (Test-DockerObject -Type secret -Name $Name) {
        if (-not (Test-DockerObjectManaged -Type secret -Name $Name)) {
            Stop-Installer "Docker secret $Name exists but is not installer-managed."
        }
        Write-Info "Reusing Docker secret $Name."
        return
    }
    if ([string]::IsNullOrEmpty($Value)) {
        Stop-Installer "Cannot create Docker secret $Name from an empty value."
    }
    Assert-SingleLine -Value $Value -Name "Docker secret $Name"

    $secretFile = [IO.Path]::GetTempFileName()
    try {
        [IO.File]::WriteAllText(
            $secretFile,
            $Value,
            (New-Object Text.UTF8Encoding($false))
        )
        & docker secret create --label $InstallerLabel $Name $secretFile *> $null
        if ($LASTEXITCODE -ne 0) {
            Stop-Installer "Failed to create Docker secret $Name."
        }
    } finally {
        Remove-Item -LiteralPath $secretFile -Force -ErrorAction SilentlyContinue
    }
    Write-Info "Created Docker secret $Name."
}

function New-GeneratedSecretIfMissing {
    param(
        [Parameter(Mandatory)][string]$Name,
        [Parameter(Mandatory)][ValidateSet('app-key', 'password')][string]$Kind
    )

    if (Test-DockerObject -Type secret -Name $Name) {
        if (-not (Test-DockerObjectManaged -Type secret -Name $Name)) {
            Stop-Installer "Docker secret $Name exists but is not installer-managed."
        }
        Write-Info "Reusing Docker secret $Name."
        return
    }

    $value = if ($Kind -eq 'app-key') { New-AppKey } else { New-RandomHex }
    New-SecretIfMissing -Name $Name -Value $value
}

function New-ConfigIfMissing {
    param(
        [Parameter(Mandatory)][string]$Name,
        [Parameter(Mandatory)][string]$Content
    )

    if (Test-DockerObject -Type config -Name $Name) {
        if (-not (Test-DockerObjectManaged -Type config -Name $Name)) {
            Stop-Installer "Docker config $Name exists but is not installer-managed."
        }
        return
    }

    $configFile = [IO.Path]::GetTempFileName()
    try {
        $normalizedContent = ($Content -replace "`r`n", "`n").TrimEnd([char[]]"`r`n") + "`n"
        [IO.File]::WriteAllText(
            $configFile,
            $normalizedContent,
            (New-Object Text.UTF8Encoding($false))
        )
        & docker config create --label $InstallerLabel $Name $configFile *> $null
        if ($LASTEXITCODE -ne 0) {
            Stop-Installer "Failed to create Docker config $Name."
        }
    } finally {
        Remove-Item -LiteralPath $configFile -Force -ErrorAction SilentlyContinue
    }
}

function Initialize-Configs {
    $appEntrypoint = @'
#!/bin/sh
set -eu

load_secret() {
    variable_name="$1"
    secret_path="$2"
    value="$(cat "$secret_path")"
    export "$variable_name=$value"
}

load_secret APP_KEY /run/secrets/koakademy-app-key
load_secret DB_PASSWORD /run/secrets/koakademy-db-password
load_secret REDIS_PASSWORD /run/secrets/koakademy-redis-password
load_secret AWS_ACCESS_KEY_ID /run/secrets/koakademy-s3-access-key
load_secret AWS_SECRET_ACCESS_KEY /run/secrets/koakademy-s3-secret-key

exec /usr/local/bin/start-container "$@"
'@

    $redisEntrypoint = @'
#!/bin/sh
set -eu

redis_password="$(cat /run/secrets/koakademy-redis-password)"
exec redis-server --appendonly yes --requirepass "$redis_password"
'@

    $storageInit = @'
#!/bin/sh
set -eu

export AWS_ACCESS_KEY_ID="$(cat /run/secrets/koakademy-s3-access-key)"
export AWS_SECRET_ACCESS_KEY="$(cat /run/secrets/koakademy-s3-secret-key)"
export AWS_EC2_METADATA_DISABLED=true

aws_s3() {
    aws --no-cli-pager --endpoint-url "$AWS_ENDPOINT" "$@"
}

attempt=0
until aws_s3 s3api head-bucket --bucket "$AWS_BUCKET" >/dev/null 2>&1; do
    attempt=$((attempt + 1))

    if [ "$STORAGE_MODE" = "rustfs" ]; then
        if aws_s3 s3api create-bucket --bucket "$AWS_BUCKET" >/dev/null 2>&1; then
            break
        fi
    fi

    if [ "$attempt" -ge 60 ]; then
        echo "Unable to access S3 bucket '$AWS_BUCKET' at '$AWS_ENDPOINT'." >&2
        exit 1
    fi

    sleep 2
done

if [ "$STORAGE_MODE" = "rustfs" ]; then
    policy="$(printf '{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Principal":"*","Action":["s3:GetObject"],"Resource":["arn:aws:s3:::%s/*"]}]}' "$AWS_BUCKET")"
    cors="$(printf '{"CORSRules":[{"AllowedHeaders":["*"],"AllowedMethods":["GET","HEAD"],"AllowedOrigins":["%s"],"ExposeHeaders":["ETag"],"MaxAgeSeconds":3600}]}' "$APP_ORIGIN")"
    aws_s3 s3api put-bucket-policy --bucket "$AWS_BUCKET" --policy "$policy"
    aws_s3 s3api put-bucket-cors --bucket "$AWS_BUCKET" --cors-configuration "$cors"
fi

echo "S3 bucket '$AWS_BUCKET' is ready."
'@

    New-ConfigIfMissing -Name $AppEntrypointConfig -Content $appEntrypoint
    New-ConfigIfMissing -Name $RedisEntrypointConfig -Content $redisEntrypoint
    New-ConfigIfMissing -Name $StorageInitConfig -Content $storageInit
    Write-Info 'Docker configs are ready.'
}

function Initialize-Network {
    if (Test-DockerObject -Type network -Name $NetworkName) {
        if (-not (Test-DockerObjectManaged -Type network -Name $NetworkName)) {
            Stop-Installer "Docker network $NetworkName exists but is not installer-managed."
        }
        $driver = Get-DockerOutput -Arguments @('network', 'inspect', '--format', '{{.Driver}}', $NetworkName)
        $scope = Get-DockerOutput -Arguments @('network', 'inspect', '--format', '{{.Scope}}', $NetworkName)
        $attachable = Get-DockerOutput -Arguments @('network', 'inspect', '--format', '{{.Attachable}}', $NetworkName)
        if ($driver -ne 'overlay' -or $scope -ne 'swarm' -or $attachable -ne 'true') {
            Stop-Installer "Existing network $NetworkName is not an attachable Swarm overlay."
        }
        Write-Info "Reusing network $NetworkName."
        return
    }

    Invoke-Docker -Arguments @(
        'network', 'create', '--driver', 'overlay', '--attachable',
        '--label', $InstallerLabel, $NetworkName
    ) -DiscardOutput
    Write-Info "Created network $NetworkName."
}

function New-VolumeIfMissing {
    param([Parameter(Mandatory)][string]$Name)

    if (Test-DockerObject -Type volume -Name $Name) {
        if (-not (Test-DockerObjectManaged -Type volume -Name $Name)) {
            Stop-Installer "Docker volume $Name exists but is not installer-managed."
        }
        Write-Info "Reusing volume $Name."
        return
    }

    Invoke-Docker -Arguments @(
        'volume', 'create', '--label', $InstallerLabel, $Name
    ) -DiscardOutput
    Write-Info "Created volume $Name."
}

function Initialize-Volumes {
    New-VolumeIfMissing -Name $AppVolume
    New-VolumeIfMissing -Name $PostgresVolume
    New-VolumeIfMissing -Name $RedisVolume

    if ($StorageMode -eq 'rustfs') {
        New-VolumeIfMissing -Name $RustFsVolume
    }
}

function Assert-ServiceAvailable {
    param([Parameter(Mandatory)][string]$Name)
    if ((Test-DockerObject -Type service -Name $Name) -and -not (Test-ServiceManaged -Name $Name)) {
        Stop-Installer "Service $Name already exists but is not managed by the KoAkademy installer."
    }
}

function New-ServiceIfMissing {
    param(
        [Parameter(Mandatory)][string]$Name,
        [Parameter(Mandatory)][string[]]$Arguments
    )

    Assert-ServiceAvailable -Name $Name
    if (Test-DockerObject -Type service -Name $Name) {
        if (Test-ServiceReady -Name $Name) {
            Write-Info "Reusing healthy service $Name."
            return
        }

        $replicas = Get-ServiceReplicaStatus -Name $Name
        $state = Get-ServiceLatestTaskField -Name $Name -Field 'CurrentState'
        Write-WarningMessage "Service $Name is unhealthy (replicas=$(if ($replicas) { $replicas } else { 'n/a' }) state=$(if ($state) { $state } else { 'unknown' })); recreating..."
        Remove-ServiceIfPresent -Name $Name
    }

    Write-Info "Creating service $Name..."
    $command = @('service', 'create', '--name', $Name, '--label', $InstallerLabel) + $Arguments
    Invoke-Docker -Arguments $command -DiscardOutput
    Write-Info "Created service $Name."
}

function Wait-Service {
    param(
        [Parameter(Mandatory)][string]$Name,
        [int]$TimeoutSeconds = 300
    )

    Write-Info "Waiting for $Name..."
    $lastStatus = ''
    $terminalFailureStreak = 0
    for ($elapsed = 0; $elapsed -lt $TimeoutSeconds; $elapsed += 2) {
        $replicas = Get-ServiceReplicaStatus -Name $Name

        if ($replicas -match '^([0-9]+)/([0-9]+)$' -and
            [int]$Matches[1] -ge 1 -and $Matches[1] -eq $Matches[2]) {
            Write-Info "$Name is ready ($replicas)."
            return
        }

        $state = Get-ServiceLatestTaskField -Name $Name -Field 'CurrentState'
        $errorText = Get-ServiceLatestTaskField -Name $Name -Field 'Error'
        $desiredState = Get-ServiceLatestTaskField -Name $Name -Field 'DesiredState'

        if ($state -like 'Rejected*' -or ($desiredState -eq 'Shutdown' -and $state -like 'Failed*')) {
            $terminalFailureStreak++
        } else {
            $terminalFailureStreak = 0
        }

        if ($terminalFailureStreak -ge 3) {
            & docker service ps --no-trunc $Name | Out-Host
            & docker service logs --tail 80 $Name | Out-Host
            $failureReason = if ($errorText) { $errorText } elseif ($state) { $state } else { 'unknown state' }
            Stop-Installer "Service $Name failed before becoming ready: $failureReason"
        }

        $status = "$(if ($replicas) { $replicas } else { 'n/a' })|$(if ($state) { $state } else { 'pending' })"
        if ($status -ne $lastStatus) {
            $message = "$Name`: replicas=$(if ($replicas) { $replicas } else { 'n/a' }) state=$(if ($state) { $state } else { 'pending' })"
            if ($errorText) {
                $message = "$message ($errorText)"
            }
            Write-Info $message
            $lastStatus = $status
        }

        Start-Sleep -Seconds 2
    }

    & docker service ps --no-trunc $Name | Out-Host
    & docker service logs --tail 80 $Name | Out-Host
    Stop-Installer "Service $Name did not converge within $TimeoutSeconds seconds."
}

function Wait-Job {
    param(
        [Parameter(Mandatory)][string]$Name,
        [int]$TimeoutSeconds = 300
    )

    Write-Info "Waiting for job $Name..."
    $lastStatus = ''
    $terminalFailureStreak = 0
    for ($elapsed = 0; $elapsed -lt $TimeoutSeconds; $elapsed += 2) {
        $state = Get-ServiceLatestTaskField -Name $Name -Field 'CurrentState'
        $errorText = Get-ServiceLatestTaskField -Name $Name -Field 'Error'
        $desiredState = Get-ServiceLatestTaskField -Name $Name -Field 'DesiredState'

        if ($state -like 'Complete*') {
            Invoke-Docker -Arguments @('service', 'rm', $Name) -DiscardOutput
            Write-Info "Job $Name completed."
            return
        }

        if ($state -like 'Rejected*' -or ($desiredState -eq 'Shutdown' -and $state -like 'Failed*')) {
            $terminalFailureStreak++
        } else {
            $terminalFailureStreak = 0
        }

        if ($terminalFailureStreak -ge 3) {
            & docker service ps --no-trunc $Name | Out-Host
            & docker service logs --tail 80 $Name | Out-Host
            $failureReason = if ($errorText) { $errorText } elseif ($state) { $state } else { 'unknown state' }
            Stop-Installer "One-off service $Name failed: $failureReason"
        }

        if ($state -ne $lastStatus) {
            $message = if ($state) { $state } else { 'pending' }
            if ($errorText) {
                $message = "$message ($errorText)"
            }
            Write-Info "$Name`: $message"
            $lastStatus = $state
        }

        Start-Sleep -Seconds 2
    }

    & docker service ps --no-trunc $Name | Out-Host
    & docker service logs --tail 80 $Name | Out-Host
    $failureReason = if ($errorText) { $errorText } elseif ($state) { $state } else { 'unknown state' }
    Stop-Installer "One-off service $Name did not complete within $TimeoutSeconds seconds: $failureReason"
}

function Initialize-ApplicationUrl {
    Assert-Port -Port $AppPort -Name 'KOAKADEMY_APP_PORT'
    if (-not $AppUrl) {
        $defaultUrl = "http://$(Get-AccessHost):$AppPort"
        $script:AppUrl = Read-Value -Message 'Public KoAkademy URL' -Default $defaultUrl
    }

    $script:AppUrl = $AppUrl.TrimEnd('/')
    $uri = ConvertTo-HttpUri -Value $AppUrl -Name 'Application URL' -OriginOnly
    $script:AppHost = $uri.Host

    if ($uri.Scheme -eq 'https') {
        $script:SessionSecureCookie = 'true'
        Write-WarningMessage "The installer publishes host port $AppPort without TLS. Ensure your existing HTTPS edge forwards to this port."
    } else {
        $script:SessionSecureCookie = 'false'
        Write-WarningMessage 'HTTP is suitable for local/LAN evaluation only. Add an HTTPS edge before production use.'
    }
}

function Initialize-Storage {
    if (-not $StorageMode) {
        Write-Host ''
        Write-Host 'Choose upload storage:'
        Write-Host '  1) Local RustFS (single-node, no built-in redundancy)'
        Write-Host '  2) External S3-compatible service (for example Cloudflare R2)'
        $choice = Read-Value -Message 'Storage option' -Default '1'
        $script:StorageMode = switch ($choice.ToLowerInvariant()) {
            { $_ -in @('1', 'rustfs') } { 'rustfs'; break }
            { $_ -in @('2', 'external', 's3') } { 'external'; break }
            default { Stop-Installer 'Storage option must be 1 or 2.' }
        }
    }

    if ($StorageMode -eq 'rustfs') {
        Assert-Port -Port $RustFsPort -Name 'KOAKADEMY_RUSTFS_PORT'
        $script:RustFsVersion = Resolve-RustFsVersion
        $script:AwsBucketValue = if ($env:KOAKADEMY_S3_BUCKET) { $env:KOAKADEMY_S3_BUCKET } else { 'koakademy' }
        Assert-Bucket -Bucket $AwsBucketValue
        $script:AwsRegionValue = if ($env:KOAKADEMY_S3_REGION) { $env:KOAKADEMY_S3_REGION } else { 'us-east-1' }
        $script:AwsEndpointValue = "http://$RustFsService`:9000"
        $defaultPublicUrl = "http://$AppHost`:$RustFsPort/$AwsBucketValue"
        $script:AwsUrlValue = if ($env:KOAKADEMY_S3_PUBLIC_URL) {
            $env:KOAKADEMY_S3_PUBLIC_URL
        } else {
            $defaultPublicUrl
        }
        $null = ConvertTo-HttpUri -Value $AwsUrlValue -Name 'RustFS public object URL'
        $script:AwsPathStyleValue = 'true'

        if ($AppUrl.StartsWith('https://') -and $AwsUrlValue.StartsWith('http://')) {
            Stop-Installer "HTTPS KoAkademy cannot use an HTTP RustFS public URL. Set KOAKADEMY_S3_PUBLIC_URL to an HTTPS edge for port $RustFsPort."
        }

        if (-not (Test-DockerObject -Type secret -Name $S3AccessKeySecret)) {
            $script:AwsAccessKeyValue = "koa$(New-RandomHex -Bytes 12)"
            $script:AwsSecretKeyValue = New-RandomHex
        }
        return
    }

    if ($StorageMode -ne 'external') {
        Stop-Installer "KOAKADEMY_STORAGE must be 'rustfs' or 'external'."
    }

    $script:AwsEndpointValue = Read-Value `
        -Message 'S3 API endpoint (for R2: https://ACCOUNT_ID.r2.cloudflarestorage.com)' `
        -Default $env:KOAKADEMY_S3_ENDPOINT
    $null = ConvertTo-HttpUri -Value $AwsEndpointValue -Name 'S3 endpoint'

    $script:AwsBucketValue = Read-Value -Message 'Existing S3 bucket name' -Default $env:KOAKADEMY_S3_BUCKET
    Assert-Bucket -Bucket $AwsBucketValue

    $script:AwsRegionValue = Read-Value -Message 'S3 region' -Default $(if ($env:KOAKADEMY_S3_REGION) { $env:KOAKADEMY_S3_REGION } else { 'auto' })
    Assert-Region -Region $AwsRegionValue

    $script:AwsUrlValue = Read-Value `
        -Message 'Public object base URL (CDN, r2.dev, or custom domain)' `
        -Default $env:KOAKADEMY_S3_PUBLIC_URL
    $null = ConvertTo-HttpUri -Value $AwsUrlValue -Name 'S3 public object URL'

    if ($env:KOAKADEMY_S3_PATH_STYLE) {
        $pathStyle = $env:KOAKADEMY_S3_PATH_STYLE
    } else {
        $pathStyle = if (Read-YesNo -Message 'Use path-style S3 requests?' -Default $false) { 'true' } else { 'false' }
    }
    if ($pathStyle -match '^(true|1|yes|y)$') {
        $script:AwsPathStyleValue = 'true'
    } elseif ($pathStyle -match '^(false|0|no|n)$') {
        $script:AwsPathStyleValue = 'false'
    } else {
        Stop-Installer 'KOAKADEMY_S3_PATH_STYLE must be true or false.'
    }

    if (-not (Test-DockerObject -Type secret -Name $S3AccessKeySecret)) {
        $script:AwsAccessKeyValue = if ($env:KOAKADEMY_S3_ACCESS_KEY) {
            $env:KOAKADEMY_S3_ACCESS_KEY
        } else {
            Read-Value -Message 'S3 access key ID'
        }
        $script:AwsSecretKeyValue = if ($env:KOAKADEMY_S3_SECRET_KEY) {
            $env:KOAKADEMY_S3_SECRET_KEY
        } else {
            Read-SecretValue -Message 'S3 secret access key'
        }
    }
}

function Write-ApplicationEnvironment {
    $script:TempEnvFile = [IO.Path]::GetTempFileName()
    $trustedProxies = if ($env:KOAKADEMY_TRUSTED_PROXIES) { $env:KOAKADEMY_TRUSTED_PROXIES } else { '' }
    Assert-SingleLine -Value $trustedProxies -Name 'KOAKADEMY_TRUSTED_PROXIES'
    $lines = @(
        'APP_NAME=KoAkademy',
        'APP_ENV=production',
        'APP_DEBUG=false',
        "APP_URL=$AppUrl",
        'APP_TIMEZONE=UTC',
        'APP_LOCALE=en',
        'APP_FALLBACK_LOCALE=en',
        'APP_MAINTENANCE_DRIVER=file',
        "PORTAL_HOST=$AppHost",
        "ADMIN_HOST=$AppHost",
        'TRUSTED_HOSTS=',
        "TRUSTED_PROXIES=$trustedProxies",
        'APP_PORT=8000',
        'DB_CONNECTION=pgsql',
        "DB_HOST=$PostgresService",
        'DB_PORT=5432',
        'DB_DATABASE=koakademy',
        'DB_USERNAME=koakademy',
        'REDIS_CLIENT=phpredis',
        "REDIS_HOST=$RedisService",
        'REDIS_PORT=6379',
        'REDIS_DB=0',
        'REDIS_CACHE_DB=1',
        'REDIS_QUEUE_DB=2',
        'CACHE_STORE=redis',
        'QUEUE_CONNECTION=redis',
        'SESSION_DRIVER=redis',
        'SESSION_LIFETIME=120',
        'SESSION_ENCRYPT=true',
        "SESSION_SECURE_COOKIE=$SessionSecureCookie",
        'SESSION_HTTP_ONLY=true',
        'SESSION_SAME_SITE=lax',
        'SESSION_DOMAIN=',
        'FILESYSTEM_DISK=s3',
        "AWS_DEFAULT_REGION=$AwsRegionValue",
        "AWS_BUCKET=$AwsBucketValue",
        "AWS_ENDPOINT=$AwsEndpointValue",
        "AWS_URL=$AwsUrlValue",
        "AWS_USE_PATH_STYLE_ENDPOINT=$AwsPathStyleValue",
        'LARAVEL_PDF_DRIVER=gotenberg',
        'LARAVEL_PDF_PRODUCTION_DRIVER=gotenberg',
        'LARAVEL_PDF_PRODUCTION_FALLBACK=',
        'LARAVEL_PDF_ROLLBACK_DRIVER=gotenberg',
        "GOTENBERG_URL=http://$GotenbergService`:3000",
        'GOTENBERG_USERNAME=',
        'GOTENBERG_PASSWORD=',
        'MAIL_MAILER=log',
        "MAIL_FROM_ADDRESS=no-reply@$AppHost",
        'MAIL_FROM_NAME=KoAkademy',
        'BROADCAST_CONNECTION=log',
        'LOG_CHANNEL=stack',
        'LOG_STACK=single',
        'LOG_LEVEL=info',
        'RUN_MIGRATIONS=false',
        'RUN_DOCKER_SCRIPTS=false',
        'RUN_SCOUT_SETTINGS=false',
        'RUN_OPTIMIZE=foreground',
        'HORIZON_ENABLED=true',
        'HORIZON_PROCESSES=2',
        'HORIZON_PDF_PROCESSES=1',
        'PULSE_ENABLED=false',
        'NIGHTWATCH_ENABLED=false',
        'TELESCOPE_ENABLED=false',
        'SCOUT_DRIVER=null',
        'SENTRY_LARAVEL_DSN=',
        'SENTRY_TRACES_SAMPLE_RATE=0.0',
        'VITE_APP_NAME=KoAkademy'
    )
    [IO.File]::WriteAllLines(
        $TempEnvFile,
        [string[]]$lines,
        (New-Object Text.UTF8Encoding($false))
    )
}

function Start-VolumePermissionJob {
    if (Test-DockerObject -Type service -Name $RustFsService) {
        return
    }

    Invoke-Docker -Arguments @('service', 'rm', 'koakademy-rustfs-volume-init') -AllowFailure -DiscardOutput
    Invoke-Docker -Arguments @(
        'service', 'create',
        '--name', 'koakademy-rustfs-volume-init',
        '--label', $InstallerLabel,
        '--mode', 'replicated-job',
        '--restart-condition', 'on-failure',
        '--restart-max-attempts', '3',
        '--constraint', "node.hostname==$CurrentNode",
        '--mount', "type=volume,source=$RustFsVolume,target=/data",
        $AlpineImage,
        'chown', '-R', '10001:10001', '/data'
    ) -DiscardOutput
    Wait-Job -Name 'koakademy-rustfs-volume-init' -TimeoutSeconds 120
}

function Start-StorageInitJob {
    Invoke-Docker -Arguments @('service', 'rm', 'koakademy-storage-init') -AllowFailure -DiscardOutput
    Invoke-Docker -Arguments @(
        'service', 'create',
        '--name', 'koakademy-storage-init',
        '--label', $InstallerLabel,
        '--mode', 'replicated-job',
        '--restart-condition', 'on-failure',
        '--restart-max-attempts', '3',
        '--constraint', "node.hostname==$CurrentNode",
        '--network', $NetworkName,
        '--secret', "source=$S3AccessKeySecret,target=koakademy-s3-access-key",
        '--secret', "source=$S3SecretKeySecret,target=koakademy-s3-secret-key",
        '--config', "source=$StorageInitConfig,target=/run/configs/koakademy-storage-init,mode=0555",
        '--env', "STORAGE_MODE=$StorageMode",
        '--env', "AWS_ENDPOINT=$AwsEndpointValue",
        '--env', "AWS_BUCKET=$AwsBucketValue",
        '--env', "AWS_DEFAULT_REGION=$AwsRegionValue",
        '--env', "APP_ORIGIN=$($AppUrl.TrimEnd('/'))",
        '--entrypoint', '/bin/sh',
        $AwsCliImage,
        '/run/configs/koakademy-storage-init'
    ) -DiscardOutput
    Wait-Job -Name 'koakademy-storage-init' -TimeoutSeconds 300
}

function Start-MigrationJob {
    param([Parameter(Mandatory)][string]$Image)

    Invoke-Docker -Arguments @('service', 'rm', 'koakademy-migrate') -AllowFailure -DiscardOutput
    Invoke-Docker -Arguments @(
        'service', 'create',
        '--name', 'koakademy-migrate',
        '--label', $InstallerLabel,
        '--mode', 'replicated-job',
        '--restart-condition', 'on-failure',
        '--restart-max-attempts', '20',
        '--restart-delay', '3s',
        '--constraint', "node.hostname==$CurrentNode",
        '--network', $NetworkName,
        '--env-file', $TempEnvFile,
        '--secret', "source=$AppKeySecret,target=koakademy-app-key",
        '--secret', "source=$DbPasswordSecret,target=koakademy-db-password",
        '--secret', "source=$RedisPasswordSecret,target=koakademy-redis-password",
        '--secret', "source=$S3AccessKeySecret,target=koakademy-s3-access-key",
        '--secret', "source=$S3SecretKeySecret,target=koakademy-s3-secret-key",
        '--config', "source=$AppEntrypointConfig,target=/run/configs/koakademy-app-entrypoint,mode=0555",
        '--entrypoint', '/bin/sh',
        $Image,
        '/run/configs/koakademy-app-entrypoint',
        'php', 'artisan', 'migrate', '--force', '--no-interaction'
    ) -DiscardOutput
    Wait-Job -Name 'koakademy-migrate' -TimeoutSeconds 600
}

function Install-Dependencies {
    $constraint = "node.hostname==$CurrentNode"

    New-ServiceIfMissing -Name $PostgresService -Arguments @(
        '--constraint', $constraint,
        '--network', $NetworkName,
        '--secret', "source=$DbPasswordSecret,target=koakademy-db-password",
        '--env', 'POSTGRES_DB=koakademy',
        '--env', 'POSTGRES_USER=koakademy',
        '--env', 'POSTGRES_PASSWORD_FILE=/run/secrets/koakademy-db-password',
        '--mount', "type=volume,source=$PostgresVolume,target=/var/lib/postgresql",
        '--health-cmd', 'pg_isready -U koakademy -d koakademy',
        '--health-interval', '10s',
        '--health-timeout', '5s',
        '--health-retries', '10',
        '--restart-condition', 'any',
        $PostgresImage
    )

    New-ServiceIfMissing -Name $RedisService -Arguments @(
        '--constraint', $constraint,
        '--network', $NetworkName,
        '--secret', "source=$RedisPasswordSecret,target=koakademy-redis-password",
        '--config', "source=$RedisEntrypointConfig,target=/run/configs/koakademy-redis-entrypoint,mode=0555",
        '--mount', "type=volume,source=$RedisVolume,target=/data",
        '--health-cmd', 'redis-cli -a "$(cat /run/secrets/koakademy-redis-password)" ping | grep -q PONG',
        '--health-interval', '10s',
        '--health-timeout', '5s',
        '--health-retries', '10',
        '--restart-condition', 'any',
        '--entrypoint', '/bin/sh',
        $RedisImage,
        '/run/configs/koakademy-redis-entrypoint'
    )

    New-ServiceIfMissing -Name $GotenbergService -Arguments @(
        '--constraint', $constraint,
        '--network', $NetworkName,
        '--health-cmd', 'curl --fail --silent --show-error http://localhost:3000/health',
        '--health-interval', '10s',
        '--health-timeout', '5s',
        '--health-retries', '10',
        '--restart-condition', 'any',
        $GotenbergImage
    )

    if ($StorageMode -eq 'rustfs') {
        Start-VolumePermissionJob
        New-ServiceIfMissing -Name $RustFsService -Arguments @(
            '--constraint', $constraint,
            '--network', $NetworkName,
            '--secret', "source=$S3AccessKeySecret,target=koakademy-s3-access-key",
            '--secret', "source=$S3SecretKeySecret,target=koakademy-s3-secret-key",
            '--env', 'RUSTFS_ACCESS_KEY_FILE=/run/secrets/koakademy-s3-access-key',
            '--env', 'RUSTFS_SECRET_KEY_FILE=/run/secrets/koakademy-s3-secret-key',
            '--env', 'RUSTFS_VOLUMES=/data',
            '--env', 'RUSTFS_ADDRESS=0.0.0.0:9000',
            '--env', 'RUSTFS_CONSOLE_ADDRESS=0.0.0.0:9001',
            '--env', 'RUSTFS_CONSOLE_ENABLE=true',
            '--env', 'RUSTFS_OBS_LOGGER_LEVEL=warn',
            '--mount', "type=volume,source=$RustFsVolume,target=/data",
            '--publish', "published=$RustFsPort,target=9000,mode=host",
            '--health-cmd', 'curl --fail --silent --show-error http://127.0.0.1:9000/health',
            '--health-interval', '15s',
            '--health-timeout', '5s',
            '--health-retries', '20',
            '--health-start-period', '30s',
            '--restart-condition', 'any',
            "rustfs/rustfs:$RustFsVersion"
        )
    }

    Wait-Service -Name $PostgresService
    Wait-Service -Name $RedisService
    Wait-Service -Name $GotenbergService
    if ($StorageMode -eq 'rustfs') {
        Wait-Service -Name $RustFsService
    }
}

function Install-Application {
    param([Parameter(Mandatory)][string]$Image)

    if ((Test-DockerObject -Type service -Name $AppService) -and (Test-ServiceReady -Name $AppService)) {
        Write-Info "Reusing healthy service $AppService."
        return
    }

    Start-StorageInitJob
    Start-MigrationJob -Image $Image

    New-ServiceIfMissing -Name $AppService -Arguments @(
        '--constraint', "node.hostname==$CurrentNode",
        '--network', $NetworkName,
        '--env-file', $TempEnvFile,
        '--secret', "source=$AppKeySecret,target=koakademy-app-key",
        '--secret', "source=$DbPasswordSecret,target=koakademy-db-password",
        '--secret', "source=$RedisPasswordSecret,target=koakademy-redis-password",
        '--secret', "source=$S3AccessKeySecret,target=koakademy-s3-access-key",
        '--secret', "source=$S3SecretKeySecret,target=koakademy-s3-secret-key",
        '--config', "source=$AppEntrypointConfig,target=/run/configs/koakademy-app-entrypoint,mode=0555",
        '--mount', "type=volume,source=$AppVolume,target=/app/storage",
        '--publish', "published=$AppPort,target=8000,mode=host",
        '--health-cmd', 'healthcheck',
        '--health-interval', '30s',
        '--health-timeout', '10s',
        '--health-retries', '5',
        '--health-start-period', '60s',
        '--restart-condition', 'any',
        '--update-parallelism', '1',
        '--update-order', 'stop-first',
        '--rollback-parallelism', '1',
        '--rollback-order', 'stop-first',
        '--entrypoint', '/bin/sh',
        $Image,
        '/run/configs/koakademy-app-entrypoint'
    )

    Wait-Service -Name $AppService -TimeoutSeconds 600
}

function Test-ApplicationHealth {
    $healthUrl = "http://127.0.0.1:$AppPort/up"
    Write-Info "Checking $healthUrl..."
    for ($attempt = 0; $attempt -lt 90; $attempt++) {
        try {
            Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 5 | Out-Null
            return
        } catch {
            Start-Sleep -Seconds 2
        }
    }

    & docker service ps --no-trunc $AppService | Out-Host
    Stop-Installer "KoAkademy did not become healthy at $healthUrl."
}

try {
    Write-Host 'KoAkademy Docker Swarm installer'
    Write-Host ''

    Initialize-SwarmManager
    Initialize-Runtime

    $KoAkademyVersion = Resolve-KoAkademyVersion
    $koAkademyImage = "ghcr.io/$Repository`:$KoAkademyVersion"
    Assert-Image -Image $koAkademyImage -Name 'KoAkademy'
    Assert-Image -Image $PostgresImage -Name 'PostgreSQL'
    Assert-Image -Image $RedisImage -Name 'Redis'
    Assert-Image -Image $GotenbergImage -Name 'Gotenberg'
    Assert-Image -Image $AwsCliImage -Name 'AWS CLI'
    Assert-Image -Image $AlpineImage -Name 'Alpine'
    if ($StorageMode -eq 'rustfs') {
        Assert-Image -Image "rustfs/rustfs:$RustFsVersion" -Name 'RustFS'
    }

    Initialize-Network
    Initialize-Volumes
    New-GeneratedSecretIfMissing -Name $AppKeySecret -Kind 'app-key'
    New-GeneratedSecretIfMissing -Name $DbPasswordSecret -Kind 'password'
    New-GeneratedSecretIfMissing -Name $RedisPasswordSecret -Kind 'password'
    New-SecretIfMissing -Name $S3AccessKeySecret -Value $AwsAccessKeyValue
    New-SecretIfMissing -Name $S3SecretKeySecret -Value $AwsSecretKeyValue
    Initialize-Configs
    Write-ApplicationEnvironment
    Install-Dependencies
    Install-Application -Image $koAkademyImage
    Test-ApplicationHealth

    Write-Host ''
    if (Write-InstallationStatus) {
        Write-Host "KoAkademy $KoAkademyVersion is ready."
    } else {
        Stop-Installer "KoAkademy $KoAkademyVersion is still reporting unhealthy services."
    }
    Write-Host "Application: $AppUrl"
    Write-Host "First-time setup: $AppUrl/setup"
    Write-Host "Admin portal: $AppUrl/admin"
    Write-Host ''
    Write-Host 'Useful commands:'
    Write-Host '  docker service ls --filter label=com.koakademy.managed-by=swarm-installer'
    Write-Host "  docker service logs -f $AppService"
    Write-Host ''
    Write-WarningMessage 'Back up PostgreSQL, application storage, and your S3/RustFS data before upgrades.'
} finally {
    if ($TempEnvFile -and (Test-Path -LiteralPath $TempEnvFile)) {
        Remove-Item -LiteralPath $TempEnvFile -Force
    }
}
