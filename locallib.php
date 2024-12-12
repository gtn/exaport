<?php
// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

require_once($CFG->libdir . '/portfolio/caller.php');

class exaport_portfolio_caller extends portfolio_module_caller_base {

    /** @var int callback arg - the id of artefact we export */
    protected $aid;

    /** @var string component of the submission files we export */
    protected $component;

    /** @var string callback arg - the area of submission files we export */
    protected $area;

    /** @var int callback arg - the id of file we export */
    protected $fileid;

    /** @var int callback arg - the cmid of the assignment we export */
    protected $cmid;

    /**
     * Callback arg for a single file export.
     */
    public static function expected_callbackargs() {
        return array(
            'cmid' => false,
            'aid' => false,
            'area' => false,
            'component' => false,
            'fileid' => false,
        );
    }

    /**
     * The constructor.
     *
     * @param array $callbackargs
     */
    public function __construct($callbackargs) {
        parent::__construct($callbackargs);
    }

    /**
     * Load data needed for the portfolio export.
     *
     * If the assignment type implements portfolio_load_data(), the processing is delegated
     * to it. Otherwise, the caller must provide either fileid (to export single file) or
     * submissionid and filearea (to export all data attached to the given submission file area)
     * via callback arguments.
     *
     * @throws     portfolio_caller_exception
     */
    public function load_data() {
        return true;
    }

    /**
     * Prepares the package up before control is passed to the portfolio plugin.
     *
     * @return mixed
     * @throws portfolio_caller_exception
     */
    public function prepare_package() {
        return $this->prepare_package_file();
    }

    /**
     * Calculate a sha1 has of either a single file or a list
     * of files based on the data set by load_data.
     *
     * @return string
     */
    public function get_sha1() {
        $fs = get_file_storage();
        $this->single_file = $fs->get_file_by_id($this->fileid);
        return $this->get_sha1_file();
    }

    /**
     * Calculate the time to transfer either a single file or a list
     * of files based on the data set by load_data.
     *
     * @return int
     */
    public function expected_time() {
        return $this->expected_time_file();
    }

    public function get_return_url() {
        return 'http://moodle.localhost/blocks/exaport/view_items.php?courseid=1';
    }

    /**
     * Checking the permissions.
     *
     * @return bool
     */
    public function check_permissions() {
        $context = context_system::instance();
        return true;
    }

    /**
     * Display a module name.
     *
     * @return string
     */
    public static function display_name() {
        return get_string('pluginname', 'block_exaport');
    }

    /**
     * Return array of formats supported by this portfolio call back.
     *
     * @return array
     */
    public static function base_supported_formats() {
        return array(PORTFOLIO_FORMAT_FILE, PORTFOLIO_FORMAT_LEAP2A);
    }
}
