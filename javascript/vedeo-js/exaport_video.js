// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

window.jQueryExaport = jQuery.noConflict(true);

function errorVideo() {
	//jQueryExaport('#video_content').hide();
	jQueryExaport('#video_error').show();
}

videojs = videojs('video_file');
videojs.on('error', errorVideo);						

// videojs.options.flash.swf = '".$CFG->wwwroot."/blocks/exaport/javascript/vedeo-js/video-js.swf';
