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
