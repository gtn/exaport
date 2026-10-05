# Item-content migration and read-only audit

## Storage transition

Before the structured-content transition, an item stored its link in
`block_exaportitem.url`, its historical attachment marker in
`block_exaportitem.attachment`, and uploaded content in the
`block_exaport/item_file/<item id>` File API area. Structured content is now an
ordered set of `block_exaportitemblock` rows of type `text`, `link`, or `file`.
File blocks use `item_content_file/<block id>` and embedded text-editor files use
`item_content_text/<block id>`, both in the parent item's owner user context.

Upgrade **2026092900** migrated meaningful legacy URLs and non-directory legacy
files. Its expected clean postcondition is:

* the parent `url` and `attachment` columns are empty;
* no non-directory files remain in `item_file`; and
* each migrated URL/file has a corresponding structured link/file block.

The same upgrade stores one privacy-safe aggregate report in
`block_exaportmigration`. It records source counts at the start of the successful
run, operations completed by that run, residual counts at its end, and timing.
The unique migration version prevents duplicate completed reports. It contains
no item IDs, owner IDs, URLs, filenames, titles, user details, or file contents.

The upgrade is restart-safe and remains in the historical upgrade code. The
audit does not re-run it and deliberately does not compare URLs, filenames, or
content hashes to guess whether content is duplicated. See also
[structured content export](structured-content-export.md).

Upgrade **2026100500** is a post-migration verification step for development
installations that had already passed 2026092900 (the last such published
development version was 2026092910). It records a new aggregate snapshot with
an explicit `clean`/`requires_review` result. It never returns early because a
2026092900 report exists, never reruns conversion, and never guesses that a URL,
filename, or hash makes two representations equivalent. Fresh installs create
the current schema directly and therefore have no historical migration report;
their live audit is the authoritative check.

The migration fetches at most 500 items at a time using ascending-ID keyset
pagination. Immediately before processing each non-empty batch it gives that
batch a fresh 30-minute (1,800-second) Moodle timeout. There is no fixed
batch-count limit or total duration limit, so a healthy migration may continue
for many hours while still retaining a finite application-level guardrail for
each batch. Aggregate, privacy-safe progress is reported after every completed
batch.

Each item commits in its own delegated transaction. If, for example, a later
batch fails, items completed in earlier batches remain committed, but the
2026092900 upgrade savepoint and final migration report remain unapplied. The
next Moodle upgrade attempt scans again from ID zero. Completed items have empty
legacy sources and are harmless no-ops; the first incomplete item is retried
from its intact legacy source.

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
During migration, source and destination streams are both opened and digested,
and their actual byte counts are checked before the legacy area is deleted.
This one-off check proves that backing bytes are retrievable without adding file
reads to normal rendering or download requests.

Back up both the database and Moodledata before upgrading. Run the audit before
upgrade where possible to record the legacy baseline, immediately after the
upgrade, after release validation, and before any future Phase 10B cleanup.

## Running the audit in Moodle administration

No shell or Bash access is required. Only Moodle site administrators can access
the audit page. Navigate to:

**Site administration → Plugins → Blocks → Item-content migration audit**

Visiting the page does not run a query. Choose an optional positive item ID, a
sample limit from 1 to 1000, and whether to show verbose bounded identifiers,
then select **Run audit**. Select **Run audit and download JSON** to receive the
same result as a privacy-safe JSON file. Both actions are authenticated Moodle
form submissions protected by a session key.

The page displays the stored historical report separately from the current
integrity audit. The JSON download includes both sections. The historical report
explains what happened during migration; the live audit remains authoritative
for the installation's current state.

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
| `legacy_attachment` | warning | Non-empty historical attachment metadata remains; it is not interpreted as a File API ID. |
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

## Supported upgrade and recovery procedure

The supported direct source for this experimental rework is **2026091001**.
Upgrades from that baseline run schema creation (2026091602), migration
(2026092900), and post-migration verification (2026100500) in order. Versions
between those savepoints, including deployed development version 2026092910,
resume at the first unapplied step. Older production Exaport installations use
the existing historical upgrade chain to reach 2026091001 first; they must be
tested from the exact locally deployed version and Moodle release. Moodle 4.2
or later is required. A fresh install is a separate path through `install.xml`
and does not exercise migration.

Before any upgrade or recovery, take a mutually consistent database and
Moodledata backup. A database-only rollback can point restored file records at
deleted bytes, while a Moodledata-only rollback cannot restore the corresponding
rows. To roll back, restore **both** backups from the same point and deploy the
matching older plugin code; there is no downgrade routine.

After upgrade, run the site-wide audit and retain its JSON. `clean` means no
current finding; `warning` requires an explanation; `error` blocks treating the
migration as clean. For residual data:

1. Stop writes and preserve the matching backups and audit JSON.
2. Use bounded item/file/block IDs from verbose audit output to establish the
   original owner and context. Never infer ownership from a filename, URL, or
   content hash.
3. If an item contains both structured and legacy representations, compare it
   with the pre-upgrade backup and user-visible intent. Do not rerun the report-
   driven migration: its completed report is evidence, not a repair command.
4. For wrong-context or orphan files, use a reviewed, site-specific Moodle File
   API script to copy (not move) the record into an explicitly confirmed owner,
   item and block. Verify streamed bytes and application access before deleting
   the source. Missing users/contexts require an ownership decision; the plugin
   deliberately does not create a user context or guess a replacement owner.
5. Rerun the audit for the affected item and then site-wide. Remove preserved
   sources only after review, byte/download verification, and a fresh backup.

An interrupted 2026092900 run is different from a prior or manual conversion.
The former retains its source transactionally and can be retried; the latter may
already contain both representations and must follow the controlled review above.

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
| Upgrade and restore conversion | `db/upgradelib.php`, `backup/moodle2/restore_exaport_stepslib.php` | Upgrade converts stored legacy sources; restore converts legacy backup data after restoration. | Keep historical upgrade logic and supported old-backup conversion. |
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
