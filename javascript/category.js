jQueryExaport(function($){

	//ExabisEportfolio.load_userlist();
	$('#sharing-userlist').html('loading userlist...');
	$('#sharing-grouplist').html('loading grouplist...');
	
	// sharing
	function update_sharing()
	{
		var share_text = '';
		var $form = $('#mform1');

		if ($form.find(':input[name=internshare]').is(':checked')) {
			$('#internaccess-settings').show();
			$('#internaccess-groups').hide();

			if ($form.find(':input[name=shareall]:checked').val() == 1) { 
				$('#internaccess-users').hide();
				$('#internaccess-groups').hide();
			} else if ($form.find(':input[name=shareall]:checked').val() == 2) {
				$('#internaccess-users').hide();
				$('#internaccess-groups').show();
                ExabisEportfolio.load_grouplist('cat_mod');
			} else {
				$('#internaccess-groups').hide();
				$('#internaccess-users').show();
                ExabisEportfolio.load_userlist('cat_mod');
			}
		} else {
			$('#internaccess-settings').hide();
		}
	}
	
	$(function(){
		// changing the checkboxes / radiobuttons update the sharing text, visible options, etc.
		$('#mform1 input[type=checkbox], #mform1 input[type=radio]').click(update_sharing);
		update_sharing();
	});
	
});
