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
 * Form for editing block instances.
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Form for editing block instances.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_vitrina_edit_form extends block_edit_form {

    /**
     * Defines forms elements.
     *
     * @param \moodleform $mform The form to add elements to.
     *
     * @return void
     */
    protected function specific_definition($mform) {
        global $CFG, $DB, $PAGE;

        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('customtitle', 'block_vitrina'));
        $mform->setType('config_title', PARAM_TEXT);

        // Amount of courses shown at instance.
        $mform->addElement('text', 'config_singleamount', get_string('singleamountcourses', 'block_vitrina'), ['size' => 2]);
        $mform->setType('config_singleamount', PARAM_INT);
        $mform->setDefault('config_singleamount', 4);
        $mform->addHelpButton('config_singleamount', 'singleamountcourses', 'block_vitrina');

        // Tabs

        $mform->addElement('checkbox', 'config_tabdefault', get_string('defaultsort', 'block_vitrina'));

        // Show premium tab config only if premium is available.
        if (\block_vitrina\controller::premium_available()) {
            $mform->addElement('checkbox', 'config_tabpremium', get_string('premium', 'block_vitrina'));
        }

        $mform->addElement('checkbox', 'config_tabrecents', get_string('recents', 'block_vitrina'));

        // Show greats tab config only if rate_course block exists.
        $bmanager = new \block_manager($PAGE);
        if ($bmanager->is_known_block_type('rate_course')) {
            $mform->addElement('checkbox', 'config_tabgreats', get_string('greats', 'block_vitrina'));
        }

        // Select courses categories.
        $displaylist = \core_course_category::make_categories_list('moodle/course:create');

        $options = [
            'multiple' => true,
            'noselectionstring' => get_string('selectcategories', 'block_vitrina')
        ];

        $mform->addElement('autocomplete', 'config_categories', get_string('coursecategory', 'block_vitrina'), $displaylist, $options);

    }
}
