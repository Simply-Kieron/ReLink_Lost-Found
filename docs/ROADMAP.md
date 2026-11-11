# Classroom Lost & Found Management System: Development Roadmap

## Project summary
A centralized system to help return lost items. Students report lost items, staff upload found items, and owners claim items through verification.

Items covered: IDs, wallets, keys, USBs, calculators, jackets, bottles, phones.

## Stack contract (strict, for humans and AI coding agents)
- Frontend: semantic HTML5 and locally authored CSS in `assets/site.css`
- Backend: plain PHP with CRUD operations
- Database: MySQL relational database with 2+ related tables
- Not allowed: PHP frameworks (Laravel, Symfony, etc.), JS frameworks (React, Vue, etc.), Composer packages, jQuery, Bootstrap, or any tool outside the course stack
- Google Stitch is used only as an optional design-time prototyping tool. Keep screenshots and design notes; do not ship generated code or externally hosted assets.
- No external runtime libraries, CDNs, fonts, embeds, analytics, or tracking scripts.
- Do not add code comments in any generated code.

## Roles
- Student: registers, reports lost items, browses items, submits claims, manages own reports
- Staff: uploads found items, reviews claims (approve or reject), manages all items

## Rules that apply to every phase
- Privacy policy page, terms and conditions page, and consent checkboxes on forms that collect personal data
- Cookie notice only if cookies beyond the session cookie are used
- Accessibility: semantic HTML, alt text on every image, sufficient color contrast, keyboard-friendly forms, visible focus states, labelled inputs, clear button labels
- No fake reviews, no unsupported claims in copy, no fake data presented as real
- Only use images that are owned or permitted
- Comply with local laws on student data
- Flag all possible risks in the README

---

## Phase 0: Scope and rules
- [ ] Freeze the three core features: lost item reporting, found item upload, claim verification
- [ ] Confirm roles and permissions
- [ ] Confirm the plain PHP, MySQL, HTML, and local CSS stack contract
- [ ] Write the requirements list

Done when: scope is written down and stack questions are answered.

## Phase 1: Prototyping in Google Stitch
- [ ] Write one prompt per screen using the existing mockup as reference
- [ ] Screens: landing, login/register, dashboard, lost items list, found items list, report lost form, upload found form, item detail with claim form, staff claim review, profile
- [ ] Generate and iterate until layout, colors and navigation are consistent
- [ ] Check every screen for contrast, focus states, labelled fields and clear button labels
- [ ] Export screenshots and design notes into `/design`; do not retain generated runtime code
- [ ] List reusable components: navbar, footer, item card, form field, status badge, alert
- [ ] Get feedback from a classmate or teacher, then freeze the design

Done when: an approved screen set and a component list exist in `/design`.

Note: Stitch output can include external scripts, fonts, and images. Keep design references local and rebuild application pages with semantic HTML and the project's local CSS.

## Phase 2: Database design
Tables (minimum 2 related; recommended 4):

- `users`: id, name, email (unique), password_hash, role (student/staff), created_at
- `categories`: id, name
- `items`: id, type (lost/found), title, category_id, description, location, date_event, image_path, status (open/claimed/returned), reported_by (FK users.id), created_at
- `claims`: id, item_id (FK items.id), claimant_id (FK users.id), proof_description, status (pending/approved/rejected), reviewed_by (FK users.id, nullable), created_at

Tasks:
- [ ] Draw the ER diagram
- [ ] Write `sql/schema.sql` with primary keys, foreign keys and indexes
- [ ] Write `sql/seed.sql` with test categories, one staff account and sample items

Done when: schema imports cleanly into MySQL and seed data loads.

## Phase 3: Project skeleton
Suggested structure:

```
/public        entry pages and assets
/includes      db connection, auth helpers, header, footer, csrf helpers
/pages         feature pages
/uploads       user-uploaded images
/sql           schema.sql, seed.sql
/design        Stitch exports and screenshots
README.md
```

- [ ] Create the database connection using PDO or mysqli with prepared statements
- [ ] Create shared header and footer partials
- [ ] Use the project's locally authored stylesheet at `assets/site.css`
- [ ] Create base layout with skip link, landmarks (header, nav, main, footer)

Done when: a blank page renders with the shared layout and connects to the database.

## Phase 4: Authentication
- [ ] Register with validation and consent checkbox
- [ ] Login using `password_hash` and `password_verify`
- [ ] Session start, `session_regenerate_id` on login, logout
- [ ] Role guard helper used on every protected page
- [ ] CSRF token on every form

Done when: students and staff can log in and are blocked from pages outside their role.

## Phase 5: Core features
1. Lost item reporting
   - [ ] Create form with validation
   - [ ] "My reports" list with edit and delete (CRUD)
2. Found item upload (staff only)
   - [ ] Form with image upload: check size, extension and MIME type, rename the file, store outside direct execution
   - [ ] Edit and delete found items
3. Browse and search
   - [ ] Lists for lost and found items
   - [ ] Filters by category, date and status
   - [ ] Pagination
4. Claim verification
   - [ ] Claim form on item detail with a proof question only the owner can answer
   - [ ] Staff review page: approve or reject
   - [ ] Approval sets the item status to returned, and a claim cannot be approved twice
5. Dashboard and profile
   - [ ] Counts by status
   - [ ] User's own items and claims

Done when: the full flow works end to end: report lost, upload found, claim, approve.

## Phase 6: Compliance and accessibility
- [ ] Privacy policy page
- [ ] Terms and conditions page
- [ ] Consent checkboxes on register, report and claim forms
- [ ] Cookie notice only if needed
- [ ] Alt text on all images
- [ ] Verify color contrast on all pages
- [ ] Keyboard-only walkthrough of every form
- [ ] Confirm no third-party embeds or tracking scripts
- [ ] Confirm all images and icons are owned or permitted

## Phase 7: Security hardening and testing
- [ ] Prepared statements on every query
- [ ] `htmlspecialchars` on every output
- [ ] Server-side validation on every input
- [ ] Role tests: a student cannot reach staff pages
- [ ] Claim tests: cannot claim own report, cannot double-approve
- [ ] Upload tests: reject non-image and oversized files
- [ ] Responsive check at phone, tablet and desktop widths

## Phase 8: Deployment and presentation
- [ ] Run locally on the course environment (XAMPP/WAMP or equivalent)
- [ ] Export the final database
- [ ] Write the README: setup steps, test accounts, known risks
- [ ] Prepare the demo flow: report lost item, upload found item, claim, approve

---

## Risks to flag
- Privacy: names, emails and item photos are personal data. Collect only what is needed and state how long it is kept.
- Claim fraud: someone may claim an item that isn't theirs. Mitigate with proof questions and mandatory staff approval, and do not publicly show identifying details of found items.
- Sensitive items: IDs and phones may show personal information, so avoid or blur public photos of these.
- Stack drift: Stitch output, remote fonts, icon libraries, scripts, and images can quietly add outside dependencies.
- File uploads: a common PHP attack vector, so validate strictly.
- Timeline: roughly 3-4 weeks at a steady pace. If time runs short, cut filters and pagination first, never claim verification.

## Instructions for the AI coding agent
- Work one phase at a time and finish its "Done when" check before moving on.
- Stay inside the stack contract. If something seems to need an outside library, stop and ask.
- Use prepared statements, output escaping and CSRF tokens everywhere.
- Do not add comments to code.
- After each phase, list what was built and any risks found.
