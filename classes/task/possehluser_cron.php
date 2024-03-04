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
require_once($CFG->dirroot . '/local/importpossehluser/locallib.php');




if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

/**
 * Proxy lock factory, task to clean history.
 *
 * @package    local_importpossehluser
 * @author     Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @copyright   2023 ILD TH Lübeck <dev.ild@th-luebeck.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/importpossehluser/locallib.php');


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

    /**
     * Updates existing user and 
     * prepares the CSV data for processing.
     *
     * @param array $result The result data to be prepared.
     * @return array The prepared CSV data.
     */
    $csv_data = update_existing_user_prepare_csv_data_for_new_user($result);
    possehl_process($csv_data);

    $result = get_data_from_external_db($sql);
    foreach ($result as $row) {
        //get user by email and check if lastlogin is set
        global $DB;
        $userobj_new = $DB->get_record('user', ['email' => $row["mail"]]);

        if ($userobj_new) {

            //if lastlogin is not set, set to updatedAt from external db
            if ($userobj_new->lastlogin == 0 || empty($userobj_new->lastlogin)) {
                $updatedAt = $row['updatedAt'];
                $userobj_new->lastlogin = strtotime($updatedAt);
                $DB->update_record('user', $userobj_new);
                echo "lastlogin set to " . $userobj_new->lastlogin . " for new import with email " .  $userobj_new->email . "\n";
            }
        }
    }




    //back to normal csv-process, see admin/tool/uploaduser
    /**
     * Calls the possehl_process function to start the import process.
     *
     * @param array $csv_data The CSV data to be processed.
     * @return void
     */
}
