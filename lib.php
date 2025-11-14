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
 * Manage files added to the html editors at instance config of block_vitrina.
 *
 * @package    block_vitrina
 * @copyright  2023 David Arias
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Form for editing Vitrina block instances.
 *
 * @package block_vitrina
 * @category files
 * @param stdClass $course course object
 * @param stdClass $birecordorcm block instance record
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 * @todo MDL-36050 improve capability check on stick blocks, so we can check user capability before sending images.
 */
function block_vitrina_pluginfile($course, $birecordorcm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if (
        $context->contextlevel != CONTEXT_BLOCK &&
        $context->contextlevel != CONTEXT_COURSE &&
        $context->contextlevel != CONTEXT_MODULE
    ) {
        send_file_not_found();
    }

    if ($filearea !== 'content_header' && $filearea !== 'content_footer') {
        send_file_not_found();
    }

    $fs = get_file_storage();

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    if ($filearea === 'content_header') {
        $file = $fs->get_file($context->id, 'block_vitrina', 'content_header', 0, $filepath, $filename);

        if (!($file && !$file->is_directory())) {
            send_file_not_found();
        }
    } else if ($filearea === 'content_footer') {
        $file = $fs->get_file($context->id, 'block_vitrina', 'content_footer', 0, $filepath, $filename);

        if (!($file && !$file->is_directory())) {
            send_file_not_found();
        }
    } else {
        send_file_not_found();
    }

    \core\session\manager::write_close();
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Perform global search replace such as when migrating site to new URL.
 *
 * @package block_vitrina
 * @param string $search
 * @param string $replace
 * @return void
 */
function block_vitrina_global_db_replace($search, $replace) {
    global $DB;

    $instances = $DB->get_recordset('block_instances', ['blockname' => 'vitrina']);
    foreach ($instances as $instance) {
        $config = unserialize_object(base64_decode($instance->configdata));

        if (isset($config->htmlheader) && is_string($config->htmlheader)) {
            $config->htmlheader = str_replace($search, $replace, $config->htmlheader);
            $DB->update_record(
                'block_instances',
                [
                    'id' => $instance->id,
                    'configdata' => base64_encode(serialize($config)),
                    'timemodified' => time(),
                ]
            );
        }

        if (isset($config->htmlfooter) && is_string($config->htmlfooter)) {
            $config->htmlfooter = str_replace($search, $replace, $config->htmlfooter);
            $DB->update_record(
                'block_instances',
                [
                    'id' => $instance->id,
                    'configdata' => base64_encode(serialize($config)),
                    'timemodified' => time(),
                ]
            );
        }
    }

    $instances->close();
}

/**
 * Given an array with a file path, it returns the itemid and the filepath for the defined filearea.
 *
 * @package block_vitrina
 * @param string $filearea The filearea.
 * @param array  $args The path (the part after the filearea and before the filename).
 * @return array The itemid and the filepath inside the $args path, for the defined filearea.
 */
function block_vitrina_get_path_from_pluginfile(string $filearea, array $args): array {
    // This block never has an itemid (the number represents the revision but it's not stored in database).
    array_shift($args);

    // Get the filepath.
    if (empty($args)) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    return [
        'itemid' => 0,
        'filepath' => $filepath,
    ];
}
