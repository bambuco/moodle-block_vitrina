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

define(['jquery', 'core/str', 'core/log'], function($, Str, Log) {

    // Load strings.
    var strings = [];
    var s = [];

    /**
     * Load strings from server.
     */
    function loadStrings() {

        if (strings.length > 0) {

            strings.forEach(one => {
                s[one.key] = one.key;
            });

            Str.get_strings(strings).done(function(results) {
                var pos = 0;
                strings.forEach(one => {
                    s[one.key] = results[pos];
                    pos++;
                });
            }).fail(function(e) {
                Log.debug('Error loading strings');
                Log.debug(e);
            });
        }

    }
    // End of Load strings.

    /**
     * Initialise functions for the detail page block.
     *
     */
    var detail = function() {
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
     * Initialise all for the block.
     *
     */
    var init = function() {

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
                    tabslist.push($tab);

                    $tab.on('click', function() {
                        tabslist.forEach(one => {
                            $(one.data('ref')).removeClass('active');
                        });

                        $tabs.find('.active[data-ref]').removeClass('active');
                        $tab.addClass('active');
                        $($tab.data('ref')).addClass('active');
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

    return {
        init: init,
        detail: detail
    };
});
