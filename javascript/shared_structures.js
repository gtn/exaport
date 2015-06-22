
jQueryExaport(function($){

	$('#block-exaport-courses').change(function(){
		var val = $(this).val();
		if (val) {
			document.location.href = $(this).attr('url').replace('%courseid%', val);
		}
	});
	
	$('.view-group-header').on('click', function(){
		$(this).parents('.view-group').toggleClass('view-group-open');
	});
});
