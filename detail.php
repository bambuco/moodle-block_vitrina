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
 * Course details.
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('classes/output/detail.php');

$id = optional_param('id', 0, PARAM_INT);
$enroll = optional_param('enroll', false, PARAM_BOOL);
$tologin = optional_param('tologin', false, PARAM_BOOL);

if ($id == SITEID) {
    // This course is not a real course.
    redirect($CFG->wwwroot . '/');
}

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_login(null, true);

$syscontext = context_system::instance();

$PAGE->set_context($syscontext);
$PAGE->set_url('/blocks/vitrina/detail.php', ['id' => $course->id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('coursedetail', 'block_vitrina'));

if ($enroll) {

    if (isguestuser() || !isloggedin()) {
        $SESSION->wantsurl = (string)(new moodle_url('/blocks/vitrina/detail.php', ['id' => $course->id, 'enroll' => 1]));
        redirect(get_login_url());
    }

    $coursecontext = \context_course::instance($course->id, $USER, '', true);

    \block_vitrina\controller::course_preprocess($course);

    $enrollable = true;

    // Check if the course is only for premium users.
    if ($course->paymenturl) {
        $enrollable = \block_vitrina\controller::is_user_premium();
    }

    // If currently not enrolled.
    if ($enrollable && !is_enrolled($coursecontext)) {
        $enrolinstances = enrol_get_instances($course->id, true);
        $enrolplugin = enrol_get_plugin('self');

        foreach ($enrolinstances as $instance) {
            if ($instance->enrol == 'self') {

                if ($instance->password) {
                    $url = new moodle_url('/enrol/index.php', ['id' => $course->id]);
                    redirect($url);
                } else {
                    $enrolplugin->enrol_self($instance);
                }
                break;
            }
        }
    }
} else if ($tologin) {
    if (isguestuser() || !isloggedin()) {
        $SESSION->wantsurl = (string)(new moodle_url('/blocks/vitrina/detail.php', ['id' => $course->id]));
        redirect(get_login_url());
    }
}

\block_vitrina\controller::include_templatecss();

echo $OUTPUT->header();

if (!$course->visible) {
    echo get_string('notvisible', 'block_vitrina');
} else {

    $renderable = new \block_vitrina\output\detail($course);
    $renderer = $PAGE->get_renderer('block_vitrina');
    echo $renderer->render($renderable);
}

echo $OUTPUT->footer();
