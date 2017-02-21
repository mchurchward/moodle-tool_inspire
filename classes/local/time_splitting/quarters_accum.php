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
 * Range processor splitting the course in quarters and accumulating data.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\time_splitting;

defined('MOODLE_INTERNAL') || die();

/**
 * Range processor splitting the course in quarters and accumulating data.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quarters_accum extends base {

    public function get_name() {
        return get_string('timesplitting:quartersaccum', 'tool_inspire');
    }

    protected function define_ranges() {
        $duration = floor(($this->analysable->get_end() - $this->analysable->get_start()) / 4);
        return [
            [
                //'start' => $this->analysable->get_start(),
                //'end' => $this->analysable->get_start() + $duration
            //], [
                //'start' => $this->analysable->get_start(),
                //'end' => $this->analysable->get_start() + ($duration * 2)
            //], [
                'start' => $this->analysable->get_start(),
                'end' => $this->analysable->get_start() + ($duration * 3)
            ], [
                'start' => $this->analysable->get_start(),
                'end' => $this->analysable->get_start() + ($duration * 4)
            ]
        ];
    }
}
