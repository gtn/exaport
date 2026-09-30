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

Every error is a release blocker for removal of legacy compatibility fallbacks
and should be investigated before shipping migration cleanup. Warnings require
review and explanation but do not automatically mean content loss. In
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

## Retained read-only compatibility inventory (Phase 10B checklist)

The first migration release intentionally retains these paths:

| Compatibility path | Source | Why retained now | Future Phase 10B review |
|---|---|---|---|
| Legacy URL and `item_file` rendering | `classes/output/item_content_blocks.php`, `lib/reportlib.php` | Items missed by upgrade must remain viewable in normal/shared/PDF output. | Remove only after audits and release evidence establish no residual content. |
| Legacy thumbnail selection | `lib/lib.php` and thumbnail consumers | Residual legacy images still need previews. | Remove legacy candidate lookup after blocker-free qualification. |
| Legacy `item_file` pluginfile serving | `lib.php` | Existing URLs and shared content must continue resolving. | Remove the file-area route only with a separately reviewed compatibility policy. |
| Legacy export fallbacks | `classes/externallib/externallib.php`, `classes/wp_integration.php`, `lib/lib.exaport.php`, `lib/reportlib.php` | Integrations must not omit anomalous residual content. | Remove fallback projection after every supported export path is validated. |
| Compatibility copy of residual content | `lib/lib.php` | Copying an anomalous old item must not lose its remaining file. | Remove after residual storage is resolved, without changing structured copying. |
| Legacy file deletion cleanup | `lib/lib.php` | Deletion must remove both modern and residual storage to avoid orphaned private files. | Retain until legacy serving/storage is conclusively retired. |
| Privacy export of residual `item_file` | `classes/privacy/provider.php` | Data-subject exports must include retained legacy user data. | Remove only when storage is absent and the privacy impact is reviewed. |
| Historical migration implementation | `db/upgradelib.php`, `db/upgrade.php` | Existing sites may still upgrade through 2026092900. | Keep historical upgrade code; Phase 10B must not rewrite an already shipped upgrade. |

These fallbacks are read compatibility and lifecycle safeguards, not permission
to continue legacy writes. Phase 10A removes none of them and does not change
normal item UI, imports, exports, copies, deletion, or pluginfile behaviour.
