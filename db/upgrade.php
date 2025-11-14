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
 * This file keeps track of upgrades to the block.
 *
 * @package block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade code for the vitrina block.
 *
 * @param int $oldversion
 */
function xmldb_block_vitrina_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2023042602) {
        $customfields = $DB->get_records('customfield_field');

        foreach ($customfields as $field) {
            $select = "plugin = 'block_vitrina' AND value = :value AND " .
                        " name IN ('thematic', 'units', 'requirements', 'license', 'media', 'duration', 'experts', " .
                                    " 'expertsshort', 'paymenturl')";

            $params = ['value' => $field->shortname];

            $DB->set_field_select('config_plugins', 'value', $field->id, $select, $params);
        }

        $fieldname = get_config('block_vitrina', 'premiumfield');

        if (!empty($fieldname)) {
            $premiumfield = $DB->get_record('user_info_field', ['shortname' => $fieldname]);

            if ($premiumfield) {
                $DB->set_field_select(
                    'config_plugins',
                    'value',
                    $premiumfield->id,
                    "plugin = 'block_vitrina' AND name = 'premiumfield'"
                );
            }
        }

        // Savepoint reached.
        upgrade_block_savepoint(true, 2023042602, 'vitrina');
    }

    if ($oldversion < 2023042604) {
        $DB->delete_records('config_plugins', ['plugin' => 'block_vitrina', 'name' => 'thematic']);
        $DB->delete_records('config_plugins', ['plugin' => 'block_vitrina', 'name' => 'units']);
        $DB->delete_records('config_plugins', ['plugin' => 'block_vitrina', 'name' => 'requirements']);
        $DB->delete_records('config_plugins', ['plugin' => 'block_vitrina', 'name' => 'duration']);
        $DB->delete_records('config_plugins', ['plugin' => 'block_vitrina', 'name' => 'experts']);
        $DB->delete_records('config_plugins', ['plugin' => 'block_vitrina', 'name' => 'expertsshort']);

        // Savepoint reached.
        upgrade_block_savepoint(true, 2023042604, 'vitrina');
    }

    return true;
}
