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

jQueryExaport(function ($) {
	$(function () {
		var select = $("#id_itemgrade");
		if (select.length) {
			var slider = $("<div id='slider'></div>").insertAfter(select).slider({
				min: 0,
				max: 100,
				range: "min",
				value: select[0].selectedIndex + 1,
				slide: function (event, ui) {
					select[0].selectedIndex = ui.value - 1;
				}
			});
			$("#id_itemgrade").change(function () {
				slider.slider("value", this.selectedIndex + 1);
			});
		}
	});

	$(function () {
		// disable moodle 'really leave this page?' message
		window.setTimeout(function () {
			window.onbeforeunload = null;
		}, 500);
	});
});
