# Build wc-generic-product-designer.zip with the correct WordPress folder name.
#
# Usage:
#   .\scripts\build-plugin-zip.ps1
#
param(
	[string] $PluginSlug = 'wc-generic-product-designer-main'
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent ( Split-Path -Parent $MyInvocation.MyCommand.Path )
$BuildDir = Join-Path $Root 'build'
$StageDir = Join-Path $BuildDir $PluginSlug
$ZipPath = Join-Path $Root ( "$PluginSlug.zip" )

$ExcludeDirs = @(
	'.git',
	'.github',
	'.cursor',
	'.idea',
	'.vscode',
	'node_modules',
	'build'
)

if ( Test-Path $BuildDir ) {
	Remove-Item -Recurse -Force $BuildDir
}
New-Item -ItemType Directory -Path $StageDir -Force | Out-Null

Get-ChildItem -Path $Root -Force | ForEach-Object {
	if ( $ExcludeDirs -contains $_.Name ) {
		return
	}
	Copy-Item -Path $_.FullName -Destination ( Join-Path $StageDir $_.Name ) -Recurse -Force
}

if ( Test-Path $ZipPath ) {
	Remove-Item -Force $ZipPath
}

Compress-Archive -Path $StageDir -DestinationPath $ZipPath -Force
Remove-Item -Recurse -Force $BuildDir

Write-Host "Created $ZipPath"
Write-Host "Upload this zip via Plugins -> Add New -> Upload Plugin (updates existing plugin folder)."
