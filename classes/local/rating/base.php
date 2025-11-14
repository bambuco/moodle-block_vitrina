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
 * Class containing the general logic to course rating.
 *
 * @package   block_vitrina
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_vitrina\local\rating;

/**
 * Rating base.
 *
 * @copyright 2023 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base {
    /**
     * Define if rating is available.
     *
     * @return boolean
     */
    public static function rating_available(): bool {
        global $PAGE;

        $bmanager = new \block_manager($PAGE);

        return $bmanager->is_known_block_type('rate_course');
    }

    /**
     * Get the course rating.
     *
     * @param object|int $course Course to be processed.
     * @param bool $large True if load full information about the course rating.
     * @return object
     */
    public static function get_ratings($course, $large = false): ?object {
        global $DB;

        if (!self::rating_available()) {
            return null;
        }

        if (is_object($course)) {
            $course = $course->id;
        }

        if ($large) {
            $values = $DB->get_records('block_rate_course', ['course' => $course], '', 'id, rating');

            // Start default array to 1-5 stars.
            $ratinglist = [0, 0, 0, 0, 0, 0];
            unset($ratinglist[0]);

            $ratingsum = 0;
            foreach ($values as $one) {
                $ratinglist[$one->rating]++;
                $ratingsum += $one->rating;
            }

            $ratings = count($values);
            $rating = $ratings > 0 ? $ratingsum / $ratings : 0;

            $ratingpercents = [];
            foreach ($ratinglist as $key => $one) {
                $ratingpercents[$key] = $ratings > 0 ? round($one * 100 / $ratings) : 0;
            }
        } else {
            $sql = "SELECT AVG(rating) AS rating, COUNT(1) AS ratings  FROM {block_rate_course} WHERE course = :courseid";
            $rate = $DB->get_record_sql($sql, ['courseid' => $course]);
            $ratinglist = null;
            $rating = $rate->rating;
            $ratings = $rate->ratings;
        }

        // Not rating course yet.
        if ($ratings == 0) {
            return null;
        }

        $courserating = new \stdClass();
        $courserating->total = $rating;
        $courserating->count = $ratings;
        $courserating->detail = [];

        if ($ratinglist) {
            foreach ($ratinglist as $key => $one) {
                $detail = new \stdClass();
                $detail->value = $key;
                $detail->count = $one;
                $detail->avg = round($ratingpercents[$key]);
                $courserating->detail[] = $detail;
            }
        }

        return $courserating;
    }

    /**
     * List SQL fragments to get the course ratings.
     */
    public static function sql_map(): array {

        return [
            'ratingfield' => 'AVG(r.rating)',
            'totalfield' => 'COUNT(1)',
            'join' => "INNER JOIN {block_rate_course} r ON r.course = c.id",
        ];
    }
}
