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
if ((optional_param('savejobprofile', false, PARAM_BOOL)
    || optional_param('addrow', false, PARAM_BOOL)
    || optional_param('removerows', false, PARAM_BOOL)) && confirm_sesskey()) {

    // Note: optional_param_array only supports one-dimensional arrays. Our structure is nested
    // (rows[idx][field]), so we must read directly from POST after sesskey verification.
    $rows = isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : [];

    // Remove selected rows if requested.
    $toremove = isset($_POST['remove']) && is_array($_POST['remove']) ? $_POST['remove'] : [];
    if (is_array($toremove) && !empty($toremove)) {
        foreach ($toremove as $idx => $val) {
            if ($val) {
                unset($rows[$idx]);
            }
        }
        $rows = array_values($rows);
    }

    // Add a blank row if requested.
    if (optional_param('addrow', false, PARAM_BOOL)) {
        $rows[] = ['skill' => '', 'weight' => '', 'system' => '', 'assignment' => '', 'instructor' => ''];
    }

    // Compute derived columns and normalize values.
    $normalized = [];
    foreach ($rows as $row) {
        $skill = $row['skill'] ?? '';
        $weight = trim((string)($row['weight'] ?? ''));
        $system = trim((string)($row['system'] ?? ''));
        $assignment = trim((string)($row['assignment'] ?? ''));
        $instructor = trim((string)($row['instructor'] ?? ''));

        $usergradeval = calculate_usergrade([$system, $assignment, $instructor]);
        $userskillval = calculate_userskill($weight, $usergradeval);

        $normalized[] = [
            'skill' => $skill,
            'weight' => format_percent_str($weight),
            'system' => format_percent_str($system),
            'assignment' => format_percent_str($assignment),
            'instructor' => format_percent_str($instructor),
            'usergrade' => format_percent_value($usergradeval),
            'userskill' => format_percent_value($userskillval, 1),
        ];
    }

    set_config($configkey, json_encode($normalized), 'gradereport_jobprofile');

    // If this was just add/remove, stay on page without message. On save, show message.
    if (optional_param('savejobprofile', false, PARAM_BOOL)) {
        redirect($PAGE->url, get_string('changessaved'), 0);
    } else {
        redirect($PAGE->url);
    }
}

// Helper: parsing and formatting.
function parse_percent_to_float($value): ?float {
    $value = trim((string)$value);
    if ($value === '' || $value === '-' ) {
        return null;
    }
    if (substr($value, -1) === '%') {
        $value = substr($value, 0, -1);
    }
    $num = (float)str_replace([',', ' '], ['', ''], $value);
    return $num;
}

function calculate_usergrade(array $components): float {
    $vals = [];
    foreach ($components as $c) {
        $v = parse_percent_to_float($c);
        if ($v !== null) {
            $vals[] = $v;
        }
    }
    if (empty($vals)) {
        return 0.0;
    }
    return array_sum($vals) / count($vals);
}

function calculate_userskill($weight, float $usergrade): float {
    $w = parse_percent_to_float($weight);
    if ($w === null) {
        return 0.0;
    }
    return round(($w * $usergrade) / 100, 1);
}

function format_percent_str($value): string {
    $v = trim((string)$value);
    if ($v === '' || $v === '-') {
        return '-';
    }
    // Ensure ends with %.
    if (substr($v, -1) !== '%') {
        $v = rtrim($v, '%') . '%';
    }
    return $v;
}

function format_percent_value(float $num, int $decimals = 0): string {
    $fmt = $decimals > 0 ? number_format($num, $decimals) : (string)round($num);
    return $fmt . '%';
}

// Render editable table form.
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
echo html_writer::input_hidden_params($PAGE->url);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_tag('table', ['class' => 'generaltable boxaligncenter']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', '');
echo html_writer::tag('th', 'Personal Skills');
echo html_writer::tag('th', 'System Measurement');
echo html_writer::tag('th', 'Assignment Measurements');
echo html_writer::tag('th', 'Instructor rating "Feedback"');
echo html_writer::tag('th', 'User grades');
echo html_writer::tag('th', 'User Skill %');
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
// Recompute derived values for display.
foreach ($data as $idx => $row) {
    $usergradeval = calculate_usergrade([$row['system'] ?? '', $row['assignment'] ?? '', $row['instructor'] ?? '']);
    $userskillval = calculate_userskill($row['weight'] ?? '', $usergradeval);
    echo html_writer::start_tag('tr');
    // Remove checkbox.
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'checkbox', 'name' => "remove[$idx]", 'value' => 1
    ]));
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
    echo html_writer::tag('td', html_writer::tag('span', format_percent_value($usergradeval)));
    echo html_writer::tag('td', html_writer::tag('span', format_percent_value($userskillval, 1)));
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
// Footer totals.
$totalweight = 0.0;
$totaluserskill = 0.0;
foreach ($data as $row) {
    $w = parse_percent_to_float($row['weight'] ?? '');
    if ($w !== null) {
        $totalweight += $w;
    }
    $ug = calculate_usergrade([$row['system'] ?? '', $row['assignment'] ?? '', $row['instructor'] ?? '']);
    $totaluserskill += calculate_userskill($row['weight'] ?? '', $ug);
}
echo html_writer::start_tag('tfoot');
echo html_writer::start_tag('tr');
echo html_writer::tag('td', html_writer::tag('strong', 'Total'), ['colspan' => 2]);
echo html_writer::tag('td', '', ['colspan' => 3]);
echo html_writer::tag('td', '', []);
echo html_writer::tag('td', html_writer::tag('strong', format_percent_value($totaluserskill, 1)));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('tfoot');
echo html_writer::end_tag('table');

echo html_writer::start_div('buttons mt-3');
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary', 'name' => 'addrow', 'value' => get_string('add')]);
echo ' ';
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary', 'name' => 'removerows', 'value' => get_string('delete')]);
echo ' ';
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'name' => 'savejobprofile', 'value' => get_string('savechanges')]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo $OUTPUT->footer();



