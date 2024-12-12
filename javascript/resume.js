// This file is part of Exabis Eportfolio (extension for Moodle)
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>.

jQueryExaport(function ($) {
  $('.expandable-head').on('click', function (e) {
    $(this).parents('td').children('.expandable-text').toggleClass('hidden');
    e.preventDefault();
    e.stopPropagation();
  });

  $('.view-group-header').on('click', function () {
    $(this).parents('.view-group').toggleClass('view-group-open');
  });

  $('.collapsible-actions .expandall').on('click', function (e) {
    e.preventDefault();
    $('.view-group').addClass('view-group-open');
    $('.collapsible-actions a').toggleClass('hidden');
  });

  $('.collapsible-actions .collapsall').on('click', function (e) {
    e.preventDefault();
    $('.view-group-open').removeClass('view-group-open');
    $('.collapsible-actions a').toggleClass('hidden');
  });

  var hash = window.location.hash.substring(1);
  if (hash) {
    $('a[name=' + hash + ']').parents('.view-group').toggleClass('view-group-open');
    $('a[name=' + hash + ']').parents('table.generaltable').find('.expandable-text').toggleClass('hidden');
  }

  if ($('#comptree').length) {
    // Delete cookie for treeview.
    // TODO: why?!?
    document.cookie = 'comptree=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';
    // Open all checked uls.
    $("#comptree input:checked").parents('ul').show();
    $("#comptree input:checked").parents('ul').attr('rel', 'open');

    // Create tree for competencies.
    ddtreemenu.createTree("comptree", true);
  }
});
