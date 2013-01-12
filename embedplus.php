<?php
/*
  Plugin Name: Advanced YouTube Embed Plugin - Embed Plus
  Plugin URI: http://www.embedplus.com
  Description: YouTube embed plugin for WordPress. The smart features of this video plugin enhance the playback and engagement of each YouTube embed in your blog.
  Version: 2.3.0
  Author: EmbedPlus Team
  Author URI: http://www.embedplus.com
 */

/*
  Advanced YouTube Embed Plugin by Embed Plus
  Copyright (C) 2013 EmbedPlus.com

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

class EmbedPlusOfficialPlugin
{

    public static $optembedwidth = null;
    public static $optembedheight = null;
    public static $defaultheight = null;
    public static $defaultwidth = null;
    public static $opt_enhance_youtube = 'embedplusopt_enhance_youtube';
    public static $opt_show_react = 'embedplusopt_show_react';
    public static $opt_auto_hd = 'embedplusopt_auto_hd';
    public static $opt_sweetspot = 'embedplusopt_sweetspot';
    public static $ytregex = '@^\s*https?://(?:www\.)?(?:youtube.com/watch\?|youtu.be/)([^\s"]+)\s*$@im';

    public function __construct()
    {

        // register the handler to enhance oEmbed embeds
        $do_youtube2embedplus = get_option(self::$opt_enhance_youtube);
        $do_autoembeds = get_option('embed_autourls');
        if ($do_youtube2embedplus === false)
        {
            // ^ if first time installing plugin
            update_option(self::$opt_enhance_youtube, 1);
            update_option(self::$opt_show_react, 1);
            update_option(self::$opt_sweetspot, 1);
            update_option(self::$opt_auto_hd, 0);

            if ($do_autoembeds)
            {
                self::do_yt_ep();
            }
        }
        else
        {
            if ($do_youtube2embedplus == 1)
            {
                if ($do_autoembeds == 0)
                {
                    update_option('embed_autourls', 1);
                }
                self::do_yt_ep();
            }
        }


        if (self::wp_above_version('2.9'))
        {
            // add admin menu under settings
            add_action('admin_menu', 'EmbedPlusOfficialPlugin::embedplus_plugin_menu');
        }
        if (!is_admin())
        {
            //tell wordpress to register the shortcode
            add_shortcode("embedplusvideo", "EmbedPlusOfficialPlugin::embedplusvideo_shortcode");

            // allow shortcode in widgets

            add_filter('widget_text', 'do_shortcode', 11);
        }
    }

    static function install()
    {
        if (self::wp_above_version('2.9'))
        {
            update_option('embed_autourls', 1);
        }
    }

    public static function wp_above_version($ver)
    {
        global $wp_version;
        if (version_compare($wp_version, $ver, '>='))
        {
            return true;
        }
        return false;
    }

    public static function do_yt_ep()
    {
        if (self::wp_above_version('2.9') && !is_admin())
        {
            add_filter('the_content', 'EmbedPlusOfficialPlugin::youtube2embedplus_non_oembed', 1);
            wp_embed_register_handler('youtube2embedplus', self::$ytregex, 'EmbedPlusOfficialPlugin::youtube2embedplus_handler', 1);
        }
    }

    public static function youtube2embedplus_non_oembed($content)
    {
        if (strpos($content, 'httpv://') !== false)
        {
            $findv = '@^\s*http[vh]://(?:www\.)?(?:youtube.com/watch\?|youtu.be/)([^\s"]+)\s*$@im';
            $content = preg_replace_callback($findv, "EmbedPlusOfficialPlugin::httpv_convert", $content);
        }
        return $content;
    }

    public static function httpv_convert($m)
    {
        return self::youtube2embedplus_handler($m, '', $m[0], '');
    }

    public static function init_dimensions($url = null)
    {

        // get default dimensions; try embed size in settings, then try theme's content width, then just 480px
        if (self::$defaultwidth == null)
        {
            self::$optembedwidth = intval(get_option('embed_size_w'));
            self::$optembedheight = intval(get_option('embed_size_h'));

            global $content_width;
            if (empty($content_width))
                $content_width = $GLOBALS['content_width'];

            self::$defaultwidth = self::$optembedwidth ? self::$optembedwidth : ($content_width ? $content_width : 480);
            self::$defaultheight = self::get_aspect_height($url);
        }
    }

    public static function get_aspect_height($url)
    {

        // attempt to get aspect ratio correct height from oEmbed
        $aspectheight = round((self::$defaultwidth * 9) / 16, 0);
        if ($url)
        {
            require_once( ABSPATH . WPINC . '/class-oembed.php' );
            $oembed = _wp_oembed_get_object();
            $args = array();
            $args['width'] = self::$defaultwidth;
            $args['height'] = self::$optembedheight;
            $args['discover'] = false;
            $odata = $oembed->fetch('http://www.youtube.com/oembed', $url, $args);

            if ($odata)
            {
                $aspectheight = $odata->height;
            }
        }

        //add 30 for YouTube's own bar
        return $aspectheight + 30;
    }

    public static function youtube2embedplus_handler($matches, $attr, $url, $rawattr)
    {

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
        foreach ($ytvars as $k => $v)
        {
            $kvp = preg_split('/=/', $v);
            if (count($kvp) == 2)
            {
                $ytkvp[$kvp[0]] = $kvp[1];
            }
            else if (count($kvp) == 1 && $k == 0)
            {
                $ytkvp['v'] = $kvp[0];
            }
        }


        // setup variables for creating embed code
        $epreq['vars'] = 'ytid=';
        $epreq['standard'] = 'http://www.youtube.com/v/';
        if ($ytkvp['v'])
        {
            $epreq['vars'] .= strip_tags($ytkvp['v']) . '&amp;';
            $epreq['standard'] .= strip_tags($ytkvp['v']) . '?fs=1&amp;';
        }
        $realheight = intval($ytkvp['h'] ? $ytkvp['h'] : $epreq['height']);
        $epreq['vars'] .= 'height=' . $realheight . '&amp;';
        $epreq['height'] = $realheight;

        $realwidth = intval($ytkvp['w'] ? $ytkvp['w'] : $epreq['width']);
        $epreq['vars'] .= 'width=' . $realwidth . '&amp;';
        $epreq['width'] = $realwidth;

        
        $realhd = intval(get_option(self::$opt_auto_hd, '0')) == 1 ? 'hd=1&amp;' : '';
        $realhd = $ytkvp['hd'] ? 'hd=' . intval($ytkvp['hd']) . '&amp;' : $realhd;
        $epreq['vars'] .= $realhd;
        $epreq['standard'] .= $realhd;

        $realstart = $ytkvp['start'] ? 'start=' . intval($ytkvp['start']) . '&amp;' : '';
        $epreq['vars'] .= $realstart;
        $epreq['standard'] .= $realstart;

        $epreq['vars'] .= 'react=' . get_option(self::$opt_show_react) . '&amp;';
        $epreq['vars'] .= 'sweetspot=' . get_option(self::$opt_sweetspot) . '&amp;';

        //$epreq['vars'] .= 'rs=w&amp;';

        return self::get_embed_code($epreq);
    }

    public static function embedplusvideo_shortcode($incomingfrompost)
    {

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

    public static function get_embed_code($incomingfromhandler)
    {
        $epheight = $incomingfromhandler['height'];
        $epwidth = $incomingfromhandler['width'];
        $epvars = $incomingfromhandler['vars'];
        $epobjid = $incomingfromhandler['id'];
        $epstandard = $incomingfromhandler['standard'];
        $epfullheight = null;

        $epobjid = htmlspecialchars($epobjid);

        if (is_numeric($epheight))
        {
            $epheight = (int) $epheight;
        }
        else
        {
            $epheight = $this->defaultheight;
        }
        $epfullheight = $epheight + 32;

        if (is_numeric($epwidth))
        {
            $epwidth = (int) $epwidth;
        }
        else
        {
            $epwidth = $this->defaultwidth;
        }

        $epvars = preg_replace('/\s/', '', $epvars);
        $epvars = preg_replace('/¬/', '&not', $epvars);
        
        if ($epstandard == "")
        {
            $epstandard = "http://www.youtube.com/embed/";
            $ytidmatch = array();
            preg_match('/ytid=([^&]+)&/i', $epvars, $ytidmatch);
            $epstandard .= $ytidmatch[1];
        }
        
        $epstandard = preg_replace('/\s/', '', $epstandard);

        $epstandard = preg_replace('/youtube.com\/v\//i', 'youtube.com/embed/', $epstandard);
            $epoutputstandard = '<iframe class="cantembedplus" title="YouTube video player" width="~width" height="~height" src="~standard" frameborder="0" allowfullscreen></iframe>';
        

        $epoutput =
                '<object type="application/x-shockwave-flash" width="~width" height="~fullheight" data="http://getembedplus.com/embedplus.swf" id="' . $epobjid . '">' . chr(13) .
                '<param value="http://getembedplus.com/embedplus.swf" name="movie" />' . chr(13) .
                '<param value="high" name="quality" />' . chr(13) .
                '<param value="transparent" name="wmode" />' . chr(13) .
                '<param value="always" name="allowscriptaccess" />' . chr(13) .
                '<param value="true" name="allowFullScreen" />' . chr(13) .
                '<param name="flashvars" value="~vars&amp;rs=w" />' . chr(13) .
                $epoutputstandard . chr(13) .
                '</object>' . chr(13) .
                '<!--[if lte IE 6]> <style type="text/css">.cantembedplus{display:none;}</style><![endif]-->';

        $ua = $_SERVER['HTTP_USER_AGENT'];
        if (strlen($epvars) == 0 ||
                stripos($ua, 'iPhone') !== false ||
                stripos($ua, 'iPad') !== false ||
                stripos($ua, 'iPod') !== false)
        {// if no embedplus vars for some reason, or if iOS
            $epoutput = $epoutputstandard;
        }

        if (function_exists('wp_specialchars_decode'))
        {
            $epvars = wp_specialchars_decode($epvars);
            $epstandard = wp_specialchars_decode($epstandard);
        }
        else
        {
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

        // reset static vals for next embed
        self::$optembedwidth = null;
        self::$optembedheight = null;
        self::$defaultheight = null;
        self::$defaultwidth = null;

        //send back text to calling function
        return $epoutput;
    }

    public static function embedplus_plugin_menu()
    {
        add_options_page('EmbedPlus Settings', 'EmbedPlus', 'manage_options', 'embedplus-official-options', 'EmbedPlusOfficialPlugin::embedplus_show_options');
    }

    public static function embedplus_show_options()
    {

        if (!current_user_can('manage_options'))
        {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // variables for the field and option names 
        $embedplus_submitted = 'embedplus_submitted';

        // Read in existing option values from database
        $opt_enhance_youtube_val = get_option(self::$opt_enhance_youtube);
        $opt_show_react_val = get_option(self::$opt_show_react);
        $opt_auto_hd_val = get_option(self::$opt_auto_hd);
        $opt_sweetspot_val = get_option(self::$opt_sweetspot);

        // See if the user has posted us some information
        // If they did, this hidden field will be set to 'Y'
        if (isset($_POST[$embedplus_submitted]) && $_POST[$embedplus_submitted] == 'Y')
        {
            // Read their posted values
            $opt_enhance_youtube_val = $_POST[self::$opt_enhance_youtube] == (true || 'on') ? 1 : 0;
            $opt_show_react_val = $_POST[self::$opt_show_react] == (true || 'on') ? 1 : 0;
            $opt_auto_hd_val = $_POST[self::$opt_auto_hd] == (true || 'on') ? 1 : 0;
            $opt_sweetspot_val = $_POST[self::$opt_sweetspot] == (true || 'on') ? 1 : 0;

            // Save the posted value in the database
            update_option(self::$opt_enhance_youtube, $opt_enhance_youtube_val);
            update_option(self::$opt_show_react, $opt_show_react_val);
            update_option(self::$opt_auto_hd, $opt_auto_hd_val);
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

            <h3><?php _e("Auto-Embed Settings") ?></h3>

            <p>
                <?php _e("WordPress 2.9 and above automatically converts YouTube URLs (<a href=\"http://codex.wordpress.org/Embeds#In_A_Nutshell\" target=\"_blank\">that are on their own line &raquo;</a>) to actual video embeds. This plugin can make those \"auto-embeds\" display the enhanced player if you check the first option below." . (get_option('embed_autourls') ? "" : " <strong>Make sure that <strong><a href=\"/wp-admin/options-media.php\">Settings &raquo; Media &raquo; Embeds &raquo; Auto-embeds</a></strong> is checked too.</strong>")); ?>
            </p>
            <p>
                <input name="<?php echo self::$opt_enhance_youtube; ?>" id="<?php echo self::$opt_enhance_youtube; ?>" <?php checked($opt_enhance_youtube_val, 1); ?> type="checkbox" class="checkbox">
                <label for="<?php echo self::$opt_enhance_youtube; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/epicon.png"/> <?php _e('Automatically enhance all your YouTube embeds') ?></label>
            </p>
            <p>
                <input name="<?php echo self::$opt_auto_hd; ?>" id="<?php echo self::$opt_auto_hd; ?>" <?php checked($opt_auto_hd_val, 1); ?> type="checkbox" class="checkbox">
                <label for="<?php echo self::$opt_auto_hd; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/hd.jpg"/> <?php _e('Automatically make all videos HD quality (when possible).') ?></label>
            </p>
            <p>
                <input name="<?php echo self::$opt_sweetspot; ?>" id="<?php echo self::$opt_sweetspot; ?>" <?php checked($opt_sweetspot_val, 1); ?> type="checkbox" class="checkbox">
                <label for="<?php echo self::$opt_sweetspot; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/ssm.jpg"/> <?php _e('Enable <a href="http://www.embedplus.com/whysearchhere.aspx" target="_blank">Sweetspot Marking</a> for the next/previous buttons') ?></label>            
            </p>
            <p>
                <input name="<?php echo self::$opt_show_react; ?>" id="<?php echo self::$opt_show_react; ?>" <?php checked($opt_show_react_val, 1); ?> type="checkbox" class="checkbox">
                <label for="<?php echo self::$opt_show_react; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/convo.jpg"/> <?php _e('Display Social Media Reactions (This is recommended so your visitors can see web discussions for each video right from your blog)') ?></label>            
            </p>

            <p>
                <strong><?php _e("Additional URL Options") ?></strong>
            </p>
            <?php
            _e("<p>If you are using the auto-embed feature above, the following optional values can be added to the YouTube URLs to quickly override default behavior. Each option should begin with '&'</p>");
            _e('<ul>');
            _e("<li><strong>w - Sets the width of your player.</strong> If omitted, the default width will be the width of your theme's content (or your <a href=\"/wp-admin/options-media.php\">WordPress maximum embed size</a>, if set).<em> Example: http://www.youtube.com/watch?v=quwebVjAEJA<strong>&w=500</strong>&h=350</em></li>");
            _e("<li><strong>h - Sets the height of your player.</strong> <em>Example: http://www.youtube.com/watch?v=quwebVjAEJA&w=500<strong>&h=350</strong></em> </li>");
            _e("<li><strong>start - Sets the time (in seconds) to start the video.</strong> <em>Example: http://www.youtube.com/watch?v=quwebVjAEJA&w=500&h=350<strong>&start=20</strong></em> </li>");
            _e("<li><strong>hd - If set to 1, this makes the video play in HD quality when possible.</strong> <em>Example: http://www.youtube.com/watch?v=quwebVjAEJA&w=500&h=350<strong>&hd=1</strong></em> </li>");
            _e('</ul>');
            ?>

            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
            </p>


        </form>

        <h3><?php _e("EmbedPlus Wizard") ?></h3>
        <p>
            If you want make further customizations, use the wizard below and you'll get the appropriate code to embed in the end. Otherwise, you can just click save changes above and begin embedding videos.
        </p>
        <p>
            If your blog's rich-text editor is enabled, you have access to a new EmbedPlus wizard button (look for this in your editor: <img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/epicon.png"/>). 
            If you use the HTML editor instead, you can use the wizard here below, or go to our <a href="http://www.embedplus.com/embedcode.aspx" target="_blank">website</a>.

        </p>
        <iframe style="-webkit-box-shadow: 0px 0px 20px 0px #000000; box-shadow: 0px 0px 20px 0px #000000;" src="http://www.embedplus.com/wpembedcode.aspx?blogwidth=<?php
        self::init_dimensions();
        echo self::$defaultwidth ? self::$defaultwidth : ""
            ?>" width="950" height="1200"/>
        </div>

        <?php
    }

}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//class start
class Add_new_tinymce_btn
{

    public $btn_arr;
    public $js_file;

    /*
     * call the constructor and set class variables
     * From the constructor call the functions via wordpress action/filter
     */

    function __construct($seperator, $btn_name, $javascrip_location)
    {
        $this->btn_arr = array("Seperator" => $seperator, "Name" => $btn_name);
        $this->js_file = $javascrip_location;
        add_action('init', array($this, 'add_tinymce_button'));
        add_filter('tiny_mce_version', array($this, 'refresh_mce_version'));
    }

    /*
     * create the buttons only if the user has editing privs.
     * If so we create the button and add it to the tinymce button array
     */

    function add_tinymce_button()
    {
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages'))
            return;
        if (get_user_option('rich_editing') == 'true')
        {
            //the function that adds the javascript
            add_filter('mce_external_plugins', array($this, 'add_new_tinymce_plugin'));
            //adds the button to the tinymce button array
            add_filter('mce_buttons', array($this, 'register_new_button'));
        }
    }

    /*
     * add the new button to the tinymce array
     */

    function register_new_button($buttons)
    {
        array_push($buttons, $this->btn_arr["Seperator"], $this->btn_arr["Name"]);
        return $buttons;
    }

    /*
     * Call the javascript file that loads the
     * instructions for the new button
     */

    function add_new_tinymce_plugin($plugin_array)
    {
        $plugin_array[$this->btn_arr['Name']] = $this->js_file;
        return $plugin_array;
    }

    /*
     * This function tricks tinymce in thinking
     * it needs to refresh the buttons
     */

    function refresh_mce_version($ver)
    {
        $ver += 3;
        return $ver;
    }

}

//class end

register_activation_hook(__FILE__, array('EmbedPlusOfficialPlugin', 'install'));

$embedplusoplg = new EmbedPlusOfficialPlugin();
$embedplusmce = new Add_new_tinymce_btn('|', 'embedpluswiz', plugins_url() . '/embedplus-for-wordpress/js/embedplus_mce.js.php');

if (EmbedPlusOfficialPlugin::wp_above_version('2.9'))
{
    add_action('admin_enqueue_scripts', 'embedplus_admin_enqueue_scripts');
}
else
{
    wp_enqueue_style('embedpluswiz', plugins_url() . '/embedplus-for-wordpress/js/embedplus_mce.css');
}

function embedplus_admin_enqueue_scripts()
{
    wp_enqueue_style('embedpluswiz', plugins_url() . '/embedplus-for-wordpress/js/embedplus_mce.css');
}
