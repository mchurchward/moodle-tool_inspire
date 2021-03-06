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
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');

/**
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course implements \tool_inspire\analysable {

    const MIN_STUDENT_LOGS_PERCENT = 90;

    protected static $instance = null;

    protected $studentroles = [];
    protected $teacherroles = [];

    protected $course = null;
    protected $coursecontext = null;
    protected $starttime = null;
    protected $started = null;
    protected $endtime = null;
    protected $finished = null;

    protected $studentids = [];
    protected $teacherids = [];

    protected $ntotallogs = null;

    /**
     * Course manager constructor.
     *
     * Use self::instance() instead to get a course_manager instance as it returns cached copies.
     *
     * Loads course students and teachers.
     *
     * Let's try to keep this computationally inexpensive.
     *
     * @param int|stdClass $course Course id
     * @param array $studentroles
     * @param array $teacherroles
     * @return void
     */
    public function __construct($course) {

        if (is_scalar($course)) {
            $this->course = get_course($course);
        } else {
            $this->course = $course;
        }

        $this->coursecontext = \context_course::instance($this->course->id);

        $studentroles = get_config('tool_inspire', 'studentroles');
        $teacherroles = get_config('tool_inspire', 'teacherroles');

        if (empty($studentroles) || empty($teacherroles)) {
            // Unexpected, site settings should be set with default values.
            throw new \moodle_exception('errornoroles', 'tool_inspire');
        }

        $this->studentroles = explode(',', $studentroles);
        $this->teacherroles = explode(',', $teacherroles);

        $this->now = time();

        // Get the course users, including users assigned to student and teacher roles at an higher context.
        $this->studentids = $this->get_user_ids($this->studentroles);
        $this->teacherids = $this->get_user_ids($this->teacherroles);
    }

    public function get_id() {
        return $this->course->id;
    }

    public function get_context() {
        if ($this->coursecontext === null) {
            $this->coursecontext = \context_course::instance($this->course->id);
        }
        return $this->coursecontext;
    }

    /**
     * Get the course start timestamp.
     *
     * @return int Timestamp or 0 if has not started yet.
     */
    public function get_start() {
        global $DB;

        if ($this->starttime !== null) {
            return $this->starttime;
        }

        if (empty($this->studentids)) {
            $this->starttime = 0;
            return $this->starttime;
        }

        // The field always exist but may have no valid if the course is created through a sync process.
        if (!empty($this->course->startdate)) {
            $this->starttime = (int)$this->course->startdate;
        } else {
            // Fallback to the first student log.
            list($studentssql, $studentsparams) = $DB->get_in_or_equal($this->studentids, SQL_PARAMS_NAMED);
            $select = 'courseid = :courseid AND userid ' . $studentssql;
            $params = ['courseid' => $this->course->id] + $studentsparams;
            $records = $DB->get_records_select('logstore_standard_log', $select, $params,
                'timecreated ASC', 'id, timecreated', 0, 1);
            if (!$records) {
                // If there are no logs we assume the course has not started yet.
                $this->starttime = 0;
                return $this->starttime;
            }
            $this->starttime  = (int)reset($records)->timecreated;
        }

        return $this->starttime;
    }

    /**
     * Get the course end timestamp.
     *
     * @return int Timestamp, \tool_inspire\analysable::MAX_TIME if we don't know but ongoing and 0 if we can not work it out.
     */
    public function get_end() {
        global $DB;

        if ($this->endtime !== null) {
            return $this->endtime;
        }

        // The enddate field is only available from Moodle 3.2 (MDL-22078).
        if (!empty($this->course->enddate)) {
            $this->endtime = (int)$this->course->enddate;
            return $this->endtime;
        }

        // Not worth trying if we weren't able to determine the startdate, we need to base the calculations below on the
        // course start date.
        $starttime = $this->get_start();
        if (!$starttime) {
            $this->endtime = 0;
            return $this->endtime;
        }

        if (empty($this->studentids)) {
            $this->endtime = 0;
            return $this->endtime;
        }

        if ($this->get_total_logs() === 0) {
            // No way to know if there are no logs.
            $this->endtime = 0;
            return $this->endtime;
        }

        list($filterselect, $filterparams) = $this->get_query_filters();

        $sql = "SELECT COUNT(DISTINCT userid) FROM {logstore_standard_log} " .
            "WHERE $filterselect AND timecreated > :timecreated";
        $params = $filterparams + array('timecreated' => $this->now - (WEEKSECS * 4));
        $ntotallastmonth = $DB->count_records_sql($sql, $params);

        // If more than 1/4 of the students accessed the course in the last 4 weeks we can consider that
        // the course is still ongoing and we can not determine when it will finish.
        if ($ntotallastmonth > count($this->studentids) / 4) {
            $this->endtime = \tool_inspire\analysable::MAX_TIME;
            return $this->endtime;
        }

        // We consider that the course was already finished. We still need to work out a date though,
        // this may be computationally expensive.
        //
        // We will consider the end date the approximate date when
        // the {self::MIN_STUDENT_LOGS_PERCENT}% of the student logs are included.
        //
        // Default to ongoing. This may not be the best choice for courses with not much accesses, we
        // may want to update self::MIN_STUDENT_LOGS_PERCENT in those cases.
        $bestcandidate = \tool_inspire\analysable::MAX_TIME;

        // We also store the percents so we can evaluate the algorithm and constants used.
        $logspercents = [];

        // We continuously try to find out the final course week until we reach 'a week' accuracy.
        list($loopstart, $looptime, $loopend) = $this->update_loop_times($starttime, $this->now);

        do {

            // Add the time filters to the sql query.
            $select = $filterselect . " AND timecreated >= :starttime AND timecreated <= :endtime";
            $params = $filterparams + ['starttime' => $starttime, 'endtime' => $looptime];
            $nlogs = $DB->count_records_select('logstore_standard_log', $select, $params);

            // Move $looptime ahead or behind to find the more accurate end date according
            // to self::MIN_STUDENT_LOGS_PERCENT.
            $logspercents[$looptime] = intval($nlogs / $this->get_total_logs() * 100);
            if ($nlogs !== 0 && $logspercents[$looptime] > self::MIN_STUDENT_LOGS_PERCENT) {
                // We satisfy MIN_STUDENT_LOGS_PERCENT so we have a valid result.

                // Store this value as the best end time candidate if the $looptime is lower than the
                // previous best candidate.
                if ($looptime < $bestcandidate) {
                    $bestcandidate = $looptime;
                }

                // Go back in time to refine the end time. We want as much accuracy as possible.
                list($loopstart, $looptime, $loopend) = $this->update_loop_times($loopstart, $looptime);
            } else {
                // We move ahead in time if we don't reach the minimum percent and if $nlogs === 0 (no logs between
                // starttime and $looptime).
                list($loopstart, $looptime, $loopend) = $this->update_loop_times($looptime, $loopend);
            }

        // We continuously check until we get 'a week' accuracy.
        } while ($loopend - $loopstart > WEEKSECS);

        // We couldn't work out any date with more logs than self::MIN_STUDENT_LOGS_PERCENT, notify the admin running
        // the script about it.
        if ($bestcandidate === \tool_inspire\analysable::MAX_TIME) {
            debugging(json_encode($logspercents));
        }

        $this->endtime = (int)$bestcandidate;
        return $this->endtime;
    }

    public function get_course_data() {
        return $this->course;
    }

    /**
     * Is the course valid to extract indicators from it?
     *
     * @return bool
     */
    public function is_valid() {

        if (!$this->was_started() || !$this->is_finished()) {
            return false;
        }

        return true;
    }

    /**
     * Has the course started?
     *
     * @return bool
     */
    public function was_started() {

        if ($this->started === null) {
            if ($this->get_start() === 0 || $this->now < $this->get_start()) {
                // Not yet started.
                $this->started = false;
            } else {
                $this->started = true;
            }
        }

        return $this->started;
    }

    /**
     * Has the course finished?
     *
     * @return bool
     */
    public function is_finished() {

        if ($this->finished === null) {
            $endtime = $this->get_end();
            if ($endtime === 0 || $this->now < $endtime) {
                // It is not yet finished or no idea when it finishes.
                $this->finished = false;
            } else {
                $this->finished = true;
            }
        }

        return $this->finished;
    }

    /**
     * Returns a list of user ids matching the specified roles in this course.
     *
     * @param array $roleids
     * @return array
     */
    public function get_user_ids($roleids) {

        // We need to index by ra.id as a user may have more than 1 $roles role.
        $records = get_role_users($roleids, $this->coursecontext, true, 'ra.id, u.id AS userid, r.id AS roleid', 'ra.id ASC');

        // If a user have more than 1 $roles role array_combine will discard the duplicate.
        $callable = array($this, 'filter_user_id');
        $userids = array_values(array_map($callable, $records));
        return array_combine($userids, $userids);
    }

    /**
     * Returns the course students.
     *
     * @return stdClass[]
     */
    public function get_students() {
        return $this->studentids;
    }

    /**
     * Returns the total number of student logs in the course
     *
     * @return int
     */
    public function get_total_logs() {
        global $DB;

        if ($this->ntotallogs === null) {
            list($filterselect, $filterparams) = $this->get_query_filters();
            $this->ntotallogs = $DB->count_records_select('logstore_standard_log', $filterselect, $filterparams);
        }

        return $this->ntotallogs;
    }

    public function get_all_activities($activitytype) {
        $modinfo = get_fast_modinfo($this->get_course_data(), -1);
        $instances = $modinfo->get_instances_of($activitytype);

        $instancesbycontext = array();
        foreach ($instances as $instance) {
            $instancesbycontext[$instance->context->id] = $instance;
        }
        return $instancesbycontext;
    }

    public function get_activities($activitytype, $starttime, $endtime, $student = false) {

        // $student may not be available, default to not calculating dynamic data.
        $studentid = -1;
        if ($student) {
            $studentid = $student->id;
        }
        $modinfo = get_fast_modinfo($this->get_course_data(), $studentid);
        $activities = $modinfo->get_instances_of($activitytype);

        $timerangeactivities = array();
        foreach ($activities as $activity) {
            if (!$this->completed_by($activity, $starttime, $endtime)) {
                continue;
            }

            $timerangeactivities[$activity->context->id] = $activity;
        }

        return $timerangeactivities;
    }

    protected function completed_by(\cm_info $activity, $starttime, $endtime) {

        // We can't check uservisible because:
        // - Any activity with available until would not be counted.
        // - Sites may block student's course view capabilities once the course is closed.

        // Students can not view hidden activities by default, this is not reliable 100% but accurate in most of the cases.
        if ($activity->visible === false) {
            return false;
        }

        // We skip activities that were not yet visible or their 'until' was not in this $starttime - $endtime range.
        if ($activity->availability) {
            $info = new \core_availability\info_module($activity);
            $activityavailability = $this->availability_completed_by($info, $starttime, $endtime);
            if ($activityavailability === false) {
                return false;
            } else if ($activityavailability === true) {
                // This activity belongs to this time range.
                return true;
            }
        }

        //// We skip activities in sections that were not yet visible or their 'until' was not in this $starttime - $endtime range.
        $section = $activity->get_modinfo()->get_section_info($activity->sectionnum);
        if ($section->availability) {
            $info = new \core_availability\info_section($section);
            $sectionavailability = $this->availability_completed_by($info, $starttime, $endtime);
            if ($sectionavailability === false) {
                return false;
            } else if ($sectionavailability === true) {
                // This activity belongs to this section time range.
                return true;
            }
        }

        // When the course is using format weeks we use the week's end date.
        $format = course_get_format($activity->get_modinfo()->get_course());
        if ($this->course->format === 'weeks') {
            $dates = $format->get_section_dates($section);

            // We need to consider the +2 hours added by get_section_dates.
            // Avoid $starttime <= $dates->end because $starttime may be the start of the next week.
            if ($starttime < ($dates->end - 7200) && $endtime >= ($dates->end - 7200)) {
                return true;
            } else {
                return false;
            }
        }

        // TODO Think about activities in sectionnum 0.
        if ($activity->sectionnum == 0) {
            return false;
        }

        if (!$this->get_end() || !$this->get_start()) {
            debugging('Activities which due date is in a time range can not be calculated ' .
                'if the course doesn\'t have start and end date', DEBUG_DEVELOPER);
            return false;
        }

        if (!course_format_uses_sections($this->course->format)) {
            // If it does not use sections and there are no availability conditions to access it it is available
            // and we can not magically classify it into any other time range than this one.
            return true;
        }

        // Split the course duration in the number of sections and consider the end of each section the due
        // date of all activities contained in that section.
        $formatoptions = $format->get_format_options();
        if (!empty($formatoptions['numsections'])) {
            $nsections = $formatoptions['numsections'];
        } else {
            // There are course format that use sections but without numsections, we fallback to the number
            // of cached sections in get_section_info_all, not that accurate though.
            $coursesections = $activity->get_modinfo()->get_section_info_all();
            $nsections = count($coursesections);
            if (isset($coursesections[0])) {
                // We don't count section 0 if it exists.
                $nsections--;
            }
        }

        $courseduration = $this->get_end() - $this->get_start();
        $sectionduration = round($courseduration / $nsections);
        $activitysectionenddate = $this->get_start() + ($sectionduration * $activity->sectionnum);
        if ($activitysectionenddate > $starttime && $activitysectionenddate <= $endtime) {
            return true;
        }

        return false;
    }

    protected function availability_completed_by(\core_availability\info $info, $starttime, $endtime) {

        $dateconditions = $info->get_availability_tree()->get_all_children('\availability_date\condition');
        foreach ($dateconditions as $condition) {
            // Availability API does not allow us to check from / to dates nicely, we need to be naughty.
            // TODO Would be nice to expand \availability_date\condition API for this calling a save that
            // does not save is weird.
            $conditiondata = $condition->save();

            if ($conditiondata->d === \availability_date\condition::DIRECTION_FROM &&
                    $conditiondata->t > $endtime) {
                // Skip this activity if any 'from' date is later than the end time.
                return false;

            } else if ($conditiondata->d === \availability_date\condition::DIRECTION_UNTIL &&
                    ($conditiondata->t < $starttime || $conditiondata->t > $endtime)) {
                // Skip activity if any 'until' date is not in $starttime - $endtime range.
                return false;
            } else if ($conditiondata->d === \availability_date\condition::DIRECTION_UNTIL &&
                    $conditiondata->t < $endtime && $conditiondata->t > $starttime) {
                return true;
            }
        }

        // This can be interpreted as 'the activity was available but we don't know if its expected completion date
        // was during this period.
        return null;
    }

    /**
     * Used by get_user_ids to extract the user id.
     *
     * @param \stdClass $record
     * @return int The user id.
     */
    protected function filter_user_id($record) {
        return $record->userid;
    }

    /**
     * Returns the average time between 2 timestamps.
     *
     * @param int $start
     * @param int $end
     * @return array [starttime, averagetime, endtime]
     */
    protected function update_loop_times($start, $end) {
        $avg = intval(($start + $end) / 2);
        return array($start, $avg, $end);
    }

    /**
     * Returns the query and params used to filter {logstore_standard_log} table by this course students.
     *
     * @return array
     */
    protected function get_query_filters() {
        global $DB;

        // Check the amount of student logs in the 4 previous weeks.
        list($studentssql, $studentsparams) = $DB->get_in_or_equal($this->studentids, SQL_PARAMS_NAMED);
        $filterselect = "courseid = :courseid AND userid $studentssql";
        $filterparams = array('courseid' => $this->course->id) + $studentsparams;

        return array($filterselect, $filterparams);
    }
}
