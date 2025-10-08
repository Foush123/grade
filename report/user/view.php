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
 * User report view.
 *
 * @package    gradereport_user
 * @copyright  1999 onwards Martin Dougiamas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/gradelib.php');

// Load course and context
$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);

// Capability check
require_capability('gradereport/user:view', $context);

// Load the report class
require_once(__DIR__.'/classes/report/user.php');
use gradereport_user\report\user as user_report;

// Setup Moodle grade plugin return object (optional)
$gpr = new grade_plugin_return([
    'type' => 'report',
    'plugin' => 'user',
    'courseid' => $course->id
]);

// Create the report instance
$report = new user_report($course, $gpr, $context);

// Output the page header
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'gradereport_user'));

// Print the user grades table
$report->print_table();

// Output the page footer
echo $OUTPUT->footer();
