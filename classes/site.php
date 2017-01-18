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

/**
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site implements \tool_inspire\analysable {

    public function get_id() {
        return SYSCONTEXTID;
    }

    public function get_context() {
        return \context_system::instance();
    }

    public function get_usual_required_records() {
        return array();
    }

    public function get_metadata() {
        // TODO We should be very careful with what we include here this is a WIP example.
        return [
            'var1' => 'value1',
            'var2' => 'value2',
        ];
    }

    public function get_start() {
        global $DB;
        return $DB->get_record_sql("SELECT MIN(timecreated) AS time FROM {logstore_standard_log}");
    }

    public function get_end() {
        global $DB;
        return $DB->get_record_sql("SELECT MAX(timecreated) AS time FROM {logstore_standard_log}");
    }
}
