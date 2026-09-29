# Structured item content export

Exaport exports an item as one parent artefact. During the legacy transition its
presentation order is item intro, legacy URL, legacy `item_file` files, and then
structured blocks ordered by `sortorder ASC, id ASC`. Legacy and structured
values are not deduplicated: matching URLs or filenames can represent distinct
user-created content.

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
* WordPress view export keeps its existing fields and adds an ordered
  `structured_blocks` array. Structured and legacy files use the existing
  multipart upload contract; editor-file entries retain their placeholder path.
* Moodle privacy export retains block records and exports both
  `item_content_file` and `item_content_text` file areas per block.

## Compatibility limitation

The SCORM package remains compatible with the legacy marker importer, but this
phase does not add a structured sidecar. All content is visible and all files
are packaged; however, reimporting the package reconstructs only the legacy
marker subset and cannot reconstruct arbitrary mixed block order, block titles,
or rich-text editor-file relationships. The export therefore makes no claim of
lossless structured round-trip support.

The labels for XAPI, EPX, and directory export remain in language resources,
but the current user-facing import/export page links only to the SCORM exporter
and WordPress integration. No separate active serializer for those labels was
found, so inactive EPX schema assets and labels are unchanged.

The external web-service item response still exposes its established legacy
URL/file shape. Adding a versioned blocks response requires an external-service
contract/version change and remains a migration blocker if clients claim that
endpoint is a complete-content representation. Moodle Portfolio API code found
in this phase imports files into Exaport or redirects files to another portfolio;
it is not a separate serializer of a complete Exaport item.
