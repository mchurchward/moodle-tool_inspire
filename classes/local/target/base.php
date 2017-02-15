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
 * Abstract base target.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire\local\target;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base target.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends \tool_inspire\calculable {

    /**
     * This target have linear or discrete values.
     *
     * @return bool
     */
    abstract public function is_linear();

    /**
     * Returns the analyser class that should be used along with this target.
     *
     * @return string
     */
    abstract public function get_analyser_class();

    /**
     * Allows the target to verify that the analysable is a good candidate.
     *
     * This method can be used as a quick way to discard invalid analysables.
     * e.g. Imagine that your analysable don't have students and you need them.
     *
     * @param \tool_inspire\analysable $analysable
     * @param bool $fortraining
     * @return true|string
     */
    abstract public function is_valid_analysable(\tool_inspire\analysable $analysable, $fortraining = true);

    /**
     * Calculates this target for the provided samples.
     *
     * In case there are no values to return or the provided sample is not applicable just return null.
     *
     * @param int $sample
     * @param \tool_inspire\analysable $analysable
     * @return float|null
     */
    abstract protected function calculate_sample($sampleid, \tool_inspire\analysable $analysable);

    public function prediction_actions(\tool_inspire\prediction $prediction) {

        $viewpredictionaction = new \stdClass();
        $params = array('id' => $prediction->get_prediction_data()->id);
        $url = new \moodle_url('/admin/tool/inspire/prediction.php', $params);
        $viewpredictionaction->url = $url->out(false);
        $viewpredictionaction->text = get_string('viewprediction', 'tool_inspire');
        $viewpredictionaction->classes = 'btn btn-secondary';

        return array($viewpredictionaction);
    }

    public function get_display_value($value) {
        return $value;
    }

    public function get_value_style($value) {
        throw new \coding_exception('Please overwrite \tool_inspire\local\target\base::get_value_style');
    }

    /**
     * Callback to execute once a prediction has been returned from the predictions processor.
     *
     * @param int $sampleid
     * @param float|int $prediction
     * @param float $predictionscore
     * @return void
     */
    public function prediction_callback($modelid, $sampleid, $samplecontext, $prediction, $predictionscore) {
        return;
    }

    public function generate_insights($modelid, $samplecontexts) {
        global $CFG;

        foreach ($samplecontexts as $context) {

            $insightinfo = new \stdClass();
            $insightinfo->insightname = $this->get_name();
            $insightinfo->contextname = $context->get_context_name();
            $subject = get_string('insightmessagesubject', 'tool_inspire', $insightinfo);

            if ($context->contextlevel >= CONTEXT_COURSE) {
                // Course level notification.
                $users = get_enrolled_users($context, 'tool/inspire:listinsights');
            } else {
                // TODO Category level and their managers should also get notifications.
                $users = get_admins();
            }

            if (!$coursecontext = $context->get_course_context(false)) {
                $coursecontext = \context_course::instance(SITEID);
            }

            foreach ($users as $user) {

                $message = new \core\message\message();
                $message->component = 'tool_inspire';
                $message->name = 'insights';

                $message->userfrom = get_admin();
                $message->userto = $user;

                $insighturl = new \moodle_url('/admin/tool/inspire/insights.php?modelid=' . $modelid . '&contextid=' . $context->id);
                $message->subject = $subject;
                // Same than the subject.
                $message->contexturlname = $message->subject;
                $message->courseid = $coursecontext->instanceid;

                $message->fullmessage = get_string('insightinfomessage', 'tool_inspire', $insighturl->out());
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = get_string('insightinfomessage', 'tool_inspire', $insighturl->out());
                $message->smallmessage = get_string('insightinfomessage', 'tool_inspire', $insighturl->out());
                $message->contexturl = $insighturl->out(false);


                message_send($message);
            }
        }

    }

    public static function instance() {
        return new static();
    }

    /**
     * Defines a boundary to ignore predictions below the specified prediction score.
     *
     * Value should go from 0 to 1.
     *
     * @return float
     */
    protected function min_prediction_score() {
        // The default minimum discards predictions with a low score.
        return 0.6;
    }

    /**
     * Should the model callback be triggered?
     *
     * @param mixed $class
     * @return bool
     */
    public function triggers_callback($predictedvalue, $predictionscore) {

        $minscore = floatval($this->min_prediction_score());
        if ($minscore < 0) {
            debugging(get_class($this) . ' minimum prediction score is below 0, please update it to a value between 0 and 1.');
        } else if ($minscore > 1) {
            debugging(get_class($this) . ' minimum prediction score is above 1, please update it to a value between 0 and 1.');
        }

        // We need to consider that targets may not have a min score.
        if (!empty($minscore) && floatval($predictionscore) < $minscore) {
            return false;
        }

        if (!$this->is_linear()) {
            if (in_array($predictedvalue, $this->ignored_predicted_classes())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculates the target.
     *
     * Returns an array of values which size matches $sampleids size.
     *
     * Rows with null values will be skipped as invalid by time splitting methods.
     *
     * @param array $sampleids
     * @param \tool_inspire\analysable $analysable
     * @param integer $starttime startime is not necessary when calculating targets
     * @param integer $endtime endtime is not necessary when calculating targets
     * @return array The format to follow is [userid] = scalar|null
     */
    public function calculate($sampleids, \tool_inspire\analysable $analysable) {

        $calculations = [];
        foreach ($sampleids as $sampleid => $unusedsampleid) {
            $calculatedvalue = $this->calculate_sample($sampleid, $analysable);

            if (!is_null($calculatedvalue)) {
                if ($this->is_linear() && ($calculatedvalue > static::get_max_value() || $calculatedvalue < static::get_min_value())) {
                    throw new \coding_exception('Calculated values should be higher than ' . static::get_min_value() .
                        ' and lower than ' . static::get_max_value() . '. ' . $calculatedvalue . ' received');
                } else if (!$this->is_linear() && static::is_a_class($calculatedvalue) === false) {
                    throw new \coding_exception('Calculated values should be one of the target classes (' .
                        json_encode(static::get_classes()) . '). ' . $calculatedvalue . ' received');
                }
            }
            $calculations[$sampleid] = $calculatedvalue;
        }
        return $calculations;
    }
}
