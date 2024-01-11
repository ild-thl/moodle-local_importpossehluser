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
 * Link to CSV user upload
 *
 * @package    local
 * @subpackage importpossehluser
 * @copyright   2023 ILD TH Lübeck <dev.ild@th-luebeck.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Ensure the configurations for this site are set
if ($hassiteconfig) {

	// Create the new settings page
	// - in a local plugin this is not defined as standard, so normal $settings->methods will throw an error as
	// $settings will be NULL
	$settings = new admin_settingpage('local_importpossehluser', 'Import possehl user');

	// Create 
	$ADMIN->add('localplugins', $settings);

	// Add a setting field to the settings for this page
	$settings->add(new admin_setting_configtext(

		// This is the reference you will use to your configuration
		'local_importpossehluser_servername',

		// This is the friendly title for the config, which will be displayed
		'Server-URL',

		// This is helper text for this config field
		'Server URL für DB-Verbindung',

		// This is the default value
		'',

		// This is the type of Parameter this config is
		PARAM_TEXT

	));

	// Add a setting field to the settings for this page
	$settings->add(new admin_setting_configtext(

		// This is the reference you will use to your configuration
		'local_importpossehluserdb_dbname',

		// This is the friendly title for the config, which will be displayed
		'DB-Name',

		// This is helper text for this config field
		'Datenbank-Name',

		// This is the default value
		'',

		// This is the type of Parameter this config is
		PARAM_TEXT

	));

	// Add a setting field to the settings for this page
	$settings->add(new admin_setting_configtext(

		// This is the reference you will use to your configuration
		'local_importpossehl_username',

		// This is the friendly title for the config, which will be displayed
		'Username',

		// This is helper text for this config field
		'Username für DB-Connection',

		// This is the default value
		'',

		// This is the type of Parameter this config is
		PARAM_TEXT

	));

	// Add a setting field to the settings for this page
	$settings->add(new admin_setting_configtext(

		// This is the reference you will use to your configuration
		'local_importpossehl_tablename',

		// This is the friendly title for the config, which will be displayed
		'Tablename',

		// This is helper text for this config field
		'Tablename für DB-Connection',

		// This is the default value
		'',

		// This is the type of Parameter this config is
		PARAM_TEXT

	));

	// Add a setting field to the settings for this page
	$settings->add(new admin_setting_configtext(

		// This is the reference you will use to your configuration
		'local_importpossehl_importstart',

		// This is the friendly title for the config, which will be displayed
		'Startwert Import',

		// This is helper text for this config field
		'Startwert, bei dem der Import in kleinen Schritten ausgeführt wird',

		// This is the default value
		'0',

		// This is the type of Parameter this config is
		PARAM_TEXT

	));

	// Add a setting field to the settings for this page
	$settings->add(new admin_setting_configtext(

		// This is the reference you will use to your configuration
		'local_importpossehl_importamount',

		// This is the friendly title for the config, which will be displayed
		'Usermenge',

		// This is helper text for this config field
		'Menge an Usern, die gleichzeitig importiert werden sollen',

		// This is the default value
		'100',

		// This is the type of Parameter this config is
		PARAM_TEXT

	));

	$settings->add(new admin_setting_configtext(

		// This is the reference you will use to your configuration
		'local_importpossehl_deletetimespan',

		// This is the friendly title for the config, which will be displayed
		'Löschzeitraum',

		// This is helper text for this config field
		'Zeitraum, nachdem in Logopak-DB penDisabled gesetzte User gelöscht werden sollen (in Monaten)',

		// This is the default value
		'6',

		// This is the type of Parameter this config is
		PARAM_TEXT

	));


	// Add a setting field to the settings for this page
	$settings->add(new admin_setting_configpasswordunmask(

		// This is the reference you will use to your configuration
		'local_importpossehluserdb_pw',

		// This is the friendly title for the config, which will be displayed
		'Passwort',

		// This is helper text for this config field
		'Passwort für DB-Connection',

		// This is the default value
		'',


		PARAM_TEXT

	));
}
