<?php
/*
  Plugin Name: EmbedPlus for WordPress
  Plugin URI: http://www.embedplus.com
  Description: Enable WordPress to support enhanced EmbedPlus videos (slow motion, zoom, scene skipping, etc.)
  Version: 2.0
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

//define('WP_DEBUG', true);

class EmbedPlusOfficialPlugin {

    public static $optembedwidth = null;
    public static $optembedheight = null;
    public static $defaultheight = null;
    public static $defaultwidth = null;
    public static $opt_enhance_youtube = 'embedplusopt_enhance_youtube';
    public static $opt_show_react = 'embedplusopt_show_react';
    public static $opt_sweetspot = 'embedplusopt_sweetspot';
    public static $ytregex = '@^\s*https?://(?:www\.)?(?:youtube.com/watch\?|youtu.be/)([^\s"]+)\s*$@im';

    public function __construct() {

        // register the handler to enhance oEmbed embeds
        $do_youtube2embedplus = get_option(self::$opt_enhance_youtube);
        $do_autoembeds = get_option('embed_autourls');
        if ($do_youtube2embedplus === false) {
            update_option(self::$opt_enhance_youtube, 1);
            update_option(self::$opt_show_react, 1);
            update_option(self::$opt_sweetspot, 1);

            if ($do_autoembeds) {
                self::do_yt_ep();
            }
        } else {
            if ($do_youtube2embedplus == 1 && $do_autoembeds) {
                self::do_yt_ep();
            }
        }


        if (self::above_version29()) {
            // add admin menu under settings
            add_action('admin_menu', 'EmbedPlusOfficialPlugin::embedplus_plugin_menu');
        }

        //tell wordpress to register the shortcode
        add_shortcode("embedplusvideo", "EmbedPlusOfficialPlugin::embedplusvideo_shortcode");

        // allow shortcode in widgets
        if (!is_admin()) {
            add_filter('widget_text', 'do_shortcode', 11);
        }
    }

    public static function above_version29() {
        global $wp_version;
        if (version_compare($wp_version, '2.9', '>=')) {
            return true;
        }

        return false;
    }

    public static function do_yt_ep() {
        if (self::above_version29()) {
            wp_embed_register_handler('youtube2embedplus', self::$ytregex, 'EmbedPlusOfficialPlugin::youtube2embedplus_handler', 1);
        }
    }

    public static function init_dimensions($url = null) {

        // get default dimensions; try embed size in settings, then try theme's content width, then just 480px
        if (self::$defaultwidth == null) {
            self::$optembedwidth = intval(get_option('embed_size_w'));
            self::$optembedheight = intval(get_option('embed_size_h'));

            global $content_width;
            if (empty($content_width))
                $content_width = $GLOBALS['content_width'];

            self::$defaultwidth = self::$optembedwidth ? self::$optembedwidth : ($content_width ? $content_width : 480);
            self::$defaultheight = self::get_aspect_height($url);
        }
    }

    public static function get_aspect_height($url) {

        // attempt to get aspect ratio correct height from oEmbed
        $aspectheight = round((self::$defaultwidth * 9) / 16, 0);
        if ($url) {
            require_once( ABSPATH . WPINC . '/class-oembed.php' );
            $oembed = _wp_oembed_get_object();
            $args = array();
            $args['width'] = self::$defaultwidth;
            $args['height'] = self::$optembedheight;
            $args['discover'] = false;
            $odata = $oembed->fetch('http://www.youtube.com/oembed', $url, $args);

            if ($odata) {
                $aspectheight = $odata->height;
            }
        }

        //add 30 for YouTube's own bar
        return $aspectheight + 30;
    }

    public static function youtube2embedplus_handler($matches, $attr, $url, $rawattr) {

        //for future: cache results http://codex.wordpress.org/Class_Reference/WP_Object_Cache
        //$cachekey = '_epembed_' . md5( $url . serialize( $attr ) );

        self::init_dimensions($url);

        $epreq = array(
            "height" => self::$defaultheight,
            "width" => self::$defaultwidth,
            "vars" => "",
            "standard" => "",
            "id" => "ep" . rand(10000, 99999)
        );

        $ytvars = array();
        $matches[1] = preg_replace('/&amp;/i', '&', $matches[1]);
        $ytvars = preg_split('/[&?]/i', $matches[1]);


        // extract youtube vars (special case for youtube id)
        $ytkvp = array();
        foreach ($ytvars as $k => $v) {
            $kvp = preg_split('/=/', $v);
            if (count($kvp) == 2) {
                $ytkvp[$kvp[0]] = $kvp[1];
            } else if (count($kvp) == 1 && $k == 0) {
                $ytkvp['v'] = $kvp[0];
            }
        }


        // setup variables for creating embed code
        $epreq['vars'] = 'ytid=';
        $epreq['standard'] = 'http://www.youtube.com/v/';
        if ($ytkvp['v']) {
            $epreq['vars'] .= strip_tags($ytkvp['v']) . '&amp;';
            $epreq['standard'] .= strip_tags($ytkvp['v']) . '?fs=1&amp;';
        }
        $realheight = intval($ytkvp['h'] ? $ytkvp['h'] : $epreq['height']);
        $epreq['vars'] .= 'height=' . $realheight . '&amp;';
        $epreq['height'] = $realheight;

        $realwidth = intval($ytkvp['w'] ? $ytkvp['w'] : $epreq['width']);
        $epreq['vars'] .= 'width=' . $realwidth . '&amp;';
        $epreq['width'] = $realwidth;

        $realhd = $ytkvp['hd'] ? 'hd=' . intval($ytkvp['hd']) . '&amp;' : '';
        $epreq['vars'] .= $realhd;
        $epreq['standard'] .= $realhd;

        $realstart = $ytkvp['start'] ? 'start=' . intval($ytkvp['start']) . '&amp;' : '';
        $epreq['vars'] .= $realstart;
        $epreq['standard'] .= $realstart;

        $epreq['vars'] .= 'react=' . get_option(self::$opt_show_react) . '&amp;';
        $epreq['vars'] .= 'sweetspot=' . get_option(self::$opt_sweetspot) . '&amp;';

        return self::get_embed_code($epreq);
    }

    public static function embedplusvideo_shortcode($incomingfrompost) {

        self::init_dimensions();

        //process incoming attributes assigning defaults if required
        $incomingfrompost = shortcode_atts(array(
            "height" => self::$defaultheight,
            "width" => self::$defaultwidth,
            "vars" => "",
            "standard" => "",
            "id" => "ep" . rand(10000, 99999)
                ), $incomingfrompost);

        $epoutput = EmbedPlusOfficialPlugin::get_embed_code($incomingfrompost);
        //send back text to replace shortcode in post
        return $epoutput;
    }

    public static function get_embed_code($incomingfromhandler) {
        $epheight = $incomingfromhandler['height'];
        $epwidth = $incomingfromhandler['width'];
        $epvars = $incomingfromhandler['vars'];
        $epobjid = $incomingfromhandler['id'];
        $epstandard = $incomingfromhandler['standard'];
        $epfullheight = null;

        $epobjid = htmlspecialchars($epobjid);

        if (is_numeric($epheight)) {
            $epheight = (int) $epheight;
        } else {
            $epheight = $this->defaultheight;
        }
        $epfullheight = $epheight + 32;

        if (is_numeric($epwidth)) {
            $epwidth = (int) $epwidth;
        } else {
            $epwidth = $this->defaultwidth;
        }

        $epvars = preg_replace('/\s/', '', $epvars);
        $epstandard = preg_replace('/\s/', '', $epstandard);

        $epstandard = preg_replace('/youtube.com\/v\//i', 'youtube.com/embed/', $epstandard);

        if (preg_match('/youtube.com\/v/i', $epstandard)) {
            $epoutputstandard = '<object class="cantembedplus" height="~height" width="~width" type="application/x-shockwave-flash" data="~standard">' . chr(13) .
                    '<param name="movie" value="~standard" />' . chr(13) .
                    '<param name="allowScriptAccess" value="always" />' . chr(13) .
                    '<param name="allowFullScreen" value="true" />' . chr(13) .
                    '<param name="wmode" value="transparent" />' . chr(13) .
                    '</object>' . chr(13);
        } else {
            $epoutputstandard = '<iframe class="cantembedplus" title="YouTube video player" width="~width" height="~height" src="~standard" frameborder="0" allowfullscreen></iframe>';
        }

        $epoutput =
                '<object type="application/x-shockwave-flash" width="~width" height="~fullheight" data="http://getembedplus.com/embedplus.swf" id="' . $epobjid . '">' . chr(13) .
                '<param value="http://getembedplus.com/embedplus.swf" name="movie" />' . chr(13) .
                '<param value="high" name="quality" />' . chr(13) .
                '<param value="transparent" name="wmode" />' . chr(13) .
                '<param value="always" name="allowscriptaccess" />' . chr(13) .
                '<param value="true" name="allowFullScreen" />' . chr(13) .
                '<param name="flashvars" value="~vars" />' . chr(13) .
                $epoutputstandard . chr(13) .
                '</object>' . chr(13) .
                '<!--[if lte IE 6]> <style type="text/css">.cantembedplus{display:none;}</style><![endif]-->';

        if (strlen($epvars) == 0) {
            $epoutput = $epoutputstandard;
        }

        if (function_exists('wp_specialchars_decode')) {
            $epvars = wp_specialchars_decode($epvars);
            $epstandard = wp_specialchars_decode($epstandard);
        } else {
            $epvars = htmlspecialchars_decode($epvars);
            $epstandard = htmlspecialchars_decode($epstandard);
        }
        //strip tags
        $epvars = strip_tags($epvars);
        $epstandard = strip_tags($epstandard);

        $epoutput = str_replace('~height', $epheight, $epoutput);
        $epoutput = str_replace('~fullheight', $epfullheight, $epoutput);
        $epoutput = str_replace('~width', $epwidth, $epoutput);
        $epoutput = str_replace('~standard', $epstandard, $epoutput);
        $epoutput = str_replace('~vars', $epvars, $epoutput);
        //send back text to calling function
        return $epoutput;
    }

    public static function embedplus_plugin_menu() {
        add_options_page('EmbedPlus Settings', 'EmbedPlus', 'manage_options', 'embedplus-official-options', 'EmbedPlusOfficialPlugin::embedplus_show_options');
    }

    public static function embedplus_show_options() {

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // variables for the field and option names 
        $embedplus_submitted = 'embedplus_submitted';

        // Read in existing option values from database
        $opt_enhance_youtube_val = get_option(self::$opt_enhance_youtube);
        $opt_show_react_val = get_option(self::$opt_show_react);
        $opt_sweetspot_val = get_option(self::$opt_sweetspot);

        // See if the user has posted us some information
        // If they did, this hidden field will be set to 'Y'
        if (isset($_POST[$embedplus_submitted]) && $_POST[$embedplus_submitted] == 'Y') {
            // Read their posted values
            $opt_enhance_youtube_val = $_POST[self::$opt_enhance_youtube] == (true || 'on') ? 1 : 0;
            $opt_show_react_val = $_POST[self::$opt_show_react] == (true || 'on') ? 1 : 0;
            $opt_sweetspot_val = $_POST[self::$opt_sweetspot] == (true || 'on') ? 1 : 0;

            // Save the posted value in the database
            update_option(self::$opt_enhance_youtube, $opt_enhance_youtube_val);
            update_option(self::$opt_show_react, $opt_show_react_val);
            update_option(self::$opt_sweetspot, $opt_sweetspot_val);

            // Put a settings updated message on the screen
            ?>
            <div class="updated"><p><strong><?php _e('Settings saved.'); ?></strong></p></div>
            <?php
        }

        // Now display the settings editing screen

        echo '<div class="wrap">';

        // header

        echo "<h2>" . __('EmbedPlus Settings') . "</h2>";

        // settings form
        ?>
        <style type="text/css">
            .epicon { width: 20px; height: 20px; vertical-align: middle; padding-right: 5px;}
            #epform p { line-height: 20px; }
            .epindent {margin-left: 45px;}
            .epsmalltext {font-style: italic;}
            #epform ul li {margin-left: 30px; list-style: disc outside none;}

        </style>

        <form name="form1" method="post" action="" id="epform">
            <input type="hidden" name="<?php echo $embedplus_submitted; ?>" value="Y">

            <p>
        <?php _e("WordPress automatically converts YouTube URLs (<a href=\"http://codex.wordpress.org/Embeds#In_A_Nutshell\" target=\"_blank\">that are on their own line &raquo;</a>) to actual video embeds. This plugin can make those \"auto-embeds\" use the <a href=\"http://www.embedplus.com\" target=\"_blank\">EmbedPlus</a> player." . (get_option('embed_autourls') ? "" : "<strong>Make sure that <strong><a href=\"/wp-admin/options-media.php\">Settings &raquo; Media &raquo; Embeds &raquo; Auto-embeds</a></strong> is checked too.</strong>")); ?>
            </p>

            <p>
                <input name="<?php echo self::$opt_enhance_youtube; ?>" id="<?php echo self::$opt_enhance_youtube; ?>" <?php checked($opt_enhance_youtube_val, 1); ?> type="checkbox" class="checkbox">
                <label for="<?php echo self::$opt_enhance_youtube; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/epicon.jpg"/> <?php _e('Automatically enhance all your YouTube embeds') ?></label>
            </p>
            <p>
                <input name="<?php echo self::$opt_show_react; ?>" id="<?php echo self::$opt_show_react; ?>" <?php checked($opt_show_react_val, 1); ?> type="checkbox" class="checkbox">
                <label for="<?php echo self::$opt_show_react; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/convo.jpg"/> <?php _e('Allow visitors to see Real-time Reactions') ?></label>            
            </p>
            <p>
                <input name="<?php echo self::$opt_sweetspot; ?>" id="<?php echo self::$opt_sweetspot; ?>" <?php checked($opt_sweetspot_val, 1); ?> type="checkbox" class="checkbox">
                <label for="<?php echo self::$opt_sweetspot; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/ssm.jpg"/> <?php _e('Enable <a href="http://www.embedplus.com/whysearchhere.aspx" target="_blank">Sweetspot Marking</a> for the next/previous buttons') ?></label>            
            </p>

            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
            </p>

            <h3><?php _e("Additional URL Options") ?></h3>
        <?php
        _e("<p>The following optional values can be added to the YouTube URLs to override default behavior. Each option should begin with '&'</p>");
        _e('<ul>');
        _e("<li><strong>w - Sets the width of your player.</strong> If omitted, the default width will be the width of your theme's content (or your <a href=\"/wp-admin/options-media.php\">WordPress maximum embed size</a>, if set).<em>Example: http://www.youtube.com/watch?v=quwebVjAEJA<strong>&w=500</strong>&h=350</em></li>");
        _e("<li><strong>h - Sets the height of your player.</strong> <em>Example: http://www.youtube.com/watch?v=quwebVjAEJA&w=500<strong>&h=350</strong></em> </li>");
        _e("<li><strong>start - Sets the time (in seconds) to start the video.</strong> <em>Example: http://www.youtube.com/watch?v=quwebVjAEJA&w=500&h=350<strong>&start=20</strong></em> </li>");
        _e("<li><strong>hd - If set to 1, this makes the video play in HD quality if available.</strong> <em>Example: http://www.youtube.com/watch?v=quwebVjAEJA&w=500&h=350<strong>&hd=1</strong></em> </li>");
        _e('</ul>');
        ?>

        </form>
        </div>

        <?php
    }

}

$embedplusoplg = new EmbedPlusOfficialPlugin();
?>