(function() {
	tinymce.PluginManager.requireLangPack('samb');
	 
	tinymce.create('tinymce.plugins.samb', {
		
		init : function(ed, url) {
      this.editor = ed;
      
			ed.addCommand('samb', function() {
				var se = ed.selection;
					
				ed.windowManager.open({
					file : url + '/dialog.php',
					width : 450 + parseInt(ed.getLang('samb.delta_width', 0)),
					height : 280 + parseInt(ed.getLang('samb.delta_height', 0)),
					inline : 1
				}, {
					plugin_url : url 
				});
			});

			ed.addButton('samb', {
				title : 'samb.insert_samb',
				cmd : 'samb',
				image : url + '/img/sam.png'
			});

			ed.onNodeChange.add(function(ed, cm, n, co) {
				//cm.setActive('svg', !co);
				cm.setDisabled('samg', !co);
			});
		},
		
		getInfo : function() {
			return {
					longname  : 'Simple Ads Manager',
					author 	  : 'minimus',
					authorurl : 'http://blogcoding.ru/',
					infourl   : 'http://www.simplelib.com/',
					version   : "0.1.1"
			};
		}
	});

	tinymce.PluginManager.add('samb', tinymce.plugins.samb);
})();