<?php
	global $iframe_sources;
	global $httpstr;
	$httpstr = 'http';
	$iframe_sources = array(
			// glogster
	        array(
				'match' => '#^https?://([^.]*(\.edu)?)\.glogster\.com/([a-zA-Z0-9\-_+/]*).*#',
				'url'   => 'http://$1.glogster.com/$3',
			),	
			array(
				'match' => '#^https?://(?:(?:www|edu)\.)?glogster\.com/([a-zA-Z0-9_/-]*?)/g-([a-zA-Z0-9_-]*).*#',
				'url'   => 'http://www.glogster.com/glog/$2',
			),			
			
			// google video
	        array(
				'match' => '#^http://video\.google\.com.*doc[Ii]d=(\-?[0-9]+).*#',
				'url'   => 'http://video.google.com/googleplayer.swf?docId=$1',
			),

			// prezi
			array(
                'match' => '#^https?://(www\.)?prezi\.com/embed/([a-zA-Z0-9\-_+\?/=\&;]*).*#',
                'url'   => $httpstr . '://prezi.com/embed/$2',
            ),
            array(
                'match' => '#https?://prezi\.com/([a-zA-Z0-9\-_]+)/.*#',
                'url'   => $httpstr . '://prezi.com/embed/$1/?bgcolor=ffffff&amp;lock_to_path=0&amp;autoplay=0&amp;autohide_ctrls=0&amp;features=undefined&amp;disabled_features=undefined',
            ),
			
			// scivee   
			array(
				'match' => '#^http://(www\.)?scivee\.tv/node/([0-9]+).*#',
				'url'   => 'http://www.scivee.tv/flash/embedPlayer.swf?id=$2&type=3',
			),
			array(
				'match' => '#^http://(www\.)?scivee\.tv.*id=([0-9]+).*#',
				'url'   => 'http://www.scivee.tv/flash/embedPlayer.swf?id=$2&type=3',
			),
			
			// slideshare
			array(
				'match' => '@^https?://(www\.)?slideshare\.net/(?!slideshow/)([^/]+)/([^/?&#]+).*@',
				'url'   => 'http://www.slideshare.net/$2/$3',
			),
			
			// teachertube   
			array(
				'match' => '#^http://(?:www\.)?teachertube\.com/embed\.php\?pg=video_(\d+).*#',
				'url'   => 'http://www.teachertube.com/embed/player.swf?file=http://www.teachertube.com/embedFLV.php?pg=video_$1',
			),
			array(
				'match' => '#^http://(?:www\.)?teachertube\.com/viewVideo\.php\?video_id=(\d+).*#',
				'url'   => 'http://www.teachertube.com/embed/player.swf?file=http://www.teachertube.com/embedFLV.php?pg=video_$1',
			),

			// vimeo
            array(
                'match' => '#^http://player\.vimeo\.com/video/([0-9]+).*#',
                'url'   => $httpstr . '://player.vimeo.com/video/$1'
            ),
            array(
                'match' => '#^https?://(www\.|secure\.)?vimeo\.com/([0-9]+)#',
                'url'   => $httpstr . '://player.vimeo.com/video/$2'
            ),

			// voicethread   --- not tested
            array(
                'match' => '#^https?://(www\.)?voicethread\.com/share/([0-9]+).*#',
                'url'   => $httpstr . '://voicethread.com/book.swf?b=$2',
            ),
            array(
                'match' => '@^https?://(www\.)?voicethread\.com/\??#q\.b([0-9]+).*@',
                'url'   => $httpstr . '://voicethread.com/book.swf?b=$2',
            ),
	
			// voki    --- not tested
			array(
				'match' => '#^http://www\.voki\.com/pickup\.php\?(partnerid=symbaloo&)?scid=([0-9]+)#',
				'url' => 'http://voki.com/php/checksum/scid=$2'
			),
			array(
				'match' => '#^http://www\.voki\.com/pickup\.php\?(partnerid=symbaloo&)?scid=([0-9]+)&height=([0-9]+)&width=([0-9]+)#',
				'url' => 'http://voki.com/php/checksum/scid=$2&height=$3&width=$4'
			),
			array(
				'match' => '#^http://voki\.com/php/checksum/scid=([0-9]+)&height=([0-9]+)&width=([0-9]+)#',
				'url' => 'http://voki.com/php/checksum/scid=$1&height=$2&width=$3'
			),

			// wikieducator
			array(
				'match' => '#^https?://(www\.)?wikieducator\.org/index\.php\?(old|cur)id=([0-9]+).*#',
				'url'   => 'http://wikieducator.org/index.php?$2id=$3',
			),
			array(
				'match' => '#^https?://(www\.)?wikieducator\.org/([a-zA-Z0-9_\-+:%/]+).*#',
				'url'   => 'http://wikieducator.org/$2',
			),

			// youtube
            array(
                'match' => '#^https?://(www\.)?youtube\.com/watch\?v=([a-zA-Z0-9_=-]+).*#',
                'url'   => $httpstr . '://www.youtube.com/embed/$2'
            ),
            array(
                'match' => '#^https?://(www\.)?youtube\.com/embed/([a-zA-Z0-9\-_+]*).*#',
                'url'   => $httpstr . '://www.youtube.com/embed/$2',
            ),
            array(
                'match' => '#^https?://(www\.)?youtu\.be/([a-zA-Z0-9\-_+]*)#',
                'url'   => $httpstr . '://www.youtube.com/embed/$2',
            ),
    );/**/

	function process_media_url($input, $width=0, $height=0) {
		global $iframe_sources;
		$src = $input;
		$array_m = array();
		if (stripos($input, '<iframe') !== false) { 
			//preg_match( '/src="([^"]*)"/i', $input, $array_m) ;
			preg_match( '/src=["|\']([^"|\']+)/i', $input, $array_m) ;
			if (count($array_m)>0)
				$src = $array_m[1];
		};		
		$width  = $width  ? (int)$width  : 0;
		$height = $height ? (int)$height : 0;
//		print_r($array_m);
//		echo $src;

		foreach ($iframe_sources as $source) { 
			if (preg_match($source['match'], $src)) { 
				$output = preg_replace($source['match'], $source['url'], $src);
				$result = '<iframe width="' . $width . '" height="' . $height . '" src="' . $output . '" frameborder=0></iframe>';
				return $result;
			}
		}
//		return 'I can not recognize the url';
		return $input;
    }/**/

?>