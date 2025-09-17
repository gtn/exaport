<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_exaport\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when an exaport item is created.
 */
class item_created extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'block_exaportitem';
    }

    public static function get_name() {
        return get_string('eventitemcreated', 'block_exaport');
    }

    public function get_description() {
        return "User {$this->userid} created exaport item {$this->objectid} in course {$this->courseid}";
    }

    public function get_url() {
        return new \moodle_url('/blocks/exaport/item.php', array(
            'courseid' => $this->courseid,
            'id' => $this->objectid,
            'action' => 'edit'
        ));
    }
}

