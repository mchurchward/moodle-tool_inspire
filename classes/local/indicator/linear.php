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
 * Abstract linear indicator.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract linear indicator.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class linear extends base {

    /**
     * Set to false to avoid context features to be added as dataset features.
     *
     * @return bool
     */
    protected static function include_averages() {
        return true;
    }

    public static function get_feature_headers() {

        $codename = get_called_class();

        if (static::include_averages()) {
            // The calculated value + context indicators.
            $headers = array($codename, $codename . '/mean');
        } else {
            $headers = array($codename);
        }
        return $headers;
    }

    public function get_display_value($value, $subtype = false) {
        $diff = static::get_max_value() - static::get_min_value();
        return round(100 * ($value - static::get_min_value()) / $diff) . '%';
    }

    public function get_value_style($value, $subtype = false) {
        if ($value < 0) {
            return 'alert alert-warning';
        } else {
            return 'alert alert-info';
        }
    }

    protected function to_features($calculatedvalues) {

        $mean = array_sum($calculatedvalues) / count($calculatedvalues);

        foreach ($calculatedvalues as $sampleid => $calculatedvalue) {
            if (static::include_averages()) {
                $calculatedvalues[$sampleid] = array($calculatedvalue, $mean);
            } else {
                // Basically just convert the scalar to an array of scalars with a single value.
                $calculatedvalues[$sampleid] = array($calculatedvalue);
            }
        }

        // Returns each sample as an array of values, appending the mean to the calculated value.
        return $calculatedvalues;
    }
}
