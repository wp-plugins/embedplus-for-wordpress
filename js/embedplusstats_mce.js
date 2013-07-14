(function() {    
    tinymce.create('tinymce.plugins.Embedplusstats', {
        init : function(ed, url) {
            var plep = new Image();
            plep.src = url+'/../images/btn_embedplusstats.png';
            ed.addButton('embedplusstats', {
                title : 'How much are your visitors actually watching the videos you post? Click here to start using this popular feature from EmbedPlus Labs »',
                onclick : function(ev) {
                    window.open(epbasesite + '/dashboard/easy-video-analytics-seo.aspx?ref=wysiwygbutton&prokey=' + epprokey, '_blank');
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
                version : epversion
            };
        }
    });
    tinymce.PluginManager.add('embedplusstats', tinymce.plugins.Embedplusstats);

})();
