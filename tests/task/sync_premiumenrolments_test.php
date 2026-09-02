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

namespace block_vitrina\task;

/**
 * Unit tests for the sync_premiumenrolments scheduled task.
 *
 * @package    block_vitrina
 * @copyright  2024 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_vitrina\task\sync_premiumenrolments
 */
final class sync_premiumenrolments_test extends \advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();

        // Reset the static cache in controller via reflection.
        $reflection = new \ReflectionClass(\block_vitrina\local\controller::class);
        $property = $reflection->getProperty('cachedpremiumfield');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Test that get_name returns a non-empty string.
     *
     * @covers ::get_name
     */
    public function test_get_name(): void {
        $this->resetAfterTest();

        $task = new sync_premiumenrolments();
        $name = $task->get_name();

        $this->assertNotEmpty($name);
        $this->assertIsString($name);
    }

    /**
     * Test execute when no premium course is configured.
     *
     * @covers ::execute
     */
    public function test_execute_no_premium_course_configured(): void {
        $this->resetAfterTest();

        // Ensure no premiumenrolledcourse config is set.
        unset_config('premiumenrolledcourse', 'block_vitrina');

        $this->expectOutputString("No premium course selected\n");

        $task = new sync_premiumenrolments();
        $task->execute();
    }

    /**
     * Test execute when premium course is set but no premium field is configured.
     *
     * @covers ::execute
     */
    public function test_execute_no_premium_field_configured(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        set_config('premiumenrolledcourse', $course->id, 'block_vitrina');
        unset_config('premiumcoursefield', 'block_vitrina');

        $this->expectOutputString("No course premium field selected\n");

        $task = new sync_premiumenrolments();
        $task->execute();
    }

    /**
     * Test that expired enrolments are suspended.
     *
     * @covers ::execute
     */
    public function test_execute_suspends_expired_enrolments(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a custom field for premium courses.
        $category = $DB->get_record('customfield_category', ['component' => 'core_course']);
        if (!$category) {
            $handler = \core_customfield\handler::get_handler('core_course', 'course');
            $handler->create_category();
            $category = $DB->get_record('customfield_category', ['component' => 'core_course']);
        }

        $fieldid = $DB->insert_record('customfield_field', [
            'shortname' => 'premium',
            'name' => 'Premium',
            'type' => 'checkbox',
            'categoryid' => $category->id,
            'sortorder' => 0,
            'configdata' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        set_config('premiumcoursefield', $fieldid, 'block_vitrina');

        // Create course and user.
        $course = $this->getDataGenerator()->create_course();
        set_config('premiumenrolledcourse', $course->id, 'block_vitrina');

        $user = $this->getDataGenerator()->create_user();

        // Set up self-enrolment with an expired timeend.
        $selfplugin = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$instance) {
            $selfplugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        }
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $instance->id]);

        // Enrol user with timeend in the past (expired).
        $selfplugin->enrol_user($instance, $user->id, null, 0, time() - 100);

        // Verify enrolment is active before task runs.
        $enrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ]);
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $enrolment->status);

        // Execute the task.
        $this->expectOutputString("Suspended enrolment for user {$user->id}\n");
        $task = new sync_premiumenrolments();
        $task->execute();

        // Verify the enrolment has been suspended.
        $enrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ]);
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $enrolment->status);
    }

    /**
     * Test that valid (non-expired) enrolments remain active.
     *
     * @covers ::execute
     */
    public function test_execute_keeps_active_valid_enrolments(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a custom field for premium courses.
        $category = $DB->get_record('customfield_category', ['component' => 'core_course']);
        if (!$category) {
            $handler = \core_customfield\handler::get_handler('core_course', 'course');
            $handler->create_category();
            $category = $DB->get_record('customfield_category', ['component' => 'core_course']);
        }

        $fieldid = $DB->insert_record('customfield_field', [
            'shortname' => 'premium',
            'name' => 'Premium',
            'type' => 'checkbox',
            'categoryid' => $category->id,
            'sortorder' => 0,
            'configdata' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        set_config('premiumcoursefield', $fieldid, 'block_vitrina');

        // Create course and user.
        $course = $this->getDataGenerator()->create_course();
        set_config('premiumenrolledcourse', $course->id, 'block_vitrina');

        $user = $this->getDataGenerator()->create_user();

        // Set up self-enrolment with a future timeend.
        $selfplugin = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$instance) {
            $selfplugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        }
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $instance->id]);

        // Enrol user with timeend in the future (still valid).
        $selfplugin->enrol_user($instance, $user->id, null, 0, time() + 86400);

        // Execute the task.
        $task = new sync_premiumenrolments();
        $task->execute();

        // Verify the enrolment remains active.
        $enrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $user->id,
        ]);
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $enrolment->status);
    }
}
