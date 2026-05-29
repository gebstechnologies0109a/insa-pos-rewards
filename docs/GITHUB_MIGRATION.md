# GitHub migration (ePay Plus / insa-pos-rewards monorepo)

This document records export from the GEBS GitHub remote to a **non-GEBS** GitHub account, and follow-up steps for Forge and CI.

## Migration complete (2026-05-30)

| Item | Value |
|------|--------|
| **Local path** | `c:\laragon\www\ePay Plus` |
| **New GitHub username** | `ronaldo82ba` |
| **Account email** | `ronaldo82ba@gmail.com` (browser session; not exposed via GitHub API) |
| **New private repo** | [`ronaldo82ba/epayplus-platform`](https://github.com/ronaldo82ba/epayplus-platform) |
| **New remote URL** | `https://github.com/ronaldo82ba/epayplus-platform.git` |
| **Previous GEBS `origin`** | `https://github.com/gebstechnologies0109a/insa-pos-rewards.git` (kept as `geb-origin`) |
| **Latest commit on `main`** | `45fa8b5` — docs: record GitHub migration to ronaldo82ba/epayplus-platform |
| **Branches pushed** | `main`, `deploy/epayplus`, `deploy/insa` |
| **Tags** | none |
| **GitHub CLI auth** | Active: **`ronaldo82ba`** (scopes: `gist`, `read:org`, `repo`, `workflow`); inactive: `gebstechnologies0109a` |

### Branch commit SHAs (local = remote on `epayplus-platform`)

| Branch | SHA |
|--------|-----|
| `main` | `45fa8b56a14f6ef3df5731ba9b2ad9435332b3b3` |
| `deploy/epayplus` | `be335e458efec163efdc0d3e0122fa4db292cab8` |
| `deploy/insa` | `4bb841d0dad91a98ecd1ee9ea4d204e45fddffa4` |

### Remotes (after migration)

```text
origin     https://github.com/ronaldo82ba/epayplus-platform.git
geb-origin https://github.com/gebstechnologies0109a/insa-pos-rewards.git
github-new https://github.com/ronaldo82ba/insa-pos-rewards.git  (legacy; optional to remove)
```

- **`origin`** — default push/fetch target (`ronaldo82ba/epayplus-platform`).
- **`geb-origin`** — read-only backup of the old GEBS remote; **not deleted**.
- **`github-new`** — earlier test push to `insa-pos-rewards`; safe to remove with `git remote remove github-new` if unused.

## Security check

- **`.env`** — gitignored (`.gitignore` line 3); present on disk but **not tracked**.
- **`auth.json`** — gitignored; not tracked.
- **History** — no commits found touching `.env` or `auth.json`.
- **Tracked env templates only** — `.env.example`, `postman/ePayPlus-Maya-Biller-Sandbox.env.example` (expected).
- **Do not push** local untracked artifacts: `INSAPOSv2/.gradle/`, `INSAPOSv2/app/build/`, `apk-output/`, root/`ePayPlus` screenshots, `docs/screenshots/`, large extracts under `docs/dafox_apk_extract/` and `docs/device-scans/` (most are untracked; `.gitignore` already excludes `*.apk`, `apk-output/`, `public/build`, `node_modules`, `vendor`).

One screenshot is already in the repo: `docs/device-scans/sarismarthub-screenshot.png` (harmless; no secrets).

## Offline export (backup)

A full mirror bundle was created for handoff or import on another machine:

- **Path:** `C:\Users\Admin\Downloads\epayplus-repo-export.bundle`
- **Size:** ~13.4 MB
- **Verified:** complete history; refs include `main`, `deploy/epayplus`, `deploy/insa`, and stash ref `e8a04f4`

### Restore from bundle (optional, on any machine)

```powershell
mkdir C:\path\to\epayplus-clone
cd C:\path\to\epayplus-clone
git clone C:\Users\Admin\Downloads\epayplus-repo-export.bundle .
git branch -a
```

---

## Laravel Forge (action required)

Update **each Forge site** (INSA and ePay Plus):

1. **Site → Git Repository** — change URL to `https://github.com/ronaldo82ba/epayplus-platform.git` (or SSH equivalent).
2. **Deployment branch** — unchanged: `deploy/insa` or `deploy/epayplus` per site.
3. Re-run deploy or `git pull` on the server once to confirm access (deploy keys or Forge GitHub app must be authorized on the **new** account/repo).

See also: `docs/FORGE_DEPLOY.md`, `docs/DEPLOYMENT_SEPARATION.md`.

## GitHub Actions (Android CI)

Workflows live at:

- `.github/workflows/build-android.yml`
- `.github/workflows/deploy-branches.yml`

They do **not** hardcode `gebstechnologies0109a/insa-pos-rewards`. Enable **Actions** on `ronaldo82ba/epayplus-platform` and re-create **secrets** (signing keys, etc.) under **Settings → Secrets and variables → Actions**.

---

## References to old repo name in code/docs

Strings like `insa-pos-rewards` appear in **Forge deploy scripts and docs** as **site/path naming**, not as the GitHub remote URL. Update Forge git URL only; renaming Forge site folders is optional.

---

## Quick checklist

- [x] `gh auth login` / refresh with non-GEBS account (`ronaldo82ba`, `workflow` scope)
- [x] Create `ronaldo82ba/epayplus-platform` (private) and push all branches
- [ ] Update Forge git repository URL(s) to `https://github.com/ronaldo82ba/epayplus-platform.git`
- [ ] Re-add GitHub Actions secrets on new repo
- [x] Rename remotes (`geb-origin` + new `origin`)
- [ ] Optionally remove `github-new` remote and `ronaldo82ba/insa-pos-rewards` test repo
- [ ] Keep bundle `epayplus-repo-export.bundle` as backup until Forge is verified

## Session notes

- **Repo name chosen:** `epayplus-platform` (private) — clearer monorepo name than reusing `insa-pos-rewards`.
- **Blocker resolved:** initial push rejected without `workflow` OAuth scope; fixed via `gh auth refresh -s workflow`.
- **GEBS remote:** preserved as `geb-origin`; no pushes made to GEBS during this migration.
