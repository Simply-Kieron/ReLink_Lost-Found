# ReLink — Classroom Lost & Found Management System

ReLink is a classroom lost and found management system for students to report lost and found items and claim found items after staff verify their student status. Staff review found-item reports before publication, review claims, and review student ID submissions. Student ID photos and claim verification details are restricted to staff.

## Technology stack

- PHP 8.2 or later, using plain PHP (no framework)
- MySQL 5.7 or later with InnoDB and `utf8mb4`
- PHP PDO with the `pdo_mysql` driver and PHP `fileinfo` extension
- Apache 2.4 on XAMPP or WAMP
- HTML5, Tailwind CSS 4, and locally authored component styles at `assets/site.css`
- Tailwind CSS is compiled locally to `assets/tailwind.generated.css`; the generated stylesheet is served without a runtime CDN or JavaScript framework
- Node.js 20 or later and npm are needed only to rebuild the CSS; there are no Composer packages, external fonts, analytics, or third-party embeds

The app's interface has no runtime network dependency.

## Local setup: XAMPP or WAMP on Windows

Use a fresh development database. Do not import the seed into a database containing real data. The example credentials below are for local demonstrations only.

1. Install or start XAMPP/WAMP with Apache 2.4, MySQL 5.7+ (or a compatible MySQL server), PHP 8.2+, and the PHP `pdo_mysql` and `fileinfo` extensions enabled. Apache must allow `.htaccess` overrides for this project; the included rules deny direct HTTP access to internal folders and disable upload execution.
2. Copy the project folder into the web server's document tree. A convenient folder name without URL-special characters is:
   - XAMPP: `C:\xampp\htdocs\lost-and-found`
   - WAMP: `C:\wamp64\www\lost-and-found` (use the actual WAMP installation path)
3. Start Apache and MySQL from the XAMPP/WAMP control panel. Confirm that phpMyAdmin opens from the local server.
4. In phpMyAdmin, open the **SQL** tab and create a new database:

   ```sql
   CREATE DATABASE lost_and_found
       CHARACTER SET utf8mb4
       COLLATE utf8mb4_unicode_ci;
   ```

   If a database named `lost_and_found` already exists, create a different empty database name and use that name in `includes/config.php` in the next step.
5. Select the empty database in phpMyAdmin. Use its **Import** tab to import `sql/schema.sql` first, then import `sql/seed.sql`. Confirm both imports complete without errors. The seed creates item categories, two demonstration accounts, and six records explicitly labelled as sample data.
6. In the project directory, copy `includes/config.example.php` to `includes/config.php`. For example, from PowerShell opened in the project directory:

   ```powershell
   Copy-Item includes\config.example.php includes\config.php
   ```

   Edit `includes/config.php` so `database`, `username`, and `password` match the database created above. The example uses host `127.0.0.1` and port `3306`; change these if your local MySQL server uses another host or port. The default WAMP/XAMPP root account may have a blank password locally; use your configured database account, not these example settings, for any shared or production server. This configuration file is ignored by Git and must not be committed.
7. Allow the Apache/PHP service identity to write files in the project's `uploads` folder. On Windows, use the folder's **Properties → Security** settings to grant the account running Apache/PHP the minimum required modify/write permission. Do not grant broad write access to `Everyone`. Keep the existing `uploads/.htaccess` file in place.
8. Open `http://localhost/lost-and-found/public/index.php` in a browser. If the project is installed in a different folder, replace `lost-and-found` in the URL with that folder name.
9. Log in with one of the sample accounts below, or register a student account. After testing, remove or change the sample accounts and sample records before using the application with real users.

### Rebuilding Tailwind CSS

The compiled stylesheet is included, so Node.js is not required to run the app. When changing Tailwind classes or styles, install the development dependency once using Node.js 20 or later, then rebuild:

```powershell
npm install
npm run build:css
```

Use `npm run watch:css` during development to rebuild the stylesheet as PHP templates change. The Tailwind content configuration scans the shared includes, page templates, and public entry point.

For an existing installation, back up the database and import `sql/verification-migration.sql` once to create the student verification table, followed by `sql/verification-code-request-migration.sql` and `sql/verification-delayed-submission-migration.sql`. If the table already exists, do not re-import its create-table migration; apply any missing upgrade migrations in order. Import `sql/found-item-review-migration.sql` once to enable staff review of student found-item reports; existing found listings remain approved. Import `sql/item-category-groups-migration.sql` once to consolidate existing categories into the grouped list, including "Other". Ensure Apache honors `uploads/student-verification/.htaccess`; that folder stores private ID photos and must not be directly web-accessible.

### Image upload limits

Item photos, school ID photos, and profile photos are limited to 15 MB. Item photos also require alternative text before they are saved. PHP's `upload_max_filesize` must be larger than the app's largest image limit, and `post_max_size` must also allow for the multipart form fields. The supplied WAMP PHP configuration uses 16 MB and 32 MB respectively. Restart Apache after changing PHP upload settings.

Import `sql/user-management-migration.sql` once on existing installations to add account suspension and forced password-change fields. Import `sql/support-chat-migration.sql` once to add student support sessions and message history. If support chat is already installed, import `sql/support-chat-reference-code-migration.sql` once to add reference codes to existing sessions. Import `sql/profile-avatar-migration.sql` once to add public profile-photo support. Keep `uploads/profile-avatars/.htaccess` in place; profile photos are public, while student ID photos remain private.

### Support chat

Students open **Chat with Staff** from the account menu to continue an open session, review previous chats, or start a new one. Each session has a private reference code shown to both students and staff; it identifies the conversation but does not grant access to it. Staff use **Support Inbox** to read and reply to open sessions, then close each conversation when resolved. When staff use **I found this item** on a lost-item listing, the owner receives a private message in Chat with Staff; the lost report stays open until the item is actually returned. Student finder reports continue to appear in the staff inbox. Closed conversations remain available as read-only history to students; staff can review them in the inbox. The chat is page-refresh based; it does not send email or provide real-time notifications. Students should not send passwords or verification codes in chat.

### Found-item reporting and review

Verified students can submit found-item reports from the account menu. Reports stay private while pending staff review; staff approve valid listings before they appear in the public Found Items feed. Staff can also continue to add already-reviewed items directly from **Manage found items**.

### Lost-report moderation

Lost reports are public after submission. Staff can use **Manage lost reports** to reject and hide inappropriate listings without deleting their history, or restore a rejected report so it is public again. Students can see when their report is not approved in My Activity. Rejected reports are omitted from public lost-item browsing and cannot receive finder reports.

### Student verification workflow

1. Students keep the sign-up email on their account; it cannot be changed in Profile. They enter their school email and select **Request code**.
2. A staff member finds the code request in **User Management** and issues an 8-digit one-time code. Staff manually send it to the student's school email. The code expires after 24 hours and allows five attempts.
3. After receiving the code, the student enters it in Profile, adds the school ID number and photo, and submits all three together. A correct code confirms the school email; the account remains unverified while staff reviews the private ID photo.
4. Staff review the submitted ID in **User Management** and approve or reject it. Approval verifies the student. Only then can the student create lost- or found-item reports or claim found items. Staff accounts are not subject to student verification.
5. If the code expires or reaches its attempt limit, the student can request another code. Staff issue and manually send the replacement; the student submits the replacement together with their ID details.

Staff can use **User Management** to search/filter a compact list of users, suspend/reactivate student accounts, revoke verification, issue a temporary password that must be changed at next login, or permanently delete a student account and its associated records.

Email delivery is intentionally manual; the app does not send email. Before real use, agree on who reviews ID documents, how codes are sent safely, and when submitted ID data is deleted.

### Seeded demonstration accounts

| Role | Email | Password |
|---|---|---|
| Staff | `staff@example.test` | `LostFound-Demo-2026!` |
| Student | `student@example.test` | `LostFound-Demo-2026!` |

These are public sample credentials for a local demo only. Do not use them on an Internet-accessible deployment. The seed stores pre-generated password hashes, not the plaintext password.

### Database import from Command Prompt (optional)

If the MySQL command-line client is available, run these commands in Windows Command Prompt (`cmd.exe`) from the project directory after creating an empty database. Replace the executable path and database name to match your installation:

```cmd
"C:\xampp\mysql\bin\mysql.exe" -u root -p lost_and_found < sql\schema.sql
"C:\xampp\mysql\bin\mysql.exe" -u root -p lost_and_found < sql\seed.sql
```

For WAMP, the client is under the WAMP installation's `bin\mysql\...` directory. A blank local database password may require omitting `-p`. The phpMyAdmin import steps above work without locating the client executable.

## Fresh setup verification

A fresh disposable database can be imported from the schema and seed files. The seeded staff account can review student verification requests; the seeded student remains unable to post or claim until it completes the ID review and code workflow. Existing claim and lost-report flows then operate for verified students. On the local Apache host, confirm direct requests to the internal `sql/`, `includes/`, and `docs/` folders and to `uploads/student-verification/` are denied.

## Setup gaps to resolve before real use

- Copy `includes/config.example.php` to the ignored `includes/config.php` and set the actual database connection details; the real config is intentionally not supplied or committed.
- Use a fresh database for first import. On an existing install, apply the migrations described above after taking a backup.
- Complete the school contact and retention information in the Privacy Policy and decide who reviews ID documents, sends codes, and handles deletion requests.
- The school must test the `.htaccess` access denials and upload protections on its own XAMPP/WAMP or production host. Hosts with overrides disabled need equivalent server configuration.
- Set a school review timeline and an operational contact for unresolved claims; the app does not send email or enforce an SLA.

## Main folders

```text
assets/       Tailwind source, compiled utilities, and component styles
design/       Design-phase materials
docs/         ER diagram, compliance report, security test plan, and demo script
includes/     Shared PHP helpers, database connection, and config template
pages/        Authentication, item, claim, dashboard, profile, and legal pages
public/       Public landing page
sql/          MySQL schema and sample seed data
uploads/      User-uploaded item images and Apache upload protections
README.md     Project setup and usage
```

The local Apache URL uses the project directory as its base because the current page routes and asset links reference sibling `pages`, `includes`, and `assets` directories.

## Known risks and limitations

- **Privacy and records:** The app stores names, sign-up and school email addresses, password hashes, school ID numbers and photos, reports, claims, uploaded images, and a session cookie. ID photos are private to staff in the app, but the school must verify server access controls, complete the school contact and retention details in the Privacy Policy, and define deletion and review procedures before real use. Consent acknowledgements are required in relevant forms, but the current schema does not retain a timestamp or terms-version record.
- **Claim fraud and human review:** A claim is evidence submitted by a user, not proof of identity. Staff must independently assess claims and arrange a safe handover. The software does not establish ownership, send notifications, or provide an appeal or dispute process.
- **Sensitive item photos and public details:** Public found-item pages hide photos for IDs, wallets, and phones and never display private verification details. Photos and descriptions for other categories can be public; lost reports are visible in the browse list too. Do not upload faces, student IDs, contact details, serial numbers, or other sensitive information. Staff should keep private verification details out of titles, public descriptions, and locations.
- **File uploads:** Images are checked for size, extension, and detected image content, assigned random names, and the Apache `uploads/.htaccess` disables script execution and directory listing. These protections depend on server configuration: confirm Apache allows the required `.htaccess` overrides and that PHP can write to `uploads` but untrusted uploads cannot execute. Re-test these controls on the deployment host. Back up and remove uploaded images according to the school's retention policy.
- **Code delivery:** Verification codes are generated by the app but are not emailed. Staff must deliver them to the submitted school email and avoid sending them to the separate sign-up email.
- **Response timeline:** Claim review is manual. There is no email/SMS notification, service-level target, or automatic expiry for reports or claims. The school must publish its own expected review and item-collection timeline and check the staff review queue.
- **Deployment security:** Session-based failed-login throttling can be bypassed by starting a new session. Use HTTPS, non-sample database credentials with least privilege, production-grade rate limiting, restricted web-server access, backups, monitoring, and a documented deletion process before public deployment. The setup instructions are for a local development environment, not a complete production deployment guide.
- **Web-root exposure:** The application expects the project directory to be the URL base, so PHP routes and local assets are siblings of `public/`. Apache `.htaccess` rules deny direct HTTP access to `includes/`, `sql/`, `docs/`, and `design/`. These rules only work if the host allows the required overrides. Verify that direct requests to those folders are denied on the actual host before launch; configure equivalent server rules if `.htaccess` is disabled.

## Manual testing

See [docs/test-plan.md](docs/test-plan.md) for the recorded manual security and accessibility checks. See [docs/demo-script.md](docs/demo-script.md) for a guided student-to-staff walkthrough.

## Main local working copy

The main local project is `C:\wamp64\www\relink`, connected to
`https://github.com/Simply-Kieron/ReLink_Lost-Found.git`.
Make changes and run Git commands from that folder.

Open `http://localhost/relink/` or `http://localhost/relink/pages/login.php`.
On this machine, Apache runs from `C:\wamp`. A directory junction at
`C:\wamp\www\relink` serves the main `C:\wamp64\www\relink` folder. Keep the original `LostFound` folder as a
backup; it is no longer the main working copy.

The local database configuration and uploaded files are copied for local use
and stay excluded from Git. The existing database is shared with the original
copy; this move does not create or import a database.
