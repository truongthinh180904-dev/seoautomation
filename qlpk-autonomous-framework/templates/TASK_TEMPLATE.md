# Task: <TASK_ID> — <Short title>

- `feature_id`: `<FEATURE_ID>`
- `task_id`: `<TASK_ID>`
- `role`: `PLANNER | CODER | AUTOMATED_TESTER | REVIEWER`
- `model`: `<verified model name or NOT_VERIFIED>`
- `status`: `DRAFT`
- `complexity`: `LOW | MEDIUM | HIGH`
- `dependencies`: `[]`
- `owner/approval`: `<name or pending>`

## Objective
<Một kết quả cụ thể, không mô tả giải pháp quá sớm.>

## Context / assumptions
- Confirmed:
- Unknowns:
- Assumptions requiring approval:

## Scope
### Allowed files
- `path/to/file`

### Out of scope
- Không sửa các file/miền không liên quan.
- Không chạy migration hoặc thao tác dữ liệu nếu chưa được duyệt.

## Acceptance criteria
1. [ ] Given/When/Then hoặc điều kiện đo lường cụ thể.
2. [ ] Trường hợp lỗi/biên được xác minh.
3. [ ] Không có thay đổi ngoài scope.

## Test commands
| Command | Purpose | Required? | Evidence |
|---|---|---|---|
| `<verified command>` | `<purpose>` | YES/NO | `NOT_RUN` |

Không ghi command chưa xác minh là command chạy được. Nếu không chạy được, ghi `NOT_RUN`/`BLOCKED` và lý do.

## Data, security and migration impact
- PII/clinical data involved: `NO / YES — explain`
- Auth/authorization impact:
- Database/migration impact:
- Secret handling:
- Rollback:

## Risks / dependencies
- 

## Completion evidence
- Changed files:
- Commands + exit codes:
- Test result:
- Report: `reports/tasks/<TASK_ID>.md`
- Remaining issues:
