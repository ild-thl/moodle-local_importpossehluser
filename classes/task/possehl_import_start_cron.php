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

class possehl_import_start_cron extends \core\task\scheduled_task
{

    public function get_name()
    {
        return get_string('possehl_import_start_cron', 'local_importpossehluser');
    }



    public function execute()
    {
        start_import_process();
    }
}


function start_import_process()
{
    /*
    //$dateiPfad = __DIR__ . '/local/importpossehluser/classes/task/count.txt';
    $dateiPfad = __DIR__ . '/count.txt';

    // Überprüfen, ob die Datei existiert
    if (file_exists($dateiPfad)) {
        // Datei öffnen
        $count = file_get_contents($dateiPfad);
    } else {
        $count = 0;
    }

    echo ("Import gestartet bei Nr. " . $count);
*/

    global $DB;

    if ($DB->get_records('config')) {
        //get Server-Connection-Params from DB -> saved in settings.php
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

        $countstring = $DB->get_record('config', ['name' => 'local_importpossehl_importstart']);

        if ($countstring) {
            // Wandle den Wert in einen Integer um
            $count = intval($countstring->value);

            // Jetzt kannst du $intValue verwenden, das ein Integer ist
        } else {
            $count = 0;
            // Handle den Fall, dass kein Eintrag gefunden wurde
        }
        $amountstring = $DB->get_record('config', ['name' => 'local_importpossehl_importamount']);
        if ($amountstring) {
            // Wandle den Wert in einen Integer um
            $amount = intval($amountstring->value);

            // Jetzt kannst du $intValue verwenden, das ein Integer ist
        } else {
            $amount = 0;
            // Handle den Fall, dass kein Eintrag gefunden wurde
        }
    } else {
        echo ("No connection to database. ");
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
    //$possehl_tablename = $possehl_tablename; 
    //$possehl_tablename = "tixxt_user";
    $sql = "SELECT  `givenname`, `sn`, `mail`, `sid` FROM `" . $tablename . "`";
    $result = $conn->query($sql);
    $i = 0;
    if ($result) {

        for ($l = $count; $l < $count + $amount; $l++) {
            if ($result->data_seek($l)) {
                $i++;

                /* Eine einzelne Zeile abrufen */
                $row = $result->fetch_assoc();
                //Emaildomains als Profilfeld unternehmen erstellen
                $maildata = " ";
                $maildata = substr(strrchr($row["mail"], "@"), 1);
                $csv_data .= $row["mail"] . "," . $row["givenname"] . "," . $row["sn"] . "," . $row["mail"] . "," . $row["sid"] . "," . $maildata . "," . $maildata . "\n";
            }
        }
        $dateiPfad = __DIR__ . '/count.txt';

        // Der zu speichernde Wert
        //$wert = $count + 100;
        //save new val in db
        $new_count = $count + $amount;
        // Zunächst holst du den aktuellen Eintrag
        $record = $DB->get_record('config', ['name' => 'local_importpossehl_importstart']);

        if ($record) {
            // Setze den neuen Wert
            $record->value = $count + $amount; // Ersetze 'neuerWert' mit dem Wert, den du setzen möchtest

            // Aktualisiere den Eintrag in der Datenbank
            $DB->update_record('config', $record);
        }

        // Den Wert in die Datei schreiben
        //file_put_contents($dateiPfad, $wert);
    } else {
        echo "0 results";
    }

    echo ($csv_data);
    initial_possehl_process($csv_data);
}



function initial_possehl_process($data): void
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
