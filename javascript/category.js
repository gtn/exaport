// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

jQueryExaport(function($){

	//ExabisEportfolio.load_userlist();
	$('#sharing-userlist').html('loading userlist...');
	$('#sharing-grouplist').html('loading grouplist...');
	
	$('#structure_sharing-userlist').html('loading userlist...');
	$('#structure_sharing-grouplist').html('loading grouplist...');
	
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
	
	// structure sharing
	function update_structure_sharing()
	{
		var share_text = '';
		var $form = $('#mform1');
		if ($form.find(':input[name=structure_share]').is(':checked')) {
			$('#structureshare-settings').show();
			$('#structure_sharing-groups').hide();
			if ($form.find(':input[name=structure_shareall]:checked').val() == 1) { 
				$('#structure_sharing-users').hide();
				$('#structure_sharing-groups').hide();
			} else if ($form.find(':input[name=structure_shareall]:checked').val() == 2) {
				$('#structure_sharing-users').hide();
				$('#structure_sharing-groups').show();
				ExabisEportfolio.load_grouplist('cat_mod', 'structure_');
			} else {
				$('#structure_sharing-groups').hide();
				$('#structure_sharing-users').show();
				ExabisEportfolio.load_userlist('cat_mod', 'structure_');
			}
		} else {
			$('#structureshare-settings').hide();
		}
	}
	
	$(function(){
		// changing the checkboxes / radiobuttons update the sharing text, visible options, etc.
		$('#mform1 input[type=checkbox], #mform1 input[type=radio]').click(update_sharing);
		update_sharing();
		// and for structure_sharing
		$('#mform1 div#fitem_id_structure_share input[type=checkbox], #mform1 div#structureshare-settings input[type=radio]').click(update_structure_sharing);
		update_structure_sharing();
	});
	
});
