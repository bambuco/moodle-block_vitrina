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
 * Tests for the get_courses external service.
 *
 * @package    block_vitrina
 * @copyright  2024 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_vitrina\external;

use core_external\external_function_parameters;
use core_external\external_multiple_structure;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Tests for get_courses external function.
 *
 * @package    block_vitrina
 * @coversDefaultClass \block_vitrina\external\get_courses
 */
final class get_courses_test extends \core_external\tests\externallib_testcase {

    /**
     * Set up the page context for rendering.
     */
    private function setup_page(): void {
        global $PAGE;
        $PAGE->set_url('/');
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * Test execute_parameters returns proper structure.
     *
     * @covers ::execute_parameters
     */
    public function test_execute_parameters(): void {
        $this->resetAfterTest();

        $params = get_courses::execute_parameters();
        $this->assertInstanceOf(external_function_parameters::class, $params);
    }

    /**
     * Test execute_returns returns proper structure.
     *
     * @covers ::execute_returns
     */
    public function test_execute_returns_structure(): void {
        $this->resetAfterTest();

        $returns = get_courses::execute_returns();
        $this->assertInstanceOf(external_multiple_structure::class, $returns);
    }

    /**
     * Test execute returns courses.
     *
     * @covers ::execute
     */
    public function test_execute_returns_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_page();

        $generator = $this->getDataGenerator();

        $generator->create_course([
            'visible' => 1,
            'enddate' => 0,
            'startdate' => time() - DAYSECS,
        ]);
        $generator->create_course([
            'visible' => 1,
            'enddate' => 0,
            'startdate' => time() - DAYSECS,
        ]);

        set_config('singleamount', 10, 'block_vitrina');

        $result = get_courses::execute('default', [], 0, 10, 0);

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));

        foreach ($result as $course) {
            $this->assertArrayHasKey('id', (array) $course);
            $this->assertArrayHasKey('html', (array) $course);
        }
    }

    /**
     * Test execute with category filter.
     *
     * @covers ::execute
     */
    public function test_execute_with_category_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_page();

        $generator = $this->getDataGenerator();

        $cat = $generator->create_category();
        $othercat = $generator->create_category();

        $course1 = $generator->create_course([
            'category' => $cat->id,
            'visible' => 1,
            'enddate' => 0,
            'startdate' => time() - DAYSECS,
        ]);
        $course2 = $generator->create_course([
            'category' => $cat->id,
            'visible' => 1,
            'enddate' => 0,
            'startdate' => time() - DAYSECS,
        ]);
        $course3 = $generator->create_course([
            'category' => $othercat->id,
            'visible' => 1,
            'enddate' => 0,
            'startdate' => time() - DAYSECS,
        ]);

        set_config('singleamount', 10, 'block_vitrina');

        $filters = [
            ['type' => 'categories', 'values' => [(string) $cat->id]],
        ];

        $result = get_courses::execute('default', $filters, 0, 10, 0);

        $returnedids = array_map(function ($item) {
            return is_object($item) ? $item->id : $item['id'];
        }, $result);

        $this->assertContains($course1->id, $returnedids);
        $this->assertContains($course2->id, $returnedids);
        $this->assertNotContains($course3->id, $returnedids);
    }

    /**
     * Test that hidden courses are excluded from results.
     *
     * @covers ::execute
     */
    public function test_execute_hidden_courses_excluded(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_page();

        $generator = $this->getDataGenerator();

        $visiblecourse = $generator->create_course([
            'visible' => 1,
            'enddate' => 0,
            'startdate' => time() - DAYSECS,
        ]);
        $hiddencourse = $generator->create_course([
            'visible' => 0,
            'enddate' => 0,
            'startdate' => time() - DAYSECS,
        ]);

        set_config('singleamount', 10, 'block_vitrina');
        set_config('includehiddencourses', 0, 'block_vitrina');

        $result = get_courses::execute('default', [], 0, 10, 0);

        $returnedids = array_map(function ($item) {
            return is_object($item) ? $item->id : $item['id'];
        }, $result);

        $this->assertNotContains($hiddencourse->id, $returnedids);
    }

    /**
     * Test execute with amount limit.
     *
     * @covers ::execute
     */
    public function test_execute_with_amount_limit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setup_page();

        $generator = $this->getDataGenerator();
        $cat = $generator->create_category();

        for ($i = 0; $i < 5; $i++) {
            $generator->create_course([
                'category' => $cat->id,
                'visible' => 1,
                'enddate' => 0,
                'startdate' => time() - DAYSECS,
            ]);
        }

        set_config('singleamount', 10, 'block_vitrina');

        $filters = [
            ['type' => 'categories', 'values' => [(string) $cat->id]],
        ];

        $result = get_courses::execute('default', $filters, 0, 2, 0);

        $this->assertLessThanOrEqual(2, count($result));
    }
}
