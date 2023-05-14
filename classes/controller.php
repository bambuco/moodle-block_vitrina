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
 * Class containing the general controls.
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_vitrina;

/**
 * Component controller.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controller {

    /**
     * @var int Cached payment field id.
     */
    protected static $cachedpayfield = null;

    /**
     * @var bool True if load full information about the course.
     */
    protected static $large = false;

    /**
     * @var int Instances includes in page request.
     */
    private static $instancescounter = 0;

    /**
     * Process a specific course to be displayed.
     *
     * @param object $course Course to be processed.
     * @param bool   $large  True if load full information about the course.
     */
    public static function course_preprocess($course, $large = false) {
        global $CFG, $OUTPUT, $DB, $PAGE, $USER;

        self::$large = $large;
        $course->haspaymentgw = false;
        $course->paymenturl = null;

        $payfield = \block_vitrina\controller::get_payfield();

        if ($payfield) {
            $course->paymenturl = $DB->get_field('customfield_data', 'value',
                                        ['fieldid' => $payfield->id, 'instanceid' => $course->id]);
        }

        // Load course context to general purpose.
        $coursecontext = \context_course::instance($course->id, $USER, '', true);

        // Load the course enrol info.
        $enrolinstances = enrol_get_instances($course->id, true);

        $course->enrollable = false;
        $course->enrollasguest = false;
        $course->fee = [];
        foreach ($enrolinstances as $instance) {
            if ($instance->enrol == 'self') {
                $course->enrollable = true;
                break;
            } else if ($instance->enrol == 'fee' && enrol_is_enabled('fee')) {

                $cost = (float) $instance->cost;
                if ( $cost <= 0 ) {
                    $cost = (float) get_config('enrol_fee', 'cost');
                }

                if ($cost > 0) {
                    $datafee = new \stdClass();
                    $datafee->cost = \core_payment\helper::get_cost_as_string($cost, $instance->currency);
                    $datafee->itemid = $instance->id;
                    $datafee->label = !empty($instance->name) ? $instance->name : get_string('sendpaymentbutton', 'enrol_fee');
                    $datafee->description = get_string('purchasedescription', 'enrol_fee',
                                                format_string($course->fullname, true, ['context' => $coursecontext]));

                    $course->fee[] = $datafee;
                    $course->enrollable = true;
                    $course->haspaymentgw = true;
                }

            } else if ($instance->enrol == 'guest' && enrol_is_enabled('guest')) {

                $course->enrollable = true;
                $course->enrollasguest = true;

            }
        }

        // If course has a single cost, load it for fast printing.
        if (count($course->fee) == 1) {
            $course->cost = $course->fee[0]->cost;
        }

        $course->imagepath = self::get_courseimage($course);

        $bmanager = new \block_manager($PAGE);

        if (!property_exists($course, 'rating')) {
            if ($bmanager->is_known_block_type('rate_course')) {

                if ($large) {
                    $values = $DB->get_records('block_rate_course', ['course' => $course->id], '', 'id, rating');

                    // Start default array to 1-5 stars.
                    $ratinglist = [0, 0, 0, 0, 0, 0];
                    unset($ratinglist[0]);

                    $ratingsum = 0;
                    foreach ($values as $one) {
                        $ratinglist[$one->rating]++;
                        $ratingsum += $one->rating;
                    }

                    $ratings = count($values);
                    $rating = $ratings > 0 ? $ratingsum / $ratings : 0;

                    $ratingpercents = [];
                    foreach ($ratinglist as $key => $one) {
                        $ratingpercents[$key] = $ratings > 0 ? round($one * 100 / $ratings) : 0;
                    }
                } else {
                    $sql = "SELECT AVG(rating) AS rating, COUNT(1) AS ratings  FROM {block_rate_course} WHERE course = :courseid";
                    $rate = $DB->get_record_sql($sql, ['courseid' => $course->id]);
                    $ratinglist = null;
                    $rating = $rate->rating;
                    $ratings = $rate->ratings;
                }

                $course->rating = new \stdClass();
                $course->rating->total = $rating;
                $course->rating->count = $ratings;

                if ($ratinglist) {
                    $course->rating->detail = [];
                    foreach ($ratinglist as $key => $one) {
                        $detail = new \stdClass();
                        $detail->value = $key;
                        $detail->count = $one;
                        $detail->avg = round($ratingpercents[$key]);
                        $course->rating->detail[] = $detail;
                    }
                } else {
                    $course->rating->detail = null;
                }
            }
        }

        if (property_exists($course, 'rating') && $course->rating) {

            if (!is_object($course->rating)) {
                $rating = $course->rating;
                $course->rating = new \stdClass();
                $course->rating->total = $rating;
                $course->rating->count = property_exists($course, 'ratings') ? $course->ratings : 0;
                $course->rating->detail = null;
            }

            // Not rating course.
            if ($course->rating->total == 0) {
                $course->rating = null;
            } else {
                $course->rating->total = round($course->rating->total, 1);
                $course->rating->percent = round($course->rating->total * 20);
                $course->rating->formated = str_pad($course->rating->total, 3, '.0');
                $course->rating->stars = $course->rating->total > 0 ? range(1, $course->rating->total) : null;
            }
        }

        // If course is active or waiting.
        $course->active = $course->startdate <= time();

        // Load data for course detail.
        if ($large) {
            $fullcourse = new \core_course_list_element($course);

            $course->commentscount = $DB->count_records('comments', ['contextid' => $coursecontext->id,
                                                                     'component' => 'block_comments']);

            if ($course->commentscount > 0) {
                $course->hascomments = true;

                // Get 20 newest records.
                $course->comments = $DB->get_records('comments',
                                                     ['contextid' => $coursecontext->id, 'component' => 'block_comments'],
                                                     'timecreated DESC', '*', 0, 20);

                $course->comments = array_values($course->comments);

                $strftimeformat = get_string('strftimerecentfull', 'langconfig');

                foreach ($course->comments as $comment) {
                    $user = $DB->get_record('user', ['id' => $comment->userid]);
                    $userpicture = new \user_picture($user, ['alttext' => false, 'link' => false]);
                    $userpicture->size = 200;
                    $comment->userpicture = $userpicture->get_url($PAGE);
                    $comment->timeformated = userdate($comment->timecreated, $strftimeformat);
                    $comment->userfirstname = $user->firstname;
                }
            } else {
                $course->hascomments = false;
                $course->comments = null;
            }

            // Search related courses by tags.
            $course->hasrelated = false;
            $course->related = [];
            $related = [];
            $relatedlimit = 3;

            $categories = get_config('block_vitrina', 'categories');

            $categoriesids = [];
            $catslist = explode(',', $categories);
            foreach ($catslist as $catid) {
                if (is_numeric($catid)) {
                    $categoriesids[] = (int)trim($catid);
                }
            }

            $categoriescondition = '';
            if (count($categoriesids) > 0) {
                $categoriescondition = " AND c.category IN (" . implode(',', $categoriesids) . ")";
            }

            if (\core_tag_tag::is_enabled('core', 'course')) {
                // Get the course tags.
                $tags = \core_tag_tag::get_item_tags_array('core', 'course', $course->id);

                if (count($tags) > 0) {
                    $ids = [];
                    foreach ($tags as $key => $tag) {
                        $ids[] = $key;
                    }

                    $sqlintances = "SELECT c.id, c.category FROM {tag_instance} t " .
                                    " INNER JOIN {course} c ON t.itemtype = 'course' AND c.id = t.itemid" .
                                    " WHERE t.tagid IN (" . (implode(',', $ids)) . ") " . $categoriescondition .
                                    " GROUP BY c.id, c.category" .
                                    " ORDER BY t.timemodified DESC";

                    $instances = $DB->get_records_sql($sqlintances);

                    foreach ($instances as $instance) {
                        if ($instance->id != $course->id &&
                                $instance->id != SITEID &&
                                count($related) < $relatedlimit &&
                                !in_array($instance->id, $related)) {

                            $related[] = $instance->id;
                        }
                    }
                }
            }

            if (count($related) < $relatedlimit) {
                // Exclude previous related courses, current course and the site.
                $relatedids = implode(',', array_merge($related, [$course->id, SITEID]));
                $sql = "SELECT id FROM {course} c " .
                        " WHERE visible = 1 AND (enddate > :enddate OR enddate IS NULL) AND id NOT IN ($relatedids)" .
                        $categoriescondition .
                        " ORDER BY startdate DESC";
                $params = ['enddate' => time()];
                $othercourses = $DB->get_records_sql($sql, $params, 0, $relatedlimit - count($related));

                foreach ($othercourses as $other) {
                    $related[] = $other->id;
                }
            }

            if (count($related) > 0) {
                $course->hasrelated = true;

                $coursesinfo = $DB->get_records_list('course', 'id', $related);

                // Load other info about the courses.
                foreach ($coursesinfo as $one) {

                    $one->imagepath = self::get_courseimage($one);
                    $one->active = $one->startdate <= time();
                    if ($payfieldid) {
                        $one->paymenturl = $DB->get_field('customfield_data', 'value',
                                                    ['fieldid' => $payfieldid, 'instanceid' => $one->id]);
                    }

                    if ($bmanager->is_known_block_type('rate_course')) {
                        $sql = "SELECT AVG(rating) AS rating, COUNT(1) AS ratings  FROM {block_rate_course} WHERE course = :cid";
                        $rate = $DB->get_record_sql($sql, ['cid' => $one->id]);

                        $one->rating = new \stdClass();
                        $one->rating->total = 0;
                        $one->rating->count = 0;
                        $one->rating->detail = null;

                        if ($rate) {
                            $one->rating->total = round($rate->rating, 1);
                            $one->rating->count = $rate->ratings;
                            $one->rating->percent = round($one->rating->total * 20);
                            $one->rating->formated = str_pad($one->rating->total, 3, '.0');
                            $one->rating->stars = $one->rating->total > 0 ? range(1, $one->rating->total) : null;
                        }
                    }

                    $course->related[] = $one;
                }
            }

            // Load the teachers information.
            $course->hasinstructors = false;

            if ($fullcourse->has_course_contacts()) {
                $course->hasinstructors = true;
                $course->instructors = [];
                $instructors = $fullcourse->get_course_contacts();

                foreach ($instructors as $key => $instructor) {

                    $user = $DB->get_record('user', ['id' => $key]);
                    $userpicture = new \user_picture($user, ['alttext' => false, 'link' => false]);
                    $userpicture->size = 200;
                    $user->userpicture = $userpicture->get_url($PAGE);
                    $user->profileurl = $CFG->wwwroot . '/user/profile.php?id=' . $key;

                    $course->instructors[] = $user;
                }
            }
        }
    }

    /**
     * Define if premium features are available.
     *
     * @return boolean
     */
    public static function premium_available() : bool {

        $payfield = self::get_payfield();
        return $payfield ? true : false;
    }

    /**
     * Get the payment field.
     *
     * @return object The payment field.
     */
    public static function get_payfield() : object {
        global $DB;

        if (!self::$cachedpayfield) {
            $paymenturlfield = get_config('block_vitrina', 'paymenturl');
            if (!empty($paymenturlfield)) {
                self::$cachedpayfield = $DB->get_record('customfield_field', ['id' => $paymenturlfield]);
            }
        }

        return self::$cachedpayfield ?? null;
    }

    /**
     * Define if the current or received user is premium.
     *
     * @param stdClass $user User object.
     * @return boolean
     */
    public static function is_user_premium($user = null) : bool {
        global $USER, $DB;
        if (!$user) {
            $user = $USER;
        }

        $premiumfieldid = get_config('block_vitrina', 'premiumfield');
        $premiumvalue = get_config('block_vitrina', 'premiumvalue');

        if (empty($premiumfieldid) || empty($premiumvalue)) {
            return false;
        }

        $premiumfield = $DB->get_field('user_info_field', 'shortname', ['id' => $premiumfieldid]);

        if (empty($premiumfield)) {
            return false;
        }

        if (isset($user->profile[$premiumfield]) && $user->profile[$premiumfield] == $premiumvalue) {
            return true;
        }

        return false;
    }

    /**
     * Get the course preview image.
     *
     * @param stdClass $course Course object.
     * @return string Image url.
     */
    public static function get_courseimage($course) : string {
        global $CFG, $OUTPUT;

        $coursefull = new \core_course_list_element($course);

        $courseimage = '';
        foreach ($coursefull->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            $url = file_encode_url("$CFG->wwwroot/pluginfile.php",
                    '/'. $file->get_contextid(). '/'. $file->get_component(). '/'.
                    $file->get_filearea(). $file->get_filepath(). $file->get_filename(), !$isimage);
            if ($isimage) {
                $courseimage = $url;
                break;
            }
        }

        if (empty($courseimage)) {
            $type = get_config('block_vitrina', 'coverimagetype');

            switch ($type) {
                case 'generated':
                    $courseimage = $OUTPUT->get_generated_image_for_id($course->id);
                break;
                case 'none':
                    $courseimage = '';
                break;
                default:
                    $courseimage = (string)(new \moodle_url($CFG->wwwroot . '/blocks/vitrina/pix/' .
                                                                (self::$large ? 'course' : 'course_small') . '.png'));
            }
        }

        return $courseimage;
    }

    /**
     * Include a CSS file according the current used template.
     *
     * @return void
     */
    public static function include_templatecss() {

        global $CFG, $PAGE;

        $template = get_config('block_vitrina', 'templatetype');
        $csspath = $CFG->dirroot . '/blocks/vitrina/templates/' . $template . '/styles.css';

        // If the template is not the default and a templace CSS file exist, include the CSS file.
        if ($template != 'default' && file_exists($csspath)) {
            $PAGE->requires->css('/blocks/vitrina/templates/' . $template . '/styles.css');
        }

    }

    /**
     * Generate a unique id for block instance.
     *
     * @return string Unique identifier.
     */
    public static function get_uniqueid() {
        $uniqueid = 'block_vitrina_' . self::$instancescounter;
        self::$instancescounter++;

        return $uniqueid;
    }
}
