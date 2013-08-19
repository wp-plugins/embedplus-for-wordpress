<?php
/*
  Plugin Name: Advanced YouTube Embed by Embed Plus
  Plugin URI: http://www.embedplus.com
  Description: YouTube embed plugin for WordPress. The smart features of this video plugin enhance the playback and engagement of each YouTube embed in your blog.
  Version: 3.5
  Author: EmbedPlus Team
  Author URI: http://www.embedplus.com
 */

/*
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

    public static $version = '3.5';
    public static $opt_version = 'version';
    public static $optembedwidth = null;
    public static $optembedheight = null;
    public static $defaultheight = null;
    public static $defaultwidth = null;
    public static $opt_enhance_youtube = 'enhance_youtube';
    public static $opt_show_react = 'show_react';
    public static $opt_auto_hd = 'auto_hd';
    public static $opt_sweetspot = 'sweetspot';
    public static $opt_emb = 'emb';
    public static $opt_pro = 'pro';
    public static $opt_alloptions = 'embedplusopt_alloptions';
    public static $alloptions = null;
    //public static $epbase = 'http://localhost:2346';
    public static $epbase = 'http://www.embedplus.com';
    //TEST REGEX
    ///////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////
    public static $ytregex = '@^\s*https?://(?:www\.)?(?:(?:youtube.com/watch\?)|(?:youtu.be/))([^\s"]+)\s*$@im';

    ///////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////////

    public function __construct()
    {
        self::$alloptions = get_option(self::$opt_alloptions);
        if (self::$alloptions == false || version_compare(self::$alloptions[self::$opt_version], self::$version, '<'))
        {
            self::install();
        }

        if (self::$optembedwidth == null)
        {
            self::$optembedwidth = intval(get_option('embed_size_w'));
            self::$optembedheight = intval(get_option('embed_size_h'));
        }

        $do_youtube2embedplus = self::$alloptions[self::$opt_enhance_youtube];

        if ($do_youtube2embedplus == 1)
        {
            self::do_yt_ep();
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

            if (get_option(self::$opt_alloptions) === false)
            {
                $_opt_enhance_youtube = get_option('embedplusopt_enhance_youtube', 1);
                $_opt_show_react = get_option('embedplusopt_show_react', 1);
                $_opt_auto_hd = get_option('embedplusopt_auto_hd', 0);
                $_opt_sweetspot = get_option('embedplusopt_sweetspot', 1);
                $_opt_emb = 1; //get_option('embedplusopt_emb', 1);
                $_opt_pro = get_option('embedplusopt_pro', '');

                $all = array(
                    self::$opt_version => self::$version,
                    self::$opt_enhance_youtube => $_opt_enhance_youtube,
                    self::$opt_show_react => $_opt_show_react,
                    self::$opt_auto_hd => $_opt_auto_hd,
                    self::$opt_sweetspot => $_opt_sweetspot,
                    self::$opt_pro => $_opt_pro,
                    self::$opt_emb => $_opt_emb
                );

                add_option(self::$opt_alloptions, $all);
            }
            self::$alloptions = get_option(self::$opt_alloptions);
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


        $realhd = intval(self::$alloptions[self::$opt_auto_hd]) == 1 ? 'hd=1&amp;' : '';
        $realhd = $ytkvp['hd'] ? 'hd=' . intval($ytkvp['hd']) . '&amp;' : $realhd;
        $epreq['vars'] .= $realhd;
        $epreq['standard'] .= $realhd;

        $realstart = $ytkvp['start'] ? 'start=' . intval($ytkvp['start']) . '&amp;' : '';
        $epreq['vars'] .= $realstart;
        $epreq['standard'] .= $realstart;

        $realend = $ytkvp['end'] ? 'end=' . intval($ytkvp['end']) . '&amp;' : '';
        $epreq['vars'] .= preg_replace('/end/', 'stop', $realend);
        $epreq['standard'] .= $realend;

        $epreq['vars'] .= 'react=' . intval(self::$alloptions[self::$opt_show_react]) . '&amp;';
        $epreq['vars'] .= 'sweetspot=' . intval(self::$alloptions[self::$opt_sweetspot]) . '&amp;';
        $epreq['vars'] .= self::$alloptions[self::$opt_emb] == '0' ? 'emb=0&amp;' : '';

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

        if (self::$alloptions[self::$opt_emb] == '0')
        {
            $incomingfrompost['vars'] .= '&emb=0';
        }


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
        self::$defaultheight = null;
        self::$defaultwidth = null;

        //send back text to calling function
        return $epoutput;
    }

    public static function embedplus_plugin_menu()
    {
        add_menu_page('EmbedPlus Settings', 'EmbedPlus', 'manage_options', 'embedplus-official-options', 'EmbedPlusOfficialPlugin::embedplus_show_options', plugins_url('images/epicon.png', __FILE__), '10.00392854349');
        add_menu_page('EmbedPlus Video Analytics Dashboard', 'EmbedPlus PRO', 'manage_options', 'embedplus-video-analytics-dashboard', 'EmbedPlusOfficialPlugin::epstats_show_options', plugins_url('images/epstats16.png', __FILE__), '10.00492854349');
        add_options_page('EmbedPlus Settings', 'EmbedPlus', 'manage_options', 'embedplus-official-options', 'EmbedPlusOfficialPlugin::embedplus_show_options');
    }

    public static function epstats_show_options()
    {

        if (!current_user_can('manage_options'))
        {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Now display the settings editing screen
        ?>
        <div class="wrap">
            <?php
            // header

            echo "<h2>" . '<img src="' . plugins_url('images/epicon.png', __FILE__) . '" /> ' . __('EmbedPlus PRO') . "</h2>";

            // settings form
            ?>
            <style type="text/css">
                .epicon { width: 20px; height: 20px; vertical-align: middle; padding-right: 5px;}
                .epindent {padding-left: 25px;}
                iframe.shadow {-webkit-box-shadow: 0px 0px 20px 0px #000000; box-shadow: 0px 0px 20px 0px #000000;}
            </style>
            <br>
            <?php
            $thishost = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "");
            $thiskey = self::$alloptions[self::$opt_pro];
            if (self::$alloptions[self::$opt_pro] && strlen(trim(self::$alloptions[self::$opt_pro])) > 0)
            {
                echo '<p><i>Logging you in...</i></p>';
            }
            ?>
            <iframe class="shadow" src="<?php echo self::$epbase ?>/dashboard/easy-video-analytics-seo.aspx?ref=protab&domain=<?php echo $thishost; ?>&prokey=<?php echo $thiskey; ?>" width="1030" height="2000" scrolling="auto"></iframe>
        </div>
        <?php
    }

    public static function has_pro()
    {
        
    }

    public static function my_embedplus_pro_validate()
    {
        $result = array();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
        {

            try
            {
                $tmppro = preg_replace('/[^A-Za-z0-9-]/i', '', $_REQUEST[self::$opt_pro]);
                $new_options = array();
                $websiteurl = site_url();

                $valurl = self::$epbase . "/dashboard/wordpress-pro-validate.aspx?domain=" . urlencode($websiteurl) . "&prokey=" . $tmppro;
                $is_valid = file_get_contents($valurl);

                $result['data'] = $is_valid;

                if (trim($is_valid) === '1')
                {
                    $new_options[self::$opt_pro] = $tmppro;
                    $result['type'] = 'success';
                }
                else
                {
                    $new_options[self::$opt_pro] = '';
                    $result['type'] = 'failed';
                }

                $all = get_option(self::$opt_alloptions);
                $all = $new_options + $all;
                update_option(self::$opt_alloptions, $all);
            }
            catch (Exception $ex)
            {
                $result['type'] = 'error';
            }

            echo json_encode($result);
        }
        else
        {
            header("Location: " . $_SERVER["HTTP_REFERER"]);
        }
        die();
    }

    public static function my_embedplus_pro_record()
    {
        $result = array();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
        {
            $tmppro = preg_replace('/[^A-Za-z0-9-]/i', '', $_REQUEST[self::$opt_pro]);
            $new_options = array();
            $new_options[self::$opt_pro] = $tmppro;
            $all = get_option(self::$opt_alloptions);
            $all = $new_options + $all;
            update_option(self::$opt_alloptions, $all);

            if (strlen($tmppro) > 0)
            {
                $result['type'] = 'success';
            }
            else
            {
                $result['type'] = 'error';
            }
            echo json_encode($result);
        }
        else
        {
            $result['type'] = 'error';
            header("Location: " . $_SERVER["HTTP_REFERER"]);
        }
        die();
    }

    public static function embedplus_show_options()
    {

        if (!current_user_can('manage_options'))
        {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // variables for the field and option names 
        $embedplus_submitted = 'embedplus_submitted';
        $pro_submitted = 'pro_submitted';

        // Read in existing option values from database
//        $opt_enhance_youtube_val = self::$alloptions[self::$opt_enhance_youtube];
//        $opt_show_react_val = self::$alloptions[self::$opt_show_react];
//        $opt_auto_hd_val = self::$alloptions[self::$opt_auto_hd];
//        $opt_sweetspot_val = self::$alloptions[self::$opt_sweetspot];

        $all = get_option(self::$opt_alloptions);

        // See if the user has posted us some information
        // If they did, this hidden field will be set to 'Y'
        if (isset($_POST[$embedplus_submitted]) && $_POST[$embedplus_submitted] == 'Y')
        {
            // Read their posted values
            $new_options = array();

            $new_options[self::$opt_enhance_youtube] = $_POST[self::$opt_enhance_youtube] == (true || 'on') ? 1 : 0;
            $new_options[self::$opt_show_react] = $_POST[self::$opt_show_react] == (true || 'on') ? 1 : 0;
            $new_options[self::$opt_auto_hd] = $_POST[self::$opt_auto_hd] == (true || 'on') ? 1 : 0;
            $new_options[self::$opt_sweetspot] = $_POST[self::$opt_sweetspot] == (true || 'on') ? 1 : 0;
            $new_options[self::$opt_emb] = $_POST[self::$opt_emb] == (true || 'on') ? 0 : 1;

            $all = $new_options + $all;

            // Save the posted value in the database
            update_option(self::$opt_alloptions, $all);

            // Put a settings updated message on the screen
            ?>
            <div class="updated"><p><strong><?php _e('Settings saved.'); ?></strong></p></div>
            <?php
        }

        // Now display the settings editing screen
        ?>
        <style type="text/css">
            .epicon { width: 20px; height: 20px; vertical-align: middle; padding-right: 5px;}
            #epform p { line-height: 20px; }
            .epindent {padding-left: 25px;}
            .epsmalltext {font-style: italic;}
            #epform ul li, ul.reglist li {margin-left: 30px; list-style: disc outside none;}
            .orange {color: #f85d00;}
            .bold {font-weight: bold;}
            .grey{color: #888888;}
            iframe.shadow {-webkit-box-shadow: 0px 0px 20px 0px #000000; box-shadow: 0px 0px 20px 0px #000000;}
            .smallnote {font-style: italic; color: #888888; font-size: 11px;}
        </style>
        <div class="wrap">


            <?php
            $haspro = ($all[self::$opt_pro] && strlen(trim($all[self::$opt_pro])) > 0);

            if ($haspro)
            {
                echo "<h2>" . '<img src="' . plugins_url('images/epicon.png', __FILE__) . '" /> ' . __('Thank you for going PRO.');
                echo ' &nbsp;<input type="submit" name="showkey" class="button-primary" style="vertical-align: 15%;" id="showprokey" value="Show my PRO key" />';
                echo "</h2>";
                ?>
                <?php
            }
            else
            {
                echo "<h2>" . '<img src="' . plugins_url('images/epicon.png', __FILE__) . '" /> ' . __('Go PRO') . "</h2>";
                ?>
                <a href="<?php echo self::$epbase ?>/dashboard/easy-video-analytics-seo.aspx?ref=protab" target="_blank">Click here to go PRO.</a> Your PRO key will then be immediately emailed to you.
                <?php
            }
            ?>
            <div class="epindent">


                <form name="form2" method="post" action="" id="epform2">
                    <input type="hidden" name="<?php echo $pro_submitted; ?>" value="Y">

                    <p class="submit submitpro" <?php if ($haspro) echo 'style="display: none;"' ?>>
                        <label for="opt_pro"><?php _e('Enter PRO key:') ?></label>
                        <input style="box-shadow: 0px 0px 5px 0px #1870D5; width: 270px;" name="<?php echo self::$opt_pro; ?>" id="opt_pro" value="<?php echo $all[self::$opt_pro]; ?>" type="text">

                        <input type="submit" name="Submit" class="button-primary" id="prokeysubmit" value="<?php _e('Save Key') ?>" /> &nbsp;
                        <span style="display: none;" id="prokeyloading" class="orange bold">Verifying...</span>
                        <span  class="orange bold" style="display: none;" id="prokeysuccess">Success! Please refresh this page.</span>
                        <span class="orange bold" style="display: none;" id="prokeyfailed">Sorry, that seems to be an invalid key.</span>
                    </p>

                </form>

            </div>

            <?php
            // header

            echo "<h2>" . '<img src="' . plugins_url('images/epicon.png', __FILE__) . '" /> ' . __('EmbedPlus Settings') . "</h2>";

            // settings form
            ?>

            <div class="epindent">
                <form name="form1" method="post" action="" id="epform">
                    <input type="hidden" name="<?php echo $embedplus_submitted; ?>" value="Y">

                    <h3><?php _e("Auto-Embed Settings") ?></h3>

                    <p>
                        <?php
                        _e("This plugin automatically converts YouTube URLs that are on their own line, in plain text, to actual video embeds. All you have to do is paste the YouTube URL in the editor (example: <code>http://www.youtube.com/watch?v=YVvn8dpSAt0</code>), and:");
                        ?>
                    <ul class="reglist">
                        <li>Make sure the url is really on its own line by itself</li>
                        <li>Make sure the url is <strong>not</strong> an active hyperlink (i.e., it should just be plain text). Otherwise, highlight the url and click the "unlink" button in your editor: <img src="<?php echo plugins_url('images/unlink.png', __FILE__) ?>"/>.</li>
                    </ul>       
                    <?php
                    _e("This plugin can make those \"auto-embeds\" display the enhanced player if you check the first option below"
                            . (get_option('embed_autourls') ? "" : " <strong>Make sure that <strong><a href=\"/wp-admin/options-media.php\">Settings &raquo; Media &raquo; Embeds &raquo; Auto-embeds</a></strong> is checked too.</strong>"));
                    ?>
                    </p>
                    <p>
                        <input name="<?php echo self::$opt_enhance_youtube; ?>" id="<?php echo self::$opt_enhance_youtube; ?>" <?php checked($all[self::$opt_enhance_youtube], 1); ?> type="checkbox" class="checkbox">
                        <label for="<?php echo self::$opt_enhance_youtube; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/epicon.png"/> <?php _e('Automatically enhance all your YouTube embeds') ?></label>
                    </p>
                    <p>
                        <input name="<?php echo self::$opt_auto_hd; ?>" id="<?php echo self::$opt_auto_hd; ?>" <?php checked($all[self::$opt_auto_hd], 1); ?> type="checkbox" class="checkbox">
                        <label for="<?php echo self::$opt_auto_hd; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/hd.jpg"/> <?php _e('Automatically make all videos HD quality (when possible).') ?></label>
                    </p>
                    <p>
                        <input name="<?php echo self::$opt_sweetspot; ?>" id="<?php echo self::$opt_sweetspot; ?>" <?php checked($all[self::$opt_sweetspot], 1); ?> type="checkbox" class="checkbox">
                        <label for="<?php echo self::$opt_sweetspot; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/ssm.jpg"/> <?php _e('Enable <a href="' . self::$epbase . '/whysearchhere.aspx" target="_blank">Sweetspot Marking</a> for the next/previous buttons') ?></label>            
                    </p>
                    <?php
                    $eadopt = get_option('embedplusopt_enhance_youtube') !== false;
                    $prostuffmsg = "<p class=\"smallnote\">
                            We're building a growing list of customizations that offer more advanced and dynamic functionality. These will be made available to our PRO users as they are developed over time. We, in fact, encourage you to send us suggestions with the PRO priority support form (at the bottom of this page).
                        </p>";
                    if (!$eadopt)
                    {
                        echo $prostuffmsg;
                    }

                    if ($haspro || $eadopt)
                    {
                        ?>
                        <p>
                            <input name="<?php echo self::$opt_show_react; ?>" id="<?php echo self::$opt_show_react; ?>" <?php checked($all[self::$opt_show_react], 1); ?> type="checkbox" class="checkbox">
                            <label for="<?php echo self::$opt_show_react; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/convo.jpg"/> <?php _e('Display Social Media Reactions (This is recommended so your visitors can see web discussions for each video right from your blog)') ?></label>            
                        </p>


                        <?php
                        if ($eadopt)
                        {
                            echo $prostuffmsg;
                        }
                    }
                    else
                    {
                        ?>
                        <p>
                            <input type="checkbox" disabled class="checkbox">
                            <img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/convo.jpg"/> Hide Social Media Reactions (This button shows web discussions for each video right from your blog) (<a class="pp" target="_blank" href="<?php echo self::$epbase ?>/dashboard/sale.aspx?iframe=true&width=90%&height=80%" title="">available in PRO version &raquo;</a>)
                        </p>
                        <?php
                    }



                    //////////////////////
                    if ($haspro)
                    {
                        ?>
                        <p>
                            <input name="<?php echo self::$opt_emb; ?>" id="<?php echo self::$opt_emb; ?>" <?php checked($all[self::$opt_emb], '0'); ?> type="checkbox" class="checkbox">
                            <label for="<?php echo self::$opt_emb; ?>"><img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/get.jpg"/> <?php _e('Hide GET button') ?></label>
                        </p>

                        <?php
                    }
                    else
                    {
                        ?>
                        <p>
                            <input type="checkbox" disabled class="checkbox">
                            <img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/get.jpg"/> Hide GET button (<a class="pp" target="_blank" href="<?php echo self::$epbase ?>/dashboard/sale.aspx?iframe=true&width=90%&height=80%" title="">available in PRO version &raquo;</a>)</span>
                        </p>
                        <?php
                    }
                    ?>
                    <p>
                        <input type="checkbox" disabled class="checkbox">
                        <img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/nobar.jpg"/> <i>Coming Soon?</i> Advanced hiding of other buttons or entire extra control bar with dynamic player resizing
                    </p>
                    <p class="submit">
                        <input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                    </p>
            </div>


            <?php echo "<h2>" . '<img src="' . plugins_url('images/epicon.png', __FILE__) . '" />' . " Additional URL Options</h2>" ?>
            <div class="epindent">

                <?php
                _e("<p>If you are using the auto-embed feature above, the following optional values can be added to the YouTube URLs to quickly override default behavior. Each option should begin with '&'</p>");
                _e('<ul>');
                _e("<li><strong>w - Sets the width of your player.</strong> If omitted, the default width will be the width of your theme's content (or your <a href=\"/wp-admin/options-media.php\">WordPress maximum embed size</a>, if set).<em> Example: http://www.youtube.com/watch?v=quwebVjAEJA<strong>&w=500</strong>&h=350</em></li>");
                _e("<li><strong>h - Sets the height of your player.</strong> <em>Example: http://www.youtube.com/watch?v=quwebVjAEJA&w=500<strong>&h=350</strong></em> </li>");
                _e("<li><strong>hd - If set to 1, this makes the video play in HD quality when possible.</strong> <em>Example: http://www.youtube.com/watch?v=quwebVjAEJA&w=500&h=350<strong>&hd=1</strong></em> </li>");
                _e("<li><strong>start - Sets the time (in seconds) to start the video.</strong> <em>Example: http://www.youtube.com/watch?v=quwebVjAEJA&w=500&h=350<strong>&start=20</strong></em> </li>");
                _e("<li><strong>end - Sets the time (in seconds) to end the video.</strong> <em>Example: http://www.youtube.com/watch?v=quwebVjAEJA&w=500&h=350<strong>&end=60</strong></em> </li>");
                _e('</ul>');
                ?>

            </div>

        </form>


        <?php
        echo "<h2>" . '<img src="' . plugins_url('images/epicon.png', __FILE__) . '" /> ' . __('EmbedPlus Wizard') . "</h2>";
        ?>
        <div class="epindent">
            <p>
                If you want make further customizations, use the wizard below and you'll get the appropriate code to embed in the end. Otherwise, you can just click save changes above and begin embedding videos.
            </p>
            <p>
                If your blog's rich-text editor is enabled, you have access to a new EmbedPlus wizard button (look for this in your editor: <img class="epicon" src="<?php echo WP_PLUGIN_URL; ?>/embedplus-for-wordpress/images/epicon.png"/>). 
                If you use the HTML editor instead, you can use the wizard here below, or go to our <a href="<?php echo self::$epbase ?>/embedcode.aspx" target="_blank">website</a>.

            </p>
            <iframe src="<?php echo self::$epbase ?>/wpembedcode.aspx?fromiframe=1&eadopt=<?php echo get_option('embedplusopt_enhance_youtube') === false ? '0' : '1' ?>&prokey=<?php echo $all[self::$opt_pro]; ?>&domain=<?php echo site_url(); ?>&blogwidth=<?php
        self::init_dimensions();
        echo self::$defaultwidth ? self::$defaultwidth : ""
        ?>" width="950" height="1200" class="shadow"></iframe>
            <br>
        </div>
        <?php
        echo "<h2>" . '<img src="' . plugins_url('images/epicon.png', __FILE__) . '" /> ' . __('Priority Support') . "</h2>";
        ?>
        <div class="epindent">
            <p>
                <strong>PRO users:</strong> Below, We've enabled the ability to have priority support with our team.  Use this to get one-on-one help with any issues you might have or to send us suggestions for future features.  We typically respond within minutes during normal work hours.  
            </p>
            <p>
                <strong>Tip for non-PRO users:</strong> We've found that a common support request has been from users that are pasting video links on single lines, as required, but are not seeing the video embed show up. One of these two suggestions is usually the fix:
            <ul class="reglist">
                <li>Make sure the url is really on its own line by itself</li>
                <li>Make sure the url is not an active hyperlink (i.e., it should just be plain text). Otherwise, highlight the url and click the "unlink" button in your editor: <img src="<?php echo plugins_url('images/unlink.png', __FILE__) ?>"/>.</li>
            </ul>                
            </p>
            <iframe src="<?php echo self::$epbase ?>/dashboard/prosupport.aspx?&prokey=<?php echo $all[self::$opt_pro]; ?>&domain=<?php echo site_url(); ?>" width="500" height="600"></iframe>
        </div>

        </div>
        <script type="text/javascript">
            var prokeyval;
            jQuery(document).ready(function($) {
                                                                                                                                                                                                                                                                                                                
                $('.pp').prettyPhoto({ modal: false, theme: 'dark_rounded' });
                                                                                                        
                jQuery('#showprokey').click(function(){
                    jQuery('.submitpro').show(500);
                    return false;
                });
                                                                                             
                                                                                             
                jQuery('#prokeysubmit').click(function(){
                    jQuery(this).attr('disabled', 'disabled');
                    jQuery('#prokeyfailed').hide();
                    jQuery('#prokeysuccess').hide();
                    jQuery('#prokeyloading').show();
                    prokeyval = jQuery('#opt_pro').val();
                                                                                                                            
                    var tempscript=document.createElement("script");
                    tempscript.src="//www.embedplus.com/dashboard/wordpress-pro-validatejp.aspx?prokey=" + prokeyval;
                    var n=document.getElementsByTagName("head")[0].appendChild(tempscript);
                    setTimeout(function(){
                        n.parentNode.removeChild(n)
                    },500);
                    return false;
                });
                                                                                        
                window.embedplus_record_prokey = function(good){
                                            
                    jQuery.ajax({
                        type : "post",
                        dataType : "json",
                        timeout: 30000,
                        url : "<?php echo admin_url('admin-ajax.php') ?>",
                        data : { action: 'my_embedplus_pro_record', <?php echo self::$opt_pro; ?>:  (good? prokeyval : "")},
                        success: function(response) {
                            if(response.type == "success") {
                                jQuery("#prokeysuccess").show();
                            }
                            else {
                                jQuery("#prokeyfailed").show();
                            }
                        },
                        error: function(xhr, ajaxOptions, thrownError){
                            jQuery('#prokeyfailed').show();
                        },
                        complete: function() {
                            jQuery('#prokeyloading').hide();
                            jQuery('#prokeysubmit').removeAttr('disabled');
                        }
                                                                                                                                                                                                                                                                                                                                                                        
                    });
                                            
                };
                                                                                        
            });
        </script>
        <?php
    }

}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//class start
class Add_new_tinymce_btn_EmbedPlus
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

$embedplusmce = new Add_new_tinymce_btn_EmbedPlus('|', 'embedpluswiz', plugins_url() . '/embedplus-for-wordpress/js/embedplus_mce.js');
$epstatsmce = new Add_new_tinymce_btn_EmbedPlus('|', 'embedplusstats', plugins_url() . '/embedplus-for-wordpress/js/embedplusstats_mce.js');

if (EmbedPlusOfficialPlugin::wp_above_version('2.9'))
{
    add_action('admin_enqueue_scripts', 'embedplus_admin_enqueue_scripts');
    //add_action("wp_ajax_my_embedplus_pro_validate", array('EmbedPlusOfficialPlugin', 'my_embedplus_pro_validate'));
    add_action("wp_ajax_my_embedplus_pro_record", array('EmbedPlusOfficialPlugin', 'my_embedplus_pro_record'));
}
else
{
    add_action('wp_print_scripts', 'embedplus_output_scriptvars');
    wp_enqueue_style('embedpluswiz', plugins_url() . '/embedplus-for-wordpress/css/embedplus_mce.css');
    wp_enqueue_style('embedplusoptionscss', plugins_url() . '/embedplus-for-wordpress/css/prettyPhoto.css');
    wp_enqueue_script('embedplusoptionsjs', plugins_url() . '/embedplus-for-wordpress/js/jquery.prettyPhoto.js');
}

function embedplus_admin_enqueue_scripts()
{
    add_action('wp_print_scripts', 'embedplus_output_scriptvars');
    wp_enqueue_style('embedpluswiz', plugins_url() . '/embedplus-for-wordpress/css/embedplus_mce.css');
    wp_enqueue_style('embedplusoptionscss', plugins_url() . '/embedplus-for-wordpress/css/prettyPhoto.css');
    wp_enqueue_script('embedplusoptionsjs', plugins_url() . '/embedplus-for-wordpress/js/jquery.prettyPhoto.js');
}

function embedplus_output_scriptvars()
{
    $blogwidth = null;
    try
    {
        $embed_size_w = intval(get_option('embed_size_w'));

        global $content_width;
        if (empty($content_width))
            $content_width = $GLOBALS['content_width'];
        if (empty($content_width))
            $content_width = $_GLOBALS['content_width'];

        $blogwidth = $embed_size_w ? $embed_size_w : ($content_width ? $content_width : 450);
    }
    catch (Exception $ex)
    {
        
    }

    $epprokey = EmbedPlusOfficialPlugin::$alloptions[EmbedPlusOfficialPlugin::$opt_pro];

    $eadopt = get_option('embedplusopt_enhance_youtube') === false ? '0' : '1';
    ?>
    <script type="text/javascript">
        var epblogwidth = <?php echo $blogwidth; ?>;
        var epprokey = '<?php echo $epprokey; ?>';
        var epbasesite = '<?php echo EmbedPlusOfficialPlugin::$epbase; ?>';
        var epversion = '<?php echo EmbedPlusOfficialPlugin::$version; ?>';
        var epeadopt = '<?php echo $eadopt; ?>';
    </script>
    <?php
}