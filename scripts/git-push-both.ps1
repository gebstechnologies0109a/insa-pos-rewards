param(
    [switch]$All,
    [switch]$Help
)

$ErrorActionPreference = 'Stop'
$Remotes = @('origin', 'personal')

function Get-RepoRoot {
    $root = git rev-parse --show-toplevel 2>$null
    if (-not $root) { throw 'Not inside a git repository.' }
    return $root.Trim()
}

if ($Help) {
    Write-Host 'Usage: git-push-both.ps1 [-All] [-Help]'
    exit 0
}

Set-Location (Get-RepoRoot)

if ($All) {
    foreach ($remote in $Remotes) {
        Write-Host "==> ${remote}: push --all"
        git push $remote --all
        if ($LASTEXITCODE -ne 0) { throw "git push $remote --all failed" }
        Write-Host "==> ${remote}: push --tags"
        git push $remote --tags
        if ($LASTEXITCODE -ne 0) { throw "git push $remote --tags failed" }
    }
} else {
    $branch = (git rev-parse --abbrev-ref HEAD).Trim()
    if ($branch -eq 'HEAD') { throw 'Detached HEAD; checkout a branch first.' }
    foreach ($remote in $Remotes) {
        Write-Host "==> ${remote}: push $branch"
        git push $remote $branch
        if ($LASTEXITCODE -ne 0) { throw "git push $remote $branch failed" }
    }
}

Write-Host 'Done. Both remotes updated.'
