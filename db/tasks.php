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
 * Task definition for block_rss_client.
 * @copyright   2023 ILD TH Lübeck <dev.ild@th-luebeck.de>
 * @package   local_importpossehluser
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_importpossehluser\task\possehluser_cron',
        'blocking' => 0,
        'hour' => '1',
        'day' => '*',
        'disabled'=> 1, 
    ],
    [
        'classname' => 'local_importpossehluser\task\possehl_import_start_cron',
        'blocking' => 0,
        'hour' => '12',
        'month' => '*',
        'disabled'=> 1, 
    ],
    [
        'classname' => 'local_importpossehluser\task\delete_possehluser_cron',
        'blocking' => 0,
        'hour' => '1',
        'day' => '*',
        'disabled'=> 1, 
    ],
];