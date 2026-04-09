#
# Setup Sail Alias Script for Windows
# This script adds a 'sail' alias to your PowerShell profile
#
# Usage:
#   PowerShell: .\setup-sail-alias.ps1
#   PowerShell (as admin): powershell -ExecutionPolicy Bypass -File setup-sail-alias.ps1
#

param(
    [switch]$Force
)

# Colors for output
function Write-Info {
    param([string]$Message)
    Write-Host "[INFO]" -NoNewline -ForegroundColor Cyan
    Write-Host " $Message"
}

function Write-Success {
    param([string]$Message)
    Write-Host "[SUCCESS]" -NoNewline -ForegroundColor Green
    Write-Host " $Message"
}

function Write-Warn {
    param([string]$Message)
    Write-Host "[WARN]" -NoNewline -ForegroundColor Yellow
    Write-Host " $Message"
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR]" -NoNewline -ForegroundColor Red
    Write-Host " $Message"
}

# Get PowerShell profile path
function Get-ProfilePath {
    $profilePath = $PROFILE

    # Check for PowerShell Core (pwsh)
    if ($PSVersionTable.PSEdition -eq 'Core') {
        $profilePath = $PROFILE.CurrentUserCurrentHost
    }

    return $profilePath
}

# Check if profile directory exists, create if not
function Initialize-ProfileDirectory {
    $profileDir = Split-Path (Get-ProfilePath) -Parent

    if (-not (Test-Path $profileDir)) {
        Write-Info "Creating profile directory: $profileDir"
        New-Item -ItemType Directory -Path $profileDir -Force | Out-Null
    }
}

# Check if alias already exists
function Test-AliasExists {
    param([string]$ProfilePath)

    if (-not (Test-Path $ProfilePath)) {
        return $false
    }

    $content = Get-Content $ProfilePath -ErrorAction SilentlyContinue
    return $content | Select-String -Pattern "Set-Alias\s+sail" -Quiet
}

# Add Sail alias to profile
function Add-SailAlias {
    param([string]$ProfilePath)

    # Create backup
    if (Test-Path $ProfilePath) {
        $backupPath = "$ProfilePath.backup.$(Get-Date -Format 'yyyyMMdd_HHmmss')"
        Copy-Item $ProfilePath $backupPath
        Write-Info "Created backup: $backupPath"
    }

    # Add the alias
    $aliasBlock = @"

# Laravel Sail alias - added by setup-sail-alias.ps1
# Try local sail binary first, fallback to vendor/bin/sail
function Invoke-Sail {
    param(
        [Parameter(ValueFromRemainingArguments=$true)]
        [string[]]$Arguments
    )

    if (Test-Path "sail") {
        .\sail @Arguments
    } elseif (Test-Path "vendor\bin\sail") {
        vendor\bin\sail @Arguments
    } else {
        Write-Error "Sail not found. Make sure you're in a Laravel project directory."
    }
}

Set-Alias -Name sail -Value Invoke-Sail -Scope Global
"@

    # Append to profile
    Add-Content -Path $ProfilePath -Value $aliasBlock -Encoding UTF8
    Write-Success "Sail alias added to $ProfilePath"
}

# Main execution
function Main {
    Write-Host ""
    Write-Host "╔════════════════════════════════════════════════════════════╗"
    Write-Host "║         Laravel Sail Alias Setup for Windows              ║"
    Write-Host "╚════════════════════════════════════════════════════════════╝"
    Write-Host ""

    # Check if running in PowerShell
    if ($null -eq $PSVersionTable) {
        Write-Error "This script must be run in PowerShell"
        exit 1
    }

    Write-Info "PowerShell version: $($PSVersionTable.PSVersion)"
    Write-Info "Edition: $($PSVersionTable.PSEdition)"

    # Get profile path
    $profilePath = Get-ProfilePath
    Write-Info "Profile path: $profilePath"

    # Check if alias already exists
    if (Test-AliasExists $profilePath) {
        Write-Warn "Sail alias already exists in your PowerShell profile"
        if (-not $Force) {
            $response = Read-Host "Do you want to update it? (y/N)"
            if ($response -notmatch "^[Yy]") {
                Write-Info "Aborted by user"
                exit 0 }
    }


            }
        # Initialize profile directory
    Initialize-ProfileDirectory

    # Add alias
    Write-Info "Adding Sail alias..."

    try {
        Add-SailAlias $profilePath
    }
    catch {
        Write-Error "Failed to add alias: $_"
        exit 1
    }

    # Print instructions
    Write-Host ""
    Write-Success "Sail alias setup complete!"
    Write-Host ""
    Write-Host "Next steps:"
    Write-Host "───────────"
    Write-Host "1. Restart PowerShell or run: . `$PROFILE"
    Write-Host "2. Test the alias: sail --version"
    Write-Host ""
    Write-Host "The alias will work in all new PowerShell sessions."
    Write-Host ""
}

# Run main function
Main
