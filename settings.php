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
 * Settings for the block.
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/blocks/vitrina/classes/admin_setting_configmultiselect_autocomplete.php');

if ($ADMIN->fulltree) {

    // Get custom fields.
    $fields = [0 => ''];
    $customfields = $DB->get_records_menu('customfield_field', null, 'name', 'id, name');

    foreach ($customfields as $k => $v) {
        $fields[$k] = format_string($v, true);
    }

    // Get user fields.
    $userfields = [0 => ''];
    $customuserfields = $DB->get_records_menu('user_info_field', null, 'shortname', 'id, shortname');

    foreach ($customuserfields as $k => $v) {
        $userfields[$k] = format_string($v, true);
    }

    // Course fields.
    $name = 'block_vitrina/settingsheaderfields';
    $heading = get_string('settingsheaderfields', 'block_vitrina');
    $setting = new admin_setting_heading($name, $heading, '');
    $settings->add($setting);

    // Thematic field.
    $name = 'block_vitrina/thematic';
    $title = get_string('thematicfield', 'block_vitrina');
    $help = get_string('thematicfield_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, '', $fields);
    $settings->add($setting);

    // Content units field.
    $name = 'block_vitrina/units';
    $title = get_string('unitsfield', 'block_vitrina');
    $help = get_string('unitsfield_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, '', $fields);
    $settings->add($setting);

    // Requirements field.
    $name = 'block_vitrina/requirements';
    $title = get_string('requirementsfield', 'block_vitrina');
    $help = get_string('requirementsfield_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, '', $fields);
    $settings->add($setting);

    // License field.
    $name = 'block_vitrina/license';
    $title = get_string('licensefield', 'block_vitrina');
    $help = get_string('licensefield_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, '', $fields);
    $settings->add($setting);

    // Media field.
    $name = 'block_vitrina/media';
    $title = get_string('mediafield', 'block_vitrina');
    $help = get_string('mediafield_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, '', $fields);
    $settings->add($setting);

    // Duration field.
    $name = 'block_vitrina/duration';
    $title = get_string('durationfield', 'block_vitrina');
    $help = get_string('durationfield_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, '', $fields);
    $settings->add($setting);

    // Experts field.
    $name = 'block_vitrina/experts';
    $title = get_string('expertsfield', 'block_vitrina');
    $help = get_string('expertsfield_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, '', $fields);
    $settings->add($setting);

    // Short experts field.
    $name = 'block_vitrina/expertsshort';
    $title = get_string('expertsshortfield', 'block_vitrina');
    $help = get_string('expertsshortfield_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, '', $fields);
    $settings->add($setting);

    // Payment fields.
    $name = 'block_vitrina/settingsheaderpayment';
    $heading = get_string('settingsheaderpayment', 'block_vitrina');
    $setting = new admin_setting_heading($name, $heading, '');
    $settings->add($setting);

    // Payment url field.
    $name = 'block_vitrina/paymenturl';
    $title = get_string('paymenturlfield', 'block_vitrina');
    $help = get_string('paymenturlfield_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, '', $fields);
    $settings->add($setting);

    // Premium type user field.
    $name = 'block_vitrina/premiumfield';
    $title = get_string('premiumfield', 'block_vitrina');
    $help = get_string('premiumfield_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, '', $userfields);
    $settings->add($setting);

    // Premium type value.
    $name = 'block_vitrina/premiumvalue';
    $title = get_string('premiumvalue', 'block_vitrina');
    $help = get_string('premiumvalue_help', 'block_vitrina');
    $setting = new admin_setting_configtext($name, $title, $help, '');
    $settings->add($setting);

    // Appearance.
    $name = 'block_vitrina/settingsheaderappearance';
    $heading = get_string('settingsheaderappearance', 'block_vitrina');
    $setting = new admin_setting_heading($name, $heading, '');
    $settings->add($setting);

    // Courses in block view.
    $name = 'block_vitrina/singleamount';
    $title = get_string('singleamountcourses', 'block_vitrina');
    $help = get_string('singleamountcourses_help', 'block_vitrina');
    $setting = new admin_setting_configtext($name, $title, $help, 4, PARAM_INT, 2);
    $settings->add($setting);

    // Courses by page.
    $name = 'block_vitrina/amount';
    $title = get_string('amountcourses', 'block_vitrina');
    $help = get_string('amountcourses_help', 'block_vitrina');
    $setting = new admin_setting_configtext($name, $title, $help, 20, PARAM_INT, 5);
    $settings->add($setting);

    // Sort by default.
    $options = [
        'default' => get_string('sortbystartdate', 'block_vitrina'),
        'sortbyfinishdate' => get_string('sortbyfinishdate', 'block_vitrina'),
        'sortalphabetically' => get_string('sortalphabetically', 'block_vitrina')
    ];

    $name = 'block_vitrina/sortbydefault';
    $title = get_string('sortbydefault', 'block_vitrina');
    $help = get_string('sortbydefault_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, 'default', $options);
    $settings->add($setting);

    // Days to upcoming courses.
    $name = 'block_vitrina/daystoupcoming';
    $title = get_string('daystoupcoming', 'block_vitrina');
    $help = get_string('daystoupcoming_help', 'block_vitrina');
    $setting = new admin_setting_configtext($name, $title, $help, 0, PARAM_INT, 3);
    $settings->add($setting);

    // Social networks.
    $name = 'block_vitrina/networks';
    $title = get_string('socialnetworks', 'block_vitrina');
    $help = get_string('socialnetworks_help', 'block_vitrina');
    $setting = new admin_setting_configtextarea($name, $title, $help, '');
    $settings->add($setting);

    // Select courses categories.
    $name = 'block_vitrina/categories';
    $title = get_string('categories', 'block_vitrina');
    $help = get_string('categories_help', 'block_vitrina');
    $displaylist = \core_course_category::make_categories_list('moodle/category:manage');
    $default = [];

    $setting = new admin_setting_configmultiselect_autocomplete (
        'block_vitrina/categories',
        get_string('categories', 'block_vitrina'),
        get_string('categories_help', 'block_vitrina'),
        $default,
        $displaylist
    );

    $settings->add($setting);

    // Block summary.
    $name = 'block_vitrina/summary';
    $title = get_string('summary', 'block_vitrina');
    $help = get_string('summary_help', 'block_vitrina');
    $setting = new admin_setting_confightmleditor($name, $title, $help, '');
    $settings->add($setting);

    // Block detail info.
    $name = 'block_vitrina/detailinfo';
    $title = get_string('detailinfo', 'block_vitrina');
    $help = get_string('detailinfo_help', 'block_vitrina');
    $setting = new admin_setting_confightmleditor($name, $title, $help, '');
    $settings->add($setting);

    // Tabs view.
    $options = [
        'default' => get_string('textandicon', 'block_vitrina'),
        'showtext' => get_string('showtext', 'block_vitrina'),
        'showicon' => get_string('showicon', 'block_vitrina')
    ];

    $name = 'block_vitrina/tabview';
    $title = get_string('tabview', 'block_vitrina');
    $help = get_string('tabview_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, 'default', $options);
    $settings->add($setting);

    // Views icons.
    $name = 'block_vitrina/viewsicons';
    $title = get_string('viewsicons', 'block_vitrina');
    $help = get_string('viewsicons_help', 'block_vitrina');
    $setting = new admin_setting_configtextarea($name, $title, $help, '');
    $settings->add($setting);

    // Cover image type.
    $options = [
        'default' => get_string('coverimagetype_default', 'block_vitrina'),
        'generated' => get_string('coverimagetype_generated', 'block_vitrina'),
        'none' => get_string('coverimagetype_none', 'block_vitrina'),
    ];

    $name = 'block_vitrina/coverimagetype';
    $title = get_string('coverimagetype', 'block_vitrina');
    $help = get_string('coverimagetype_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, 'default', $options);
    $settings->add($setting);

    // Template type.
    $options = ['default' => ''];

    $path = $CFG->dirroot . '/blocks/vitrina/templates/';
    $files = array_diff(scandir($path), ['..', '.']);

    foreach ($files as $file) {
        if (is_dir($path . $file)) {
            $options[$file] = $file;
        }
    }

    $name = 'block_vitrina/templatetype';
    $title = get_string('templatetype', 'block_vitrina');
    $help = get_string('templatetype_help', 'block_vitrina');
    $setting = new admin_setting_configselect($name, $title, $help, 'default', $options);
    $settings->add($setting);

}
