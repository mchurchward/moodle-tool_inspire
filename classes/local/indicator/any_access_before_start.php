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
 * Any access after the end indicator.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Any access after the end indicator.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class any_access_after_end extends binary {

    public static function get_name() {
        return get_string('target:accessesafterend', 'tool_inspire');
    }

    public static function required_sample_data() {
        return array('user', 'course', 'context');
    }

    public function calculate_sample($sampleid, $samplesorigin, $starttime = false, $endtime = false) {
        global $DB;

        $user = $this->retrieve('user', $sampleid);
        $course = new \tool_analysable\course($this->retrieve('course', $sampleid));

        // Filter by context to use the db table index.
        $context = $this->retrieve('context', $sampleid);
        $select = "userid = :userid AND contextlevel = :contextlevel AND contextinstanceid = :contextinstanceid AND " .
            "timecreated > :end";
        $params = array('userid' => $user->id, 'contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid, 'end' => $course->get_end());
        return $DB->record_exists_select('logstore_standard_log', $select, $params) ? self::get_max_value() : self::get_min_value();
    }
}
