define(['jquery', 'core/str'], function($, Str) {
    function user(id, shared, notify) {
        return {id: id, name: 'User ' + id, rolename: 'Learner', shared_to: shared, notify_user: notify};
    }

    function course(id, users) {
        return {id: id, fullname: 'Course ' + id, users: users, has_shared_users: users.length > 0, users_complete: false};
    }

    QUnit.module('block_exaport submitted sharing recipients', {
        beforeEach: function() {
            var test = this;
            var loaded = window.ExabisEportfolio ? $.Deferred().resolve() :
                $.getScript(M.cfg.wwwroot + '/blocks/exaport/javascript/exaport.js');
            var strings = Str.get_strings([
                {key: 'sharing_load_more_users', component: 'block_exaport'},
                {key: 'sharing_load_users_error', component: 'block_exaport'},
                {key: 'sharing_users_not_ready', component: 'block_exaport'},
                {key: 'sharing_load_groups_error', component: 'block_exaport'},
                {key: 'loading', component: 'moodle'}, {key: 'retry', component: 'moodle'}
            ]);
            return $.when(loaded, strings).then(function() {
                test.originalGetJSON = $.getJSON;
                test.requests = [];
                $.getJSON = function(url, params, success) {
                    var response = $.Deferred();
                    if (success) {
                        response.done(success);
                    }
                    test.requests.push({params: params, response: response});
                    return response.promise();
                };
                test.show = function(submitted, alwaysNotify) {
                    window.ExabisEportfolio.userlist_loaded = false;
                    window.ExabisEportfolio.grouplist_loaded = false;
                    $('#qunit-fixture').html('<form><section data-region="exaport-sharing">' +
                        '<input name="id" value="7"><input name="exaport_userlist_ready" value="0">' +
                        '<input name="exaport_grouplist_ready" value="0">' +
                        '<input id="alwaysnotifywhenshare" value="' + (alwaysNotify ? '1' : '0') + '">' +
                        '<input type="checkbox" name="shareenabled" value="1" checked>' +
                        '<input type="checkbox" name="internshare" value="1" checked>' +
                        '<input type="radio" name="shareall" value="0" checked>' +
                        '<input type="radio" name="shareall" value="2">' +
                        '<div data-region="sharing-settings"><div id="sharing-userlist"></div>' +
                        '<div data-region="groups-settings"><div id="sharing-grouplist"></div></div>' +
                        '</div></section></form>');
                    test.root = $('#qunit-fixture [data-region="exaport-sharing"]');
                    test.form = test.root.closest('form');
                    if (submitted !== undefined) {
                        test.root.attr('data-submitted-sharing', JSON.stringify(submitted));
                        if (Array.isArray(submitted.groups)) {
                            var fallback = $('<div data-region="submitted-group-selection"></div>')
                                .prependTo(test.root.find('[data-region="groups-settings"]'));
                            submitted.groups.forEach(function(id) {
                                $('<input type="hidden">').attr('name', 'sharegroups[' + id + ']').val(id).appendTo(fallback);
                            });
                            test.root.find('[name="exaport_grouplist_ready"]').val('1');
                        }
                    }
                };
                return null;
            });
        },
        afterEach: function() {
            $.getJSON = this.originalGetJSON;
        }
    });

    QUnit.test('new submitted recipients and notification choices survive duplicate and lazy rosters', function(assert) {
        this.show({users: [8, 9], notify: [8], groups: [], courses: [
            course(11, [user(8, false, false), user(9, false, true)]), course(12, [])
        ]});
        window.ExabisEportfolio.load_userlist('cat_mod');
        assert.strictEqual(this.requests.length, 0, 'submitted courses replace the initial database request');
        assert.strictEqual(this.root.find('[name="exaport_userlist_ready"]').val(), '1');
        assert.ok(this.root.find('.shareusers[value="8"]').prop('checked'), 'new recipient is restored');
        assert.ok(this.root.find('.notifyusers:checkbox[value="8"]').prop('checked'), 'submitted notify is restored');
        assert.notOk(this.root.find('.notifyusers:checkbox[value="9"]').prop('checked'), 'old notify state is ignored');

        this.root.find('.course-group[courseid="12"] .course-group-title').trigger('click');
        assert.strictEqual(this.requests[0].params.action, 'userlistcourse');
        this.requests[0].response.resolve([user(8, false, false), user(9, true, true), user(4, true, true)]);
        assert.strictEqual(this.root.find('.shareusers[value="8"]:checked').length, 2, 'both copies share the pending choice');
        assert.strictEqual(this.root.find('.notifyusers:checkbox[value="8"]:checked').length, 2);
        assert.strictEqual(this.root.find('.notifyusers:checkbox[value="9"]:checked').length, 0);
        assert.notOk(this.root.find('.shareusers[value="4"]').prop('checked'), 'an unchecked old DB share cannot return');

        this.root.find('.shareusers[value="8"]').first().trigger('click');
        this.root.find('.course-group[courseid="11"] .exaport-load-course-users').trigger('click');
        this.requests[1].response.resolve([user(8, true, true), user(9, true, true), user(4, true, true)]);
        assert.strictEqual(this.root.find('.shareusers[value="8"]:checked').length, 0,
            'later roster loading cannot undo a new unchecked choice');
        this.root.find('.notifyusers:checkbox[value="9"]').first().trigger('click');
        assert.strictEqual(this.root.find('.notifyusers:checkbox[value="9"]:checked').length, 2,
            'new notification changes still synchronize across duplicate rows');
    });

    QUnit.test('a known empty submitted selection stays empty after a saved roster arrives', function(assert) {
        this.show({users: [], notify: [], groups: [], courses: [course(11, [])]});
        window.ExabisEportfolio.load_userlist('cat_mod');
        assert.strictEqual(this.root.find('[name="exaport_userlist_ready"]').val(), '1', 'empty is a complete selection');
        assert.strictEqual(this.requests.length, 0);
        this.root.find('.course-group-title').trigger('click');
        this.requests[0].response.resolve([user(4, true, true)]);
        assert.strictEqual(this.root.find('.shareusers:checked').length, 0);
        assert.strictEqual(this.root.find('.notifyusers:checked').length, 0);
    });

    QUnit.test('unknown submitted recipients use the normal initial AJAX response', function(assert) {
        this.show({users: null, notify: null, groups: [], courses: null});
        window.ExabisEportfolio.load_userlist('cat_mod');
        assert.strictEqual(this.requests.length, 1);
        assert.strictEqual(this.requests[0].params.action, 'userlist');
        assert.strictEqual(this.root.find('[name="exaport_userlist_ready"]').val(), '0', 'not ready before rendering');
        this.requests[0].response.resolve([course(11, [user(4, true, true)])]);
        assert.strictEqual(this.root.find('[name="exaport_userlist_ready"]').val(), '1');
        assert.ok(this.root.find('.shareusers[value="4"]').prop('checked'), 'unknown is not an instruction to clear');
        assert.ok(this.root.find('.notifyusers:checkbox[value="4"]').prop('checked'));
    });

    QUnit.test('normal GET still loads saved recipients and blocks Save on an initial request failure', function(assert) {
        this.show();
        window.ExabisEportfolio.load_userlist('cat_mod');
        this.requests[0].response.reject();
        assert.strictEqual(this.root.find('[name="exaport_userlist_ready"]').val(), '0');
        var submit = $.Event('submit');
        this.form.triggerHandler(submit);
        assert.ok(submit.isDefaultPrevented(), 'an unavailable selection cannot silently remove saved shares');
        this.root.find('#sharing-userlist button').trigger('click');
        this.requests[1].response.resolve([course(11, [user(4, true, true)])]);
        assert.strictEqual(this.root.find('[name="exaport_userlist_ready"]').val(), '1');
        assert.ok(this.root.find('.shareusers[value="4"]').prop('checked'));
    });

    QUnit.test('incomplete submitted course data never becomes ready or falls back to saved recipients', function(assert) {
        var incomplete = course(11, []);
        incomplete.has_shared_users = true;
        this.show({users: [], notify: [], groups: [], courses: [incomplete]});
        window.ExabisEportfolio.load_userlist('cat_mod');
        assert.strictEqual(this.requests.length, 0);
        assert.strictEqual(this.root.find('[name="exaport_userlist_ready"]').val(), '0');
        this.root.find('#sharing-userlist button').trigger('click');
        assert.strictEqual(this.requests.length, 0, 'retry retains the submitted audience instead of requesting DB shares');
        assert.strictEqual(this.root.find('[name="exaport_userlist_ready"]').val(), '0');
    });

    QUnit.test('a rendering retry retains newly submitted recipients until they render successfully', function(assert) {
        this.show({users: [8], notify: [], groups: [], courses: [course(11, [user(8, false, false)])]});
        var originalGetString = M.util.get_string;
        var failOnce = true;
        M.util.get_string = function(key) {
            if (key === 'sharing_load_more_users' && failOnce) {
                failOnce = false;
                throw new Error('Render failure');
            }
            return originalGetString.apply(this, arguments);
        };
        try {
            window.ExabisEportfolio.load_userlist('cat_mod');
            assert.strictEqual(this.root.find('[name="exaport_userlist_ready"]').val(), '0');
            this.root.find('#sharing-userlist button').trigger('click');
            assert.strictEqual(this.requests.length, 0);
            assert.strictEqual(this.root.find('[name="exaport_userlist_ready"]').val(), '1');
            assert.ok(this.root.find('.shareusers[value="8"]').prop('checked'), 'the new recipient remains submitted');
        } finally {
            M.util.get_string = originalGetString;
        }
    });

    QUnit.test('always-notify still submits enabled hidden notifications for every selected duplicate', function(assert) {
        this.show({users: [8], notify: [], groups: [], courses: [
            course(11, [user(8, false, false)]), course(12, [user(8, false, false)])
        ]}, true);
        window.ExabisEportfolio.load_userlist('views_mod');
        assert.strictEqual(this.root.find('.notifyusers:checkbox:checked:disabled').length, 2);
        assert.strictEqual(this.root.find('.notifyusers[type="hidden"]:enabled').length, 2);
        this.root.find('.shareusers[value="8"]').first().trigger('click');
        assert.strictEqual(this.root.find('.notifyusers[type="hidden"]:enabled').length, 0);
        this.root.find('.shareusers[value="8"]').last().trigger('click');
        assert.strictEqual(this.root.find('.notifyusers:checkbox:checked:disabled').length, 2);
        assert.strictEqual(this.form.serializeArray().filter(function(field) {
            return field.name === 'notifyusers[8]';
        }).length, 2, 'disabled notification checkboxes retain their normal hidden-field submission');
    });

    QUnit.test('group choices survive a submitted off state, including explicitly selecting no groups', function(assert) {
        var test = this;
        [[22], []].forEach(function(selected) {
            test.show({users: null, notify: null, groups: selected, courses: null});
            test.root.find('[name="shareenabled"]').prop('checked', false);
            test.root.find('[name="shareall"][value="2"]').prop('checked', true);
            // Enabling the form later starts the permitted-groups request as usual.
            test.root.find('[name="shareenabled"]').prop('checked', true);
            window.ExabisEportfolio.load_grouplist('cat_mod');
            var request = test.requests[test.requests.length - 1];
            assert.strictEqual(request.params.action, 'grouplist', 'group availability still comes from the server');
            assert.strictEqual(test.root.find('[name="exaport_grouplist_ready"]').val(), '1');
            request.response.resolve([{id: 11, name: 'Course', groups: [
                {id: 11, name: 'Old DB group', member_cnt: 1, shared_to: true},
                {id: 22, name: 'New selected group', member_cnt: 1, shared_to: false}
            ]}]);
            assert.notOk(test.root.find('.sharegroups[value="11"]').prop('checked'), 'old DB group remains unchecked');
            assert.strictEqual(test.root.find('.sharegroups[value="22"]').prop('checked'), selected.length > 0);
            assert.strictEqual(test.root.find('[name="exaport_grouplist_ready"]').val(), '1');
            assert.strictEqual(test.root.find('[data-region="submitted-group-selection"]').length, 0);
        });
    });

    QUnit.test('restored groups remain submitted through loading and failures, then real checkboxes take over', function(assert) {
        var test = this;
        [[22], []].forEach(function(selected) {
            test.show({users: null, notify: null, groups: selected, courses: null});
            var postedGroups = function() {
                return test.form.serializeArray().filter(function(field) {
                    return field.name.indexOf('sharegroups[') === 0;
                }).map(function(field) {
                    return Number(field.value);
                });
            };
            window.ExabisEportfolio.load_grouplist('cat_mod');
            assert.deepEqual(postedGroups(), selected, 'a second POST retains the known selection while loading');
            assert.strictEqual(test.root.find('[name="exaport_grouplist_ready"]').val(), '1');
            test.requests[test.requests.length - 1].response.reject();
            assert.deepEqual(postedGroups(), selected, 'network failure cannot discard restored group inputs');
            assert.strictEqual(test.root.find('[name="exaport_grouplist_ready"]').val(), '1');
            test.root.find('#sharing-grouplist button').trigger('click');
            test.requests[test.requests.length - 1].response.resolve({error: true});
            assert.deepEqual(postedGroups(), selected, 'malformed responses retain the selection on retry');
            test.root.find('#sharing-grouplist button').trigger('click');
            test.requests[test.requests.length - 1].response.resolve([{name: 'Groups', groups: [
                {id: 11, name: 'Old group', member_cnt: 1, shared_to: true},
                {id: 22, name: 'New group', member_cnt: 1, shared_to: false}
            ]}]);
            assert.deepEqual(postedGroups(), selected, 'handover introduces no duplicate submitted group values');
            assert.strictEqual(test.root.find('[data-region="submitted-group-selection"]').length, 0);
            test.root.find('.sharegroups[value="22"]').prop('checked', false).triggerHandler('click');
            assert.deepEqual(postedGroups(), [], 'unchecking after handover never reintroduces the fallback');
            assert.strictEqual(test.root.find('#sharing-grouplist button').length, 0, 'success removes Retry');
        });
    });

    QUnit.test('unknown submitted groups load saved checks normally and become ready only after rendering', function(assert) {
        this.show({users: null, notify: null, groups: null, courses: null});
        window.ExabisEportfolio.load_grouplist('cat_mod');
        assert.strictEqual(this.root.find('[name="exaport_grouplist_ready"]').val(), '0');
        this.requests[0].response.resolve([{id: 11, name: 'Course', groups: [
            {id: 22, name: 'Saved group', member_cnt: 1, shared_to: true}
        ]}]);
        assert.ok(this.root.find('.sharegroups[value="22"]').prop('checked'), 'unknown groups are not cleared');
        assert.strictEqual(this.root.find('[name="exaport_grouplist_ready"]').val(), '1');
    });

    QUnit.test('a partial group render is removed while restored POST inputs remain available for Retry', function(assert) {
        this.show({users: null, notify: null, groups: [22], courses: null});
        var originalHtml = $.fn.html;
        var failOnce = true;
        $.fn.html = function(value) {
            var result = originalHtml.apply(this, arguments);
            if (failOnce && this.is('#sharing-grouplist') && typeof value === 'string' &&
                    value.indexOf('name="sharegroups[') >= 0) {
                failOnce = false;
                throw new Error('Group render failed after inserting controls');
            }
            return result;
        };
        try {
            window.ExabisEportfolio.load_grouplist('cat_mod');
            var courses = [{name: 'Groups', groups: [
                {id: 22, name: 'New group', member_cnt: 1, shared_to: false}
            ]}];
            this.requests[0].response.resolve(courses);
            assert.strictEqual(this.root.find('#sharing-grouplist .sharegroups').length, 0, 'partial controls are removed');
            assert.strictEqual(this.root.find('[name="exaport_grouplist_ready"]').val(), '1');
            assert.strictEqual(this.form.serializeArray().filter(function(field) {
                return field.name === 'sharegroups[22]';
            }).length, 1, 'the accepted selection still submits exactly once');
            this.root.find('#sharing-grouplist button').trigger('click');
            this.requests[1].response.resolve(courses);
            assert.strictEqual(this.root.find('[data-region="submitted-group-selection"]').length, 0);
            assert.ok(this.root.find('.sharegroups[value="22"]').prop('checked'), 'Retry uses the submitted selection');
        } finally {
            $.fn.html = originalHtml;
        }
    });

    QUnit.test('failed, malformed and unrenderable group responses keep the group audience unknown', function(assert) {
        var test = this;
        ['failed', 'malformed', 'unrenderable'].forEach(function(failure) {
            test.show({users: null, notify: null, groups: null, courses: null});
            window.ExabisEportfolio.load_grouplist('cat_mod');
            var response = test.requests[test.requests.length - 1].response;
            if (failure === 'failed') {
                response.reject();
            } else {
                response.resolve(failure === 'malformed' ? {error: true} : [{id: 11, name: 'Course', groups: [null]}]);
            }
            assert.strictEqual(test.root.find('[name="exaport_grouplist_ready"]').val(), '0', failure);
            assert.strictEqual(test.root.find('#sharing-grouplist .sharegroups').length, 0, 'no partial controls remain');
            assert.strictEqual(test.root.find('#sharing-grouplist button').length, 1, 'the list can be retried');
        });
    });
});
