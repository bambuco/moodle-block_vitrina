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
 * Unit tests for the controller class.
 *
 * @package    block_vitrina
 * @category   test
 * @copyright  2025 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_vitrina\local\controller
 */
final class controller_test extends \advanced_testcase {
    /**
     * Reset static cached properties on the controller via reflection.
     */
    private function reset_controller_cache(): void {
        $rc = new \ReflectionClass(\block_vitrina\local\controller::class);
        $props = [
            'cachedpayfield',
            'cachedpremiumfield',
            'isuserpremium',
            'usermembership',
            'showicons',
            'showtext',
            'viewsicons',
        ];
        foreach ($props as $prop) {
            $rp = $rc->getProperty($prop);
            $rp->setAccessible(true);
            $rp->setValue(null, null);
        }
    }

    /**
     * Set up before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->reset_controller_cache();

        // Reset the instances counter.
        $rc = new \ReflectionClass(\block_vitrina\local\controller::class);
        $rp = $rc->getProperty('instancescounter');
        $rp->setAccessible(true);
        $rp->setValue(null, 0);
    }

    /**
     * Test get_uniqueid returns correct format and increments.
     *
     * @covers ::get_uniqueid
     */
    public function test_get_uniqueid(): void {
        $this->resetAfterTest();

        $first = \block_vitrina\local\controller::get_uniqueid();
        $second = \block_vitrina\local\controller::get_uniqueid();

        $this->assertMatchesRegularExpression('/^block_vitrina_\d+$/', $first);
        $this->assertMatchesRegularExpression('/^block_vitrina_\d+$/', $second);
        $this->assertNotEquals($first, $second);

        // Extract the numeric part and verify incrementing.
        $firstnum = (int) str_replace('block_vitrina_', '', $first);
        $secondnum = (int) str_replace('block_vitrina_', '', $second);
        $this->assertEquals($firstnum + 1, $secondnum);
    }

    /**
     * Test get_courses_views always includes default and recents.
     *
     * Optional views depend on site plugins/config: greats when a rating manager
     * is available, premium when a premium course field is configured.
     *
     * @covers ::get_courses_views
     */
    public function test_get_courses_views_default(): void {
        $this->resetAfterTest();

        $views = \block_vitrina\local\controller::get_courses_views();

        $this->assertContains('default', $views);
        $this->assertContains('recents', $views);

        $ratemanager = \block_vitrina\local\controller::get_ratemanager();
        if ($ratemanager::rating_available()) {
            $this->assertContains('greats', $views);
        } else {
            $this->assertNotContains('greats', $views);
        }

        if (\block_vitrina\local\controller::premium_available()) {
            $this->assertContains('premium', $views);
        } else {
            $this->assertNotContains('premium', $views);
        }
    }

    /**
     * Test get_courses_by_view with default view returns only active visible courses.
     *
     * @covers ::get_courses_by_view
     */
    public function test_get_courses_by_view_default(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        // Two visible active courses.
        $course1 = $generator->create_course([
            'visible' => 1,
            'startdate' => time() - DAYSECS,
            'enddate' => 0,
        ]);
        $course2 = $generator->create_course([
            'visible' => 1,
            'startdate' => time() - DAYSECS,
            'enddate' => time() + DAYSECS * 30,
        ]);

        // One course with enddate in the past (should be excluded).
        $course3 = $generator->create_course([
            'visible' => 1,
            'startdate' => time() - DAYSECS * 60,
            'enddate' => time() - DAYSECS,
        ]);

        $courses = \block_vitrina\local\controller::get_courses_by_view('default');

        // Use array key lookup: MariaDB returns course ids as strings while
        // get_records_sql array keys are integers (PHPUnit assertContains is strict).
        $this->assertArrayHasKey($course1->id, $courses);
        $this->assertArrayHasKey($course2->id, $courses);
        $this->assertArrayNotHasKey($course3->id, $courses);
        $this->assertArrayNotHasKey(SITEID, $courses);
    }

    /**
     * Test get_courses_by_view with recents view returns only future-start courses.
     *
     * @covers ::get_courses_by_view
     */
    public function test_get_courses_by_view_recents(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        // Course starting in the future.
        $future = $generator->create_course([
            'visible' => 1,
            'startdate' => time() + DAYSECS * 10,
            'enddate' => 0,
        ]);

        // Course starting in the past.
        $past = $generator->create_course([
            'visible' => 1,
            'startdate' => time() - DAYSECS * 10,
            'enddate' => 0,
        ]);

        $courses = \block_vitrina\local\controller::get_courses_by_view('recents');

        $this->assertArrayHasKey($future->id, $courses);
        $this->assertArrayNotHasKey($past->id, $courses);
    }

    /**
     * Test get_courses_by_view with an invalid view falls back to default.
     *
     * @covers ::get_courses_by_view
     */
    public function test_get_courses_by_view_invalid_view(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course([
            'visible' => 1,
            'startdate' => time() - DAYSECS,
            'enddate' => 0,
        ]);

        $courses = \block_vitrina\local\controller::get_courses_by_view('nonexistentview');

        $this->assertArrayHasKey($course->id, $courses);
    }

    /**
     * Test get_courseimage returns placeholder when no overview image is set.
     *
     * @covers ::get_courseimage
     */
    public function test_get_courseimage_default_placeholder(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['visible' => 1]);

        $image = \block_vitrina\local\controller::get_courseimage($course);

        $this->assertStringContainsString('course_small.png', $image);
    }

    /**
     * Test format_cost formats correctly with decimal points.
     *
     * @covers ::format_cost
     */
    public function test_format_cost(): void {
        $this->resetAfterTest();

        set_config('decimalpoints', 2, 'block_vitrina');

        $result = \block_vitrina\local\controller::format_cost(100.0, 'USD');

        $this->assertStringContainsString('100', $result);
        $this->assertStringContainsString('$', $result);
    }

    /**
     * Test format_cost applies surcharge correctly.
     *
     * @covers ::format_cost
     */
    public function test_format_cost_with_surcharge(): void {
        $this->resetAfterTest();

        set_config('decimalpoints', 0, 'block_vitrina');

        $result = \block_vitrina\local\controller::format_cost(100.0, 'USD', 10.0);

        $this->assertStringContainsString('110', $result);
    }

    /**
     * Test premium_available returns false when no premiumcoursefield configured.
     *
     * @covers ::premium_available
     */
    public function test_premium_available_without_field(): void {
        $this->resetAfterTest();

        set_config('premiumcoursefield', '', 'block_vitrina');

        $this->assertFalse(\block_vitrina\local\controller::premium_available());
    }

    /**
     * Test is_user_premium returns false without premium config.
     *
     * @covers ::is_user_premium
     */
    public function test_is_user_premium_not_premium(): void {
        $this->resetAfterTest();

        set_config('premiumfield', '', 'block_vitrina');
        set_config('premiumvalue', '', 'block_vitrina');
        set_config('premiumenrolledcourse', '', 'block_vitrina');
        set_config('premiumcohort', '', 'block_vitrina');

        $this->assertFalse(\block_vitrina\local\controller::is_user_premium());
    }

    /**
     * Test load_enrolinfo with self enrolment enabled.
     *
     * @covers ::load_enrolinfo
     */
    public function test_load_enrolinfo_self(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setGuestUser();

        $course = $this->getDataGenerator()->create_course(['visible' => 1]);

        $selfplugin = enrol_get_plugin('self');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self']);
        if (!$instance) {
            $selfplugin->add_instance($course);
        } else {
            // Make sure it is enabled.
            $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $instance->id]);
        }

        \block_vitrina\local\controller::load_enrolinfo($course);

        $this->assertTrue($course->enrollable);
        $this->assertArrayHasKey('self', $course->enrollsavailables);
    }

    /**
     * Test get_configuredcustomfields returns empty without config.
     *
     * @covers ::get_configuredcustomfields
     */
    public function test_get_configuredcustomfields_empty(): void {
        $this->resetAfterTest();

        set_config('filtercustomfields', '', 'block_vitrina');

        $result = \block_vitrina\local\controller::get_configuredcustomfields();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test show_tabicon returns true with default tabview.
     *
     * @covers ::show_tabicon
     */
    public function test_show_tabicon_default(): void {
        $this->resetAfterTest();

        set_config('tabview', 'default', 'block_vitrina');

        $this->assertTrue(\block_vitrina\local\controller::show_tabicon());
    }

    /**
     * Test show_tabtext returns false with showicon tabview.
     *
     * @covers ::show_tabtext
     */
    public function test_show_tabtext_showicon(): void {
        $this->resetAfterTest();

        set_config('tabview', 'showicon', 'block_vitrina');

        $this->assertFalse(\block_vitrina\local\controller::show_tabtext());
    }

    /**
     * Test include_templatecss does not throw with default template type.
     *
     * @covers ::include_templatecss
     */
    public function test_include_templatecss_default(): void {
        $this->resetAfterTest();

        set_config('templatetype', 'default', 'block_vitrina');

        \block_vitrina\local\controller::include_templatecss();

        // If no exception was thrown, the test passes.
        $this->assertTrue(true);
    }

    /**
     * Test get_staticfilters returns the expected list.
     *
     * @covers ::get_staticfilters
     */
    public function test_get_staticfilters(): void {
        $this->resetAfterTest();

        $filters = \block_vitrina\local\controller::get_staticfilters();

        $this->assertEquals(['langs', 'categories', 'fulltext'], $filters);
    }

    /**
     * Test get_views_icons returns the expected keys.
     *
     * @covers ::get_views_icons
     */
    public function test_get_views_icons(): void {
        $this->resetAfterTest();

        $icons = \block_vitrina\local\controller::get_views_icons();

        $this->assertIsArray($icons);
        $this->assertArrayHasKey('default', $icons);
        $this->assertArrayHasKey('greats', $icons);
        $this->assertArrayHasKey('premium', $icons);
        $this->assertArrayHasKey('recents', $icons);
    }
}
