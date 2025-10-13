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

// Render the static image for now. Place your image file at pix/jobprofile.png.
$imageurl = new moodle_url('/grade/report/jobprofile/pix/jobprofile.png');

echo html_writer::start_div('gradereport-jobprofile-image');
echo html_writer::empty_tag('img', [
    'src' => $imageurl->out(false),
    'alt' => get_string('pluginname', 'gradereport_jobprofile'),
    'style' => 'max-width:100%;height:auto;'
]);
echo html_writer::end_div();

echo $OUTPUT->footer();



