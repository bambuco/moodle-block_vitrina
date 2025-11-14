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
     * @var array Enrollment messages.
     */
    private $enrolmsg = [];

    /**
     * Constructor.
     *
     * @param object $course A course
     * @param array $enrolmsg Enrollment messages
     */
    public function __construct($course, array $enrolmsg = []) {

        \block_vitrina\local\controller::course_preprocess($course, true);
        $this->course = $course;
        $this->enrolmsg = $enrolmsg;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array Context variables for the template
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $PAGE, $USER, $DB;

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
                $network->url = str_replace('{urlencoded}', urlencode($courseurl), $network->url);
                $network->url = str_replace('{name}', $this->course->fullname, $network->url);
                $socialnetworks[] = $network;
            }
        }

        // Load custom course fields.
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $datas = $handler->get_instance_data($this->course->id);
        $fields = ['license', 'media', 'mediaposter'];
        $custom = new \stdClass();

        // Select specific fields to display.
        $fieldids = [];
        foreach ($fields as $field) {
            $id = get_config('block_vitrina', $field);

            if (!empty($id)) {
                $fieldids[$field] = $id;
            }
        }

        $custom->customfields = [];
        $custom->longcustomfields = [];
        $custom->hascustomfields = false;
        $custom->haslongcustomfields = false;

        // Select generic short fields to display.
        $showcustomfields = get_config('block_vitrina', 'showcustomfields');

        if (!empty($showcustomfields)) {
            $showcustomfields = explode(',', $showcustomfields);
        }

        if (!$showcustomfields || count($showcustomfields) == 0) {
            $showcustomfields = [];
        }

        // Select generic long fields to display.
        $showlongfields = get_config('block_vitrina', 'showlongcustomfields');

        if (!empty($showlongfields)) {
            $showlongfields = explode(',', $showlongfields);
        }

        if (!$showlongfields || count($showlongfields) == 0) {
            $showlongfields = [];
        }

        $imgextentions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        foreach ($datas as $data) {
            $key = $data->get_field()->get('id');

            $exist = false;
            foreach ($fieldids as $field => $id) {
                if ($id == $key) {
                    $c = new \stdClass();
                    $c->title = format_text($data->get_field()->get('name'), FORMAT_HTML);

                    $c->value = $data->export_value();

                    if (!empty($c->value)) {
                        if ($field == 'license') {
                            if (get_string_manager()->string_exists('license-' . $c->value, 'block_vitrina')) {
                                $c->text = get_string('license-' . $c->value, 'block_vitrina');
                                $c->path = $c->value == 'cc-0' ? 'zero/1.0' : trim($c->value, 'cc-') . '/4.0';
                            } else {
                                $c->text = $c->value;
                            }
                        } else if ($field == 'media') {
                            if (
                                strpos($c->value, 'https://www.youtube.com') === 0 ||
                                strpos($c->value, 'https://youtube.com') === 0 ||
                                strpos($c->value, 'https://player.vimeo.com') === 0
                            ) {
                                $c->isembed = true;
                            } else if (in_array(pathinfo(strtolower($c->value), PATHINFO_EXTENSION), $imgextentions)) {
                                $c->isimage = true;
                            }
                        }

                        $custom->$field = $c;
                    }

                    $exist = true;
                    break;
                }
            }

            if (!$exist) {
                $value = $data->export_value();

                if (is_string($value)) {
                    $value = trim($value);
                }

                if (!empty($value)) {
                    $c = new \stdClass();
                    $c->title = format_text($data->get_field()->get('name'), FORMAT_HTML);
                    $c->value = $value;
                    $c->key = $key;
                    $c->shortname = $data->get_field()->get('shortname');

                    if (in_array($key, $showcustomfields)) {
                        $custom->customfields[] = $c;
                    } else if (in_array($key, $showlongfields)) {
                        $custom->longcustomfields[] = $c;
                    } else {
                        $custom->{$c->shortname} = $c;
                    }
                }
            }
        }

        $custom->hascustomfields = count($custom->customfields) > 0;
        $custom->haslongcustomfields = count($custom->longcustomfields) > 0;

        // End Load custom course fields.

        // Load the course context.
        $coursecontext = \context_course::instance($this->course->id, $USER, '', true);

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

        $custom->completed = $this->course->completed ?? false;
        $custom->progress = $this->course->progress ?? null;
        $custom->hasprogress = $this->course->hasprogress;

        $enrollstate = $custom->completed ? 'completed' : ($custom->enrolled ? 'enrolled' : 'none');

        $custom->enrolltitle = get_string('notenrollable', 'block_vitrina');
        $custom->enrollurl = null;
        $custom->enrollurllabel = '';
        $custom->requireauth = false;

        $sesskey = sesskey();

        $shoppluginname = get_config('block_vitrina', 'shopmanager');
        $shopmanager = null;
        if (!empty($shoppluginname)) {
            $shopmanager = 'block_vitrina\local\shop\\' . $shoppluginname;
        }

        if ($custom->completed) {
            $custom->enrolltitle = get_string('completed', 'block_vitrina');
            $custom->enrollurl = new \moodle_url('/course/view.php', ['id' => $this->course->id]);
            $custom->enrollurllabel = get_string('gotocourse', 'block_vitrina');

            // If the user complete the course, disable the payment gateway.
            $this->course->haspaymentgw = false;
        } else if ($custom->enrolled) {
            // Look for active enrolments only.
            $until = enrol_get_enrolment_end($coursecontext->instanceid, $USER->id);

            if ($until === false) {
                $custom->enrolltitle = get_string('enrolledended', 'block_vitrina');
            } else {
                $custom->enrolltitle = get_string('enrolled', 'block_vitrina');
                $custom->enrollurl = new \moodle_url('/course/view.php', ['id' => $this->course->id]);
                $custom->enrollurllabel = get_string('gotocourse', 'block_vitrina');
            }

            // If the user is enrolled, disable the payment gateway.
            $this->course->haspaymentgw = false;
        } else if (has_capability('moodle/course:view', $coursecontext)) {
            $custom->enrolltitle = get_string('hascourseview', 'block_vitrina');
            $custom->enrollurl = new \moodle_url('/course/view.php', ['id' => $this->course->id]);
            $custom->enrollurllabel = get_string('gotocourse', 'block_vitrina');

            // If the user is enrolled, disable the payment gateway.
            $this->course->haspaymentgw = false;
        } else if (!empty($this->course->paymenturl)) {
            $custom->enrolltitle = get_string('paymentrequired', 'block_vitrina');
            $custom->enrollurl = $this->course->paymenturl;
            $custom->enrollurllabel = get_string('paymentbutton', 'block_vitrina');
        } else if ($this->course->enrollable) {
            $custom->enrollform = [];
            if (array_key_exists('guest', $this->course->enrollsavailables)) {
                $custom->enrolltitle = get_string('allowguests', 'enrol_guest');
                $custom->enrollurl = new \moodle_url('/course/view.php', ['id' => $this->course->id]);
                $custom->enrollurllabel = get_string('gotocourse', 'block_vitrina');
            } else if (array_key_exists('premium', $this->course->enrollsavailables)) {
                $custom->enrolltitle = get_string('enrollavailablepremium', 'block_vitrina');
                $params = ['id' => $this->course->id, 'enroll' => 1, 'sesskey' => $sesskey];
                $custom->enrollurl = new \moodle_url('/blocks/vitrina/detail.php', $params);
                $custom->enrollurllabel = get_string('enroll', 'block_vitrina');

                // If the user is premium, disable the payment gateway.
                $this->course->haspaymentgw = false;
            } else {
                if (array_key_exists('self', $this->course->enrollsavailables)) {
                    $enrolplugin = enrol_get_plugin('self');
                    $enrolopen = false;
                    foreach ($this->course->enrollsavailables['self'] as $instance) {
                        if (empty($instance->password)) {
                            $enrolopen = true;
                            break;
                        }
                    }

                    if (!$enrolopen) {
                        $custom->enrolltitle = get_string('enrollrequired', 'block_vitrina');
                        if (count($this->course->enrollsavailables['self']) > 1) {
                            $content = \html_writer::start_tag('select', ['name' => 'enrolid', 'class' => 'custom-select']);

                            foreach ($this->course->enrollsavailables['self'] as $instance) {
                                $name = $enrolplugin->get_instance_name($instance);
                                $content .= \html_writer::tag('option', $name, [
                                    'value' => $instance->id,
                                ]);
                            }

                            $content .= \html_writer::end_tag('select');

                            $label = get_string('pluginname', 'enrol_self');
                        } else {
                            $instance = reset($this->course->enrollsavailables['self']);
                            $content = \html_writer::tag('input', '', [
                                'type' => 'hidden',
                                'name' => 'enrolid',
                                'value' => $instance->id,
                            ]);

                            $label = $enrolplugin->get_instance_name($instance);
                        }

                        $content .= \html_writer::tag('input', '', [
                            'type' => 'password',
                            'name' => 'enrolpassword',
                        ]);

                        $params = [
                            'id' => $this->course->id,
                            'enroll' => 1,
                            'sesskey' => $sesskey,
                        ];
                        $custom->enrollform[] = (object) [
                            'sesskey' => sesskey(),
                            'courseid' => $this->course->id,
                            'enrollurl' => new \moodle_url('/blocks/vitrina/detail.php', $params),
                            'enrol' => 'self',
                            'label' => $label,
                            'content' => $content,
                        ];
                    } else {
                        $custom->enrolltitle = get_string('enrollrequired', 'block_vitrina');
                        $params = ['id' => $this->course->id, 'enroll' => 1, 'sesskey' => $sesskey, 'enroltype' => 'self'];
                        $custom->enrollurl = new \moodle_url('/blocks/vitrina/detail.php', $params);
                        $custom->enrollurllabel = get_string('enroll', 'block_vitrina');
                    }

                    // If the user can self-enroll, disable the payment gateway.
                    $this->course->haspaymentgw = false;
                }

                if (array_key_exists('customgr', $this->course->enrollsavailables)) {
                    $custom->requireauth = isguestuser() || !isloggedin();

                    $custom->enrolltitle = get_string('enrollrequired', 'block_vitrina');

                    if (!$custom->requireauth) {
                        $enrolplugin = enrol_get_plugin('customgr');

                        if (count($this->course->enrollsavailables['customgr']) > 1) {
                            $content = \html_writer::start_tag('select', ['name' => 'enrolid', 'class' => 'custom-select']);

                            foreach ($this->course->enrollsavailables['customgr'] as $instance) {
                                $name = $enrolplugin->get_instance_name($instance);
                                $content .= \html_writer::tag('option', $name, [
                                    'value' => $instance->id,
                                ]);
                            }

                            $content .= \html_writer::end_tag('select');

                            $label = get_string('customgrenroll', 'block_vitrina');
                        } else {
                            $instance = reset($this->course->enrollsavailables['customgr']);
                            $content = \html_writer::tag('input', '', [
                                'type' => 'hidden',
                                'name' => 'enrolid',
                                'value' => $instance->id,
                            ]);

                            $label = $enrolplugin->get_instance_name($instance);
                        }

                        $params = [
                            'id' => $this->course->id,
                            'enroll' => 1,
                            'sesskey' => $sesskey,
                        ];
                        $custom->enrollform[] = (object) [
                            'sesskey' => sesskey(),
                            'courseid' => $this->course->id,
                            'enrollurl' => new \moodle_url('/blocks/vitrina/detail.php', $params),
                            'enrol' => 'customgr',
                            'label' => $label,
                            'content' => $content,
                        ];
                    }

                    // If the user can self-enroll, disable the payment gateway.
                    $this->course->haspaymentgw = false;
                }

                if (array_key_exists('token', $this->course->enrollsavailables)) {
                    $custom->requireauth = isguestuser() || !isloggedin();

                    $custom->enrolltitle = get_string('enrollrequired', 'block_vitrina');

                    if (!$custom->requireauth) {
                        $enrolplugin = enrol_get_plugin('token');

                        if (count($this->course->enrollsavailables['token']) > 1) {
                            $content = \html_writer::start_tag('select', ['name' => 'enrolid', 'class' => 'custom-select']);

                            foreach ($this->course->enrollsavailables['token'] as $instance) {
                                $name = $enrolplugin->get_instance_name($instance);
                                $content .= \html_writer::tag('option', $name, [
                                    'value' => $instance->id,
                                ]);
                            }

                            $content .= \html_writer::end_tag('select');

                            $label = get_string('tokenenroll', 'block_vitrina');
                        } else {
                            $instance = reset($this->course->enrollsavailables['token']);
                            $content = \html_writer::tag('input', '', [
                                'type' => 'hidden',
                                'name' => 'enrolid',
                                'value' => $instance->id,
                            ]);

                            $label = $enrolplugin->get_instance_name($instance);
                        }

                        $content .= \html_writer::tag('input', '', [
                            'type' => 'text',
                            'name' => 'enroltoken',
                        ]);

                        $params = [
                            'id' => $this->course->id,
                            'enroll' => 1,
                            'sesskey' => $sesskey,
                        ];
                        $custom->enrollform[] = (object) [
                            'sesskey' => sesskey(),
                            'courseid' => $this->course->id,
                            'enrollurl' => new \moodle_url('/blocks/vitrina/detail.php', $params),
                            'enrol' => 'token',
                            'label' => $label,
                            'content' => $content,
                        ];
                    }

                    // If the user can self-enroll, disable the payment gateway.
                    $this->course->haspaymentgw = false;
                }

                if ($this->course->haspaymentgw) {
                    $custom->enrolltitle = get_string('paymentrequired', 'block_vitrina');

                    if ($shopmanager) {
                        $custom->hascart = true;
                        $custom->shopmanager = $shopmanager::render_from_template();
                        foreach ($this->course->fee as $fee) {
                            $fee->reference = $shopmanager::get_product_reference('enrol_fee', $fee->itemid);
                        }
                    } else {
                        $custom->requireauth = isguestuser() || !isloggedin();
                        $custom->successurl = new \moodle_url('/blocks/vitrina/detail.php', [
                            'id' => $this->course->id,
                            'msg' => 'enrolled',
                        ]);
                    }
                }
            }
        }

        if ($custom->requireauth) {
            $url = new \moodle_url('/blocks/vitrina/detail.php', ['id' => $this->course->id, 'tologin' => true]);
            $custom->requireauthurl = $url;
        }

        if ($this->course->hasrelated && $shopmanager && !$this->course->enrolled && !$this->course->canview) {
            foreach ($this->course->related as $onerelated) {
                $onerelated->hascart = true;
                $onerelated->shopmanager = $shopmanager::render_from_template();
                foreach ($onerelated->fee as $fee) {
                    $fee->reference = $shopmanager::get_product_reference('enrol_fee', $fee->itemid);
                }
            }
        }

        $PAGE->requires->js_call_amd('block_vitrina/main', 'detail');

        // End Check enrolled status.

        $defaultvariables = [
            'course' => $this->course,
            'custom' => $custom,
            'baseurl' => $CFG->wwwroot,
            'networks' => $socialnetworks,
            'detailinfo' => $detailinfo,
            'enrollstate' => $enrollstate,
            'coursename' => $coursename,
            'originalcoursename' => $this->course->fullname,
            'hasenrollmsg' => !empty($this->enrolmsg),
            'enrolmsg' => $this->enrolmsg,
            'opendetailstarget' => get_config('block_vitrina', 'opendetailstarget'),
        ];

        return $defaultvariables;
    }
}
