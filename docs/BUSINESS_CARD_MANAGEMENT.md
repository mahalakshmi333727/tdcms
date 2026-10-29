# Business Card Management

The Admin **Assignments** page lists businesses as cards. A business is a
parent record; Excel-imported content is stored separately and becomes a regular
TDCMS text assignment when an Admin assigns it to one of the business's active
users. Cards also allow an Admin to add an eligible user or import Excel without
leaving the listing.

The listing supports combined business/assigned-user search, assigned-user
filtering, and business creation-date ranges. Results are paginated server-side
in groups of ten, with active filters retained when navigating pages. The
Create Business form starts each business with one selected active user;
additional users can be added from its card or details page.
The business Reviews Count is a manually editable value; it is separate from
the database-driven Total Content count.

## Database upgrade

Back up the target database and verify the backup before applying migrations.
For Windows XAMPP, run from Command Prompt, substituting the database host,
port, and name:

```bat
C:\xampp\mysql\bin\mysqldump.exe -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME > C:\secure-backups\tdcms-before-business-cards.sql
```

Apply the existing installation's outstanding migrations in the order listed
in the Installation Guide. The business-card migration requires
the current `users` and `assignments` tables (including the `tags` column):

```bat
C:\xampp\mysql\bin\mysql.exe -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < sql\migrations\2026_10_add_business_card_management.sql
```

Enter the database password at the prompt; do not put it in a command, source
file, or publicly accessible configuration. Before applying the migration,
check that none of its four business tables or the `assignments.business_content_id`
column already exists. The migration is additive, preserves existing

After the business-card tables have been created, apply
`sql/migrations/2026_10_add_business_reviews_count.sql` once to add the editable
Reviews Count field. The migration initializes existing businesses from their
current non-archived content count. The migration is additive, preserves existing
assignments, and is **not idempotent**; do not run it twice. It does not import
or reinterpret pre-existing assignments as business content. Never import
`sql/schema.sql` into an existing installation because it drops and recreates
tables. Fresh installs use the updated schema directly.

## Excel content format

Business content imports accept `.xlsx` workbooks. Use the first worksheet
with a header row containing `Title` and `Content`; `Reference Link` is
optional. There is no application-level record-count limit. PHP upload
configuration, available memory, execution time, and database capacity still
constrain the practical workbook size.

Accepted title aliases include `Page`, `Section`, and `Task`. Accepted content
aliases include `Review Text`, `Review Content`, `Text`, and `Description`;
link aliases include `Reference`, `Link`, `URL`, and `Website`. Each valid row
needs a title and content. Links must be HTTP or HTTPS URLs. Invalid rows and
duplicates are reported in import history. Duplicate detection is per business
and hashes the trimmed title, normalized line endings in the trimmed text, and
item reference link. The business-level reference link is used for an
assignment when the item has no link.

## Count and assignment rules

- **Total content:** non-archived content rows stored for the business.
- **Used content:** those rows with a linked assignment whose status is
  `completed`. Completion remains governed by the existing copy/reference/
  confirmation flow; a page view or click alone does not count.
- **Active content:** those rows with a linked `pending` assignment whose
  assignment is not archived.
- **Available content:** non-archived content with no linked assignment.
- Each content row can become **one assignment for one business user**. The
  assignment retains the existing ownership, deadline, completion, archive,
  and overdue-notification workflow. Unlinking a user does not alter existing
  assignments.
- Daily limit counts all business-content assignment rows created during the
  current Asia/Kolkata calendar day, including tasks completed or archived
  later that day. A transaction locks the business row while checking capacity
  and creating an assignment, so concurrent requests cannot exceed the limit.
  Editing the limit does not alter assignment history.

An Admin can add or remove active users, import more XML, edit business details,
and assign available content from the business details page. The individual
assignment administration route remains available separately and is not part
of the business-card listing.

The existing **Bulk Assign** workflow remains available for standalone tasks.
It can optionally target an existing business; when selected, the chosen user
must be an active member, each task is linked to that business's content, and
the business's daily limit and per-business duplicate checks apply. The
business and its membership are not created by Bulk Assign. Leave the business
selection blank to retain the original standalone assignment behavior. A
business-linked bulk request is atomic per submitted task batch and is rejected
if it exceeds the remaining daily limit.
