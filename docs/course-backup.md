# Course backup scope

Moodle course backup and restore include Exaport's course category templates,
view templates, and distribution settings. They do not include personal portfolio
items, their content blocks, or their files, even when **Include users** is enabled.
Copying or restoring a course therefore does not create additional items in users'
portfolios. This preserves the course backup scope from before the item-content
rework; personal portfolio backup is a separate feature.

These course settings belong to the course rather than to a particular Exaport
block instance. Only the instance with the lowest `block_instances.id` serializes
them, so a course with multiple Exaport blocks restores each setting/template once.

The item-content migration and Exaport's own portfolio export/import are separate
from Moodle course backup and restore.

## Naming the root of a copied category template

Administrators can enable **Allow course-based naming of copied templates** under
Exaport's **Structure distribution** settings. The feature is off by default.
The configurable pattern defaults to `Ausbildungsdokumente {coursefullname}`;
`{coursefullname}` is replaced with the destination course's full name. An empty
pattern uses that default. The existing category-name limit is 255 characters;
longer generated names are shortened to fit, including multibyte names.

A course manager then enables **Name the first template root after the destination
course when copying** in **Structure distribution**. This opt-in is backed up and
copied with the course. Saving it does not rename the current template.
This uses the existing block backup: the source course needs an Exaport block for
its templates and distribution settings to be included in Moodle's course backup.

During copy/restore finalization, the first top-level category inserted from that
backup is renamed. "First" follows template display order (`sortorder`, with the
source category ID breaking ties). Other roots, descendants, ordering and teacher
sharing settings are preserved. Existing destination template nodes are excluded,
including when restoring into an existing course. Source templates, personal
portfolio categories, content and sharing assignments are never renamed or edited
by this operation. Distribution to students continues through the existing paths.

Later changes to a course's name do not update the copied template automatically.
Copying that course again resolves the name for the next destination. Backups
without the new opt-in retain their original category names. Disabling the global
feature preserves saved course opt-ins, while preventing automatic naming and
changes to the course option until the administrator enables it again.
