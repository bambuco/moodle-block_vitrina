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
 * Comments base.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base {
    /**
     * Define if comments plugin is available.
     *
     * @return boolean
     */
    public static function comments_available(): bool {
        global $PAGE;

        $bmanager = new \block_manager($PAGE);

        return $bmanager->is_known_block_type('comments');
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

        $coursecontext = \context_course::instance($course);
        $comments = $DB->get_records(
            'comments',
            ['contextid' => $coursecontext->id, 'component' => 'block_comments'],
            'timecreated DESC',
            'content, format, userid, timecreated',
            0,
            $amount
        );

        return $comments;
    }
}
