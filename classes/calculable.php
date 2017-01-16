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
 * Calculable dataset items abstract class.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire;

defined('MOODLE_INTERNAL') || die();

/**
 * Calculable dataset items abstract class.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class calculable {

    /**
     * Calculation for all provided samples.
     *
     * @param int $sample
     * @param \tool_inspire\analysable $analysable
     * @param array $data
     * @param int $starttime
     * @param int $endtime
     * @return int[]|float[]
     */
    abstract public function calculate($samples, \tool_inspire\analysable $analysable, $data, $starttime = false, $endtime = false);

    /**
     * Return database records required to perform a calculation, for all course students.
     *
     * Course data and course users data is available in $this->course, $this->studentids and
     * $this->teachers. In self::calculate you can also access $data['course'] and
     * $data['user'], this last one including only students and teachers by default.
     *
     * Please, only load whatever info you really need as all this data will be stored in
     * memory so only include boolean and integer fields, you can also include string fields if you know
     * that they will not contain big chunks of text.
     *
     * You can just return null if:
     * - You need hardly reusable records
     * - You can sort the calculation out easily using a single query fetching data from multiple db tables
     * - You need fields that can be potentially big (varchars and texts)
     *
     * IMPORTANT! No database writes are allowed here as we keep track of all different dataset items requirements.
     *
     * @param \tool_inspire\local\analysable $analysable
     * @return null|array The format to follow is [tablename][id] = stdClass(dbrecord)
     */
    public function get_required_records(\tool_inspire\analysable $analysable) {
        return null;
    }

    /**
     * Returns a name for the indicator.
     *
     * Used as column identificator.
     *
     * Defaults to the indicator class name.
     *
     * @return string
     */
    public static function get_name() {
        return get_called_class();
    }

    /**
     * Returns the number of weeks a time range contains.
     *
     * Useful for calculations that depend on the time range duration.
     *
     * @param int $starttime
     * @param int $endtime
     * @return float
     */
    protected function get_time_range_weeks_number($starttime, $endtime) {
        if ($endtime <= $starttime) {
            throw new \coding_exception('End time timestamp should be greater than start time.');
        }

        $diff = $endtime - $starttime;

        // No need to be strict about DST here.
        return $diff / WEEKSECS;
    }

    /**
     * Limits the calculated value to the minimum and maximum values.
     *
     * @param float $calculatedvalue
     * @return float|null
     */
    protected function limit_value($calculatedvalue) {
        return max(min($calculatedvalue, static::get_max_value()), static::get_min_value());
    }

    /**
     * Classifies the provided value into the provided range according to the ranges predicates.
     *
     * Use:
     * - eq as 'equal'
     * - ne as 'not equal'
     * - lt as 'lower than'
     * - le as 'lower or equal than'
     * - gt as 'greater than'
     * - ge as 'greater or equal than'
     *
     * @param int|float $value
     * @param array $ranges e.g. [ ['lt', 20], ['ge', 20] ]
     * @return void
     */
    protected function classify_value($value, $ranges) {

        // To automatically return calculated values from min to max values.
        $rangeweight = (static::get_max_value() - static::get_min_value()) / (count($ranges) - 1);

        foreach ($ranges as $key => $range) {

            $match = false;

            if (count($range) != 2) {
                throw \coding_exception('classify_value() $ranges array param should contain 2 items, the predicate ' .
                    'e.g. greater (gt), lower or equal (le)... and the value.');
            }

            list($predicate, $rangevalue) = $range;

            switch ($predicate) {
                case 'eq':
                    if ($value == $rangevalue) {
                        $match = true;
                    }
                    break;
                case 'ne':
                    if ($value != $rangevalue) {
                        $match = true;
                    }
                    break;
                case 'lt':
                    if ($value < $rangevalue) {
                        $match = true;
                    }
                    break;
                case 'le':
                    if ($value <= $rangevalue) {
                        $match = true;
                    }
                    break;
                case 'gt':
                    if ($value > $rangevalue) {
                        $match = true;
                    }
                    break;
                case 'ge':
                    if ($value >= $rangevalue) {
                        $match = true;
                    }
                    break;
                default:
                    throw new \coding_exception('Unrecognised predicate ' . $predicate . '. Please use eq, ne, lt, le, ge or gt.');
            }

            // Calculate and return a linear calculated value for the provided value.
            if ($match) {
                $calculatedvalue = round(static::get_min_value() + ($rangeweight * $key), 2);
                if ($calculatedvalue === 0) {
                    $calculatedvalue = $this->get_middle_value();
                }
                return $calculatedvalue;
            }
        }

        throw new \coding_exception('The provided value "' . $value . '" can not be fit into any of the provided ranges, you ' .
            'should provide ranges for all possible values.');
    }
}
