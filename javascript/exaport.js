
window.jQueryExaport = jQuery.noConflict(true);

(function($){
	$.empty = function(obj) {
		if (!obj) {
			return true;
		}

		for ( key in obj) {
			return false;
		}
		return true;
	};

	window.ExabisEportfolio = $E = {};

	
	$.extend($E, {
		courseid: 1,

		translations: null,

		translate: function(key)
		{
			if (this.translations[key] == undefined) {
				return '[[js['+key+']js]]';
			} else {
				return this.translations[key];
			}
		},

		setTranslations: function(translations) {
			this.translations = translations;
		},

		userlist_loaded: false,
		structure_userlist_loaded: false,
		load_userlist: function(type, target)
		{
			target = typeof target !== 'undefined' ? target : '';			
			if (target == 'structure_') {
				if (this.structure_userlist_loaded) {
					return;
				}
				this.structure_userlist_loaded = true;				
			} else {
				if (this.userlist_loaded) {
					return;
				}
				this.userlist_loaded = true;				
			}
			$('#'+target+'sharing-userlist').html('loading userlist...');

			$.getJSON(document.location.href, {action: target+'userlist'}, function(courses){
				var html = '';

				if (!$.empty(courses)) {
					$.each(courses, function(tmp, course){
						html += '<fieldset class="course-group"><legend class="course-group-title">' +
								($E.courseid == course.id ? '<b>' : '') +
								course.fullname +
								($E.courseid == course.id ? '</b>' : '') +
								'</legend>';

						html += '<div class="course-group-content">';
						if (!$.empty(course.users)) {
							html += "<table width=\"70%\">";
							html += "<tr><th align=\"center\">&nbsp;</th>";
							if (type == 'views_mod' && target == '') html += "<th align=\"center\">&nbsp;</th>";
							html += "<th align=\"left\">"+$E.translate('name')+"</th><th align=\"right\">"+$E.translate('role')+"</th></tr>";

							html += '<tr><td align=\"center\" width="5%">';
							html += '<input class="shareusers-check-all" courseid="'+course.id+'" type="checkbox" />';
							html += "<br />"+$E.translate('checkall');
							html += "</td></tr>";

							$.each(course.users, function(tmp, user){
								html += '<tr><td align=\"center\" width="5%">';
								html += '<input class="shareusers" type="checkbox" courseid="'+course.id+'" name="'+target+'shareusers['+user.id+']" value="'+user.id+'"' +
									(user.shared_to ? ' checked="checked"' : '') +
									' />';
								if (type == 'views_mod' && target == '') {
									html += "<br />"+$E.translate('sharejs');
									html += '</td><td align=\"center\" width="5%" style="padding-right: 20px;">';
									html += '<input class="notifyusers" type="checkbox" disabled="disabled" name="notifyusers['+user.id+']" value="'+user.id+'" />';
									html += "<br />"+$E.translate('notify');
								}
								html += "</td><td align=\"center\" width='45%'>" + user.name + "</td><td align=\"center\" width='45%'>" + user.rolename + "</td></tr>";
							});

							html += "</table>";
						} else {
							html += $E.translate('nousersfound');
						}
						html += '</div>';
						html += "</fieldset>";
					});
				} else {
					html += '<b>'+$E.translate('nousersfound')+'</b>';
				}

				$('#'+target+'sharing-userlist').html(html);
				
				// set default checkboxes for category
				if (typeof sharedusersarr != 'undefined') { // In view sharing this array is undefined
					if (sharedusersarr.length > 0) {
						$.each(sharedusersarr, function(tmp, userid){
							$('#mform1 #internaccess-users input:checkbox[value='+userid+']').attr("checked", true);
						})
					}
				} 
				if (typeof structure_sharedusersarr != 'undefined') {
					if (structure_sharedusersarr.length > 0) {
						$.each(structure_sharedusersarr, function(tmp, userid){
							$('#mform1 #structure_sharing-users input:checkbox[value='+userid+']').attr("checked", true);
						})
					}						
				}
				// CHECK ALL buttons
				$('#'+target+'sharing-userlist .shareusers-check-all').click(function(){
					// check/uncheck all users in this course
					$('#'+target+'sharing-userlist .shareusers:checkbox[courseid='+$(this).attr('courseid')+']')
						.prop('checked', $(this).is(':checked'))
						// execute click handler
						.each(function(){
							// wrapped in each, because triggerHandler only works on first element
							$(this).triggerHandler('click');
						});
				});
				
				/*
				$('#sharing-userlist .shareusers:checkbox, #sharing-userlist .notifyusers:checkbox').click(function(){
					// check/uncheck this user in other courses
					$('#sharing-userlist :checkbox[name="'+this.name+'"]').attr('checked', this.checked);
				});
				*/

                // stop slow loading
<<<<<<< HEAD
				$('#sharing-userlist .shareusers:checkbox').click(function(){
=======
				$('#'+target+'sharing-userlist .shareusers:checkbox').click(function(){
>>>>>>> experimental
					// enable/disable notifyuser, according to shared users checkbox
					var $notifyboxes = $(this).closest('tr').find('.notifyusers');

					$notifyboxes.attr('disabled', !this.checked);
					if (!this.checked) {
						$notifyboxes.prop('checked', false);
					}
					
					// check/uncheck all users
<<<<<<< HEAD
					var $courseCheckboxes = $('#sharing-userlist .shareusers:checkbox[courseid='+$(this).attr('courseid')+']');
					$('#sharing-userlist .shareusers-check-all[courseid='+$(this).attr('courseid')+']').prop('checked', $courseCheckboxes.not(':checked').length == 0);
				});
                $('.course-group-content').each(function(){
                    var flag = 0;
                    $(this).find( 'table > tbody > tr > td > input.shareusers').each(function(){
=======
					var $courseCheckboxes = $('#'+target+'sharing-userlist .shareusers:checkbox[courseid='+$(this).attr('courseid')+']');
					$('#'+target+'sharing-userlist .shareusers-check-all[courseid='+$(this).attr('courseid')+']').prop('checked', $courseCheckboxes.not(':checked').length == 0);
				});
                $('.course-group-content').each(function(){
                    var flag = 0;
                    $(this).find( 'table > tbody > tr > td > input.shareusers').each(function(){
                        if (flag==1)
                            return false;
                        if ($(this).prop('checked')==false)
                            flag = 1;

                        var $notifyboxes = $(this).closest('tr').find('.notifyusers');
                        $notifyboxes.attr('disabled', !this.checked);
                        if (!this.checked) {
                            $notifyboxes.prop('checked', false);
                        }
                    });
                    if (flag == 0) {
                        $(this).find('table > tbody > tr > td > input.shareusers-check-all').prop('checked', true);
                    }
                });

				// open/close course group
				$('.course-group-title').on('click', function(){
					$(this).closest('.course-group').toggleClass('course-group-open');
				});
				// open all shared courses
				$('.course-group').has('input:checked').addClass('course-group-open');
			});
		},

		grouplist_loaded: false,
		structure_grouplist_loaded: false,
		load_grouplist: function(type, target)
		{
			target = typeof target !== 'undefined' ? target : '';			
			if (target == 'structure_') {
				if (this.structure_grouplist_loaded) {
					return;
				}
				this.structure_grouplist_loaded = true;				
			} else {
				if (this.grouplist_loaded) {
					return;
				}
				this.grouplist_loaded = true;
			};

			$('#'+target+'sharing-grouplist').html('loading grouplist...');

			$.getJSON(document.location.href, {action: target+'grouplist'}, function(courses){
				var html = '';

				if (!$.empty(courses)) {
					$.each(courses, function(tmp, course){
						html += '<fieldset class="course-group"><legend class="course-group-title">' +
								($E.courseid == course.id ? '<b>' : '') +
								course.fullname +
								($E.courseid == course.id ? '</b>' : '') +
								'</legend>';

						html += '<div class="course-group-content">';
						if (!$.empty(course.groups)) {
							html += "<table width=\"70%\">";
							html += "<tr><th align=\"center\">&nbsp;</th>";
							if (type == 'views_mod') html += "<th align=\"center\">&nbsp;</th>";
							html += "<th align=\"left\">"+$E.translate('grouptitle')+"</th><th align=\"right\">"+$E.translate('membersnumber')+"</th></tr>";

							html += '<tr><td align=\"center\" width="5%">';
							html += '<input class="sharegroups-check-all" courseid="'+course.id+'" type="checkbox" />';
							html += "<br />"+$E.translate('checkall');
							html += "</td></tr>";

							$.each(course.groups, function(tmp, group){
								html += '<tr><td align=\"center\" width="5%">';
								html += '<input class="sharegroups" type="checkbox" courseid="'+course.id+'" name="'+target+'sharegroups['+group.id+']" value="'+group.id+'"' +
									(group.shared_to ? ' checked="checked"' : '') +
									' />';
								html += "</td><td align=\"center\" width='45%'>" + group.title + "</td><td align=\"center\" width='45%'>" + group.members + "</td></tr>";
							});

							html += "</table>";
						} else {
							html += $E.translate('nogroupsfound');
						}
						html += '</div>';
						html += "</fieldset>";
					});
				} else {
					html += '<b>'+$E.translate('nogroupsfound')+'</b>';
				}

				$('#'+target+'sharing-grouplist').html(html);
				// set default checkboxes for category
				if (typeof sharedgroupsarr != 'undefined') { // In view sharing this array is undefined
					if (sharedgroupsarr !== undefined && sharedgroupsarr.length > 0) {
						$.each(sharedgroupsarr, function(tmp, groupid){
							$('#mform1 #internaccess-groups input:checkbox[value='+groupid+']').attr("checked", true);
						})
					}
				} 
				if (typeof structure_sharedgroupsarr != 'undefined') { 
					if (structure_sharedgroupsarr !== undefined && structure_sharedgroupsarr.length > 0) {
						$.each(structure_sharedgroupsarr, function(tmp, groupid){
							$('#mform1 #structure_sharing-groups input:checkbox[value='+groupid+']').attr("checked", true);
						})
					}
				};				
				
				$('#'+target+'sharing-grouplist .sharegroups-check-all').click(function(){
					// check/uncheck all groups in this course
					$('#'+target+'sharing-grouplist .sharegroups:checkbox[courseid='+$(this).attr('courseid')+']')
						.prop('checked', $(this).is(':checked'))
						// execute click handler
						.each(function(){
							// wrapped in each, because triggerHandler only works on first element
							$(this).triggerHandler('click');
						});
				});

                // stop slow loading
				$('#'+target+'sharing-grouplist .sharegroups:checkbox').click(function(){
					// check/uncheck all groups
					var $courseCheckboxes = $('#'+target+'sharing-grouplist .sharegroups:checkbox[courseid='+$(this).attr('courseid')+']');
					$('#'+target+'sharing-grouplist .sharegroups-check-all[courseid='+$(this).attr('courseid')+']').prop('checked', $courseCheckboxes.not(':checked').length == 0);
				});
                $('.course-group-content').each(function(){
                    var flag = 0;
                    $(this).find( 'table > tbody > tr > td > input.sharegroups').each(function(){
>>>>>>> experimental
                        if (flag==1)
                            return false;
                        if ($(this).prop('checked')==false)
                            flag = 1;
<<<<<<< HEAD

                        var $notifyboxes = $(this).closest('tr').find('.notifyusers');
                        $notifyboxes.attr('disabled', !this.checked);
                        if (!this.checked) {
                            $notifyboxes.prop('checked', false);
                        }
                    });
                    if (flag == 0) {
                        $(this).find('table > tbody > tr > td > input.shareusers-check-all').prop('checked', true);
=======
                    });
                    if (flag == 0) {
                        $(this).find('table > tbody > tr > td > input.sharegroups-check-all').prop('checked', true);
>>>>>>> experimental
                    }
                });

				// open/close course group
				$('.course-group-title').on('click', function(){
					$(this).closest('.course-group').toggleClass('course-group-open');
				});
				// open all shared courses
				$('.course-group').has('input:checked').addClass('course-group-open');
			});
		}

	});

	$(function(){
		if ($('body').attr('class').match(/course-([^\s]+)/)) {
			$E.courseid = RegExp.$1;
		}
	});
	
})(jQueryExaport);
