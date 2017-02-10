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
 * Binary classifier target.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\target;

defined('MOODLE_INTERNAL') || die();

/**
 * Binary classifier target.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class binary extends discrete {

    /**
     * Returns the target discrete values.
     *
     * Only useful for targets using discrete values, must be overwriten if it is the case.
     *
     * @return array
     */
    public static final function get_classes() {
        return array(0, 1);
    }

    /**
     * Returns the predicted classes that will be ignored.
     *
     * @return array
     */
    protected function ignored_predicted_classes() {
        // Zero-value class is usually ignored in binary classifiers.
        return array(0);
    }

    public function get_value_style($value) {

        if (!self::is_a_class($value)) {
            throw new \moodle_exception('errorpredictionformat', 'tool_inspire');
        }

        if (in_array($value, $this->ignored_predicted_classes())) {
            // Just in case, if it is ignored the prediction should not even be recorded but if it would, it is ignored now,
            // which should mean that is it nothing serious.
            return 'alert alert-success';
        }

        // Default binaries are danger when prediction = 1.
        if ($value) {
            return 'alert alert-danger';
        }
        return 'alert alert-success';
    }

    protected function classes_description() {
        return array(
            get_string('yes'),
            get_string('no')
        );
    }

}
