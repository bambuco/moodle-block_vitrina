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

/**
 * Class containing the general logic to course comments.
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_vitrina\local\comments;

/**
 * Use plugin admin tool courserating.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_courserating {
    /**
     * Define if comments plugin is available.
     *
     * @return boolean
     */
    public static function comments_available(): bool {
        $list = \core_component::get_plugin_list('tool');
        foreach ($list as $name => $dir) {
            if ($name == 'courserating') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the course comments.
     *
     * @param object|int $course Course to be processed.
     * @param int $amount Amount of comments to be returned.
     * @return object
     */
    public static function get_comments($course, $amount = 20): array {
        global $DB;

        if (!self::comments_available()) {
            return [];
        }

        if (is_object($course)) {
            $course = $course->id;
        }

        $comments = $DB->get_records(
            'tool_courserating_rating',
            ['courseid' => $course, 'hasreview' => 1],
            'timemodified DESC',
            'review AS content, \'0\' AS format, userid, timemodified AS timecreated',
            0,
            $amount
        );

        return $comments;
    }
}
