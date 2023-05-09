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
 * Class containing renderers for the block.
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
 * Class containing data for the block.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {

    /**
     * @var array List of tabs to print.
     */
    private $tabs;

    /**
     * @var array Courses views.
     */
    private $views = null;


    /**
     * Constructor.
     *
     * @param array $tabs The tabs configuration.
     * @param array $views The courses views.
     */
    public function __construct($tabs, $views = []) {
        global $CFG, $OUTPUT;

        $this->tabs = $tabs;
        $this->views = $views;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array Context variables for the template
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;

        $icons = [
            'default' => 'th',
            'recents' => 'calendar-check-o',
            'greats' => 'thumbs-up',
            'premium' => 'star'
        ];

        $showtabs = [];
        foreach ($this->tabs as $k => $tab) {
            $one = new \stdClass();
            $one->title = get_string('tabtitle_' . $tab, 'block_vitrina');
            $one->key = $tab;
            $one->icon = $icons[$tab];
            $one->state = $k == 0 ? 'active' : '';
            $showtabs[] = $one;
        }

        // Tabs config view.
        $tabview = get_config('block_vitrina', 'tabview');
        $showicon = false;
        $showtext = false;

        if (!empty($tabview)) {

            if ($tabview == 'showicon') {
                $showicon = true;
            } else if ($tabview == 'showtext') {
                $showtext = true;
            } else {
                $showicon = true;
                $showtext = true;
            }
        }

        $tabbames = ['default', 'recents', 'greats', 'premium'];
        $activetab = false;
        $getviews = [];
        $firsttab = !empty($this->tabs) ? $this->tabs[0] : '';
        $sortbydefault = get_config('block_vitrina', 'sortbydefault');
        $sortedby = false;

        if (!empty($sortbydefault)) {

            if ($sortbydefault == 'default') {
                $sortedby = get_string('sortbystartdate', 'block_vitrina');
            } else {
                $sortedby = get_string($sortbydefault, 'block_vitrina');
            }
        }

        foreach ($this->views as $view => $courses) {
                $status = ($view === $firsttab) ? 'active' : '';
                $sortedby = ($view === 'default') ? $sortedby : false;
                $getviews[] = [
                    'view' => $view,
                    'status' => $status,
                    'sortedby' => $sortedby,
                    'coursesview' => $courses,
                ];

            foreach ($courses as $course) {
                \block_vitrina\controller::course_preprocess($course);
            }
        }

        $uniqueid = \block_vitrina\controller::get_uniqueid();
        $sortbydefaultconfig = get_config('block_vitrina', 'sortbydefault');

        $defaultvariables = [
            'getviews' => array_values($getviews),
            'baseurl' => $CFG->wwwroot,
            'hastabs' => count($this->tabs) > 1,
            'tabs' => $showtabs,
            'sortedby' => $sortedby,
            'uniqueid' => $uniqueid,
            'showicon' => $showicon,
            'showtext' => $showtext,
            'defaultsort' => $sortbydefaultconfig == 'default',
            'sortbyfinishdate' => $sortbydefaultconfig == 'sortbyfinishdate',
            'sortalphabetically' => $sortbydefaultconfig == 'sortalphabetically'
        ];

        return $defaultvariables;
    }
}
