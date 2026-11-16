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

Installation and upgrade register the definitions in `db/usertours/` once.
During a completely fresh Moodle installation, blocks are installed before the
User tours tool. Registration is then completed on the first Exaport item-page
request, before Moodle renders the header and selects a tour. A lock protects
concurrent first requests.

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
