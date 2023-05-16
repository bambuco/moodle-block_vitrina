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

$instanceid = optional_param('id', 0, PARAM_INT);
$view = optional_param('view', 'default', PARAM_TEXT);

require_login(null, true);

$syscontext = context_system::instance();

$PAGE->set_context($syscontext);
$PAGE->set_url('/blocks/vitrina/index.php');
$PAGE->set_pagelayout('incourse');
$PAGE->set_heading(get_string('catalog', 'block_vitrina'));
$PAGE->set_title(get_string('catalog', 'block_vitrina'));

$uniqueid = \block_vitrina\controller::get_uniqueid();
\block_vitrina\controller::include_templatecss();

$bypage = get_config('block_vitrina', 'amount');
if (empty($bypage)) {
    $bypage = 20;
}

$PAGE->requires->js_call_amd('block_vitrina/main', 'catalog', [$uniqueid, $view, $instanceid, $bypage]);
$PAGE->requires->js_call_amd('block_vitrina/main', 'filters', [$uniqueid]);

echo $OUTPUT->header();

$summary = get_config('block_vitrina', 'summary');

echo format_text($summary, FORMAT_HTML, ['trusted' => true, 'noclean' => true]);

$renderable = new \block_vitrina\output\catalog($uniqueid, $view);
$renderer = $PAGE->get_renderer('block_vitrina');

echo $renderer->render($renderable);

echo $OUTPUT->footer();
