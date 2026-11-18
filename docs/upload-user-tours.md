# Document-upload User tours

Exaport uses Moodle's native User tours tool to explain the document-upload
workflow. Both guides have two steps and use Moodle's native tour navigation and
End tour controls. They do not submit forms or upload files for the user.

* **Upload a document** appears on the page creation form. It points to
  **Content** and **Save and close**, explaining how to upload a document, apply
  the dialog and save the page to keep the upload.
* **Add documents through Content** appears in the page editor when the
  Content add button is available. It points to Content and the **+** button,
  explaining how to choose **File**, apply the upload dialog, then save the page.

Both guides explain that **Apply** updates the form while the main **Save** saves
the page, content and competencies together. Cancelling the main form discards
its pending changes. The icon image field remains a thumbnail field.

The guides apply across courses, including creation in Shared Categories. The
second guide is excluded when a page cannot be edited. Assignment imports,
read-only shared pages and other Exaport pages are outside their URL
matches. The permanent hint on the page creation form also explains where to
upload documents.

## Administration and completion

Manage the guides under **Site administration > Appearance > User tours**.
The native administration UI supports editing, disabling, reordering, exporting
and deleting guides, and adding role, course or theme restrictions. Exaport owns
its shipped definitions: a deliberate revision increase replaces that guide,
including any local changes. Standard Moodle tour priority applies when another
tour also matches the same page.

Moodle records completion separately for each guide and user. Completing the
creation guide does not complete the content guide. Users can replay a guide
with Moodle's **Reset user tour on this page** link. Administrators can reset a
guide for all users when they want to explain a significant change again.
English, German, Dutch and French text is supplied through Exaport's language strings.

## Registration and lifecycle

Installation and upgrade queue the native `register_user_tours` ad-hoc task to
synchronise the definitions in `db/usertours/`. Moodle's upgrade guidance requires
plugin API calls to be deferred until all plugin schemas are current. The task
imports new guides and replaces outdated guides after upgrade; the install and
upgrade callbacks only queue it.

Guides become available once cron successfully runs the queued task. Requests
from the page editor do not perform registration. Deferring the import also handles
a completely fresh Moodle installation, where blocks are installed before the User tours tool.
A lock serialises concurrent registration attempts. If it cannot be obtained,
the task throws Moodle's lock-timeout exception so the task runner retains it
for a retry.

The `db/usertours/` directory is Exaport's own organization for JSON data files;
Moodle does not automatically discover tours there. The list in
`classes/local/user_tours.php` gives each guide a stable identity, JSON filename
and independent revision. Exaport imports definitions through the public
`tool_usertours\manager::import_tour_from_json()` method.

Each installed guide stores `block_exaport_tour` and
`block_exaport_tour_version` in its existing `tool_usertours_tours.configdata`
field. There are no per-tour entries in `config_plugins` and no custom completion
table. Moodle owns the per-user completion and replay preferences. Both guides
are at revision 2 because users who completed the original two-step or
immediate-save workflow must see the new instructions again.

Synchronisation follows Moodle's own shipped-tour pattern:

* A missing guide is imported at its declared revision.
* A guide whose installed revision is current is left untouched, including its
  ID, steps, enabled state and user completion. Newer installed revisions are
  also retained, so deploying older code does not downgrade them.
* A guide whose installed revision is older is removed through Moodle's API and
  reimported from its JSON file. This replaces its local changes and clears only
  that guide's completion preferences, allowing users to see the revised guide.

The transaction covers removals, imports and the quick-check marker. A single
`usertourssignature` value in `config_plugins` records a hash of the declared tour
list and revisions. It lets repeated task executions skip the synchronisation work.
The hash changes automatically when a guide is added or its revision increases;
there is no second version number to bump manually. Missing guides, including
ones deleted locally, are recreated on the next catalogue synchronisation.
Unrelated tours are left alone. Uninstalling Exaport removes only the guides
bearing its known identity markers and clears the signature.

Failed synchronisation explicitly rolls back before releasing the registration
lock. Existing guides, their completion preferences and the previous signature
are retained, so a repaired deployment can be retried safely.

## Adding and updating guides

For a new guide, add its JSON definition and language strings, and add an entry
to `user_tours::TOURS` with a new stable identity and `version` set to `1`.

To deliberately replace an existing guide and show it again, edit its JSON and
increase only that guide's `version`. For example:

```php
private const TOURS = [
    'upload-create' => ['file' => 'create_item.json', 'version' => 2],
    'upload-content' => ['file' => 'add_file.json', 'version' => 3],
];
```

Here only the content guide is replaced on existing installations. The creation
guide and its completion remain unchanged. Fresh installations receive the
current definition of both guides.

For either change, bump the plugin version and add the normal upgrade savepoint
that queues `\block_exaport\task\register_user_tours` with
`\core\task\manager::queue_adhoc_task($task, true)`. The upgrade step must not
call the User tours API directly. The task always reads the current tour list,
so an installation that skips releases receives all current definitions.

For a wording-only correction that does not need to replay a guide, edit the
language strings without increasing its tour revision. The stored steps reference
those strings, so Moodle resolves the updated text through its normal language
cache. Changes to JSON selectors, steps or URL filters require a revision increase
to update already-installed guides.

## Moodle references

* [User tours: configuration, URL matching, CSS targets and JSON import/export](https://docs.moodle.org/405/en/User_tours)
* [Public User tours import API](https://phpdoc.moodledev.io/4.5/db/de3/classtool__usertours_1_1manager.html)
* [Moodle core's per-tour revisions and replacement of shipped tours](https://github.com/moodle/moodle/blob/MOODLE_405_STABLE/admin/tool/usertours/classes/manager.php#L855-L935)
* [Upgrade restrictions and deferring plugin API calls](https://moodledev.io/docs/4.5/guides/upgrade#upgrade-code-restrictions)
* [Ad-hoc task API and duplicate task detection](https://moodledev.io/docs/4.5/apis/subsystems/task/adhoc)
* [Plugin lifecycle files](https://moodledev.io/docs/4.5/apis/commonfiles)
* [Lock API](https://moodledev.io/docs/4.5/apis/core/lock)
* [Delegated transactions](https://moodledev.io/docs/4.5/apis/core/dml/delegated-transactions)
