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
 * This file contains the activity completion criteria type class and any
 * supporting functions it may require.
 *
 * @package core_completion
 * @category completion
 * @copyright 2009 Catalyst IT Ltd
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course completion critieria - completion on activity completion
 *
 * @package core_completion
 * @category completion
 * @copyright 2009 Catalyst IT Ltd
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_criteria_activity extends completion_criteria {

    /* @var int Criteria [COMPLETION_CRITERIA_TYPE_ACTIVITY] */
    public $criteriatype = COMPLETION_CRITERIA_TYPE_ACTIVITY;

    /**
     * Finds and returns a data_object instance based on params.
     *
     * @param array $params associative arrays varname=>value
     * @return completion_criteria_activity data_object instance or false if none found.
     */
    public static function fetch($params) {
        $params['criteriatype'] = COMPLETION_CRITERIA_TYPE_ACTIVITY;
        return self::fetch_helper('course_completion_criteria', __CLASS__, $params);
    }

    /**
     * Add appropriate form elements to the critieria form
     *
     * @param moodleform $mform  Moodle forms object
     * @param stdClass $data details of various modules
     */
    public function config_form_display(&$mform, $data = null) {
        $modnames = get_module_types_names();
        $mform->addElement('advcheckbox',
                'criteria_activity['.$data->id.']',
                $modnames[self::get_mod_name($data->module)] . ' - ' . format_string($data->name),
                null,
                array('group' => 1));

        if ($this->id) {
            $mform->setDefault('criteria_activity['.$data->id.']', 1);
        }
    }

    /**
     * Update the criteria information stored in the database
     *
     * @param stdClass $data Form data
     */
    public function update_config(&$data) {
        global $DB;

        if (!empty($data->criteria_activity) && is_array($data->criteria_activity)) {

            $this->course = $data->id;

            // Data comes from advcheckbox, so contains keys for all activities.
            // A value of 0 is 'not checked' whereas 1 is 'checked'.
            foreach ($data->criteria_activity as $activity => $val) {
                // Only update those which are checked.
                if (!empty($val)) {
                    $module = $DB->get_record('course_modules', array('id' => $activity));
                    if (!$module || $module->completion == COMPLETION_TRACKING_NONE || $module->deletioninprogress) {
                        // Never create a criterion that can not be satisfied.
                        continue;
                    }
                    $this->module = self::get_mod_name($module->module);
                    $this->moduleinstance = $activity;
                    $this->id = null;
                    $this->insert();
                }
            }
        }
    }

    /**
     * Delete the course completion criterion for a course module and clean up related completions.
     *
     * Existing course completions that are already marked as completed are preserved.
     *
     * @param int $coursemoduleid The course module id.
     * @param int $courseid The course id.
     * @return bool True if a criterion was found and deleted, false otherwise.
     */
    public static function delete_for_course_module(int $coursemoduleid, int $courseid): bool {
        global $DB;

        // There may be duplicates, so remove every matching criterion.
        $criteriaids = $DB->get_fieldset_select(
            'course_completion_criteria',
            'id',
            'course = :course AND moduleinstance = :cmid AND criteriatype = :type',
            [
                'course' => $courseid,
                'cmid' => $coursemoduleid,
                'type' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
            ]
        );

        if (!empty($criteriaids)) {
            $transaction = $DB->start_delegated_transaction();
            [$insql, $inparams] = $DB->get_in_or_equal($criteriaids, SQL_PARAMS_NAMED);
            $DB->delete_records_select(
                'course_completion_crit_compl',
                "course = :course AND criteriaid $insql",
                ['course' => $courseid] + $inparams
            );
            $DB->delete_records_select('course_completion_criteria', "id $insql", $inparams);
            self::flag_incomplete_for_reaggregation($courseid);
            $transaction->allow_commit();
            return true;
        }

        return false;
    }

    /**
     * Flag the course completions that are not yet complete so they are re-aggregated by cron.
     *
     * Completed course completions (timecompleted set) are never modified.
     *
     * @param int $courseid The course id.
     * @return void
     */
    public static function flag_incomplete_for_reaggregation(int $courseid): void {
        global $DB;

        $DB->set_field_select(
            'course_completions',
            'reaggregate',
            time(),
            'course = :course AND timecompleted IS NULL',
            ['course' => $courseid]
        );
    }

    /**
     * Check if any of the given course modules is used as a course completion criterion.
     *
     * @param int[] $coursemoduleids Array of course module IDs.
     * @param int $courseid The course id.
     * @return bool True if at least one module is a criterion, false otherwise.
     */
    public static function any_is_course_criterion(array $coursemoduleids, int $courseid): bool {
        global $DB;

        if (empty($coursemoduleids)) {
            return false;
        }

        foreach (array_chunk($coursemoduleids, 1000) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $exists = $DB->record_exists_select(
                'course_completion_criteria',
                "course = :course AND criteriatype = :type AND moduleinstance $insql",
                ['course' => $courseid, 'type' => COMPLETION_CRITERIA_TYPE_ACTIVITY] + $inparams
            );
            if ($exists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a course module is used as a course completion criterion.
     *
     * @param int $coursemoduleid The course module id.
     * @param int $courseid The course id.
     * @return bool True if the module is a criterion, false otherwise.
     */
    public static function is_course_criterion(int $coursemoduleid, int $courseid): bool {
        return self::any_is_course_criterion([$coursemoduleid], $courseid);
    }

    /**
     * Delete orphan activity completion criteria where the module is missing, disabled, or being deleted.
     *
     * Existing completed course completions are preserved; incomplete ones are flagged for re-aggregation.
     *
     * @return void
     */
    public static function delete_orphan_criteria(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $sql = "SELECT cr.id, cr.course
                  FROM {course_completion_criteria} cr
             LEFT JOIN {course_modules} cm ON cr.moduleinstance = cm.id
                 WHERE cr.criteriatype = :criteriatype
                   AND (cm.id IS NULL
                        OR cm.completion = :completionnone
                        OR cm.deletioninprogress = 1)";
        $params = [
            'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
            'completionnone' => COMPLETION_TRACKING_NONE,
        ];

        while ($orphans = $DB->get_records_sql_menu($sql, $params, 0, 1000)) {
            $transaction = $DB->start_delegated_transaction();
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($orphans));
            $DB->delete_records_select('course_completion_crit_compl', "criteriaid $insql", $inparams);
            $DB->delete_records_select('course_completion_criteria', "id $insql", $inparams);

            [$courseinsql, $courseinparams] = $DB->get_in_or_equal(array_unique(array_values($orphans)));
            $DB->set_field_select(
                'course_completions',
                'reaggregate',
                time(),
                "timecompleted IS NULL AND course $courseinsql",
                $courseinparams
            );
            $transaction->allow_commit();
        }
    }

    /**
     * Get module instance module type
     *
     * @param int $type Module type id
     * @return string
     */
    public static function get_mod_name($type) {
        static $types;

        if (!is_array($types)) {
            global $DB;
            $types = $DB->get_records('modules');
        }

        return $types[$type]->name;
    }

    /**
     * Gets the module instance from the database and returns it.
     * If no module instance exists this function returns false.
     *
     * @return stdClass|bool
     */
    public function get_mod_instance() {
        global $DB;

        return $DB->get_record_sql(
            "
                SELECT
                    m.*
                FROM
                    {{$this->module}} m
                INNER JOIN
                    {course_modules} cm
                 ON cm.id = {$this->moduleinstance}
                AND m.id = cm.instance
            "
        );
    }

    /**
     * Review this criteria and decide if the user has completed
     *
     * @param completion_completion $completion     The user's completion record
     * @param bool $mark Optionally set false to not save changes to database
     * @return bool
     */
    public function review($completion, $mark = true) {
        global $DB;

        $course = $DB->get_record('course', array('id' => $completion->course));
        $cm = $DB->get_record('course_modules', array('id' => $this->moduleinstance));
        $info = new completion_info($course);

        $data = $info->get_data($cm, false, $completion->userid);

        // If the activity is complete.
        if (in_array($data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
            if ($mark) {
                $completion->mark_complete();
            }

            return true;
        }

        return false;
    }

    /**
     * Return criteria title for display in reports
     *
     * @return string
     */
    public function get_title() {
        return get_string('activitiescompleted', 'completion');
    }

    /**
     * Return a more detailed criteria title for display in reports
     *
     * @return  string
     */
    public function get_title_detailed() {
        global $DB;
        $module = $DB->get_record('course_modules', array('id' => $this->moduleinstance));
        $activity = $DB->get_record($this->module, array('id' => $module->instance));

        return shorten_text(format_string($activity->name, true,
                array('context' => context_module::instance($module->id))));
    }

    /**
     * Return criteria type title for display in reports
     *
     * @return  string
     */
    public function get_type_title() {
        return get_string('activities', 'completion');
    }

    /**
     * Find users who have completed this criteria and mark them accordingly
     */
    public function cron() {
        \core_completion\api::mark_course_completions_activity_criteria();
    }

    /**
     * Return criteria progress details for display in reports
     *
     * @param completion_completion $completion The user's completion record
     * @return array An array with the following keys:
     *     type, criteria, requirement, status
     */
    public function get_details($completion) {
        // Get completion info
        $modinfo = get_fast_modinfo($completion->course);
        $cm = $modinfo->get_cm($this->moduleinstance);

        $details = array();
        $details['type'] = $this->get_title();
        if ($cm->has_view()) {
            $details['criteria'] = html_writer::link($cm->url, $cm->get_formatted_name());
        } else {
            $details['criteria'] = $cm->get_formatted_name();
        }

        // Build requirements
        $details['requirement'] = array();

        if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
            $details['requirement'][] = get_string('markingyourselfcomplete', 'completion');
        } elseif ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            if ($cm->completionview) {
                $modulename = core_text::strtolower(get_string('modulename', $this->module));
                $details['requirement'][] = get_string('viewingactivity', 'completion', $modulename);
            }

            if (!is_null($cm->completiongradeitemnumber)) {
                $details['requirement'][] = get_string('achievinggrade', 'completion');
            }

            if ($cm->completionpassgrade) {
                $details['requirement'][] = get_string('achievingpassinggrade', 'completion');
            }
        }

        $details['requirement'] = implode(', ', $details['requirement']);

        $details['status'] = '';

        return $details;
    }

    /**
     * Return pix_icon for display in reports.
     *
     * @param string $alt The alt text to use for the icon
     * @param array $attributes html attributes
     * @return pix_icon
     */
    public function get_icon($alt, ?array $attributes = null) {
        return new pix_icon('monologo', $alt, "mod_{$this->module}", $attributes);
    }
}
