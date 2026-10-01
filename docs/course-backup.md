# Course backup ownership

Exaport portfolio items, their structured content, course templates, view templates, and
distribution settings are scoped to a course rather than to an individual block instance.
Moodle nevertheless runs a block backup task for every Exaport instance on the course.

Only the Exaport instance with the lowest `block_instances.id` in the course emits this shared
data. Other instances emit an empty Exaport plugin structure while retaining Moodle's ordinary
block-instance backup. Choosing an owner from persistent instance ids, rather than choosing the
first task that executes, makes the result independent of backup task processing order. A normal
course backup includes all of the course's block instances, including the elected owner.

Restore consequently inserts each personal and course-scoped record once. In particular, restore
does not insert duplicate portfolio data and then attempt to deduplicate it. Legacy item fields are
converted only after that item's files and structured blocks have been restored, so one legacy URL
and attachment produce one link block and one file block.
