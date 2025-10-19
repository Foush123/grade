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
require_once($CFG->dirroot.'/grade/report/grader/lib.php');
require_once($CFG->dirroot.'/grade/report/analytics/lib.php');

$courseid = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$edit = optional_param('edit', -1, PARAM_BOOL);
$sortitemid = optional_param('sortitemid', 0, PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHA);
$target = optional_param('target', '', PARAM_ALPHA);
$toggle = optional_param('toggle', null, PARAM_INT);
$toggle_type = optional_param('type', 0, PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($course->id);
require_capability('gradereport/grader:view', $context);

$PAGE->set_url('/grade/report/grader_analytics/index.php', array('id' => $courseid));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'gradereport_grader') . ' - Analytics Enhanced');
$PAGE->set_heading(get_string('pluginname', 'gradereport_grader') . ' - Analytics Enhanced');

// Include the original grader report functionality
$gpr = new grade_plugin_return(array(
    'type' => 'report',
    'plugin' => 'grader',
    'course' => $course,
    'page' => $page
));

$report = new grade_report_grader($courseid, $gpr, $context, $page, $sortitemid, 'ASC');

// Load users and grades
$report->load_users();
$report->load_final_grades();

// Get analytics data for all users
$userids = array_keys($report->users);
$analytics_data = gradereport_analytics_get_comprehensive_data($courseid, $userids);

// Render the enhanced grader report
echo $OUTPUT->header();

// Add analytics columns to the report
$analytics_columns = array(
    'h5p_interactions' => 'H5P Interactions',
    'video_completion' => 'Video Completion %',
    'scorm_score' => 'SCORM Score',
    'sessions_attended' => 'Live Sessions',
    'punctuality' => 'Punctuality %',
    'attendance_rate' => 'Attendance %',
    'badges_count' => 'Badges',
    'competency_evidence' => 'Competency Evidence',
    'deadline_adherence' => 'Deadline Adherence %',
    'ta_rating' => 'TA Rating'
);

echo html_writer::start_tag('div', array('class' => 'analytics-enhanced-grader'));
echo html_writer::tag('h2', 'Enhanced Grader Report with Analytics', array('class' => 'analytics-title'));

// Create analytics summary
echo html_writer::start_tag('div', array('class' => 'analytics-summary'));
echo html_writer::tag('h3', 'Analytics Summary');

$summary_data = array();
foreach ($analytics_data as $userid => $analytics) {
    $user = $report->users[$userid];
    $summary_data[] = array(
        'userid' => $userid,
        'fullname' => fullname($user),
        'email' => $user->email,
        'h5p_interactions' => isset($analytics['interactive_content']['h5p']) ? 
            array_sum(array_column($analytics['interactive_content']['h5p'], 'interaction_count')) : 0,
        'video_completion' => isset($analytics['interactive_content']['video']) ? 
            round(array_sum(array_column($analytics['interactive_content']['video'], 'completion_rate')) / count($analytics['interactive_content']['video']), 2) : 0,
        'scorm_score' => isset($analytics['interactive_content']['scorm']) ? 
            round(array_sum(array_column($analytics['interactive_content']['scorm'], 'avg_score')) / count($analytics['interactive_content']['scorm']), 2) : 0,
        'sessions_attended' => isset($analytics['live_sessions']) ? 
            array_sum(array_column(array_column($analytics['live_sessions'], 'sessions_attended'), 'sessions_attended')) : 0,
        'attendance_rate' => isset($analytics['attendance']) ? 
            round(array_sum(array_column($analytics['attendance'], 'attendance_rate')) / count($analytics['attendance']), 2) : 0,
        'badges_count' => isset($analytics['badges']) ? count($analytics['badges']) : 0,
        'competency_evidence' => isset($analytics['competencies']) ? 
            array_sum(array_column($analytics['competencies'], 'evidence_count')) : 0,
        'deadline_adherence' => $analytics['behavioral']['deadline_adherence'] ?? 0,
        'ta_rating' => isset($analytics['ta_evaluation']) ? 
            round(array_sum(array_column($analytics['ta_evaluation'], 'avg_ta_rating')) / count($analytics['ta_evaluation']), 2) : 0
    );
}

// Create analytics table
echo html_writer::start_tag('table', array('class' => 'table table-striped analytics-table'));
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Student');
echo html_writer::tag('th', 'Email');
foreach ($analytics_columns as $key => $label) {
    echo html_writer::tag('th', $label, array('class' => 'analytics-header'));
}
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
foreach ($summary_data as $data) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $data['fullname']);
    echo html_writer::tag('td', $data['email']);
    
    foreach ($analytics_columns as $key => $label) {
        $value = $data[$key] ?? 0;
        $cell_class = 'analytics-cell analytics-' . $key;
        echo html_writer::tag('td', $value, array('class' => $cell_class));
    }
    
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

echo html_writer::end_tag('div');

// Add export buttons
echo html_writer::start_tag('div', array('class' => 'analytics-actions'));
echo html_writer::link(
    new moodle_url('/grade/report/grader/index.php', array('id' => $courseid, 'export' => 'csv')),
    'Export Enhanced CSV',
    array('class' => 'btn btn-primary')
);
echo html_writer::link(
    new moodle_url('/grade/report/analytics/index.php', array('id' => $courseid)),
    'View Full Analytics Dashboard',
    array('class' => 'btn btn-secondary')
);
echo html_writer::end_tag('div');

echo html_writer::end_tag('div');

// Add CSS styles
echo html_writer::start_tag('style');
echo '
.analytics-enhanced-grader {
    margin: 20px 0;
}

.analytics-title {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}

.analytics-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.analytics-table {
    margin-top: 15px;
}

.analytics-header {
    background: #e9ecef;
    font-weight: bold;
    text-align: center;
}

.analytics-cell {
    text-align: center;
    padding: 8px;
}

.analytics-h5p_interactions {
    background-color: #d1ecf1;
}

.analytics-video_completion {
    background-color: #d4edda;
}

.analytics-scorm_score {
    background-color: #fff3cd;
}

.analytics-sessions_attended {
    background-color: #f8d7da;
}

.analytics-attendance_rate {
    background-color: #d1ecf1;
}

.analytics-badges_count {
    background-color: #fff3cd;
}

.analytics-competency_evidence {
    background-color: #d4edda;
}

.analytics-deadline_adherence {
    background-color: #f8d7da;
}

.analytics-ta_rating {
    background-color: #e2e3e5;
}

.analytics-actions {
    margin: 20px 0;
}

.analytics-actions .btn {
    margin-right: 10px;
}
';
echo html_writer::end_tag('style');

echo $OUTPUT->footer();
