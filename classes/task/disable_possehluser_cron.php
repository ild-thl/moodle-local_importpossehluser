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
 * Task to disable users from external db if marked so.
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



class disable_possehluser_cron extends \core\task\scheduled_task
{

    public function get_name()
    {
        return get_string('disable_possehluser_cron', 'local_importpossehluser');
    }



    public function execute()
    {
        start_deactivate_process();
    }
}


function start_deactivate_process()
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

    $sql = "SELECT mail FROM `" . $tablename . "` WHERE disabled=1";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            try {
                $DB->set_field('user', 'suspended', 1, array('email' => $row["mail"]));
                echo "Benutzerstatus erfolgreich aktualisiert.";
            } catch (dml_exception $e) {
                echo "Fehler bei der Aktualisierung des Benutzerstatus: " . $e->getMessage();
            }
        }
        // }
    } else {
        echo "0 results";
    }

}
