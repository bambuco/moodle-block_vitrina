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
 * Block renderer
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_vitrina\output;

use plugin_renderer_base;
use renderable;

/**
 * Vitrina block renderer
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Return the template content for the block.
     *
     * @param main $main The main renderable
     * @return string HTML string
     */
    public function render_main(main $main): string {
        global $CFG;

        $template = get_config('block_vitrina', 'templatetype');
        $path = $CFG->dirroot . '/blocks/vitrina/templates/' . $template . '/main.mustache';

        if ($template != 'default' && file_exists($path)) {
            $templatefile = 'block_vitrina/' . $template . '/main';
        } else {
            $templatefile = 'block_vitrina/main';
        }

        return $this->render_from_template($templatefile, $main->export_for_template($this));
    }

    /**
     * Return the template content for the block.
     *
     * @param catalog $catalog The catalog renderable
     * @return string HTML string
     */
    public function render_catalog(catalog $catalog): string {
        global $CFG;

        $template = get_config('block_vitrina', 'templatetype');
        $path = $CFG->dirroot . '/blocks/vitrina/templates/' . $template . '/catalog.mustache';

        if ($template != 'default' && file_exists($path)) {
            $templatefile = 'block_vitrina/' . $template . '/catalog';
        } else {
            $templatefile = 'block_vitrina/catalog';
        }

        return $this->render_from_template($templatefile, $catalog->export_for_template($this));
    }

    /**
     * Return the template content for the block.
     *
     * @param detail $detail The detail renderable
     * @return string HTML string
     */
    public function render_detail(detail $detail): string {
        global $CFG;

        $template = get_config('block_vitrina', 'templatetype');
        $path = $CFG->dirroot . '/blocks/vitrina/templates/' . $template . '/detail.mustache';

        if ($template != 'default' && file_exists($path)) {
            $templatefile = 'block_vitrina/' . $template . '/detail';
        } else {
            $templatefile = 'block_vitrina/detail';
        }

        return $this->render_from_template($templatefile, $detail->export_for_template($this));
    }

    /**
     * Return the template for courses in the block.
     *
     * @param object $course The course information
     * @return string HTML string
     */
    public function render_course(object $course): string {
        global $CFG;

        static $shopmanager = null;

        $template = get_config('block_vitrina', 'templatetype');
        $path = $CFG->dirroot . '/blocks/vitrina/templates/' . $template . '/course.mustache';

        if ($template != 'default' && file_exists($path)) {
            $templatefile = 'block_vitrina/' . $template . '/course';
        } else {
            $templatefile = 'block_vitrina/course';
        }

        if ($shopmanager === null) {
            $shoppluginname = get_config('block_vitrina', 'shopmanager');
            if (!empty($shoppluginname)) {
                $shopmanager = 'block_vitrina\local\shop\\' . $shoppluginname;
            }
        }

        if ($shopmanager && !$course->enrolled && !$course->canview) {
            $course->hascart = true;
            $course->shopmanager = $shopmanager::render_from_template();

            foreach ($course->fee as $fee) {
                $fee->reference = $shopmanager::get_product_reference('enrol_fee', $fee->itemid);
            }
        }
        $course->opendetailstarget = get_config('block_vitrina', 'opendetailstarget');

        return $this->render_from_template($templatefile, $course);
    }
}
