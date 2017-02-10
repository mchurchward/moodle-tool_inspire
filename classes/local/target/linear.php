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
 * Linear values target.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\target;

defined('MOODLE_INTERNAL') || die();

/**
 * Linear values target.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class linear extends base {

    public function is_linear() {
        return true;
    }

    public function get_value_style($value) {

        // This is very generic, targets will probably be interested in overwriting this.
        $diff = static::get_max_value() - static::get_min_value();
        if (($value - static::get_min_value()) / $diff >= 0.5) {
            return 'alert alert-success';
        }
        return 'alert alert-danger';
    }

    /**
     * Gets the maximum value for this target
     *
     * @return float
     */
    protected static function get_max_value() {
        // Coding exception as this will only be called if this target have linear values.
        throw new \coding_exception('Overwrite get_max_value() and return the target max value');
    }

    /**
     * Gets the minimum value for this target
     *
     * @return float
     */
    protected static function get_min_value() {
        // Coding exception as this will only be called if this target have linear values.
        throw new \coding_exception('Overwrite get_min_value() and return the target min value');
    }

    /**
     * Returns the minimum value that triggers the callback.
     *
     * @return float
     */
    protected function get_callback_boundary() {
        // Coding exception as this will only be called if this target have linear values.
        throw new \coding_exception('Overwrite get_callback_boundary() and return the min value that ' .
            'should trigger the callback');
    }
}
