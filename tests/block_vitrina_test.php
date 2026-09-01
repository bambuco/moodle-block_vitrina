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

namespace block_vitrina;

/**
 * Unit test for block_vitrina class.
 *
 * @package   block_vitrina
 * @copyright 2024 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \block_vitrina
 */
final class block_vitrina_test extends \advanced_testcase {
    /**
     * Tests that init sets the block title to the pluginname string.
     * @covers ::init
     */
    public function test_init(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $block = $this->create_block($course);

        $this->assertEquals(get_string('pluginname', 'block_vitrina'), $block->title);
    }

    /**
     * Tests specialization with a custom title.
     * @covers ::specialization
     */
    public function test_specialization_with_custom_title(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $block = $this->create_block($course);

        $itemid1 = file_get_unused_draft_itemid();
        $itemid2 = file_get_unused_draft_itemid();
        $data = (object) [
            'title' => 'Custom Vitrina Title',
            'htmlheader' => ['itemid' => $itemid1, 'text' => '', 'format' => FORMAT_HTML],
            'htmlfooter' => ['itemid' => $itemid2, 'text' => '', 'format' => FORMAT_HTML],
        ];
        $block->instance_config_save($data);
        // Theinstance_config_save() only persists to the DB; simulate a reload to refresh in-memory config.
        $block->config = $data;
        $block->specialization();

        $this->assertEquals('Custom Vitrina Title', $block->title);
    }

    /**
     * Tests specialization without a custom title uses newblocktitle.
     * @covers ::specialization
     */
    public function test_specialization_without_title(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $block = $this->create_block($course);

        $itemid1 = file_get_unused_draft_itemid();
        $itemid2 = file_get_unused_draft_itemid();
        $data = (object) [
            'htmlheader' => ['itemid' => $itemid1, 'text' => '', 'format' => FORMAT_HTML],
            'htmlfooter' => ['itemid' => $itemid2, 'text' => '', 'format' => FORMAT_HTML],
        ];
        $block->instance_config_save($data);
        $block->specialization();

        $this->assertEquals(get_string('newblocktitle', 'block_vitrina'), $block->title);
    }

    /**
     * Tests that get_content returns a stdClass with text and footer.
     * @covers ::get_content
     */
    public function test_get_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $block = $this->create_block($course);

        // Enable the default tab so the block renders content.
        $itemid1 = file_get_unused_draft_itemid();
        $itemid2 = file_get_unused_draft_itemid();
        $config = (object) [
            'default' => 1,
            'htmlheader' => ['itemid' => $itemid1, 'text' => '', 'format' => FORMAT_HTML],
            'htmlfooter' => ['itemid' => $itemid2, 'text' => '', 'format' => FORMAT_HTML],
        ];
        $block->instance_config_save($config);

        // Reset cached content to force re-generation.
        $block->content = null;
        $content = $block->get_content();

        $this->assertInstanceOf(\stdClass::class, $content);
        $this->assertObjectHasProperty('text', $content);
        $this->assertObjectHasProperty('footer', $content);
    }

    /**
     * Tests that get_content returns empty content for guest users when guestloginbutton is disabled.
     * @covers ::get_content
     */
    public function test_get_content_guest_no_login_button(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->guestloginbutton = 0;
        $CFG->autologinguests = 0;
        $this->setGuestUser();

        $course = $this->getDataGenerator()->create_course();

        // We need admin to create the block.
        $this->setAdminUser();
        $block = $this->create_block($course);
        $itemid1 = file_get_unused_draft_itemid();
        $itemid2 = file_get_unused_draft_itemid();
        $config = (object) [
            'default' => 1,
            'htmlheader' => ['itemid' => $itemid1, 'text' => '', 'format' => FORMAT_HTML],
            'htmlfooter' => ['itemid' => $itemid2, 'text' => '', 'format' => FORMAT_HTML],
        ];
        $block->instance_config_save($config);

        // Switch to non-logged-in state.
        $this->setUser(null);

        $block->content = null;
        $content = $block->get_content();

        $this->assertInstanceOf(\stdClass::class, $content);
        $this->assertEmpty($content->text);
        $this->assertEmpty($content->footer);
    }

    /**
     * Tests that instance_delete clears file areas.
     * @covers ::instance_delete
     */
    public function test_instance_delete(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $block = $this->create_block($course);

        // Add a file to the content_header area.
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'component' => 'block_vitrina',
            'filearea' => 'content_header',
            'contextid' => $block->context->id,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'testfile.txt',
        ], 'Test file content');

        $this->assertTrue($fs->file_exists($block->context->id, 'block_vitrina', 'content_header', 0, '/', 'testfile.txt'));

        $block->instance_delete();

        $this->assertFalse($fs->file_exists($block->context->id, 'block_vitrina', 'content_header', 0, '/', 'testfile.txt'));
    }

    /**
     * Tests that content_is_trusted returns true for a course context.
     * @covers ::content_is_trusted
     */
    public function test_content_is_trusted_course_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $block = $this->create_block($course);

        // On a course page the parent context is a course context, so it should be trusted.
        $this->assertTrue($block->content_is_trusted());
    }

    /**
     * Constructs a page object for the test course.
     *
     * @param \stdClass $course Moodle course object
     * @return \moodle_page Page object representing course view
     */
    protected static function construct_page($course): \moodle_page {
        $context = \context_course::instance($course->id);
        $page = new \moodle_page();
        $page->set_context($context);
        $page->set_course($course);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->blocks->load_blocks();
        return $page;
    }

    /**
     * Creates a vitrina block on a course.
     *
     * @param \stdClass $course Course object
     * @return \block_vitrina Block instance object
     */
    protected function create_block($course): \block_vitrina {
        $page = self::construct_page($course);
        $page->blocks->add_block_at_end_of_default_region('vitrina');

        // Load the block.
        $page = self::construct_page($course);
        $page->blocks->load_blocks();
        $blocks = $page->blocks->get_blocks_for_region($page->blocks->get_default_region());
        $block = end($blocks);
        return $block;
    }
}
