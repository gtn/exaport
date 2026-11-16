# Document-upload User tours

Exaport uses Moodle's native User tours tool to explain the document-upload
workflow. Both guides have two steps and use the normal Next, Back and End tour
controls. They do not submit forms or upload files for the user.

* **Create an object to upload a document** appears on the add-object page. It
  points to the title and **Save and keep editing**, explaining that documents
  are added after the object has been saved.
* **Add documents through Content** appears on the edit-object page when the
  Content add button is available. It points to Content and the **+** button,
  explaining how to choose **File** and save the upload dialog.

The guides apply across courses, including creation in Shared Categories. The
second guide is excluded when an object cannot be edited. Assignment imports,
read-only shared-item pages and other Exaport pages are outside their URL
matches. The permanent hint on the new-object form also explains where to
upload documents.

## Administration and completion

Manage the guides under **Site administration > Appearance > User tours**.
Administrators can edit, disable, reorder, export or delete them and add their
own role, course or theme restrictions. Standard Moodle tour priority applies
when another tour also matches the same page.

Moodle records completion separately for each guide and user. Completing the
creation guide does not complete the content guide. Users can replay a guide
with Moodle's **Reset user tour on this page** link. Administrators can reset a
guide for all users when they want to explain a significant change again.
English and German text is supplied through Exaport's language strings.

## Registration and lifecycle

Installation and upgrade queue one native ad-hoc task to register the definitions
in `db/usertours/`. Moodle's upgrade guidance requires plugin API calls to be
deferred until all plugin schemas are current. The task imports the guides after
upgrade; the install and upgrade callbacks only queue it.

If cron has not run yet, the first Exaport item-page request completes registration
before Moodle renders the header and selects a tour. This also handles a completely
fresh Moodle installation, where blocks are installed before the User tours tool.
A lock protects concurrent requests. The queued task then becomes a no-op.

The `db/usertours/` directory is Exaport's own organization for JSON data files;
Moodle does not automatically discover tours there. Exaport imports them through
the public `tool_usertours\manager::import_tour_from_json()` method.

Registration is transactional. A plugin configuration revision prevents later
requests, installation recovery or ordinary upgrades from recreating deleted
guides or resetting completion. Stable `block_exaport_tour` markers in the tour
configuration also prevent duplicate registration after a JSON import or an
interrupted setup. Registration preserves existing guide text, enabled states,
filters and steps.

The JSON files can be imported manually through Moodle's User tours interface.
Adding a new release guide should use a new identity and an explicit upgrade
step; existing site-customized guides should be retained. Uninstalling Exaport
removes only the guides bearing its known identity markers.

## Moodle references

* [User tours: configuration, URL matching, CSS targets and JSON import/export](https://docs.moodle.org/405/en/User_tours)
* [Public User tours import API](https://phpdoc.moodledev.io/4.5/db/de3/classtool__usertours_1_1manager.html)
* [Upgrade restrictions and deferring plugin API calls](https://moodledev.io/docs/4.5/guides/upgrade#upgrade-code-restrictions)
* [Ad-hoc task API and duplicate task detection](https://moodledev.io/docs/4.5/apis/subsystems/task/adhoc)
* [Plugin lifecycle files](https://moodledev.io/docs/4.5/apis/commonfiles)
* [Lock API](https://moodledev.io/docs/4.5/apis/core/lock)
* [Delegated transactions](https://moodledev.io/docs/4.5/apis/core/dml/delegated-transactions)
