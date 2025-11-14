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
 * Event observer.
 *
 * @package    block_vitrina
 * @copyright  2024 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_vitrina\local;

/**
 * Events observer.
 *
 * Manage all events related to points and others block elements.
 *
 * @package    block_vitrina
 * @copyright  2024 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Remove user enrolment.
     * @var int
     */
    const ACTION_REMOVE = 0;

    /**
     * Inactive user enrolment.
     * @var int
     */
    const ACTION_INACTIVE = 1;

    /**
     * Re-active user enrolment.
     * @var int
     */
    const ACTION_REACTIVE = 2;

    /**
     * Hook enrol event
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_unenrolled(\core\event\user_enrolment_deleted $event) {

        // The enrolment information is load from the event object because the enrolment is already deleted.
        $enrolment = (object)$event->other['userenrolment'];

        self::user_change_enrolment($enrolment, $event->relateduserid, self::ACTION_REMOVE);
    }

    /**
     * Hook update enrol event
     *
     * @param \core\event\user_enrolment_updated $event
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event) {
        global $DB;
        $enrolment = $DB->get_record('user_enrolments', ['id' => $event->objectid]);

        $action = $enrolment->status == ENROL_USER_ACTIVE ? self::ACTION_REACTIVE : self::ACTION_INACTIVE;

        self::user_change_enrolment($enrolment, $event->relateduserid, $action);
    }

    /**
     * Remove, inactive or re-active user enrolments.
     *
     * @param object $changedenrolment
     * @param int $userid
     * @param int $action
     */
    public static function user_change_enrolment(object $changedenrolment, int $userid, int $action) {
        global $DB;

        $changedenrol = $DB->get_record('enrol', ['id' => $changedenrolment->enrolid]);
        $premiumenrolledcourse = get_config('block_vitrina', 'premiumenrolledcourse');
        $premiumenrolledcourseids = explode(',', $premiumenrolledcourse);

        if (empty($premiumenrolledcourse) || !in_array($changedenrol->courseid, $premiumenrolledcourseids)) {
            return;
        }

        $enrolments = $DB->get_records('user_enrolments', ['userid' => $userid]);
        if (!$enrolments) {
            return;
        }

        $premiumfield = \block_vitrina\local\controller::get_premiumfield();
        if (!$premiumfield) {
            return;
        }

        foreach ($enrolments as $enrolment) {
            // In order not to make changes to the enrolment that is triggering the change.
            if ($enrolment->id == $changedenrolment->id) {
                continue;
            }

            $enrol = $DB->get_record('enrol', ['id' => $enrolment->enrolid, 'enrol' => 'self']);

            if (!$enrol) {
                continue;
            }

            $ispremium = $DB->get_field('customfield_data', 'value', [
                                                                        'fieldid' => $premiumfield->id,
                                                                        'instanceid' => $enrol->courseid,
                                                                    ]);

            // Only make action user if the course is premium.
            if (!$ispremium) {
                continue;
            }

            // Change the self-enrolled in premium courses.
            $selfenrol = enrol_get_plugin('self');

            switch ($action) {
                case self::ACTION_INACTIVE:
                    $selfenrol->update_user_enrol(
                        $enrol,
                        $userid,
                        ENROL_USER_SUSPENDED,
                        $changedenrolment->timestart,
                        $changedenrolment->timeend
                    );
                    break;
                case self::ACTION_REACTIVE:
                    $selfenrol->update_user_enrol(
                        $enrol,
                        $userid,
                        ENROL_USER_ACTIVE,
                        $changedenrolment->timestart,
                        $changedenrolment->timeend
                    );
                    break;
                default:
                    $selfenrol->unenrol_user($enrol, $userid);
            }
        }
    }
}
