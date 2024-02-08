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
require_once($CFG->dirroot . '/local/importpossehluser/locallib.php');




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

    global $DB;

    $result = get_data_from_external_db();


    /**
     * This code retrieves the value of the 'local_importpossehl_importstart' configuration setting from the database.
     * The value is nessessary as the new starting point of a chunk of imported users.
     * If the setting exists, it converts the value to an integer and assigns it to the variable $count.
     * If the setting does not exist, it assigns the value 0 to $count.
     * 
     * @var int $count The integer value of the 'local_importpossehl_importstart' configuration setting.
     */
    $countstring = $DB->get_record('config', ['name' => 'local_importpossehl_importstart']);

    if ($countstring) {
        //change value to integer
        $count = intval($countstring->value);
    } else {
        $count = 0;
    }
    /**
     * Retrieves the import amount from the 'config' table and converts it to an integer.
     * This value is the chunk size of users being imported at once.
     * If the import amount is found, it is stored in the variable $amount.
     * If no import amount is found, the variable $amount is set to 0.
     *
     * @param object $DB The database object used to query the 'config' table.
     * @return void
     */
    $amountstring = $DB->get_record('config', ['name' => 'local_importpossehl_importamount']);
    if ($amountstring) {
        // Convert the value to an integer
        $amount = intval($amountstring->value);
    } else {
        $amount = 0;
    }

    $i = 0;
    $all_emails = array();
    if ($result) {
        $table_header = "username,firstname,lastname,email,profile_field_sidnumber,profile_field_unternehmen,profile_field_userimport,cohort1,suspended";
        $csv_data = $table_header . "\n";



        for ($l = $count; $l < $count + $amount; $l++) {
            if ($result->data_seek($l)) {
                $i++;
                $row = $result->fetch_assoc();
                $username = $row["mail"];
                $firstname = $row["givenname"];
                $lastname = $row["sn"];
                $email = $row["mail"];

                //append email to array
                array_push($all_emails, $email);

                $profileFieldSidnumber = $row["sid"];
                //use email domain as enterprise
                $profileFieldEnterprise = substr(strrchr($email, "@"), 1);
                //use "automatisch" as userimport value to distinguish between manually and automatically imported users
                $profileFieldUserimport = "automatisch";
                //use email domain as cohort
                $cohort1 = $profileFieldEnterprise;
                $suspended = $row["penDisabled"];
                $csv_data .= $username . "," . $firstname . "," . $lastname . "," . $email . "," . $profileFieldSidnumber . "," . $profileFieldEnterprise . "," . $profileFieldUserimport . "," . $cohort1 . "," . $suspended . "\n";
            }
        }

        //get the current value of the 'local_importpossehl_importstart' configuration setting from the database
        //as starting point for the next chunk of imported users
        $record = $DB->get_record('config', ['name' => 'local_importpossehl_importstart']);

        if ($record) {
            // set new value for 'local_importpossehl_importstart' configuration setting
            $record->value = $count + $amount; // Ersetze 'neuerWert' mit dem Wert, den du setzen möchtest

            // update the 'local_importpossehl_importstart' configuration setting in the database
            $DB->update_record('config', $record);
        }
    } else {
        echo "0 results";
    }

    echo ($csv_data);

    //back to normal csv-process, see admin/tool/uploaduser
    /**
     * Calls the possehl_process function to start the import process.
     *
     * @param array $csv_data The CSV data to be processed.
     * @return void
     */
    possehl_process($csv_data);

    //set lastlogin in user table in db to current time for all imported user in this chunk 
    for ($i = 0; $i < count($all_emails); $i++) {
        $user = $DB->get_record('user', array('email' => $all_emails[$i]));
        $user->lastlogin = time();
        $DB->update_record('user', $user);
    }
}
