# Item-content migration and read-only audit

## Storage transition

### Optional Exacomp integration

Exaport can be installed without Exacomp. If Exacomp code is present, installing
or upgrading this Exaport release requires Exacomp **2026111200** or later.
The upgrade checks this before any migration or upgrade savepoint. The install
callback also checks it, including recovery of an interrupted installation.
Moodle creates the fresh installation's tables before calling that callback;
keep the site in maintenance mode until installation completes successfully.

Update Exacomp first, then Exaport, or deploy both together. The check reads the
Exacomp version on disk so it accepts a combined deployment even before Moodle
has recorded Exacomp's new installed version. The matching Exacomp release pauses all plugin operations until Exaport's content
conversion and deferred event replay have completed. Moodle's installed version
alone no longer means that portfolio content is ready. A failed upgrade does not restore deployed PHP
files: restore the previous code or finish the compatible deployment before
reopening the site.

Installing or restoring old Exacomp afterward is unsupported. Its direct database
writes cannot be intercepted by Exaport's PHP API. Run the **current** audit to
find legacy URLs, legacy files and orphaned structured content created by such
writers. Findings include counts and bounded sample IDs, but cannot identify
which plugin wrote the data. The historical migration report does not change.

### Structured storage

Before the structured-content transition, an item stored its link in
`block_exaportitem.url`, its historical attachment marker in
`block_exaportitem.attachment`, and uploaded content in the
`block_exaport/item_file/<item id>` File API area. Structured content is now an
ordered set of `block_exaportitemblock` rows of type `text`, `link`, or `file`.
File blocks use `item_content_file/<block id>` and embedded text-editor files use
`item_content_text/<block id>`, both in the parent item's owner user context.

The `attachment` column is retained as opaque historical metadata. Its value is
not moved into a block, interpreted as a File API ID, or used by current content
readers and visibility filters. Migration and later item edits preserve existing
values verbatim; item/category copies preserve them too. Moodle course backups
exclude personal portfolio items, as described in [course backup scope](course-backup.md).
New items created from scratch initialize the field to an empty string. Keeping
the metadata permits later investigation without requiring every installation to
have a pre-upgrade backup. It does not itself restore historical OEZ visibility:
an item with no introduction or supported structured block can remain hidden.

The relevant versions and identifiers for this release are:

| Purpose | Value |
| --- | --- |
| Content-block table creation savepoint | `2026101901` |
| Migration schema/report identifier (historical savepoint) | `2026101902` |
| Background-migration scheduling savepoint and final Exaport version | `2026111203` |
| Finding-history schema savepoint | `2026101902` |
| Historical migration report identifier (`migrationversion`) | `2026101902` |

The report identifier remains fixed at `2026101902`; it is not the installed
plugin version. The ordinary upgrade creates the schema and queues
`\block_exaport\task\migrate_item_content`. It does not convert portfolio items
inside the administrator's upgrade request. Fresh installations and upgrades of an already-structured release need no legacy
conversion, including fresh older installations without a historical report. An
existing completed report is preserved and does not queue a new conversion.

The background task converts meaningful legacy URLs and non-directory legacy
files. Its expected clean postcondition is:

* the parent `url` column is empty;
* no non-directory files remain in `item_file`; and
* each migrated URL/file has a corresponding structured link/file block.

Retained attachment metadata is not a migration residual and does not prevent a
clean result. Meaningful parent URLs are cleared after copying their exact values
to link blocks; actual files are independently copied and verified through the
File API. These operations do not require erasing the attachment marker.

### Background execution and plugin pause

After the normal Moodle upgrade finishes, ordinary Moodle pages remain usable.
**Exaport and Exacomp stay paused** until conversion and deferred event replay
finish. Plugin pages, external functions (including Diggr-plus), file callbacks,
public integration APIs, distribution/import tasks, privacy requests and plugin
backup/restore steps reject operations before their normal work. Course blocks
show a temporary-unavailability notice. The audit, migration issues and migration
status pages remain available to authorised administrators.

User and course deletion are temporarily rejected as well: Moodle would otherwise
delete contexts and files belonging to the paused plugins. Normal course activity
can continue. The companion Exacomp release journals its course/activity/quiz
observers, including required snapshots, actor identity and quiz-grade inputs;
replay happens in order while both plugins are still paused. Each observer's
writes and journal deletion commit together. A short event lock protects the
final queue-empty check and reopening, without holding that lock during the
potentially slow file-table counts.

Deploy complete matching code packages in the normal maintenance window and stop
old plugin requests/workers before replacing PHP files. The pause cannot stop
arbitrary third-party code or direct database writes that bypass these entry
points. Do not downgrade, uninstall the plugins or change their configuration
while migration is pending.

### Progress, failure and retry

The task fetches up to 500 ascending item identities at a time and yields after
60 seconds between items, or at the batch boundary. Each item's conversion,
committed cursor and operation counts share one database transaction. A failed
item cannot advance the cursor; retries do not rescan the completed prefix. An
interrupted older synchronous migration's existing checkpoint is honoured. A
singleton Moodle lock prevents concurrent workers from migrating the same data.

No background task calls `upgrade_set_timeout()` or renews Moodle's global
`upgraderunning` lock. The normal task runner manages execution and retry; an
individual item is not given the former ten-minute upgrade lock. The 60-second
batch target is checked between transactions and cannot interrupt blocked
storage/database operations. Use supervised cron/CLI workers for large sites.

Keep Moodle cron enabled and running. A stopped cron leaves both plugins safely
paused, not half available. Progress, the last committed item, the last error and
a retry button are available under **Site administration > Plugins > Blocks >
Portfolio content migration**. The same page links to Moodle's native ad hoc task
queue and logs. After fixing only the cause, request a retry there or use the
normal task runner:

```sh
php admin/cli/adhoc_task.php --execute --classname='\block_exaport\task\migrate_item_content'
```

Use the CLI path from your Moodle release (on releases with a public document
root, the task entry point is still provided under the installation's CLI
administration directory). Native **Run all** / **Run again** can also be used by
administrators where Moodle's PHP CLI path is configured. These web-triggered
runs still depend on hosting request/process limits; recurring cron is the normal
continuation route. Continuations can be picked up by a subsequent cron run.

On an exception or killed worker, earlier items remain committed, the failed
item rolls back, and both plugins stay paused. Moodle retains the task for retry.
Do not reset versions, clear a live worker's lock or delete historical reports.
After confirming a process is dead, use Moodle's task recovery route; distributed
lock expiry/recovery follows the configured Moodle lock backend. Full rollback
requires the matched old database, complete Moodledata and old plugin code.
Replacing PHP alone is not rollback.

### Historical report

One aggregate report in `block_exaportmigration` describes **content conversion**.
The audit page and JSON download expose it read-only. New reports use format 5:
source counts at background-job start, cumulative committed operations across its
batches/retries, residuals and retained historical issue counts. The `resume`
section records an initial older synchronous checkpoint; operations before that
checkpoint are not invented or added to the background counters. Older completed
reports retain their original format and evidence, including format 4's
successful-attempt counters.

`status.clean` means counted legacy residuals and retained findings are zero,
excluding informational parent totals. Neither status flag proves readable file
bytes, correct relationships or application behaviour. Orphaned/wrong-context
legacy files remain for investigation; preserved attachment metadata is not a
residual. Reports remain immutable even if later repairs change the current
audit. A completed content report can precede the last deferred event: the
explicit pending state, rather than the report or installed version, determines
plugin readiness. The pause is cleared only after report commit and an empty
deferred-event queue.

The read-only audit never reruns migration or guesses duplicate content from
names, URLs or hashes. See also [structured content export](structured-content-export.md).

### File-reference verification and historical findings

The migration does not open backing files to calculate their size or checksum.
Moodle's File API creates destination records referencing the same stored content
hash. Every destination's context, component, file area, block, owner, filepath,
filename, repository-reference ID, content hash and recorded size are verified
before legacy records are removed. Database errors, unexpected backend errors
and destination verification failures abort and roll back the item's transaction.
A missing/unreadable local filedir root still stops migration as a storage outage.

This verifies preservation and placement of file references. It does not detect
missing bytes, read failures or corruption already present in backing storage,
and does not date any such damage discovered later. Known incorrect hashes or
sizes in source metadata are preserved rather than recalculated or repaired.
File API hooks and repository handling remain in place.

`block_exaportmigissue` retains administrator-only findings recorded by earlier
upgrade attempts that inspected source bytes. New attempts do not create these
storage-health findings. Existing rows are never cleared: original owner, item,
file ID, expected hash, reason, observation times and count remain historical
evidence. If a previously recorded source is migrated on retry, its exact
destination ID and copy time are still saved inside the item's transaction.
Rollback cannot leave a mapping to an uncommitted destination.

Format-4 reports include `issue_history_at_successful_run_end` across all attempts.
They cannot report a clean result just because a new attempt did not scan bytes.
Existing completed reports, including formats 1–3, are reused without modification.

### Finding an affected file after upgrade

Open **Site administration → Plugins → Blocks → exabis ePortfolio**, then
**Open audit** under **Item-content migration audit**. The **Migration issues**
button near the top of the audit page opens the read-only history at
`/blocks/exaport/item_content_migration.php`. This page
also includes findings from interrupted attempts that never saved a final report.
It shows history in pages of 100 rows: item/owner IDs, original and migrated file
IDs, expected content hash, reason, observation times/count and file-record copy
time. It offers no repair or retry actions and makes no claim about current file
health. Access requires a site administrator and the existing audit capability.

Reasons are `source_unreadable` (could not open the content), `source_read_failed`
(read failed or stalled), and `source_content_mismatch` (size or SHA-1 differed
from metadata). A missing destination ID/time means that the file-record copy has not committed,
for example because the upgrade stopped after recording the finding. The original file
row normally no longer exists after successful conversion; use the **migrated
file ID** to inspect its current metadata. For example, replacing the prefix and
ID as appropriate:

```sql
SELECT id, contextid, filearea, itemid, filepath, filename, contenthash, filesize
  FROM mdl_files
 WHERE component = 'block_exaport' AND id = 123;
```

If backing bytes were missing or damaged, investigate storage access and restore
the original bytes for the recorded content hash from a verified backup where
available. With standard local storage the path is
`moodledata/filedir/<hash[0:2]>/<hash[2:4]>/<full hash>`; custom/remote backends use
their own storage locations. **No migration rerun is needed**: restoring those
bytes or fixing access also repairs the migrated reference. Missing bytes cannot
be recreated by retrying a migration. If no backup exists, the finding documents
that loss while the rest of the item remains usable. Do not delete records or
create empty replacement files just to suppress findings.

Historical findings are retained after restoration, replacement, or normal item
deletion; they describe what the upgrade observed, not an outstanding work queue.
Owner-context privacy export includes the findings and approved erasure removes
them. Findings include no file contents, URLs, filenames or user names.

Task boundaries cannot override PHP-FPM, web-server, proxy, process-supervisor,
database or remote-storage limits. Regular cron or a supervised CLI task runner
is preferable to repeatedly starting the upgrade through a browser.

Legacy and imported File API paths are preserved, including nested directories.
Structured file links, thumbnails, shared views, and integration URLs include
that stored filepath when serving content. This is a runtime serving guarantee:
already migrated nested files require no repair, flattening, or remigration.

Back up both the database and Moodledata before upgrading. Run the audit before
upgrade where possible to record the legacy baseline, immediately after the
upgrade, after release validation, and before any future Phase 10B cleanup.

## Running the audit in Moodle administration

No shell or Bash access is required. Only Moodle site administrators can access
the audit page. Navigate to:

**Site administration → Plugins → Blocks → exabis ePortfolio**

On the Exaport settings page
(`/admin/settings.php?section=blocksettingexaport`), find **Item-content migration
audit** at the bottom. This entire row appears only when a completed report for
content-migration savepoint `2026101902` exists; it is hidden on fresh installations
with no migration history. Its read-only explanation appears in the left
settings column, with **Open audit** in the right column. The audit has no separate
administration-menu or administration-search entry; its existing access checks
still apply.

Visiting the page does not run the live audit. Choose an optional positive item ID, a
sample limit from 1 to 1000, and whether to show verbose bounded identifiers,
then select **Run audit**. Select **Run audit and download JSON** to receive the
same result as a privacy-safe JSON file. Both actions are authenticated Moodle
form submissions protected by a session key.

When a completed report exists for the content-migration savepoint, the page
displays it separately from the current database and file-reference checks.
The JSON download includes it. Otherwise both the settings entry and historical report section are
hidden; administrators can still open the audit page directly to run a live audit.
Fresh installations normally have no migration report, but
absence alone does not prove a fresh installation: an interrupted upgrade can
also leave no matching report. The report table is part of the schema on both fresh and
upgraded installations; its existence does not indicate migration completion.
The historical report explains what happened during migration; the live audit
checks current database relationships and file references. It does not read
backing file bytes or prove that attachments can be downloaded. The prominent
**Migration issues** button opens the historical file findings separately.
When retained findings exist, a warning above that button shows how many files
were affected during upgrade. It remains visible after files are restored.
The current checks report **Database and file-reference checks passed.** when no
structural findings are found. The JSON status `clean` has the same limited scope.
Historical file-copy counts describe
migrated file records and references, including records whose backing bytes were
already missing; they do not count successfully repaired or downloaded files.

An item filter restricts attributable
item, block, and file checks to that parent. Site-wide orphan checks are skipped
because an orphan cannot be safely attributed to the requested item. It never
silently expands a filtered audit to the full site.

JSON includes the audit format version, installed plugin version, UTC generation
time, status, severity totals, informational counts, findings, bounded IDs, and
the applied filter. Descriptions are supplemental; automation should use the
stable finding code and severity. Neither output mode includes URLs, filenames,
item titles, file contents, user names, or email addresses. Verbose mode adds
only bounded related record/context identifiers.

## Finding reference

| Code | Severity | Meaning |
|---|---|---|
| `legacy_url` | error | A meaningful URL remains on the parent item. |
| `legacy_url_sentinel` | warning | Whitespace or the exact historical `false` sentinel remains. |
| `legacy_item_file` | error | A non-directory `item_file` record remains. |
| `orphan_legacy_file` | error | A legacy file has no parent item. |
| `legacy_file_context_mismatch` | error | A legacy file is outside its owner's user context. |
| `orphan_content_block` | error | A block has no parent item. |
| `unsupported_block_type` | warning | A block type other than `text`, `link`, or `file` is retained for forward compatibility. |
| `empty_link_block` | warning | A link block has no usable URL. |
| `empty_file_block` | warning | A file block has no non-directory file. |
| `orphan_structured_file` | error | An `item_content_file` record has no block. |
| `structured_file_wrong_block_type` | error | An `item_content_file` belongs to a non-file block. |
| `orphan_text_file` | error | An `item_content_text` record has no block. |
| `text_file_wrong_block_type` | error | An editor file belongs to a non-text block. |
| `structured_file_context_mismatch` | error | A block file is outside the parent owner's context. |
| `text_file_context_mismatch` | error | An editor file is outside the parent owner's context. |
| `missing_owner_context` | error or warning | The user context is missing. It is an error when File API content claims to exist and a warning for database-only content. |
| `structured_owner_unresolved` | error | Reserved for an ownership relationship that cannot be resolved without ambiguity. Current primary-key joins expose concrete missing-parent/context cases under the more precise codes above, avoiding duplicate reports. |
| `unexpected_block_fields` | warning | A supported block carries content/URL fields not expected for its type. Titles remain valid for every type. |

Directory placeholders do not count as content. Unknown block types are warnings,
not corruption, because they may have been produced by a newer version. Empty
file blocks are warnings because an upload may simply have been abandoned.
Soft-deleted users are not findings when their retained user context is valid.

Every error identifies residual data that must be investigated before schema or
storage cleanup. Ordinary runtime paths do not present residual parent `url`,
`attachment`, or `item_file` content: structured blocks are authoritative even
when both representations remain on an item. Warnings require review and
explanation but do not automatically mean content loss. In
particular, never manually delete residual data before taking and preserving
database and Moodledata backups. Determine provenance and ownership first.

## Read-only and performance guarantees

Phase 10A produces evidence only. The service performs database reads, does not
load file bodies, does not calculate hashes, does not call File API mutation
methods, and checks the context table directly rather than calling a context API
that might create a context. It does not migrate, repair, delete, move, clear,
create, update timestamps/configuration, or offer a `--repair` option.

Counts use aggregate SQL. Samples use independently bounded queries. File
queries always constrain both component and file area, parent/context checks use
joins or `EXISTS`, and the implementation does not issue a context query per
file. The item filter is pushed into applicable SQL. Thus full counts remain
accurate while web/JSON output and sample memory are bounded; no file binary is
read. Phase 10B repair or cleanup requires separate design and review.

## Residual-data boundaries (Phase 10B checklist)

Residual legacy data is retained only at explicit migration, recovery,
compliance, and cleanup boundaries. It is not an ordinary-runtime fallback:

| Compatibility path | Source | Why retained now | Future Phase 10B review |
|---|---|---|---|
| Authorized legacy `item_file` pluginfile serving | `lib.php` | Previously issued, permission-checked file URLs can continue resolving while residual storage exists. | Remove the route only with a separately reviewed compatibility policy. |
| Upgrade conversion | `db/upgradelib.php` | Upgrade converts stored legacy sources. | Keep historical upgrade logic. |
| Migration audit | `classes/local/item_content_audit.php` | Administrators need privacy-safe evidence of residual data. | Retain until schema and storage cleanup is complete. |
| Legacy file deletion cleanup | `lib/lib.php` | Deletion must remove both modern and residual storage to avoid orphaned private files. | Retain until legacy serving/storage is conclusively retired. |
| Privacy export of residual `item_file` | `classes/privacy/provider.php` | Data-subject exports must include retained legacy user data. | Remove only when storage is absent and the privacy impact is reviewed. |

Renderers, thumbnails, item lists, copies, imports, external-service responses,
WordPress export, and SCORM export read structured blocks only. A residual
legacy value therefore cannot override, duplicate, or supplement structured
content. Privacy export intentionally remains broader because it must return
retained personal data, and deletion intentionally remains broader so it cannot
leave orphaned files.

### Web-service compatibility projection

The active item-bearing read services (`view_details` and
`get_all_user_items`) return the complete ordered structured data in
`contentblocks`. For compatibility with clients using the pre-migration shape,
they also derive the top-level `url` (the first non-empty ordered link block)
and `files` (all ordered file blocks flattened) from that same structured data.
This is a projection, not a runtime fallback: the parent `url` and `attachment`
columns and the `item_file` area are never read for these responses.

After an application refreshes its web-service data, structured files use
authorized `item_content_file` URLs. Token-authenticated requests receive
`/webservice/pluginfile.php` URLs containing the current token. Previously
cached `portfoliofile.php` URLs are intentionally outside this compatibility
guarantee. Clients should migrate to `contentblocks` to retain multiple ordered
links, text blocks, and distinct file groups; the legacy fields cannot express
that complete structure.
