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
        global $DB, $CFG;

        require_once($CFG->libdir . '/filelib.php');

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
                    $categoryid[] = (int) trim($catid);
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

        // Load tabs and views.
        $tabnames = ['default', 'recents', 'greats', 'premium'];
        $tabs = [];
        $views = [];

        if (isset($this->config) && is_object($this->config)) {
            foreach ($tabnames as $tabname) {
                if (property_exists($this->config, $tabname) && $this->config->$tabname) {
                    $tabs[] = $tabname;
                    $views[$tabname] = [];
                }
            }
        } else {
            $tabs[] = 'default';
            $views['default'] = [];
        }

        // Sort and get default view.
        $sortbydefault = get_config('block_vitrina', 'sortbydefault');

        if ($sortbydefault == 'sortalphabetically') {
            $newdefaultview = $DB->get_records_select('course', $select, $params, 'fullname ASC', '*', 0, $amount);
            $views['default'] = array_merge($views['default'], $newdefaultview);

        } else if ($sortbydefault == 'sortbyfinishdate') {
            $sql = "SELECT *,
                        CASE WHEN enddate IS NULL THEN 3
                        WHEN enddate < UNIX_TIMESTAMP() THEN 2
                        ELSE 1 END AS date_classification
                        FROM {course}
                        WHERE $select
                        ORDER BY date_classification ASC,
                        enddate ASC,
                        startdate DESC";
            $newdefaultview = $DB->get_records_sql($sql, $params, 0, $amount);
            $views['default'] = array_merge($views['default'], $newdefaultview);
        } else {
            $newdefaultview = $DB->get_records_select('course', $select, $params, 'startdate ASC', '*', 0, $amount);
            $views['default'] = array_merge($views['default'], $newdefaultview);
        }

        // Get next courses.
        if (isset($views['recents']) &&
            $this->config->recents &&
            isset($daystoupcoming) &&
            is_numeric($daystoupcoming)) {

            $paramsnextcourses['currenttime'] = time();
            $paramsnextcourses['startdate'] = $params['startdate'];
            $selectnextcourses = 'startdate > :currenttime AND ' . $select;
            $newrecentview = $DB->get_records_select('course',
                                                      $selectnextcourses,
                                                      $paramsnextcourses,
                                                      'startdate ASC',
                                                      '*',
                                                      0,
                                                      $amount);
            $views['recents'] = array_merge($views['recents'], $newrecentview);
        }

        // Get outstanding courses.
        $dbman = $DB->get_manager();
        $bmanager = new \block_manager($this->page);

        if (isset($views['greats']) &&
            $this->config->greats &&
            $dbman->table_exists('block_rate_course') &&
            $bmanager->is_known_block_type('rate_course')) {

            $selectgreats = str_replace(' AND id ', ' AND c.id ', $select);
            $sql = "SELECT c.*, AVG(r.rating) AS rating, COUNT(1) AS ratings
                        FROM {course} c
                        INNER JOIN {block_rate_course} r ON r.course = c.id
                        WHERE " . $selectgreats .
                        " GROUP BY c.id HAVING rating > 3
                        ORDER BY rating DESC";
            $newgreatsview = $DB->get_records_sql($sql, $params, 0, $amount);
            $views['greats'] = array_merge($views['greats'], $newgreatsview);
        }

        // Get premium courses.
        if (isset($views['premium']) &&
            $this->config->premium &&
            \block_vitrina\controller::premium_available()) {

            $params['fieldid'] = \block_vitrina\controller::get_payfieldid();
            $selectpremium = str_replace(' AND id ', ' AND c.id ', $select);
            $sql = "SELECT c.*
                    FROM {course} c
                    INNER JOIN {customfield_data} cd ON
                    cd.fieldid = :fieldid AND
                    cd.value != '' AND
                    cd.instanceid = c.id
                    WHERE " . $selectpremium .
                    " ORDER BY c.fullname ASC";
            $newpremiumview = $DB->get_records_sql($sql, $params, 0, $amount);
            $views['premium'] = array_merge($views['premium'], $newpremiumview);
        }

        $html = '';
        $filteropt = new stdClass;
        $filteropt->overflowdiv = true;

        // If the content is trusted, do not clean it.
        if ($this->content_is_trusted()) {
            $filteropt->noclean = true;
        }

        if (isset($this->config->htmlheader)) {
            // Rewrite url.
            $this->config->htmlheader = file_rewrite_pluginfile_urls($this->config->htmlheader,
                                                                     'pluginfile.php',
                                                                     $this->context->id,
                                                                     'block_vitrina',
                                                                     'content_header',
                                                                     null);
            // Default to FORMAT_HTML.
            $htmlheaderformat = FORMAT_HTML;
            // Check to see if the format has been properly set on the config.
            if (isset($this->config->htmlheaderformat)) {
                $htmlheaderformat = $this->config->htmlheaderformat;
            }
            $html .= format_text($this->config->htmlheader, $htmlheaderformat, $filteropt);
        }

        if (isset($this->config->htmlfooter)) {
            // Rewrite url.
            $this->config->htmlfooter = file_rewrite_pluginfile_urls($this->config->htmlfooter,
                                                                     'pluginfile.php',
                                                                     $this->context->id,
                                                                     'block_vitrina',
                                                                     'content_footer',
                                                                     null);
            // Default to FORMAT_HTML.
            $htmlfooterformat = FORMAT_HTML;
            // Check to see if the format has been properly set on the config.
            if (isset($this->config->htmlfooterformat)) {
                $htmlfooterformat = $this->config->htmlfooterformat;
            }
            $this->content->footer = format_text($this->config->htmlfooter, $htmlfooterformat, $filteropt);
        }
        // Memory footprint.
        unset($filteropt);

        if ($views && is_array($views)) {
            // Load templates to display courses.
            $renderable = new \block_vitrina\output\main($tabs, $views);
            $renderer = $this->page->get_renderer('block_vitrina');
            $html .= $renderer->render($renderable);
        }

        $this->content->text = $html;
        \block_vitrina\controller::include_templatecss();
        $this->page->requires->js_call_amd('block_vitrina/main', 'init');
        return $this->content;
    }

    /**
     * Serialize and store config data.
     *
     * @param object $data
     * @param boolean $nolongerused
     * @return void
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $DB;

        $config = clone($data);
        // Move embedded files into a proper filearea and adjust HTML links to match.
        $config->htmlheader = file_save_draft_area_files($data->htmlheader['itemid'],
                              $this->context->id,
                              'block_vitrina',
                              'content_header',
                              0,
                              ['subdirs' => true],
                              $data->htmlheader['text']);
        $config->htmlfooter = file_save_draft_area_files($data->htmlfooter['itemid'],
                              $this->context->id,
                              'block_vitrina',
                              'content_footer',
                              0,
                              ['subdirs' => true],
                              $data->htmlfooter['text']);
        $config->htmlheaderformat = $data->htmlheader['format'];
        $config->htmlfooterformat = $data->htmlfooter['format'];
        parent::instance_config_save($config, $nolongerused);
    }

    /**
     * Delete area files when the block instance is deleted.
     *
     * @return bool
     */
    public function instance_delete() {
        global $DB;
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'block_vitrina');
        return true;
    }

    /**
     * Check if the block content is trusted and avoid JS inyection.
     *
     * @return bool
     */
    public function content_is_trusted() {
        global $SCRIPT;

        $context = context::instance_by_id($this->instance->parentcontextid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }

        // Find out if this block is on the profile page.
        if ($context->contextlevel == CONTEXT_USER) {
            if ($SCRIPT === '/my/index.php') {
                return true;
            } else {
                // No JS on public personal pages, it would be a big security issue.
                return false;
            }
        }
        return true;
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

