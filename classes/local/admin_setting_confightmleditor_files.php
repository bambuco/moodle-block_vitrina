<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * HTML editor setting with embedded files.
 *
 * @package    block_vitrina
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/lib/form/editor.php');

/**
 * Stores HTML editor content and its embedded files in a system file area.
 *
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_vitrina_admin_setting_confightmleditor_files extends admin_setting_confightmleditor {
    /** @var string File area used by this setting. */
    protected $filearea;

    /** @var context_system System context used by the setting. */
    protected $context;

    /** @var array Editor and file area options. */
    protected $options;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     * @param string $filearea
     */
    public function __construct($name, $visiblename, $description, $filearea) {
        parent::__construct($name, $visiblename, $description, '');
        $this->filearea = $filearea;
        $this->context = context_system::instance();
        $this->options = [
            'context' => $this->context,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => 0,
            'areamaxbytes' => FILE_AREA_MAX_BYTES_UNLIMITED,
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE | FILE_CONTROLLED_LINK,
            'subdirs' => true,
            'noclean' => true,
            'enable_filemanagement' => true,
            'removeorphaneddrafts' => false,
        ];
        $this->customcontrol = true;
    }

    /**
     * Returns the setting value.
     *
     * @return string|null
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Saves the editor content and its draft files.
     *
     * @param array|string $data
     * @return string
     */
    public function write_setting($data) {
        if (!is_array($data)) {
            return $this->config_write($this->name, $data) ? '' : get_string('errorsetting', 'admin');
        }

        $text = $data['text'] ?? '';
        $draftitemid = clean_param($data['itemid'] ?? 0, PARAM_INT);
        if (!$draftitemid) {
            return get_string('errorsetting', 'admin');
        }

        $text = file_save_draft_area_files(
            $draftitemid,
            $this->context->id,
            'block_vitrina',
            $this->filearea,
            0,
            $this->options,
            $text
        );

        return $this->config_write($this->name, $text) ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Generates the editor inside the standard administration settings form.
     *
     * @param string $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        $elementname = $this->get_full_name();
        $draftitemid = file_get_submitted_draft_itemid($elementname);

        $text = file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'block_vitrina',
            $this->filearea,
            0,
            $this->options,
            (string)$data
        );

        $editor = new MoodleQuickForm_editor(
            $elementname,
            $this->visiblename,
            ['id' => $this->get_id(), 'rows' => 15, 'cols' => 80],
            $this->options
        );
        $editor->setValue([
            'text' => $text,
            'format' => FORMAT_HTML,
            'itemid' => $draftitemid,
        ]);
        if ($this->is_readonly()) {
            $editor->freeze();
        }

        return format_admin_setting(
            $this,
            $this->visiblename,
            $editor->toHtml(),
            $this->description,
            true,
            '',
            $this->get_defaultsetting(),
            $query
        );
    }
}
