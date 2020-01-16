jQuery(function($){
	function arrUnique(value, index, self) { 
	    return self.indexOf(value) === index;
	}

	var ajax_scan = null,
		results = $('#woocrl-results'),
		message = $('#woocrl-message'),
		btn_do = $('#woocrl_do'),
		btn_stop = $('#woocrl_stop');

	function woo_crawler(source_url) {
		ajax_scan = setTimeout(function(){
			if(source_url.length>0) {
				btn_do.html('Scanning...');
				btn_do.prop('disabled', true);
				btn_stop.prop('disabled', false);

				var crawl_url = source_url.shift();
				
					$.ajax({
						url: woocrl.ajax_url+'?action=woocrl_scan',
						type: 'POST',
						data: {su:crawl_url},
						dataType: 'json',
						success: function(res) {
							var msg = (res['error']!='')?res['crawled_url']+' - '+res['error'] : res['crawled_url'];
							message.html(msg);
							//console.log(res);
							var next_crawl = source_url.concat(res['next_crawl']);
							next_crawl = next_crawl.filter(arrUnique);

							woo_crawler(next_crawl);
						},
						error: function(xhr,status,err) {
							woo_crawler(source_url);
						}

					});
				

			} else {
				btn_do.html('Get products');
				btn_do.prop('disabled', false);
				btn_stop.prop('disabled', true);
			}
		}, 1000);
	}

	btn_do.on('click', function(e){
		var su = [$('#woocrl_su').val()],
			ua = $('#woocrl_ua').val();
		if(su!='') {
			$.ajax({
				url: woocrl.ajax_url+'?action=woocrl_remove_crawled',
				data: {ua:ua},
				success: function(res) {
					woo_crawler(su);
				}
			});
		} else {
			alert('Input your source url!');
		}
	});

	btn_stop.on('click', function(e){
		if(ajax_scan!=null) {
			clearTimeout(ajax_scan);
		}
		btn_do.html('Get products');
		btn_do.prop('disabled', false);
		btn_stop.prop('disabled', true);
	});
});