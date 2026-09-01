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
 * Unit tests for the block_vitrina implementation of the privacy API.
 *
 * @package    block_vitrina
 * @category   test
 * @copyright  2023 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_vitrina\privacy;

defined('MOODLE_INTERNAL') || die();

use block_vitrina\privacy\provider;

/**
 * Unit tests for the block_vitrina implementation of the privacy API.
 *
 * @copyright  2023 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \block_vitrina\privacy\provider
 */
final class provider_test extends \advanced_testcase {

    /**
     * Test that the provider implements the null_provider interface.
     *
     * @covers ::get_reason
     */
    public function test_implements_null_provider(): void {
        $provider = new provider();
        $this->assertInstanceOf(\core_privacy\local\metadata\null_provider::class, $provider);
    }

    /**
     * Test that get_reason returns the expected language string identifier.
     *
     * @covers ::get_reason
     */
    public function test_get_reason(): void {
        $this->assertEquals('privacy:metadata', provider::get_reason());
    }
}
