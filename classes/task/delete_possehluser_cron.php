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


function start_delete_process()
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
        $tableobj = $DB->get_record('config', ['name' => 'local_importpossehl_deletetimespan']);
        $timespan = $tableobj->value;
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
 /*TODO: Zeit ändern in Monate, Minuten ist nur zum Testen */
    $sql = "SELECT * FROM `" . $tablename . "` WHERE penDisabled = 1 AND updatedAt <= CURRENT_TIMESTAMP - INTERVAL '" . $timespan . " minutes';";

    $result = $conn->query($sql);
    var_dump($result);
    if ($result) {
        echo ("results<br/>");
        $i = 0;
        while ($row = $result->fetch_assoc()) {
            $i++;
            echo ("nbr. " . $i . " while <br/>");
            $updatedTimestamp = strtotime($row['updatedAt']);
            $fifteenMinutesAgo = strtotime('-' . $timespan . 'minutes');
            $idnumber = $row['sid'];
            $userid = $DB->get_field('user', 'id', array('idnumber' => $idnumber));

            if ($updatedTimestamp < $fifteenMinutesAgo and $row['penDisabled'] == 1) {
                try {
                    $DB->delete_records('user', array('id' => $userid));
                    echo "Benutzer mit " . $row['mail'] . "erfolgreich gelöscht.<br/>";
                } catch (dml_exception $e) {
                    echo "Fehler beim Löschen des Benutzers: " . $row['mail'] . " :" . $e->getMessage() . "<br/>";
                }
            } else {
                echo ("User mit " . $row['mail'] . "nicht betroffen");
            }
        }
        // }
    } else {
        echo "0 results";
    }
}
