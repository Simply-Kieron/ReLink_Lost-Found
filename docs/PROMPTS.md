# Classroom Lost & Found: AI Prompt Pack

How to use:
1. Start every new session by pasting the **Master Prompt** first.
2. Then paste one **Phase Prompt** at a time.
3. Do not move on until the phase's acceptance checks pass.
4. Keep `ROADMAP.md` in the project root so the agent can read it.

---

## Master Prompt

```text
You are a senior full-stack developer helping me build a school project: the "Classroom Lost & Found Management System". Read ROADMAP.md in the project root before doing anything.

PROJECT
A centralized system to help return lost items. Students report lost items, staff upload found items, and owners claim items through verification. Items include IDs, wallets, keys, USBs, calculators, jackets, bottles and phones.

STRICT TECH STACK (non-negotiable)
- Frontend: semantic HTML5 and locally authored CSS in `assets/site.css`
- Backend: plain PHP with CRUD operations
- Database: MySQL relational database with at least 2 related tables (plan: users, categories, items, claims)
- Forbidden: PHP frameworks, JavaScript frameworks, Composer packages, jQuery, Bootstrap, and any tool or library outside the course stack
- If something seems to require an outside library, STOP and ask me instead of adding it

ROLES
- Student: registers, reports lost items, browses items, submits claims, manages own reports
- Staff: uploads found items, reviews claims (approve/reject), manages all items

CODING RULES
- Do NOT write any comments in code, in any file
- Use PDO or mysqli with prepared statements for every query
- Escape all output with htmlspecialchars
- Validate all input on the server
- Hash passwords with password_hash and verify with password_verify
- Add a CSRF token to every form
- Use sessions with session_regenerate_id on login
- Check the user's role on every protected page
- Validate file uploads by size, extension and MIME type, and rename stored files
- Keep code simple and readable at a student level, with small files and clear names

FRONTEND RULES
- Semantic HTML: header, nav, main, section, article, footer, form, label
- Mobile-first responsive layout using the project's local stylesheet
- Accessibility: alt text on all images, sufficient color contrast, visible focus states, every input has a label, keyboard-friendly forms, clear button labels, a skip link
- No third-party embeds, no analytics or tracking scripts, no external fonts

COMPLIANCE RULES
- Privacy policy page, terms and conditions page, consent checkboxes on forms that collect personal data
- Cookie notice only if cookies beyond the session cookie are used
- No fake reviews, no unsupported claims in copy, no fake data presented as real
- Only use images and icons that are owned or permitted
- Comply with local laws on student data
- Flag every possible risk you notice

HOW TO WORK
- Work on ONE phase at a time, and only the phase I give you
- Before writing code, list the files you will create or change
- After finishing, give me: files changed, how to test, acceptance checklist result, and risks found
- Never modify files outside the scope of the current phase unless you tell me why first
- If anything is ambiguous, ask me before assuming

Reply with "Ready" and a 3-line summary of your understanding. Do not write code yet.
```

---

## Phase 0 Prompt: Scope and Requirements

```text
PHASE 0: SCOPE AND REQUIREMENTS

Task:
1. Read ROADMAP.md and summarize the three core features, the two roles and the stack contract.
2. Write docs/requirements.md containing:
   - User stories for students and staff (at least 6 each)
   - Functional requirements for lost item reporting, found item upload and claim verification
   - Non-functional requirements: security, accessibility, privacy
   - Out-of-scope list
3. List any open questions about the requirements that need my decision.

Do not write application code.

Acceptance checks:
- requirements.md exists and covers all three core features
- Open questions are listed clearly
```

---

## Phase 1 Prompt: Prototyping in Google Stitch

Use these in Google Stitch, not in the coding agent. Start with the global design prompt, then generate each screen.

### Global design prompt (paste first)

```text
Design a clean, friendly, mobile-first web app called "Classroom Lost & Found" for a school. Audience: students and staff. Style: simple, trustworthy, rounded cards, generous spacing, a calm blue primary color with a warm accent for "Found" and a neutral accent for "Lost". Use high-contrast text, large tap targets, visible focus outlines and clear button labels such as "Report Lost Item" and "Submit Claim". Use a consistent navbar (Home, Lost Items, Found Items, Dashboard, Profile, Login) and footer (Privacy Policy, Terms). Every form field must have a visible label. Do not use stock photos of real people, and do not invent testimonials, statistics or reviews.
```

### Per-screen prompts

```text
Screen 1, Landing page: hero explaining that students can report lost items, staff upload found items, and owners claim through verification. Show a 3-step "How it works" section, two buttons ("Report Lost Item", "Browse Found Items"), and a short list of item categories (IDs, wallets, keys, USBs, calculators, jackets, bottles, phones).
```

```text
Screen 2, Login and Register: two clear forms with labelled fields (name, email, password, confirm password), a role-neutral registration, a consent checkbox linking to the Privacy Policy and Terms, and clear error message areas.
```

```text
Screen 3, Student dashboard: summary cards (My Lost Reports, My Claims, Items Returned), a table of my recent reports with status badges (Open, Claimed, Returned), and quick action buttons.
```

```text
Screen 4, Lost items list: search bar, filters (category, date, status), a grid of item cards (title, category, location, date, status badge), and pagination.
```

```text
Screen 5, Found items list: same layout as Lost items list, with a "Found" badge style. Do not show identifying details on cards for sensitive items such as IDs and phones.
```

```text
Screen 6, Report Lost Item form: fields for item title, category, description, last seen location, date lost, optional photo upload with alt text description field, and a consent checkbox. Clear Submit and Cancel buttons.
```

```text
Screen 7, Upload Found Item form (staff only): fields for item title, category, description, where found, date found, photo upload with alt text description, a private "verification detail" field (visible to staff only), and Submit and Cancel buttons.
```

```text
Screen 8, Item detail and claim: item image, description, status, and a claim form asking the user to describe proof of ownership, plus a consent checkbox and a "Submit Claim" button. Show a notice that staff will verify every claim.
```

```text
Screen 9, Staff claim review: a table of pending claims with item, claimant, proof description and date, with "Approve" and "Reject" buttons and a confirmation step.
```

```text
Screen 10, Profile: user details form (name, email), change password section, and a list of the user's reports and claims.
```

### Phase 1 exit checklist (do this yourself)
- Consistent navbar, colors and components across all screens
- Contrast and focus states checked
- Every form field labelled
- Export HTML/CSS or screenshots into /design
- Component list written: navbar, footer, item card, form field, status badge, alert

---

## Phase 2 Prompt: Database Design

```text
PHASE 2: DATABASE DESIGN

Task:
Create sql/schema.sql and sql/seed.sql for MySQL.

Tables:
- users: id, name, email (unique), password_hash, role (student/staff), created_at
- categories: id, name (unique)
- items: id, type (lost/found), title, category_id (FK), description, location, date_event, image_path, image_alt, verification_detail, status (open/claimed/returned), reported_by (FK users.id), created_at
- claims: id, item_id (FK), claimant_id (FK users.id), proof_description, status (pending/approved/rejected), reviewed_by (FK users.id, nullable), created_at

Requirements:
- InnoDB, utf8mb4, primary keys, foreign keys with sensible ON DELETE rules, indexes on status, type, category_id and email
- Seed: categories (IDs, wallets, keys, USBs, calculators, jackets, bottles, phones), one staff account, one student account (use a pre-generated password hash and tell me the plain password), and 6 sample items clearly marked as sample data
- Also produce docs/er-diagram.md with a text ER diagram

Do not write PHP in this phase. No code comments.

Acceptance checks:
- schema.sql imports into a fresh MySQL database with no errors
- seed.sql loads with no errors
- Foreign keys work: deleting a referenced row is handled as designed
```

---

## Phase 3 Prompt: Project Skeleton

```text
PHASE 3: PROJECT SKELETON

Task:
Create the folder structure: public, includes, pages, uploads, sql, design, docs.

Build:
- includes/db.php: database connection with PDO and prepared statements, with settings in a separate config file that is excluded from version control
- includes/header.php and includes/footer.php: semantic layout with skip link, header, nav (shows links by login state and role), main landmark and footer with Privacy Policy and Terms links
- includes/functions.php: helpers for escaping output, redirects and flash messages
- public/index.php: landing page using the layout
- Use only the project's locally authored CSS; do not add a CSS framework or CDN.

Requirements: follow the Master Prompt rules. No code comments. No outside libraries.

Acceptance checks:
- The landing page renders with the shared layout
- The database connection works and fails gracefully with a friendly message
- The layout is responsive and keyboard navigable
```

---

## Phase 4 Prompt: Authentication

```text
PHASE 4: AUTHENTICATION

Task:
Build register, login, logout and role guards.

Requirements:
- Registration with server-side validation (name, email format, password length, confirm password) and a required consent checkbox
- Login with password_verify, generic error messages (do not reveal whether the email exists), session_regenerate_id on success
- Logout that destroys the session
- includes/auth.php with helpers: require_login, require_role, current_user
- CSRF token helpers in includes/csrf.php used on every form
- Basic protection against repeated failed logins (simple attempt counter in the session is enough)

Follow the Master Prompt. No code comments.

Acceptance checks:
- A new student can register and log in
- A student cannot open any staff-only page
- Forms without a valid CSRF token are rejected
- Passwords are stored only as hashes
```

---

## Phase 5 Prompt A: Lost Item Reporting

```text
PHASE 5A: LOST ITEM REPORTING

Task:
Build full CRUD for lost items (type = lost).

Pages:
- Report form (create) with validation, category dropdown from the database, optional image upload with alt text input, and consent checkbox
- My Reports list (read) showing status
- Edit report (update), only by the owner
- Delete report (delete), only by the owner, with a confirmation step

Requirements:
- Prepared statements, output escaping, CSRF tokens
- Ownership check on every edit and delete
- Image uploads validated by size, extension and MIME type, and saved with a random filename

Follow the Master Prompt. No code comments.

Acceptance checks:
- A student can create, view, edit and delete only their own lost reports
- Invalid or oversized uploads are rejected with a clear message
- Another user cannot edit or delete someone else's report by changing the URL
```

---

## Phase 5 Prompt B: Found Item Upload

```text
PHASE 5B: FOUND ITEM UPLOAD (STAFF ONLY)

Task:
Build full CRUD for found items (type = found), restricted to staff.

Pages:
- Upload found item form with category, description, location found, date found, image with alt text, and a private verification_detail field
- Manage found items list with edit, delete and status change
- Public found items list must never show verification_detail

Requirements:
- require_role('staff') on every page and action
- Follow the Master Prompt rules for uploads, CSRF and escaping
- For sensitive categories (IDs, phones, wallets), do not display the image publicly; show a placeholder instead

No code comments.

Acceptance checks:
- Students get blocked from all staff pages
- verification_detail never appears in any public page or source
- Staff can create, edit, delete and change status
```

---

## Phase 5 Prompt C: Browse and Search

```text
PHASE 5C: BROWSE AND SEARCH

Task:
Build the Lost Items and Found Items list pages.

Requirements:
- Item cards with title, category, location, date and status badge
- Filters: category, status, date range, and keyword search
- Pagination (10 items per page) using LIMIT and OFFSET with validated integers
- All filters implemented with prepared statements
- Empty state message when nothing matches
- Accessible filter form with labels and keyboard support

Follow the Master Prompt. No code comments.

Acceptance checks:
- Combined filters return correct results
- Pagination works and does not break with invalid page numbers
- No SQL injection through any filter field
```

---

## Phase 5 Prompt D: Claim Verification

```text
PHASE 5D: CLAIM VERIFICATION

Task:
Build the claim workflow.

Student side:
- Item detail page with a claim form: proof description and consent checkbox
- A student cannot claim their own report, and cannot submit a second pending claim on the same item
- My Claims list showing status

Staff side:
- Pending claims page showing item, claimant, proof description and the item's private verification_detail
- Approve and Reject actions with a confirmation step
- On approve: claim becomes approved, item becomes returned, other pending claims on that item become rejected, all in one database transaction
- Record reviewed_by

Follow the Master Prompt. No code comments.

Acceptance checks:
- A claim cannot be approved twice
- Only staff can review claims
- The transaction leaves the data consistent if something fails
- Students never see verification_detail
```

---

## Phase 5 Prompt E: Dashboard and Profile

```text
PHASE 5E: DASHBOARD AND PROFILE

Task:
Build the dashboard and profile pages.

Dashboard:
- Student: counts of my lost reports, my claims, and items returned, plus a recent activity list
- Staff: counts of open found items, pending claims, and returned items, plus links to manage them

Profile:
- Update name and email with validation
- Change password (requires current password)
- List my reports and claims

Follow the Master Prompt. No code comments.

Acceptance checks:
- Counts match the database
- Email uniqueness is enforced on update
- Password change requires the current password
```

---

## Phase 6 Prompt: Compliance and Accessibility

```text
PHASE 6: COMPLIANCE AND ACCESSIBILITY

Task:
1. Create public pages: privacy-policy.php and terms.php. Write plain-language content that matches what the app actually collects (name, email, password hash, item reports, claims, uploaded images, session cookie). Mark clearly where the school's contact details and retention period must be filled in by me. Do not invent legal claims.
2. Add consent checkboxes (linked to these pages) on register, report and claim forms if not already present.
3. Add a cookie notice only if the app uses cookies beyond the session cookie. Tell me whether it is needed.
4. Audit the whole project for: alt text, labels, contrast, focus states, heading order, skip link, keyboard-only use, and clear button labels. Fix issues.
5. Confirm there are no third-party embeds, tracking scripts, external fonts or outside libraries.
6. Produce docs/compliance-report.md listing what was checked, what was fixed, and remaining risks.

No code comments.

Acceptance checks:
- Privacy and terms pages are linked in the footer and from forms
- Every image has alt text and every input has a label
- compliance-report.md exists
```

---

## Phase 7 Prompt: Security Hardening and Testing

```text
PHASE 7: SECURITY HARDENING AND TESTING

Task:
Review the entire codebase and fix issues.

Checklist:
- Every query uses prepared statements
- Every output is escaped
- Every form has and verifies a CSRF token
- Every protected page checks login and role
- Uploads: size, extension and MIME checks, random filenames, and no PHP execution inside /uploads (add an .htaccess rule if Apache is used)
- Error messages do not leak SQL or file paths
- Session settings: httponly cookies, regenerate on login
- No debug output left in code

Then create docs/test-plan.md with manual test cases and expected results for: auth, role access, lost CRUD, found CRUD, search, claims (including double approval and self-claim), upload rejection, and responsive and keyboard checks.

Report each issue found, its fix, and any risk left. No code comments.

Acceptance checks:
- All test cases in test-plan.md are written and pass
- No high-risk issues remain unresolved
```

---

## Phase 8 Prompt: Deployment and Presentation

```text
PHASE 8: DEPLOYMENT AND PRESENTATION

Task:
1. Write README.md with: project description, features, exact tech stack, setup steps for a local XAMPP/WAMP environment (create database, import schema.sql and seed.sql, set config, folder permissions for /uploads), test account credentials, and folder structure.
2. Add a "Known risks and limitations" section covering privacy, claim fraud, sensitive item photos, file uploads and timeline.
3. Write docs/demo-script.md: a step-by-step demo of the full flow (student reports lost item, staff uploads found item, student claims, staff approves) with what to say at each step.
4. Verify a fresh setup works by following the README exactly, and list any gaps.

No code comments.

Acceptance checks:
- A new person can set up the project using only the README
- The demo script runs end to end without errors
```

---

## Reusable Fix and Review Prompts

### Bug fix
```text
There is a bug in [feature/page]. Expected: [what should happen]. Actual: [what happens]. Steps to reproduce: [steps]. Find the root cause, fix only what is needed, stay within the stack contract, add no code comments, and tell me which files changed and how to retest.
```

### Phase review
```text
Review the work from the phase we just finished against its acceptance checks and the Master Prompt rules. List every violation, security risk, accessibility issue and stack drift you find, ordered by severity, and fix them. Do not add new features.
```

### Stack drift check
```text
Scan the whole project for anything outside the allowed stack: external libraries, CDN links, JavaScript frameworks, Composer files, external fonts, tracking scripts, or embeds. List each finding with its file and remove or replace it with a stack-compliant solution. Do not treat the optional Google Stitch design-time connection as an application dependency.
```
