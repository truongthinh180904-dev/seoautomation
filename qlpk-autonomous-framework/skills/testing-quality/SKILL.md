# Skill: Automated Testing and Quality Gates

## Principles
- A test result is a process result, not an AI opinion.
- Save exact command, working directory, exit code, relevant output and timestamp.
- Never convert `NOT_RUN`, skipped, unavailable or partial tests into PASS.
- Redact secrets and personal/clinical data from logs before storing reports.

## Suggested gate order
1. Verify repository state and task scope.
2. Format/lint (only commands actually configured).
3. Static/type checks.
4. Unit tests.
5. Integration/feature tests.
6. Build.
7. E2E tests if configured and safe.
8. Compare all required commands with `ROADMAP.md`.

## Failure behavior
- Any required command fails: feature remains `TESTING`/`IN_PROGRESS`; do not call Reviewer.
- Give the Coder the failing command, exit code and relevant redacted log.
- Re-run failed test and all regression gates required by the task.
- If environment/dependency is unavailable, mark `BLOCKED` or `PARTIAL`, not PASS.
- Do not hide flaky tests; record retry count and outcomes.

## Review/fix loop
- Reviewer is called only after all mandatory automated checks PASS.
- Each review FAIL creates tracked fix tasks.
- Run tests again after fixes before re-review.
- Maximum 3 review rounds per feature by default; after that `BLOCKED`.
- Never mark DONE until tests PASS and Reviewer PASS.
