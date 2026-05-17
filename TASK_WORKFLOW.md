# AI Coding Workflow

## Workflow Rules

1. Only work on ONE isolated task at a time
2. Never refactor unrelated modules
3. Never rewrite architecture without approval
4. Preserve strict typing
5. Preserve API contracts
6. Preserve queue workflows
7. Preserve database integrity
8. Never remove business logic
9. Only clean dead code safely
10. Always follow modular architecture

---

# Required Flow

Architecture
→ Task Breakdown
→ Antigravity Implementation
→ Cursor Review
→ Manual Test
→ Git Commit
→ Next Task

---

# Git Strategy

Commit after every completed task.

Example:
feat(keyword): implement xlsx upload module

fix(queue): retry failed article jobs

refactor(api): optimize article service

---

# AI Safety Rules

Antigravity must:

* avoid massive refactors
* avoid touching unrelated modules
* avoid deleting configs
* avoid changing env structure
* avoid changing database schema unexpectedly

---

# Task Scope Rules

Each task should:

* affect minimal files
* have clear acceptance criteria
* be independently testable
* preserve backward compatibility
