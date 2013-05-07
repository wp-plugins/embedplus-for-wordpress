<?php if (false)
{
    ?>
    <script type="text/javascript">
<?php } ?>

(function() {    
    tinymce.create('tinymce.plugins.Embedplusstats', {
        init : function(ed, url) {
            var plep = new Image();
            plep.src = url+'/btn_embedplusstats.png';
            ed.addButton('embedplusstats', {
                title : 'How much are your visitors actually watching the videos you post? Click here to start using this popular feature from EmbedPlus Labs »',
                onclick : function(ev) {
                    window.open('http://www.embedplus.com/dashboard/wordpress-video-analytics-seo.aspx', '_blank');
                }
            });
                       
        },
        createControl : function(n, cm) {
            return null;
        },
        getInfo : function() {
            return {
                longname : "Embedplus Video Analytics Dashboard",
                author : 'EmbedPlus',
                authorurl : 'http://www.embedplus.com/',
                infourl : 'http://www.embedplus.com/',
                version : "2.6.0"
            };
        }
    });
    tinymce.PluginManager.add('embedplusstats', tinymce.plugins.Embedplusstats);
    
})();

<?php if (false)
{
    ?>
    </script>
<?php } ?>