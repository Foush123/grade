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

require_once('../../../config.php');
require_once($CFG->dirroot.'/grade/report/analytics/lib.php');

$courseid = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$format = optional_param('format', 'html', PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($course->id);
require_capability('gradereport/analytics:view', $context);

$PAGE->set_url('/grade/report/analytics/index.php', array('id' => $courseid));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'gradereport_analytics'));
$PAGE->set_heading(get_string('pluginname', 'gradereport_analytics'));

// Get all enrolled users
$users = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname, u.firstname');

if (empty($users)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('nousers', 'gradereport_analytics'));
    echo $OUTPUT->footer();
    exit;
}

// Get comprehensive analytics data
$analytics = gradereport_analytics_get_comprehensive_data($courseid, array_keys($users));

if ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode($analytics);
    exit;
}

if ($format === 'csv') {
    gradereport_analytics_export_csv($analytics, $course);
    exit;
}

// Render the analytics dashboard
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('gradereport_analytics');
echo $renderer->render_analytics_dashboard($analytics, $course);

echo $OUTPUT->footer();
