var exaportViewEdit;
var newItem = null, lastclicked = null;

define(['jquery',
        'block_exaport/jquery-json',
        'jqueryui',
        'block_exaport/touchpunch',
        'core/modal_factory',
        'core/modal_events'],
        function($, json, jqui, jquitp, modalFactory, modalEvents) {

    var dialogue;
    var helpDialogue;
    var last_popup;

    var defaultModalOptions = {
        title: 'test title',
        body: '',
        footer: '',
        //template: 'SAVE_CANCEL',
        //type: 'SAVE_CANCEL',
    };

    var exaportViewEditCreate = function() {

        var viewEdit = {
            resetViewContent: function(){
                // Load stored blocks.
                var blocks = $('form :input[name=blocks]').val();
                if (blocks) {
                    blocks = $.parseJSON(blocks);
                }
                var myresume = $('form :input[name=myresume]').val();
                if (myresume) {
                    myresume = $.parseJSON(myresume);
                }
                if (!blocks) {
                    // Start with headline.
                    blocks = [{
                        type: 'headline'
                    }];
                }
                var portfolioDesignBlocks = $('.portfolioDesignBlocks');
                portfolioDesignBlocks.empty();
                $.each(blocks, function(){
                    generateItem('new', this).appendTo(
                        // Wenn vorhanden zur richtigen spalte hinzufügen, sonst immer zur 1ten.
                        (this.positionx && portfolioDesignBlocks[this.positionx - 1]) ? portfolioDesignBlocks[this.positionx - 1] : portfolioDesignBlocks[0]
                    );
                });
                resetElementStates();
                updateBlockData();
            },

            initAddItems: function(title){
                $('#add-items-list .add-item').click(function(event){
                    var $input = $(this).find('input');

                    if (!$(event.target).is(':input')) {
                        // Toggle checkbox (if the user clicked the div and not the checkbox).
                        $input.prop('checked', !$input.prop('checked'));
                    }

                    if ($input.prop('checked')) {
                        $(this).addClass('checked');
                    } else {
                        $(this).removeClass('checked');
                    }
                });
            },

            addPersonalInfo: function(id) {
                if (!this.checkFields()) {
                    return;
                }
                if (id != -1) {
                    newItem = lastclicked;
                }
                var data = {};
                data.type = 'personal_information';
                data.id = id;
                data.block_title = $('#block_title').val();
                // if ($('#firstname').attr('checked') == 'checked') {
                var fncheckbox = $('.pieform input[name="fields\[firstname\]"]').first();
                if (fncheckbox !== undefined && fncheckbox.is(':checked')) {
                    data.firstname = fncheckbox.val();
                }
                // if ($('#lastname').attr('checked') == 'checked') {
                var lncheckbox = $('.pieform input[name="fields\[lastname\]"]').first();
                if (lncheckbox.is(':checked')) {
                    data.lastname = lncheckbox.val();
                }
                data.picture = $('form input[name=picture]:checked').val();
                data.email = $('form input[name=email]:checked').val();
                // ...data.text = tinyMCE.get('block_intro').getContent();.
                data.text = $('#id_block_intro').val();
                // If it is tinyMCE: use #tinymce.
                if ($('.mceEditor iframe').length) {
                    var iframe = $('.mceEditor iframe');
                    var iframetextElement = $('#tinymce', iframe.contents());
                    if (iframetextElement.length) {
                        data.text = iframetextElement.html();
                    }
                }
                newItem.data('portfolio', data);
                generateItem('update', $(newItem));
                dialogue.hide();
                newItem = null;
                saveBlockData();
            },

            addHeadline: function(id) {
                if (!this.checkFields()) {
                    return;
                }
                if (id != -1) {
                    newItem = lastclicked;
                }
                data = {};
                data.text = $('#headline').val();
                data.type = 'headline';
                data.id = id;
                newItem.data('portfolio', data);
                generateItem('update', $(newItem));
                // ...$E.last_popup.remove();.
                newItem = null;
                dialogue.hide();
                saveBlockData();
            },

            addText: function(id) {
                if (!this.checkFields()) {
                    return;
                }
                if (id != -1) {
                    newItem = lastclicked;
                }
                data = {};
                data.type = 'text';
                data.id = id;
                data.block_title = $('#block_title').val();
                // ...data.text = tinymce.get('block_text').getContent();.
                data.text = $('#id_block_text').val();
                // If it is tinyMCE: use #tinymce.
                if ($('.mceEditor iframe').length) {
                    var iframe = $('.mceEditor iframe');
                    var iframetextElement = $('#tinymce', iframe.contents());
                    if (iframetextElement.length) {
                        data.text = iframetextElement.html();
                    }
                }
                // ...data.text = $('#id_text').val();.
                newItem.data('portfolio', data);
                generateItem('update', $(newItem));
                dialogue.hide();
                newItem = null;
                saveBlockData();
            },

            addItem: function(id) {
                if (!this.checkFields()) {
                    return;
                }
                if (id != -1) {
                    newItem = lastclicked;
                }
                var i = 0;
                $('#blockform input.add-item-checkbox:checked').each(function () {
                    i = i + 1;
                    if (i > 1) {
                        var clone = $(newItem).clone();
                        newItem.after(clone);
                        newItem = clone;
                    };
                    data = {};
                    data.type = 'item';
                    data.itemid = $(this).val();
                    newItem.data('portfolio', data);
                    generateItem('update', $(newItem));
                });
                dialogue.hide();
                newItem = null;
                saveBlockData();
            },

            addCvInfo: function(id) {
                if (!this.checkFields()) {
                    return;
                }
                if (id != -1) {
                    newItem = lastclicked;
                }
                var i = 0;
                $('#blockform input.add-cvitem-checkbox:checked').each(function () {
                    i = i + 1;
                    if (i > 1) {
                        var clone = $(newItem).clone();
                        newItem.after(clone);
                        newItem = clone;
                    }
                    data = {};
                    data.type = 'cv_information';
                    data.itemid = $(this).val();
                    if ($('#blockform [name="add_withfiles"]').is(":checked")) {
                        data.resume_withfiles = 1;
                    } else {
                        data.resume_withfiles = 0;
                    }
                    data.resume_itemtype = $(this).attr('data-cvtype');
                    var goalsSkills = ['goalspersonal', 'goalsacademic', 'goalcareers',
                                       'skillspersonal', 'skillsacademic', 'skillscareers'];
                    if (goalsSkills.indexOf(data.resume_itemtype) > -1) {
                        data.itemid = null;
                    }
                    newItem.data('portfolio', data);
                    generateItem('update', $(newItem));
                });
                dialogue.hide();
                newItem = null;
                saveBlockData();
            },

            addMedia: function(id) {
                if (!this.checkFields()) {
                    return;
                }
                if (id != -1) {
                    newItem = lastclicked;
                }
                data = {};
                data.block_title = $('#block_title').val();
                data.type = 'media';
                data.contentmedia = $('#block_media').val();
                data.width = $('#block_width').val();
                data.height = $('#block_height').val();
                data.create_as_note = $('input[name=create_as_note]:checked').length ? 1 : 0;
                data.id = id;
                newItem.data('portfolio', data);
                generateItem('update', $(newItem));
                dialogue.hide();
                newItem = null;
                saveBlockData();
            },

            addBadge: function(id) {
                if (!this.checkFields()) {
                    return;
                }
                if (id != -1) {
                    newItem = lastclicked;
                }
                var i = 0;
                $('#blockform input[name="add_badges[]"]:checked').each(function () {
                    i = i + 1;
                    if (i > 1) {
                        var clone = $(newItem).clone();
                        newItem.after(clone);
                        newItem = clone;
                    };
                    data = {};
                    data.type = 'badge';
                    data.itemid = $(this).val();
                    newItem.data('portfolio', data);
                    generateItem('update', $(newItem));
                });
                dialogue.hide();
                newItem = null;
                saveBlockData();
            },

            checkFields: function() {
                var ok = true;
                $('#blockform .not-empty-check').each(function(){
                    var forInput = $(this).attr('for');
                    var input = $('#blockform').find('input[name="' + forInput + '"]');
                    if (input.length == 0) {
                        // Input not found, ignore.
                        $(this).hide();
                        return;
                    }
                    if (input.val().length) {
                        // Has value.
                        $(this).hide();
                        return;
                    }
                    // Show error.
                    $(this).show();
                    input.focus();
                    ok = false;
                    return false;
                });

                // All checks ok.
                return ok;
            },

            filterItemsByTag: function() {
                // Clear the search by title.
                $('#filterByTitle').val('')
                $('div.add-item-category').hide();
                $('div.add-item').show();
                $('div.add-item-sub').show();
                $('tr.sharedArtefacts').show();
                var selectedTag = $('.tagfilter').val();
                if (selectedTag != '') {
                    $('div.add-item').each(function() {
                        var elementTags = $(this).data('tags');
                        if (elementTags !== undefined) {
                            if (elementTags.indexOf(selectedTag) == -1) {
                                $(this).hide();
                            }
                        } else {
                            $(this).hide();
                        };
                    });
                } else {
                    $('div.add-item-category, div.add-item-sub').show();
                };
                // Hide category names if it has no visible artefact.
                $('div.add-item-sub').each(function(){
                    if ($(this).find('div.add-item:visible').length == 0) {
                        $(this).hide();
                    };
                });
                $('div.add-item:visible').each(function() {
                    var categoryId = $(this).data('category');
                    $('div.add-item-category[data-category="' + categoryId + '"]').show();
                });
                // List of shared artefacts.
                if ($('div.add-item[data-category="sharedFromUser"]:visible').length == 0) {
                    $('tr.sharedArtefacts').hide();
                }
            },

            filterItemsByTitle: function() {
                // Reset filter by tag.
                if ($('.tagfilter').length) {
                    $('.tagfilter')[0].selectedIndex = 0;
                }
                var text = $('#blockform #filterByTitle').val().toLowerCase();
                $('div.add-item-category').hide();
                $('div.add-item').show();
                $('div.add-item-sub').show();
                $('tr.sharedArtefacts').show();
                if (text != '') {
                    $('div.add-item:visible').each(function() {
                        var elementText = $(this).text();
                        if (elementText.toLowerCase().indexOf(text) > -1) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        };
                    });
                } else {
                    $('div.add-item-category, div.add-item-sub').show();
                };
                // Hide category names if it has no visible artefact.
                $('div.add-item-sub').each(function() {
                    if ($(this).find('div.add-item:visible').length == 0) {
                        $(this).hide();
                    };
                });
                $('div.add-item:visible').each(function(){
                    var categoryId = $(this).data('category');
                    $('div.add-item-category[data-category="' + categoryId + '"]').show();
                });
                // List of shared artefacts.
                if ($('div.add-item[data-category="sharedFromUser"]:visible').length == 0) {
                    $('tr.sharedArtefacts').hide();
                }
            },

            clearItemFilters: function() {
                if ($('.tagfilter').length) {
                    $('.tagfilter')[0].selectedIndex = 0;
                }
                $('#blockform #filterByTitle').val('');
                exaportViewEdit.filterItemsByTitle();
            },

            cancelAddEdit: function() {
                dialogue.hide();
                updateBlockData();
            },

        }
        return viewEdit;
    };

    function resetElementStates()
    {
        $('.portfolioOptions li').removeClass('selected');
        $('.portfolioDesignBlocks > li').each(function() {
            $('.portfolioOptions li[itemid=' + $(this).data('portfolio').itemid + ']').addClass('selected');
        });
    }

    function updateBlockData()
    {
        var blocks = [];
        $('.portfolioDesignBlocks').each(function(positionx) {
            // Immediate li children, because content can have li too.
            $(this).children('li:visible').not('.block-placeholder').each(function(positiony) {
                blocks.push($.extend($(this).data('portfolio'), {
                    positionx: positionx + 1,
                    positiony: positiony + 1
                }));
            });
        });

        var input = $('form :input[name=blocks]');
        input.val($.toJSON(blocks));
    };

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
                    if ($item.attr('text')) {
                        data.text = $item.attr('text');
                    }
                    if ($item.attr('block_title')) {
                        data.block_title = $item.attr('block_title');
                    }
                    if ($item.attr('firstname')) {
                        data.firstname = $item.attr('firstname');
                    }
                    if ($item.attr('lastname')) {
                        data.lastname = $item.attr('lastname');
                    }
                    if ($item.attr('picture')) {
                        data.picture = $item.attr('picture');
                    }
                    if ($item.attr('email')) {
                        data.email = $item.attr('email');
                    }
                }
                // Store data.
                $item.data('portfolio', data);
            }
        }

        $item.addClass('item');
        $item.css('position', 'relative');
        /*
        Bug, wenn auf relativ setzen
        if ($.browser.msie) {
            $item.css('height', '1%');
        }.
        */
        var header_content = '';
        if (data.itemid && !data.item && portfolioItems && portfolioItems[data.itemid]) {
            data.item = portfolioItems[data.itemid];
        }
        if (data.type == 'item' && data.itemid && data.item) {
            var itemData = data.item;
            var ilink = itemData.link;
            if (ilink != "") {
                ilink = $E.translate('link') + ': ' + ilink + '<br />';
            }

            var itemPictures = '';
            if (itemData.filescount > 1) {
                var imgWidth = 40;
                if (itemData.filescount > 3) {
                    imgWidth = 35;
                }
                if (itemData.filescount > 5) {
                    imgWidth = 30;
                }
                itemPictures += '<div class="pictureset" style="float:right; position: relative; text-align: right; max-width: 150px;">';
                for (var imi = 0; imi < itemData.filescount; imi++) {
                    if (imi && imi % 5 === 0) {
                        itemPictures += '<br />';
                    }
                    // itemPictures += '<div class="picture" style="float:right; position: relative; height: '+imgWidth+'px; width: '+imgWidth+'px;">';
                    itemPictures += '<div class="picture" style="float:right; height: '+imgWidth+'px; width: '+imgWidth+'px;">';
                    itemPictures += '<img style="max-width: 100%; max-height: 100%;" src="' + M.cfg['wwwroot'] + '/blocks/exaport/item_thumb.php?item_id=' + itemData.id + '&imindex='+imi+'">';
                    itemPictures += '</div>';
                }
                itemPictures += '</div>';
            } else {
                itemPictures += '<div class="picture" style="float:right; position: relative; height: 100px; width: 100px;">';
                itemPictures += '<img style="max-width: 100%; max-height: 100%;" src="' + M.cfg['wwwroot'] + '/blocks/exaport/item_thumb.php?item_id=' + itemData.id + '">';
                itemPictures += '</div>';
            }

            var tempString = '';
            tempString += '<div id="id_holder" style="display:none;"></div> ';
            tempString += '<div class="item_info" style="overflow: hidden;">';
            tempString += '<div class="header">' + $E.translate('viewitem') + ': ' + itemData.name + '</div>';
            tempString += itemPictures;
            tempString += '<div class="body">' + $E.translate('type') + ': ' + $E.translate(itemData.type) + '<br />';
            tempString += $E.translate('category') + ': ' + itemData.category + '<br />' + ilink;
            tempString += $E.translate('comments') + ': ' + itemData.comments + '<div class="exaport-item-intro"></div>';
            if (itemData.competences) {
                tempString += '<script type="text/javascript" src="javascript/wz_tooltip.js"></script><a onmouseover="Tip(\'' + itemData.competences + '\')" onmouseout="UnTip()"><img src="' + M.cfg['wwwroot'] + '/pix/t/grades.png" class="iconsmall" alt="' + 'competences' + '" /></a>';
            };
            tempString += '</div></div>';
            $item.html(tempString);
            if (!itemData.competences) {
                // User html may be malformed, so savely inject it here.
                $item.find('.exaport-item-intro').html(itemData.intro);
            }
        } else if (data.type == 'personal_information') {
            var tempString = '<div id="id_holder" style="display:none;"></div>';
            tempString += '<div class="personal_info" style="overflow: hidden;">';
            tempString += '<div class="header">' + $E.translate('personalinformation') + ': </div>';
            tempString += '<div class="picture" style="float:right; position: relative;"></div>';
            tempString += '<div class="name"></div>';
            tempString += '<div class="email"></div>';
            tempString += '<div class="body"></div>';
            tempString += '</div>';
            $item.html(tempString);
            $item.find('div.header').append(data.block_title);
            if (data.picture != '' && data.picture != null) {
                $item.find('div.picture').append('<img src="' + data.picture + '">');
            }
            $item.find('div.name').append(data.firstname); $item.find('div.name').append(' ');
            $item.find('div.name').append(data.lastname);
            $item.find('div.email').append(data.email);
            $item.find('div.body').append(data.print_text);
        } else if (data.type == 'headline') {
            var tempString = '<div id="id_holder" style="display:none;"></div>';
            tempString += '<div class="header">' + $E.translate('view_specialitem_headline') + '<div class="body"></div></div>';
            $item.html(tempString);
            $item.find('div.body').append(data.print_text);
        } else if (data.type == 'media') {
            var tempString = '<div id="id_holder" style="display:none;"></div>';
            tempString += '<div class="header"></div><div class="body"></div>';
            $item.html(tempString);
            $item.find('div.header').append($E.translate('view_specialitem_media') + ($.trim(data.block_title).length ? ': ' + data.block_title : ''));
            $item.find('div.body').append(data.contentmedia);
        } else if (data.type == 'badge') {
            if (typeof data.badge == 'undefined') {
                $item.html('loading');
            } else {
                var badge = data.badge;
                var tempString = '<div id="id_holder" style="display:none;"></div>';
                tempString += '<div class="item_info" style="overflow: hidden;">';
                tempString += '<div class="header">' + $E.translate('view_specialitem_badge') + ': ' + badge.name + '</div>';
                tempString += '<div class="picture" style="float:right; position: relative; height: 100px; width: 100px;">';
                tempString += '<img style="max-width: 100%; max-height: 100%;" src="' + badge.imageUrl + '">';
                tempString += '</div>';
                tempString += '<div class="body">' + badge.description + '</div>';
                    /*				+
                                    '</div>' +
                                    '<div class="body">'+$E.translate('type')+': '+$E.translate(itemData.type)+'<br />' +
                                    $E.translate('category')+': '+itemData.category+'<br />' + ilink +
                                    $E.translate('comments')+': '+itemData.comments+'<br />' + itemData.intro +
                                    '</div>
                                    */
                $item.html(tempString);
            }
        } else if (data.type == 'cv_information') {
            data.item = null;
            var bodyContent = '';
            var titleContent = '';
            var addToHeader = '';
            var attachments = [];
            switch (data.resume_itemtype) {
                case 'edu':
                    addToHeader = $E.translate('cofigureblock_cvinfo_education_history');
                    if (data.itemid && resumeItems && resumeItems.educations[data.itemid]) {
                        itemData = resumeItems.educations[data.itemid];
                        attachments = itemData.attachments;
                        var description = '';
                        description += '<span class="edu_institution">' + itemData.institution + ':</span> ';
                        description += '<span class="edu_qualname">' + itemData.qualname + '</span>';
                        if (itemData.startdate != '' || itemData.enddate != '') {
                            description += ' (';
                            if (itemData.startdate != '') {
                                description += '<span class="edu_startdate">' + itemData.startdate + '</span>';
                            }
                            if (itemData.enddate != '') {
                                description += '<span class="edu_enddate"> - ' + itemData.enddate + '</span>';
                            }
                            description += ')';
                        }
                        if (itemData.qualdescription != '') {
                            description += '<span class="edu_qualdescription">' + itemData.qualdescription + '</span>';
                        }
                        bodyContent = description;
                    }
                    break;
                case 'employ':
                    addToHeader = $E.translate('cofigureblock_cvinfo_employment_history');
                    if (data.itemid && resumeItems && resumeItems.employments[data.itemid]) {
                        itemData = resumeItems.employments[data.itemid];
                        attachments = itemData.attachments;
                        var description = '';
                        description += '<span class="employ_jobtitle">' + itemData.jobtitle + ':</span> ';
                        description += '<span class="employ_employer">' + itemData.employer + '</span>';
                        if (itemData.startdate != '' || itemData.enddate != '') {
                            description += ' (';
                            if (itemData.startdate != '') {
                                description += '<span class="employ_startdate">' + itemData.startdate + '</span>';
                            }
                            if (itemData.enddate != '') {
                                description += '<span class="employ_enddate"> - ' + itemData.enddate + '</span>';
                            }
                            description += ')';
                        }
                        if (itemData.positiondescription != '') {
                            description += '<span class="employ_positiondescription">' + itemData.positiondescription + '</span>';
                        }
                        bodyContent = description;
                    }
                    break;
                case 'certif':
                    addToHeader = $E.translate('cofigureblock_cvinfo_certif');
                    if (data.itemid && resumeItems && resumeItems.certifications[data.itemid]) {
                        itemData = resumeItems.certifications[data.itemid];
                        attachments = itemData.attachments;
                        var description = '';
                        description += '<span class="certif_title">' + itemData.title + '</span> ';
                        if (itemData.date != '') {
                            description += '<span class="certif_date">(' + itemData.date + ')</span>';
                        }
                        if (itemData.description != '') {
                            description += '<span class="certif_description">' + itemData.description + '</span>';
                        }
                        bodyContent = description;
                    }
                    break;
                case 'public':
                    addToHeader = $E.translate('cofigureblock_cvinfo_public');
                    if (data.itemid && resumeItems && resumeItems.publications[data.itemid]) {
                        itemData = resumeItems.publications[data.itemid];
                        attachments = itemData.attachments;
                        var description = '';
                        description += '<span class="public_title">' + itemData.title;
                        if (itemData.contribution != '') {
                            description += ' (' + itemData.contribution + ')';
                        }
                        description += '</span> ';
                        if (itemData.date != '') {
                            description += '<span class="public_date">(' + itemData.date + ')</span>';
                        }
                        if (itemData.contributiondetails != '' || itemData.url != '') {
                            description += '<span class="public_description">';
                            if (itemData.contributiondetails != '') {
                                description += itemData.contributiondetails;
                            }
                            if (itemData.url != '') {
                                description += '<br /><a href="' + itemData.url + '" class="public_url" target="_blank">' + itemData.url + '</a>';
                            }
                            description += '</span>';
                        }
                        bodyContent = description;
                    }
                    break;
                case 'mbrship':
                    addToHeader = $E.translate('cofigureblock_cvinfo_mbrship');
                    if (data.itemid && resumeItems && resumeItems.profmembershipments[data.itemid]) {
                        itemData = resumeItems.profmembershipments[data.itemid];
                        attachments = itemData.attachments;
                        var description = '';
                        description += '<span class="mbrship_title">' + itemData.title + '</span> ';
                        if (itemData.startdate != '' || itemData.enddate != '') {
                            description += ' (';
                            if (itemData.startdate != '') {
                                description += '<span class="mbrship_startdate">' + itemData.startdate + '</span>';
                            }
                            if (itemData.enddate != '') {
                                description += '<span class="mbrship_enddate"> - ' + itemData.enddate + '</span>';
                            }
                            description += ')';
                        }
                        if (itemData.description != '') {
                            description += '<span class="mbrship_description">' + itemData.description + '</span>';
                        }
                        bodyContent = description;
                    }
                    break;
                case 'goalspersonal':
                case 'goalsacademic':
                case 'goalscareers':
                case 'skillspersonal':
                case 'skillsacademic':
                case 'skillscareers':
                    attachments = resumeItems[data.resume_itemtype + '_attachments'];
                    console.log(resumeItems);
                    console.log(data.resume_itemtype + '_attachments');
                    addToHeader = $E.translate('resume_' + data.resume_itemtype);
                    var description = '';
                    if (resumeItems['' + data.resume_itemtype]) {
                        description += '<span class="' + data.resume_itemtype + '_text">' + resumeItems['' + data.resume_itemtype] + '</span> ';
                    }
                    bodyContent = description;
                    break;
                case 'interests':
                    addToHeader = $E.translate('cofigureblock_cvinfo_interests');
                    var description = '';
                    if (resumeItems.interests != '') {
                        description += '<span class="interests">' + resumeItems.interests + '</span> ';
                    }
                    bodyContent = description;
                    break;
                default:
                    break;
            }

            if (data.resume_withfiles == "1" && attachments && attachments.length) {
                bodyContent += '<ul class="resume_attachments ' + data.resume_itemtype + '_attachments">';
                $.each(attachments, function (k, file) {
                    bodyContent += '<li><a href="' + file.fileurl + '" target="_blank">' + file.filename + '</a></li>';
                })
                bodyContent += '</ul>';
            }

            var tempString = '<div id="id_holder" style="display:none;"></div>';
            tempString += '<div class="cv_info" style="overflow: hidden;">';
            tempString += '<div class="header">' + $E.translate('cvinformation') + ': ' + addToHeader + '</div>';
            tempString += '<div class="body">' + bodyContent + '</div>';
            tempString += '</div>';
            $item.html(tempString);
        } else {
            data.type = 'text';
            var tempString = '<div id="id_holder" style="display:none;"></div>';
            tempString += '<div class="header"></div>';
            tempString += '<div class="body"><p class="text" ' + $E.translate('view_specialitem_text_defaulttext') + '"></p></div>';
            $item.html(tempString);
            $item.find('div.header').append($E.translate('view_specialitem_text') + ($.trim(data.block_title).length ? ': ' + data.block_title : ''));
            $item.find('div.body').append(data.print_text);
        }

        // Insert default texts.
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
        $item.find('div#id_holder').append(data.id);

        if (type == 'new') {
            // No edit button for items, badges and resume items.
            var typesWithoutEditButton = ['item', 'badge', 'cv_information'];
            // if ((data.type != 'item') && (data.type != 'badge')) {
            if (!(typesWithoutEditButton.indexOf(data.type) > -1)) {
                $('<a class="edit" title="Edit"><span>Edit</span></a>').prependTo($item).click(editItemClick);
            }
        }
        else {
            $item.append('<a class="unsaved" title="This block was not saved"><span>Unsaved</span></a>');
        }
        $('<a class="delete" title="' + $E.translate('delete') + '"><span>' + $E.translate('delete') + '</span></a>').prependTo($item).click(deleteItemClick);
        $item.find(':input').change(function() {
            $item.data('portfolio').text = $(this).val();
        });
        updateBlockData();
        // Unshared blocks.
        if (data.unshared == 1) {
            $item.find('div.item_info').addClass('unshared_block');
            $item_header = $item.find('div.header').html();
            $item.find('div.header').html($item_header + '<span class="unshared_message">Unshared</span>');
        }
        return $item;
    }

    // Click on 'delete' block.
    function deleteItemClick()
    {
        $(this).parents('.item').remove();
        resetElementStates();
        updateBlockData();
    }

    function saveBlockData() {
        var data = $('form#view_edit_form').serializeArray();
        data.push({name: 'ajax', value: 1});
        showPreloadinator($('#view-preview'), '', true);
        $.ajax({
            url: document.location.href,
            type: 'POST',
            data: data,
            success: function(res) {
                var data = JSON.parse(res);
                $('form :input[name=blocks]').val(data.blocks);
                exaportViewEdit.resetViewContent();
                hidePreloadinator($('#view-preview'), '');
            }
        });
    }

    function editItemClick()
    {
        var item_id = $(this).parent().find("#id_holder").html();
        lastclicked = $(this).parent();
        showPreloadinator(lastclicked, '', false);
        $.ajax({
            url: M.cfg['wwwroot'] + '/blocks/exaport/blocks.json.php',
            type: 'POST',
            data: {
                item_id: item_id,
                action: 'edit',
                'viewid': $('form :input[name=viewid]').val(),
                'text[itemid]': $('form :input[name=draft_itemid]').val(),
                sesskey: $('form :input[name=sesskey]').val()
            },
            success: function(res) {
                var data = JSON.parse(res);

                showDialog(data.modalTitle, data.html);
                // Focus first element.
                // ...popup.$body.find('input:visible:first').focus();.

            }
        });
    }

    // Main function -> will be started on page load.
    var initContentEdit = function() {

        exaportViewEdit = exaportViewEditCreate();
        exaportViewEdit.resetViewContent();

        $(".portfolioDesignBlocks").sortable({
            beforeStop: function (event, ui) {
                newItem = ui.item;
            },
            receive: function(e, ui){

                console.log('start to modal!');

                // Get ajax only for item from the top block.
                var uiattr = $(ui.item[0]).closest('ul').prop("className");
                if (uiattr.search("portfolioDesignBlocks") == -1) {
                    $.ajax({
                        url: M.cfg['wwwroot'] + '/blocks/exaport/blocks.json.php',
                        type: 'POST',
                        data: {
                            type_block: ui.item.attr('block-type'),
                            action: 'add',
                            'viewid': $('form :input[name=viewid]').val(),
                            'text[itemid]': $('form :input[name=draft_itemid]').val(),
                            sesskey: $('form :input[name=sesskey]').val()
                        },
                        success: function(res) {
                            var data = JSON.parse (res);

                            showDialog(data.modalTitle, data.html);

                            // ...var popup = $E.popup({ bodyContent: data.html, onhide: function(){
                            // if (newItem) $(newItem).remove();
                            // } });
                            // focus first element
                            // $E.last_popup.$body.find('input:visible:first').focus();.

                            // Create a <script> tag from result.
                            $('#temp_javascript').remove();
                            var script = document.createElement('script');
                            $(script).attr('id', 'temp_javascript');
                            // console.log(data.script);
                            script.textContent = data.script;
                            document.body.appendChild(script);

                            $('body').on('change keydown paste input', '#filterByTitle', exaportViewEdit.filterItemsByTitle);
                        }
                    });
                    updateBlockData();
                }
            },
            update: function(e, ui) {
                updateBlockData();
            },
            handle: '.header',
            placeholder: "block-placeholder",
            forcePlaceholderSize: true,
            connectWith: ['.portfolioDesignBlocks'],
        });
        $(".portfolioOptions").sortable({
            connectWith: ['.portfolioDesignBlocks'],
            placeholder: "block-placeholder",
            forcePlaceholderSize: true,
            stop: function(e, ui) {
                // Listenelemente zurücksetzen.
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
        $(".portfolioElement").draggable({
            connectToSortable: '.portfolioDesignBlocks',
            placeholder: ".block-placeholder",
            forcePlaceholderSize: true,
            helper: "clone",
            stop: function(e, ui){
                showPreloadinator($(ui.helper), '', false);
            }
        });
        $('body').on('click', '[data-toggle="gtn-help-modal"]', function(e) {
            e.preventDefault();
            var trigger = $(this);
            var title = $(this).attr('data-title');
            var content = $(this).attr('data-content');
            if (helpDialogue) {
                helpDialogue.setTitle(title);
                helpDialogue.setBody(content);
                helpDialogue.show();
            } else {
                modalFactory.create({
                    title: title,
                    body: content,
                    footer: '',
                }, trigger).done(function (modal) {
                    helpDialogue = modal;
                    helpDialogue.setTitle(title);
                    helpDialogue.setBody(content);
                    // Display the dialogue.
                    helpDialogue.show();
                });
            }
        });


    } // End initContentEdit.

    function showPreloadinator(element, tohide, fixedposition) {
        var id = 'preloader' + $.now();
        var preloadinatorTemplate = '<div id="' + id + '" class="preloader ' + (fixedposition ? 'preloader-fixed' : '') + ' js-preloader flex-center">' +
                '<div class="dots">'+
                    '<div class="dot"></div>'+
                    '<div class="dot"></div>'+
                    '<div class="dot"></div>'+
                '</div>'+
            '</div>';
        if (tohide != '') {
            element.find(tohide).hide();
        }
        element.append(preloadinatorTemplate);
        // hide element on very long requests
        setTimeout('$(\'#' + id + '\').remove();', 15000); // 15 seconds

    } // End showPreloadinator

    function hidePreloadinator(element, toshow) {
        if (toshow != '') {
            $(element).find('.blocktype').show();
        }
        $(element).find('.preloader').remove();
    } // End hidePreloadinator

    function showDialog(title, body) {
        if (dialogue) {
            dialogue.setTitle(title);
            dialogue.setBody(body);
            // Display the dialogue.
            dialogue.show();
        } else {
            modalFactory.create(defaultModalOptions).done(function (modal) {
                dialogue = modal;
                dialogue.setTitle(title);
                dialogue.setBody(body);
                // Display the dialogue.
                dialogue.show();
                // On show handler
                /*dialogue.getRoot().on(modalEvents.shown, function () {
                    console.log('modal is shown');
                    console.log(data);
                    dialogue.setTitle(data.modalTitle);
                    dialogue.setBody(data.html);
                });*/
                // On show handler. (for slow requests)
                dialogue.getRoot().on(modalEvents.shown, function () {
                    hidePreloadinator(newItem, '.blocktype');
                    hidePreloadinator(lastclicked, '');
                    console.log('modal is shown');
                });
                // On hide handler.
                dialogue.getRoot().on(modalEvents.hidden, function () {
                    // Empty modal contents when it's hidden.
                    // console.log('modal is hidden');
                    dialogue.setBody('');
                    // Hide handler.
                    if (newItem) {
                        $(newItem).remove();
                    }
                });
            });
        }
    }

    // Sharing.
    function update_sharing()
    {
        var share_text = '';
        var $form = $('#exaport-view-mod');

        if ($form.find(':input[name=externaccess]').is(':checked')) {
            share_text += $E.translate('externalaccess') + ' ';
            $('#externaccess-settings').show();
        } else {
            $('#externaccess-settings').hide();
        }

        if ($form.find(':input[name=internaccess]').is(':checked')) {
            $('#internaccess-settings').show();
            $('#internaccess-groups').hide();
            if (share_text) {
                share_text += ' ' + $E.translate('viewand') + ' ';
            }
            share_text += $E.translate('internalaccess') + ': ';

            if ($form.find(':input[name=shareall]:checked').val() == 1) {
                share_text += $E.translate('internalaccessall');
                $('#internaccess-users').hide();
                $('#internaccess-groups').hide();
            } else if ($form.find(':input[name=shareall]:checked').val() == 2) {
                share_text += $E.translate('internalaccessgroups');
                $('#internaccess-users').hide();
                $('#internaccess-groups').show();
                ExabisEportfolio.load_grouplist('views_mod');
            } else {
                share_text += $E.translate('internalaccessusers');
                $('#internaccess-groups').hide();
                $('#internaccess-users').show();
                ExabisEportfolio.load_userlist('views_mod');
            }
        } else {
            $('#internaccess-settings').hide();
        }

        if ($form.find(':input[name=sharedemails]').is(':checked')) {
            if (share_text) {
                share_text += ' ' + $E.translate('viewand') + ' ';
            };
            share_text += $E.translate('emailaccess') + ' ';
            $('#emailaccess-settings').show();
        } else {
            $('#emailaccess-settings').hide();
        };

        if (!share_text) {
            share_text = $E.translate('view_sharing_noaccess');
        }
        $('#view-share-text').html(share_text);
    }

    /*var initialise = function($params) {
            console.log('exaport AMD loaded');
            initContentEdit();
            // for sharing of views
            // changing the checkboxes / radiobuttons update the sharing text, visible options, etc.
            $('.view-sharing input[type=checkbox], .view-sharing input[type=radio]').click(update_sharing);
            update_sharing();
        };
        return initialise();.
    */

    // MAIN code of MODULE.
    return {
        initialise: function ($params) {
            // console.log('exaport AMD loaded');
            initContentEdit();
            // For sharing of views
            // changing the checkboxes / radiobuttons update the sharing text, visible options, etc.
            $('.view-sharing input[type=checkbox], .view-sharing input[type=radio]').click(update_sharing);
            update_sharing();
        },
    };

});
