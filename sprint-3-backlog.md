# Sprint 3 Backlog (Updated)

Sprint length: 2 weeks
Goal: Hoàn thiện nền tảng phân tích, báo cáo, chat realtime và chuẩn hoá kiểm duyệt nội dung & báo cáo chi tiêu.

---

## Coding (đã chỉnh)

1. Integrate AI Content & Image API
   - Mục tiêu: Tích hợp dịch vụ moderation (ví dụ OpenAI moderation / Vision hoặc dịch vụ tương đương) để kiểm tra text và hình ảnh upload.
   - Deliverables: endpoint `api/moderation.php`, service wrapper, env config `config/moderation.php`.
   - Acceptance: uploads/images/text có thể gửi tới moderation; response logged; flagged items marked `needs_review`.

2. Implement AI Moderation Logic
   - Mục tiêu: business logic xử lý kết quả moderation: thresholds, auto-reject vs flag-for-human, audit log.
   - Deliverables: moderation rules, DB table `moderation_logs`, admin review UI.
   - Acceptance: false-positive handling documented; moderator có thể review/override.

3. Build Expense Report Upload API
   - Mục tiêu: endpoint để upload file báo cáo chi tiêu (XLSX/CSV), parse, validate và lưu vào DB `expenses`.
   - Deliverables: `api/expenses-upload.php`, DB migration for `expenses` table, validation.
   - Acceptance: file upload → parsed → preview → commit to DB; invalid rows reported.

4. Build Financial Dashboard UI (extend)
   - Mục tiêu: mở rộng `admin/dashboard.php` + charts để hiển thị báo cáo chi tiêu/thu chi/flows.
   - Deliverables: UI charts, filters by date/campaign, export button (reuse `admin/reports_export.php`).
   - Acceptance: filters hoạt động, export CSV/XLSX, numbers match DB queries.

5. Develop Analytics & Data APIs
   - Mục tiêu: endpoints REST cho các số liệu (donation trend, campaign stats, top donors) để UI tiêu thụ.
   - Deliverables: `api/analytics/donations.php`, `api/analytics/campaigns.php`, paged responses.
   - Acceptance: endpoints trả JSON hợp lệ, có caching/limit cho large datasets.

6. Develop Admin Chart UI
   - Mục tiêu: reusable chart components (Chart.js) cho admin dashboards.
   - Deliverables: chart components, client code to call analytics APIs.
   - Acceptance: charts render, responsive, handle empty states.

7. Implement WebSocket for Chat (optional) / Improve Polling
   - Mục tiêu: nâng cấp realtime chat từ polling sang WebSocket hoặc cải thiện polling/SSE.
   - Deliverables: architecture doc (Ratchet / socket.io / Pusher), optional server integration, auth cho sockets.
   - Acceptance: message delivery latency giảm, reconnect handling.

8. Implement Auto-sync & Chat UI
   - Mục tiêu: chat client auto-sync (reconnect, dedupe) + integrate donations realtime (SSE already available at `api/donations-stream.php`).
   - Deliverables: improved client code in `includes/chat-widget.php`, advisor UI `chat-advisor.php` improvements.
   - Acceptance: messages appear in <2s under normal conditions; SSE donations appear in admin list.

9. Content Review Queue & Moderator UI
   - Mục tiêu: add an admin moderation queue for items flagged by AI or users, with filters, bulk actions and audit log.
   - Deliverables: `admin/moderation-queue.php`, APIs `api/moderation/list.php`, `api/moderation/action.php`, DB `moderation_logs` schema.
   - Acceptance: moderators can view flagged items, approve/reject/mark-for-review in bulk, and actions are logged.

10. Expense Approval Workflow
   - Mục tiêu: add approval steps for uploaded expense reports (submit → review → approve/reject) with notifications.
   - Deliverables: `api/expenses-review.php`, `admin/expenses-review.php`, DB flags (`status`, `approved_by`, `approved_at`).
   - Acceptance: finance user can review and approve; status changes reflected in financial dashboard and exports.

11. Scheduled Reports & Exports
   - Mục tiêu: allow scheduling recurring exports (daily/weekly/monthly) and deliver via email or SFTP.
   - Deliverables: background job script (`tools/scheduled_reports.php`), UI in admin dashboard to configure schedules, email templates.
   - Acceptance: scheduled job runs, generates reports, and sends to configured recipients; failures logged.

12. Chat UX: Typing Indicator & Read Receipts
   - Mục tiêu: improve chat UX with typing indicators and read receipts (polling or via socket when available).
   - Deliverables: client updates in `includes/chat-widget.php` and `chat-advisor.php`, backend endpoints to persist/read receipt state.
   - Acceptance: users see typing indicators and read status; minimal extra load on polling.

13. Auto-sync Worker for System Consistency
   - Mục tiêu: implement a background worker to auto-sync campaign/inventory states (reconcile race conditions, update counts).
   - Deliverables: CLI worker `workers/auto_sync.php`, cron entry docs, idempotent sync operations.
   - Acceptance: worker detects inconsistencies and fixes them without duplicating operations; safe to run periodically.


## Testing (mới thêm)

1. Test AI & Analytics APIs
   - Test cases: moderation accept/reject paths, flagged items, rate limits, analytics endpoints (trend, top donors), response shapes.
   - Preconditions: sandbox moderation credentials, seeded test data.

2. Test Expense & Upload APIs
   - Test cases: valid XLSX/CSV parsing, invalid rows handling, preview then commit, permissions (admin only), export.
   - Preconditions: DB migration for `expenses`, test files.

3. Test Financial Dashboard & Exports
   - Test cases: filters (date/campaign), charts consistency with raw queries, export XLSX matches UI numbers.
   - Preconditions: sample dataset with expenses/donations.

4. Test Transparency Dashboard UI (if separate)
   - Test cases: data visibility, public vs admin views, export, accessibility.
   - Preconditions: define which metrics are public.

5. Test Chat APIs & Real-time Chat UI
   - Test cases: start chat (guest & logged user), advisor receive/send, message order, reconnect, duplicate suppression, SSE donation stream.
   - Preconditions: test advisor account, test guest flow, run `CHAT_TEST_GUIDE.md` steps.

6. Test Campaign features (create/update/approve/volunteer)
   - Test cases: create campaign (upload media), pending→approve flow, volunteer register/unregister, progress & countdown correctness.
   - Preconditions: admin account, sample campaign.

7. Regression Tests / Fix Verification
   - Test cases: ensure `requireLogin()`/role checks, approve→inventory flow, payment flow (sandbox) work after fixes.
   - Preconditions: test accounts (use `create_test_accounts.php`) and test DB.

8. Test Automation & Helpers
   - Tasks: provide programmatic-login helper (test script), fix `Test/` require paths, create `Test/README.md` with run instructions.
   - Acceptance: testers can run integration scripts locally with minimal manual steps.

---

## Notes / Implementation priorities
- Priority (High): AI moderation infra (for safety), Expense API, Analytics endpoints, Chat real-time improvements. 
- Reuse existing code: reports/export (`admin/reports_export.php`), stats helpers (`includes/functions.php`), chat APIs (`api/chat-*.php`) and SSE donations (`api/donations-stream.php`).
- Missing today: moderation service, expenses DB. Implement these before running related tests.

---

## Next actions (options)
- A) create GitHub Issues for each task (title + acceptance criteria), or
- B) create PR scaffold (moderation stub + `Test/README.md` + programmatic-login helper).

Choose A or B or request edits.
