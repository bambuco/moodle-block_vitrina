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
namespace block_vitrina\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');

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
     * @var int Cached premium field id.
     */
    protected static $cachedpremiumfield = null;

    /**
     * @var bool True if load full information about the course.
     */
    protected static $large = false;

    /**
     * @var int Instances includes in page request.
     */
    private static $instancescounter = 0;

    /**
     * @var array List of icons for the views.
     */
    private static $viewsicons = null;

    /**
     * @var bool True if show icons in tabs views.
     */
    private static $showicons = null;

    /**
     * @var bool True if show text in tabs views.
     */
    private static $showtext = null;

    /**
     * @var bool True if the user is premium.
     */
    private static $isuserpremium = null;

    /**
     * @var string Membership type.
     */
    private static $usermembership = null;

    /**
     * @var array List of available courses views.
     */
    public const COURSES_VIEWS = ['default', 'recents', 'greats', 'premium'];

    /**
     * @var array List of available sorts.
     */
    public const COURSES_SORTS = ['default', 'alphabetically', 'startdate', 'finishdate'];

    /**
     * @var array List of available types in custom fields to filter.
     */
    public const CUSTOMFIELDS_SUPPORTED = ['select', 'checkbox', 'multiselect'];

    /**
     * @var array List of available static filters (not include filters by custom fields).
     */
    public const STATICFILTERS = ['langs', 'categories', 'fulltext'];

    /**
     * @var string The user is premium by user field.
     */
    public const PREMIUMBYFIELD = 'field';

    /**
     * @var string The user is premium by enrolled course.
     */
    public const PREMIUMBYCOURSE = 'course';

    /**
     * @var string The user is premium by cohort.
     */
    public const PREMIUMBYCOHORT = 'cohort';

    /**
     * Process a specific course to be displayed.
     *
     * @param object $course Course to be processed.
     * @param bool   $large  True if load full information about the course.
     */
    public static function course_preprocess($course, $large = false) {
        global $CFG, $DB, $PAGE, $USER;

        $isuserpremium = self::is_user_premium();

        self::$large = $large;
        $course->haspaymentgw = false;
        $course->paymenturl = null;
        $course->baseurl = $CFG->wwwroot;
        $course->hassummary = !empty($course->summary);
        $course->fullname = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);
        $course->summary = format_text($course->summary, $course->summaryformat);

        $payfield = self::get_payfield();
        if (!$isuserpremium) {
            if ($payfield) {
                $course->paymenturl = $DB->get_field(
                    'customfield_data',
                    'value',
                    ['fieldid' => $payfield->id, 'instanceid' => $course->id]
                );
            }
        }

        $premiumfield = self::get_premiumfield();

        if ($premiumfield) {
            $course->premium = $DB->get_field(
                'customfield_data',
                'value',
                ['fieldid' => $premiumfield->id, 'instanceid' => $course->id]
            );
        } else {
            $course->premium = null;
        }

        // Load the course enrol info.
        self::load_enrolinfo($course);

        if ((!$premiumfield || $course->premium) && $isuserpremium) {
            $course->fee = null;
        } else {
            // If course has a single cost, load it for fast printing.
            if (count($course->fee) == 1) {
                $course->cost = $course->fee[0]->cost;
            }
        }

        $course->imagepath = self::get_courseimage($course);

        $ratemanager = self::get_ratemanager();
        $ratingavailable = $ratemanager::rating_available();

        if (!property_exists($course, 'rating')) {
            if ($ratingavailable) {
                $course->rating = $ratemanager::get_ratings($course, $large);
            }
        }

        if (property_exists($course, 'rating') && $course->rating) {
            if (!is_object($course->rating)) {
                $rating = $course->rating;
                $course->rating = new \stdClass();
                $course->rating->total = $rating;
                $course->rating->count = property_exists($course, 'ratings') ? $course->ratings : 0;
                $course->rating->detail = null;
                $course->hasrating = $course->rating->count > 0;
            }

            // Not rating course.
            if ($course->rating->total == 0) {
                $course->rating = null;
            } else {
                $course->rating->total = round($course->rating->total, 1);
                $course->rating->percent = round($course->rating->total * 20);
                $course->rating->formated = str_pad($course->rating->total, 3, '.0');
                $course->rating->stars = $course->rating->total > 0 ? range(1, $course->rating->total) : null;
                $course->hasrating = $course->rating->count > 0;
            }
        }

        // If course is active or waiting.
        $course->active = $course->startdate <= time();

        // Course progress.
        if (isloggedin() && !isguestuser() && !empty($course->enablecompletion)) {
            $completioninfo = new \completion_info($course);
            $course->completed = $completioninfo->is_course_complete($USER->id);
            $course->progress = \core_completion\progress::get_course_progress_percentage($course);
            $course->progress = is_numeric($course->progress) ? round($course->progress) : null;
            $course->hasprogress = $course->progress ?? false;
        } else {
            $course->completed = null;
            $course->progress = null;
            $course->hasprogress = false;
        }

        // Load data for course detail.
        if ($large) {
            $fullcourse = new \core_course_list_element($course);

            $commentsmanager = self::get_commentsmanager();
            $comments = $commentsmanager::get_comments($course);
            $course->commentscount = count($comments);
            $course->latestcomments = null;

            if ($course->commentscount > 0) {
                $course->hascomments = true;
                $course->comments = array_values($comments);

                $strftimeformat = get_string('strftimerecentfull', 'langconfig');

                foreach ($course->comments as $comment) {
                    $user = $DB->get_record('user', ['id' => $comment->userid]);
                    $userpicture = new \user_picture($user, ['alttext' => false, 'link' => false]);
                    $userpicture->size = 200;
                    $comment->userpicture = $userpicture->get_url($PAGE);
                    $comment->timeformated = userdate($comment->timecreated, $strftimeformat);
                    $comment->userfirstname = $user->firstname;
                    $comment->userlastname = $user->lastname;
                }

                $course->latestcomments = array_slice($course->comments, 0, 3);
            } else {
                $course->hascomments = false;
                $course->comments = null;
            }

            // Search related courses by tags.
            $course->hasrelated = false;
            $course->related = [];
            $related = [];
            $relatedlimit = get_config('block_vitrina', 'relatedlimit');

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

            if (!empty($relatedlimit) && \core_tag_tag::is_enabled('core', 'course')) {
                // Get the course tags.
                $tags = \core_tag_tag::get_item_tags_array('core', 'course', $course->id);

                if (count($tags) > 0) {
                    $ids = [];
                    foreach ($tags as $key => $tag) {
                        $ids[] = $key;
                    }

                    $sqlintances = "SELECT c.id, c.category FROM {tag_instance} t " .
                                    " INNER JOIN {course} c ON t.itemtype = 'course' AND c.id = t.itemid AND c.visible = 1" .
                                    " WHERE t.tagid IN (" . (implode(',', $ids)) . ") " . $categoriescondition .
                                    " GROUP BY c.id, c.category" .
                                    " ORDER BY t.timemodified DESC";

                    $instances = $DB->get_records_sql($sqlintances);

                    foreach ($instances as $instance) {
                        if (
                            $instance->id != $course->id &&
                            $instance->id != SITEID &&
                            count($related) < $relatedlimit &&
                            !in_array($instance->id, $related)
                        ) {
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
                    $one->hassummary = !empty($one->summary);
                    $one->imagepath = self::get_courseimage($one);
                    $one->active = $one->startdate <= time();
                    $one->fullname = format_string($one->fullname, true, ['context' => \context_course::instance($one->id)]);
                    $one->summary = format_text($one->summary, $course->summaryformat);

                    if (!$isuserpremium && $payfield) {
                        $one->paymenturl = $DB->get_field(
                            'customfield_data',
                            'value',
                            ['fieldid' => $payfield->id, 'instanceid' => $one->id]
                        );
                    }

                    if ($premiumfield) {
                        $one->premium = $DB->get_field(
                            'customfield_data',
                            'value',
                            ['fieldid' => $premiumfield->id, 'instanceid' => $one->id]
                        );
                    }

                    if ($ratingavailable) {
                        $one->rating = new \stdClass();
                        $one->rating->total = 0;
                        $one->rating->count = 0;
                        $one->rating->detail = null;
                        $one->hasrating = false;

                        $ratemanager = self::get_ratemanager();
                        $onerating = $ratemanager::get_ratings($one->id, $large);

                        if ($onerating && $onerating->count > 0) {
                            $one->rating->total = round($onerating->rating, 1);
                            $one->rating->count = $onerating->count;
                            $one->rating->percent = round($onerating->total * 20);
                            $one->rating->formated = str_pad($onerating->total, 3, '.0');
                            $one->hasrating = true;
                            $one->rating->stars = $onerating->total > 0 ? range(1, $onerating->total) : null;
                        }
                    }

                    // Load the related course enrol info.
                    self::load_enrolinfo($one);
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
                    $user->description = format_text($user->description, FORMAT_HTML);

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
    public static function premium_available(): bool {

        $premiumfield = self::get_premiumfield();
        return $premiumfield ? true : false;
    }

    /**
     * Get the payment field.
     *
     * @return object The payment field.
     */
    public static function get_payfield(): ?object {
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
     * Get the premium field.
     *
     * @return object The premium field.
     */
    public static function get_premiumfield(): ?object {
        global $DB;

        if (!self::$cachedpremiumfield) {
            $premiumfield = get_config('block_vitrina', 'premiumcoursefield');
            if (!empty($premiumfield)) {
                self::$cachedpremiumfield = $DB->get_record('customfield_field', ['id' => $premiumfield]);
            }
        }

        return self::$cachedpremiumfield ?? null;
    }

    /**
     * Define if the current or received user is premium.
     *
     * @param stdClass $user User object.
     * @return boolean True if the user is premium.
     */
    public static function is_user_premium($user = null): bool {

        if (self::$isuserpremium !== null) {
            return self::$isuserpremium;
        }

        $membership = self::type_membership($user);

        return $membership ? true : false;
    }

    /**
     * Return the user membership type.
     *
     * @param stdClass $user User object.
     * @return string|null If the user is premium return the type of membership or null if not.
     */
    public static function type_membership($user = null): ?string {
        global $USER, $DB;

        if (self::$isuserpremium !== null) {
            return self::$usermembership;
        }

        if (!$user) {
            $user = $USER;
        }

        $premiumfieldid = get_config('block_vitrina', 'premiumfield');
        $premiumvalue = get_config('block_vitrina', 'premiumvalue');

        // If the premium field and value are set, check if the user is premium.
        // It overrides the "Course to read premium users" setting.
        if (!empty($premiumfieldid) && !empty($premiumvalue)) {
            $premiumfield = $DB->get_field('user_info_field', 'shortname', ['id' => $premiumfieldid]);

            if (!empty($premiumfield)) {
                if (isset($user->profile[$premiumfield]) && $user->profile[$premiumfield] == $premiumvalue) {
                    self::$isuserpremium = true;
                    self::$usermembership = self::PREMIUMBYFIELD;
                    return self::$usermembership;
                }
            }
        }

        // If the user is enrolled in the "Course to read premium users" is a premium user.
        $premiumcourseid = get_config('block_vitrina', 'premiumenrolledcourse');
        if (!empty($premiumcourseid)) {
            // Check if the user is enrolled in the premium course.
            if (is_enrolled(\context_course::instance($premiumcourseid), $user->id, '', true)) {
                self::$isuserpremium = true;
                self::$usermembership = self::PREMIUMBYCOURSE;
                return self::$usermembership;
            }
        }

        // If the user is in the cohort to premium users.
        $premiumcohort = get_config('block_vitrina', 'premiumcohort');
        if (!empty($premiumcohort)) {
            if (cohort_is_member($premiumcohort, $USER->id)) {
                self::$isuserpremium = true;
                self::$usermembership = self::PREMIUMBYCOHORT;
                return self::$usermembership;
            }
        }

        self::$isuserpremium = false;
        return null;
    }

    /**
     * Get the course preview image.
     *
     * @param \stdClass $course Course object.
     * @return string Image url.
     */
    public static function get_courseimage($course): string {
        global $CFG, $OUTPUT;

        $coursefull = new \core_course_list_element($course);

        $courseimage = '';
        foreach ($coursefull->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();

            if ($isimage) {
                $urlpath = '/' . $file->get_contextid() . '/' . $file->get_component() . '/';
                $urlpath .= $file->get_filearea() . $file->get_filepath() . $file->get_filename();

                $url = \moodle_url::make_file_url(
                    "$CFG->wwwroot/pluginfile.php",
                    $urlpath,
                    !$isimage
                );

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

    /**
     * Get the available courses views.
     */
    public static function get_courses_views(): array {

        $availablesorting = self::COURSES_VIEWS;

        $ratemanager = self::get_ratemanager();
        $ratingavailable = $ratemanager::rating_available();

        if (!$ratingavailable) {
            // Remove the greats value if the rate feature is not available.
            if (($key = array_search('greats', $availablesorting)) !== false) {
                unset($availablesorting[$key]);
            }
        }

        if (!self::premium_available()) {
            if (($key = array_search('premium', $availablesorting)) !== false) {
                unset($availablesorting[$key]);
            }
        }

        return $availablesorting;
    }

    /**
     * Get courses by view.
     *
     * @param string $view The view key.
     * @param array $categoriesids The categories ids.
     * @param array $filters A filters objects list with type and value.
     * @param string $sort The sort.
     * @param int $amount The amount of courses to get.
     * @param int $initial From where to start counting the next courses to get.
     * @return array The courses list.
     */
    public static function get_courses_by_view(
        string $view = 'default',
        array $categoriesids = [],
        array $filters = [],
        string $sort = '',
        int $amount = 0,
        int $initial = 0
    ): array {
        global $DB, $CFG;

        $availableviews = self::get_courses_views();
        if (!in_array($view, $availableviews)) {
            $view = 'default';
        }

        if (empty($sort) || !in_array($sort, self::COURSES_SORTS)) {
            $sort = get_config('block_vitrina', 'sortbydefault');
        }

        if (empty($amount)) {
            $amount = get_config('block_vitrina', 'singleamount');
        }

        if (count($categoriesids) == 0) {
            $categories = get_config('block_vitrina', 'categories');
            $catslist = explode(',', $categories);
            foreach ($catslist as $catid) {
                if (is_numeric($catid)) {
                    $categoriesids[] = (int) trim($catid);
                }
            }
        }

        $courses = [];
        $select = 'c.visible = 1 AND c.id <> :siteid AND (c.enddate > :now OR c.enddate = 0)';
        $params = ['siteid' => SITEID, 'now' => time()];

        // Add categories filter.
        if (count($categoriesids) > 0) {
            [$selectincats, $paramsincats] = $DB->get_in_or_equal($categoriesids, SQL_PARAMS_NAMED, 'categories');
            $params += $paramsincats;
            $select .= ' AND category ' . $selectincats;
        }
        // End of categories filter.

        $joincustomfields = '';
        $customfields = self::get_configuredcustomfields();

        // Add filters.
        foreach ($filters as $filter) {
            switch ($filter['type']) {
                case 'fulltext':
                    $text = trim(implode('%', $filter['values']));

                    if (!empty($text)) {
                        $text = $DB->sql_like_escape($text);
                        $text = str_replace(' ', '%', $text);

                        // To search in basic course fields.
                        $fieldstosearch = $DB->sql_concat_join("' '", ['c.fullname', 'c.shortname', 'c.summary']);
                        $like = $DB->sql_like($fieldstosearch, ':text', false);
                        $select .= ' AND ' . $like;
                        $params['text'] = '%' . $text . '%';

                        // To search in custom fields.
                        $like = $DB->sql_like('cfd.value', ':cftext', false);
                        $params['cftext'] = '%' . $text . '%';

                        $joincustomfields .= " LEFT JOIN {customfield_data} cfd ON c.id = cfd.instanceid AND " . $like;
                    }

                    break;
                case 'langs':
                    $langs = $filter['values'];
                    $defaultlang = $CFG->lang;

                    if (in_array($defaultlang, $langs)) {
                        $langs[] = '';
                    } else {
                        // Remove empty values.
                        $langs = array_filter($langs);
                    }

                    if (count($langs) > 0) {
                        [$selectinlangs, $paramsinlangs] = $DB->get_in_or_equal($langs, SQL_PARAMS_NAMED, 'langs');
                        $params = array_merge($params, $paramsinlangs);
                        $select .= ' AND c.lang ' . $selectinlangs;
                    }

                    break;
                default:
                    // Custom fields filters values.

                    // Cast to int.
                    $customfieldid = (int) $filter['type'];

                    if (empty($customfieldid)) {
                        break;
                    }

                    // By security. Only allow to filter by selected custom fields.
                    if (!isset($customfields[$customfieldid])) {
                        break;
                    }

                    $currentfield = $customfields[$customfieldid];

                    $values = array_map('intval', $filter['values']);

                    // If all values are selected, not include in filter.
                    if ($currentfield->type == 'checkbox' && in_array(0, $values) && in_array(1, $values)) {
                        break;
                    }

                    $orifnull = '';

                    $alias = 'cfdf' . $customfieldid;
                    $prefix = 'byfield' . $customfieldid;

                    if ($currentfield->type == 'multiselect') {
                        $select .= " AND (";
                        $elements = [];
                        foreach ($values as $key => $value) {
                            $elementkey = $prefix . '_' . $key;
                            // Multiselect values are stored between 0 and 1, so we need to decrease the value by 1 to search.
                            // The select value are the position in the list, starting by 1. Select is the default value.
                            $value = (int)$value - 1;
                            $elements[] = $DB->sql_like($DB->sql_concat("','", $alias . '.value', "','"), ':' . $elementkey);
                            $params[$elementkey] = '%' . $value . '%';
                        }
                        $select .= implode(' OR ', $elements) . ')';
                    } else {
                        [$selectin, $paramsin] = $DB->get_in_or_equal($values, SQL_PARAMS_NAMED, $prefix);

                        // Include "is null" if it is a checkbox and include the 0/not value.
                        if ($currentfield->type == 'checkbox' && in_array(0, $values)) {
                            $prefix = 'bynf' . $customfieldid;
                            [$selectnull, $paramsnull] = $DB->get_in_or_equal([], SQL_PARAMS_NAMED, $prefix, true, null);
                            $orifnull = " OR $alias.id " . $selectnull;
                            $params = array_merge($params, $paramsnull);
                        }

                        $select .= " AND ($alias.intvalue " . $selectin . $orifnull . ')';

                        $params = array_merge($params, $paramsin);
                    }

                    $joincustomfields .= " LEFT JOIN {customfield_data} $alias ON " .
                                        " c.id = $alias.instanceid AND $alias.fieldid = :fieldid$customfieldid";
                    $params['fieldid' . $customfieldid] = $customfieldid;

                    break;
            }
        }
        // End of filters.

        $sql = '';
        $specialfields = '';

        // Create the order by according the sort.
        switch ($sort) {
            case 'startdate':
                $sortby = 'c.startdate ASC';
                break;
            case 'finishdate':
                $sortby = 'endtype ASC, c.enddate ASC, c.startdate DESC';
                $specialfields = ", CASE WHEN c.enddate = 0 THEN 2 ELSE 1 END AS endtype";
                break;
            case 'alphabetically':
                $sortby = 'c.fullname ASC';
                break;
            default:
                $sortby = 'c.sortorder ASC';
        }

        switch ($view) {
            case 'greats':
                $ratemanager = self::get_ratemanager();
                [$ratingfield, $totalfield, $joinrate] = array_values($ratemanager::sql_map());

                $sql = "SELECT DISTINCT c.*, $ratingfield AS rating, $totalfield AS ratings " .
                            " FROM {course} c " .
                            $joinrate . ' ' .
                            $joincustomfields .
                            " WHERE " . $select .
                            " GROUP BY c.id HAVING rating > 3 " .
                            " ORDER BY rating DESC";
                break;
            case 'premium':
                $premiumfield = self::get_premiumfield();

                if ($premiumfield) {
                    $params['fieldid'] = $premiumfield->id;

                    $sql = "SELECT DISTINCT c.* $specialfields " .
                        " FROM {course} c" .
                        " INNER JOIN {customfield_data} cd ON cd.fieldid = :fieldid AND cd.value = '1' AND cd.instanceid = c.id" .
                        $joincustomfields .
                        " WHERE " . $select .
                        " ORDER BY " . $sortby;
                }
                break;
            case 'recents':
                $select .= ' AND c.startdate > :nowtostart';
                $params['nowtostart'] = time();
                // Not break, continue to default.
            default:
                $sql = "SELECT DISTINCT c.* $specialfields " .
                        " FROM {course} c" .
                        $joincustomfields .
                        " WHERE " . $select .
                        " ORDER BY " . $sortby;
        }

        if (!empty($sql)) {
            $courses = $DB->get_records_sql($sql, $params, $initial, $amount);
        }

        return $courses;
    }

    /**
     * Get the icont list for views tabs.
     *
     * @return array The icons list.
     */
    public static function get_views_icons(): array {

        if (!empty(self::$viewsicons)) {
            return self::$viewsicons;
        }

        $customicons = get_config('block_vitrina', 'viewsicons');

        $icons = [
            'default' => 'a/view_icon_active',
            'greats' => 't/emptystar',
            'premium' => 'i/badge',
            'recents' => 'i/calendareventtime',
        ];

        if (!empty($customicons)) {
            $lines = explode("\n", $customicons);

            foreach ($lines as $line) {
                $line = trim($line);
                $options = explode('=', $line);
                if (count($options) == 2) {
                    $icons[trim($options[0])] = trim($options[1]);
                }
            }
        }

        self::$viewsicons = $icons;

        return $icons;
    }

    /**
     * Define if show icons in tabs views.
     *
     * @return bool If show icons.
     */
    public static function show_tabicon(): bool {

        if (self::$showicons !== null) {
            return self::$showicons;
        }

        // Tabs config view.
        $tabview = get_config('block_vitrina', 'tabview');

        self::$showicons = !empty($tabview) ? $tabview !== 'showtext' : false;

        return self::$showicons;
    }

    /**
     * Define if show the text in tabs views.
     *
     * @return bool If show the text.
     */
    public static function show_tabtext(): bool {

        if (self::$showtext !== null) {
            return self::$showtext;
        }

        // Tabs config view.
        $tabview = get_config('block_vitrina', 'tabview');

        self::$showtext = !empty($tabview) ? $tabview !== 'showicon' : false;

        return self::$showtext;
    }

    /**
     * Get the available languages list.
     *
     * @param array $selectedlist The selected languages.
     * @return array The languages list.
     */
    public static function get_languages(array $selectedlist = []): array {
        $langs = get_string_manager()->get_list_of_translations();

        $response = [];

        foreach ($langs as $lang => $name) {
            $selected = in_array($lang, $selectedlist);
            $response[] = [
                'value' => $lang,
                'label' => $name,
                'selected' => $selected,
            ];
        }

        return $response;
    }

    /**
     * Get the available categories list.
     *
     * @param array $selectedlist The selected categories.
     * @param bool $nested If return the categories in a nested way.
     * @return array The categories list.
     */
    public static function get_categories(array $selectedlist = [], bool $nested = false): array {
        global $DB;

        $select = 'visible = 1';
        $params = [];

        $categoriesids = [];
        $categories = get_config('block_vitrina', 'categories');
        $catslist = explode(',', $categories);
        foreach ($catslist as $catid) {
            if (is_numeric($catid)) {
                $categoriesids[] = (int) trim($catid);
            }
        }

        if (count($categoriesids) > 0) {
            [$selectincats, $paramsincats] = $DB->get_in_or_equal($categoriesids, SQL_PARAMS_NAMED, 'categories');
            $params += $paramsincats;
            $select .= ' AND id ' . $selectincats;
        }

        $categories = $DB->get_records_select('course_categories', $select, $params, 'sortorder ASC');

        $response = [];

        foreach ($categories as $category) {
            $selected = in_array($category->id, $selectedlist);
            $node = (object)[
                'value' => $category->id,
                'label' => format_string($category->name, true),
                'selected' => $selected,
                'haschilds' => false,
                'childs' => [],
                'indent' => 0,
            ];

            if ($nested && $category->parent) {
                $parents = explode('/', $category->path);

                // Search the most parent category.
                $tosearch = $response;
                $root = null;
                $indent = 0;
                foreach ($parents as $parentid) {
                    if ($parentid == $category->id) {
                        continue;
                    }

                    foreach ($tosearch as $element) {
                        if ($element->value == $parentid) {
                            $indent++;
                            $root = $element;
                            $root->haschilds = true;
                            $tosearch = $root->childs;
                            break;
                        }
                    }
                }

                $node->indent = $indent;
                // Add the category to the more close parent.
                if ($root) {
                    $root->childs[] = $node;
                } else {
                    $response[] = $node;
                }
            } else {
                $response[] = $node;
            }
        }

        return $response;
    }

    /**
     * Get the available custom fields to filter.
     *
     * @param array $selectedvalues The selected values.
     * @return array The custom fields to filter.
     */
    public static function get_customfieldsfilters(array $selectedvalues = []): array {
        global $DB;

        $filtercontrols = [];

        $customfields = self::get_configuredcustomfields();

        foreach ($customfields as $key => $customfield) {
            $options = [];
            $selectedinfield = [];

            if (!empty($selectedvalues[$customfield->id])) {
                $selectedinfield = $selectedvalues[$customfield->id];
            }

            switch ($customfield->type) {
                case 'checkbox':
                    $options[] = [
                        'value' => 1,
                        'label' => get_string('yes'),
                        'selected' => in_array(1, $selectedinfield),
                    ];
                    $options[] = [
                        'value' => 0,
                        'label' => get_string('no'),
                        'selected' => in_array(0, $selectedinfield),
                    ];
                    break;
                case 'multiselect':
                case 'select':
                    $data = @json_decode($customfield->configdata);

                    $parsedoptions = explode("\n", $data->options);
                    foreach ($parsedoptions as $pos => $value) {
                        $index = $pos + 1;
                        $selected = in_array($index, $selectedinfield);
                        $options[] = [
                            'value' => $index,
                            'label' => format_string($value, true),
                            'selected' => $selected,
                        ];
                    }
                    break;
            }

            if (count($options) > 1) {
                $control = new \stdClass();
                $control->title = format_string($customfield->name, true);
                $control->key = $customfield->id;
                $control->options = $options;
                $filtercontrols[] = $control;
            }
        }

        return $filtercontrols;
    }

    /**
     * Return confugured custom field to filter.
     *
     * @return array The custom fields objects selected to filter.
     */
    public static function get_configuredcustomfields(): array {
        global $DB;

        $filtercustomfields = get_config('block_vitrina', 'filtercustomfields');

        if (!empty($filtercustomfields)) {
            $filtercustomfields = explode(',', $filtercustomfields);
        }

        if (!$filtercustomfields || count($filtercustomfields) == 0) {
            return [];
        }

        // Cast to int.
        $filtercustomfields = array_map('intval', $filtercustomfields);

        [$selectin, $params] = $DB->get_in_or_equal($filtercustomfields, SQL_PARAMS_NAMED, 'ids');
        $select = ' cf.id ' . $selectin;

        $sql = "SELECT cf.* FROM {customfield_field} cf " .
        " INNER JOIN {customfield_category} cc ON cc.id = cf.categoryid AND cc.component = 'core_course'" .
        " WHERE " . $select .
        " ORDER BY cf.sortorder ASC";
        $customfields = $DB->get_records_sql($sql, $params);

        return $customfields;
    }

    /**
     * Get the available static filters.
     *
     * @return array The static filters.
     */
    public static function get_staticfilters(): array {
        return self::STATICFILTERS;
    }

    /**
     * Set the available enrol info in a course.
     *
     * @param object $course The course object.
     */
    public static function load_enrolinfo(object $course) {
        global $USER, $CFG, $DB;

        // Load course context to general purpose.
        $coursecontext = \context_course::instance($course->id, $USER, '', true);

        // Load the course enrol info.
        $enrolinstances = enrol_get_instances($course->id, true);

        $course->enrollable = false;
        $course->enrollsavailables = [];
        $course->fee = [];
        $course->haspaymentgw = false;
        $course->enrolled = !(isguestuser() || !isloggedin() || !is_enrolled($coursecontext));
        $course->canview = has_capability('moodle/course:view', $coursecontext);
        $ispremium = self::is_user_premium();

        $premiumcohort = get_config('block_vitrina', 'premiumcohort');

        foreach ($enrolinstances as $instance) {
            if ($instance->enrolstartdate > time() || ($instance->enrolenddate > 0 && $instance->enrolenddate < time())) {
                // Self enrolment not yet started.
                continue;
            }

            if ($instance->enrol == 'self') {
                if ($instance->customint3 > 0) {
                    // Max enrol limit specified.
                    $count = $DB->count_records('user_enrolments', ['enrolid' => $instance->id]);
                    if ($count >= $instance->customint3) {
                        // Bad luck, no more self enrolments here.
                        continue;
                    }
                }

                // Course premium require a self enrolment.
                if (property_exists($course, 'premium') && ($course->premium || !self::premium_available()) && $ispremium) {
                    // The validation only applies to premium courses if the premiumcohort setting is configured.
                    // If premiumcohort is configured the course requires the specific cohort.
                    if (
                        !$premiumcohort
                        || empty($instance->customint5)
                        || $instance->customint5 == $premiumcohort
                    ) {
                        $course->enrollable = true;
                        $course->enrollsavailables['premium'][] = $instance;
                        continue;
                    }
                }

                if ($instance->customint5) {
                    require_once($CFG->dirroot . '/cohort/lib.php');
                    if (!cohort_is_member($instance->customint5, $USER->id)) {
                        // The user cannot enroll because they are not in the cohort.
                        continue;
                    }
                }

                $course->enrollsavailables['self'][] = $instance;
                $course->enrollable = true;
            } else if ($instance->enrol == 'fee' && enrol_is_enabled('fee')) {
                $cost = (float) $instance->cost;
                if ($cost <= 0) {
                    $cost = (float) get_config('enrol_fee', 'cost');
                }

                if ($cost > 0) {
                    $datafee = new \stdClass();
                    $datafee->cost = $cost;
                    $datafee->currency = $instance->currency;
                    $datafee->formatedcost = self::format_cost($cost, $instance->currency);
                    $datafee->itemid = $instance->id;
                    $datafee->label = !empty($instance->name) ? $instance->name : get_string('sendpaymentbutton', 'enrol_fee');
                    $datafee->description = get_string(
                        'purchasedescription',
                        'enrol_fee',
                        format_string($course->fullname, true, ['context' => $coursecontext])
                    );
                    $datafee->originalcoursename = $course->fullname;

                    $course->fee[] = $datafee;
                    $course->enrollable = true;
                    $course->enrollsavailables['fee'][] = $instance;
                    $course->haspaymentgw = true;
                }
            } else if ($instance->enrol == 'guest' && enrol_is_enabled('guest')) {
                $course->enrollable = true;
                $course->enrollsavailables['guest'][] = $instance;
            } else if ($instance->enrol == 'customgr' && enrol_is_enabled('customgr')) {
                $enrolplugin = enrol_get_plugin('customgr');

                if ($enrolplugin->is_self_enrol_available($instance)) {
                    $course->enrollable = true;
                    $course->enrollsavailables['customgr'][] = $instance;
                }
            } else if ($instance->enrol == 'token' && enrol_is_enabled('token')) {
                $enrolplugin = enrol_get_plugin('token');

                if ($enrolplugin->is_self_enrol_available($instance)) {
                    $course->enrollable = true;
                    $course->enrollsavailables['token'][] = $instance;
                }
            }
        }
    }

    /**
     * Returns human-readable amount with correct number of fractional digits and currency indicator, can also apply surcharge
     *
     * @param float $amount amount in the currency units
     * @param string $currency The currency
     * @param float $surcharge surcharge in percents
     * @return string
     */
    public static function format_cost(float $amount, string $currency, float $surcharge = 0): string {
        $amount = $amount * (100 + $surcharge) / 100;

        $decimalpoints = (int)get_config('block_vitrina', 'decimalpoints');

        $locale = get_string('localecldr', 'langconfig');
        $fmt = \NumberFormatter::create($locale, \NumberFormatter::CURRENCY);
        $fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimalpoints);
        $localisedcost = numfmt_format_currency($fmt, $amount, $currency);

        if (strpos($localisedcost, '$') === false) {
            $localisedcost = '$' . $localisedcost;
        }

        return $localisedcost;
    }

    /**
     * Get the usable course rate manager.
     */
    public static function get_ratemanager(): string {

        $rateplugin = get_config('block_vitrina', 'ratingmanager');

        switch ($rateplugin) {
            case 'tool_courserating':
                return '\block_vitrina\local\rating\tool_courserating';
            break;
            default:
                return '\block_vitrina\local\rating\base';
        }
    }

    /**
     * Get the usable course comments manager.
     */
    public static function get_commentsmanager(): string {

        $commentsplugin = get_config('block_vitrina', 'commentsmanager');

        switch ($commentsplugin) {
            case 'tool_courserating':
                return '\block_vitrina\local\comments\tool_courserating';
            break;
            default:
                return '\block_vitrina\local\comments\base';
        }
    }
}
