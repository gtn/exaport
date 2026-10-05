# Structured item content export

Exaport exports an item as one parent artefact. Structured blocks are the sole
runtime content authority and are ordered by `sortorder ASC, id ASC`. Residual
parent `url`, `attachment`, and `item_file` data is not rendered or serialized,
even when it exists on the same item as structured content. The parent intro
remains item metadata/description and is exported where the format supports it.

## Active serializers

* The SCORM/package exporter packages structured text, link, and file blocks.
  File-block assets use
  `items/<item id>/blocks/<block id>/<filepath>/<filename>` and editor assets use
  the same block directory with an `editor` segment. Unsafe path components are
  normalized and a deterministic numeric suffix prevents archive collisions.
  Generated pages and all assets are listed by their manifest resource.
  Each item page also contains an `EXAPORT_ITEM_CONTENT_V1` comment pointing to
  `items/<export item id>/content.json`. The JSON sidecar has the fixed format
  name `exaport-item-content`, integer version `1`, parent `type` and `intro`,
  and an ordered `blocks` array. A block stores `type`, `sortorder`, `title`,
  `content`, `contentformat`, `url`, and separate `files` and `textassets`
  arrays. Each asset records its original File API `filepath` and `filename`
  plus its package-root-relative `archivepath`. Export database IDs are used
  only to allocate archive directories; they are not identities in the JSON.
* Text is cleaned according to its stored Moodle format. Text-editor files are
  packaged and matching `@@PLUGINFILE@@` references are changed to relative
  archive URLs. An unreferenced editor file is still packaged, so it remains
  available even when rich-text markup cannot display it.
* WordPress view export keeps its existing fields with neutral legacy URL/file
  values and adds an ordered `structured_blocks` array. Structured files use the
  existing multipart upload contract; editor-file entries retain their
  placeholder path.
* Moodle privacy export retains block records and exports both
  `item_content_file` and `item_content_text` file areas per block. Unlike an
  ordinary serializer, it also exports residual `item_file` data because that
  retained personal data remains in scope for a data-subject request.

## Import and compatibility policy

New packages are identified explicitly by the versioned page marker. Their
sidecars are authoritative even if compatibility description markup is also
present, so each item is imported exactly once. Import allocates a new parent
and new block IDs, then restores file and embedded-text assets under those new
block IDs. It preserves block array order and stored sort orders, titles,
content formats, multiple links, multiple files, nested paths, and duplicate
filenames in distinct blocks or paths. The parent URL and attachment stay empty
and no `item_file` files are created. The HTML pages continue to render the same
links, files, and rewritten embedded assets for the package's SCORM/viewing use.

Before any blocks are written, the importer validates the format/version, block
shape, logical File API paths, archive paths, and the existence of every required
asset. Absolute paths, traversal, malformed metadata, unsupported versions, and
missing assets fail the item import. Parent, blocks, files, category association,
comments, and supported competence links share a transaction, preventing a
half-created item.

Packages without the structured marker follow the legacy marker importer.
Legacy `BOOKMARK_EXT_URL`, `BOOKMARK_FILE_URL`, and description markers remain
supported and are converted directly to link/file blocks, including all file
markers accepted by the old format. Thus old exports remain importable, while
new packages intentionally do not populate legacy parent URL/attachment fields
or the `item_file` area and are not promised to work in old Exaport versions.

The labels for XAPI, EPX, and directory export remain in language resources,
but the current user-facing import/export page links only to the SCORM exporter
and WordPress integration. No separate active serializer for those labels was
found, so inactive EPX schema assets and labels are unchanged.

External web-service responses retain established legacy URL/file fields as
neutral values and expose ordered structured content separately. Residual parent
values and `item_file` files are never projected into those fields. Moodle
Portfolio API code imports files into Exaport or redirects files to another
portfolio; it is not a separate serializer of a complete Exaport item.
