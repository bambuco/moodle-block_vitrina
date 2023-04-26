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

define(['jquery', 'core/str', 'core/log'], function($, str, Log) {

    // Load strings.
    var strings = [];
    strings.push({key: 'courselinkcopiedtoclipboard', component: 'block_vitrina'});

    var s = [];

    if (strings.length > 0) {

        strings.forEach(one => {
            s[one.key] = one.key;
        });

        str.get_strings(strings).done(function(results) {
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
    // End of Load strings.

    /**
     * Initialise all for the block.
     *
     */
    var init = function() {
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

        $('[data-vitrina-toggle]').on('click', function() {
            var $this = $(this);
            var cssclass = $this.attr('data-vitrina-toggle');
            var target = $this.attr('data-target');

            $(target).toggleClass(cssclass);
        });

    };

    return {
        init: init
    };
});
