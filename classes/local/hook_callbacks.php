<?php
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

namespace block_vitrina\local;

/**
 * Allows plugins to add any elements to the html.
 *
 * @package    block_vitrina
 * @copyright  2024 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Callback to add head elements.
     * Load social network metadata.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook,
    ): void {
        global $PAGE, $OUTPUT;

        $course = $PAGE->course;

        $title = $PAGE->title;
        $url = $PAGE->url;
        $summaryplain = strip_tags(format_text($course->summary, $course->summaryformat));

        if ($course->id == SITEID) {
            $imagepath = $OUTPUT->get_logo_url();
        } else {
            $imagepath = \block_vitrina\local\controller::get_courseimage($course);
        }

        $hook->add_html('<meta property="og:title" content="' . $title . '"/>');
        $hook->add_html('<meta property="og:url" content="' . $url . '"/>');

        if ($summaryplain) {
            $hook->add_html('<meta property="og:description" content="' . $summaryplain . '"/>');
        }

        if ($imagepath) {
            $hook->add_html('<meta property="og:image" content="' . $imagepath . '"/>');
        }
    }
}
