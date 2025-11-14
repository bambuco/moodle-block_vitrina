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
 * Class containing data for the courses catalog.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog implements renderable, templatable {
    /**
     * @var string The uniqueid of the block instance.
     */
    private $uniqueid;

    /**
     * @var string The view type.
     */
    private $view;

    /**
     * Constructor.
     *
     * @param string $uniqueid The uniqueid of the block instance.
     * @param string $view The view type.
     */
    public function __construct($uniqueid, $view = 'default') {

        $this->uniqueid = $uniqueid;
        $this->view = $view;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array Context variables for the template
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;

        $availableviews = \block_vitrina\local\controller::get_courses_views();

        $icons = \block_vitrina\local\controller::get_views_icons();

        $showtabs = [];
        foreach ($availableviews as $k => $view) {
            $one = new \stdClass();
            $one->title = get_string('tabtitle_' . $view, 'block_vitrina');
            $one->key = $view;
            $one->icon = $output->image_icon($icons[$view], $one->title);
            $one->state = $view == $this->view ? 'active' : '';
            $showtabs[] = $one;
        }

        // Filter controls.
        $filtercontrols = [];

        $staticfilters = get_config('block_vitrina', 'staticfilters');
        $staticfilters = explode(',', $staticfilters);

        // Filter by category.
        $catfilterview = null;
        if (in_array('categories', $staticfilters)) {
            $catfilterview = get_config('block_vitrina', 'catfilterview');

            $nested = $catfilterview == 'tree';

            $categoriesoptions = \block_vitrina\local\controller::get_categories([], $nested);

            if (count($categoriesoptions) > 1) {
                $control = new \stdClass();
                $control->title = get_string('category');
                $control->key = 'categories';
                $control->options = $categoriesoptions;
                $filtercontrols[] = $control;
            }
        }

        // Filter by language.
        if (in_array('langs', $staticfilters)) {
            $options = \block_vitrina\local\controller::get_languages();

            if (count($options) > 1) {
                $control = new \stdClass();
                $control->title = get_string('language');
                $control->key = 'langs';
                $control->options = $options;
                $filtercontrols[] = $control;
            }
        }

        // Filter by custom fields.

        // Add to filtercontrols the array returned by the method get_customfieldsfilters.
        $filtercontrols = array_merge($filtercontrols, \block_vitrina\local\controller::get_customfieldsfilters());

        $filterproperties = new \stdClass();

        if (in_array('fulltext', $staticfilters)) {
            $filterproperties->fulltext = true;
        }
        // End of filter controls.

        $defaultvariables = [
            'uniqueid' => $this->uniqueid,
            'baseurl' => $CFG->wwwroot,
            'hastabs' => count($showtabs) > 1,
            'tabs' => $showtabs,
            'showicon' => \block_vitrina\local\controller::show_tabicon(),
            'showtext' => \block_vitrina\local\controller::show_tabtext(),
            'filtercontrols' => $filtercontrols,
            'filterproperties' => $filterproperties,
            'catfilterview' => $catfilterview,
            'opendetailstarget' => get_config('block_vitrina', 'opendetailstarget'),
        ];

        return $defaultvariables;
    }
}
