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
        global $CFG, $DB;

        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('customtitle', 'block_vitrina'));
        $mform->setType('config_title', PARAM_TEXT);

        // Amount of courses shown at instance.
        $mform->addElement('text', 'config_singleamount', get_string('singleamountcourses', 'block_vitrina'), ['size' => 2]);
        $mform->setType('config_singleamount', PARAM_INT);
        $mform->setDefault('config_singleamount', 0);
        $mform->addHelpButton('config_singleamount', 'singleamountcourses', 'block_vitrina');

        // Tabs.
        $options = [
            '0' => get_string('no'),
            '1' => get_string('yes'),
        ];
        $mform->addElement('select', 'config_default', get_string('defaultsort', 'block_vitrina'), $options);
        $mform->setDefault('config_default', 1);

        $mform->addElement('select', 'config_recents', get_string('recents', 'block_vitrina'), $options);

        // Show greats tab config only if rating feature exists.
        $ratemanager = \block_vitrina\local\controller::get_ratemanager();
        $ratingavailable = $ratemanager::rating_available();

        if ($ratingavailable) {
            $mform->addElement('select', 'config_greats', get_string('greats', 'block_vitrina'), $options);
        }

        // Show premium tab config only if premium is available.
        if (\block_vitrina\local\controller::premium_available()) {
            $mform->addElement('select', 'config_premium', get_string('premium', 'block_vitrina'), $options);
        }

        // Select courses categories.
        $displaylist = \core_course_category::make_categories_list('moodle/course:create');

        $options = [
            'multiple' => true,
            'noselectionstring' => get_string('selectcategories', 'block_vitrina'),
        ];

        $mform->addElement(
            'autocomplete',
            'config_categories',
            get_string('coursecategory', 'block_vitrina'),
            $displaylist,
            $options
        );

        $editoroptions = ['maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true, 'context' => $this->block->context];

        // Header HTML editor.
        $mform->addElement('editor', 'config_htmlheader', get_string('htmlheader', 'block_vitrina'), null, $editoroptions);
        $mform->setType('config_htmlheader', PARAM_RAW); // XSS is prevented when printing the block contents and serving files.

        // Footer HTML editor.
        $mform->addElement('editor', 'config_htmlfooter', get_string('htmlfooter', 'block_vitrina'), null, $editoroptions);
        $mform->setType('config_htmlfooter', PARAM_RAW); // XSS is prevented when printing the block contents and serving files.
    }

    /**
     * Set the data for header and footer html draft.
     *
     * @param array $defaults
     * @return void
     */
    public function set_data($defaults) {

        // Set data for header.
        if (!empty($this->block->config) && !empty($this->block->config->htmlheader)) {
            $htmlheader = $this->block->config->htmlheader;
            $draftidheader = file_get_submitted_draft_itemid('config_htmlheader');
            if (empty($htmlheader)) {
                $currenthtmlheader = '';
            } else {
                $currenthtmlheader = $htmlheader;
            }
            $defaults->config_htmlheader['text'] = file_prepare_draft_area(
                $draftidheader,
                $this->block->context->id,
                'block_vitrina',
                'content_header',
                0,
                ['subdirs' => true],
                $currenthtmlheader
            );
            $defaults->config_htmlheader['itemid'] = $draftidheader;
            $defaults->config_htmlheader['format'] = $this->block->config->htmlheaderformat ?? FORMAT_MOODLE;
        } else {
            $htmlheader = '';
        }

        // Set data for footer.
        if (!empty($this->block->config) && !empty($this->block->config->htmlfooter)) {
            $htmlfooter = $this->block->config->htmlfooter;
            $draftidfooter = file_get_submitted_draft_itemid('config_htmlfooter');
            if (empty($htmlfooter)) {
                $currenthtmlfooter = '';
            } else {
                $currenthtmlfooter = $htmlfooter;
            }
            $defaults->config_htmlfooter['text'] = file_prepare_draft_area(
                $draftidfooter,
                $this->block->context->id,
                'block_vitrina',
                'content_footer',
                0,
                ['subdirs' => true],
                $currenthtmlfooter
            );
            $defaults->config_htmlfooter['itemid'] = $draftidfooter;
            $defaults->config_htmlfooter['format'] = $this->block->config->htmlfooterformat ?? FORMAT_MOODLE;
        } else {
            $htmlfooter = '';
        }

        unset($this->block->config->htmlheader);
        unset($this->block->config->htmlfooter);
        parent::set_data($defaults);

        // Restore html header and html footer.
        if (!isset($this->block->config)) {
            $this->block->config = new stdClass();
        }
        $this->block->config->htmlheader = $htmlheader;
        $this->block->config->htmlfooter = $htmlfooter;
    }
}
