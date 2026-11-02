#!/usr/bin/env bash
# ==============================================================================
# QLPK MULTI-AGENT SAFE AUTONOMOUS ORCHESTRATOR
# Tech Stack: Laravel 12/13 (PHP 8.3) + Next.js 16 (React 19, Tailwind CSS v4)
# Spec: Multi-Agent Safe Autonomous Framework Spec (Anti-Fake-Done)
# ==============================================================================

set -o pipefail

# --- PATH & DIRECTORIES ---
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
FRAMEWORK_DIR="${SCRIPT_DIR}"

LOGS_DIR="${FRAMEWORK_DIR}/logs"
STATE_DIR="${FRAMEWORK_DIR}/.agy-state"
REPORTS_DIR="${FRAMEWORK_DIR}/reports/tasks"
TEMPLATES_DIR="${FRAMEWORK_DIR}/templates"
SKILLS_DIR="${FRAMEWORK_DIR}/skills"

mkdir -p "${LOGS_DIR}" "${STATE_DIR}" "${REPORTS_DIR}"

MAIN_LOG="${LOGS_DIR}/orchestrator.log"
LOCK_FILE="${STATE_DIR}/orchestrator.lock"
STOP_FILE="${STATE_DIR}/STOP"
PID_FILE="${STATE_DIR}/orchestrator.pid"

# --- MODEL ROUTING CONFIGURATION (ĐỊNH TUYẾN MODEL CHUẨN) ---
PLANNER_MODEL="${PLANNER_MODEL:-gemini-4-argon-medium}"
CODER_MODEL="${CODER_MODEL:-gemini-3.8-flash-high}"
REVIEWER_MODEL="${REVIEWER_MODEL:-gemini-4-argon-medium}"
DEBUG_FALLBACK_MODEL="${DEBUG_FALLBACK_MODEL:-gemini-3.8-flash-high}"

# --- CONFIGURATION & THRESHOLDS ---
ALLOWED_BRANCHES="${ALLOWED_BRANCHES:-dev}"
AUTO_PUSH_BRANCH="${AUTO_PUSH_BRANCH:-dev}"
FORCE_RUN="${FORCE_RUN:-0}"
CPU_LOAD_PER_CORE_LIMIT="${CPU_LOAD_PER_CORE_LIMIT:-4.0}"
MIN_FREE_MEMORY_PERCENT="${MIN_FREE_MEMORY_PERCENT:-10}"
RESOURCE_POLL_INTERVAL_SECONDS="${RESOURCE_POLL_INTERVAL_SECONDS:-15}"
RESOURCE_WAIT_SECONDS="${RESOURCE_WAIT_SECONDS:-300}"

TASK_COOLDOWN_SECONDS="${TASK_COOLDOWN_SECONDS:-15}"
ERROR_COOLDOWN_SECONDS="${ERROR_COOLDOWN_SECONDS:-30}"
QUOTA_BACKOFF_SECONDS="${QUOTA_BACKOFF_SECONDS:-900}"

MODEL_TIMEOUT_SECONDS="${MODEL_TIMEOUT_SECONDS:-900}"
TASK_TIMEOUT_SECONDS="${TASK_TIMEOUT_SECONDS:-3600}"
MAX_TASKS_PER_SESSION="${MAX_TASKS_PER_SESSION:-50}"
SESSION_TIMEOUT_SECONDS="${SESSION_TIMEOUT_SECONDS:-43200}"

ALLOW_UNTRACKED_REGEX='^[?MADRCU! ]{2}[[:space:]]+(qlpk-autonomous-framework/(logs|reports|\.agy-state)|\.DS_Store)'

# --- LOGGING UTILITIES ---
log() {
    local level="$1"; shift
    local ts
    ts="$(date '+%Y-%m-%d %H:%M:%S')"
    echo "[${ts}] [${level}] $*" | tee -a "${MAIN_LOG}"
}
log_info()    { log "INFO" "$@"; }
log_warn()    { log "WARN" "$@"; }
log_error()   { log "ERROR" "$@"; }
log_success() { log "SUCCESS" "$@"; }

# --- SAFETY: FORBIDDEN COMMAND FILTER ---
assert_safe_command() {
    local cmd="$1"
    local forbidden_regex='(migrate:(fresh|reset|refresh)|db:wipe|DROP[[:space:]]+(DATABASE|TABLE)|TRUNCATE[[:space:]]+TABLE|git[[:space:]]+(reset[[:space:]]+--hard|clean|push[[:space:]]+--force|stash)|--dangerously-skip-permissions)'
    if echo "${cmd}" | grep -Eiq "${forbidden_regex}"; then
        log_error "DANGEROUS COMMAND DETECTED: '${cmd}'. Execution strictly forbidden!"
        return 1
    fi
    return 0
}

# --- SYSTEM RESOURCES & HEALTH CHECK ---
check_system_resources() {
    log_info "Checking system resources (CPU load & RAM)..."
    local waited=0

    while [ "${waited}" -lt "${RESOURCE_WAIT_SECONDS}" ]; do
        # 1. Check STOP file
        if [ -f "${STOP_FILE}" ]; then
            log_warn "STOP file detected at ${STOP_FILE}. Gracefully aborting."
            return 2
        fi

        # 2. CPU load per core (macOS / Linux compatible)
        local ncpu=4
        local load_1m=1.0
        if [[ "$OSTYPE" == "darwin"* ]]; then
            ncpu="$(sysctl -n hw.ncpu 2>/dev/null || echo 4)"
            load_1m="$(sysctl -n vm.loadavg 2>/dev/null | awk '{print $2}' || echo 1.0)"
        else
            ncpu="$(nproc 2>/dev/null || echo 4)"
            load_1m="$(awk '{print $1}' /proc/loadavg 2>/dev/null || echo 1.0)"
        fi

        local load_per_core
        load_per_core="$(awk -v l="${load_1m}" -v c="${ncpu}" 'BEGIN {if (c > 0) printf "%.2f", l / c; else print 1.0}')"

        # 3. Available Memory % (macOS Unified Memory: free + speculative + inactive / Linux available)
        local free_mem_pct=50
        if [[ "$OSTYPE" == "darwin"* ]]; then
            local page_size free_pages speculative_pages inactive_pages purgeable_pages total_mem_bytes
            page_size="$(vm_stat | grep 'page size of' | awk '{print $8}' || echo 4096)"
            free_pages="$(vm_stat | grep 'Pages free:' | awk '{print $3}' | tr -d '.' || echo 0)"
            speculative_pages="$(vm_stat | grep 'Pages speculative:' | awk '{print $3}' | tr -d '.' || echo 0)"
            inactive_pages="$(vm_stat | grep 'Pages inactive:' | awk '{print $3}' | tr -d '.' || echo 0)"
            purgeable_pages="$(vm_stat | grep 'Pages purgeable:' | awk '{print $3}' | tr -d '.' || echo 0)"
            total_mem_bytes="$(sysctl -n hw.memsize 2>/dev/null || echo 17179869184)"

            local avail_bytes
            avail_bytes="$(( (free_pages + speculative_pages + inactive_pages + purgeable_pages) * page_size ))"
            free_mem_pct="$(awk -v a="${avail_bytes}" -v t="${total_mem_bytes}" 'BEGIN {if (t > 0) printf "%.1f", (a / t) * 100; else print 50}')"
        else
            free_mem_pct="$(free 2>/dev/null | awk '/Mem:/ {if ($2 > 0) printf "%.1f", ($7 / $2) * 100; else print 50}' || echo 50)"
        fi

        # 4. Evaluate against thresholds
        local cpu_ok mem_ok
        cpu_ok="$(awk -v cur="${load_per_core}" -v lim="${CPU_LOAD_PER_CORE_LIMIT}" 'BEGIN {print (cur <= lim) ? 1 : 0}')"
        mem_ok="$(awk -v cur="${free_mem_pct}" -v lim="${MIN_FREE_MEMORY_PERCENT}" 'BEGIN {print (cur >= lim) ? 1 : 0}')"

        if [ "${cpu_ok}" -eq 1 ] && [ "${mem_ok}" -eq 1 ]; then
            log_success "Resources healthy: CPU load/core=${load_per_core} (<= ${CPU_LOAD_PER_CORE_LIMIT}), Free RAM=${free_mem_pct}% (>= ${MIN_FREE_MEMORY_PERCENT}%)"
            return 0
        fi

        log_warn "System stressed: CPU=${load_per_core}/${CPU_LOAD_PER_CORE_LIMIT}, RAM=${free_mem_pct}%/${MIN_FREE_MEMORY_PERCENT}%. Cooldown ${RESOURCE_POLL_INTERVAL_SECONDS}s (Waited ${waited}/${RESOURCE_WAIT_SECONDS}s)..."
        sleep "${RESOURCE_POLL_INTERVAL_SECONDS}"
        waited=$((waited + RESOURCE_POLL_INTERVAL_SECONDS))
    done

    log_error "System resource wait timeout (${RESOURCE_WAIT_SECONDS}s). Halting safely to prevent OOM / overheating."
    return 1
}

# --- GIT SAFETY & CLEAN TREE ENFORCEMENT ---
assert_clean_git_tree() {
    cd "${PROJECT_ROOT}" || exit 1

    # Check branch
    local current_branch
    current_branch="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")"

    if [ "${FORCE_RUN}" -ne 1 ]; then
        local branch_allowed=0
        IFS=',' read -ra ADDR <<< "${ALLOWED_BRANCHES}"
        for b in "${ADDR[@]}"; do
            if [ "${current_branch}" = "${b}" ]; then
                branch_allowed=1
                break
            fi
        done

        if [ "${branch_allowed}" -eq 0 ]; then
            log_error "Current branch is '${current_branch}'. Required branch: [${ALLOWED_BRANCHES}]. Aborting for branch protection! (Set FORCE_RUN=1 to override)"
            return 1
        fi
    else
        log_warn "FORCE_RUN=1 active. Skipping branch restriction (Current: ${current_branch})."
    fi

    # Check clean tree
    local dirty_files
    dirty_files="$(git status --porcelain 2>/dev/null | grep -vE "${ALLOW_UNTRACKED_REGEX}" || true)"
    if [ -n "${dirty_files}" ]; then
        log_error "Dirty git tree detected before task run. Untracked/uncommitted files:"
        echo "${dirty_files}" | tee -a "${MAIN_LOG}"
        log_error "Clean or stash changes before running orchestrator."
        return 1
    fi

    log_success "Git tree clean on branch '${current_branch}'."
    return 0
}

# --- ANTI-FAKE-DONE VERIFICATION GATE ---
verify_done_gate() {
    local task_id="$1"
    local run_id="$2"

    local test_report="${REPORTS_DIR}/${task_id}-test.md"
    local review_report="${REPORTS_DIR}/${task_id}-review.md"

    log_info "Verifying Anti-Fake-Done Gate for ${task_id} (RUN_ID: ${run_id})..."

    # 1. Both reports must exist
    if [ ! -f "${test_report}" ]; then
        log_error "Gate Failure: Test report missing (${test_report})."
        return 1
    fi
    if [ ! -f "${review_report}" ]; then
        log_error "Gate Failure: Review report missing (${review_report})."
        return 1
    fi

    # 2. RUN_ID must match current session in both reports
    if ! grep -Eq "(\*\*RUN_ID\*\*|RUN_ID):[[:space:]]*\`?${run_id}\`?" "${test_report}"; then
        log_error "Gate Failure: Test report RUN_ID does not match current run (${run_id})."
        return 1
    fi
    if ! grep -Eq "(\*\*RUN_ID\*\*|RUN_ID):[[:space:]]*\`?${run_id}\`?" "${review_report}"; then
        log_error "Gate Failure: Review report RUN_ID does not match current run (${run_id})."
        return 1
    fi

    # 3. Overall results must be PASS
    if ! grep -Eiq "(\*\*OVERALL_TEST_RESULT\*\*|OVERALL_TEST_RESULT):[[:space:]]*\`?PASS\`?" "${test_report}"; then
        log_error "Gate Failure: Test report does NOT have OVERALL_TEST_RESULT: PASS."
        return 1
    fi

    if ! grep -Eiq "(\*\*OVERALL_REVIEW_RESULT\*\*|OVERALL_REVIEW_RESULT):[[:space:]]*\`?PASS\`?" "${review_report}"; then
        log_error "Gate Failure: Review report does NOT have OVERALL_REVIEW_RESULT: PASS."
        return 1
    fi

    # 4. Check for verdict PASS json
    if ! grep -q '"verdict": "PASS"' "${review_report}"; then
        log_error "Gate Failure: Reviewer verdict JSON missing '\"verdict\": \"PASS\"'."
        return 1
    fi

    log_success "ANTI-FAKE-DONE GATE PASSED: ${task_id} verified by real test exit code and reviewer review."
    return 0
}

# --- RUN AUTOMATED TESTER (REAL SHELL RUNNER) ---
run_automated_tester() {
    local task_id="$1"
    local run_id="$2"
    local test_cmd="$3"

    local test_report="${REPORTS_DIR}/${task_id}-test.md"
    log_info "Running Automated Tester for ${task_id}..."

    if ! assert_safe_command "${test_cmd}"; then
        return 1
    fi

    local start_time
    start_time="$(date '+%Y-%m-%d %H:%M:%S')"
    local output_file
    output_file="$(mktemp)"

    cd "${PROJECT_ROOT}" || return 1

    # Execute test command directly
    local exit_code=0
    eval "${test_cmd}" > "${output_file}" 2>&1 || exit_code=$?

    local end_time
    end_time="$(date '+%Y-%m-%d %H:%M:%S')"
    local overall_result="FAIL"
    [ "${exit_code}" -eq 0 ] && overall_result="PASS"

    # Write test report
    cat <<EOF > "${test_report}"
# Task Test Report: ${task_id}

- **RUN_ID**: ${run_id}
- **Task ID**: ${task_id}
- **Timestamp Start**: ${start_time}
- **Timestamp End**: ${end_time}
- **Command Executed**: \`${test_cmd}\`
- **Exit Code**: ${exit_code}
- **OVERALL_TEST_RESULT**: ${overall_result}

## Output Summary (Last 120 lines)
\`\`\`text
$(tail -n 120 "${output_file}")
\`\`\`
EOF
    rm -f "${output_file}"

    if [ "${exit_code}" -ne 0 ]; then
        log_error "Automated test FAILED with exit code ${exit_code} for ${task_id}."
        return 1
    fi

    log_success "Automated test PASSED (exit code 0) for ${task_id}."
    return 0
}

# --- SLEEP & AWAKE PREVENTION + CLEANUP ---
cleanup_on_exit() {
    if [ -f "${STATE_DIR}/caffeinate.pid" ]; then
        kill "$(cat "${STATE_DIR}/caffeinate.pid" 2>/dev/null)" 2>/dev/null || true
        rm -f "${STATE_DIR}/caffeinate.pid"
    fi
    rm -f "${LOCK_FILE}" "${PID_FILE}"
}

init_caffeinate() {
    if [[ "$OSTYPE" == "darwin"* ]] && command -v caffeinate >/dev/null 2>&1; then
        log_info "Spawning macOS caffeinate to prevent sleep during overnight execution..."
        caffeinate -dimsu -w $$ &
        local caff_pid=$!
        echo "${caff_pid}" > "${STATE_DIR}/caffeinate.pid"
    fi
}

# --- HEADLESS SNAPSHOT HELPERS (AVOIDS SHELL 'command' TOOL IN HEADLESS AGY) ---
refresh_workspace_snapshot() {
    local snapshot_file="${STATE_DIR}/workspace-files.txt"
    (
        cd "${PROJECT_ROOT}" || exit 0
        echo "# Workspace File Inventory (Generated at $(date '+%Y-%m-%d %H:%M:%S'))"
        echo "# Project Root: ${PROJECT_ROOT}"
        find PHASE_1_AUDIT.md HANDOVER_CONTEXT.md AGENTS.md qlpk-autonomous-framework backend frontend \
            -maxdepth 6 \
            -not -path '*/vendor/*' \
            -not -path '*/node_modules/*' \
            -not -path '*/.next/*' \
            -not -path '*/storage/*' \
            -not -path '*/bootstrap/cache/*' \
            -not -path 'qlpk-autonomous-framework/logs/*' \
            -not -path 'qlpk-autonomous-framework/.agy-state/*' \
            2>/dev/null | sort | sed "s|^|${PROJECT_ROOT}/|"
    ) > "${snapshot_file}"
}

generate_review_diff_snapshot() {
    local task_id="$1"
    local diff_file="${STATE_DIR}/${task_id}-diff.patch"
    (
        cd "${PROJECT_ROOT}" || exit 0
        echo "=== GIT STATUS (EXCLUDING LOGS/STATE) ==="
        git status --porcelain 2>/dev/null | grep -vE "${ALLOW_UNTRACKED_REGEX}" || true
        echo ""
        echo "=== GIT DIFF HEAD ==="
        git diff HEAD 2>/dev/null || true
    ) > "${diff_file}"
}

# --- AGENT INVOCATION WRAPPERS (AGY CLI BINDINGS) ---
invoke_planner() {
    local prompt="$1"
    log_info "Invoking PLANNER (Model: ${PLANNER_MODEL}, Mode: plan)..."
    cd "${PROJECT_ROOT}" || return 1
    agy --model "${PLANNER_MODEL}" --mode plan -p "${prompt}"
}

invoke_coder() {
    local prompt="$1"
    local task_id="${2:-TASK}"
    local model="${CODER_MODEL}"
    local coder_log="${LOGS_DIR}/${task_id}-coder.log"
    refresh_workspace_snapshot
    log_info "Invoking CODER for ${task_id} (Model: ${model}, Mode: accept-edits)..."

    cd "${PROJECT_ROOT}" || return 1
    local exit_code=0
    agy --model "${model}" --mode accept-edits -p "${prompt}" > "${coder_log}" 2>&1 || exit_code=$?

    # Self-heal if model accidentally tried calling run_command ('command' permission denied in headless -p)
    if grep -Fq 'required the "command" permission' "${coder_log}" 2>/dev/null; then
        log_warn "Coder triggered headless 'command' permission auto-denial on ${task_id}. Retrying immediately with strict FILE-TOOLS-ONLY enforcement..."
        refresh_workspace_snapshot
        local strict_retry_prompt="CẢNH BÁO KHẨN CẤP: Lượt chạy vừa rồi của bạn bị HỦY NGAY LẬP TỨC vì bạn đã gọi tool chạy lệnh shell (\`run_command\` / \`command\`) trong chế độ headless \`-p\`!
TUYỆT ĐỐI KHÔNG ĐƯỢC GỌI \`run_command\`, \`ask_question\`, hay \`invoke_subagent\` DƯỚI BẤT KỲ HÌNH THỨC NÀO!
Hãy CHỈ SỬ DỤNG 3 công cụ đã được cấp quyền sẵn là: \`read_file\`, \`write_file\`, \`edit_file\`.
Để xem danh sách toàn bộ file/thư mục hiện có trong dự án (thay cho \`ls\`/\`find\`), hãy dùng \`read_file\` đọc \`${PROJECT_ROOT}/qlpk-autonomous-framework/.agy-state/workspace-files.txt\`.

${prompt}"
        exit_code=0
        agy --model "${model}" --mode accept-edits -p "${strict_retry_prompt}" >> "${coder_log}" 2>&1 || exit_code=$?
    fi

    # Fallback logic on Rate Limit (429) or quota message
    if [ "${exit_code}" -eq 429 ] || [ "${exit_code}" -eq 137 ] || grep -Eiq "(429|RESOURCE_EXHAUSTED|quota|rate limit)" "${coder_log}" 2>/dev/null; then
        log_warn "Coder quota/rate limit encountered (${exit_code}). Activating DEBUG_FALLBACK_MODEL: ${DEBUG_FALLBACK_MODEL} after ${QUOTA_BACKOFF_SECONDS}s backoff..."
        sleep "${QUOTA_BACKOFF_SECONDS}"
        refresh_workspace_snapshot
        exit_code=0
        agy --model "${DEBUG_FALLBACK_MODEL}" --mode accept-edits -p "${prompt}" >> "${coder_log}" 2>&1 || exit_code=$?
    fi
    return "${exit_code}"
}

invoke_reviewer() {
    local task_id="$1"
    local prompt="$2"
    local model="${REVIEWER_MODEL}"
    local review_report="${REPORTS_DIR}/${task_id}-review.md"
    local reviewer_err="${LOGS_DIR}/${task_id}-reviewer.err.log"
    generate_review_diff_snapshot "${task_id}"
    log_info "Invoking REVIEWER for ${task_id} (Model: ${model}, Mode: plan)..."

    cd "${PROJECT_ROOT}" || return 1
    local exit_code=0
    agy --model "${model}" --mode plan -p "${prompt}" > "${review_report}" 2> "${reviewer_err}" || exit_code=$?

    # Self-heal if Reviewer tried calling run_command ('command' permission denied in headless plan mode)
    if grep -Fq 'required the "command" permission' "${reviewer_err}" 2>/dev/null || grep -Fq 'required the "command" permission' "${review_report}" 2>/dev/null; then
        log_warn "Reviewer triggered headless 'command' permission auto-denial on ${task_id}. Retrying immediately with strict READ_FILE-ONLY enforcement..."
        local strict_rev_prompt="CẢNH BÁO KHẨN CẤP: Lượt chạy vừa rồi bị HỦY vì bạn gọi \`run_command\` trong chế độ headless \`--mode plan\`!
TUYỆT ĐỐI CẤM GỌI \`run_command\`, \`ask_question\`, hay \`invoke_subagent\`! CHỈ ĐƯỢC GỌI \`read_file\`!
Bản \`git diff\` đầy đủ đã có sẵn tại \`${PROJECT_ROOT}/qlpk-autonomous-framework/.agy-state/${task_id}-diff.patch\` — hãy đọc bằng \`read_file\`!

${prompt}"
        exit_code=0
        agy --model "${model}" --mode plan -p "${strict_rev_prompt}" > "${review_report}" 2>> "${reviewer_err}" || exit_code=$?
    fi

    # Fallback logic on Rate Limit (429)
    if [ "${exit_code}" -eq 429 ] || grep -Eiq "(429|RESOURCE_EXHAUSTED|quota|rate limit)" "${reviewer_err}" 2>/dev/null; then
        log_warn "Reviewer quota/rate limit encountered (${exit_code}). Activating DEBUG_FALLBACK_MODEL: ${DEBUG_FALLBACK_MODEL} after ${QUOTA_BACKOFF_SECONDS}s backoff..."
        sleep "${QUOTA_BACKOFF_SECONDS}"
        exit_code=0
        agy --model "${DEBUG_FALLBACK_MODEL}" --mode plan -p "${prompt}" > "${review_report}" 2>> "${reviewer_err}" || exit_code=$?
    fi
    return "${exit_code}"
}

# --- TASK DEFINITIONS & PROGRESS UPDATER ---
get_task_test_cmd() {
    local task_id="$1"
    case "${task_id}" in
        TASK-01) echo "test -s PHASE_1_AUDIT.md" ;;
        TASK-02) echo "cd backend && (composer dump-autoload -q || php /usr/local/bin/composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=MigrationSchemaTest" ;;
        TASK-03) echo "cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter='AuthTest|TenantIsolationTest'" ;;
        TASK-04) echo "cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=CreditTest" ;;
        TASK-05) echo "cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=PaymentOrderTest" ;;
        TASK-06) echo "cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=PipelineTest" ;;
        TASK-07) echo "cd frontend && npm install --no-audit --no-fund && npm run build" ;;
        TASK-08) echo "cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test && cd ../frontend && npm run build" ;;
        *)       echo "false" ;;
    esac
}

is_task_done() {
    local task_id="$1"
    grep -Eq "\|[[:space:]]*\*\*${task_id}\*\*[[:space:]]*\|.*\`DONE\`" "${FRAMEWORK_DIR}/PROGRESS.md"
}

update_progress_status() {
    local task_id="$1"
    local new_status="$2"
    local test_exit="$3"
    local verdict="$4"
    local attempt="$5"
    local ts
    ts="$(date '+%Y-%m-%d %H:%M')"
    local progress_file="${FRAMEWORK_DIR}/PROGRESS.md"

    php -r '
        $file = $argv[1];
        $taskId = $argv[2];
        $status = $argv[3];
        $testExit = $argv[4];
        $verdict = $argv[5];
        $attempt = $argv[6];
        $ts = $argv[7];
        $content = file_get_contents($file);
        if ($content === false) exit(0);
        $lines = explode("\n", $content);
        foreach ($lines as &$line) {
            if (strpos($line, "**{$taskId}**") !== false) {
                $parts = explode("|", $line);
                if (count($parts) >= 10) {
                    $parts[5] = " `{$status}` ";
                    $parts[6] = " {$testExit} ";
                    $parts[7] = " {$verdict} ";
                    $parts[8] = " {$attempt}/3 ";
                    $parts[9] = " {$ts} ";
                    $line = implode("|", $parts);
                }
            }
        }
        unset($line);
        $updated = implode("\n", $lines);
        $done = preg_match_all("/\| `DONE` \|/", $updated);
        $inProg = preg_match_all("/\| `IN_PROGRESS` \|/", $updated);
        $testing = preg_match_all("/\| `(TESTING|REVIEWING)` \|/", $updated);
        $pending = preg_match_all("/\| `PENDING` \|/", $updated);
        $blocked = preg_match_all("/\| `(BLOCKED|FAILED)` \|/", $updated);
        $total = 8;
        $pct = fn($n) => round(($n / $total) * 100, 1) . "%";
        $updated = preg_replace("/(\*\*Đã hoàn thành \(DONE\)\*\* \| )\*\*\d+\*\* \| [0-9.]+%/", "$1**{$done}** | " . $pct($done), $updated);
        $updated = preg_replace("/(\*\*Đang xử lý \(IN_PROGRESS\)\*\* \| )\*\*\d+\*\* \| [0-9.]+%/", "$1**{$inProg}** | " . $pct($inProg), $updated);
        $updated = preg_replace("/(\*\*Đang kiểm tra \(TESTING \/ REVIEWING\)\*\* \| )\*\*\d+\*\* \| [0-9.]+%/", "$1**{$testing}** | " . $pct($testing), $updated);
        $updated = preg_replace("/(\*\*Đang chờ xử lý \(PENDING\)\*\* \| )\*\*\d+\*\* \| [0-9.]+%/", "$1**{$pending}** | " . $pct($pending), $updated);
        $updated = preg_replace("/(\*\*Tạm dừng \/ Bị chặn \(BLOCKED \/ FAILED\)\*\* \| )\*\*\d+\*\* \| [0-9.]+%/", "$1**{$blocked}** | " . $pct($blocked), $updated);
        file_put_contents($file, $updated);
    ' "${progress_file}" "${task_id}" "${new_status}" "${test_exit}" "${verdict}" "${attempt}" "${ts}" || true
}

execute_single_task() {
    local task_id="$1"
    local session_run_id="$2"
    local test_cmd
    test_cmd="$(get_task_test_cmd "${task_id}")"
    local test_report="${REPORTS_DIR}/${task_id}-test.md"
    local review_report="${REPORTS_DIR}/${task_id}-review.md"

    log_info "=================================================================="
    log_info "STARTING ${task_id} (Test Command: ${test_cmd})"
    log_info "=================================================================="

    local review_round=1
    local feedback_context=""

    while [ "${review_round}" -le 3 ]; do
        check_system_resources || return 1

        local run_id="${session_run_id}_${task_id}_R${review_round}"
        update_progress_status "${task_id}" "IN_PROGRESS" "-" "-" "${review_round}"

        local coder_prompt="Bạn là CODER Agent (${CODER_MODEL}) vận hành trong QLPK Safe Autonomous Framework.
Thư mục gốc dự án (PROJECT_ROOT): \`${PROJECT_ROOT}\`
Nhiệm vụ hiện tại: Triển khai trọn vẹn **${task_id}** theo đặc tả trong \`${PROJECT_ROOT}/qlpk-autonomous-framework/ROADMAP.md\` và báo cáo kiểm toán \`${PROJECT_ROOT}/PHASE_1_AUDIT.md\`.

QUY TẮC SỐ 0 — CHẾ ĐỘ HEADLESS (\`-p --mode accept-edits\`) [QUAN TRỌNG SỐNG CÒN]:
- **TUYỆT ĐỐI CẤM GỌI \`run_command\` (bash/shell/terminal), \`ask_question\`, hoặc \`invoke_subagent\`!** Trong chế độ headless \`-p\`, bất kỳ lệnh gọi \`run_command\` nào cũng bị từ chối quyền \`command\` (\`auto-denied\`) và hủy toàn bộ lượt chạy ngay lập tức!
- **CHỈ SỬ DỤNG ĐÚNG 3 CÔNG CỤ FILE TRỰC TIẾP** (luôn truyền đường dẫn tuyệt đối bắt đầu bằng \`${PROJECT_ROOT}/...\`):
  1. \`read_file\` — đọc file mã nguồn/tài liệu
  2. \`write_file\` — tạo file mới (hoặc \`Overwrite: true\` khi cần ghi đè file trong \`allowed_files\`)
  3. \`edit_file\` — chỉnh sửa chính xác file hiện có trong \`allowed_files\`
- Để tra cứu cây thư mục & tên các file hiện có trong dự án (ví dụ danh sách các file migration hiện tại trong \`backend/database/migrations/\`) mà KHÔNG cần gọi \`ls\` hay \`find\`, hãy dùng \`read_file\` đọc file danh mục vừa được hệ thống xuất sẵn tại:
  \`${PROJECT_ROOT}/qlpk-autonomous-framework/.agy-state/workspace-files.txt\`

QUY TẮC NGHIỆP VỤ BẮT BUỘC:
1. Dùng \`read_file\` đọc kỹ phần đặc tả của **${task_id}** trong \`${PROJECT_ROOT}/qlpk-autonomous-framework/ROADMAP.md\`, các cẩm nang trong \`${PROJECT_ROOT}/qlpk-autonomous-framework/skills/\`, checklist 14 tiêu chí trong \`${PROJECT_ROOT}/qlpk-autonomous-framework/REVIEWER.md\` và chi tiết lỗi/thiết kế trong \`${PROJECT_ROOT}/PHASE_1_AUDIT.md\`.
2. **TUÂN THỦ TUYỆT ĐỐI \`allowed_files\`** của ${task_id} trong \`ROADMAP.md\`. Tuyệt đối KHÔNG sửa hoặc tạo bất kỳ file nào nằm ngoài \`allowed_files\` (cũng KHÔNG tự sửa \`PROGRESS.md\` hay \`reports/\`).
3. Mọi migration phải tương thích cả MySQL/PostgreSQL lẫn SQLite \`:memory:\` của PHPUnit (\`backend/phpunit.xml\`). Không xóa bảng hay phá vỡ logic đang chạy tốt.
4. Giữ code chuẩn Laravel Pint (cho PHP) và TypeScript strict không có \`any\` (cho Next.js). Không để lại \`dd()\`, \`dump()\`, \`console.log()\`.
5. KHÔNG tự chạy Pint/Composer/PHPUnit/NPM — ngay sau khi bạn hoàn tất sửa file, hệ thống Bash bên ngoài sẽ tự động chạy \`./vendor/bin/pint\` và lệnh kiểm thử thật: \`${test_cmd}\`.
${feedback_context}"

        invoke_coder "${coder_prompt}" "${task_id}" || log_warn "Coder exited non-zero on ${task_id} round ${review_round}; proceeding to Automated Tester check."

        # Inner Test-Fix Loop (max 2 fix retries on test failure)
        local test_try=1
        local test_passed=0
        while [ "${test_try}" -le 3 ]; do
            (cd "${PROJECT_ROOT}/backend" && [ -x ./vendor/bin/pint ] && ./vendor/bin/pint -q) 2>/dev/null || true
            update_progress_status "${task_id}" "TESTING" "running" "-" "${review_round}"
            if run_automated_tester "${task_id}" "${run_id}" "${test_cmd}"; then
                test_passed=1
                break
            fi

            if [ "${test_try}" -ge 3 ]; then
                break
            fi

            log_warn "${task_id} failed Automated Tester (attempt ${test_try}/3). Invoking CODER to fix real test errors..."
            local test_tail
            test_tail="$(cat "${test_report}" 2>/dev/null || echo 'Test failed')"
            local fix_prompt="Bạn là CODER Agent (Thư mục gốc: \`${PROJECT_ROOT}\`). Bản sửa vừa rồi cho **${task_id}** bị AUTOMATED TESTER đánh trượt khi chạy \`${test_cmd}\`.

QUY TẮC HEADLESS BẮT BUỘC:
- **TUYỆT ĐỐI CẤM GỌI \`run_command\`, \`ask_question\`, hoặc \`invoke_subagent\`!** Chỉ sử dụng 3 công cụ \`read_file\`, \`write_file\`, \`edit_file\` với đường dẫn tuyệt đối \`${PROJECT_ROOT}/...\`. (Có thể đọc danh sách file hiện có tại \`${PROJECT_ROOT}/qlpk-autonomous-framework/.agy-state/workspace-files.txt\`).
- KHÔNG tự chạy Pint hay PHPUnit/NPM bằng lệnh shell — hệ thống Automated Tester sẽ tự chạy ngay sau khi bạn sửa file xong!

Hãy đọc kỹ log lỗi thực tế dưới đây và sửa triệt để nguyên nhân lỗi CHỈ TRONG PHẠM VI \`allowed_files\` của ${task_id} trong \`${PROJECT_ROOT}/qlpk-autonomous-framework/ROADMAP.md\`:

${test_tail}"
            invoke_coder "${fix_prompt}" "${task_id}" || true
            test_try=$((test_try + 1))
        done

        if [ "${test_passed}" -ne 1 ]; then
            feedback_context="LƯU Ý TỪ VÒNG TRƯỚC: Lệnh test \`${test_cmd}\` bị FAIL (xem \`qlpk-autonomous-framework/reports/tasks/${task_id}-test.md\`). Hãy khắc phục triệt để!"
            review_round=$((review_round + 1))
            sleep "${ERROR_COOLDOWN_SECONDS}"
            continue
        fi

        # Automated test passed -> Invoke independent REVIEWER
        update_progress_status "${task_id}" "REVIEWING" "0" "PENDING" "${review_round}"
        local changed_files
        changed_files="$(cd "${PROJECT_ROOT}" && git status --porcelain | grep -vE "${ALLOW_UNTRACKED_REGEX}" || true)"

        local reviewer_prompt="Bạn là REVIEWER Agent độc lập (${REVIEWER_MODEL}, chế độ chỉ đọc \`plan\`).
Thư mục gốc dự án (PROJECT_ROOT): \`${PROJECT_ROOT}\`

QUY TẮC HEADLESS BẮT BUỘC:
- **TUYỆT ĐỐI CẤM GỌI \`run_command\` (bash/shell/git), \`ask_question\`, hoặc \`invoke_subagent\`!** Gọi \`run_command\` trong chế độ headless sẽ bị hủy lượt ngay lập tức!
- **CHỈ SỬ DỤNG DUY NHẤT CÔNG CỤ \`read_file\`** (với đường dẫn tuyệt đối \`${PROJECT_ROOT}/...\`).
- Toàn bộ \`git status\` và \`git diff HEAD\` đã được hệ thống xuất sẵn vào file \`${PROJECT_ROOT}/qlpk-autonomous-framework/.agy-state/${task_id}-diff.patch\` — hãy dùng \`read_file\` đọc trực tiếp file đó!

Hãy thẩm định **${task_id}** với mã phiên **RUN_ID: ${run_id}**:
1. Dùng \`read_file\` đọc danh sách \`allowed_files\` và yêu cầu của **${task_id}** trong \`${PROJECT_ROOT}/qlpk-autonomous-framework/ROADMAP.md\`.
2. Dùng \`read_file\` đọc báo cáo kiểm thử thực tế tại \`${PROJECT_ROOT}/qlpk-autonomous-framework/reports/tasks/${task_id}-test.md\` (kiểm tra khớp \`RUN_ID: ${run_id}\` và \`Exit Code: 0\`).
3. Đối chiếu danh sách file thay đổi hiện tại trong git (chi tiết diff xem tại \`${PROJECT_ROOT}/qlpk-autonomous-framework/.agy-state/${task_id}-diff.patch\`):
\`\`\`
${changed_files}
\`\`\`
4. Dùng \`read_file\` đọc trực tiếp nội dung các file vừa được tạo/sửa của **${task_id}** và kiểm tra nghiêm ngặt toàn bộ **14 tiêu chí Anti-Fake-Done** trong \`${PROJECT_ROOT}/qlpk-autonomous-framework/REVIEWER.md\`.

ĐẦU RA BẮT BUỘC (In trực tiếp báo cáo Markdown ra stdout theo đúng định dạng dưới đây để hệ thống lưu vào \`${task_id}-review.md\`):
# Task Review Report: ${task_id}

- **RUN_ID**: ${run_id}
- **Task ID**: ${task_id}
- **Reviewer Model**: ${REVIEWER_MODEL}
- **OVERALL_REVIEW_RESULT**: PASS (hoặc FAIL)

## 14-Point Checklist Verification
(Liệt kê đánh giá trung thực từ tiêu chí 01 đến 14)

## Review Verdict JSON
\`\`\`json
{
  \"task_id\": \"${task_id}\",
  \"run_id\": \"${run_id}\",
  \"verdict\": \"PASS\",
  \"checklist_score\": \"14/14\",
  \"notes\": \"...\"
}
\`\`\`
(Nếu có bất kỳ vi phạm P0/P1 nào, bắt buộc ghi \`OVERALL_REVIEW_RESULT: FAIL\` và \`\"verdict\": \"FAIL\"\` kèm danh sách file/dòng cần sửa)."

        invoke_reviewer "${task_id}" "${reviewer_prompt}" || log_warn "Reviewer exited non-zero on ${task_id}"

        if verify_done_gate "${task_id}" "${run_id}"; then
            update_progress_status "${task_id}" "DONE" "0" "PASS" "${review_round}"
            cd "${PROJECT_ROOT}" || return 1
            git add -A
            git commit -m "feat(${task_id}): complete ${task_id} verified by QLPK Anti-Fake-Done gate [${run_id}]" || log_warn "Nothing new to commit for ${task_id}"
            log_info "Pushing completed ${task_id} up to remote branch origin/${AUTO_PUSH_BRANCH}..."
            if git push -u origin "HEAD:${AUTO_PUSH_BRANCH}"; then
                log_success "PUSHED ${task_id} TO origin/${AUTO_PUSH_BRANCH} SUCCESSFULLY!"
            else
                log_warn "git push to origin/${AUTO_PUSH_BRANCH} failed (check network/auth); commit is safely saved locally."
            fi
            log_success "COMPLETED & COMMITTED ${task_id} ON ROUND ${review_round}!"
            return 0
        fi

        log_warn "Anti-Fake-Done Gate REJECTED ${task_id} on round ${review_round}/3. Feeding Reviewer report back to Coder..."
        feedback_context="PHẢN HỒI BẮT BUỘC SỬA TỪ REVIEWER (xem \`qlpk-autonomous-framework/reports/tasks/${task_id}-review.md\`):
$(cat "${review_report}" 2>/dev/null || echo 'Gate verification failed')"
        review_round=$((review_round + 1))
        sleep "${ERROR_COOLDOWN_SECONDS}"
    done

    update_progress_status "${task_id}" "FAILED" "1" "FAIL" "3"
    log_error "${task_id} FAILED after 3 full review rounds. Halting pipeline safely for human inspection."
    return 1
}

run_autonomous_loop() {
    local target_task="${1:-ALL}"
    local session_start
    session_start="$(date +%s)"
    local session_run_id="RUN_$(date '+%Y%m%d_%H%M%S')"
    local tasks_completed=0
    local task_list="TASK-01 TASK-02 TASK-03 TASK-04 TASK-05 TASK-06 TASK-07 TASK-08"

    if [ "${target_task}" != "ALL" ]; then
        task_list="${target_task}"
    fi

    if ! assert_clean_git_tree; then
        log_error "Aborting autonomous loop: Please commit or stash uncommitted changes first so each task starts from a clean git baseline."
        return 1
    fi

    for task_id in ${task_list}; do
        if [ -f "${STOP_FILE}" ]; then
            log_warn "STOP signal detected before ${task_id}. Exiting gracefully."
            return 0
        fi

        if is_task_done "${task_id}" && [ "${target_task}" = "ALL" ]; then
            log_info "Skipping ${task_id} (already marked DONE in PROGRESS.md)."
            continue
        fi

        local now_ts elapsed
        now_ts="$(date +%s)"
        elapsed=$((now_ts - session_start))
        if [ "${elapsed}" -ge "${SESSION_TIMEOUT_SECONDS}" ]; then
            log_warn "Session timeout (${SESSION_TIMEOUT_SECONDS}s) reached. Stopping gracefully."
            return 0
        fi
        if [ "${tasks_completed}" -ge "${MAX_TASKS_PER_SESSION}" ]; then
            log_warn "Max tasks per session (${MAX_TASKS_PER_SESSION}) reached. Stopping gracefully."
            return 0
        fi

        if ! execute_single_task "${task_id}" "${session_run_id}"; then
            return 1
        fi

        tasks_completed=$((tasks_completed + 1))
        log_info "Cooldown ${TASK_COOLDOWN_SECONDS}s before next task..."
        sleep "${TASK_COOLDOWN_SECONDS}"
    done

    log_success "ALL TARGET TASKS IN PHASE 1 COMPLETED & VERIFIED SUCCESSFULLY!"
    return 0
}

# --- DRY RUN CHECK ---
do_dry_run() {
    echo "=================================================================="
    echo "  QLPK AUTONOMOUS FRAMEWORK - ENVIRONMENT DRY-RUN AUDIT"
    echo "=================================================================="
    log_info "1. Checking Model Routing Configuration..."
    echo "   - PLANNER Model        : ${PLANNER_MODEL} (Mode: plan)"
    echo "   - CODER Model          : ${CODER_MODEL} (Mode: accept-edits)"
    echo "   - REVIEWER Model       : ${REVIEWER_MODEL} (Mode: plan)"
    echo "   - DEBUG FALLBACK Model : ${DEBUG_FALLBACK_MODEL}"

    log_info "2. Checking Git..."
    git --version || log_error "git missing!"

    log_info "3. Checking PHP & Composer..."
    php -v | head -n 1 || log_error "php missing!"
    composer --version 2>/dev/null || log_warn "composer not in global PATH"

    log_info "4. Checking Node & NPM..."
    node -v || log_error "node missing!"
    npm -v || log_error "npm missing!"

    log_info "5. Checking Framework Structure..."
    [ -f "${FRAMEWORK_DIR}/AGENTS.md" ] && log_success "AGENTS.md present" || log_error "AGENTS.md missing"
    [ -f "${FRAMEWORK_DIR}/PLANNER.md" ] && log_success "PLANNER.md present" || log_error "PLANNER.md missing"
    [ -f "${FRAMEWORK_DIR}/REVIEWER.md" ] && log_success "REVIEWER.md present" || log_error "REVIEWER.md missing"
    [ -f "${FRAMEWORK_DIR}/ROADMAP.md" ] && log_success "ROADMAP.md present" || log_error "ROADMAP.md missing"
    [ -f "${FRAMEWORK_DIR}/PROGRESS.md" ] && log_success "PROGRESS.md present" || log_error "PROGRESS.md missing"

    log_info "6. Checking System Resources..."
    check_system_resources || log_warn "Resources stress detected during dry-run"

    log_info "7. Checking Git Clean Tree..."
    assert_clean_git_tree || log_warn "Git tree dirty (Dry-run only)"

    echo "=================================================================="
    log_success "Dry run complete! System ready for autonomous execution."
    echo "=================================================================="
}

# --- MAIN DISPATCHER ---
case "${1:-run}" in
    dry-run)
        do_dry_run
        exit 0
        ;;
    stop)
        log_warn "Creating STOP file at ${STOP_FILE}..."
        touch "${STOP_FILE}"
        echo "STOP signal sent. The orchestrator will safely exit at the next boundary."
        exit 0
        ;;
    status)
        echo "=== QLPK ORCHESTRATOR STATUS ==="
        if [ -f "${LOCK_FILE}" ]; then
            echo "Status: RUNNING (PID: $(cat "${PID_FILE}" 2>/dev/null || echo "Unknown"))"
        else
            echo "Status: IDLE / STOPPED"
        fi
        [ -f "${STOP_FILE}" ] && echo "Notice: STOP signal is currently active."
        echo ""
        echo "=== ACTIVE MODEL ROUTING ==="
        echo "PLANNER  : ${PLANNER_MODEL}"
        echo "CODER    : ${CODER_MODEL}"
        echo "REVIEWER : ${REVIEWER_MODEL}"
        echo "FALLBACK : ${DEBUG_FALLBACK_MODEL}"
        echo ""
        check_system_resources
        exit 0
        ;;
    run)
        # Remove any previous STOP signal when starting a fresh run
        rm -f "${STOP_FILE}"

        # Check single instance lock
        if [ -f "${LOCK_FILE}" ]; then
            log_error "Orchestrator already running or stale lock at ${LOCK_FILE}. Remove lock if previous session died."
            exit 1
        fi
        echo $$ > "${PID_FILE}"
        touch "${LOCK_FILE}"
        trap cleanup_on_exit EXIT INT TERM

        init_caffeinate
        log_info "Starting QLPK Autonomous Orchestrator session..."
        do_dry_run

        log_info "Autonomous execution loop started. Monitoring tasks from ROADMAP.md & PROGRESS.md..."
        run_autonomous_loop "${2:-ALL}"
        ;;
    *)
        echo "Usage: $0 {run [TASK-XX]|dry-run|stop|status}"
        exit 1
        ;;
esac
