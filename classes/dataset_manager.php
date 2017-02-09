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
class dataset_manager {

    const LABELLED_FILEAREA = 'labelled';
    const UNLABELLED_FILEAREA = 'unlabelled';
    const EVALUATION_FILENAME = 'evaluation.csv';

    /**
     * The model id.
     *
     * @var int
     */
    protected $modelid;

    /**
     * Range processor in use.
     *
     * @var string
     */
    protected $timesplitting;

    /**
     * @var int
     */
    protected $analysableid;

    /**
     * Whether this is a dataset for evaluation or not.
     *
     * @var bool
     */
    protected $evaluation;

    /**
     * Labelled (true) or unlabelled data (false).
     *
     * @var bool
     */
    protected $includetarget;

    /**
     * Simple constructor.
     *
     * @return void
     */
    public function __construct($modelid, $analysableid, $timesplittingcodename, $evaluation = false, $includetarget = false) {
        $this->modelid = $modelid;
        $this->analysableid = $analysableid;
        $this->timesplitting = $timesplittingcodename;
        $this->evaluation = $evaluation;
        $this->includetarget = $includetarget;
    }

    /**
     * Mark the analysable as being analysed.
     *
     * @return void
     */
    public function init_process() {
        $lockkey = 'modelid:' . $this->modelid . '-analysableid:' . $this->analysableid .
            '-timesplitting:' . $this->timesplitting . '-includetarget:' . (int)$this->includetarget;

        // Large timeout as processes may be quite long.
        $lockfactory = \core\lock\lock_config::get_lock_factory('tool_inspire');
        $this->lock = $lockfactory->get_lock($lockkey, WEEKSECS);

        // We release the lock if there is an error during the process.
        \core_shutdown_manager::register_function(array($this, 'release_lock'), array($this->lock));
    }

    /**
     * Store the dataset in the internal file system.
     *
     * @param array $data
     * @return \stored_file
     */
    public function store($data) {

        // Delete previous file if it exists.
        $fs = get_file_storage();
        $filerecord = [
            'component' => 'tool_inspire',
            'filearea' => self::get_filearea($this->includetarget),
            'itemid' => $this->analysableid,
            'contextid' => \context_system::instance()->id,
            'filepath' => '/' . $this->modelid . '/analysable/' . $this->timesplitting . '/',
            'filename' => self::get_filename($this->evaluation)
        ];

        $select = " = {$filerecord['itemid']} AND filepath = :filepath";
        $fs->delete_area_files_select($filerecord['contextid'], $filerecord['component'], $filerecord['filearea'],
            $select, array('filepath' => $filerecord['filepath']));

        // Write all this stuff to a tmp file.
        $filepath = make_request_directory() . DIRECTORY_SEPARATOR . $filerecord['filename'];
        $fh = fopen($filepath, 'w+');
        foreach ($data as $line) {
            fputcsv($fh, $line);
        }
        fclose($fh);

        return $fs->create_file_from_pathname($filerecord, $filepath);
    }

    /**
     * Mark as analysed.
     *
     * @return void
     */
    public function close_process() {
        $this->lock->release();
    }

    public function release_lock(\core\lock\lock $lock) {
        $lock->release();
    }

    public static function get_evaluation_file($modelid, $timesplittingcodename) {
        $fs = get_file_storage();
        // Evaluation data is always labelled.
        return $fs->get_file(\context_system::instance()->id, 'tool_inspire', self::LABELLED_FILEAREA,
            self::convert_to_int($timesplittingcodename), '/' . $modelid . '/timesplitting/' . $timesplittingcodename . '/', self::EVALUATION_FILENAME);
    }

    public static function delete_evaluation_file($modelid, $timesplittingcodename) {
        $fs = get_file_storage();
        if ($file = self::get_evaluation_file($modelid, $timesplittingcodename)) {
            $file->delete();
            return true;
        }

        return false;
    }

    /**
     * Merge multiple files into one.
     *
     * Important! It is the caller responsability to ensure that the datasets are compatible.
     *
     * @param array  $files
     * @param string $filename
     * @param int    $modelid
     * @param string $timesplittingcodename
     * @param bool   $evaluation
     * @param bool   $includetarget
     * @return \stored_file
     */
    public static function merge_datasets(array $files, $modelid, $timesplittingcodename, $evaluation, $includetarget) {

        $tmpfilepath = make_request_directory() . DIRECTORY_SEPARATOR . 'tmpfile.csv';

        // Add headers.
        // We could also do this with a single iteration gathering all files headers and appending them to the beginning of the file
        // once all file contents are merged.
        $varnames = '';
        $analysablesvalues = array();
        foreach ($files as $file) {
            $rh = $file->get_content_file_handle();

            // Copy the var names as they are, all files should have the same var names.
            $varnames = fgetcsv($rh);

            $analysablesvalues[] = fgetcsv($rh);

            // Copy the columns as they are, all files should have the same columns.
            $columns = fgetcsv($rh);
        }

        // Merge analysable values skipping the ones that are the same in all analysables.
        $values = array();
        foreach ($analysablesvalues as $analysablevalues) {
            foreach ($analysablevalues as $varkey => $value) {
                // Sha1 to make it unique.
                $values[$varkey][sha1($value)] = $value;
            }
        }
        foreach ($values as $varkey => $varvalues) {
            $values[$varkey] = implode('|', $varvalues);
        }

        // Start writing to the merge file.
        $wh = fopen($tmpfilepath, 'w');

        fputcsv($wh, $varnames);
        fputcsv($wh, $values);
        fputcsv($wh, $columns);

        // Iterate through all files and add them to the tmp one. We don't want file contents in memory.
        foreach ($files as $file) {
            $rh = $file->get_content_file_handle();

            // Skip headers.
            fgets($rh);
            fgets($rh);
            fgets($rh);

            // Copy all the following lines.
            while ($line = fgets($rh)) {
                fwrite($wh, $line);
            }
            fclose($rh);
        }
        fclose($wh);

        $filerecord = [
            'component' => 'tool_inspire',
            'filearea' => self::get_filearea($includetarget),
            'itemid' => self::convert_to_int($timesplittingcodename),
            'contextid' => \context_system::instance()->id,
            'filepath' => '/' . $modelid . '/timesplitting/' . $timesplittingcodename . '/',
            'filename' => self::get_filename($evaluation)
        ];

        $fs = get_file_storage();

        return $fs->create_file_from_pathname($filerecord, $tmpfilepath);
    }

    public static function get_structured_data(\stored_file $dataset) {

        if ($dataset->get_filearea() !== 'unlabelled') {
            throw new \coding_exception('Sorry, only support for unlabelled data');
        }

        $rh = $dataset->get_content_file_handle();

        // Skip dataset info.
        fgets($rh);
        fgets($rh);

        $calculations = array();

        $headers = fgetcsv($rh);
        // Get rid of the sampleid column name.
        array_shift($headers);

        while ($columns = fgetcsv($rh)) {
            $uniquesampleid = array_shift($columns);
            $calculations[$uniquesampleid] = array_combine($headers, $columns);
        }

        return $calculations;
    }

    /**
     * I know it is not very orthodox...
     *
     * @param string $string
     * @return int
     */
    protected static function convert_to_int($string) {
        $sum = 0;
        for ($i = 0; $i < strlen($string); $i++) {
            $sum += ord($string[$i]);
        }
        return $sum;
    }

    protected static function get_filename($evaluation) {

        if ($evaluation === true) {
            $filename = self::EVALUATION_FILENAME;
        } else {
            // Incremental time, the lock will make sure we don't have concurrency problems.
            $filename = time() . '.csv';
        }

        return $filename;
    }

    protected static function get_filearea($includetarget) {

        if ($includetarget === true) {
            $filearea = self::LABELLED_FILEAREA;
        } else {
            $filearea = self::UNLABELLED_FILEAREA;
        }

        return $filearea;
    }

}
