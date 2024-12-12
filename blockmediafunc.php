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

defined('MOODLE_INTERNAL') || die();

global $iframesources;
global $httpstr;
$httpstr = 'http';
$iframesources = array(
    // Glogster.
    array(
        'match' => '/http(s?):\/\/([^.]*(\.edu)?)\.glogster\.com\/([a-zA-Z0-9\-_+\/]*).*/',
        'url' => $httpstr . '$1://$1.glogster.com/$4',
    ),
    array(
        'match' => '/http(s?):\/\/(?:(?:www|edu)\.)?glogster\.com\/([a-zA-Z0-9_\/-]*?)\/g-([a-zA-Z0-9_-]*).*/',
        'url' => $httpstr . '$1://www.glogster.com/glog/$3',
    ),

    // Google video.
    array(
        'match' => '/http:\/\/video\.google\.com.*doc[Ii]d=(\-?[0-9]+).*/',
        'url' => 'http://video.google.com/googleplayer.swf?docId=$1',
    ),

    // Prezi.
    array(
        'match' => '/http(s?):\/\/(www\.)?prezi\.com\/embed\/([a-zA-Z0-9\-_+\?\/=\&;]*).*/',
        'url' => $httpstr . '$1://prezi.com/embed/$3',
    ),
    array(
        'match' => '/http(s?):\/\/prezi\.com\/([a-zA-Z0-9\-_]+)\/.*/',
        'url' => $httpstr . '$1://prezi.com/embed/$2/?bgcolor=ffffff&amp;lock_to_path=0&amp;autoplay=0&amp;' .
            'autohide_ctrls=0&amp;features=undefined&amp;disabled_features=undefined',
    ),

    // Scivee.
    array(
        'match' => '/http:\/\/(www\.)?scivee\.tv\/node\/([0-9]+).*/',
        'url' => 'http://www.scivee.tv/flash/embedPlayer.swf?id=$2&type=3',
    ),
    array(
        'match' => '/http:\/\/(www\.)?scivee\.tv.*id=([0-9]+).*/',
        'url' => 'http://www.scivee.tv/flash/embedPlayer.swf?id=$2&type=3',
    ),

    // Slideshare.
    array(
        'match' => '/http(s?):\/\/(www\.)?slideshare\.net\/(?!slideshow\/)([^\/]+)\/([^\/?&#]+).*/',
        'url' => $httpstr . '$1://www.slideshare.net/$3/$4',
    ),

    // Teachertube.
    array(
        'match' => '/http:\/\/(?:www\.)?teachertube\.com\/embed\.php\?pg=video_(\d+).*/',
        'url' => 'http://www.teachertube.com/embed/player.swf?file=http://www.teachertube.com/embedFLV.php?pg=video_$1',
    ),
    array(
        'match' => '/http:\/\/(?:www\.)?teachertube\.com\/viewVideo\.php\?video_id=(\d+).*/',
        'url' => 'http://www.teachertube.com/embed/player.swf?file=http://www.teachertube.com/embedFLV.php?pg=video_$1',
    ),

    // Vimeo.
    array(
        'match' => '/http:\/\/player\.vimeo\.com\/video\/([0-9]+).*/',
        'url' => $httpstr . '://player.vimeo.com/video/$1',
    ),
    array(
        'match' => '/http(s?):\/\/(www\.|secure\.)?vimeo\.com\/([0-9]+)/',
        'url' => $httpstr . '$1://player.vimeo.com/video/$3',
    ),

    // Voicethread   --- not tested.
    array(
        'match' => '/http(s?):\/\/(www\.)?voicethread\.com\/share\/([0-9]+).*/',
        'url' => $httpstr . '$1://voicethread.com/book.swf?b=$3',
    ),
    array(
        'match' => '/http(s?):\/\/(www\.)?voicethread\.com\/\??#q\.b([0-9]+).*/',
        'url' => $httpstr . '$1://voicethread.com/book.swf?b=$3',
    ),

    // Voki	--- not tested.
    array(
        'match' => '/http:\/\/www\.voki\.com\/pickup\.php\?(partnerid=symbaloo&)?scid=([0-9]+)/',
        'url' => 'http://voki.com/php/checksum/scid=$2',
    ),
    array(
        'match' => '/http:\/\/www\.voki\.com\/pickup\.php\?(partnerid=symbaloo&)?scid=([0-9]+)&height=([0-9]+)&width=([0-9]+)/',
        'url' => 'http://voki.com/php/checksum/scid=$2&height=$3&width=$4',
    ),
    array(
        'match' => '/http:\/\/voki\.com\/php\/checksum\/scid=([0-9]+)&height=([0-9]+)&width=([0-9]+)/',
        'url' => 'http://voki.com/php/checksum/scid=$1&height=$2&width=$3',
    ),

    // Wikieducator.
    array(
        'match' => '/http(s?):\/\/(www\.)?wikieducator\.org\/index\.php\?(old|cur)id=([0-9]+).*/',
        'url' => $httpstr . '$1://wikieducator.org/index.php?$2id=$4',
    ),
    array(
        'match' => '/http(s?):\/\/(www\.)?wikieducator\.org\/([a-zA-Z0-9_\-+:%\/]+).*/',
        'url' => $httpstr . '$1://wikieducator.org/$3',
    ),

    // Youtube.
    array(
        'match' => '/http(s?):\/\/(www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_=-]+).*/',
        'url' => $httpstr . '$1://www.youtube.com/embed/$3',
    ),
    array(
        'match' => '/http(s?):\/\/(www\.)?youtube\.com\/embed\/([a-zA-Z0-9\-_+]*).*/',
        'url' => $httpstr . '$1://www.youtube.com/embed/$3',
    ),
    array(
        'match' => '/http(s?):\/\/(www\.)?youtu\.be\/([a-zA-Z0-9\-_+]*)/',
        'url' => $httpstr . '$1://www.youtube.com/embed/$3',
    ),
);

function process_media_url($input, $width = 0, $height = 0) {
    global $iframesources;
    $src = $input;
    $arraym = array();
    if (stripos($input, '<iframe') !== false) {
        preg_match('/src=["|\']([^"|\']+)/i', $input, $arraym);
        if (count($arraym) > 0) {
            $src = $arraym[1];
        }
    };
    $width = $width ? (int)$width : 0;
    $height = $height ? (int)$height : 0;
    $output = $src;
    foreach ($iframesources as $source) {
        if (preg_match($source['match'], $src, $matches)) {
            $iframe = '<iframe width="' . $width . '" height="' . $height . '" ' .
                ' src="' . preg_replace($source['match'], $source['url'], $matches[0]) . '" frameborder="0">' .
                '</iframe>';
            $output = preg_replace($source['match'], $iframe, $output);
            return $output;
        }
    };
    return $output;
}
