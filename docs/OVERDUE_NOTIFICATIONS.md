# Same-day overdue notifications

## Scope and deadline behavior

Deadlines apply to regular text assignments only. Excel assignments keep
their separate workflow and are not changed by this feature.

Each new text assignment records its assignment time and deadline in the
application's `Asia/Kolkata` timezone. Its deadline is 4:00 PM IST on that
same calendar date. Assignments made after 4:00 PM retain that day's 4:00 PM
deadline and become eligible for notification on the next detector run.
Bulk assignments and imported text tasks use the same insertion path and
receive individual timestamps and deadlines.

Editing or reassigning an assignment does not reset its original deadline.
The existing update activity log remains the audit record for edits. Existing
assignments receive no deadline during migration and are not backfilled or
notified retroactively.

The detector creates a notification only when the assignment is still
`pending`, not archived, has a deadline, and that deadline has passed. A
task completed before the detector runs does not get an overdue notification.
Once generated, a notification remains in the database even if the task is
later completed or archived. Assignment and user names and timing are stored
as snapshots; deleting a related record will not delete the notification.

Notifications are generated in batches of up to 500 on each run. A unique
constraint on the assignment prevents duplicates when runs overlap or repeat.

## Applying the database migration

1. Back up the database before applying schema changes.
   Fresh installations already receive these structures from `sql/schema.sql`;
   do not run this migration again after importing that full schema.
2. Ensure the existing assignment-progress and assignment-archive migrations
   have been applied; the overdue lookup index uses `assignments.archived_at`.
3. From the project root, run the new migration once against the application
   database:

   ```sh
   mysql -h DB_HOST -u DB_USER -p DB_NAME < sql/migrations/2026_10_add_assignment_deadlines_and_admin_notifications.sql
   ```

   Replace the connection placeholders with the database credentials for the
   target environment. The migration adds a nullable deadline, an index for
   overdue lookup, and the persistent Admin notification table. It does not
   modify existing assignment rows.
4. Verify that `assignments.due_at` and `admin_notifications` exist before
   deploying the PHP changes.

## Scheduling the detector

The PHP CLI script must run independently of Admin logins. Run it at least
every five minutes. It refuses non-CLI execution, and Apache denies direct
requests to the `bin` directory.

On Linux/Unix, add a cron entry using the absolute PHP and project paths:

```cron
*/5 * * * * /usr/bin/php /absolute/path/to/tdcms/bin/check_overdue_assignments.php >> /absolute/path/to/tdcms/logs/overdue_notifications.log 2>&1
```

For example, if PHP is `/usr/bin/php` and the project is
`/var/www/html/tdcms`:

```cron
*/5 * * * * /usr/bin/php /var/www/html/tdcms/bin/check_overdue_assignments.php >> /var/www/html/tdcms/logs/overdue_notifications.log 2>&1
```

On Windows/XAMPP, create a Windows Task Scheduler task that repeats every
five minutes. Set **Program/script** to:

```text
C:\xampp\php\php.exe
```

Set **Add arguments** to:

```text
C:\xampp\htdocs\tdcms\bin\check_overdue_assignments.php
```

Set **Start in** to:

```text
C:\xampp\htdocs\tdcms
```

Use an account that can read the application files and connect to MySQL.
Run the same command manually from a terminal to verify CLI/database access:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\tdcms\bin\check_overdue_assignments.php
```

The job runs on its schedule, so an overdue notification is created on the
first successful run after the deadline, not at an exact guaranteed second.
Monitor the scheduled task and its output/error log to detect failures.
