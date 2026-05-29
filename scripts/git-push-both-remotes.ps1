# Push all local branches to both GitHub mirrors (geb + ronaldo).
# Run from repo root: .\scripts\git-push-both-remotes.ps1

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $RepoRoot

function Write-AuthHint {
    param([string]$User)
    Write-Host "  If push is denied, run: gh auth switch --user $User" -ForegroundColor Yellow
}

Write-Host "Pushing all branches to geb (gebstechnologies0109a/insa-pos-rewards)..."
Write-AuthHint -User "gebstechnologies0109a"
git push geb --all
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host ""
Write-Host "Pushing all branches to ronaldo (ronaldo82ba/epayplus-platform)..."
Write-AuthHint -User "ronaldo82ba"
git push ronaldo --all
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host ""
Write-Host "Done. Both remotes updated." -ForegroundColor Green
