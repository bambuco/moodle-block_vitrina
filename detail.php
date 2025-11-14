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
$enroltype = optional_param('enroltype', '', PARAM_TEXT);

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
$PAGE->set_heading(get_string('coursedetail', 'block_vitrina', $course));
$PAGE->set_title(get_string('coursedetailtitle', 'block_vitrina', $course));
$PAGE->set_course($course);

$msg = [];

if ($tologin) {
    if (isguestuser() || !isloggedin()) {
        $SESSION->wantsurl = (string)(new moodle_url('/blocks/vitrina/detail.php', ['id' => $course->id]));
        redirect(get_login_url());
    }
}

$enrolmsg = [];
do {
    if (!$enroll || !$course->visible) {
        break;
    }

    if (isguestuser() || !isloggedin()) {
        $params = ['id' => $course->id, 'enroll' => $enroll, 'sesskey' => sesskey()];
        $SESSION->wantsurl = (string)(new moodle_url('/blocks/vitrina/detail.php', $params));
        redirect(get_login_url());
    }

    $coursecontext = \context_course::instance($course->id, $USER, '', true);

    // Check if the user is already enrolled in the course.
    if (is_enrolled($coursecontext)) {
        break;
    }

    // Check the session key.
    if (!confirm_sesskey()) {
        break;
    }

    \block_vitrina\local\controller::course_preprocess($course, true);

    $enrollable = array_key_exists('self', $course->enrollsavailables) ||
        array_key_exists('premium', $course->enrollsavailables) ||
        array_key_exists('customgr', $course->enrollsavailables) ||
        array_key_exists('token', $course->enrollsavailables);

    // If not exist an available enrollment enabled.
    if (!$enrollable) {
        break;
    }

    $enrolinstances = [];
    foreach ($course->enrollsavailables as $enrollsavailable) {
        $enrolinstances = array_merge($enrolinstances, array_values($enrollsavailable));
    }

    $premiumcohort = null;
    $premiumtype = null;
    if (array_key_exists('premium', $course->enrollsavailables)) {
        if ($course->premium || !\block_vitrina\local\controller::premium_available()) {
            $premiumcohort = get_config('block_vitrina', 'premiumcohort');
        }

        $premiumtype = \block_vitrina\local\controller::type_membership();
    }

    foreach ($enrolinstances as $instance) {
        if ($instance->enrol == 'self') {
            $enrolplugin = enrol_get_plugin('self');

            // If the premiumcohort is configured this instance is only available to premium users enrollments.
            if (
                !array_key_exists('premium', $course->enrollsavailables) &&
                $premiumcohort &&
                $instance->customint5 &&
                $instance->customint5 == $premiumcohort
            ) {
                continue;
            }

            // The validation only applies to premium courses if the premiumcohort setting is configured.
            // If premiumcohort is configured the course requires the specific cohort.
            // The enrol type is empty for premium courses.
            if (
                empty($enroltype) && array_key_exists('premium', $course->enrollsavailables) && (
                    !$premiumcohort
                    || empty($instance->customint5)
                    || $instance->customint5 == $premiumcohort
                )
            ) {
                $data = null;
                if ($instance->password) {
                    // If the instance has a password but the course is premium the password is simuled.
                    $data = new stdClass();
                    $data->enrolpassword = $instance->password;
                }

                // Change the end dates from the course if the user is premium for the 'premiumenrolledcourse'.
                if ($premiumtype == \block_vitrina\local\controller::PREMIUMBYCOURSE) {
                    $premiumcourseid = get_config('block_vitrina', 'premiumenrolledcourse');

                    if (!empty($premiumcourseid)) {
                        $premiumcontext = \context_course::instance($premiumcourseid);
                        $until = enrol_get_enrolment_end($premiumcontext->instanceid, $USER->id);

                        if ($until !== false) {
                            $secondstoend = $until - time();
                            $instance->enrolperiod = $secondstoend;
                        }
                    }
                }

                $enrolplugin->enrol_self($instance, $data);
                break;
            }

            // If the self enrolment is available use it directly because is the more basic.
            if (array_key_exists('self', $course->enrollsavailables) && $enroltype == 'self') {
                $data = null;
                if ($instance->password) {
                    $enrolid = optional_param('enrolid', 0, PARAM_INT);

                    // The enrolid is required for the self enrolment when use password.
                    if (empty($enrolid)) {
                        continue;
                    }

                    // It is not the correct instance.
                    if ($instance->id != $enrolid) {
                        continue;
                    }

                    $data = new stdClass();
                    // The password is required.
                    $data->enrolpassword = optional_param('enrolpassword', '', PARAM_TEXT);

                    if (empty($data->enrolpassword)) {
                        continue;
                    }

                    if ($data->enrolpassword != $instance->password) {
                        $enrolmsg[] = get_string('passwordinvalid', 'enrol_self');
                        continue;
                    }
                }

                $enrolplugin->enrol_self($instance, $data);
                break;
            }
        } else if ($instance->enrol == 'customgr' && $enroltype == 'customgr') {
            $enrolid = optional_param('enrolid', 0, PARAM_INT);

            // The enrolid is required for the custom group enrolment.
            if (empty($enrolid)) {
                continue;
            }

            // It is not the correct instance.
            if ($instance->id != $enrolid) {
                continue;
            }

            $enrolplugin = enrol_get_plugin('customgr');

            if (!$enrolplugin->can_self_enrol($instance, false) || !$enrolplugin->is_self_enrol_available($instance)) {
                continue;
            }

            $data = null;
            if ($instance->password) {
                $data = new stdClass();
                // The password is required.
                $data->enrolpassword = optional_param('enrolpassword', '', PARAM_TEXT);

                if (empty($data->enrolpassword) || $data->enrolpassword != $instance->password) {
                    continue;
                }
            }

            $enrolplugin->enrol_customgr($instance, $data);
        } else if ($instance->enrol == 'token' && $enroltype == 'token') {
            $enrolid = optional_param('enrolid', 0, PARAM_INT);

            // The enrolid is required for the custom group enrolment.
            if (empty($enrolid)) {
                continue;
            }

            // It is not the correct instance.
            if ($instance->id != $enrolid) {
                continue;
            }

            $enrolplugin = enrol_get_plugin('token');

            if (!$enrolplugin->can_self_enrol($instance, false) || !$enrolplugin->is_self_enrol_available($instance)) {
                continue;
            }

            $data = new stdClass();

            // The token is required.
            $data->enroltoken = optional_param('enroltoken', '', PARAM_TEXT);
            if (empty($data->enroltoken)) {
                continue;
            }

            if (!$enrolplugin->enrol_self($instance, $data)) {
                $enrolmsg[] = get_string('tokeninvalid', 'enrol_token');
            }
        }
    }
} while (false); // Trick to avoid nesting of IF statements.

\block_vitrina\local\controller::include_templatecss();

echo $OUTPUT->header();

if (!$course->visible) {
    echo get_string('notvisible', 'block_vitrina');
} else {
    $renderable = new \block_vitrina\output\detail($course, $enrolmsg);
    $renderer = $PAGE->get_renderer('block_vitrina');
    echo $renderer->render($renderable);
}

echo $OUTPUT->footer();
