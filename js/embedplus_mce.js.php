<?php
$installdir = explode("wp-content", __FILE__);
$installdir = $installdir[0];

require( $installdir . 'wp-load.php' );


$blogwidth = null;
try
{
    $optembedwidth = intval(get_option('embed_size_w'));
    $optembedheight = intval(get_option('embed_size_h'));

    global $content_width;
    if (empty($content_width))
        $content_width = $GLOBALS['content_width'];
    if (empty($content_width))
        $content_width = $_GLOBALS['content_width'];

    $blogwidth = $optembedwidth ? $optembedwidth : ($content_width ? $content_width : 450);
}
catch (Exception $ex)
{
    
}
?>

<?php if (false)
{ ?>


    <script type="text/javascript">
<?php } ?>

    (function() {
        tinymce.create('tinymce.plugins.Embedpluswiz', {
            init : function(ed, url) {
                var plep = new Image();
                plep.src = url+'/btn_embedpluswiz.png';
                ed.addButton('embedpluswiz', {
                    title : 'EmbedPlus Shortcode Wizard',
                    //image : url+'/btn_embedpluswizoff.png',
                    onclick : function(ev) {
                        modalw = Math.round(jQuery(window).width() *.9);
                        modalh = Math.round(jQuery(window).height() *.8);
                        ed.windowManager.open({
                            title : "EmbedPlus Shortcode Wizard",
                            file : 'http://www.embedplus.com/wpembedcode.aspx?blogwidth=<?php echo $blogwidth ? $blogwidth : "" ?>',
                            width : 950,
                            height : modalh,
                            inline : true,
                            resizable: true,
                            scrollbars: true
                        }, {
                            plugin_url : url, // Plugin absolute URL
                            some_custom_arg : '' // Custom argument
                        });
                    }
                });
                       
            },
            createControl : function(n, cm) {
                return null;
            },
            getInfo : function() {
                return {
                    longname : "Embedplus Shortcode Wizard",
                    author : 'EmbedPlus',
                    authorurl : 'http://embedplus.com/',
                    infourl : 'http://embedplus.com/',
                    version : "1.0"
                };
            }
        });
        tinymce.PluginManager.add('embedpluswiz', tinymce.plugins.Embedpluswiz);
    
    })();

<?php if (false)
{ ?>
    </script>
<?php } ?>