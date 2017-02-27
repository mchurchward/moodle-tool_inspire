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
 * Model logs table class.
 *
 * @package    tool_inspire
 * @copyright  2017 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\output;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Model logs table class.
 *
 * @package    tool_inspire
 * @copyright  2017 David Monllao {@link http://www.davidmonllao.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_logs extends \table_sql {

    /**
     * @var int
     */
    protected $modelid = null;

    /**
     * Sets up the table_log parameters.
     *
     * @param string $uniqueid unique id of form.
     * @param int $modelid model id
     */
    public function __construct($uniqueid, $modelid) {
        global $PAGE;

        parent::__construct($uniqueid);

        $this->modelid = $modelid;

        $this->set_attribute('class', 'modellog generaltable generalbox');
        $this->set_attribute('aria-live', 'polite');

        $this->define_columns(array('time', 'target', 'indicators', 'timesplitting', 'score', 'result', 'usermodified'));
        $this->define_headers(array(
            get_string('time'),
            get_string('target', 'tool_inspire'),
            get_string('indicators', 'tool_inspire'),
            get_string('timesplittingmethod', 'tool_inspire'),
            get_string('score', 'tool_inspire'),
            get_string('resultinfo', 'tool_inspire'),
            get_string('fullnameuser'),
        ));
        $this->pageable(true);
        $this->collapsible(false);
        $this->sortable(false);
        $this->is_downloadable(false);

        $this->define_baseurl($PAGE->url);
    }

    /**
     * Generate the time column.
     *
     * @param stdClass $log log data.
     * @return string HTML for the time column
     */
    public function col_time($log) {
        $recenttimestr = get_string('strftimerecent', 'core_langconfig');
        return userdate($log->timecreated, $recenttimestr);
    }

    /**
     * Generate the target column.
     *
     * @param stdClass $log log data.
     * @return string HTML for the target column
     */
    public function col_target($log) {
        $target = \tool_inspire\manager::get_target($log->target);
        return $target->get_name();
    }

    /**
     * Generate the indicators column.
     *
     * @param stdClass $log log data.
     * @return string HTML for the indicators column
     */
    public function col_indicators($log) {
        $indicatorclasses = json_decode($log->indicators);
        $indicators = array();
        foreach ($indicatorclasses as $indicatorclass) {
            $indicator = \tool_inspire\manager::get_indicator($indicatorclass);
            if ($indicator) {
                $indicators[] = $indicator->get_name();
            } else {
                debugging('Can\'t load ' . $indicatorclass . ' indicator', DEBUG_DEVELOPER);
            }
        }
        return '<ul><li>' . implode('</li><li>', $indicators) . '</li></ul>';
    }

    /**
     * Generate the context column.
     *
     * @param stdClass $log log data.
     * @return string HTML for the context column
     */
    public function col_timesplitting($log) {
        $timesplitting = \tool_inspire\manager::get_time_splitting($log->timesplitting);
        return $timesplitting->get_name();
    }

    /**
     * Generate the score column.
     *
     * @param stdClass $log log data.
     * @return string HTML for the score column
     */
    public function col_score($log) {
        return strval(round($log->score * 100, 2)) . '%';
    }

    /**
     * Generate the score column.
     *
     * @param stdClass $log log data.
     * @return string HTML for the score column
     */
    public function col_result($log) {
        return $log->result;
    }

    /**
     * Generate the usermodified column.
     *
     * @param stdClass $log log data.
     * @return string HTML for the usermodified column
     */
    public function col_usermodified($log) {
        $user = \core_user::get_user($log->usermodified);
        return fullname($user);
    }

    /**
     * Query the logs table. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
		global $DB;

        $total = $DB->count_records('tool_inspire_models_log', array('modelid' => $this->modelid));
        $this->pagesize($pagesize, $total);

        $this->rawdata = $DB->get_records('tool_inspire_models_log', array('modelid' => $this->modelid), 'timecreated DESC', '*',
            $this->get_page_start(), $this->get_page_size());

        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars($total > $pagesize);
        }
    }
}
