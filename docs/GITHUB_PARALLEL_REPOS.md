# Parallel GitHub repos (GEBS + ronaldo82ba)

This monorepo is mirrored on **two GitHub accounts** so both always carry the same branches and history.

## Remotes (canonical layout)

| Remote | URL | Account |
|--------|-----|---------|
| **`geb`** | `https://github.com/gebstechnologies0109a/insa-pos-rewards.git` | GEBS (`gebstechnologies0109a`) |
| **`ronaldo`** | `https://github.com/ronaldo82ba/epayplus-platform.git` | Personal (`ronaldo82ba`) |

- **`ronaldo`** is the default fetch/push target for day-to-day work (`main` tracks `ronaldo/main`).
- **`geb`** keeps the legacy repo name `insa-pos-rewards` in sync for GEBS tooling, Forge, and backups.

Branches to keep aligned on **both** remotes:

- `main`
- `deploy/epayplus`
- `deploy/insa`

### Optional second repo on ronaldo82ba

`ronaldo82ba/insa-pos-rewards` may exist from an earlier import test. **Canonical repo on ronaldo is `epayplus-platform`.** To mirror the same branches there as well:

```powershell
git remote add ronaldo-insa https://github.com/ronaldo82ba/insa-pos-rewards.git
git push ronaldo-insa --all
```

Remove `github-new` was removed locally; use `ronaldo` instead.

## Push both mirrors

```powershell
.\scripts\git-push-both-remotes.ps1
```

Or manually:

```powershell
git push geb --all
git push ronaldo --all
```

## Authentication (GitHub CLI + HTTPS)

Both accounts can be logged in via `gh auth login`. Check status:

```powershell
gh auth status
```

Git HTTPS pushes use the **active** `gh` account. Switch before each remote if needed:

```powershell
gh auth switch --user gebstechnologies0109a
git push geb --all

gh auth switch --user ronaldo82ba
git push ronaldo --all
```

The helper script prints reminders; it does not switch accounts automatically.

Required scopes: `repo` (and `workflow` on ronaldo if pushing workflow files).

## Laravel Forge

You can point Forge at **either** mirror (same branches):

| Site | Branch | GEBS git URL | ronaldo git URL |
|------|--------|--------------|-----------------|
| ePay Plus | `deploy/epayplus` | `https://github.com/gebstechnologies0109a/insa-pos-rewards.git` | `https://github.com/ronaldo82ba/epayplus-platform.git` |
| INSA | `deploy/insa` | same GEBS URL | same ronaldo URL |

Typical setups:

- **Leave production Forge on GEBS** (`geb` remote URL) and use `ronaldo` as backup / personal CI.
- **Move Forge to ronaldo** when ready; keep pushing to `geb` until GEBS decommissioned.

Authorize deploy keys or the Forge GitHub app on the account that owns the repo URL you choose.

See also: `docs/FORGE_DEPLOY.md`, `docs/DEPLOYMENT_SEPARATION.md`.

## GitHub Actions

Workflows: `.github/workflows/build-android.yml`, `.github/workflows/deploy-branches.yml`.

Enable Actions and recreate **secrets** on **each** repo you use for CI (`geb` and/or `ronaldo`).

## Security (unchanged)

- `.env`, `auth.json` — gitignored; do not commit.
- Do not push build artifacts (`INSAPOSv2/app/build/`, `apk-output/`, etc.).

## Offline backup

Bundle (if created): `C:\Users\Admin\Downloads\epayplus-repo-export.bundle` — full history mirror for handoff.

## Migration history

Export from GEBS to `ronaldo82ba/epayplus-platform` was completed 2026-05-30. This document replaces `GITHUB_MIGRATION.md` with an ongoing **dual-mirror** workflow instead of a one-way move.

## Quick checklist

- [x] Remotes named `geb` and `ronaldo`
- [ ] After each feature merge: run `.\scripts\git-push-both-remotes.ps1` (with correct `gh auth switch`)
- [ ] Forge URL(s) documented per site (GEBS vs ronaldo)
- [ ] Actions secrets on each repo used for CI
