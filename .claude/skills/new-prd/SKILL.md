---
name: new-prd
description: Generate a PRD and tasks file for a new feature. Use this skill whenever the user asks to "create a PRD", "write a PRD", "plan a feature", "create planning files", "write tasks for X", or describes a feature they want to build and needs the planning documents. Always trigger this skill when the user provides a feature prompt and wants structured planning output.
---

# PRD and Tasks Generator

Generate two planning files for a new feature based on a user prompt:
- `.claude/prd/{feature-slug}.md` — Product Requirements Document
- `.claude/tasks/{feature-slug}.md` — Implementation tasks checklist

## Process

### 1. Understand the Prompt

Read the user's feature description. If the prompt is ambiguous or missing critical details, ask targeted questions:
- What problem does this solve?
- Any specific API shape or data model constraints?
- Backend only, frontend only, or full-stack?
- Any integration with existing entities (Source, Endpoint, Event, DeliveryAttempt)?

Do not ask more than 3 clarifying questions at once. If the prompt is clear enough, proceed directly.

### 2. Derive the Feature Slug

Convert the feature name to `kebab-case` for use in file names (e.g. "Webhook Signature Verification" → `webhook-signature-verification`). Keep it short and descriptive.

### 3. Generate the PRD

Write `.claude/prd/{slug}.md` following this exact structure:

```markdown
# PRD: {Feature Name}

## Problem

{One paragraph describing what is missing or painful today. Be specific.}

## Goal

{One paragraph describing what this feature delivers and its scope.}

---

## Requirements

### Functional

- **FR-1**: {requirement}
- **FR-2**: {requirement}
...

### Non-functional

- **NFR-1**: {requirement}
...

---

## API

### `{METHOD} /api/v1/{path}`

Authenticated. Returns HTTP {code}.

**Query/Body parameters:** (table if applicable)

**Response:**

\`\`\`json
{example response}
\`\`\`

---

## Implementation

### Backend

#### {Layer: Value Objects / Port / Repository / Use Case / Controller / Unit Tests}

**Create/Modify** `{file path}`

{Implementation details, code snippets where helpful}

### Frontend

#### {Component/Page}

**Create/Modify** `{file path}`

{Implementation details, code snippets where helpful}

---

## Files Summary

| Action | File |
|---|---|
| Create | `...` |
| Modify | `...` |

---

## Verification

| # | Check |
|---|---|
| 1 | {specific, testable assertion} |
...
```

#### PRD writing rules

- **FR/NFR numbering**: Start FR-1, FR-2… for functional; NFR-1, NFR-2… for non-functional. Keep them atomic — one verifiable fact per item.
- **API section**: Include only if the feature adds or modifies HTTP endpoints. Show full JSON request/response examples.
- **Implementation section**: Be specific — exact file paths, class names, constructor params, method signatures, SQL queries where relevant. Include code snippets for non-trivial logic.
- **Architecture constraints** (always enforce):
  - `src/Domain/` — zero Symfony/Doctrine imports
  - `src/Application/` — ports only, no framework code
  - All new use cases and controllers must use `#[WithMonologChannel('hookyard')]` and inject `LoggerInterface`
  - All logging uses the structured context array, never string interpolation
  - All new endpoints read `$request->attributes->get('request_id')` and pass it as first arg to `execute()`
- **Testing**: Always include a PHPUnit unit test section. Use `createMock()` for ports and `new NullLogger()` for loggers.
- **Verification table**: Each row must be a specific, concrete check (HTTP status codes, exact route, test command, UI element). Aim for 10–20 rows.

### 4. Generate the Tasks File

Write `.claude/tasks/{slug}.md` following this exact structure:

```markdown
# {Feature Name} — Implementation Tasks

All tasks follow the architecture and constraints defined in `.claude/prd/{slug}.md` and `CLAUDE.md`.

---

## Phase 1 — {Layer name, e.g. Application Layer}

- [ ] **1.1** `{file path}` — {precise description of what to create/modify, including constructor params, method signatures, and key logic}
- [ ] **1.2** ...

## Phase 2 — {Layer name, e.g. Infrastructure: Persistence}

- [ ] **2.1** ...

## Phase 3 — {Layer name, e.g. Use Case}

- [ ] **3.1** ...

## Phase 4 — {Layer name, e.g. Controller}

- [ ] **4.1** ...

## Phase 5 — {Layer name, e.g. Unit Tests}

- [ ] **5.1** ...

## Phase 6 — {Layer name, e.g. Frontend}

- [ ] **6.1** ...
```

#### Tasks writing rules

- **Phase ordering**: Follow the dependency order — Domain/Value Objects → Application Ports → Infrastructure (Repository) → Use Case → Controller → Tests → Frontend. Skip phases that don't apply.
- **Task granularity**: One file = one task (usually). If a file is trivial, you may group; if complex, split.
- **Task descriptions**: Include specific file paths, class names, constructor parameters, method signatures, and critical implementation logic. The developer should be able to implement the task from the description alone without re-reading the PRD.
- **All tasks start unchecked** `- [ ]`.
- **No Phase 0** (scaffolding) unless the feature requires new packages or Docker services.
- **Cross-reference**: The header references the PRD file, e.g. `All tasks follow the architecture and constraints defined in \`.claude/prd/{slug}.md\` and \`CLAUDE.md\`.`

### 5. Confirm Output

After writing both files, tell the user:
- Paths of the two files created
- A one-line summary of what each covers
- Any assumptions made (e.g. "I assumed this is backend-only since no UI was mentioned")
