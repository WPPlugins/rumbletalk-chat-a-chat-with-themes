(function() {
     /* Register the buttons */
     tinymce.create('tinymce.plugins.RumbleTalkButtons', {
          init : function(ed, url) {
               /**
               * Inserts shortcode content
               */
			   /**
               ed.addButton( 'button_rumbletalk_chat', {
                    title : 'Insert default RumbleTalk ChatRoom',
                    image : '../wp-content/plugins/rumbletalk-chat-a-chat-with-themes/button.jpg',
                    onclick : function() {
                         ed.selection.setContent('[rumbletalk-chat]');
                    }
               });
               
               * Inserts shortcode content
               */
               ed.addButton( 'button_rumbletalk_chat2', {
                    title : 'Insert a Chat Room \(Rumbletalk\)',
                    image : '../wp-content/plugins/rumbletalk-chat-a-chat-with-themes/button2.jpg',
                    onclick : function() {
                         ed.selection.setContent('[rumbletalk-chat hash="insert here your chat hash"]');
                    }
               });
          },
          createControl : function(n, cm) {
               return null;
          },
     });
     /* Start the buttons */
     tinymce.PluginManager.add( 'rumbletalk_mce_buttons', tinymce.plugins.RumbleTalkButtons );
})();
