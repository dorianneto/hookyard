---
name: update-changelog
description: Update CHANGELOG.md with a new version entry based on commits since the latest git tag. Use this skill when the user asks to "update the changelog", "generate changelog", "write changelog for new release", or "bump the changelog". Always trigger this skill for changelog generation tasks.
---

# Changelog Updater

Update `CHANGELOG.md` with a new version entry covering all changes from the latest git tag to `HEAD` on `main`.

## Process

### 1. Gather Git Context

Run the following commands to collect raw data:

```bash
# Latest released tag
git describe --tags --abbrev=0

# All commits since that tag (one per line with hash)
git log <latest-tag>..HEAD --oneline

# Files changed since that tag
git diff <latest-tag>..HEAD --name-only

# Full diff stat summary
git diff <latest-tag>..HEAD --stat
```

If there are no commits since the latest tag, stop and tell the user: "No changes since `<tag>` — nothing to add to the changelog."

### 2. Determine the New Version

Inspect the commits and changed files, then infer the bump type using semver rules:

| Bump | When |
|---|---|
| **Major** | Breaking API changes, removed endpoints, incompatible schema changes |
| **Minor** | New features, new endpoints, new UI pages, new integrations |
| **Patch** | Bug fixes, dependency upgrades, config tweaks, documentation only |

If mixed signals exist (e.g., new feature + bug fix), default to **Minor**.

If unsure, ask the user: "Based on the changes I see, this looks like a **{type}** bump to `{version}`. Does that sound right, or would you like a different version?"

Strip the `v` prefix from the tag to compute numeric version (e.g., `v0.3.0` → `0.4.0` for a minor bump).

### 3. Categorize Changes

Group commits and changed file paths into changelog categories. Use file paths to drive categorization:

| Path pattern | Category |
|---|---|
| `src/` (excluding `src/Entity/` ORM-only changes) | Backend |
| `frontend/` | Frontend |
| `config/`, `docker*`, `nginx*`, `supervisord*`, `.env*`, `Dockerfile*` | Infrastructure |
| `migrations/` | Backend (schema) |
| `.claude/`, `CLAUDE.md`, `README*`, `ROADMAP*`, `*.md` | Documentation |
| `composer.json`, `composer.lock`, `package.json`, `package-lock.json` | Dependencies |

Map each commit message to the appropriate section:

- **Added** — new features, new endpoints, new pages, new commands, new integrations
- **Changed** — refactors, migrations, config updates, renamed things, behavior changes
- **Fixed** — bug fixes, security patches, corrected behavior
- **Documentation** — docs-only changes (README, CLAUDE.md, ROADMAP, etc.)

Within each section, group items under `#### Backend`, `#### Frontend`, `#### Infrastructure`, or `#### Observability` sub-headers only when there are 2+ items in a given area. For a single item, omit the sub-header.

### 4. Write the Changelog Entry

Compose the new entry following the exact format used in `CHANGELOG.md`:

```markdown
## [{version}] — {YYYY-MM-DD}

### Added

#### Backend
- {item}

#### Frontend
- {item}

### Changed

- {item}

### Fixed

- {item}

### Documentation

- {item}
```

**Writing rules:**

- Use today's date (`currentDate` from system context if available, otherwise `date +%Y-%m-%d`).
- Each bullet is a single, self-contained fact. Lead with the subject ("Audit module", "Delete dialog", etc.) and describe what changed in plain language.
- Omit any section (`### Added`, `### Fixed`, etc.) if it has no entries.
- Omit sub-headers (`#### Backend`, etc.) if only one item falls under that area within a section.
- Never mention commit hashes, author names, or branch names.
- Keep bullets concise — one to two lines max each.
- For backend items, name the specific class, endpoint, or command when relevant (e.g., "`DoctrineSourceRepository` migrated to QueryBuilder").
- For frontend items, name the page or component (e.g., "Delete dialog requiring confirmation before destructive actions").

### 5. Prepend to CHANGELOG.md

Read the current `CHANGELOG.md`. Insert the new entry **immediately after the header block** (the `# Changelog` heading and its subtitle lines, before the first `---` separator).

The resulting structure must look like:

```
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [NEW VERSION] — TODAY

### ...

---

## [PREVIOUS VERSION] — ...
```

Always preserve the existing content exactly — only prepend, never rewrite existing entries.

### 6. Confirm Output

After writing the file, tell the user:

- The new version string and date
- How many commits were included
- A brief summary of what sections were added (e.g., "3 Added items, 1 Fixed item")
- Any assumptions made about categorization or bump type
