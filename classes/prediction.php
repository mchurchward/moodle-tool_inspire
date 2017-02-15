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
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prediction {

    private $prediction;

    private $sampledata;

    private $calculations = array();

    public function __construct($prediction, $sampledata) {
        global $DB;

        if (is_scalar($prediction)) {
            $prediction = $DB->get_record('tool_inspire_predictions', array('id' => $prediction), '*', MUST_EXIST);
        }
        $this->prediction = $prediction;

        $this->sampledata = $sampledata;

        $this->format_calculations();
    }

    public function get_prediction_data() {
        return $this->prediction;
    }

    public function get_sample_data() {
        return $this->sampledata;
    }

    public function get_calculations() {
        return $this->calculations;
    }

    private function format_calculations() {

        $calculations = json_decode($this->prediction->calculations, true);

        foreach ($calculations as $featurename => $value) {

            list($indicatorclass, $subtype) = $this->parse_feature_name($featurename);

            if ($indicatorclass === 'range') {
                // Time range indicators don't belong to any indicator class, we don't store them.
                continue;
            } else if (!\tool_inspire\manager::is_valid($indicatorclass, '\tool_inspire\local\indicator\base')) {
                throw new \moodle_exception('errorpredictionformat', 'tool_inspire');
            }

            $this->calculations[$featurename] = new \stdClass();
            $this->calculations[$featurename]->subtype = $subtype;
            $this->calculations[$featurename]->indicator = \tool_inspire\manager::get_indicator($indicatorclass);
            $this->calculations[$featurename]->value = $value;
        }
    }

    private function parse_feature_name($featurename) {

        $indicatorclass = $featurename;
        $subtype = false;

        // Some indicator result in more than 1 feature, we need to see which feature are we dealing with.
        $separatorpos = strpos($featurename, '/');
        if ($separatorpos !== false) {
            $subtype = substr($featurename, ($separatorpos + 1));
            $indicatorclass = substr($featurename, 0, $separatorpos);
        }

        return array($indicatorclass, $subtype);
    }

}
