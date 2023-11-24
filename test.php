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
 * Bulk user import script from external DB using csv-upload-functions from admin/tool/uploaduser
 * 
 * @package    local
 * @subpackage importpossehluser
 * @copyright   2023 ILD TH Lübeck <dev.ild@th-luebeck.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/uploaduser/locallib.php');
require_once($CFG->dirroot . '/local/importpossehluser/user_form.php');

$iid         = optional_param('iid', '', PARAM_INT);
$previewrows = optional_param('previewrows', 10, PARAM_INT);

core_php_time_limit::raise(60 * 60); // 1 hour should be enough.
raise_memory_limit(MEMORY_HUGE);

admin_externalpage_setup('tooluploaduser'); //Check, ob man berechtigt ist

$returnurl = new moodle_url('/local/importpossehluser/index.php');
$bulknurl  = new moodle_url('/admin/user/user_bulk.php');

global $DB;
$data = "";

//Dummydata
/*
$servername = "sql11.freesqldatabase.com";
$username =  "sql11654319";
$password =  "c4hRbu1DC7";
$dbname = "sql11654319";
$tablename = "user";
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


// Erstellen der SQL-Abfrage, um Spalteninformationen zu erhalten
$sql = "SHOW COLUMNS FROM `" . $tablename . "`";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Durchlaufen der Ergebnisse und Ausgabe des Spaltennamens
    while($row = $result->fetch_assoc()) {
        echo "Spaltenname: " . $row["Field"] . "<br>";
    }
} else {
    echo "Keine Spalten gefunden.";
}
