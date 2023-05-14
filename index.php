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
 * List of available courses.
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('classes/output/catalog.php');

$query = optional_param('q', '', PARAM_TEXT);
$spage = optional_param('spage', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_TEXT);

require_login(null, true);

$syscontext = context_system::instance();

$PAGE->set_context($syscontext);
$PAGE->set_url('/blocks/vitrina/index.php');
$PAGE->set_pagelayout('incourse');
$PAGE->set_heading(get_string('catalog', 'block_vitrina'));
$PAGE->set_title(get_string('catalog', 'block_vitrina'));

\block_vitrina\controller::include_templatecss();

echo $OUTPUT->header();

$summary = get_config('block_vitrina', 'summary');

echo format_text($summary, FORMAT_HTML, ['trusted' => true, 'noclean' => true]);

$amount = get_config('block_vitrina', 'amount');

if (!$amount || !is_numeric($amount)) {
    $amount = 20;
}

$availablesorting = ['default', 'recents'];

$bmanager = new \block_manager($PAGE);
if ($bmanager->is_known_block_type('rate_course')) {
    $availablesorting[] = 'greats';
}

if (\block_vitrina\controller::premium_available()) {
    $availablesorting[] = 'premium';
}

if (empty($sort) || !in_array($sort, $availablesorting)) {
    $sort = 'default';
}

$select = 'visible = 1 AND id != ' . SITEID;
$params = [];

// Categories filter.
$categories = get_config('block_vitrina', 'categories');

$categoriesids = [];
$catslist = explode(',', $categories);
foreach ($catslist as $catid) {
    if (is_numeric($catid)) {
        $categoriesids[] = (int)trim($catid);
    }
}

if (count($categoriesids) > 0) {
    $select .= ' AND category IN (' . implode(',', $categoriesids) . ')';
}
// End Categories filter.

if (!empty($query)) {
    $q = trim($query);
    $q = str_replace(' ', '%', $q);
    $q = '%' . $q . '%';
    $select .= " AND (fullname LIKE :query1 OR summary LIKE :query2)";
    $params['query1'] = $q;
    $params['query2'] = $q;
}

if ($sort == 'greats') {
    $selectgreats = str_replace(' AND id ', ' AND c.id ', $select);
    $sql = "SELECT c.*, AVG(r.rating) AS rating, COUNT(1) AS ratings
                FROM {course} c
                INNER JOIN {block_rate_course} r ON r.course = c.id
                WHERE " . $selectgreats .
                " GROUP BY c.id HAVING rating > 3
                ORDER BY rating DESC";
    $courses = $DB->get_records_sql($sql, $params, $spage * $amount, $amount);

    $sqlcount = "SELECT COUNT(DISTINCT c.id)
                FROM {course} c
                INNER JOIN {block_rate_course} r ON r.course = c.id
                WHERE " . $selectgreats;

    $coursescount = $DB->count_records_sql($sqlcount, $params);

} else if ($sort == 'premium') {

    $payfield = \block_vitrina\controller::get_payfield();

    if ($payfield) {

        $params['fieldid'] = $payfield->id;

        $selectpremium = str_replace(' AND id ', ' AND c.id ', $select);
        $sql = "SELECT c.*
                    FROM {course} c
                    INNER JOIN {customfield_data} cd ON cd.fieldid = :fieldid AND cd.value != '' AND cd.instanceid = c.id
                    WHERE " . $selectpremium .
                    " ORDER BY c.fullname ASC";
        $courses = $DB->get_records_sql($sql, $params, $spage * $amount, $amount);

        $sqlcount = "SELECT COUNT(1)
                        FROM {course} c
                        INNER JOIN {customfield_data} cd ON fieldid = :fieldid AND cd.value != '' AND cd.instanceid = c.id
                        WHERE " . $selectpremium;

        $coursescount = $DB->count_records_sql($sqlcount, $params);
    } else {
        $courses = [];
        $coursescount = 0;
    }

} else {
    if ($sort == 'recents') {
        $courses = $DB->get_records_select('course', $select, $params, 'startdate DESC', '*', $spage * $amount, $amount);
    } else {
        $courses = $DB->get_records_select('course', $select, $params, 'fullname ASC', '*', $spage * $amount, $amount);
    }
    $coursescount = $DB->count_records_select('course', $select, $params);
}

$pagingbar = new paging_bar($coursescount, $spage, $amount, "/blocks/vitrina/index.php?q={$query}&amp;sort={$sort}&amp;");
$pagingbar->pagevar = 'spage';

$renderable = new \block_vitrina\output\catalog($courses, $query, $sort);
$renderer = $PAGE->get_renderer('block_vitrina');

echo $renderer->render($renderable);

echo $OUTPUT->render($pagingbar);

echo $OUTPUT->footer();
