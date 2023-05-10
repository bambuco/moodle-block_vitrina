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
 * Class containing renderers for details.
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_vitrina\output;

use renderable;
use renderer_base;
use templatable;

/**
 * Class containing data for details.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class detail implements renderable, templatable {

    /**
     * @var object Course.
     */
    private $course = null;

    /**
     * Constructor.
     *
     * @param object $course A course
     */
    public function __construct($course) {

        \block_vitrina\controller::course_preprocess($course, true);
        $this->course = $course;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array Context variables for the template
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $OUTPUT, $PAGE, $USER, $DB;

        // Course detail info.
        $detailinfo = get_config('block_vitrina', 'detailinfo');
        $detailinfo = format_text($detailinfo, FORMAT_HTML, ['trusted' => true, 'noclean' => true]);

        // Load social networks.
        $networks = get_config('block_vitrina', 'networks');
        $networkslist = explode("\n", $networks);
        $socialnetworks = [];

        $courseurl = new \moodle_url('/blocks/vitrina/detail.php', ['id' => $this->course->id]);
        foreach ($networkslist as $one) {

            $row = explode('|', $one);
            if (count($row) >= 2) {
                $network = new \stdClass();
                $network->icon = trim($row[0]);
                $network->url = trim($row[1]);
                $network->url = str_replace('{url}', $courseurl, $network->url);
                $network->url = str_replace('{name}', $this->course->fullname, $network->url);
                $socialnetworks[] = $network;
            }
        }

        // Load custom course fields.
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $datas = $handler->get_instance_data($this->course->id);
        $fields = ['thematic', 'units', 'requirements', 'license', 'media', 'duration', 'expertsshort', 'experts'];
        $custom = new \stdClass();

        $fieldsnames = [];
        foreach ($fields as $field) {
            $name = get_config('block_vitrina', $field);

            if (!empty($name)) {
                $fieldsnames[$field] = $name;
            }
        }

        foreach ($datas as $data) {
            $key = $data->get_field()->get('shortname');

            $exist = false;
            foreach ($fieldsnames as $field => $name) {
                if ($name == $key) {
                    $c = new \stdClass();
                    $c->title = format_text($data->get_field()->get('name'), FORMAT_HTML);

                    $c->value = $data->export_value();

                    if ($field == 'license') {
                        if (get_string_manager()->string_exists('license-' . $c->value, 'block_vitrina')) {
                            $c->text = get_string('license-' . $c->value, 'block_vitrina');
                            $c->path = $c->value == 'cc-0' ? 'zero/1.0' : trim($c->value, 'cc-') . '/4.0';
                        } else {
                            $c->text = $c->value;
                        }
                    } else if ($field == 'media') {
                        if (strpos($c->value, 'https://www.youtube.com') === 0 ||
                               strpos($c->value, 'https://youtube.com') === 0 ||
                               strpos($c->value, 'https://player.vimeo.com') === 0) {
                            $c->isembed = true;
                        }
                    }

                    if (!empty($c->value)) {
                        $custom->$field = $c;
                    }

                    $exist = true;
                    break;
                }
            }

            if (!$exist) {
                $value = trim($data->export_value());

                if (!empty($value)) {
                    $c = new \stdClass();
                    $c->title = format_text($data->get_field()->get('name'), FORMAT_HTML);
                    $c->value = $value;
                    $custom->$key = $c;
                }
            }
        }

        // End Load custom course fields.

        // Load the course context.
        $coursecontext = \context_course::instance($this->course->id, $USER, '', true);

        $completed = $DB->get_record('course_completions', ['userid' => $USER->id, 'course' => $this->course->id]);

        // Special format to the course name.
        $coursename = $this->course->fullname;
        $m = explode(' ', $coursename);

        $first = '';
        $last = '';
        foreach ($m as $k => $n) {
            if ($k < (count($m) / 2)) {
                $first .= $n . ' ';
            } else {
                $last .= $n . ' ';
            }
        }

        $coursename = $first . '<span>' . $last . '</span>';
        // End course name format.

        // Check enrolled status.
        $custom->enrolled = !(isguestuser() || !isloggedin() || !is_enrolled($coursecontext));

        $custom->completed = $completed && $completed->timecompleted;

        $enrollstate = $custom->completed ? 'completed' : ($custom->enrolled ? 'enrolled' : 'none');

        $custom->enrolltitle = get_string('notenrollable', 'block_vitrina');
        $custom->enrollurl = null;
        $custom->enrollurllabel = '';

        if ($custom->completed) {

            $custom->enrolltitle = get_string('completed', 'block_vitrina');
            $custom->enrollurl = new \moodle_url('/course/view.php', ['id' => $this->course->id]);
            $custom->enrollurllabel = get_string('gotocourse', 'block_vitrina');

            // If the user complete the course, disable the payment gateway.
            $this->course->haspaymentgw = false;

        } else if ($custom->enrolled) {

            $custom->enrolltitle = get_string('enrolled', 'block_vitrina');
            $custom->enrollurl = new \moodle_url('/course/view.php', ['id' => $this->course->id]);
            $custom->enrollurllabel = get_string('gotocourse', 'block_vitrina');

            // If the user is enrolled, disable the payment gateway.
            $this->course->haspaymentgw = false;

        } else if (has_capability('moodle/course:view', $coursecontext)) {

            $custom->enrolltitle = get_string('hascourseview', 'block_vitrina');
            $custom->enrollurl = new \moodle_url('/course/view.php', ['id' => $this->course->id]);
            $custom->enrollurllabel = get_string('gotocourse', 'block_vitrina');

            // If the user is enrolled, disable the payment gateway.
            $this->course->haspaymentgw = false;

        } else if ($this->course->enrollable) {

            $ispremium = \block_vitrina\controller::is_user_premium();
            if ($this->course->paymenturl && !$ispremium) {

                $custom->enrolltitle = get_string('paymentrequired', 'block_vitrina');
                $custom->enrollurl = $this->course->paymenturl;
                $custom->enrollurllabel = get_string('paymentbutton', 'block_vitrina');

            } else if ($this->course->haspaymentgw) {
                $custom->enrolltitle = get_string('paymentrequired', 'block_vitrina');
                $custom->requireauth = isguestuser() || !isloggedin();

                if ($custom->requireauth) {
                    $url = new \moodle_url('/blocks/vitrina/detail.php', ['id' => $this->course->id, 'tologin' => true]);
                    $custom->requireauthurl = $url;
                }

            } else if ($this->course->enrollasguest) {
                $custom->enrolltitle = get_string('allowguests', 'enrol_guest');
                $custom->enrollurl = new \moodle_url('/course/view.php', ['id' => $this->course->id]);
                $custom->enrollurllabel = get_string('gotocourse', 'block_vitrina');

            } else {

                $custom->enrolltitle = get_string('enrollrequired', 'block_vitrina');
                $custom->enrollurl = new \moodle_url('/blocks/vitrina/detail.php', ['id' => $this->course->id, 'enroll' => 1]);
                $custom->enrollurllabel = get_string('enroll', 'block_vitrina');
            }

        }

        $PAGE->requires->js_call_amd('block_vitrina/main', 'detail');

        // End Check enroled status.

        $defaultvariables = [
            'course' => $this->course,
            'custom' => $custom,
            'baseurl' => $CFG->wwwroot,
            'networks' => $socialnetworks,
            'detailinfo' => $detailinfo,
            'enrollstate' => $enrollstate,
            'coursename' => $coursename
        ];

        return $defaultvariables;
    }
}
