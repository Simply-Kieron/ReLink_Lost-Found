# Classroom Lost & Found demo script

## Before the demo

1. Complete the local setup in the [README](../README.md), including importing the schema and seed data.
2. Open `http://localhost/lost-and-found/public/index.php`.
3. Have the sample staff and student credentials from the README ready. These accounts and their password are for local demonstration only.
4. Use a disposable database, a test school mailbox you can access, and a prepared fictional school ID image. Never use a real student's ID for a demo. The walkthrough creates one lost report and one matching found listing; this app does not automatically link reports to found listings.

## Walkthrough

### 1. Show the public landing page

Open the home page and point out the Lost Items and Found Items navigation.

**Say:** “This is the community-facing entry point. Students can report lost property and browse public found listings. Staff use a separate role to record found property and review claims.”

### 2. Submit student verification

1. Select **Login** and sign in as `student@example.test` using the README's sample password.
2. Open **Profile** and submit a school email you can access, a fictional school ID number, and the prepared test ID image.
3. Log out and sign in as `staff@example.test`. Open **User Management**, inspect the submitted details and image, then approve the request.
4. Copy the one-time code shown after approval and manually send it to the test school mailbox.
5. Log back in as the student, open **Profile**, and enter the emailed code.

**Say:** “Students submit their school ID from Profile. Staff review the ID, then manually send a short-lived code to the school email. The student must enter that code before posting or claiming.”

### 3. Report a lost item as a verified student

1. Select **Report Lost Item**.
2. Enter fictional details, for example:
   - Title: `Blue insulated bottle`
   - Category: `bottles`
   - Description: `A blue insulated bottle with a plain silver cap.`
   - Location: `Room 12`
   - Date: today's date
3. Leave the image blank for this demo. Check the consent box and submit.
4. Open **My Reports** and show the report and its status.

**Say:** “Only verified student accounts can create lost reports or claims. The form validates details and requires acknowledgement of the Privacy Policy and Terms.”

### 4. Sign out and sign in as staff

1. Use **Log out**.
2. Log in as `staff@example.test` with the README's sample password.
3. Open **Upload Found Item**.

**Say:** “Staff-only pages are guarded by the staff role. A student cannot open these pages by typing their URLs.”

### 5. Upload the matching found item

1. Enter a matching fictional item:
   - Title: `Blue insulated bottle`
   - Category: `bottles`
   - Description: `A blue insulated bottle was handed in to the office.`
   - Location: `Main office`
   - Date: today's date
   - Private verification detail: `A small white label marked LAB-42 is on the base.`
2. Leave the image blank. Check the consent box and submit.
3. Open **Manage Found Items** and show the listing. Point out that staff can see the private verification detail there.
4. Open **Found Items** in the public navigation and show the public listing or its detail page. Confirm the private verification detail is absent.

**Say:** “The public record gives enough information to identify the general item without revealing a distinguishing detail. Staff retain that private detail to compare against claims.”

### 6. Sign back in as the verified student and submit a claim

1. Log out, then log in again as `student@example.test`.
2. Open **Found Items**, select the newly uploaded blue bottle, and open its detail page.
3. In **Proof of ownership**, enter: `The base has a small white label marked LAB-42.`
4. Check the consent box and select **Submit claim**.
5. Open **My Claims** and show that the claim is pending.

**Say:** “The student submits identifying evidence that is not available on the public listing. The claim appears as pending, and staff alone can see the proof alongside their private verification detail.”

### 7. Review and approve the claim as staff

1. Log out and sign in as `staff@example.test`.
2. Open **Review Claims**. Compare the student's proof with the private verification detail.
3. Select **Approve**, review the confirmation page, and select **Confirm approval**.
4. Return to **Manage Found Items** and show the item's returned status. If desired, sign in as the student again and show the approved status in **My Claims**.

**Say:** “Staff make the decision after comparing the evidence. Approval is recorded with the reviewing staff account, marks the item returned, and rejects any other pending claims for that item as one database transaction.”

## Wrap-up

**Say:** “This demonstration used sample accounts and fictional details. Before real use, the school must complete the policy contact and retention information, set a real review timeline, secure the deployment, and verify the upload protections on its own server.”

## Resetting the demo

For a clean repeat, use a new disposable database and follow the README imports again. Do not drop, reset, or seed a database containing real records.
