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

require_once($CFG->libdir . '/externallib.php');

/**
 * Analytics web service external functions
 *
 * @package    gradereport_analytics
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradereport_analytics_external extends external_api {

    /**
     * Get comprehensive analytics data for a course
     *
     * @param int $courseid Course ID
     * @param int $userid Optional user ID to get data for specific user
     * @return array Analytics data
     */
    public static function get_comprehensive_analytics($courseid, $userid = 0) {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::get_comprehensive_analytics_parameters(), array(
            'courseid' => $courseid,
            'userid' => $userid
        ));

        // Validate context and permissions
        $course = $DB->get_record('course', array('id' => $params['courseid']), '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('gradereport/analytics:view', $context);

        // Get enrolled users
        $users = get_enrolled_users($context, '', 0, 'u.id', 'u.lastname, u.firstname');
        
        if (empty($users)) {
            return array();
        }

        $userids = array_keys($users);
        
        // If specific user requested, filter to that user
        if ($params['userid'] > 0) {
            if (!in_array($params['userid'], $userids)) {
                throw new invalid_parameter_exception('User not enrolled in course');
            }
            $userids = array($params['userid']);
        }

        // Get comprehensive analytics data
        require_once($CFG->dirroot . '/grade/report/analytics/lib.php');
        $analytics = gradereport_analytics_get_comprehensive_data($params['courseid'], $userids);

        // Format return data
        $result = array();
        foreach ($analytics as $uid => $data) {
            $user = $users[$uid];
            $result[] = array(
                'userid' => $uid,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'analytics' => $data
            );
        }

        return $result;
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_comprehensive_analytics_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'userid' => new external_value(PARAM_INT, 'User ID (optional)', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_multiple_structure
     */
    public static function get_comprehensive_analytics_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'firstname' => new external_value(PARAM_TEXT, 'First name'),
                    'lastname' => new external_value(PARAM_TEXT, 'Last name'),
                    'email' => new external_value(PARAM_TEXT, 'Email'),
                    'analytics' => new external_single_structure(
                        array(
                            'assignments' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'name' => new external_value(PARAM_TEXT, 'Assignment name'),
                                        'avg_grade_pct' => new external_value(PARAM_FLOAT, 'Average grade percentage'),
                                        'ontime_submission_rate' => new external_value(PARAM_FLOAT, 'On-time submission rate'),
                                        'resubmission_count' => new external_value(PARAM_INT, 'Resubmission count'),
                                        'feedback_richness' => new external_single_structure(
                                            array(
                                                'avg_length' => new external_value(PARAM_FLOAT, 'Average feedback length'),
                                                'rich_count' => new external_value(PARAM_INT, 'Rich feedback count')
                                            ),
                                            'Feedback richness data'
                                        )
                                    ),
                                    'Assignment analytics'
                                ),
                                'Assignment analytics data'
                            ),
                            'interactive_content' => new external_single_structure(
                                array(
                                    'h5p' => new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'title' => new external_value(PARAM_TEXT, 'H5P title'),
                                                'interaction_count' => new external_value(PARAM_INT, 'Interaction count'),
                                                'avg_interaction_score' => new external_value(PARAM_FLOAT, 'Average interaction score'),
                                                'last_interaction' => new external_value(PARAM_INT, 'Last interaction timestamp')
                                            ),
                                            'H5P analytics'
                                        ),
                                        'H5P analytics data'
                                    ),
                                    'video' => new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'view_count' => new external_value(PARAM_INT, 'View count'),
                                                'completion_rate' => new external_value(PARAM_FLOAT, 'Completion rate'),
                                                'last_view' => new external_value(PARAM_INT, 'Last view timestamp')
                                            ),
                                            'Video analytics'
                                        ),
                                        'Video analytics data'
                                    ),
                                    'scorm' => new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'interaction_count' => new external_value(PARAM_INT, 'Interaction count'),
                                                'avg_score' => new external_value(PARAM_FLOAT, 'Average score'),
                                                'last_interaction' => new external_value(PARAM_INT, 'Last interaction timestamp')
                                            ),
                                            'SCORM analytics'
                                        ),
                                        'SCORM analytics data'
                                    )
                                ),
                                'Interactive content analytics'
                            ),
                            'live_sessions' => new external_single_structure(
                                array(
                                    'bigbluebutton' => new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'sessions_attended' => new external_value(PARAM_INT, 'Sessions attended'),
                                                'total_minutes' => new external_value(PARAM_INT, 'Total minutes'),
                                                'punctuality_rate' => new external_value(PARAM_FLOAT, 'Punctuality rate'),
                                                'polls_answered' => new external_value(PARAM_INT, 'Polls answered'),
                                                'hands_raised' => new external_value(PARAM_INT, 'Hands raised')
                                            ),
                                            'BigBlueButton analytics'
                                        ),
                                        'BigBlueButton analytics data'
                                    ),
                                    'zoom' => new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'sessions_attended' => new external_value(PARAM_INT, 'Sessions attended'),
                                                'total_minutes' => new external_value(PARAM_INT, 'Total minutes'),
                                                'punctuality_rate' => new external_value(PARAM_FLOAT, 'Punctuality rate')
                                            ),
                                            'Zoom analytics'
                                        ),
                                        'Zoom analytics data'
                                    )
                                ),
                                'Live session analytics'
                            ),
                            'forums' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'name' => new external_value(PARAM_TEXT, 'Forum name'),
                                        'posts_created' => new external_value(PARAM_INT, 'Posts created'),
                                        'replies_made' => new external_value(PARAM_INT, 'Replies made'),
                                        'avg_response_latency' => new external_value(PARAM_FLOAT, 'Average response latency'),
                                        'posts_with_ratings' => new external_value(PARAM_INT, 'Posts with ratings'),
                                        'avg_peer_rating' => new external_value(PARAM_FLOAT, 'Average peer rating'),
                                        'instructor_replies' => new external_value(PARAM_INT, 'Instructor replies received')
                                    ),
                                    'Forum analytics'
                                ),
                                'Forum analytics data'
                            ),
                            'attendance' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'module_name' => new external_value(PARAM_TEXT, 'Module name'),
                                        'attendance_rate' => new external_value(PARAM_FLOAT, 'Attendance rate'),
                                        'late_count' => new external_value(PARAM_INT, 'Late count'),
                                        'absence_count' => new external_value(PARAM_INT, 'Absence count'),
                                        'attendance_streak' => new external_value(PARAM_INT, 'Attendance streak'),
                                        'last_attendance' => new external_value(PARAM_INT, 'Last attendance timestamp')
                                    ),
                                    'Attendance analytics'
                                ),
                                'Attendance analytics data'
                            ),
                            'competencies' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'shortname' => new external_value(PARAM_TEXT, 'Competency shortname'),
                                        'description' => new external_value(PARAM_TEXT, 'Competency description'),
                                        'rating' => new external_value(PARAM_FLOAT, 'Competency rating'),
                                        'proficiency_achieved' => new external_value(PARAM_BOOL, 'Proficiency achieved'),
                                        'status' => new external_value(PARAM_INT, 'Status'),
                                        'date_achieved' => new external_value(PARAM_INT, 'Date achieved timestamp'),
                                        'last_updated' => new external_value(PARAM_INT, 'Last updated timestamp'),
                                        'evidence_count' => new external_value(PARAM_INT, 'Evidence count'),
                                        'last_evidence' => new external_value(PARAM_INT, 'Last evidence timestamp')
                                    ),
                                    'Competency analytics'
                                ),
                                'Competency analytics data'
                            ),
                            'badges' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'name' => new external_value(PARAM_TEXT, 'Badge name'),
                                        'description' => new external_value(PARAM_TEXT, 'Badge description'),
                                        'date_earned' => new external_value(PARAM_INT, 'Date earned timestamp'),
                                        'unique_hash' => new external_value(PARAM_TEXT, 'Unique hash')
                                    ),
                                    'Badge analytics'
                                ),
                                'Badge analytics data'
                            ),
                            'certificates' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'name' => new external_value(PARAM_TEXT, 'Certificate name'),
                                        'date_achieved' => new external_value(PARAM_INT, 'Date achieved timestamp'),
                                        'code' => new external_value(PARAM_TEXT, 'Certificate code')
                                    ),
                                    'Certificate analytics'
                                ),
                                'Certificate analytics data'
                            ),
                            'behavioral' => new external_single_structure(
                                array(
                                    'deadline_adherence' => new external_value(PARAM_FLOAT, 'Deadline adherence percentage'),
                                    'learning_pace' => new external_single_structure(
                                        array(
                                            'avg_pace_hours' => new external_value(PARAM_FLOAT, 'Average pace in hours'),
                                            'active_days' => new external_value(PARAM_INT, 'Active days count')
                                        ),
                                        'Learning pace data'
                                    ),
                                    'academic_integrity' => new external_single_structure(
                                        array(
                                            'avg_similarity' => new external_value(PARAM_FLOAT, 'Average similarity percentage'),
                                            'submissions_checked' => new external_value(PARAM_INT, 'Submissions checked count')
                                        ),
                                        'Academic integrity data'
                                    )
                                ),
                                'Behavioral analytics'
                            ),
                            'ta_evaluation' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'module' => new external_value(PARAM_TEXT, 'Module name'),
                                        'avg_ta_rating' => new external_value(PARAM_FLOAT, 'Average TA rating'),
                                        'feedback_count' => new external_value(PARAM_INT, 'Feedback count'),
                                        'avg_feedback_length' => new external_value(PARAM_FLOAT, 'Average feedback length')
                                    ),
                                    'TA evaluation analytics'
                                ),
                                'TA evaluation analytics data'
                            )
                        ),
                        'Comprehensive analytics data'
                    )
                ),
                'User analytics data'
            ),
            'Comprehensive analytics data for course users'
        );
    }
}
