# Entity-Relationship Diagram

```text
+-------------------+       +-------------------+       +-------------------+
| users             |       | categories        |       | items             |
|-------------------|       |-------------------|       |-------------------|
| PK id             |       | PK id             |       | PK id             |
| name              |       | UQ name           |       | type              |
| UQ email          |       +-------------------+       | title             |
| password_hash     |                 1                 | FK category_id    |
| role              |                 |                 | description       |
| created_at        |                 +---------------->| location          |
+-------------------+                                   | date_event        |
        1                                               | image_path        |
        |                                               | image_alt         |
        +-----------------------------+                 | verification_detail|
        |                             |                 | status            |
        v                             |                 | FK reported_by    |
+-------------------+                 |                 | created_at        |
| claims            |                 |                 +-------------------+
|-------------------|                 |                          1
| PK id             |                 |                          |
| FK item_id        |-----------------+                          |
| FK claimant_id    |                                            |
| proof_description |                                            |
| status            |                                            |
| FK reviewed_by    |                                            |
| created_at        |                                            |
+-------------------+                                            |
        ^                                                        |
        +--------------------------------------------------------+

Relationships:
- One student has at most one student-verification record. Each record stores the school email, school ID number and private ID photo path.
- Staff may review many student-verification records; a verification may have no reviewer yet.
- One user can report many items; each item has one reporting user.
- One category can classify many items; each item has one category.
- One item can have many claims; each claim refers to one item.
- One user can make many claims; each claim has one claimant.
- One user can review many claims; a claim may have no reviewer yet.

Delete behavior:
- A category or user referenced by an item cannot be deleted.
- A user who is a claimant cannot be deleted while their claims exist.
- Deleting an item deletes its claims.
- Deleting a reviewer sets reviewed_by to NULL.
- Deleting a student deletes their verification record.
