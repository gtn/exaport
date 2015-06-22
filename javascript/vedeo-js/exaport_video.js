
window.jQueryExaport = jQuery.noConflict(true);

function errorVideo() {
	//jQueryExaport('#video_content').hide();
	jQueryExaport('#video_error').show();
}

videojs = videojs('video_file');
videojs.on('error', errorVideo);						

// videojs.options.flash.swf = '".$CFG->wwwroot."/blocks/exaport/javascript/vedeo-js/video-js.swf';
