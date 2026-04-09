#Requires -Version 5.1
<#
.SYNOPSIS
    Setup local SSL certificates and hosts file for development using mkcert.

.DESCRIPTION
    This script automates the process of:
    - Generating trusted SSL certificates using mkcert
    - Adding local domains to the Windows hosts file
    - Providing instructions for Firefox-based browsers

.EXAMPLE
    .\setup-ssl.ps1
    Runs the full setup process.

.EXAMPLE
    .\setup-ssl.ps1 -SkipCAInstall
    Generates certificates without reinstalling the CA.

.EXAMPLE
    .\setup-ssl.ps1 -SkipHosts
    Skips hosts file modification.

.NOTES
    Requires mkcert to be installed and Administrator privileges for hosts file.
    Install mkcert via:
    - Chocolatey: choco install mkcert
    - Scoop: scoop install mkcert
#>

param(
    [switch]$SkipCAInstall,
    [switch]$SkipHosts,
    [switch]$Force
)

$ErrorActionPreference = "Stop"

# Configuration
$ProjectRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$CertsDir = Join-Path $ProjectRoot "docker\traefik\certs"
$HostsFile = "C:\Windows\System32\drivers\etc\hosts"
$EnvFile = Join-Path $ProjectRoot ".env"

# Load environment variables if they exist
$EnvVars = @{}
if (Test-Path $EnvFile) {
    Get-Content $EnvFile | ForEach-Object {
        if ($_ -match '^\s*([^#=]+)=(.*)$') {
            $EnvVars[$matches[1]] = $matches[2]
        }
    }
}

# Determine main domain from env or fallback
$PortalHost = if ($EnvVars.ContainsKey("PORTAL_HOST")) { $EnvVars["PORTAL_HOST"] } else { "portal.dccp.test" }
$AdminHost = if ($EnvVars.ContainsKey("ADMIN_HOST")) { $EnvVars["ADMIN_HOST"] } else { "admin.dccp.test" }
$MailpitHost = if ($EnvVars.ContainsKey("MAILPIT_HOST")) { $EnvVars["MAILPIT_HOST"] } else { "mailpit.local.test" }
$MinioHost = if ($EnvVars.ContainsKey("MINIO_HOST")) { $EnvVars["MINIO_HOST"] } else { "minio.local.test" }
$MinioConsoleHost = if ($EnvVars.ContainsKey("MINIO_CONSOLE_HOST")) { $EnvVars["MINIO_CONSOLE_HOST"] } else { "minio-console.local.test" }

# Extract base domain from PORTAL_HOST
if ($PortalHost -match '^[^.]+\.(.+)$') {
    $BaseDomain = $matches[1]
} else {
    $BaseDomain = "dccp.test" # Fallback if regex fails
}

$CertFile = "${BaseDomain}.pem"
$KeyFile = "${BaseDomain}-key.pem"

# Domains for certificates (includes wildcards)
$CertDomains = @(
    $PortalHost,
    $AdminHost,
    "*.$BaseDomain",
    $MailpitHost,
    $MinioHost,
    $MinioConsoleHost,
    "*.local.test"
)

# Domains for hosts file (no wildcards - must be explicit)
$HostsDomains = @(
    $PortalHost,
    $AdminHost,
    $MailpitHost,
    $MinioHost,
    $MinioConsoleHost
)

# Colors for output
function Write-Success { param($Message) Write-Host "[OK] $Message" -ForegroundColor Green }
function Write-Info { param($Message) Write-Host "[..] $Message" -ForegroundColor Cyan }
function Write-Warn { param($Message) Write-Host "[!!] $Message" -ForegroundColor Yellow }
function Write-Err { param($Message) Write-Host "[XX] $Message" -ForegroundColor Red }

# Check if running as Administrator
function Test-Administrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

# Banner
Write-Host ""
Write-Host "================================================================" -ForegroundColor Blue
Write-Host "           DCCP Local Development Setup                        " -ForegroundColor Blue
Write-Host "================================================================" -ForegroundColor Blue
Write-Host ""

# Check admin privileges if modifying hosts file
if (-not $SkipHosts) {
    if (-not (Test-Administrator)) {
        Write-Warn "Not running as Administrator!"
        Write-Host "  To modify the hosts file, please run this script as Administrator." -ForegroundColor Gray
        Write-Host "  Or use -SkipHosts to skip hosts file modification." -ForegroundColor Gray
        Write-Host ""
        $response = Read-Host "Continue without modifying hosts file? (y/N)"
        if ($response -eq 'y' -or $response -eq 'Y') {
            $SkipHosts = $true
        }
        else {
            Write-Host ""
            Write-Host "Please run PowerShell as Administrator and try again." -ForegroundColor Yellow
            exit 1
        }
    }
}

# Step 1: Check if mkcert is installed
Write-Info "Checking if mkcert is installed..."
$mkcert = Get-Command mkcert -ErrorAction SilentlyContinue
if (-not $mkcert) {
    Write-Err "mkcert is not installed!"
    Write-Host ""
    Write-Host "Please install mkcert using one of these methods:" -ForegroundColor Yellow
    Write-Host "  - Chocolatey: choco install mkcert" -ForegroundColor Gray
    Write-Host "  - Scoop:      scoop install mkcert" -ForegroundColor Gray
    Write-Host "  - Download:   https://github.com/FiloSottile/mkcert/releases" -ForegroundColor Gray
    Write-Host ""
    exit 1
}
Write-Success "mkcert found at: $($mkcert.Source)"

# Step 2: Install the local CA
if (-not $SkipCAInstall) {
    Write-Info "Installing local Certificate Authority..."
    Write-Host "  (This may require administrator privileges)" -ForegroundColor Gray
    
    try {
        $output = & mkcert -install 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Success "Local CA installed successfully"
        }
        else {
            Write-Warn "CA may already be installed or requires admin privileges"
            Write-Host "  $output" -ForegroundColor Gray
        }
    }
    catch {
        Write-Warn "Could not install CA: $_"
    }
}
else {
    Write-Info "Skipping CA installation (-SkipCAInstall flag set)"
}

# Step 3: Create certs directory if it doesn't exist
Write-Info "Ensuring certificates directory exists..."
if (-not (Test-Path $CertsDir)) {
    New-Item -ItemType Directory -Path $CertsDir -Force | Out-Null
    Write-Success "Created directory: $CertsDir"
}
else {
    Write-Success "Directory exists: $CertsDir"
}

# Step 4: Check for existing certificates
$CertPath = Join-Path $CertsDir $CertFile
$KeyPath = Join-Path $CertsDir $KeyFile

if ((Test-Path $CertPath) -and (Test-Path $KeyPath) -and (-not $Force)) {
    Write-Warn "Certificates already exist!"
    Write-Host "  Certificate: $CertPath" -ForegroundColor Gray
    Write-Host "  Key:         $KeyPath" -ForegroundColor Gray
    Write-Host ""
    $response = Read-Host "Do you want to regenerate them? (y/N)"
    if ($response -ne 'y' -and $response -ne 'Y') {
        Write-Info "Keeping existing certificates"
    }
    else {
        $Force = $true
    }
}

# Step 5: Generate certificates
if ($Force -or -not (Test-Path $CertPath) -or -not (Test-Path $KeyPath)) {
    Write-Info "Generating SSL certificates for domains:"
    foreach ($domain in $CertDomains) {
        Write-Host "  - $domain" -ForegroundColor Gray
    }

    Push-Location $CertsDir
    try {
        $mkcertArgs = @("-key-file", $KeyFile, "-cert-file", $CertFile) + $CertDomains
        & mkcert @mkcertArgs
        
        if ($LASTEXITCODE -eq 0) {
            Write-Success "Certificates generated successfully!"
        }
        else {
            throw "mkcert failed with exit code $LASTEXITCODE"
        }
    }
    catch {
        Write-Err "Failed to generate certificates: $_"
        Pop-Location
        exit 1
    }
    Pop-Location
}

# Step 6: Verify certificates
Write-Info "Verifying generated certificates..."
if ((Test-Path $CertPath) -and (Test-Path $KeyPath)) {
    $certInfo = Get-Item $CertPath
    $keyInfo = Get-Item $KeyPath
    Write-Success "Certificate: $CertPath ($($certInfo.Length) bytes)"
    Write-Success "Key:         $KeyPath ($($keyInfo.Length) bytes)"
}
else {
    Write-Err "Certificate files not found after generation!"
    exit 1
}

# Step 7: Update hosts file
if (-not $SkipHosts) {
    Write-Host ""
    Write-Info "Updating Windows hosts file..."
    
    try {
        $hostsContent = Get-Content $HostsFile -Raw -ErrorAction Stop
        $hostsLines = Get-Content $HostsFile -ErrorAction Stop
        $modified = $false
        $addedDomains = @()
        $existingDomains = @()
        
        foreach ($domain in $HostsDomains) {
            $pattern = "^\s*127\.0\.0\.1\s+$([regex]::Escape($domain))\s*$"
            $exists = $hostsLines | Where-Object { $_ -match $pattern }
            
            if (-not $exists) {
                Add-Content -Path $HostsFile -Value "127.0.0.1 $domain" -ErrorAction Stop
                $addedDomains += $domain
                $modified = $true
            }
            else {
                $existingDomains += $domain
            }
        }
        
        if ($addedDomains.Count -gt 0) {
            Write-Success "Added to hosts file:"
            foreach ($domain in $addedDomains) {
                Write-Host "  + 127.0.0.1 $domain" -ForegroundColor Green
            }
        }
        
        if ($existingDomains.Count -gt 0) {
            Write-Info "Already in hosts file:"
            foreach ($domain in $existingDomains) {
                Write-Host "  = 127.0.0.1 $domain" -ForegroundColor Gray
            }
        }
        
        if (-not $modified) {
            Write-Success "Hosts file already configured correctly"
        }
    }
    catch {
        Write-Err "Failed to update hosts file: $_"
        Write-Host "  You may need to add entries manually to: $HostsFile" -ForegroundColor Yellow
    }
}
else {
    Write-Info "Skipping hosts file modification (-SkipHosts flag set)"
}

# Step 8: Get CA root location for Firefox browsers
$caRoot = & mkcert -CAROOT
$caRootPem = Join-Path $caRoot "rootCA.pem"

# Step 9: Print success message and next steps
Write-Host ""
Write-Host "================================================================" -ForegroundColor Green
Write-Host "                    Setup Complete!                             " -ForegroundColor Green
Write-Host "================================================================" -ForegroundColor Green
Write-Host ""

Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Restart Docker containers:" -ForegroundColor White
Write-Host "   docker compose down" -ForegroundColor Gray
Write-Host "   docker compose up -d" -ForegroundColor Gray
Write-Host ""

if ($SkipHosts) {
    Write-Host "2. Add entries to hosts file (C:\Windows\System32\drivers\etc\hosts):" -ForegroundColor White
    foreach ($domain in $HostsDomains) {
        Write-Host "   127.0.0.1 $domain" -ForegroundColor Gray
    }
    Write-Host ""
    Write-Host "3. For Firefox-based browsers (Zen/Firefox):" -ForegroundColor White
}
else {
    Write-Host "2. For Firefox-based browsers (Zen/Firefox):" -ForegroundColor White
}

Write-Host "   The local CA needs to be imported manually:" -ForegroundColor Gray
Write-Host "   - Go to Settings > Privacy and Security > Certificates > View Certificates" -ForegroundColor Gray
Write-Host "   - Click Authorities tab then Import" -ForegroundColor Gray
Write-Host "   - Select this file: $caRootPem" -ForegroundColor Cyan
Write-Host "   - Check: Trust this CA to identify websites" -ForegroundColor Gray
Write-Host "   - Restart the browser completely" -ForegroundColor Gray
Write-Host ""

Write-Host "Your local development URLs:" -ForegroundColor Yellow
Write-Host "   https://${PortalHost}" -ForegroundColor Cyan
Write-Host "   https://${AdminHost}" -ForegroundColor Cyan
Write-Host "   http://${MailpitHost}" -ForegroundColor Cyan
Write-Host "   http://${MinioHost}" -ForegroundColor Cyan
Write-Host "   http://${MinioConsoleHost}" -ForegroundColor Cyan
Write-Host ""
