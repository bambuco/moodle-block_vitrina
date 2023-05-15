// This file is part of Moodle - http://moodle.org/
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

/**
 * Javascript to initialise the block.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from 'jquery';
import {get_strings as getStrings} from 'core/str';
import Notification from 'core/notification';
import Log from 'core/log';
import Ajax from 'core/ajax';

/**
 * Private functions.
 *
 */

// Current instance (optional).
var instanceid = 0;

// Courses by page.
var bypage = 20;

// Paging variable controls.
var paging = [];

// Load strings.
var strings = [
    {key: 'courselinkcopiedtoclipboard', component: 'block_vitrina'},
    {key: 'nocoursesview', component: 'block_vitrina'},
    {key: 'nomorecourses', component: 'block_vitrina'},
];
var s = [];

/**
 * Load strings from server.
 */
function loadStrings() {

    strings.forEach(one => {
        s[one.key] = one.key;
    });

    getStrings(strings).then(function(results) {
        var pos = 0;
        strings.forEach(one => {
            s[one.key] = results[pos];
            pos++;
        });
        return true;
    }).fail(function(e) {
        Log.debug('Error loading strings');
        Log.debug(e);
    });
}
// End of Load strings.

/**
 * Load courses for a tab.
 *
 * @param {object} $tabcontent
 */
function loadCourses($tabcontent) {

    var view = $tabcontent.data('view');

    $tabcontent.addClass('loading');
    var $coursesbox = $tabcontent.find('.courses-list');

    if (paging[view] === undefined) {
        paging[view] = {
            loaded: 0,
            ended: false,
        };
    }

    // Not more courses.
    if (paging[view].ended) {
        $tabcontent.removeClass('loading');
        return;
    }

    Ajax.call([{
        methodname: 'block_vitrina_get_courses',
        args: {'view': view, 'filters': [], 'instanceid': instanceid, 'amount': bypage, 'initial': paging[view].loaded},
        done: function(data) {

            if (data && data.length > 0) {
                paging[view].loaded += data.length;

                if (data.length < bypage) {
                    paging[view].ended = true;
                }

                data.forEach(one => {
                    $coursesbox.append(one.html);
                });
            } else {
                paging[view].ended = true;
            }

            $tabcontent.removeClass('loading');

            if (paging[view].ended) {
                $tabcontent.addClass('ended');
                $tabcontent.find('.loadmore').remove();

                var nocoursesbox = $tabcontent.find('.nocourses');
                var nocoursesmsg = '';
                if (paging[view].loaded == 0) {
                    nocoursesmsg = s.nocoursesview;
                    nocoursesbox.removeClass('hidden');
                } else {
                    // Only show the message if not is the first call when reached the end.
                    if (paging[view].loaded > bypage) {
                        nocoursesmsg = s.nomorecourses;
                        nocoursesbox.removeClass('hidden');
                    }
                }
                nocoursesbox.html(nocoursesmsg);
            }
        },
        fail: function(e) {
            Notification.exception(e);
            Log.debug(e);
            $tabcontent.removeClass('loading');
        }
    }]);

}

/**
 * Component initialization.
 *
 * @method init
 */
export const init = () => {

    loadStrings();

    $('[data-vitrina-toggle]').on('click', function() {
        var $this = $(this);
        var cssclass = $this.attr('data-vitrina-toggle');
        var target = $this.attr('data-target');

        $(target).toggleClass(cssclass);
    });

    $('.block_vitrina-content').each(function() {
        var $blockcontent = $(this);

        // Tabs.
        $blockcontent.find('.block_vitrina-tabs').each(function() {
            var $tabs = $(this);
            var tabslist = [];

            $tabs.find('[data-ref]').each(function() {
                var $tab = $(this);
                var $tabcontent = $($tab.data('ref'));
                tabslist.push($tab);

                $tab.on('click', function() {
                    tabslist.forEach(one => {
                        $(one.data('ref')).removeClass('active');
                    });

                    $tabs.find('.active[data-ref]').removeClass('active');
                    $tab.addClass('active');

                    if ($tabcontent) {
                        $tabcontent.addClass('active');

                        // Load courses only the first time.
                        var view = $tabcontent.data('view');

                        if (paging[view] === undefined) {
                            loadCourses($tabcontent);
                        }
                    }
                });

                $tabcontent.find('.loadmore').on('click', function() {
                    var $this = $(this);

                    // If is a link, do nothing. Only for buttons.
                    if ($this.attr('href')) {
                        return;
                    }

                    loadCourses($tabcontent);
                });
            });

            // Load dynamic buttons.
            $blockcontent.find('[data-vitrina-tab]').each(function() {
                var $button = $(this);

                $button.on('click', function() {
                    var key = '.tab-' + $button.data('vitrina-tab');

                    tabslist.forEach($tab => {
                        if ($tab.data('ref').indexOf(key) >= 0) {
                            $tab.trigger('click');
                        }
                    });
                });
            });
        });
    });
};

/**
 * Initialise functions for the detail page.
 *
 */
export const detail = () => {
    strings.push({key: 'courselinkcopiedtoclipboard', component: 'block_vitrina'});

    $('input[name="courselink"]').on('click', function() {
        var $input = $(this);
        $input.select();
        document.execCommand("copy");

        var $msg = $('<div class="msg-courselink-copy">' + s.courselinkcopiedtoclipboard + '</div>');

        $input.parent().append($msg);
        window.setTimeout(function() {
            $msg.remove();
        }, 1600);
    });

    init();
};

/**
 * Initialise functions for the catalog page.
 *
 * @param {string} uniqueid
 * @param {string} view
 * @param {integer} currentinstanceid
 * @param {integer} currentbypage
 */
export const catalog = (uniqueid, view, currentinstanceid = 0, currentbypage = 20) => {

    instanceid = currentinstanceid;
    bypage = parseInt(currentbypage);
    var $tabcontent = $('#' + uniqueid + ' .tabs-content .tab-' + view);

    loadCourses($tabcontent);

    init();
};
