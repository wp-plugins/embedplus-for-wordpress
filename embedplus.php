<?php

/*
  Plugin Name: EmbedPlus for WordPress
  Plugin URI: http://www.embedplus.com
  Description: Enable WordPress to support EmbedPlus.com videos
  Version: 1.0
  Author: EmbedPlus Team
  Author URI: http://www.embedplus.com
 */

/*
  EmbedPlus for WordPress
  Copyright (C) 2011 EmbedPlus.com

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program. If not, see <http://www.gnu.org/licenses/>.

 */

DEFINE("EMBEDPLUS_HEIGHT", 300);
DEFINE("EMBEDPLUS_WIDTH", 400);

//tell wordpress to register the shortcode
add_shortcode("embedplusvideo", "embedplusvideo_handler");

function embedplusvideo_handler($incomingfrompost) {

    //process incoming attributes assigning defaults if required
    $incomingfrompost = shortcode_atts(array(
                "height" => EMBEDPLUS_HEIGHT,
                "width" => EMBEDPLUS_WIDTH,
                "vars" => "",
                "standard" => ""
                    ), $incomingfrompost);

    $epoutput = embedplusvideo_function($incomingfrompost);
    //send back text to replace shortcode in post
    return $epoutput;
}

//use wp_specialchars_decode so html is treated as html and not text
function embedplusvideo_function($incomingfromhandler) {
    $epheight = $incomingfromhandler['height'];
    $epwidth = $incomingfromhandler['width'];
    $epvars = $incomingfromhandler['vars'];
    $epstandard = $incomingfromhandler['standard'];
    $epfullheight = null;

    if (is_numeric($epheight)) {
        $epheight = (int) $epheight;
    } else {
        $epheight = EMBEDPLUS_HEIGHT;
    }
    $epfullheight = $epheight + 32;

    if (is_numeric($epwidth)) {
        $epwidth = (int) $epwidth;
    } else {
        $epwidth = EMBEDPLUS_WIDTH;
    }

    $epvars = preg_replace('/\s/', '', $epvars);
    $epstandard = preg_replace('/\s/', '', $epstandard);

    $epoutputstandard = '<object class="cantembedplus" height="~height" width="~width" type="application/x-shockwave-flash" data="~standard">' . chr(13) .
            '<param name="movie" value="~standard" />' . chr(13) .
            '<param name="allowScriptAccess" value="always" />' . chr(13) .
            '<param name="allowFullScreen" value="true" />' . chr(13) .
            '<param name="wmode" value="transparent" />' . chr(13) .
            '</object>' . chr(13);

    $epoutput =
            '<object type="application/x-shockwave-flash" width="~width" height="~fullheight" data="http://getembedplus.com/embedplus.swf">' . chr(13) .
            '<param value="http://getembedplus.com/embedplus.swf" name="movie" />' . chr(13) .
            '<param value="high" name="quality" />' . chr(13) .
            '<param value="transparent" name="wmode" />' . chr(13) .
            '<param value="always" name="allowscriptaccess" />' . chr(13) .
            '<param value="true" name="allowFullScreen" />' . chr(13) .
            '<param name="flashvars" value="~vars" />' . chr(13) .
            $epoutputstandard .
            '</object>' . chr(13) .
            '<!--[if lte IE 6]> <style type="text/css">.cantembedplus{display:none;}</style><![endif]-->';

    if (strlen($epvars) == 0)
    {
        $epoutput = $epoutputstandard;
    }
    
    $epvars = wp_specialchars_decode($epvars);
    $epstandard = wp_specialchars_decode($epstandard);

    $epoutput = str_replace('~height', $epheight, $epoutput);
    $epoutput = str_replace('~fullheight', $epfullheight, $epoutput);
    $epoutput = str_replace('~width', $epwidth, $epoutput);
    $epoutput = str_replace('~standard', $epstandard, $epoutput);
    $epoutput = str_replace('~vars', $epvars, $epoutput);
    //send back text to calling function
    return $epoutput;
}

?>