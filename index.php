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
 * Bulk user import script from external DB using csv-upload-functions from admin/tool/uploaduser
 * 
 * @package    local
 * @subpackage importpossehluser
 * @copyright   2023 ILD TH Lübeck <dev.ild@th-luebeck.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/uploaduser/locallib.php');
require_once($CFG->dirroot . '/local/importpossehluser/user_form.php');
require_once($CFG->dirroot . '/local/importpossehluser/locallib.php');

$iid         = optional_param('iid', '', PARAM_INT);
$previewrows = optional_param('previewrows', 10, PARAM_INT);

core_php_time_limit::raise(60 * 60); // 1 hour should be enough.
raise_memory_limit(MEMORY_HUGE);

admin_externalpage_setup('tooluploaduser'); //Check permissions

$returnurl = new moodle_url('/local/importpossehluser/index.php');
$bulknurl  = new moodle_url('/admin/user/user_bulk.php');

/**
 * Retrieves data from the database.
 *
 * @return mixed The data retrieved from the database.
 */
$tablename = get_tablename();
$timespan =  get_delete_timespan();

//get data from external db
$sql = "SELECT `givenname`, `sn`, `mail`, `sid`, `penDisabled`, `updatedAt` 
    FROM `" . $tablename . "` 
    WHERE (penDisabled = 0 OR (penDisabled = 1 AND updatedAt > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL " . $timespan . " MONTH))) 
    AND `givenname` <> ''
    AND `sn` <> '' 
    AND `mail` REGEXP '^[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$';";

$result = get_data_from_external_db($sql);
if ($result->num_rows > 0) {
    echo "Users to import: " . $result->num_rows . "\n";
} else {
    echo "No users to import";
}

/**
 * Prepares the CSV data for processing.
 *
 * @param array $result The result data to be prepared.
 * @return array The prepared CSV data.
 */
$csv_data = update_existing_user_prepare_csv_data_for_new_user($result);



//back to normal csv-process, see admin/tool/uploaduser
if (empty($iid)) {
    $mform1 = new admin_uploadpossehluser_form1();

    if ($formdata = $mform1->get_data()) {
        print_r($formdata);
        //echo $formdata; 
        $iid = csv_import_reader::get_new_iid('uploaduser');
        $cir = new csv_import_reader($iid, 'uploaduser');

        $content = $csv_data;

        $readcount = $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);
        $csvloaderror = $cir->get_error();
        unset($content);

        if (!is_null($csvloaderror)) {
            print_error('csvloaderror', '', $returnurl, $csvloaderror);
        }
        // Continue to form2.

    } else {
        print_r($formdata);
        print_object($formdata);
        echo $OUTPUT->header();

        echo $OUTPUT->heading_with_help(get_string('uploadusers', 'local_importpossehluser'), 'uploadusers', 'local_importpossehluser');

        $mform1->display();
        print_r($formdata);
        print_object($formdata);

        echo $OUTPUT->footer();
        die;
    }
} else {

    $cir = new csv_import_reader($iid, 'uploaduser');
}

// Test if columns ok.
$process = new \tool_uploaduser\process($cir);
$filecolumns = $process->get_file_columns();

$mform2 = new admin_uploadpossehluser_form2(
    null,
    ['columns' => $filecolumns, 'data' => ['iid' => $iid, 'previewrows' => $previewrows]]
);

// If a file has been uploaded, then process it. 
if ($formdata = $mform2->is_cancelled()) {
    $cir->cleanup(true);
    redirect($returnurl);
} else if ($formdata = $mform2->get_data()) {

    // Print the header.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('uploadusersresult', 'local_importpossehluser'));

    $process->set_form_data($formdata);
    $process->process();

    echo $OUTPUT->box_start('boxwidthnarrow boxaligncenter generalbox', 'uploadresults');
    echo html_writer::tag('p', join('<br />', $process->get_stats()));
    echo $OUTPUT->box_end();

    if ($process->get_bulk()) {
        echo $OUTPUT->continue_button($bulknurl);
    } else {
        echo $OUTPUT->continue_button($returnurl);
    }
    echo $OUTPUT->footer();
    die;
}

// Print the header.
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('uploaduserspreview', 'local_importpossehluser'));

// NOTE: this is JUST csv processing preview, we must not prevent import from here if there is something in the file!!
// this was intended for validation of csv formatting and encoding, not filtering the data!!!!
// we definitely must not process the whole file!

// Preview table data.
$table = new \tool_uploaduser\preview($cir, $filecolumns, $previewrows);

echo html_writer::tag('div', html_writer::table($table), ['class' => 'flexible-wrap']);

// Print the form if valid values are available.
if ($table->get_no_error()) {
    $mform2->display();
}
echo $OUTPUT->footer();
die;
