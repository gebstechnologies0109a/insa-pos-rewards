# Dual GitHub remotes (GEBS + personal)

This repo keeps two GitHub copies in sync:

- **origin** — gebstechnologies0109a/insa-pos-rewards (GEBS / Laravel Forge deploy)
- **personal** — ronaldo82ba/insa-pos (private mirror)

Branches in use: main, deploy/insa, deploy/epayplus.

## Push to both

PowerShell (repo root):

    .\scripts\git-push-both.ps1

All branches and tags:

    .\scripts\git-push-both.ps1 -All

Git Bash:

    ./scripts/git-push-both.sh
    ./scripts/git-push-both.sh --all

Local alias:

    git push-both

## Auth

If pushes to origin fail while personal succeeds, switch GitHub account:

    gh auth switch -u gebstechnologies0109a

## Verify sync

    git ls-remote origin refs/heads/main refs/heads/deploy/insa
    git ls-remote personal refs/heads/main refs/heads/deploy/insa

Tips should match on both remotes.

Scripts never use --force. git push origin alone does not update personal.
