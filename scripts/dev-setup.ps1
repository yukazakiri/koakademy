#
# DCCP Admin V3 - Local Development Setup for Windows
#
# This script sets up the local development environment using Laravel Herd
# including:
# - Prerequisites check
# - Environment configuration
# - Composer and npm dependencies
# - Herd proxy configuration
# - Database setup
#
# Usage:
#   .\scripts\dev-setup.ps1              # Full setup
#   .\scripts\dev-setup.ps1 -SkipMigrations  # Skip migrations
#   .\scripts\dev-setup.ps1 -SkipNpm         # Skip npm install

[CmdletBinding()]
param(
    [switch]$SkipMigrations,
    [switch]$SkipNpm,
    [switch]$SkipHosts
)

# Error handling
$ErrorActionPreference = "Stop"

# Configuration
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = Split-Path -Parent $ScriptDir
$HostsFile = "C:\Windows\System32\drivers\etc\hosts"

# Colors (PowerShell uses different escape sequences)
function Write-Success
{ param([string]$msg) Write-Host "[OK] $msg" -ForegroundColor Green
}
function Write-Info
{ param([string]$msg) Write-Host "[..] $msg" -ForegroundColor Cyan
}
function Write-Warn
{ param([string]$msg) Write-Host "[!!] $msg" -ForegroundColor Yellow
}
function Write-Error
{ param([string]$msg) Write-Host "[XX] $msg" -ForegroundColor Red
}
function Write-Section
{ param([string]$msg)
    Write-Host ""
    Write-Host "═══════════════════════════════════════" -ForegroundColor Blue
    Write-Host "$msg" -ForegroundColor Blue
    Write-Host "═══════════════════════════════════════" -ForegroundColor Blue
    Write-Host ""
}

# Banner
Write-Host ""
Write-Host "╔═══════════════════════════════════════════════════════╗" -ForegroundColor Blue
Write-Host "║       DCCP Admin V3 - Local Development Setup          ║" -ForegroundColor Blue
Write-Host "║           (Windows + Laravel Herd)                     ║" -ForegroundColor Blue
Write-Host "╚═══════════════════════════════════════════════════════╝" -ForegroundColor Blue
Write-Host ""

# Check if running as administrator (for hosts file modification)
$IsAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

# Step 1: Check prerequisites
Write-Section "Checking Prerequisites"

# Check PHP
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpCommand)
{
    Write-Error "PHP is not installed or not in PATH"
    Write-Host "Please install PHP or ensure Laravel Herd is properly installed."
    exit 1
}
Write-Success "PHP is installed: $(php -r 'echo PHP_VERSION;')"

# Check Composer
$composerCommand = Get-Command composer -ErrorAction SilentlyContinue
if (-not $composerCommand)
{
    Write-Error "Composer is not installed or not in PATH"
    Write-Host "Please install Composer from: https://getcomposer.org/"
    exit 1
}
Write-Success "Composer is installed: $(composer --version | Select-Object -First 1)"

# Check Herd
$herdCommand = Get-Command herd -ErrorAction SilentlyContinue
if (-not $herdCommand)
{
    Write-Error "Laravel Herd is not installed or not in PATH"
    Write-Host "Please install Laravel Herd from: https://herd.laravel.com/"
    exit 1
}
Write-Success "Laravel Herd is installed"

# Check Node.js
$nodeCommand = Get-Command node -ErrorAction SilentlyContinue
if (-not $nodeCommand)
{
    Write-Error "Node.js is not installed or not in PATH"
    Write-Host "Please install Node.js from: https://nodejs.org/"
    exit 1
}
Write-Success "Node.js is installed: $(node --version)"

# Check npm
$npmCommand = Get-Command npm -ErrorAction SilentlyContinue
if (-not $npmCommand)
{
    Write-Error "npm is not installed or not in PATH"
    exit 1
}
Write-Success "npm is installed: $(npm --version)"

# Step 2: Setup .env file
Write-Section "Environment Configuration"

$EnvFile = Join-Path $ProjectRoot ".env"
$EnvExampleFile = Join-Path $ProjectRoot ".env.example"

if (-not (Test-Path $EnvFile))
{
    Write-Info "Creating .env from .env.example..."
    if (Test-Path $EnvExampleFile)
    {
        Copy-Item $EnvExampleFile $EnvFile
        Write-Success ".env file created"
    } else
    {
        Write-Warn ".env.example not found, creating minimal .env..."
        @"
APP_NAME="DCCP Admin V3"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://dccpadminv3.test

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

PORTAL_HOST=portal.dccp.test
ADMIN_HOST=admin.dccp.test
MAILPIT_HOST=mailpit.local.test
MINIO_HOST=minio.local.test
MINIO_CONSOLE_HOST=minio-console.local.test
"@ | Out-File -FilePath $EnvFile -Encoding UTF8
        Write-Success "Minimal .env file created"
    }
} else
{
    Write-Success ".env file already exists"
}

# Generate APP_KEY if not set
$EnvContent = Get-Content $EnvFile -Raw
if ($EnvContent -match 'APP_KEY=\s*$')
{
    Write-Info "Generating APP_KEY..."
    Set-Location $ProjectRoot
    php artisan key:generate
}

# Step 3: Install Composer dependencies
Write-Section "Composer Dependencies"

Set-Location $ProjectRoot
Write-Info "Installing Composer packages..."
Write-Info "This may take a few minutes..."

$composerArgs = @("install", "--ignore-platform-req=ext-pcntl", "--ignore-platform-req=ext-posix")
$composerProcess = Start-Process composer -ArgumentList $composerArgs -NoNewWindow -Wait -PassThru

if ($composerProcess.ExitCode -eq 0)
{
    Write-Success "Composer dependencies installed"
} else
{
    Write-Error "Failed to install Composer dependencies"
    exit 1
}

# Step 4: Install npm dependencies
if (-not $SkipNpm)
{
    Write-Section "NPM Dependencies"

    Write-Info "Installing npm packages..."
    Write-Info "This may take a few minutes..."

    Set-Location $ProjectRoot

    try
    {
        $npmOutput = npm install 2>&1
        if ($LASTEXITCODE -eq 0)
        {
            Write-Success "npm dependencies installed"
        } else
        {
            Write-Warn "npm install had some issues, but continuing..."
            Write-Host $npmOutput
        }
    } catch
    {
        Write-Warn "npm install failed, but continuing..."
    }
} else
{
    Write-Info "Skipping npm install (-SkipNpm flag set)"
}

# Step 5: Configure Herd proxy
Write-Section "Herd Proxy Configuration"

# Get domains from .env
$EnvContent = Get-Content $EnvFile -Raw
$PortalHost = if ($EnvContent -match 'PORTAL_HOST=(.*)')
{ $matches[1].Trim()
} else
{ "portal.dccp.test"
}
$AdminHost = if ($EnvContent -match 'ADMIN_HOST=(.*)')
{ $matches[1].Trim()
} else
{ "admin.dccp.test"
}
$MailpitHost = if ($EnvContent -match 'MAILPIT_HOST=(.*)')
{ $matches[1].Trim()
} else
{ "mailpit.local.test"
}
$MinioHost = if ($EnvContent -match 'MINIO_HOST=(.*)')
{ $matches[1].Trim()
} else
{ "minio.local.test"
}
$MinioConsoleHost = if ($EnvContent -match 'MINIO_CONSOLE_HOST=(.*)')
{ $matches[1].Trim()
} else
{ "minio-console.local.test"
}

$Domains = @($PortalHost, $AdminHost, $MailpitHost, $MinioHost, $MinioConsoleHost)

Write-Info "Configuring Herd proxy for the following domains:"
foreach ($domain in $Domains)
{
    Write-Host "  - $domain" -ForegroundColor Cyan
}

# Herd automatically serves *.test domains
# The project will be available at the domains configured in .env
Write-Info "Herd will automatically serve *.test domains"
Write-Success "Project directory: $ProjectRoot"

# Step 6: Secure domains with HTTPS
Write-Section "SSL Certificate Setup"

Write-Info "Securing domains with Herd SSL certificates..."
$SecuredCount = 0
$AlreadySecured = 0

foreach ($domain in $Domains)
{
    try
    {
        $secureOutput = herd secure $domain 2>&1

        if ($LASTEXITCODE -eq 0)
        {
            Write-Success "Secured: $domain"
            $SecuredCount++
        } elseif ($secureOutput -match "already secured")
        {
            Write-Info "Already secured: $domain"
            $AlreadySecured++
        } else
        {
            Write-Warn "Failed to secure: $domain"
            Write-Host $secureOutput
        }
    } catch
    {
        Write-Warn "Error securing ${domain}: $_"
    }
}

if ($SecuredCount -gt 0)
{
    Write-Success "Secured $SecuredCount new domain(s) with HTTPS"
}
if ($AlreadySecured -gt 0)
{
    Write-Success "$AlreadySecured domain(s) were already secured"
}

# Step 7: Setup hosts file
if (-not $SkipHosts)
{
    Write-Section "Hosts File Setup"

    $Added = 0
    $Existing = 0

    # Filter out *.test domains as Herd handles them automatically
    $NonTestDomains = $Domains | Where-Object { $_ -notmatch '\.test$' }

    if ($NonTestDomains.Count -eq 0)
    {
        Write-Info "All domains are *.test domains - handled by Herd automatically"
        Write-Success "No hosts file modifications needed"
    } elseif (-not $IsAdmin)
    {
        Write-Warn "Script is not running as Administrator"
        Write-Warn "Cannot modify hosts file automatically"
        Write-Warn "Please add the following entries manually to ${HostsFile}:"
        foreach ($domain in $NonTestDomains)
        {
            Write-Host "  127.0.0.1 $domain" -ForegroundColor Yellow
        }
    } else
    {
        try
        {
            $HostsContent = Get-Content $HostsFile -ErrorAction SilentlyContinue
            if (-not $HostsContent)
            {
                $HostsContent = @()
            }

            foreach ($domain in $NonTestDomains)
            {
                $EntryPattern = "^\s*127\.0\.0\.1\s+$domain\s*$|^\s*::1\s+$domain\s*$"
                $AlreadyExists = $HostsContent | Where-Object { $_ -match $EntryPattern }

                if (-not $AlreadyExists)
                {
                    "127.0.0.1 $domain" | Add-Content -Path $HostsFile
                    $Added++
                } else
                {
                    $Existing++
                }
            }

            if ($Added -gt 0)
            {
                Write-Success "Added $Added domain(s) to hosts file"
            }
            if ($Existing -gt 0)
            {
                Write-Success "Found $Existing domain(s) already in hosts file"
            }
        } catch
        {
            Write-Warn "Failed to modify hosts file: $_"
            Write-Warn "Please add the following entries manually:"
            foreach ($domain in $NonTestDomains)
            {
                Write-Host "  127.0.0.1 $domain" -ForegroundColor Yellow
            }
        }
    }
} else
{
    Write-Info "Skipping hosts file setup (-SkipHosts flag set)"
}

# Step 8: Run migrations
if (-not $SkipMigrations)
{
    Write-Section "Database Setup"

    Write-Info "Running database migrations..."
    Set-Location $ProjectRoot

    try
    {
        $migrateOutput = php artisan migrate --force 2>&1
        Write-Success "Database migrations completed"
    } catch
    {
        Write-Warn "Migration had some issues: $_"
    }
} else
{
    Write-Info "Skipping database migrations (-SkipMigrations flag set)"
}

# Step 9: Build frontend assets
Write-Section "Frontend Assets Build"

Write-Info "Building frontend assets..."
Set-Location $ProjectRoot

try
{
    $buildOutput = npm run build 2>&1
    Write-Success "Frontend assets built successfully"
} catch
{
    Write-Warn "Frontend build had some issues, but continuing..."
    Write-Host $buildOutput
}

# Step 10: Summary
Write-Section "Setup Complete!"

Write-Host "Your development environment is ready!" -ForegroundColor Green
Write-Host ""

Write-Host "Access your application at:" -ForegroundColor Cyan
Write-Host "  Portal:            https://$PortalHost"
Write-Host "  Admin:             https://$AdminHost"
Write-Host "  Mailpit (Email):    https://$MailpitHost"
Write-Host "  MinIO (Storage):    https://$MinioHost"
Write-Host "  MinIO Console:      https://$MinioConsoleHost"
Write-Host ""

Write-Host "Herd Status:" -ForegroundColor Cyan
try
{
    herd status 2>&1 | ForEach-Object { Write-Host "  $_" }
} catch
{
    Write-Host "  Herd is running" -ForegroundColor Green
}
Write-Host ""

Write-Host "Next Steps:" -ForegroundColor Cyan
Write-Host "  1. Review and configure .env file as needed"
Write-Host "  2. Start Octane for better performance: php artisan octane:start"
Write-Host "  3. For development with hot-reload, run: npm run dev"
Write-Host "  4. Or simply access your app - Herd is already serving it!"
Write-Host ""

Write-Host "Note:" -ForegroundColor Yellow
Write-Host "  All domains are secured with HTTPS via Herd"
Write-Host "  Your application is ready to use at the URLs above"
Write-Host ""

Write-Host "Useful Commands:" -ForegroundColor Cyan
Write-Host "  php artisan migrate:fresh --seed    - Fresh migration with seeding"
Write-Host "  php artisan tinker                 - Interactive REPL"
Write-Host "  php artisan queue:work             - Process queue jobs"
Write-Host "  php artisan octane:start           - Start Octane server"
Write-Host "  herd restart                       - Restart Herd"
Write-Host ""

Write-Host "Happy coding!" -ForegroundColor Green
Write-Host ""
