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
 * Class containing the logic to shop manager.
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_vitrina\local\shop;

/**
 * Shop local_buybee.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_buybee {
    /**
     * Define if the plugin is available.
     *
     * @return bool
     */
    public static function available(): bool {
        $list = \core_component::get_plugin_list('local');
        foreach ($list as $name => $notused) {
            if ($name == 'buybee') {
                return get_config('local_buybee', 'enabled');
            }
        }

        return false;
    }

    /**
     * Get the product reference.
     *
     * @param string $type
     * @param int $itemid
     * @return string
     */
    public static function get_product_reference(string $type, int $itemid): string {
        return \local_buybee\controller::get_product_reference($type, $itemid);
    }

    /**
     * Render the shop data to template.
     *
     * @return array
     */
    public static function render_from_template(): array {
        global $OUTPUT;
        $data = [
            'plugin' => 'local_buybee',
            'title' => get_string('pluginname', 'local_buybee'),
            'labeladdproduct' => get_string('labeladdproduct', 'local_buybee'),
            'addicon' => $OUTPUT->image_icon('i/addtocart', get_string('labeladdproduct', 'local_buybee')),
        ];

        return $data;
    }
}
