# Push this plugin to GitHub (run after creating an empty repo on github.com).
#
# Usage:
#   .\scripts\push-to-github.ps1 -GitHubUser "your-username"
#   .\scripts\push-to-github.ps1 -GitHubUser "your-username" -RepoName "wc-generic-product-designer" -Private
#
param(
	[Parameter( Mandatory = $true )]
	[string] $GitHubUser,

	[string] $RepoName = "wc-generic-product-designer",

	[switch] $Private
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent ( Split-Path -Parent $MyInvocation.MyCommand.Path )
Set-Location $Root

$RemoteUrl = "https://github.com/$GitHubUser/$RepoName.git"
$PluginFile = Join-Path $Root "wc-generic-product-designer.php"

Write-Host "Updating GitHub Plugin URI header to $GitHubUser/$RepoName ..."
$content = Get-Content -Path $PluginFile -Raw
$content = $content -replace 'GitHub Plugin URI:\s*[^\r\n]+', "GitHub Plugin URI: $GitHubUser/$RepoName"
Set-Content -Path $PluginFile -Value $content -NoNewline

git add wc-generic-product-designer.php
git diff --cached --quiet
if ( $LASTEXITCODE -ne 0 ) {
	git commit -m "Set GitHub Plugin URI for Git Updater"
}

$existing = git remote get-url origin 2>$null
if ( $LASTEXITCODE -eq 0 ) {
	Write-Host "Remote 'origin' already exists: $existing"
	Write-Host "To change it: git remote set-url origin $RemoteUrl"
} else {
	git remote add origin $RemoteUrl
	Write-Host "Added remote origin -> $RemoteUrl"
}

Write-Host ""
Write-Host "Next steps:"
Write-Host "  1. Create repo on GitHub: https://github.com/new"
Write-Host "     Name: $RepoName"
if ( $Private ) { Write-Host "     Visibility: Private" }
Write-Host "     Do NOT add README, .gitignore, or license (already in this repo)."
Write-Host "  2. Push: git push -u origin main"
Write-Host "  3. On WordPress: install Git Updater, add GitHub token if private, install plugin from repo URL."
Write-Host ""
