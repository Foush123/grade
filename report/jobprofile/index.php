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
 * Job profile report entrypoint.
 *
 * @package   gradereport_jobprofile
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/grade/lib.php');

$courseid = required_param('id', PARAM_INT);

if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    throw new moodle_exception('invalidcourseid');
}

require_login($course);
$context = context_course::instance($course->id);

require_capability('gradereport/jobprofile:view', $context);
require_capability('moodle/grade:viewall', $context);

$PAGE->set_url('/grade/report/jobprofile/index.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->add_body_class('limitedwidth');

// Header.
print_grade_page_head($courseid, 'report', 'jobprofile', false,
    false, false, true, null, null,
    null, null);

// Load existing data from config (per-course) or set defaults.
$configkey = 'data_' . $courseid;
$existingjson = get_config('gradereport_jobprofile', $configkey);

// Default dataset corresponding to the provided table.
$defaultdata = [
    ['skill' => 'Organizational Skills', 'weight' => '10%', 'system' => '60%', 'assignment' => '-', 'instructor' => '40%', 'usergrade' => '90%', 'userskill' => '9%'],
    ['skill' => 'Communication Skills', 'weight' => '5%', 'system' => '-', 'assignment' => '60%', 'instructor' => '40%', 'usergrade' => '70%', 'userskill' => '3.5%'],
    ['skill' => 'Collaboration', 'weight' => '5%', 'system' => '-', 'assignment' => '80%', 'instructor' => '20%', 'usergrade' => '60%', 'userskill' => '3.0%'],
    ['skill' => 'Stress Management', 'weight' => '5%', 'system' => '80%', 'assignment' => '20%', 'instructor' => '-', 'usergrade' => '30%', 'userskill' => '1.5%'],
    ['skill' => '', 'weight' => '3%', 'system' => '-', 'assignment' => '-', 'instructor' => '100%', 'usergrade' => '85%', 'userskill' => '2.6%'],
    ['skill' => '', 'weight' => '-', 'system' => '20%', 'assignment' => '80%', 'instructor' => '80%', 'usergrade' => '60%', 'userskill' => '0%'],
];

$data = $existingjson ? json_decode($existingjson, true) : $defaultdata;
if (!is_array($data)) {
    $data = $defaultdata;
}

// Handle form submission.
if (optional_param('savejobprofile', false, PARAM_BOOL) && confirm_sesskey()) {
    $rows = optional_param_array('rows', [], PARAM_RAW_TRIMMED);
    $normalized = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $normalized[] = [
                'skill' => $row['skill'] ?? '',
                'weight' => $row['weight'] ?? '',
                'system' => $row['system'] ?? '',
                'assignment' => $row['assignment'] ?? '',
                'instructor' => $row['instructor'] ?? '',
                'usergrade' => $row['usergrade'] ?? '',
                'userskill' => $row['userskill'] ?? '',
            ];
        }
    }
    set_config($configkey, json_encode($normalized), 'gradereport_jobprofile');
    redirect($PAGE->url, get_string('changessaved'), 0);
}

// Render editable table form.
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
echo html_writer::input_hidden_params($PAGE->url);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_tag('table', ['class' => 'generaltable boxaligncenter']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Personal Skills');
echo html_writer::tag('th', 'System Measurement');
echo html_writer::tag('th', 'Assignment Measurements');
echo html_writer::tag('th', 'Instructor rating "Feedback"');
echo html_writer::tag('th', 'User grades');
echo html_writer::tag('th', 'User Skill %');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
foreach ($data as $idx => $row) {
    echo html_writer::start_tag('tr');
    // Skill name and weight in first cell stacked for compactness similar to screenshot.
    echo html_writer::tag('td',
        html_writer::empty_tag('input', [
            'type' => 'text', 'name' => "rows[$idx][skill]", 'value' => $row['skill'], 'size' => 28
        ]) . ' ' .
        html_writer::empty_tag('input', [
            'type' => 'text', 'name' => "rows[$idx][weight]", 'value' => $row['weight'], 'size' => 5
        ])
    );

    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'text', 'name' => "rows[$idx][system]", 'value' => $row['system'], 'size' => 6
    ]));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'text', 'name' => "rows[$idx][assignment]", 'value' => $row['assignment'], 'size' => 6
    ]));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'text', 'name' => "rows[$idx][instructor]", 'value' => $row['instructor'], 'size' => 6
    ]));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'text', 'name' => "rows[$idx][usergrade]", 'value' => $row['usergrade'], 'size' => 6
    ]));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'text', 'name' => "rows[$idx][userskill]", 'value' => $row['userskill'], 'size' => 6
    ]));
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'name' => 'savejobprofile', 'value' => get_string('savechanges')]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();



