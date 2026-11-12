# Security and Manual Test Plan

## Test setup

Run these tests against a disposable database created from `sql/schema.sql` and `sql/seed.sql`; do not use a database containing real records. Create one staff test account and register two student test accounts. The seeded found USB (item 4) is used for claim tests. Reset the disposable database between runs if needed.

For an existing database, apply `sql/verification-code-recovery-migration.sql` after the student verification migrations. Set `verification_code_encryption_key` in `includes/config.php` to a stable 64-character hexadecimal key generated with PHP's `bin2hex(random_bytes(32))`; keep it secret and backed up, since changing it makes active codes unreadable.

The results below were recorded on 2026-10-19 using the isolated `lost_found_phase7_test` database before student verification was added. Application requests were exercised over HTTP, database state was checked directly, the responsive/keyboard checks used a browser, and the upload execution check used the local Apache server. Run the verification checks below on a fresh or migrated database; the legacy PASS results do not validate the new verification flow.

## Test cases

| ID | Area | Steps | Expected result | Result |
|---|---|---|---|---|
| AUTH-01 | Registration | Register a student using a unique email, matching password confirmation, and checked consent. Inspect the stored credential. | Account is created, role is student, and the database stores a password hash rather than the submitted password. | PASS |
| AUTH-02 | Login | Submit a wrong password, then the correct password for the registered account. | Wrong credentials show the same generic message; correct credentials sign in. | PASS |
| AUTH-03 | Session | Record the session cookie before and after successful login. Inspect its attributes on the response. | Session ID changes at login; cookie is HttpOnly and SameSite=Lax. | PASS |
| AUTH-04 | CSRF | Submit a protected profile POST without a token; use valid CSRF tokens on the other form submissions. | The tokenless request is rejected with HTTP 403; valid CSRF-protected form submissions are accepted. | PASS |
| ACCESS-01 | Authentication guard | Open student-only pages in a new signed-out session. | Requests redirect to sign-in; no protected content is returned. | PASS |
| ACCESS-02 | Student role | As a student, request found-item upload, manage, edit, delete, pending-claim, and claim-review pages directly. | Each staff-only route returns HTTP 403. | PASS |
| ACCESS-03 | Staff role | As staff, request lost reporting, student report/claim lists, and lost-report edit/delete pages directly. | Each student-only route returns HTTP 403. | PASS |
| LOST-01 | Create/list | As a student, submit a valid lost report and open My Reports. | Report is saved as type `lost` and appears only in the submitter's list. | PASS |
| LOST-02 | Ownership | As a second student, open the first student's edit and delete URLs by ID. | Both requests return HTTP 404 and leave the report unchanged. | PASS |
| LOST-03 | Update/delete | As the owner, edit the report, open its delete confirmation, and confirm deletion. | Updated values are saved; the report is removed only after confirmation. | PASS |
| LOST-04 | Staff moderation | As staff, reject an approved lost report, check public browsing and the owner's report list, then restore it. | Rejection hides but retains the report and shows its not-approved state to the owner; restoring makes it public again. | PASS |
| LOST-05 | Staff finder update | As staff, use “I found this item” on another student's lost listing and open the owner's Chat with Staff. | A private staff message appears for the owner; the report remains open until the item is returned. | PASS |
| FOUND-01 | Consent/create | As staff, submit a found item without consent, then submit a valid item with consent. | Missing consent prevents saving; valid consent creates a found item visible in staff management. | PASS |
| FOUND-02 | Update/status | Edit the staff item's details and change its status from Manage Found Items. | Details and status are saved and shown in the list. | PASS |
| FOUND-03 | Delete | Create a claim for the staff item's found record, open its delete confirmation, and confirm. | The found item and its associated claims are removed according to the database rules. | PASS |
| SEARCH-01 | Combined filters | Add at least 12 matching lost-item fixtures, then combine category, status, date range, and keyword filters. | Only matching items appear; page 1 has 10 results and page 2 has the remaining 2. | PASS |
| SEARCH-02 | Invalid/injection filters | Try a nonnumeric or SQL-like category/status value and an invalid page value. | Invalid filters are rejected or safely cleared; no SQL error/injection occurs; invalid page resolves to page 1. | PASS |
| CLAIM-01 | Submit/self-claim | As a student, submit a claim for another user's open found item. Also attempt a claim for an item whose reporter is the same student. | Valid claim is pending; the self-claim is rejected. | PASS |
| CLAIM-02 | Duplicate claim | Submit a second claim while the first claim for that item is still pending. | Duplicate pending claim is rejected and no second pending row is created. | PASS |
| CLAIM-03 | Review/atomic approval | As staff, approve one of two pending claims for the same open item. Inspect both claim rows and the item. | Selected claim becomes approved, competing pending claim becomes rejected with a reviewer, and item becomes returned. | PASS |
| CLAIM-04 | Double approval/role | Try to approve the already-reviewed claim again; try to open claim review as a student. | Reapproval is rejected with no state change; student receives HTTP 403. | PASS |
| UPLOAD-01 | Extension/content | Upload an executable `.php` file as an image. | Upload is rejected with a clear supported-image message and no report is created. | PASS |
| UPLOAD-02 | Size | Upload a file larger than 15 MB. | Upload is rejected with a clear size message and no report is created. | NOT RUN |
| UPLOAD-03 | Server execution | Under Apache, request a temporary PHP probe file placed in `/uploads`. | Apache returns HTTP 403; PHP content is not executed. | PASS |
| UPLOAD-04 | Valid image | Upload a valid PNG with alt text, inspect the saved filename, then delete the report. | Image is accepted under a random filename, alt text is retained, and deleting the report removes the image. | PASS |
| OUTPUT-01 | Escaping | Submit an item title containing HTML/script markup and view the resulting listing. | Markup is rendered as text and does not execute. | PASS |
| UI-01 | Responsive | View the landing page at a 360-pixel viewport. | Content remains usable with no horizontal page overflow. | PASS |
| UI-02 | Keyboard | Starting at the landing page, press Tab to focus the skip link, then press Enter. | The skip link becomes visibly focused and navigates to the main landmark. | PASS |

## Findings and fixes

- Static application SELECT statements used `PDO::query()`. Although they had no user-controlled SQL fragments, they did not consistently meet the prepared-statement requirement. They now use prepared statements; filter values remain bound parameters.
- PHP sessions did not explicitly enable strict session IDs or cookie-only IDs. Session startup now enables strict mode and cookie-only transport; HttpOnly, SameSite=Lax, HTTPS-sensitive Secure, and session ID regeneration at login are retained.
- PHP error display could be enabled by the local PHP configuration. Application initialization now disables display to users and enables server-side error logging. Database and caught query errors return generic messages; SQL details are logged only.
- The uploads rule denied common executable file extensions, but did not force uploaded files through Apache's non-executable default handler. `/uploads/.htaccess` now disables directory listing and CGI, applies the default handler, and denies common server-script extensions. An Apache request to a temporary PHP probe returned 403.

## Remaining risks

- Failed-login throttling is session-based and can be bypassed by starting a fresh session. It is a basic deterrent, not a production-grade account-level rate limiter; consider server-side or edge rate limiting before public deployment.
- The school must complete the contact and retention details in the privacy policy and review applicable school requirements before collecting real student information.
- Apache must permit the applicable `.htaccess` overrides in production. Confirm the upload execution test returns 403 on the deployed Apache host.
- HTTPS, database credential management, backups, monitoring, and data deletion are deployment responsibilities and were not independently audited here.

No unresolved high-risk application issue was identified in this review. The remaining items are deployment or policy risks that should be addressed before a public launch.

## Student verification checks

Run these checks on a disposable database with the verification schema. Create student and staff test accounts and use a valid test ID image. Do not use real student documents in development.

| ID | Steps | Expected result | Result |
|---|---|---|---|
| VERIFY-01 | As a student with no verification record, open lost reporting and submit a claim POST directly. | Both actions are blocked and direct the student to Profile. | NOT RUN |
| VERIFY-02 | Submit an invalid file or a file larger than 15 MB as the school ID photo. | Submission is rejected and no verification record or public file is created. | NOT RUN |
| VERIFY-03 | In Student Verification, enter a school email and request a code. As staff, issue and manually deliver the code, sign out and back in, then retrieve the current code from User Management. Enter it with the school ID number and photo, then submit together. | Staff can retrieve the same active code without issuing a replacement; a valid submission confirms the email but remains unverified until staff approves the ID. | NOT RUN |
| VERIFY-04 | As a student, request the private photo URL; then request it as staff. | Student receives HTTP 403; staff can view the image through the protected document route; direct access to the stored file is denied. | NOT RUN |
| VERIFY-05 | Reject an ID submission as staff, then request a new code from the student profile. | Rejected request can be restarted; prior private image is deleted and staff receives a new code request. | NOT RUN |
| VERIFY-06 | Issue a code, enter a wrong code five times, then request a replacement and submit the correct code with ID details. | Wrong codes are rejected; replacement invalidates the old code; correct code and ID are submitted for staff review, not immediately verified. | NOT RUN |
| VERIFY-07 | Attempt to change the sign-up email by crafting a profile POST. | The stored sign-up email remains unchanged. | NOT RUN |
| VERIFY-08 | After verification, create a lost report and claim a found item. | Both actions succeed for the verified student. | NOT RUN |
| VERIFY-09 | While a code is active but before submitting ID, request another code. As staff, issue the replacement; submit it with ID details. | The old code is invalidated; the replacement code and ID submission are accepted for staff review. | NOT RUN |
| VERIFY-10 | Open Student Verification before requesting a code, while waiting, and after staff issues it. In the code-sent state, try typing ID details before entering a code, then enter fewer than eight digits and finally all eight. | School ID number/photo fields are visible but disabled before code entry; they remain disabled until all eight code digits are entered, then unlock for the combined submission. | NOT RUN |
| USERS-01 | As staff, open User Management, search by name/email, and filter by role, verification status, and account status. | The compact paginated table shows matching accounts and their correct statuses. | NOT RUN |
| USERS-02 | Issue a temporary password to a test student, log in with it, and visit another page before changing it. | Temporary password is displayed once; student is restricted to Profile until changing it; the new password works. | NOT RUN |
| USERS-03 | Suspend a test student, attempt a new login and use any existing session, then reactivate. | Suspended student cannot log in or continue using protected pages; reactivation restores access. | NOT RUN |
| USERS-04 | Revoke a verified test student's verification and open posting/claim routes. | Verification status is revoked; posting and claiming are blocked until the student verifies again. | NOT RUN |
| USERS-05 | Delete a disposable student with reports, claims, and a verification photo. | Student account, related items/claims/verification record, and uploaded images are removed; staff accounts cannot be deleted here. | NOT RUN |
| CHAT-01 | As a student, start a support chat with a subject and message; open it from chat history and send a reply. | A private open session is created, messages display in order, and the student can access only their own sessions. | NOT RUN |
| CHAT-02 | As staff, open Support Inbox, reply to the student, then close that session. | Staff can view and reply to the session; closing affects only that session and records the staff member and close time. | NOT RUN |
| CHAT-03 | As the student, open a closed session and try to submit a reply; as staff, filter to Closed and inspect the session. | Both roles can read history; closed sessions do not accept messages and remain listed as closed. | NOT RUN |
| CHAT-04 | As a student, attempt to open another student's session ID or post a reply to it; as a student, open Support Inbox directly. | Other students' chats are not disclosed or modified, and student access to the staff inbox returns HTTP 403. | NOT RUN |
