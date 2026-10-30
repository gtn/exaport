# Item-content migration and read-only audit

## Storage transition

### Optional Exacomp integration

Exaport can be installed without Exacomp. If Exacomp code is present, installing
or upgrading this Exaport release requires Exacomp **2026101903** or later.
The upgrade checks this before any migration or upgrade savepoint. The install
callback also checks it, including recovery of an interrupted installation.
Moodle creates the fresh installation's tables before calling that callback;
keep the site in maintenance mode until installation completes successfully.

Update Exacomp first, then Exaport, or deploy both together. The check reads the
Exacomp version on disk so it accepts a combined deployment even before Moodle
has recorded Exacomp's new installed version. New Exacomp rejects content calls
until Exaport's structured-content migration has completed, so completed updates
can be hours apart in that order. A failed upgrade does not restore deployed PHP
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
| Completed content-migration savepoint | `2026101902` |
| Final Exaport plugin version | `2026103001` |
| Finding-history schema savepoint | `2026103001` |
| Historical migration report identifier (`migrationversion`) | `2026101902` |

The report identifier matches the content-migration savepoint and identifies
that migration rather than the final installed plugin version. Once released,
keep this migration's identifier fixed when later plugin versions increase so
its completed reports remain discoverable.

The content migration at savepoint **2026101902** migrates meaningful legacy URLs
and non-directory legacy files. Its expected clean postcondition is:

* the parent `url` column is empty;
* no non-directory files remain in `item_file`; and
* each migrated URL/file has a corresponding structured link/file block.

Retained attachment metadata is not a migration residual and does not prevent a
clean result. Meaningful parent URLs are cleared after copying their exact values
to link blocks; actual files are independently copied and verified through the
File API. These operations do not require erasing the attachment marker.

The same upgrade stores one privacy-safe aggregate report in
`block_exaportmigration`. The report is available on the audit page and is not
printed during the upgrade. It records source counts at the start of the
successful run, operations completed by that run, residual counts at its end,
and timing.
In format version 4, `status.clean` means that counted legacy residuals and
recorded content findings are zero (the informational `total_items` count is
excluded). `status.requires_review` is true when any such evidence exists. It
remains true in the historical report even if backing storage is repaired later. Neither flag proves that application flows or
all structured-data relationships are correct; continue release validation and
the read-only audit. Residual files, including orphans or files outside the
owner's context, remain untouched for investigation. Preserve backups and
review their provenance and ownership before deciding on any manual correction;
the report does not move, delete, deduplicate, or guess ownership.
The administration report displays these flags as **Yes** (true) or **No** (false)
under a **Value** heading; numeric migration statistics retain their **Count**
heading. The stored report and JSON download retain boolean values for the flags.
The unique report identifier prevents duplicate completed reports. The report
contains no item IDs, owner IDs, URLs, filenames, titles, user details, or file
contents.

New reports use format version 4. They retain the format-2 omission of attachment-cleanup counters and include aggregate finding-history counts across all attempts. The migration
identifier remains 2026101902. Existing reports, including their
historical attachment counts, remain readable and are not rewritten. Values
already erased by an earlier build cannot be reconstructed from these aggregate
reports; this change preserves values that still exist.

The upgrade is restart-safe and remains in the historical upgrade code. The
audit does not re-run it and deliberately does not compare URLs, filenames, or
content hashes to guess whether content is duplicated. See also
[structured content export](structured-content-export.md).

The migration fetches at most 500 items at a time using ascending-ID keyset
pagination. Immediately before processing each non-empty batch it gives that
batch a fresh 30-minute (1,800-second) Moodle timeout. There is no fixed
batch-count limit or total duration limit, so a healthy migration may continue
for many hours while still retaining a finite application-level guardrail for
each batch. Aggregate, privacy-safe progress is reported after every completed
batch.

Each item commits in its own delegated transaction. If, for example, a later
batch fails, items completed in earlier batches remain committed, but the
2026101902 content-migration savepoint and final migration report remain unapplied.
The next Moodle upgrade attempt scans again from ID zero. Completed items have
empty legacy sources and are harmless no-ops; the first incomplete item is retried
from its surviving legacy source records.

### Nonblocking file migration and historical findings

Each source file is inspected once before content writes. The migration reads
its bytes in bounded chunks and compares actual size and SHA-1 against its File
API metadata. A known open/read failure or mismatch is documented immediately,
then its **file record is migrated anyway**, retaining the expected content hash,
filename, nested filepath, size and other source metadata. Backing bytes are
neither rewritten nor replaced with empty content. Healthy sibling files and
links migrate normally; there is no whole-item deferral, editing lock, or repair
queue. Readable corruption is documented just like missing/unreadable content.

Every destination record is checked for the intended context, component, file
area, block, owner, content hash and size before legacy records are removed.
The destination references the same backing content hash; no second or third
full byte read is necessary for this metadata migration. Database errors,
unexpected backend exceptions and destination-record verification failures still
abort and roll back the item's content transaction. A missing/unreadable local
filedir root is treated as a storage outage and stops migration; custom storage
backends must surface outages as exceptions. Known bad source bytes are never
mistaken for new destination-record failures.

`block_exaportmigissue` retains administrator-only evidence independently of the
completed aggregate report. It is created **before** the historical 2026101902
migration, including interrupted upgrades. Savepoint 2026103001 also installs it
on completed sites. Findings are keyed by migration, parent item, original file ID
and reason. They retain owner ID, expected content hash, first/latest observation
times and observation count. Repeated observations update the same finding;
starting another upgrade attempt never clears previously committed evidence.

A finding is committed before content writes. The exact destination file ID and
copy time are subsequently saved **inside the same transaction as the new file
record**. Thus a later rollback preserves the finding with no uncommitted target
ID; a crash after copying preserves both the file record and its mapping. Recovery
never depends on guessing file identities from matching hashes or names. Attempts
inside an enclosing transaction are refused when independent finding commits
cannot be guaranteed. A crash before an observation itself commits may require
that observation to be rediscovered on the next scan.

The final format-4 report includes both `files_with_content_issues` for its own
successful attempt and `issue_history_at_successful_run_end` for **all attempts**.
It cannot report a clean result just because an earlier attempt already migrated
a broken file and then crashed. Existing completed reports remain immutable.

### Finding an affected file after upgrade

Open **Site administration → Plugins → Blocks → exabis ePortfolio → Migration
issues**, or `/blocks/exaport/item_content_migration.php`. This read-only page
also includes findings from interrupted attempts that never saved a final report.
It shows history in pages of 100 rows: item/owner IDs, original and migrated file
IDs, expected content hash, reason, observation times/count and file-record copy
time. It offers no repair or retry actions and makes no claim about current file
health. Access requires a site administrator and the existing audit capability.

Reasons are `source_unreadable` (could not open the content), `source_read_failed`
(read failed or stalled), and `source_content_mismatch` (size or SHA-1 differed
from metadata). A missing destination ID/time means that copy did not commit or
no destination was recorded by an earlier development build. The original file
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
them. Findings include no file contents, URLs, filenames or user names. The new
savepoint automatically finishes recorded items deferred by the earlier
2026103000 development build, preserves its historical report, and retains its
finding timestamps/IDs. No manual recovery workflow is required.

Renewing Moodle's PHP timeout is only an application-level guardrail. It cannot
guarantee interruption of every blocked external operation or override limits
from PHP-FPM, Apache or another web server, reverse proxies, process supervisors,
containers, database servers, network or remote/object storage, or operating-
system process limits. Administrators should run large upgrades through a
supervised CLI upgrade where possible.

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
with no migration history. Its label and read-only explanation appear in the left
settings column, with **Open audit** in the right column. The audit has no separate
administration-menu or administration-search entry; its existing access checks
still apply.

Visiting the page does not run the live audit. Choose an optional positive item ID, a
sample limit from 1 to 1000, and whether to show verbose bounded identifiers,
then select **Run audit**. Select **Run audit and download JSON** to receive the
same result as a privacy-safe JSON file. Both actions are authenticated Moodle
form submissions protected by a session key.

When a completed report exists for the content-migration savepoint, the page
displays it separately from the current integrity audit and the JSON download
includes it. Otherwise both the settings entry and historical report section are
hidden; administrators can still open the audit page directly to run a live audit.
Fresh installations normally have no migration report, but
absence alone does not prove a fresh installation: an interrupted upgrade or an
earlier experimental report stored under a different identifier can also leave
no matching report. The report table is part of the schema on both fresh and
upgraded installations; its existence does not indicate migration completion.
The historical report explains what happened during migration; the live audit
remains authoritative for the installation's current state.

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
