
$(function(){

	//$('.view-group-header').click(function(){
	$('.view-data, .view-sharing').find('.view-group-header').css('cursor', 'pointer').click(function(){
		$(this).parents('.view-group').toggleClass('view-group-open');
	});

	

	function viewNameChange()
	{
		$('#view-name').html(this.value);
	}
	$('form :input[name=name]').change(viewNameChange).keyup(viewNameChange).keyup();



	var originalOptions = [];
	$(".portfolioOptions").each(function(i){
		originalOptions[i] = $(this).html();
	});
	function resetElements()
	{
		// listenelemente zurücksetzen
		$('.portfolioOptions').each(function(i){
			$(this).html(originalOptions[i]);
		});
		resetElementStates();
	}
	function resetElementStates()
	{
		$('.portfolioOptions li').removeClass('selected');
		$('.portfolioDesignBlocks li').each(function(){
			$('.portfolioOptions li[itemid='+$(this).data('portfolio').itemid+']').addClass('selected');
		});
	}


	
	function updateBlockData()
	{
		var blocks = [];
		$('.portfolioDesignBlocks').each(function(positionx){
			$(this).find('li:visible').not('.block-placeholder').each(function(positiony){
				blocks.push($.extend($(this).data('portfolio'), {
					positionx: positionx+1,
					positiony: positiony+1
				}));
			});
		});

		$('form :input[name=blocks]').val($.toJSON(blocks));
	}



	function deleteItemClick()
	{
		$(this).parents('.item').remove();
		resetElementStates();
		updateBlockData();
	}

	function generateItem(type, data)
	{
		var $item;
		if (type == 'new') {
			$item = $('<li></li>');
			$item.data('portfolio', data);
		} else {
			$item = $(data);
			data = $item.data('portfolio');
			if (!data) {
				data = {};
				if ($item.attr('itemid')) {
					data.type = 'item';
					data.itemid = $item.attr('itemid');
				} else {
					data.type = $item.attr('block-type');
				}
				// store data
				$item.data('portfolio', data);
			}
		}

		$item.addClass('item');
		$item.css('position', 'relative');
		/*
		// bug, wenn auf relativ setzen
		if ($.browser.msie) {
			$item.css('height', '1%');
		}
		*/

		var header_content = '';

		if (data.itemid && portfolioItems[data.itemid]) {
			data.type = 'item';

			var itemData = portfolioItems[data.itemid];

			$item.html(
				'<div class="header">'+$E.translate('viewitem')+': '+itemData.name+'</div>' +
				'<div class="body">'+$E.translate('type')+': '+$E.translate(itemData.type)+'<br />' +
				$E.translate('category')+': '+itemData.category+'<br />' +
				$E.translate('comments')+': '+itemData.comments+'<br />' +
				'</div>'
			);
		} else if (data.type == 'personal_information') {
			$item.html(
				'<div class="header">Personal Information</div>' +
				'<div class="body">Displays your personal information</body>'
			);
			$item.find(':text').val(data.text);
		} else if (data.type == 'headline') {
			$item.html(
				'<div class="header">'+$E.translate('view_specialitem_headline')+': <input type="text" style="margin-top: -6px; margin-bottom: -3px; margin-left: 5px;" default-text="'+$E.translate('view_specialitem_headline_defaulttext')+'" /></div>'
			);
			$item.find(':text').val(data.text);
		} else {
			data.type = 'text';

			$item.html(
				'<div class="header">'+$E.translate('view_specialitem_text')+'</div>' +
				'<div class="body"><textarea default-text="'+$E.translate('view_specialitem_text_defaulttext')+'"></textarea></body>'
			);
			$item.find('textarea').val(data.text);
		}

		// insert default texts
		$item.find(':input[default-text]').focus(function(){
			$(this).removeClass('default-text');
			if ($(this).attr('default-text') == $(this).val()) {
				$(this).val('');
			}
		}).blur(function(){
			if (!$.trim($(this).val())) {
				$(this).addClass('default-text');
				$(this).val($(this).attr('default-text'));
			}
		}).blur();

		$('<a class="delete" title="'+$E.translate('delete')+'"><span>'+$E.translate('delete')+'</span></a>').appendTo($item).click(deleteItemClick);
		$item.find(':input').change(function(){
			$item.data('portfolio').text = $(this).val();
			updateBlockData();
		});

		return $item;
	}


	
	// load stored blocks
	var blocks = $('form :input[name=blocks]').val();
	if (blocks) {
		blocks = $.parseJSON(blocks);
	}
	if (!blocks) {
		// start with headline
		blocks = [{
			type: 'headline'
		}];
	}
	// generate blocks into html
	var portfolioDesignBlocks = $('.portfolioDesignBlocks');
	$.each(blocks, function(){
		generateItem('new', this).appendTo(
			// wenn vorhanden zur richtigen spalte hinzufügen, sonst immer zur 1ten
			(this.positionx && portfolioDesignBlocks[this.positionx-1]) ? portfolioDesignBlocks[this.positionx-1] : portfolioDesignBlocks[0]
		);
	});
	resetElementStates();
	updateBlockData();


	
	$(".portfolioDesignBlocks").sortable({ 
		receive: function(e, ui){
			generateItem('update', ui.item);
			updateBlockData();
		},
		update: function(e, ui){
			updateBlockData();
		},
		handle: '.header',
		placeholder: "block-placeholder",
		forcePlaceholderSize: true,
		connectWith: ['.portfolioDesignBlocks']
	});
	$(".portfolioOptions").sortable({ 
		connectWith: ['.portfolioDesignBlocks'],
		placeholder: "block-placeholder",
		forcePlaceholderSize: true,
		stop: function(e, ui){
			// listenelemente zurücksetzen
			resetElements();
		}
		/*
		remove: function(e, ui){
			console.log(ui);
			console.log(ui.element.html());
			// ui.item.after(ui.placeholder.clone().css('visibility', ''));
			console.log('remove');
		}
		*/
	});


	ExabisEportfolio.load_userlist('views_mod');


	// sharing
	function update_sharing()
	{
		var share_text = '';
		var $form = $('#view-mod');

		if ($form.find(':input[name=externaccess]').is(':checked')) {
			share_text += $E.translate('externalaccess')+' ';
			$('#externaccess-settings').show();
		} else {
			$('#externaccess-settings').hide();
		}

		if ($form.find(':input[name=internaccess]').is(':checked')) {
			$('#internaccess-settings').show();
			if (share_text) {
				share_text += ' '+$E.translate('viewand')+' ';
			}
			share_text += $E.translate('internalaccess')+': ';
			
			if ($form.find(':input[name=shareall]:checked').val() > 0) {
				share_text += $E.translate('internalaccessall');
				$('#internaccess-users').hide();
			} else {
				share_text += $E.translate('internalaccessusers');
				$('#internaccess-users').show();
			}
		} else {
			$('#internaccess-settings').hide();
		}

		if (!share_text) {
			share_text = $E.translate('view_sharing_noaccess');
		}
		$('#view-share-text').html(share_text);
	}
	// changing the checkboxes / radiobuttons update the sharing text, visible options, etc.
	$('.view-sharing input[type=checkbox], .view-sharing input[type=radio]').click(update_sharing);
	update_sharing();
});
