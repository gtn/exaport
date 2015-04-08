jQueryExaport(function($){
	$('.expandable-head').on('click', function(e){
		$(this).parents('td').children('.expandable-text').toggleClass('hidden');
		e.preventDefault();
		e.stopPropagation();
	});

	$('.view-group-header').on('click', function(){
		$(this).parents('.view-group').toggleClass('view-group-open');
	});

	$('.collapsible-actions .expandall').on('click', function(){
		$('.view-group').addClass('view-group-open');
		//$('.view-group-open').removeClass('view-group');
		$('.collapsible-actions a').toggleClass('hidden');
	});
	
	$('.collapsible-actions .collapsall').on('click', function(){
		//$('.view-group-open').addClass('view-group');
		$('.view-group-open').removeClass('view-group-open');
		$('.collapsible-actions a').toggleClass('hidden');
	});
	
	var hash = window.location.hash.substring(1);
	$('a[name='+hash+']').parents('.view-group').toggleClass('view-group-open');	
});
