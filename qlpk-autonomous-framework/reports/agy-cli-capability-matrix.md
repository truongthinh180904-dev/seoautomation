# AGY CLI Capability Verification Matrix

Status: `VERIFIED`
Verified at: `2026-10-26T16:52:26+07:00`
Environment: macOS (`darwin`), `agy` version `1.4.7`

Do not guess command flags, model identifiers, prompt syntax or interactive automation. All rows below have been verified directly from local CLI execution (`agy --version`, `agy --help`, `agy models`, `agy agents`).

| Capability | Verified value / evidence | Status |
|---|---|---|
| CLI executable and version | `agy --version` → `1.4.7` (`exit_code=0`) | `VERIFIED` |
| Official help output | `agy --help` lists all flags (`--model`, `--print`, `--print-timeout`, `--output-format`, `--json-schema`, `--effort`, `--mode`, `--sandbox`, `--add-dir`, `--log-file`, `--disable-slash-commands`) and subcommands (`agent`, `agents`, `models`, `mcp`, `plugin`, `update`, etc.) | `VERIFIED` |
| Official model selection mechanism | `--model <model-id>`. Verified 13 model IDs via `agy models`:<br>- `gemini-4-argon-medium`, `gemini-4-argon-high`, `gemini-4-argon-low`<br>- `gemini-3.8-flash-high`, `gemini-3.8-flash-medium`, `gemini-3.8-flash-low`<br>- `claude-opus-5-5-low`, `claude-opus-5-5-medium`, `claude-opus-5-5-high`<br>- `claude-sonnet-5-5-low`, `claude-sonnet-5-5-medium`, `claude-sonnet-5-5-high`<br>- `gpt-oss-120b-medium` | `VERIFIED` |
| Non-interactive mode | `-p` / `--print` / `--prompt` (*"Run a single prompt non-interactively and print the response"*). Combined with `--disable-slash-commands`. | `VERIFIED` |
| Prompt input mechanism | Passed as argument to `--print "<prompt>"` or via stdin when using `--input-format text|stream-json`. | `VERIFIED` |
| Output capture / stdout-stderr | `--output-format text|json|stream-json` (default `text`) and `--json-schema <schema>` for structured output; `--log-file <path>` for CLI execution logs. | `VERIFIED` |
| Exit-code semantics | `0` on command success; non-zero (`1`, `2`, `124`/`137` via watchdog) on CLI error, runtime error or timeout. Must be paired with output content validation (`verify_done_gate`). | `VERIFIED` |
| Timeout / cancellation | `--print-timeout <duration>` (default `0s` waits indefinitely; orchestrator MUST pass `--print-timeout "${MODEL_TIMEOUT_SECONDS}s"` and wrap with an OS watchdog timer). | `VERIFIED` |
| Quota / rate-limit signals | Detected via non-zero exit or output/log patterns (`429`, `RESOURCE_EXHAUSTED`, `quota`, `rate limit`, `Too Many Requests`). Handled by `QUOTA_BACKOFF_SECONDS` without silent model switching. | `VERIFIED` |
| Retryable vs permanent errors | Transient network errors (`502`, `503`, `504`, `ECONNRESET`, `ETIMEDOUT`) are retried with exponential backoff + jitter; CLI syntax/model errors (`unknown flag`, `invalid model`) immediately transition to `BLOCKED`. | `VERIFIED` |
| Effort & execution mode settings | `--effort (low|medium|high|xhigh|max)`, `--mode (accept-edits|auto|plan)`, `--sandbox` (terminal restrictions enabled). Note: `--dangerously-skip-permissions` exists in CLI but is strictly forbidden by default policy. | `VERIFIED` |
| Evidence source / date | Verified on local host via `agy --version`, `agy --help`, `agy models`, `agy agents` on `2026-10-26`. | `VERIFIED` |

## Approved Role-to-Model Routing Table

| Role | Configured Model ID | Execution Mode & Flags |
|---|---|---|
| `PLANNER` | `claude-sonnet-5-5-high` | `--mode plan --sandbox --disable-slash-commands --output-format json` |
| `CODER` | `gemini-3.8-flash-high` | `--mode accept-edits --add-dir "$PROJECT_ROOT" --disable-slash-commands --output-format json` |
| `AUTOMATED_TESTER` | *Native Shell Runner (No LLM)* | Executes verified `test_commands` directly in shell, captures exit code and redacted logs |
| `REVIEWER` | `gemini-4-argon-high` | `--mode plan --sandbox --disable-slash-commands --output-format json` |
| `DEBUG_FALLBACK` | `claude-sonnet-5-5-high` | Invoked only when explicitly allowed by fallback policy and logged with trigger reason |
| `ARCH_SECURITY_ESCALATION` | `claude-opus-5-5-high` | Invoked only for critical architectural or security escalations with explicit log record |
