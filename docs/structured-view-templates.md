# Structured view templates

Course trainers can open **Preset structure → Collection distribution → Edit
structure** for a view template. The editor supports ordered headings and
instructions, short or multiline response fields, artefact slots and nested
sections. Root elements can be assigned to one of up to five columns. Move,
duplicate and delete controls work without JavaScript. Each structure action
saves the current field edits.

Sections can be repeatable, with an initial minimum of one to ten occurrences.
Learners can add blank occurrences and remove occurrences above that minimum.
Removing an occurrence detaches its artefacts from the collection; the artefacts
remain in the learner's personal portfolio.

Optional examples for an internship overview, midpoint reflection and final
reflection are stored in `presets/view_structures.json`. These are ordinary
configurations: their labels, counts, questions and allowed media can all be
changed. The final example includes a video slot. The core model has no
internship- or competency-specific element types.

## Distribution and editing

Manual distribution and enrolment distribution use the same code. A template's
validated definition becomes independent records in `block_exaportviewblock`.
Configuration is stored separately from response text and artefact IDs.
Logical `templatekey` and `parentkey` values preserve grouping independently of
database IDs. The existing free-layout editor continues to handle additional
content, while retaining these structured records unchanged.

Editing a distributed collection opens its fill-in form. Learners can save
incomplete work, upload files and write reflections directly, and use **Save
and preview** to open the normal shared-view renderer. **Add other content**
opens the existing free-layout editor. Text responses are plain text; file slots
use Moodle draft areas, upload limits and the existing Exaport quota checks.
An artefact is created only when its slot receives a file or reflection.

Uploaded files and reflections are normal, learner-owned structured artefact
content. Filled slots use ordinary `item` view blocks, so view grants and file
delivery retain their existing item-in-view checks. Unfilled slots survive file
and artefact removal. Browser output and PDF output read the same structured
responses. Single-column guided PDFs use block flow to support long reflections
across pages.

Distribution is identified by course, template ID and learner, protected by a
lock and unique index. Renaming a collection does not cause another copy to be
distributed, and identical titles in different courses do not collide. Teacher
sharing resolves the actual course even from an enrolment task.

Template edits affect future copies. Existing copies retain their definition,
responses and source revision. Pre-existing collections without template
provenance are not matched by title or overwritten; distributing an updated
template creates a new identified copy. Course backups include structure and
revision, but continue to exclude personal responses and artefacts.

## Storage and validation

Plugin version `2026110500` adds template structure/revision, view provenance,
and block keys/configuration. Run Moodle's normal plugin upgrade before using
the new screens. Existing templates without content remain valid.

Definitions use format version 1 and contain `columns` and ordered `nodes`.
Nodes use `heading`, `field`, `artefact` or `section`, with stable definition
keys. Section children are definitions, allowing repeated instances to start
blank. Definitions are bounded to 200 elements, five nesting levels and 200
initial expanded elements. Runtime repetition is also bounded to 200 elements.
Imported definitions cannot carry learner responses or item IDs. Complete
starter imports are validated before replacing a course's current templates.

The editor requires the existing course distribution capability. The response
screen requires ownership of the view. Both use POST/session-key checks; stale
form submissions are rejected. Upload type/count limits are checked on the
server as well as in the file picker. Definition snapshots and response text
are included in privacy export, and normal view deletion removes their records.

`tests/view_structure_test.php` covers identity, snapshots, repeated sections,
empty slots, file-type validation, ordinary artefact/file output, access scope,
deletion, teacher sharing and invalid imports. Course backup tests also check
the structure and revision after restore.
