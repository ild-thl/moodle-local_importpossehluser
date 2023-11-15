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
 * Proxy lock factory, task to clean history.
 *
 * @package    local_importpossehluser
 * @copyright   2023 ILD TH Lübeck <dev.ild@th-luebeck.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_importpossehluser\task;

//define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use stdClass;
use tool_uploaduser\local\cli_progress_tracker;

require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/uploaduser/locallib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/uploaduser/user_form.php');
require_once($CFG->libdir . '/clilib.php'); 
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/uploaduser/locallib.php');
require_once($CFG->dirroot . '/local/importpossehluser/user_form.php');




if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

/**
 * Proxy lock factory, task to clean history.
 *
 * @package    local_importpossehluser
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/clilib.php');


class possehluser_cron extends \core\task\scheduled_task
{

    public function get_name()
    {
        return get_string('possehluser_cron', 'local_importpossehluser');
    }



    public function execute()
    {
        start_process();
    }
}


function start_process()
{

    global $DB;
    if ($DB->get_records('config')) {
        $serverobj = $DB->get_record('config', ['name' => 'local_importpossehluser_servername']);
        $servername = ($serverobj->value);
        $userobj = $DB->get_record('config', ['name' => 'local_importpossehl_username']);
        $username = $userobj->value;

        $passobj = $DB->get_record('config', ['name' => 'local_importpossehluserdb_pw']);
        $password = $passobj->value;
        $dbbj = $DB->get_record('config', ['name' => 'local_importpossehluserdb_dbname']);
        $dbname = $dbbj->value;
        $tableobj = $DB->get_record('config', ['name' => 'local_importpossehl_tablename']);
        $tablename = $tableobj->value;
    } else {
        echo("No connection to database. ");
        die(); 
    }


    // Create connection
    $conn = new \mysqli($servername, $username, $password, $dbname);
    // Check connection
    if ($conn->connect_error) {
        echo "connection failed";
        //die("Connection failed: " . $conn->connect_error);
    }

    //prepare external data for Moodle-import
    $table_header = "username,firstname,lastname,email,idnumber,profile_field_unternehmen,cohort1";
    $csv_data = $table_header . "\n";

    $sql = "SELECT  `givenname`, `sn`, `mail`, `sid` FROM `" . $tablename . "`";
    $result = $conn->query($sql);
    $i = 0;
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $i++;

            //Emaildomains als Profilfeld unternehmen erstellen
            $maildata = " ";
            $maildata = substr(strrchr($row["mail"], "@"), 1);
            $csv_data .= $row["mail"] . "," . $row["givenname"] . "," . $row["sn"] . "," . $row["mail"] . "," . $row["sid"] . "," . $maildata . "," . $maildata . "\n";
            //$csv_data .= $row["mail"] . "," . $row["givenname"] . "," . $row["sn"] . "," . "noreply@noreply.noreply" . $i . "," . $row["sid"] . "," . $maildata . "\n";
        }
        // }
    } else {
        echo "0 results";
    }

    possehl_process($csv_data);
}



function possehl_process($data): void
{
    $iid         = optional_param('iid', '', PARAM_INT);
    $previewrows = optional_param('previewrows', 10, PARAM_INT);

   
    $formdata1 = new stdClass;
    $formdata1->encoding = "UTF-8";
    $formdata1->delimiter_name = "comma";


    // Read the CSV file.
    $iid = \csv_import_reader::get_new_iid('uploaduser');
    $cir = new \csv_import_reader($iid, 'uploaduser');

    $content = $data;

   
    $readcount = $cir->load_csv_content($content, $formdata1->encoding, $formdata1->delimiter_name);
    //print_object($cir);
    $csvloaderror = $cir->get_error();
    //echo $content;
    unset($content);

    if (!is_null($csvloaderror)) {
        print_error('csvloaderror', '', $csvloaderror);
    }

    $process = new \tool_uploaduser\process($cir);

    $formdata = new stdClass;
    $formdata->uutype = 0;
    $formdata->uupasswordnew = 1;
    $formdata->uuupdatetype = 0;
    $formdata->uupasswordold = 0;
    $formdata->uuallowrenames = 0;
    $formdata->uuallowdeletes = 0;
    $formdata->uuallowsuspends = 1;
    $formdata->uunoemailduplicates = 1;
    $formdata->uustandardusernames = 1;
    $formdata->uubulk = 0;
    //$formdata->username = "%l%f";
    $formdata->auth = "manual";
    $formdata->maildisplay = 2;
    $formdata->emailstop = 0;
    $formdata->mailformat = 1;
    $formdata->maildigest = 0;
    $formdata->autosubscribe = 1;
    $formdata->city = "";
    $formdata->country = "";
    $formdata->timezone = 99;
    $formdata->lang = "de";
    $formdata->description = "";
    $formdata->institution = "";
    $formdata->department = "";
    $formdata->phone1 = "";
    $formdata->phone2 = "";
    $formdata->address = "";
    $formdata->profile_field_cluster = "bitte auswählen";
    $formdata->profile_field_position = "bitte auswählen";
    $formdata->profile_field_geschaeftsbereich = "bitte auswählen";
    $formdata->iid = $iid;
    $formdata->previewrows = 10;
    $formdata->submitbutton = "Upload Possehl Users";
    $formdata->descriptionformat = 1;

    $process->set_form_data($formdata);

    $process->process();
}
