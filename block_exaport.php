<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006 exabis internet solutions <info@exabis.at>
*  All rights reserved
*
*  You can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This module is based on the Collaborative Moodle Modules from
*  NCSA Education Division (http://www.ncsa.uiuc.edu)
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

class block_exaport extends block_list {

	function init() {
        $this->title = get_string('blocktitle', 'block_exaport');
        $this->version = 2013102205;
    }

    function instance_allow_multiple() {
        return false;
    }
    
    function instance_allow_config() {
        return false;
    }
    
	function has_config() {
	    return true;
	}
    
    function get_content() {
    	global $CFG, $COURSE, $USER;
    	
    	$context = context_system::instance();
        if (!has_capability('block/exaport:use', $context)) {
	        $this->content = '';
        	return $this->content;
        }
        
        if ($this->content !== NULL) {
            return $this->content;
        }
        
        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }
        
        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';
        
		$this->content->items[]='<a title="' . get_string('mybookmarkstitle', 'block_exaport') . '" href="' . $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $COURSE->id . '">' . get_string('mybookmarks', 'block_exaport') . '</a>';
		$this->content->icons[]='<img src="' . $CFG->wwwroot . '/blocks/exaport/pix/my_portfolio.png" height="16" width="23" alt="'.get_string("mybookmarks", "block_exaport").'" />';
		
		$this->content->items[]='<a title="' . get_string('sharedbookmarks', 'block_exaport') . '" href="' . $CFG->wwwroot . '/blocks/exaport/shared_views.php?courseid=' . $COURSE->id . '">' . get_string('sharedbookmarks', 'block_exaport') . '</a>';
	    $this->content->icons[]='<img src="' . $CFG->wwwroot . '/blocks/exaport/pix/shared_portfolios.png" height="16" width="23" alt="'.get_string("sharedbookmarks", "block_exaport").'" />';
		
		$this->content->items[]='<a title="' . get_string('export', 'block_exaport') . '" href="' . $CFG->wwwroot . '/blocks/exaport/export_scorm.php?courseid=' . $COURSE->id . '">' . get_string('export', 'block_exaport') . '</a>';
		$this->content->icons[]='<img src="' . $CFG->wwwroot . '/blocks/exaport/pix/export_scorm.png" height="16" width="23" alt="'.get_string("export", "block_exaport").'" />';

        return $this->content;
    }
}
