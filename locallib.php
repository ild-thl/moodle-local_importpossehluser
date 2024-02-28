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
function get_data_from_external_db($sql)
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
    try {
        $conn = new mysqli($servername, $username, $password, $dbname);
        // Check connection
        if ($conn->connect_error) {
            echo "connection failed";
            die("Connection failed: " . $conn->connect_error);
        }

        //get all users from external db matching the criteria 
        //(penDisabled = 0 OR (penDisabled = 1 AND updatedAt > CURRENT_TIMESTAMP - INTERVAL " . $timespan . " MONTH)   
        //must have sn and givenname entry 



        $result = $conn->query($sql);
        $conn->close();
        return $result;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
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
    $emails_and_updated = array();

    if ($result) {
        var_dump($result);
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
            //helper variables for checking if user with certain sid or email already exists in Moodle DB
            $sid_in_mdl = FALSE;
            $email_in_mdl = FALSE;

            //check if user with sid already exists in Moodle DB
            try {
                $sid = $row["sid"];
                $user_records_sid = $DB->get_records_sql('SELECT userid FROM {user_info_data} WHERE data = ?', array($sid));
                //progress if  user with unique sid found
                if (count($user_records_sid) == 1) {
                    $user_record_sid = reset($user_records_sid);

                    $sid_in_mdl = TRUE;
                    $userid = $DB->get_field('user', 'id', array('id' => $user_record_sid->userid));
                    echo "User mit sid " . $sid . " in Moodle DB gefunden, hat userid " . $userid . "\n";
                    $userobj_sid = $DB->get_record('user', array('id' => $userid));
                } else if (count($user_records_sid) > 1) {
                    echo "*** More than one user with sid " . $row["sid"] . " found. ***\n";
                    $sid_in_mdl = FALSE;
                } else {
                    $sid_in_mdl = FALSE;
                    echo "User mit sid " . $sid . " not found, email: " . $row["mail"] . "\n";
                }

                //get user object from moodle db with certain userid
            } catch (dml_exception $e) {
                echo 'DB error (sid): ' . $e->getMessage();
            }

            //check if user with email already exists in Moodle DB only if sid not found
            if ($sid_in_mdl == FALSE) {

                //check if user with email already exists in Moodle DB
                try {
                    //get user object from moodle db with certain email
                    $user_records_mail = $DB->get_records('user', ['email' => $row["mail"]]);
                    //progress if  user with unique email found
                    if (count($user_records_mail) == 1) {

                        $user_record_mail = reset($user_records_mail);
                        if ($user_record_mail->email == $row["mail"]) {
                            $userobj_mail = $DB->get_record('user', ['email' => $row["mail"]]);
                            echo "User mit email " . $row["mail"] . " gefunden, hat moodle email  " . $userobj_mail->email . "\n";
                            $userid = $DB->get_field('user', 'id', array('email' => $row["mail"]));
                            $email_in_mdl = TRUE;
                        } else {
                            $email_in_mdl = FALSE;
                            echo "User with email " . $row["mail"] . " not found.\n";
                        }
                    } else if (count($user_records_mail) > 1) {
                        echo "*** More than one user with email " . $row["mail"] . " found. ***\n";
                        $email_in_mdl = FALSE;
                    } else {
                        $email_in_mdl = FALSE;
                        echo "User with email " . $row["mail"] . " not found.\n";
                    }
                } catch (dml_exception $e) {
                    echo 'DB error (email): ' . $e->getMessage();
                }
            }
            //for cleaning up firstname and lastname, unallowed characters
            $removers = array(",", ".", ";");

            //if user with certain sid already exists in Moodle DB, then update user

            if ($sid_in_mdl == TRUE) {

                //clean up firstname and lastname, unallowed characters, update data in user table
                $userobj_sid->firstname = str_replace($removers, "", $row["givenname"]);
                $userobj_sid->lastname = str_replace($removers, "", $row["sn"]);


                //update data in user table using user object
                $userobj_sid->email = $row["mail"];
                $userobj_sid->cohort1 = substr(strrchr($row["mail"], "@"), 1);
                $userobj_sid->suspended = $row["penDisabled"];
                $DB->update_record('user', $userobj_sid);

                //update data in user_info_data table; get ids of fields
                $userimport_id = $DB->get_field_select('user_info_field', 'id', $DB->sql_compare_text('name') . ' = ?', array('userimport'));
                $unternehmen_id = $DB->get_field_select('user_info_field', 'id', $DB->sql_compare_text('name') . ' = ?', array('unternehmen'));
                //$sidnumber_id = $DB->get_field_select('user_info_field', 'id', $DB->sql_compare_text('name') . ' = ?', array('sidnumber'));


                //prepare data
                $enterprise_string = substr(strrchr($row["mail"], "@"), 1);
                $import_string = "automatisch";
                //$sid_nbr = $row["sid"];

                // create or update user_info_data for import-value
                $record = $DB->get_record('user_info_data', array('userid' => $userid, 'fieldid' => $userimport_id));

                // create or update existing record
                if ($record) {
                    // Update existing record
                    $record->data = $import_string;
                    $DB->update_record('user_info_data', $record);
                } else {
                    // Insert new record
                    $record = new stdClass();
                    $record->userid = $userid;
                    $record->fieldid = $userimport_id;
                    $record->data = $import_string;
                    $DB->insert_record('user_info_data', $record);
                }

                // create or update user_info_data for unternehmen
                $record = $DB->get_record('user_info_data', array('userid' => $userid, 'fieldid' => $unternehmen_id));

                if ($record) {
                    // Update existing record
                    $record->data = $enterprise_string;
                    $DB->update_record('user_info_data', $record);
                } else {
                    // Insert new record
                    $record = new stdClass();
                    $record->userid = $userid;
                    $record->fieldid = $unternehmen_id;
                    $record->data = $enterprise_string;
                    $DB->insert_record('user_info_data', $record);
                }

                echo "(sid-update) User " . $row["mail"] . " updated sucessfully via sid.\n";
            }

            //if sid does not exist, but user with certain email exists in Moodle DB
            elseif ($email_in_mdl == TRUE) {
                //clean up firstname and lastname, unallowed characters
                $userobj_mail->firstname = str_replace($removers, "", $row["givenname"]);
                $userobj_mail->lastname = str_replace($removers, "", $row["sn"]);


                //update data in user table using user object
                $userobj_mail->cohort1 = substr(strrchr($row["mail"], "@"), 1);
                $userobj_mail->suspended = $row["penDisabled"];
                $DB->update_record('user', $userobj_mail);

                //update data in user_info_data table
                //get id of specific user_info_field
                $unternehmen_id = $DB->get_field_select('user_info_field', 'id', $DB->sql_compare_text('name') . ' = ?', array('unternehmen'));
                $userimport_id = $DB->get_field_select('user_info_field', 'id', $DB->sql_compare_text('name') . ' = ?', array('userimport'));
                $sidnumber_id = $DB->get_field_select('user_info_field', 'id', $DB->sql_compare_text('name') . ' = ?', array('sidnumber'));

                //prepare data
                $enterprise_string = substr(strrchr($row["mail"], "@"), 1);
                $import_string = "automatisch";
                $sid_nbr = $row["sid"];

                // create or update user_info_data for unternehmen
                $record = $DB->get_record('user_info_data', array('userid' => $userid, 'fieldid' => $unternehmen_id));

                if ($record) {
                    // Update existing record
                    $record->data = $enterprise_string;
                    $DB->update_record('user_info_data', $record);
                } else {
                    // Insert new record
                    $record = new stdClass();
                    $record->userid = $userid;
                    $record->fieldid = $unternehmen_id;
                    $record->data = $enterprise_string;
                    $DB->insert_record('user_info_data', $record);
                }

                // create or update user_info_data for import-value
                $record = $DB->get_record('user_info_data', array('userid' => $userid, 'fieldid' => $userimport_id));

                if ($record) {
                    // create or update existing record
                    $record->data = $import_string;
                    $DB->update_record('user_info_data', $record);
                } else {
                    // Insert new record
                    $record = new stdClass();
                    $record->userid = $userid;
                    $record->fieldid = $userimport_id;
                    $record->data = $import_string;
                    $DB->insert_record('user_info_data', $record);
                }

                // create or update user_info_data for sidnumber
                $record = $DB->get_record('user_info_data', array('userid' => $userid, 'fieldid' => $sidnumber_id));

                if ($record) {
                    // create or update existing record
                    $record->data = $sid_nbr;
                    $DB->update_record('user_info_data', $record);
                } else {
                    // Insert new record
                    $record = new stdClass();
                    $record->userid = $userid;
                    $record->fieldid = $sidnumber_id;
                    $record->data = $sid_nbr;
                    $DB->insert_record('user_info_data', $record);
                }

                echo "(email-update) User " . $row["mail"] . " updated sucessfully via email.\n";
            }
            //if no user data in moodle db, create new user
            else {
                echo "User " . $row["mail"] . " does not exist in Moodle-database, will be created.\n";
                $username = $row["mail"];
                $firstname = str_replace($removers, "", $row["givenname"]);;
                $lastname = str_replace($removers, "", $row["sn"]);
                $email = $row["mail"];
                $updatedAt = $row["updatedAt"];

                //append email to array
                array_push($emails_and_updated, array('email' => $email, 'updatedAt' => $updatedAt));


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
function delete_disabled_users_from_external_db_data($result, $timespan)
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
        foreach ($result as $row) {
            $username = $row['mail'];
            $timestamp_now = time();
            $timestamp_udate_in_db = strtotime($row['updatedAt']);
            $timespan_in_sec = $timespan * 30 * 24 * 60 * 60;
            //$lastupdated_at = strtotime(date("Y-m-d H:i:s")) - strtotime($row['updatedAt']);
            //$last_val_in_timespan = strtotime(date("Y-m-d H:i:s")) - strtotime($timespan . " months");

            //if ($row['penDisabled'] == 1 && $lastupdated_at >= $last_val_in_timespan) {
            if ($row['penDisabled'] == 1 && $timestamp_now - $timestamp_udate_in_db >= $timespan_in_sec) {

                //calculate the difference in weeks
                $last_update_since =  $timestamp_now - $timestamp_udate_in_db;
                $weeks = floor($last_update_since / (60 * 60 * 24 * 7));
                echo "From external DB: User " . $username . " disabled = " . $row['penDisabled'] . " and last updated " . $weeks . " weeks ago, timestamp now: " . $timestamp_now . ", timestamp last_updated: " . $timestamp_udate_in_db . "\n";

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
            } else {
                echo "User " . $username . " will not be deleted.\n";
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
    $sql = "SELECT username, lastlogin, suspended FROM {user} WHERE suspended = 1 AND lastlogin <= DATE_SUB(NOW(), INTERVAL " . $timespan . " MONTH);";
    try {
        $records = $DB->get_records_sql($sql);
        foreach ($records as $record) {
            $username = $record->username;
            $lastlogin = $record->lastlogin;
            $suspended = $record->suspended;
            $timestamp_now = time();
            $timestamp_udate_in_db = strtotime($lastlogin);
            $timespan_in_sec = $timespan * 30 * 24 * 60 * 60;

            //calc difference now - last login
            //$lastlogin_at = strtotime(date("Y-m-d H:i:s")) - strtotime($lastlogin);
            //$last_val_in_timespan = strtotime(date("Y-m-d H:i:s")) - strtotime($timespan . " months");
            //calc value diff - timespan in month




            if ($suspended == 1 && $timestamp_now - $timestamp_udate_in_db >= $timespan_in_sec) {
                echo $record->username . ": suspended = " . $record->suspended . "\n";
                $timestamp_now = strtotime(date("Y-m-d H:i:s"));
                $timestamp_udate_in_db = strtotime($lastlogin);
                //calculate the difference in weeks
                $last_update_since =  $timestamp_now - $timestamp_udate_in_db;

                $weeks = floor($last_update_since / (60 * 60 * 24 * 7));
                echo "From Moodle DB: User " . $username . " disabled and last login " . $weeks . " weeks ago, timestamp now: " . $timestamp_now . ", timestamp last_updated: " . $timestamp_udate_in_db . "\n";
                $DB->delete_records('user', array('username' => $username));
                echo "User " . $username . " deleted sucessfully.\n";
            } else {
                echo "User " . $username . " will not be deleted.\n";
            }
        }
    } catch (dml_exception $e) {
        echo 'DB error: ' . $e->getMessage();
    }
}
