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
	if (hash.search('goals') >= 0) 
		hash = 'goals';
	if (hash.search('skills') >= 0) 
		hash = 'skills';
	$('a[name='+hash+']').parents('.view-group').toggleClass('view-group-open');	
	
	// delete cookie for treeview 
	document.cookie = 'comptree=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';
	// open all checked uls
	$("#comptree input:checked").parents('ul').show();
	$("#comptree input:checked").parents('ul').attr('rel', 'open');
	// create tree for competencies
	ddtreemenu.createTree("comptree", true);
});
