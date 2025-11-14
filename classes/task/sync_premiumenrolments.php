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
 * Class sync_premiumenrolments
 *
 * @package    block_vitrina
 * @copyright  2024 2024 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_premiumenrolments extends \core\task\scheduled_task {
    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('syncpremiumenrolmentstask', 'block_vitrina');
    }

    /**
     * Run task for synchronising enrolments.
     */
    public function execute() {
        global $DB;

        $trace = new \text_progress_trace();

        $premiumenrolledcourse = get_config('block_vitrina', 'premiumenrolledcourse');

        if (!$premiumenrolledcourse) {
            $trace->output('No premium course selected');
            return;
        }

        $premiumfield = \block_vitrina\local\controller::get_premiumfield();
        if (!$premiumfield) {
            $trace->output('No course premium field selected');
            return;
        }

        $coursesids = explode(',', $premiumenrolledcourse);
        $enrols = $DB->get_records_list('enrol', 'courseid', $coursesids);

        foreach ($enrols as $enrol) {
            if ($enrol->status == ENROL_USER_ACTIVE) {
                $select = 'enrolid = :enrolid AND status = :status AND timeend < :timeend AND timeend > 0';
                $params = [
                    'enrolid' => $enrol->id,
                    'status' => ENROL_USER_ACTIVE,
                    'timeend' => time(),
                ];

                $premiumenrolments = $DB->get_records_select('user_enrolments', $select, $params);

                // Inactive course premium enrolments when premium membership time end.
                foreach ($premiumenrolments as $enrolment) {
                    $trace->output('Suspended enrolment for user ' . $enrolment->userid);
                    $enrolplugin = enrol_get_plugin($enrol->enrol);
                    $enrolplugin->update_user_enrol($enrol, $enrolment->userid, ENROL_USER_SUSPENDED);
                }
            }
        }
    }
}
