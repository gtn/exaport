<?php
//
// Capability definitions for the rss_client block.
//
// The capabilities are loaded into the database table when the block is
// installed or updated. Whenever the capability definitions are updated,
// the module version number should be bumped up.
//
// The system has four possible values for a capability:
// CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT, and inherit (not set).
//
//
// CAPABILITY NAMING CONVENTION
//
// It is important that capability names are unique. The naming convention
// for capabilities that are specific to modules and blocks is as follows:
//   [mod/block]/<component_name>:<capabilityname>
//
// component_name should be the same as the directory name of the mod or block.
//
// Core moodle capabilities are defined thus:
//    moodle/<capabilityclass>:<capabilityname>
//
// Examples: mod/forum:viewpost
//           block/recent_activity:view
//           moodle/site:deleteuser
//
// The variable name for the capability definitions array follows the format
//   $<componenttype>_<component_name>_capabilities
//
// For the core capabilities, the variable is $moodle_capabilities.


$capabilities = array(
    'block/exaport:use' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'user' => CAP_ALLOW
        )
    ),
    'block/exaport:export' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'user' => CAP_ALLOW
        )
    ),
    'block/exaport:import' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'user' => CAP_ALLOW
        )
    ),
    'block/exaport:importfrommoodle' => array(

        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'user' => CAP_ALLOW
        )
    ),
    'block/exaport:shareintern' => array(

        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'user' => CAP_ALLOW
        )
    ),
    'block/exaport:shareextern' => array(

        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'user' => CAP_ALLOW
        )
    ),
    'block/exaport:allowposts' => array(
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'user' => CAP_ALLOW
        )
    ),
    'block/exaport:competences' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => array(
            'coursecreator' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    )
);

?>