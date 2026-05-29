# GitHub migration (ePay Plus / insa-pos-rewards monorepo)

This document records export from the GEBS GitHub remote to a **non-GEBS** GitHub account, and follow-up steps for Forge and CI.

## Migration result (completed 2026-05-30)

| Item | Value |
|------|--------|
| **New GitHub account** | **`ronaldo82ba`** (`ronaldo82ba@gmail.com`) — confirmed logged in via Cursor browser |
| **New remote (primary)** | `https://github.com/ronaldo82ba/insa-pos-rewards.git` |
| **Local remote name** | `github-new` → points at the new repo above |
| **Import method** | GitHub **Import repository** (browser), from public GEBS source |
| **Visibility** | Private on `ronaldo82ba/insa-pos-rewards` |
| **GitHub CLI auth** | **Active:** `ronaldo82ba` · **Inactive:** `gebstechnologies0109a` |

### Branches on new remote (verified)

| Branch | Tip commit | Notes |
|--------|------------|--------|
| `main` | `6486596` | fix: define licenseActive in cashier Alpine state |
| `deploy/insa` | `6486596` | same as `main` |
| `deploy/epayplus` | `be335e4` | Fix ePay Plus dashboard and transactions to show all time ranges |

Tags from the GEBS public repo were imported as well (e.g. `insapos-v3.0.47`, `customer-display-v1`).

### Local vs new remote

Local checkout was **behind** the imported repo on `main` and `deploy/insa` at migration time. After switching remotes, run:

```powershell
cd "c:\laragon\www\ePay Plus"
git fetch github-new
git checkout main
git merge github-new/main
git checkout deploy/insa
git merge github-new/deploy/insa
```

(`deploy/epayplus` already matched at `be335e4`.)

### Extra repo on new account

An empty private repo **`ronaldo82ba/epayplus-platform`** was created during setup. It has **no code**. Safe to delete in GitHub → Settings → Danger Zone, or keep as a future rename target.

### Remotes (current)

| Remote | URL | Role |
|--------|-----|------|
| `origin` | `https://github.com/gebstechnologies0109a/insa-pos-rewards.git` | GEBS (unchanged) |
| `github-new` | `https://github.com/ronaldo82ba/insa-pos-rewards.git` | New account |

---

## Current state (local workspace)

| Item | Value |
|------|--------|
| **Local path** | `c:\laragon\www\ePay Plus` |
| **Local `main` (pre-sync)** | `0d79728` — docs: SariSmartHub device scan and report period guide |
| **Branches** | `main`, `deploy/epayplus`, `deploy/insa` |

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

## Make the new repo default `origin` (recommended next step)

Keep GEBS as read-only backup:

```powershell
cd "c:\laragon\www\ePay Plus"

git remote rename origin gebstechnologies
git remote rename github-new origin
git fetch origin
git branch -u origin/main main
git remote -v
```

To **stop** using GEBS entirely (only after you confirm):

```powershell
git remote remove gebstechnologies
```

### Future pushes (gh CLI now on `ronaldo82ba`)

```powershell
gh auth status
git push origin --all
git push origin --tags
```

Switch active gh account if needed: `gh auth switch`.

---

## Laravel Forge

For **each Forge site** (INSA and ePay Plus):

1. **Site → Git Repository** — change URL to `https://github.com/ronaldo82ba/insa-pos-rewards.git` (or SSH equivalent).
2. **Deployment branch** — unchanged: `deploy/insa` or `deploy/epayplus` per site.
3. Re-run deploy or `git pull` on the server once to confirm access (deploy keys or Forge GitHub app must be authorized on the **new** account/repo).

See also: `docs/FORGE_DEPLOY.md`, `docs/DEPLOYMENT_SEPARATION.md`.

## GitHub Actions (Android CI)

Workflows live at:

- `.github/workflows/build-android.yml`
- `.github/workflows/deploy-branches.yml`

They do **not** hardcode `gebstechnologies0109a/insa-pos-rewards`. Enable **Actions** on the new repo and re-create **secrets** (signing keys, etc.) under **Settings → Secrets and variables → Actions**.

---

## References to old repo name in code/docs

Strings like `insa-pos-rewards` appear in **Forge deploy scripts and docs** as **site/path naming**, not as the GitHub remote URL. No workflow files reference the GEBS org. Update Forge git URL only; renaming Forge site folders is optional.

---

## Quick checklist

- [x] Identify new GitHub account (`ronaldo82ba`)
- [x] `gh auth login` with non-GEBS account (active account: `ronaldo82ba`)
- [x] Create/import repo and verify all deploy branches on new remote
- [ ] Sync local branches with `github-new` (see above)
- [ ] Rename remotes (`origin` → new repo)
- [ ] Update Forge git repository URL(s)
- [ ] Re-add GitHub Actions secrets on new repo
- [ ] Optionally delete empty `epayplus-platform` repo
- [ ] Keep bundle `epayplus-repo-export.bundle` as backup until Forge is verified
