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
 * Task to delete users if marked as disabled in external DB and the
 * deletion timespan indicated in settings.php is reached.
 *
 * @package    local_importpossehluser
 * @copyright   2023 ILD TH Lübeck <dev.ild@th-luebeck.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_importpossehluser\task;

require_once(__DIR__ . '/../../../../config.php');

use stdClass;

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}



/**
 * Represents a scheduled task for deleting Possehl users.
 * Extends the core\task\scheduled_task class.
 */
class delete_possehluser_cron extends \core\task\scheduled_task
{

    public function get_name()
    {
        return get_string('delete_possehluser_cron', 'local_importpossehluser');
    }



    public function execute()
    {
        start_delete_process();
    }
}


/**
 * Selects records from the specified table where penDisabled is 1 and updatedAt is older than the specified timespan.
 *
 * @param string $tablename The name of the table to select from.
 * @param int $timespan The timespan in minutes from plugin settings.
 * @return string The SQL query.
 */
function start_delete_process()
{

    /**
     * This script is responsible for deleting possehluser records from the database based on a specified timespan.
     * It retrieves the necessary configuration values from the 'config' table in the database.
     * If there is no connection to the database, it outputs an error message and terminates the script.
     */

    $tablename = get_tablename();
    $timespan =  get_delete_timespan();

    /**
     * Selects records from the specified table where penDisabled is 1 and updatedAt is older than the specified timespan.
     *
     * @param string $tablename The name of the table to select from.
     * @param int $timespan The timespan in minutes from plugin settings.
     * @return string The SQL query.
     */

    $sql = "SELECT * FROM `" . $tablename . "` WHERE penDisabled = 1 AND updatedAt <= CURRENT_TIMESTAMP - INTERVAL " . $timespan . " MONTH;";
    $result = get_data_from_external_db($sql);
    //var_dump($result);

    //step 1: delete disabled users from external db if they match the criteria
    echo "Data from external db with time interval: \n ";
    delete_disabled_users_from_external_db_data($result, $timespan);


    //step 2 (fallback): delete disabled users from moodle db if they match the criteria
    echo "\n\n\n Data from moodle db: \n ";
    delete_disabled_users_from_moodle_db_data($timespan);
}
