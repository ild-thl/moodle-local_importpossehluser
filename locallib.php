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
 * Internal library of functions for module importpossehluser
 *
 * @package    local
 * @subpackage importpossehluser
 * @copyright   2023 ILD TH Lübeck <dev.ild@th-luebeck.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Retrieves data from the database.
 *
 * @return mysqli_result|bool The result of the database query or false if there is no connection.
 */
function get_data_from_external_db($sql)
{
    global $DB;

    //Dummydata
    /*
Host: sql11.freesqldatabase.com
Database name: sql11675637
Database user: sql11675637
Database password: uBBShqKSc8
Tablename: user
*/
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
    } else {
        echo ("No connection to database. ");
        die();
    }


    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    // Check connection
    if ($conn->connect_error) {
        echo "connection failed";
        die("Connection failed: " . $conn->connect_error);
    }
    //$sql = "SELECT  `givenname`, `sn`, `mail`, `sid` , `penDisabled`, `updatedAt` FROM `" . $tablename . "`";

    $result = $conn->query($sql);
    return $result;
}


/**
 * Retrieves the name of the table to select from.
 *
 * @return string The name of the table to select from.
 */
function get_tablename()
{
    global $DB;
    if ($DB->get_records('config')) {
        $tableobj = $DB->get_record('config', ['name' => 'local_importpossehl_tablename']);
        $tablename = $tableobj->value;
    } else {
        echo ("No connection to database. ");
        die();
    }
    return $tablename;
}


/**
 * Retrieves the timespan in month from plugin settings.
 *
 * @return int The timespan in month.
 */
function get_delete_timespan()
{
    global $DB;
    if ($DB->get_records('config')) {
        $tableobj = $DB->get_record('config', ['name' => 'local_importpossehl_deletetimespan']);
        $timespan = $tableobj->value;
    } else {
        echo ("No connection to database. ");
        die();
    }
    return $timespan;
}


/**
 * Prepares CSV data from a result set.
 *
 * @param mysqli_result $result The result set containing the data.
 * @return string The CSV data formatted as a string.
 */
function prepare_csv_data($result)
{
    if ($result) {
        $table_header = "username,firstname,lastname,email,idnumber,profile_field_unternehmen,profile_field_userimport,cohort1,suspended";
        $csv_data = $table_header . "\n";
        while ($row = $result->fetch_assoc()) {
            $username = $row["mail"];
            $firstname = $row["givenname"];
            $lastname = $row["sn"];
            $email = $row["mail"];
            $idnumber = $row["sid"];
            //$profileFieldEnterprise = " ";
            $profileFieldEnterprise = substr(strrchr($email, "@"), 1);
            $profileFieldUserimport = "automatisch";
            $cohort1 = $profileFieldEnterprise;
            $suspended = $row["penDisabled"];



            //$table_header = "username,firstname,lastname,email,idnumber,profile_field_unternehmen,profile_field_userimport,cohort1,suspended";
            //$csv_data .= $row["mail"] . "," . $row["givenname"] . "," . $row["sn"] . "," . $row["mail"] . "," . $row["sid"] . "," . $maildata . "," . $userinputvalue . "," . $maildata . "\n";
            $csv_data .= $username . "," . $firstname . "," . $lastname . "," . $email . "," . $idnumber . "," . $profileFieldEnterprise . "," . $profileFieldUserimport . "," . $cohort1 . "," . $suspended . "\n";
        }
    } else {
        $csv_data = "0 results";
    }
    return $csv_data;
}


/**
 * Process the data for importing Possehl users.
 *
 * @param mixed $data The data to be processed.
 * @return void
 */
function possehl_process($data): void
{
    // Retrieve optional parameters
    $iid = optional_param('iid', '', PARAM_INT);
    $previewrows = optional_param('previewrows', 10, PARAM_INT);

    // Set form data
    $formdata1 = new stdClass;
    $formdata1->encoding = "UTF-8";
    $formdata1->delimiter_name = "comma";

    // Read the CSV file
    $iid = \csv_import_reader::get_new_iid('uploaduser');
    $cir = new \csv_import_reader($iid, 'uploaduser');
    $content = $data;
    $readcount = $cir->load_csv_content($content, $formdata1->encoding, $formdata1->delimiter_name);
    $csvloaderror = $cir->get_error();
    unset($content);

    // Handle CSV load error
    if (!is_null($csvloaderror)) {
        print_error('csvloaderror', '', $csvloaderror);
    }

    // Process the CSV data
    /**
     * This code initializes a new instance of the \tool_uploaduser\process class and sets various properties of the $formdata object.
     * The $formdata object is used to store user data for uploading Possehl users.
     * The properties of $formdata include user type, password settings, update type, email settings, language, and profile fields.
     * The $iid variable is used to store the value of the iid parameter.
     * The $previewrows property is set to 10.
     * The $submitbutton property is set to "Upload Possehl Users".
     * The $descriptionformat property is set to 1.
     */
    $process = new \tool_uploaduser\process($cir);
    $formdata = new stdClass;
    $formdata->uutype = 2; // 0 = add only new user, 1 = add all, append number to usernames if needed, 2 = add new and update update existing users, 3 = update existing users only
    $formdata->uupasswordnew = 1; // 0 = required field from file, 1 = create password and send it via mail, if nessessary 
    $formdata->uuupdatetype = 1; // 0 = no changes, 1 = overwrite with file, 2 = overwrite with file and defaults, 3 = fill in missing from file and defaults
    $formdata->uupasswordold = 0;
    $formdata->uuallowrenames = 0;
    $formdata->uuallowdeletes = 0;
    $formdata->uuallowsuspends = 1; //  0 = no, 1 = yes
    $formdata->uunoemailduplicates = 1; // 0 = no, 1 = yes
    $formdata->uustandardusernames = 1; // 0 = no, 1 = yes
    $formdata->uubulk = 0;
    $formdata->auth = "manual";
    $formdata->maildisplay = 2; // 0 = no, 1 = yes, 2 = only other users
    $formdata->emailstop = 0;
    $formdata->mailformat = 1; // 0 = text, 1 = html, 2 = both
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


/**
 * Deletes disabled users from the Moodle database based on certain conditions from an external database.
 * Users will be deleted after timespan if they are marked as disabled in external db and have not been 
 * updated in external db in for timespan in month.
 * @param mysqli_result $result The result set of users from the external database.
 * @param int $timespan The specified timespan in month.
 * @return void
 */
function delete_disabled_users_from_external_db_data($result, $timespan)
{
    /**
     * This script is responsible for deleting users from the Moodle database
     * if they meet certain conditions from external db. It retrieves a result set of users from
     * the database and iterates over each user. If a user's 'updatedAt' timestamp
     * is older than a specified timespan and their 'penDisabled' flag is set to 1,
     * the user is deleted from the database. Otherwise, a message indicating that
     * the user is not affected is displayed. The script also handles any exceptions
     * that occur during the deletion process and displays appropriate error messages.
     */
    echo "delete disabled users from external db data \n\n\n ";

    global $DB;
    if ($result) {
        echo ("results<br/>");
        $i = 0;
        while ($row = $result->fetch_assoc()) {
            $i++;
            //echo ("nbr. " . $i . " while <br/>");
            $username = $row['mail'];
            //$email = $row['mail'];
            //$userid = $DB->get_field('user', 'id', array('username' => $email));

            try {
                //$DB->delete_records('user', array('id' => $userid));
                $DB->delete_records('user', array('username' => $username));

                echo "User " . $username . " deleted sucessfully.\n";
            } catch (dml_exception $e) {
                echo "Fehler beim Löschen des Benutzers: " . $row['mail'] . " :" . $e->getMessage() . "<br/>";
            }
        }
        // }
    } else {
        echo "0 results";
    }
}


/**
 * Deletes disabled users from the Moodle database based on a given timespan.
 * Fallback solution if users get deleted on external db.
 * Users will be deleted after timespan if they are disabled and have not logged in for timespan in month.
 * @param int $timespan The timespan in minutes.
 * @return void
 */
function delete_disabled_users_from_moodle_db_data($timespan)
{
    echo "delete disabled users from moodle db data \n\n\n ";

    global $DB;

    //sql call to get all moodle users from moodle table mdl_user with the suspended flag set to 1 and lastlogin older than timespan minutes
    $sql = "SELECT username FROM {user} WHERE suspended = 1 AND lastlogin <= DATE_SUB(NOW(), INTERVAL " . $timespan . " MONTH);";
    try {
        // Führen Sie die Abfrage aus
        $records = $DB->get_records_sql($sql);
        $recordCount = count($records);
        echo "Number of records: " . $recordCount . "\n";
        //var_dump($records);

        foreach ($records as $record) {
            $username = $record->username;
            echo $username . "\n";

            $DB->delete_records('user', array('username' => $username));
            echo "User " . $username . " deleted sucessfully.\n";
        }
    } catch (dml_exception $e) {
        echo 'DB error: ' . $e->getMessage();
    }
}
