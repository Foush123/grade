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

defined('MOODLE_INTERNAL') || die();

/**
 * Analytics report renderer
 *
 * @package    gradereport_analytics
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradereport_analytics_renderer extends plugin_renderer_base {

    /**
     * Render the analytics dashboard
     *
     * @param array $analytics Analytics data
     * @param object $course Course object
     * @return string HTML output
     */
    public function render_analytics_dashboard($analytics, $course) {
        $data = array(
            'course' => $course,
            'analytics' => $analytics,
            'export_url' => new moodle_url('/grade/report/analytics/index.php', array('id' => $course->id, 'format' => 'csv')),
            'json_url' => new moodle_url('/grade/report/analytics/index.php', array('id' => $course->id, 'format' => 'json'))
        );
        
        return $this->render_from_template('gradereport_analytics/dashboard', $data);
    }

    /**
     * Render assignment analytics section
     *
     * @param array $assignments Assignment data
     * @return string HTML output
     */
    public function render_assignment_analytics($assignments) {
        $data = array('assignments' => $assignments);
        return $this->render_from_template('gradereport_analytics/assignment_section', $data);
    }

    /**
     * Render interactive content analytics section
     *
     * @param array $interactive Interactive content data
     * @return string HTML output
     */
    public function render_interactive_analytics($interactive) {
        $data = array('interactive' => $interactive);
        return $this->render_from_template('gradereport_analytics/interactive_section', $data);
    }

    /**
     * Render live session analytics section
     *
     * @param array $sessions Live session data
     * @return string HTML output
     */
    public function render_live_session_analytics($sessions) {
        $data = array('sessions' => $sessions);
        return $this->render_from_template('gradereport_analytics/live_session_section', $data);
    }

    /**
     * Render forum analytics section
     *
     * @param array $forums Forum data
     * @return string HTML output
     */
    public function render_forum_analytics($forums) {
        $data = array('forums' => $forums);
        return $this->render_from_template('gradereport_analytics/forum_section', $data);
    }

    /**
     * Render attendance analytics section
     *
     * @param array $attendance Attendance data
     * @return string HTML output
     */
    public function render_attendance_analytics($attendance) {
        $data = array('attendance' => $attendance);
        return $this->render_from_template('gradereport_analytics/attendance_section', $data);
    }

    /**
     * Render competency analytics section
     *
     * @param array $competencies Competency data
     * @return string HTML output
     */
    public function render_competency_analytics($competencies) {
        $data = array('competencies' => $competencies);
        return $this->render_from_template('gradereport_analytics/competency_section', $data);
    }

    /**
     * Render badge analytics section
     *
     * @param array $badges Badge data
     * @return string HTML output
     */
    public function render_badge_analytics($badges) {
        $data = array('badges' => $badges);
        return $this->render_from_template('gradereport_analytics/badge_section', $data);
    }

    /**
     * Render behavioral analytics section
     *
     * @param array $behavioral Behavioral data
     * @return string HTML output
     */
    public function render_behavioral_analytics($behavioral) {
        $data = array('behavioral' => $behavioral);
        return $this->render_from_template('gradereport_analytics/behavioral_section', $data);
    }

    /**
     * Render TA evaluation analytics section
     *
     * @param array $ta_evaluation TA evaluation data
     * @return string HTML output
     */
    public function render_ta_evaluation_analytics($ta_evaluation) {
        $data = array('ta_evaluation' => $ta_evaluation);
        return $this->render_from_template('gradereport_analytics/ta_evaluation_section', $data);
    }
}
