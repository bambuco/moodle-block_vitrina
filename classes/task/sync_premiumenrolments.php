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
 * Sync premium enrolments task
 *
 * @package    block_vitrina
 * @copyright  2024 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_vitrina\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Class sync_premiumenrolments
 *
 * @package    block_vitrina
 * @copyright  2024 David Herney @ BambuCo
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

        $premiumfield = \block_vitrina\controller::get_premiumfield();
        if (!$premiumfield) {
            $trace->output('No course premium field selected');
            return;
        }

        $enrols = $DB->get_records('enrol', ['courseid' => $premiumenrolledcourse]);

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

                // ToDo: por ahora se deja la implementación de arriba que suspende la inscripción directamente en el curso
                // del listado de inscripciones premium. De esa manera, al cambiar la inscripción se dispara el cambio en los
                // cursos premium matriculados por el usuario.
                /*$usersids = array_unique($usersids);

                $params = [
                    'status' => ENROL_USER_ACTIVE,
                ];
                list($selectin, $paramsin) = $DB->get_in_or_equal($usersids, SQL_PARAMS_NAMED, 'usersids');
                $params += $paramsin;

                $sql = "SELECT ue.*, e.courseid FROM {user_enrolments} ue
                                    INNER JOIN mdl_enrol e ON e.id = ue.enrolid
                                    WHERE ue.userid = ' . $selectin . ' AND ue.status = :status";

                $enrolments = $DB->get_records_sql($sql, $params);

                foreach ($enrolments as $enrolment) {

                    if (!isset($tmpcache['premiumcourses'][$enrolment->courseid])) {
                        $tmpcache['premiumcourses'][$enrolment->courseid] = $DB->get_field('customfield_data', 'value', [
                                                                                            'fieldid' => $premiumfield->id,
                                                                                            'instanceid' => $enrolment->courseid,
                                                                                        ]);
                    }

                    $ispremium = $tmpcache['premiumcourses'][$enrolment->courseid];

                    // Only apply to premium courses.
                    if (!$ispremium) {
                        continue;
                    }

                    \block_vitrina\observer::user_change_enrolment($enrol->courseid,
                                $enrolment->userid,
                                \block_vitrina\observer::ACTION_INACTIVE);
                }*/

            }
        }

    }
}
