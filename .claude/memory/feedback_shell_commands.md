---
name: feedback-shell-commands
description: User prefers to run shell commands inside Docker containers themselves rather than having Claude execute them
metadata:
  type: feedback
---

User prefers to run shell commands that target the Docker environment themselves (e.g., `composer require`, `php bin/console doctrine:migrations:diff`, `php bin/console doctrine:migrations:migrate`).

**Why:** User wants control over when container commands execute; avoids surprises in the running environment.

**How to apply:** When a task requires running a command inside a Docker container, write the command for the user to run rather than calling it via Bash tool. For local-only commands (git, file edits, tests run outside Docker) proceed normally.
