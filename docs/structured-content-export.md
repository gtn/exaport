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

## Compatibility limitation

The SCORM package remains compatible with the legacy marker importer, but this
phase does not add a structured sidecar. All structured content is visible and
all structured files are packaged; however, reimporting the package reconstructs
only the legacy marker subset and cannot reconstruct arbitrary mixed block order,
block titles, or rich-text editor-file relationships. The export therefore makes
no claim of lossless structured round-trip support.

The labels for XAPI, EPX, and directory export remain in language resources,
but the current user-facing import/export page links only to the SCORM exporter
and WordPress integration. No separate active serializer for those labels was
found, so inactive EPX schema assets and labels are unchanged.

External web-service responses retain established legacy URL/file fields as
neutral values and expose ordered structured content separately. Residual parent
values and `item_file` files are never projected into those fields. Moodle
Portfolio API code imports files into Exaport or redirects files to another
portfolio; it is not a separate serializer of a complete Exaport item.
