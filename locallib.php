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
 * Retrieves data from the database matching certain criteria (see sql-statement).
 *
 * @return mysqli_result|bool The result of the database query or false if there is no connection.
 */
function get_data_from_external_db()
{
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

    //get all users from external db matching the criteria 
    //(penDisabled = 0 OR (penDisabled = 1 AND updatedAt > CURRENT_TIMESTAMP - INTERVAL " . $timespan . " MONTH)   
    //must have sn and givenname entry 
    $tablename = get_tablename();
    $timespan =  get_delete_timespan();
    $sql = "SELECT `givenname`, `sn`, `mail`, `sid`, `penDisabled`, `updatedAt` 
            FROM `" . $tablename . "` 
            WHERE (penDisabled = 0 OR (penDisabled = 1 AND updatedAt > CURRENT_TIMESTAMP - INTERVAL " . $timespan . " MONTH)) 
            AND `sn` <> '' 
            AND `givenname` <> ''";

    $result = $conn->query($sql);

    $conn->close();
    echo "var dump: <br/>"; 
    var_dump($result);

    //for every entry in result, echo all data
    echo "<br><br><br>result: <br/>"; 
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "givenname: " . $row["givenname"] . " - sn: " . $row["sn"] . " - mail: " . $row["mail"] . " - sid: " . $row["sid"] . " - penDisabled: " . $row["penDisabled"] . " - updatedAt: " . $row["updatedAt"] . "<br>";
        }
    } else {
        echo "0 results";
    }

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
 * Update existing users in moodle db with external db data: 
 * - check if external user id exists in moodle db in added profile field sidnumber
 * - if yes, update user
 * - if no, create new user
 * 
 * - check if external email address exists in moodle db
 * - if yes, update user
 * - if no, create new user
 * 
 * - if user does not exist, prepare data for new user and add to csv data
 * - export csv data (for moodle csv-import-process)
 *
 * @param mysqli_result $result The result set containing user data.
 * @return string The CSV data formatted for updating existing users or creating new users.
 */


function update_existing_user_prepare_csv_data_for_new_user($result)
{
    global $DB;
    if ($result) {
        /*prepare data for moodle import, will be csv-upload file data for moodle csv-import-process
        * create table header
        * 
        * username = moodle username = email
        * firstname = givenname
        * lastname = sn
        * email = mail
        * profile_field_sidnumber = sid
        * profile_field_unternehmen = substring from email
        * profile_field_userimport = "automatisch" -> indicates that user was imported automatically
        * cohort1 = profile_field_unternehmen = substring from email
        * suspended = penDisabled
        */
        $table_header = "username,firstname,lastname,email,profile_field_sidnumber,profile_field_unternehmen,profile_field_userimport,cohort1,suspended";
        $csv_data = $table_header . "\n";
        while ($row = $result->fetch_assoc()) {

            //check if user with sid already exists in Moodle DB
            try {
                $sql = "SELECT userid FROM {user_info_data} WHERE data = " . $row["sid"];
                $user_record = $DB->get_record_sql($sql);
                $userobj = $DB->get_record('user', array('id' => $user_record->userid));
                $userid = $userobj->id;
            } catch (dml_exception $e) {
                echo 'DB error (sid): ' . $e->getMessage();
            }

            //check if user with email already exists in Moodle DB
            try {
                $userobj = $DB->get_record('user', ['email' => $row["mail"]]);
                $useremail = $userobj->email;
            } catch (dml_exception $e) {
                echo 'DB error (email): ' . $e->getMessage();
            }

            //if user with certain sid already exists in Moodle DB, then update user
            if (isset($userid) && !empty($userid) && $row["sid"] == $userid) {
                //update user in moodle db
                $userobj->username = $row["mail"];
                $userobj->firstname = $row["givenname"];
                $userobj->lastname = $row["sn"];
                $userobj->email = $row["mail"];
                //$userobj->idnumber = $row["sid"];
                $userobj->profile_field_unternehmen = substr(strrchr($row["mail"], "@"), 1);
                $userobj->profile_field_userimport = "automatisch";
                $userobj->cohort1 = $userobj->profile_field_unternehmen;
                $userobj->suspended = $row["penDisabled"];
                $DB->update_record('user', $userobj);
                echo "User " . $row["mail"] . " updated sucessfully via sid.\n";
            }

            //if user with certain email already exists in Moodle DB
            elseif ($useremail == $row["mail"]) {
                //$userobj->username = $row["mail"];
                $userobj->firstname = $row["givenname"];
                $userobj->lastname = $row["sn"];
                $userobj->email = $row["mail"];
                $userobj->profile_field_sidnumber = $row["sid"];
                $userobj->profile_field_unternehmen = substr(strrchr($row["mail"], "@"), 1);
                $userobj->profile_field_userimport = "automatisch";
                $userobj->cohort1 = $userobj->profile_field_unternehmen;
                $userobj->suspended = $row["penDisabled"];
                $DB->update_record('user', $userobj);
                echo "User " . $row["mail"] . " updated sucessfully via email.\n";
            }
            //if no user data in moodle db, create new user
            else {
                echo "User " . $row["mail"] . " does not exist in Moodle-database, will be created.\n";
                $username = $row["mail"];
                $firstname = $row["givenname"];
                $lastname = $row["sn"];
                $email = $row["mail"];
                $profileFieldSidnumber = $row["sid"];
                //$profileFieldEnterprise = " ";
                $profileFieldEnterprise = substr(strrchr($email, "@"), 1);
                $profileFieldUserimport = "automatisch";
                $cohort1 = $profileFieldEnterprise;
                $suspended = $row["penDisabled"];
                //$table_header = "username,firstname,lastname,email,profile_field_sidnumber,profile_field_unternehmen,profile_field_userimport,cohort1,suspended";
                $csv_data .= $username . "," . $firstname . "," . $lastname . "," . $email . "," . $profileFieldSidnumber . "," . $profileFieldEnterprise . "," . $profileFieldUserimport . "," . $cohort1 . "," . $suspended . "\n";
            }
        }
    } else {
        $csv_data = "0 results";
    }

    return $csv_data;
}


/**
 * Process the csv data for importing new users.
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
function delete_disabled_users_from_external_db_data($result)
{
    /**
     * This script is responsible for deleting users from the Moodle database
     * if they meet certain conditions from external db. It retrieves a result set of users from
     * the database and iterates over each user. If a user's 'updatedAt' timestamp
     * is older than a specified timespan and their 'penDisabled' flag is set to 1,
     * the user is deleted from the database. Otherwise, a message indicating that
     * the user does not exist in Moodle database is displayed. The script also handles any exceptions
     * that occur during the deletion process and displays appropriate error messages.
     */
    echo "\n\n\n***** Delete user from Moodle-DB using external db-criteria:  *****\n\n\n ";
    echo "timespan for deletion in month = " . get_delete_timespan() . "\n\n";
    global $DB;
    if ($result) {
        $count = $result->num_rows;
        for ($l = 0; $l < $count; $l++) {
            if ($result->data_seek($l)) {
                $row = $result->fetch_assoc();
                $username = $row['mail'];
                $diff = strtotime(date("Y-m-d H:i:s")) - strtotime($row['updatedAt']);
                //calculate the difference in weeks
                $weeks = floor($diff / (60 * 60 * 24 * 7));
                echo "From external DB: User " . $username . " disabled and last updated " . $weeks . " weeks ago\n";

                try {
                    $userExists = $DB->get_record('user', array('username' => $username));

                    if ($userExists) {
                        $DB->delete_records('user', array('username' => $username));
                        echo "User " . $username . " deleted sucessfully.\n";
                    } else {
                        echo "User " . $username . " does not exist in Moodle-database.\n";
                    }
                } catch (dml_exception $e) {
                    echo "Fehler beim Löschen des Benutzers: " . $row['mail'] . " :" . $e->getMessage() . "<br/>";
                }
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
 * Users will be deleted after timespan if they are disabled and have not logged in for timespan in month
 * using Moodle database data.
 * @param int $timespan The timespan in month.
 * @return void
 */
function delete_disabled_users_from_moodle_db_data($timespan)
{
    echo "\n\n\n***** Delete user from Moodle-DB using internal db-criteria: *****\n\n\n ";

    global $DB;

    //sql call to get all moodle users from moodle table mdl_user with the suspended flag set to 1 and lastlogin older than timespan in month
    $sql = "SELECT username, lastlogin FROM {user} WHERE suspended = 1 AND lastlogin <= DATE_SUB(NOW(), INTERVAL " . $timespan . " MONTH);";
    try {
        $records = $DB->get_records_sql($sql);
        foreach ($records as $record) {
            $username = $record->username;
            $lastlogin = $record->lastlogin;
            //calc difference in weeks from now to last login
            $diff = strtotime(date("Y-m-d H:i:s")) - strtotime($lastlogin);

            //calculate the difference in weeks
            $weeks = floor($diff / (60 * 60 * 24 * 7));
            echo "From Moodle DB: User " . $username . " disabled and last login " . $weeks . " weeks ago\n";
            $DB->delete_records('user', array('username' => $username));
            echo "User " . $username . " deleted sucessfully.\n";
        }
    } catch (dml_exception $e) {
        echo 'DB error: ' . $e->getMessage();
    }
}
