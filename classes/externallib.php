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
 * External integration API
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_vitrina;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External WS lib.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends \external_api {

    /**
     * To validade input parameters
     * @return \external_function_parameters
     */
    public static function get_courses_parameters() {
        return new \external_function_parameters(
            [
                'view' => new \external_value(PARAM_TEXT, 'Courses view', VALUE_DEFAULT, 'default'),
                'filters' => new \external_multiple_structure(
                    new \external_single_structure(
                        [
                            'type' => new \external_value(PARAM_TEXT, 'Filter type key'),
                            'values' => new \external_multiple_structure(
                                new \external_value(PARAM_TEXT, 'Filter value'),
                            )
                        ],
                        'A filter to apply'),
                    'List of filters to search the courses', VALUE_DEFAULT, []
                ),
                'instanceid' => new \external_value(PARAM_INT, 'Block instance id', VALUE_DEFAULT, 0),
                'amount' => new \external_value(PARAM_INT, 'Amount of courses', VALUE_DEFAULT, 0),
                'initial' => new \external_value(PARAM_INT, 'From where to start', VALUE_DEFAULT, 0)
            ]
        );
    }

    /**
     * Return a courses list.
     *
     * @param string $view Courses view type
     * @param array $filters List of filters to search the courses
     * @param int $instanceid Block instance id
     * @param int $amount Amount of courses
     * @param int $initial From where to start
     * @return array Courses list
     */
    public static function get_courses(string $view = 'default',
                                        array $filters = [],
                                        int $instanceid = 0,
                                        int $amount = 0,
                                        int $initial = 0) : array {
        global $PAGE, $CFG;

        if (!isloggedin() && empty($CFG->guestloginbutton) && empty($CFG->autologinguests)) {
            require_login(null, true);
        }

        $syscontext = \context_system::instance();
        $PAGE->set_context($syscontext);

        // Parameter validation.
        $params = self::validate_parameters(
            self::get_courses_parameters(),
            [
                'view' => $view,
                'filters' => $filters,
                'instanceid' => $instanceid,
                'amount' => $amount,
                'initial' => $initial
            ]
        );

        // Read the categories if is a block instance call or the filter by categories is defined.
        $categoriesids = [];

        foreach ($params['filters'] as $filter) {
            if ($filter['type'] == 'categories') {
                $categoriesids = $filter['values'];

                // Remove filter.
                $params['filters'] = array_filter($params['filters'], function ($filter) {
                    return $filter['type'] != 'categories';
                });

                // Cast to int.
                $categoriesids = array_map('intval', $categoriesids);

                // Remove duplicates.
                $categoriesids = array_unique($categoriesids);

                // Remove empty values.
                $categoriesids = array_filter($categoriesids);

                break;
            }
        }

        if (count($categoriesids) == 0) {

            if (!empty($params['instanceid'])) {
                $block = block_instance_by_id($params['instanceid']);

                if ($block->config && count($block->config->categories) > 0) {
                    $categoriesids = $block->config->categories;
                }
            }
        }
        // End of read categories.

        $courses = \block_vitrina\controller::get_courses_by_view($params['view'],
                                                                $categoriesids,
                                                                $params['filters'],
                                                                '',
                                                                $params['amount'],
                                                                $params['initial']);

        $response = [];
        $renderer = $PAGE->get_renderer('block_vitrina');

        foreach ($courses as $course) {
            \block_vitrina\controller::course_preprocess($course);

            $renderedcourse = new \stdClass();
            $renderedcourse->id = $course->id;
            $renderedcourse->html = $renderer->render_course($course);

            $response[] = $renderedcourse;
        }

        return $response;
    }

    /**
     * Validate the return value
     * @return \external_multiple_structure
     */
    public static function get_courses_returns() {
        return new \external_multiple_structure(
            new \external_single_structure(
                [
                    'id' => new \external_value(PARAM_INT, 'Course id'),
                    'html' => new \external_value(PARAM_RAW, 'HTML with course information')
                ]
            ), 'List of courses'
        );
    }

}
