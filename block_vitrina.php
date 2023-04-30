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
 * Form for editing vitrina block instances.
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class containing block base implementation for Vitrina.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_vitrina extends block_base {

    /**
     * Initialice the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_vitrina');
    }

    /**
     * Subclasses should override this and return true if the
     * subclass block has a settings.php file.
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Which page types this block may appear on.
     *
     * The information returned here is processed by the
     * {@see blocks_name_allowed_in_format()} function. Look there if you need
     * to know exactly how this works.
     *
     * Default case: everything except mod and tag.
     *
     * @return array page-type prefix => true/false.
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * This function is called on your subclass right after an instance is loaded
     * Use this function to act on instance data just after it's loaded and before anything else is done
     * For instance: if your block will have different title's depending on location (site, course, blog, etc)
     */
    public function specialization() {
        if (isset($this->config->title)) {
            $this->title = $this->title = format_string($this->config->title, true, ['context' => $this->context]);
        } else {
            $this->title = get_string('newblocktitle', 'block_vitrina');
        }
    }

    /**
     * Are you going to allow multiple instances of each block?
     * If yes, then it is assumed that the block WILL USE per-instance configuration
     * @return boolean
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Implemented to return the content object.
     *
     * @return stdObject
     */
    public function get_content() {
        global $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        $amount = get_config('block_vitrina', 'singleamount');

        if (!$amount || !is_numeric($amount)) {
            $amount = 4;
        }

        // Take config from instance if it isn't empty.
        if (!empty($this->config->singleamount)) {
            $amount = $this->config->singleamount;
        }

        $params = [];
        $select = 'visible = 1 AND id != ' . SITEID;

        $daystoupcoming = get_config('block_vitrina', 'daystoupcoming');
        if (isset($daystoupcoming) && is_numeric($daystoupcoming)) {
            $select .= ' AND startdate < :startdate ';
            $params['startdate'] = time() + ($daystoupcoming * 24 * 60 * 60);
        }

        // Categories filter.
        $categories = get_config('block_vitrina', 'categories');

        if (!empty($this->config->categories)) {
            $categories = $this->config->categories;
        } else {
            $categoryid = [];
            $catslist = explode(',', $categories);
            foreach ($catslist as $catid) {
                if (is_numeric($catid)) {
                    $categoryid[] = (int)trim($catid);
                }
                $categories = $categoryid;
            }
        }

        foreach ($categories as $key => $id) {
            $categories[$key] = intval($id);
        }

        if (count($categories) > 0) {
            $select .= ' AND category IN (' . implode(',', $categories) . ')';
        }

        // End Categories filter.
        $courses = $DB->get_records_select('course', $select, $params, 'fullname ASC', '*', 0, $amount);

        $tabs = array();

        if (isset($this->config) && is_object($this->config)) {
            // Show all tab is printed by default if not exists the configuration parameter.
            if (property_exists($this->config, 'default') && $this->config->default) {
                $tabs[] = 'default';
            }

            if (property_exists($this->config, 'recents') && $this->config->recents) {
                $tabs[] = 'recents';
            }

            if (property_exists($this->config, 'greats') && $this->config->greats) {
                $tabs[] = 'greats';
            }

            if (property_exists($this->config, 'premium') && $this->config->premium) {
                $tabs[] = 'premium';
            }

        } else {
            $tabs[] = 'default';
            $tabs[] = 'recents';
            $tabs[] = 'greats';
            $tabs[] = 'premium';
        }

        // Get recents courses.
        $recentscourses = $DB->get_records_select('course', $select, $params, 'startdate DESC', '*', 0, $amount);

        // Get outstanding courses.
        $selectgreats = str_replace(' AND id ', ' AND c.id ', $select);
        $sql = "SELECT c.*, AVG(r.rating) AS rating, COUNT(1) AS ratings
                    FROM {course} c
                    INNER JOIN {block_rate_course} r ON r.course = c.id
                    WHERE " . $selectgreats .
                    " GROUP BY c.id HAVING rating > 3
                    ORDER BY rating DESC";
        $greatcourses = $DB->get_records_sql($sql, $params, 0, $amount);

        // Get premium courses.
        $params['fieldid'] = \block_vitrina\controller::get_payfieldid();

        $selectpremium = str_replace(' AND id ', ' AND c.id ', $select);
        $sql = "SELECT c.*
                    FROM {course} c
                    INNER JOIN {customfield_data} cd ON cd.fieldid = :fieldid AND cd.value != '' AND cd.instanceid = c.id
                    WHERE " . $selectpremium .
                    " ORDER BY c.fullname ASC";
        $premiumcourses = $DB->get_records_sql($sql, $params, 0, $amount);

        $html = '';

        if ($courses && is_array($courses)) {
            // Load templates to display courses.
            $renderable = new \block_vitrina\output\main($tabs, $courses, $recentscourses, $greatcourses, $premiumcourses);
            $renderer = $this->page->get_renderer('block_vitrina');
            $html .= $renderer->render($renderable);
        }

        $this->content->text = $html;

        \block_vitrina\controller::include_templatecss();
        $this->page->requires->js_call_amd('block_vitrina/main', 'init');

        return $this->content;
    }

    /**
     * Overridden by the block to prevent the block from being dockable.
     *
     * @return bool
     *
     * Return false as per MDL-64506
     */
    public function instance_can_be_docked() {
        return false;
    }

}
