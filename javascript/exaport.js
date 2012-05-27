
(function(){
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
		load_userlist: function()
		{
			if (this.userlist_loaded) {
				return;
			}
			this.userlist_loaded = true;

			$('#sharing-userlist').html('loading userlist...');

			$.getJSON(document.location.href, {action: 'userlist'}, function(courses){
				var html = '';

				if (!$.empty(courses)) {
					$.each(courses, function(tmp, course){
						html += '<fieldset><legend>' +
								($E.courseid == course.id ? '<b>' : '') +
								course.fullname +
								($E.courseid == course.id ? '</b>' : '') +
								'</legend>';

						if (!$.empty(course.users)) {
							html += "<table width=\"70%\">";
							html += "<tr><th align=\"center\">&nbsp;</th><th align=\"left\">"+$E.translate('name')+"</th><th align=\"right\">"+$E.translate('role')+"</th></tr>";

							$.each(course.users, function(tmp, user){
								html += '<tr><td align=\"center\" width="30" style="padding-right: 20px;">';
								html += '<input type="checkbox" name="shareusers['+user.id+']" value="'+user.id+'"' +
									(typeof sharedUsers[user.id] != 'undefined' ? ' checked' : '') +
									' />';
								html += "</td><td align=\"left\">" + user.name + "</td><td align=\"right\">" + user.rolename + "</td></tr>";
							});

							html += "</table>";
						} else {
							html += $E.translate('nousersfound');
						}
						html += "</fieldset>";
					});
				} else {
					html += '<b>'+$E.translate('nousersfound')+'</b>';
				}

				$('#sharing-userlist').html(html);

				$('#sharing-userlist :checkbox').click(function(){
					// check/uncheck this user in other courses
					$('#sharing-userlist :checkbox[name="'+this.name+'"]').attr('checked', this.checked);
				});
			});
		}
	});

	$(function(){
		if ($('body').attr('class').match(/course-([^\s]+)/)) {
			$E.courseid = RegExp.$1;
		}
	});

})();
