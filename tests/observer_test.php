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
 * Unit tests for the observer class.
 *
 * @package    block_vitrina
 * @copyright  2024 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_vitrina;

use block_vitrina\local\observer;
use block_vitrina\local\controller;

/**
 * Tests for the observer class.
 *
 * @package    block_vitrina
 * @coversDefaultClass \block_vitrina\local\observer
 */
class observer_test extends \advanced_testcase {
    /**
     * Reset controller static caches before each test.
     */
    protected function setUp(): void {
        parent::setUp();

        $properties = ['cachedpayfield', 'cachedpremiumfield', 'isuserpremium', 'usermembership'];
        $reflection = new \ReflectionClass(controller::class);
        foreach ($properties as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    /**
     * Helper to create a custom field (checkbox) for courses.
     *
     * @return int The field id.
     */
    private function create_premium_customfield(): int {
        global $DB;

        $category = $DB->get_record('customfield_category', ['component' => 'core_course']);
        if (!$category) {
            $handler = \core_customfield\handler::get_handler('core_course', 'course');
            $handler->create_category();
            $category = $DB->get_record('customfield_category', ['component' => 'core_course']);
        }

        $fieldid = $DB->insert_record('customfield_field', [
            'shortname' => 'ispremium',
            'name' => 'Is Premium',
            'type' => 'checkbox',
            'categoryid' => $category->id,
            'configdata' => '{}',
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return $fieldid;
    }

    /**
     * Helper to set a course as premium via customfield_data.
     *
     * @param int $fieldid The custom field id.
     * @param object $course The course object.
     */
    private function set_course_premium(int $fieldid, object $course): void {
        global $DB;

        $DB->insert_record('customfield_data', [
            'fieldid' => $fieldid,
            'instanceid' => $course->id,
            'intvalue' => 1,
            'value' => '1',
            'valueformat' => 0,
            'contextid' => \context_course::instance($course->id)->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Test that unenrolling from a non-premium course has no effect.
     *
     * @covers ::user_change_enrolment
     */
    public function test_unenrol_non_premium_course_no_effect(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $selfplugin = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$instance) {
            $instanceid = $selfplugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $instanceid]);
        }
        $selfplugin->enrol_user($instance, $user->id);

        $enrolment = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);

        // Should not throw and should not change anything.
        observer::user_change_enrolment($enrolment, $user->id, observer::ACTION_REMOVE);

        // User is still enrolled.
        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user->id));
    }

    /**
     * Test removing enrolment from the premium gateway course removes self-enrolments in premium courses.
     *
     * @covers ::user_change_enrolment
     */
    public function test_user_change_enrolment_remove_premium(): void {
        global $DB;

        $this->resetAfterTest();

        // Create custom field and configure it.
        $fieldid = $this->create_premium_customfield();
        set_config('premiumcoursefield', $fieldid, 'block_vitrina');

        // Create the premium gateway course.
        $gatewaycourse = $this->getDataGenerator()->create_course();
        set_config('premiumenrolledcourse', $gatewaycourse->id, 'block_vitrina');

        // Create a premium course and mark it.
        $premiumcourse = $this->getDataGenerator()->create_course();
        $this->set_course_premium($fieldid, $premiumcourse);

        $user = $this->getDataGenerator()->create_user();
        $selfplugin = enrol_get_plugin('self');

        // Enrol user in the gateway course via self enrolment.
        $gatewayinstance = $DB->get_record('enrol', ['courseid' => $gatewaycourse->id, 'enrol' => 'self']);
        if (!$gatewayinstance) {
            $instanceid = $selfplugin->add_instance($gatewaycourse);
            $gatewayinstance = $DB->get_record('enrol', ['id' => $instanceid]);
        }
        $selfplugin->enrol_user($gatewayinstance, $user->id);

        // Enrol user in the premium course via self enrolment.
        $premiuminstance = $DB->get_record('enrol', ['courseid' => $premiumcourse->id, 'enrol' => 'self']);
        if (!$premiuminstance) {
            $instanceid = $selfplugin->add_instance($premiumcourse);
            $premiuminstance = $DB->get_record('enrol', ['id' => $instanceid]);
        }
        $selfplugin->enrol_user($premiuminstance, $user->id);

        // Confirm user is enrolled in the premium course.
        $this->assertTrue(is_enrolled(\context_course::instance($premiumcourse->id), $user->id));

        // Build the enrolment object for the gateway course.
        $gatewayenrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $gatewayinstance->id,
            'userid' => $user->id,
        ]);

        // Call observer with ACTION_REMOVE.
        observer::user_change_enrolment($gatewayenrolment, $user->id, observer::ACTION_REMOVE);

        // Verify the user's self-enrolment in the premium course was removed.
        $enrolrecord = $DB->get_record('user_enrolments', [
            'enrolid' => $premiuminstance->id,
            'userid' => $user->id,
        ]);
        $this->assertFalse($enrolrecord);
    }

    /**
     * Test suspending enrolment from the premium gateway course suspends self-enrolments in premium courses.
     *
     * @covers ::user_change_enrolment
     */
    public function test_user_change_enrolment_suspend_premium(): void {
        global $DB;

        $this->resetAfterTest();

        // Create custom field and configure it.
        $fieldid = $this->create_premium_customfield();
        set_config('premiumcoursefield', $fieldid, 'block_vitrina');

        // Create the premium gateway course.
        $gatewaycourse = $this->getDataGenerator()->create_course();
        set_config('premiumenrolledcourse', $gatewaycourse->id, 'block_vitrina');

        // Create a premium course and mark it.
        $premiumcourse = $this->getDataGenerator()->create_course();
        $this->set_course_premium($fieldid, $premiumcourse);

        $user = $this->getDataGenerator()->create_user();
        $selfplugin = enrol_get_plugin('self');

        // Enrol user in the gateway course via self enrolment.
        $gatewayinstance = $DB->get_record('enrol', ['courseid' => $gatewaycourse->id, 'enrol' => 'self']);
        if (!$gatewayinstance) {
            $instanceid = $selfplugin->add_instance($gatewaycourse);
            $gatewayinstance = $DB->get_record('enrol', ['id' => $instanceid]);
        }
        $selfplugin->enrol_user($gatewayinstance, $user->id);

        // Enrol user in the premium course via self enrolment.
        $premiuminstance = $DB->get_record('enrol', ['courseid' => $premiumcourse->id, 'enrol' => 'self']);
        if (!$premiuminstance) {
            $instanceid = $selfplugin->add_instance($premiumcourse);
            $premiuminstance = $DB->get_record('enrol', ['id' => $instanceid]);
        }
        $selfplugin->enrol_user($premiuminstance, $user->id);

        // Build the enrolment object for the gateway course.
        $gatewayenrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $gatewayinstance->id,
            'userid' => $user->id,
        ]);

        // Call observer with ACTION_INACTIVE.
        observer::user_change_enrolment($gatewayenrolment, $user->id, observer::ACTION_INACTIVE);

        // Verify the user's enrolment in the premium course is suspended.
        $enrolrecord = $DB->get_record('user_enrolments', [
            'enrolid' => $premiuminstance->id,
            'userid' => $user->id,
        ]);
        $this->assertNotFalse($enrolrecord);
        $this->assertEquals(ENROL_USER_SUSPENDED, (int)$enrolrecord->status);
    }

    /**
     * Test reactivating enrolment from the premium gateway course reactivates self-enrolments in premium courses.
     *
     * @covers ::user_change_enrolment
     */
    public function test_user_change_enrolment_reactivate_premium(): void {
        global $DB;

        $this->resetAfterTest();

        // Create custom field and configure it.
        $fieldid = $this->create_premium_customfield();
        set_config('premiumcoursefield', $fieldid, 'block_vitrina');

        // Create the premium gateway course.
        $gatewaycourse = $this->getDataGenerator()->create_course();
        set_config('premiumenrolledcourse', $gatewaycourse->id, 'block_vitrina');

        // Create a premium course and mark it.
        $premiumcourse = $this->getDataGenerator()->create_course();
        $this->set_course_premium($fieldid, $premiumcourse);

        $user = $this->getDataGenerator()->create_user();
        $selfplugin = enrol_get_plugin('self');

        // Enrol user in the gateway course via self enrolment.
        $gatewayinstance = $DB->get_record('enrol', ['courseid' => $gatewaycourse->id, 'enrol' => 'self']);
        if (!$gatewayinstance) {
            $instanceid = $selfplugin->add_instance($gatewaycourse);
            $gatewayinstance = $DB->get_record('enrol', ['id' => $instanceid]);
        }
        $selfplugin->enrol_user($gatewayinstance, $user->id);

        // Enrol user in the premium course via self enrolment.
        $premiuminstance = $DB->get_record('enrol', ['courseid' => $premiumcourse->id, 'enrol' => 'self']);
        if (!$premiuminstance) {
            $instanceid = $selfplugin->add_instance($premiumcourse);
            $premiuminstance = $DB->get_record('enrol', ['id' => $instanceid]);
        }
        $selfplugin->enrol_user($premiuminstance, $user->id);

        // First suspend the enrolment in the premium course.
        $gatewayenrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $gatewayinstance->id,
            'userid' => $user->id,
        ]);
        observer::user_change_enrolment($gatewayenrolment, $user->id, observer::ACTION_INACTIVE);

        // Reset controller caches so get_premiumfield is fetched again.
        $reflection = new \ReflectionClass(controller::class);
        $prop = $reflection->getProperty('cachedpremiumfield');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        // Verify it is suspended first.
        $enrolrecord = $DB->get_record('user_enrolments', [
            'enrolid' => $premiuminstance->id,
            'userid' => $user->id,
        ]);
        $this->assertEquals(ENROL_USER_SUSPENDED, (int)$enrolrecord->status);

        // Call observer with ACTION_REACTIVE.
        observer::user_change_enrolment($gatewayenrolment, $user->id, observer::ACTION_REACTIVE);

        // Verify the user's enrolment in the premium course is active again.
        $enrolrecord = $DB->get_record('user_enrolments', [
            'enrolid' => $premiuminstance->id,
            'userid' => $user->id,
        ]);
        $this->assertNotFalse($enrolrecord);
        $this->assertEquals(ENROL_USER_ACTIVE, (int)$enrolrecord->status);
    }
}
