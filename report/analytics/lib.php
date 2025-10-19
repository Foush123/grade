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
 * Analytics report library functions
 *
 * @package    gradereport_analytics
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get comprehensive analytics data for all users in a course
 *
 * @param int $courseid Course ID
 * @param array $userids Array of user IDs
 * @return array Comprehensive analytics data
 */
function gradereport_analytics_get_comprehensive_data($courseid, $userids) {
    global $DB;
    
    $analytics = array();
    $now = time();
    
    // Get basic user data
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'users');
    
    foreach ($userids as $userid) {
        $analytics[$userid] = array(
            'userid' => $userid,
            'assignments' => array(),
            'interactive_content' => array(),
            'live_sessions' => array(),
            'forums' => array(),
            'attendance' => array(),
            'competencies' => array(),
            'badges' => array(),
            'behavioral' => array(),
            'ta_evaluation' => array()
        );
    }
    
    // 1. ASSIGNMENT ANALYTICS
    gradereport_analytics_get_assignment_data($courseid, $userids, $analytics);
    
    // 2. INTERACTIVE CONTENT ANALYTICS
    gradereport_analytics_get_interactive_content_data($courseid, $userids, $analytics);
    
    // 3. LIVE INSTRUCTOR SESSIONS
    gradereport_analytics_get_live_session_data($courseid, $userids, $analytics);
    
    // 4. FORUMS & COLLABORATION
    gradereport_analytics_get_forum_data($courseid, $userids, $analytics);
    
    // 5. ATTENDANCE
    gradereport_analytics_get_attendance_data($courseid, $userids, $analytics);
    
    // 6. COMPETENCY FRAMEWORK
    gradereport_analytics_get_competency_data($courseid, $userids, $analytics);
    
    // 7. BADGES & CERTIFICATES
    gradereport_analytics_get_badge_data($courseid, $userids, $analytics);
    
    // 8. BEHAVIORAL QUALITY & PROFESSIONALISM
    gradereport_analytics_get_behavioral_data($courseid, $userids, $analytics);
    
    // 9. TA / INSTRUCTOR EVALUATION
    gradereport_analytics_get_ta_evaluation_data($courseid, $userids, $analytics);
    
    return $analytics;
}

/**
 * Get assignment analytics data
 */
function gradereport_analytics_get_assignment_data($courseid, $userids, &$analytics) {
    global $DB;
    
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'users');
    
    // Average assignment grade % in each module
    $sql = "SELECT a.id as assignid, a.name, a.course, 
                   AVG(CASE WHEN g.finalgrade IS NOT NULL THEN (g.finalgrade / g.rawgrademax) * 100 ELSE 0 END) as avg_grade_pct,
                   COUNT(s.id) as total_submissions,
                   COUNT(CASE WHEN s.timemodified <= a.duedate THEN 1 END) as ontime_submissions,
                   COUNT(CASE WHEN s.timemodified > a.duedate THEN 1 END) as late_submissions,
                   COUNT(CASE WHEN s.status = 'submitted' THEN 1 END) as submitted_count
            FROM {assign} a
            LEFT JOIN {assign_submission} s ON a.id = s.assignment AND s.userid $usql
            LEFT JOIN {grade_grades} g ON g.itemid = (
                SELECT gi.id FROM {grade_items} gi 
                WHERE gi.itemtype = 'mod' AND gi.itemmodule = 'assign' 
                AND gi.iteminstance = a.id AND gi.courseid = a.course
            ) AND g.userid = s.userid
            WHERE a.course = :courseid
            GROUP BY a.id, a.name, a.course";
    
    $assignments = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    
    foreach ($assignments as $assign) {
        foreach ($userids as $userid) {
            $analytics[$userid]['assignments'][$assign->assignid] = array(
                'name' => $assign->name,
                'avg_grade_pct' => round($assign->avg_grade_pct, 2),
                'ontime_submission_rate' => $assign->total_submissions > 0 ? 
                    round(($assign->ontime_submissions / $assign->total_submissions) * 100, 2) : 0,
                'late_submissions' => $assign->late_submissions,
                'submitted_count' => $assign->submitted_count
            );
        }
    }
    
    // Resubmission count per user
    $sql = "SELECT s.userid, s.assignment, COUNT(s.id) - 1 as resubmission_count
            FROM {assign_submission} s
            JOIN {assign} a ON a.id = s.assignment
            WHERE a.course = :courseid AND s.userid $usql AND s.status = 'submitted'
            GROUP BY s.userid, s.assignment
            HAVING COUNT(s.id) > 1";
    
    $resubmissions = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    foreach ($resubmissions as $resub) {
        if (isset($analytics[$resub->userid]['assignments'][$resub->assignment])) {
            $analytics[$resub->userid]['assignments'][$resub->assignment]['resubmission_count'] = $resub->resubmission_count;
        }
    }
    
    // Feedback richness (length of feedback text)
    $sql = "SELECT g.userid, gi.iteminstance as assignid, 
                   AVG(LENGTH(g.feedback)) as avg_feedback_length,
                   COUNT(CASE WHEN LENGTH(g.feedback) > 100 THEN 1 END) as rich_feedback_count
            FROM {grade_grades} g
            JOIN {grade_items} gi ON g.itemid = gi.id
            WHERE gi.courseid = :courseid AND gi.itemtype = 'mod' AND gi.itemmodule = 'assign'
            AND g.userid $usql AND g.feedback IS NOT NULL
            GROUP BY g.userid, gi.iteminstance";
    
    $feedback_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    foreach ($feedback_data as $feedback) {
        if (isset($analytics[$feedback->userid]['assignments'][$feedback->assignid])) {
            $analytics[$feedback->userid]['assignments'][$feedback->assignid]['feedback_richness'] = array(
                'avg_length' => round($feedback->avg_feedback_length, 2),
                'rich_count' => $feedback->rich_feedback_count
            );
        }
    }
}

/**
 * Get interactive content analytics (H5P, Video, SCORM)
 */
function gradereport_analytics_get_interactive_content_data($courseid, $userids, &$analytics) {
    global $DB;
    
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'users');
    
    // H5P interactions
    if ($DB->get_manager()->table_exists('hvp_content_user_data')) {
        $sql = "SELECT hud.user_id as userid, hc.id as contentid, hc.title,
                       COUNT(hud.id) as interaction_count,
                       AVG(hud.data) as avg_interaction_score,
                       MAX(hud.timestamp) as last_interaction
                FROM {hvp_content_user_data} hud
                JOIN {hvp_content} hc ON hud.content_id = hc.id
                JOIN {course_modules} cm ON cm.instance = hc.id AND cm.module = (
                    SELECT id FROM {modules} WHERE name = 'hvp'
                )
                WHERE cm.course = :courseid AND hud.user_id $usql
                GROUP BY hud.user_id, hc.id, hc.title";
        
        $h5p_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
        foreach ($h5p_data as $h5p) {
            $analytics[$h5p->userid]['interactive_content']['h5p'][$h5p->contentid] = array(
                'title' => $h5p->title,
                'interaction_count' => $h5p->interaction_count,
                'avg_interaction_score' => round($h5p->avg_interaction_score, 2),
                'last_interaction' => $h5p->last_interaction
            );
        }
    }
    
    // Video completion data
    $sql = "SELECT l.userid, l.contextinstanceid as cmid, cm.instance as videoid,
                   COUNT(l.id) as view_count,
                   MAX(l.timecreated) as last_view,
                   AVG(CASE WHEN l.action = 'viewed' THEN 1 ELSE 0 END) as completion_rate
            FROM {logstore_standard_log} l
            JOIN {course_modules} cm ON l.contextinstanceid = cm.id
            JOIN {modules} m ON cm.module = m.id
            WHERE l.courseid = :courseid AND l.userid $usql 
            AND m.name IN ('video', 'hvp', 'scorm')
            GROUP BY l.userid, l.contextinstanceid, cm.instance";
    
    $video_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    foreach ($video_data as $video) {
        $analytics[$video->userid]['interactive_content']['video'][$video->videoid] = array(
            'view_count' => $video->view_count,
            'completion_rate' => round($video->completion_rate * 100, 2),
            'last_view' => $video->last_view
        );
    }
    
    // SCORM interactions
    if ($DB->get_manager()->table_exists('scorm_scoes_track')) {
        $sql = "SELECT st.userid, st.scormid, st.scoid,
                       COUNT(st.id) as interaction_count,
                       AVG(CASE WHEN st.element = 'cmi.core.score.raw' THEN st.value ELSE NULL END) as avg_score,
                       MAX(st.timemodified) as last_interaction
                FROM {scorm_scoes_track} st
                JOIN {scorm} s ON st.scormid = s.id
                WHERE s.course = :courseid AND st.userid $usql
                GROUP BY st.userid, st.scormid, st.scoid";
        
        $scorm_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
        foreach ($scorm_data as $scorm) {
            $analytics[$scorm->userid]['interactive_content']['scorm'][$scorm->scormid] = array(
                'interaction_count' => $scorm->interaction_count,
                'avg_score' => round($scorm->avg_score, 2),
                'last_interaction' => $scorm->last_interaction
            );
        }
    }
}

/**
 * Get live instructor session data
 */
function gradereport_analytics_get_live_session_data($courseid, $userids, &$analytics) {
    global $DB;
    
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'users');
    
    // BigBlueButton sessions
    if ($DB->get_manager()->table_exists('bigbluebuttonbn_logs')) {
        $sql = "SELECT bl.userid, bl.bigbluebuttonbnid, bl.meetingid,
                       COUNT(bl.id) as sessions_attended,
                       SUM(bl.duration) as total_minutes,
                       AVG(CASE WHEN bl.event = 'meeting_joined' THEN 1 ELSE 0 END) as punctuality_rate,
                       COUNT(CASE WHEN bl.event = 'poll_answered' THEN 1 END) as polls_answered,
                       COUNT(CASE WHEN bl.event = 'hand_raised' THEN 1 END) as hands_raised
                FROM {bigbluebuttonbn_logs} bl
                JOIN {bigbluebuttonbn} bbb ON bl.bigbluebuttonbnid = bbb.id
                WHERE bbb.course = :courseid AND bl.userid $usql
                GROUP BY bl.userid, bl.bigbluebuttonbnid, bl.meetingid";
        
        $bbb_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
        foreach ($bbb_data as $bbb) {
            $analytics[$bbb->userid]['live_sessions']['bigbluebutton'][$bbb->bigbluebuttonbnid] = array(
                'sessions_attended' => $bbb->sessions_attended,
                'total_minutes' => $bbb->total_minutes,
                'punctuality_rate' => round($bbb->punctuality_rate * 100, 2),
                'polls_answered' => $bbb->polls_answered,
                'hands_raised' => $bbb->hands_raised
            );
        }
    }
    
    // Zoom sessions (if plugin exists)
    if ($DB->get_manager()->table_exists('zoom_meeting_participants')) {
        $sql = "SELECT zmp.userid, zmp.meetingid,
                       COUNT(zmp.id) as sessions_attended,
                       SUM(zmp.duration) as total_minutes,
                       AVG(CASE WHEN zmp.join_time <= zmp.start_time THEN 1 ELSE 0 END) as punctuality_rate
                FROM {zoom_meeting_participants} zmp
                JOIN {zoom} z ON zmp.meetingid = z.id
                WHERE z.course = :courseid AND zmp.userid $usql
                GROUP BY zmp.userid, zmp.meetingid";
        
        $zoom_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
        foreach ($zoom_data as $zoom) {
            $analytics[$zoom->userid]['live_sessions']['zoom'][$zoom->meetingid] = array(
                'sessions_attended' => $zoom->sessions_attended,
                'total_minutes' => $zoom->total_minutes,
                'punctuality_rate' => round($zoom->punctuality_rate * 100, 2)
            );
        }
    }
}

/**
 * Get forum and collaboration data
 */
function gradereport_analytics_get_forum_data($courseid, $userids, &$analytics) {
    global $DB;
    
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'users');
    
    // Forum posts and replies
    $sql = "SELECT fp.userid, f.id as forumid, f.name as forumname,
                   COUNT(CASE WHEN fp.parent = 0 THEN 1 END) as posts_created,
                   COUNT(CASE WHEN fp.parent > 0 THEN 1 END) as replies_made,
                   AVG(TIMESTAMPDIFF(MINUTE, fd.timemodified, fp.created)) as avg_response_latency,
                   COUNT(CASE WHEN fp.rating > 0 THEN 1 END) as posts_with_ratings,
                   AVG(fp.rating) as avg_peer_rating
            FROM {forum_posts} fp
            JOIN {forum_discussions} fd ON fp.discussion = fd.id
            JOIN {forum} f ON fd.forum = f.id
            WHERE f.course = :courseid AND fp.userid $usql
            GROUP BY fp.userid, f.id, f.name";
    
    $forum_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    foreach ($forum_data as $forum) {
        $analytics[$forum->userid]['forums'][$forum->forumid] = array(
            'name' => $forum->forumname,
            'posts_created' => $forum->posts_created,
            'replies_made' => $forum->replies_made,
            'avg_response_latency' => round($forum->avg_response_latency, 2),
            'posts_with_ratings' => $forum->posts_with_ratings,
            'avg_peer_rating' => round($forum->avg_peer_rating, 2)
        );
    }
    
    // Instructor engagement (posts replied to by instructors)
    $sql = "SELECT fp.userid, f.id as forumid,
                   COUNT(CASE WHEN u.id IN (
                       SELECT DISTINCT ra.userid FROM {role_assignments} ra
                       JOIN {role} r ON ra.roleid = r.id
                       WHERE r.shortname IN ('teacher', 'editingteacher', 'manager')
                   ) THEN 1 END) as instructor_replies_received
            FROM {forum_posts} fp
            JOIN {forum_discussions} fd ON fp.discussion = fd.id
            JOIN {forum} f ON fd.forum = f.id
            JOIN {user} u ON fp.userid = u.id
            WHERE f.course = :courseid AND fp.userid $usql
            GROUP BY fp.userid, f.id";
    
    $instructor_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    foreach ($instructor_data as $inst) {
        if (isset($analytics[$inst->userid]['forums'][$inst->forumid])) {
            $analytics[$inst->userid]['forums'][$inst->forumid]['instructor_replies'] = $inst->instructor_replies_received;
        }
    }
}

/**
 * Get attendance data
 */
function gradereport_analytics_get_attendance_data($courseid, $userids, &$analytics) {
    global $DB;
    
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'users');
    
    // Course module completion as attendance proxy
    $sql = "SELECT cmc.userid, cm.id as cmid, cm.instance, m.name as modulename,
                   COUNT(CASE WHEN cmc.completionstate = 1 THEN 1 END) as attended_count,
                   COUNT(cm.id) as total_sessions,
                   MAX(cmc.timemodified) as last_attendance
            FROM {course_modules_completion} cmc
            JOIN {course_modules} cm ON cmc.coursemoduleid = cm.id
            JOIN {modules} m ON cm.module = m.id
            WHERE cm.course = :courseid AND cmc.userid $usql
            GROUP BY cmc.userid, cm.id, cm.instance, m.name";
    
    $attendance_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    foreach ($attendance_data as $att) {
        $analytics[$att->userid]['attendance'][$att->cmid] = array(
            'module_name' => $att->modulename,
            'attendance_rate' => $att->total_sessions > 0 ? 
                round(($att->attended_count / $att->total_sessions) * 100, 2) : 0,
            'late_count' => 0, // Would need specific late tracking
            'absence_count' => $att->total_sessions - $att->attended_count,
            'attendance_streak' => 0, // Would need streak calculation
            'last_attendance' => $att->last_attendance
        );
    }
}

/**
 * Get competency framework data
 */
function gradereport_analytics_get_competency_data($courseid, $userids, &$analytics) {
    global $DB;
    
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'users');
    
    // Competency ratings and levels
    if ($DB->get_manager()->table_exists('competency_usercomp')) {
        $sql = "SELECT cuc.userid, c.id as competencyid, c.shortname, c.description,
                       cuc.grade, cuc.proficiency, cuc.status,
                       cuc.timemodified, cuc.timecreated
                FROM {competency_usercomp} cuc
                JOIN {competency} c ON cuc.competencyid = c.id
                WHERE c.contextid = (
                    SELECT ctx.id FROM {context} ctx 
                    WHERE ctx.contextlevel = 50 AND ctx.instanceid = :courseid
                ) AND cuc.userid $usql";
        
        $competency_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
        foreach ($competency_data as $comp) {
            $analytics[$comp->userid]['competencies'][$comp->competencyid] = array(
                'shortname' => $comp->shortname,
                'description' => $comp->description,
                'rating' => $comp->grade,
                'proficiency_achieved' => $comp->proficiency,
                'status' => $comp->status,
                'date_achieved' => $comp->timecreated,
                'last_updated' => $comp->timemodified
            );
        }
    }
    
    // Evidence count per competency
    if ($DB->get_manager()->table_exists('competency_evidence')) {
        $sql = "SELECT ce.userid, ce.competencyid,
                       COUNT(ce.id) as evidence_count,
                       MAX(ce.timemodified) as last_evidence
                FROM {competency_evidence} ce
                WHERE ce.userid $usql
                GROUP BY ce.userid, ce.competencyid";
        
        $evidence_data = $DB->get_records_sql($sql, $uparams);
        foreach ($evidence_data as $ev) {
            if (isset($analytics[$ev->userid]['competencies'][$ev->competencyid])) {
                $analytics[$ev->userid]['competencies'][$ev->competencyid]['evidence_count'] = $ev->evidence_count;
                $analytics[$ev->userid]['competencies'][$ev->competencyid]['last_evidence'] = $ev->last_evidence;
            }
        }
    }
}

/**
 * Get badges and certificates data
 */
function gradereport_analytics_get_badge_data($courseid, $userids, &$analytics) {
    global $DB;
    
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'users');
    
    // Badges earned
    if ($DB->get_manager()->table_exists('badge_issued')) {
        $sql = "SELECT bi.userid, b.id as badgeid, b.name, b.description,
                       bi.dateissued, bi.uniquehash
                FROM {badge_issued} bi
                JOIN {badge} b ON bi.badgeid = b.id
                WHERE b.courseid = :courseid AND bi.userid $usql";
        
        $badge_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
        foreach ($badge_data as $badge) {
            $analytics[$badge->userid]['badges'][$badge->badgeid] = array(
                'name' => $badge->name,
                'description' => $badge->description,
                'date_earned' => $badge->dateissued,
                'unique_hash' => $badge->uniquehash
            );
        }
    }
    
    // Certificates (if certificate plugin exists)
    if ($DB->get_manager()->table_exists('certificate_issues')) {
        $sql = "SELECT ci.userid, c.id as certificateid, c.name,
                       ci.timecreated, ci.code
                FROM {certificate_issues} ci
                JOIN {certificate} c ON ci.certificateid = c.id
                WHERE c.course = :courseid AND ci.userid $usql";
        
        $cert_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
        foreach ($cert_data as $cert) {
            $analytics[$cert->userid]['certificates'][$cert->certificateid] = array(
                'name' => $cert->name,
                'date_achieved' => $cert->timecreated,
                'code' => $cert->code
            );
        }
    }
}

/**
 * Get behavioral quality and professionalism data
 */
function gradereport_analytics_get_behavioral_data($courseid, $userids, &$analytics) {
    global $DB;
    
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'users');
    
    // Deadline adherence
    $sql = "SELECT s.userid, a.id as assignid, a.name,
                   CASE WHEN s.timemodified <= a.duedate THEN 1 ELSE 0 END as ontime,
                   s.timemodified, a.duedate
            FROM {assign_submission} s
            JOIN {assign} a ON s.assignment = a.id
            WHERE a.course = :courseid AND s.userid $usql AND s.status = 'submitted'";
    
    $deadline_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    $deadline_stats = array();
    foreach ($deadline_data as $dl) {
        if (!isset($deadline_stats[$dl->userid])) {
            $deadline_stats[$dl->userid] = array('ontime' => 0, 'total' => 0);
        }
        $deadline_stats[$dl->userid]['ontime'] += $dl->ontime;
        $deadline_stats[$dl->userid]['total']++;
    }
    
    foreach ($deadline_stats as $userid => $stats) {
        $analytics[$userid]['behavioral']['deadline_adherence'] = 
            $stats['total'] > 0 ? round(($stats['ontime'] / $stats['total']) * 100, 2) : 0;
    }
    
    // Learning pace (time between activities)
    $sql = "SELECT l.userid, 
                   AVG(TIMESTAMPDIFF(HOUR, LAG(l.timecreated) OVER (PARTITION BY l.userid ORDER BY l.timecreated), l.timecreated)) as avg_pace_hours,
                   COUNT(DISTINCT DATE(FROM_UNIXTIME(l.timecreated))) as active_days
            FROM {logstore_standard_log} l
            WHERE l.courseid = :courseid AND l.userid $usql
            GROUP BY l.userid";
    
    $pace_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    foreach ($pace_data as $pace) {
        $analytics[$pace->userid]['behavioral']['learning_pace'] = array(
            'avg_pace_hours' => round($pace->avg_pace_hours, 2),
            'active_days' => $pace->active_days
        );
    }
    
    // Academic integrity (similarity index if plagiarism plugin exists)
    if ($DB->get_manager()->table_exists('plagiarism_plagscan_doc')) {
        $sql = "SELECT pd.userid, pd.cm, pd.similarityscore,
                       COUNT(pd.id) as submissions_checked
                FROM {plagiarism_plagscan_doc} pd
                JOIN {course_modules} cm ON pd.cm = cm.id
                WHERE cm.course = :courseid AND pd.userid $usql
                GROUP BY pd.userid, pd.cm, pd.similarityscore";
        
        $integrity_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
        foreach ($integrity_data as $int) {
            if (!isset($analytics[$int->userid]['behavioral']['academic_integrity'])) {
                $analytics[$int->userid]['behavioral']['academic_integrity'] = array(
                    'avg_similarity' => 0,
                    'submissions_checked' => 0
                );
            }
            $analytics[$int->userid]['behavioral']['academic_integrity']['avg_similarity'] = 
                round($int->similarityscore, 2);
            $analytics[$int->userid]['behavioral']['academic_integrity']['submissions_checked'] = 
                $int->submissions_checked;
        }
    }
}

/**
 * Get TA/Instructor evaluation data
 */
function gradereport_analytics_get_ta_evaluation_data($courseid, $userids, &$analytics) {
    global $DB;
    
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'users');
    
    // TA ratings (if custom field exists or using gradebook comments)
    $sql = "SELECT g.userid, gi.iteminstance, gi.itemmodule,
                   AVG(g.finalgrade) as avg_ta_rating,
                   COUNT(CASE WHEN g.feedback IS NOT NULL AND LENGTH(g.feedback) > 0 THEN 1 END) as feedback_count,
                   AVG(LENGTH(g.feedback)) as avg_feedback_length
            FROM {grade_grades} g
            JOIN {grade_items} gi ON g.itemid = gi.id
            WHERE gi.courseid = :courseid AND g.userid $usql
            GROUP BY g.userid, gi.iteminstance, gi.itemmodule";
    
    $ta_data = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    foreach ($ta_data as $ta) {
        $analytics[$ta->userid]['ta_evaluation'][$ta->iteminstance] = array(
            'module' => $ta->itemmodule,
            'avg_ta_rating' => round($ta->avg_ta_rating, 2),
            'feedback_count' => $ta->feedback_count,
            'avg_feedback_length' => round($ta->avg_feedback_length, 2)
        );
    }
}

/**
 * Export analytics data to CSV
 */
function gradereport_analytics_export_csv($analytics, $course) {
    global $CFG;
    
    $filename = 'analytics_' . $course->shortname . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV headers
    $headers = array(
        'User ID', 'First Name', 'Last Name', 'Email',
        'Assignment Avg Grade %', 'On-time Submission Rate %', 'Resubmission Count', 'Feedback Richness',
        'H5P Interactions', 'Video Completion %', 'SCORM Score',
        'Live Sessions Attended %', 'Punctuality %', 'Polls Answered %', 'Hands Raised',
        'Forum Posts', 'Forum Replies', 'Response Latency', 'Instructor Engagement', 'Peer Rating',
        'Attendance %', 'Late %', 'Absence %', 'Attendance Streak',
        'Competency Rating', 'Proficiency Achieved', 'Evidence Count', 'Date Achieved',
        'Badges Earned', 'Certificate Achieved', 'Time to Certificate',
        'Deadline Adherence %', 'Learning Pace', 'Academic Integrity %',
        'TA Rating %', 'TA Notes Count'
    );
    
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($analytics as $userid => $data) {
        $user = $DB->get_record('user', array('id' => $userid));
        
        $row = array(
            $userid,
            $user->firstname,
            $user->lastname,
            $user->email,
            // Assignment data
            isset($data['assignments']) ? array_sum(array_column($data['assignments'], 'avg_grade_pct')) / count($data['assignments']) : 0,
            isset($data['assignments']) ? array_sum(array_column($data['assignments'], 'ontime_submission_rate')) / count($data['assignments']) : 0,
            isset($data['assignments']) ? array_sum(array_column($data['assignments'], 'resubmission_count')) : 0,
            isset($data['assignments']) ? array_sum(array_column(array_column($data['assignments'], 'feedback_richness'), 'avg_length')) : 0,
            // Interactive content
            isset($data['interactive_content']['h5p']) ? array_sum(array_column($data['interactive_content']['h5p'], 'interaction_count')) : 0,
            isset($data['interactive_content']['video']) ? array_sum(array_column($data['interactive_content']['video'], 'completion_rate')) / count($data['interactive_content']['video']) : 0,
            isset($data['interactive_content']['scorm']) ? array_sum(array_column($data['interactive_content']['scorm'], 'avg_score')) : 0,
            // Live sessions
            isset($data['live_sessions']) ? array_sum(array_column($data['live_sessions'], 'sessions_attended')) : 0,
            isset($data['live_sessions']) ? array_sum(array_column($data['live_sessions'], 'punctuality_rate')) : 0,
            isset($data['live_sessions']) ? array_sum(array_column($data['live_sessions'], 'polls_answered')) : 0,
            isset($data['live_sessions']) ? array_sum(array_column($data['live_sessions'], 'hands_raised')) : 0,
            // Forums
            isset($data['forums']) ? array_sum(array_column($data['forums'], 'posts_created')) : 0,
            isset($data['forums']) ? array_sum(array_column($data['forums'], 'replies_made')) : 0,
            isset($data['forums']) ? array_sum(array_column($data['forums'], 'avg_response_latency')) : 0,
            isset($data['forums']) ? array_sum(array_column($data['forums'], 'instructor_replies')) : 0,
            isset($data['forums']) ? array_sum(array_column($data['forums'], 'avg_peer_rating')) : 0,
            // Attendance
            isset($data['attendance']) ? array_sum(array_column($data['attendance'], 'attendance_rate')) / count($data['attendance']) : 0,
            isset($data['attendance']) ? array_sum(array_column($data['attendance'], 'late_count')) : 0,
            isset($data['attendance']) ? array_sum(array_column($data['attendance'], 'absence_count')) : 0,
            isset($data['attendance']) ? array_sum(array_column($data['attendance'], 'attendance_streak')) : 0,
            // Competencies
            isset($data['competencies']) ? array_sum(array_column($data['competencies'], 'rating')) / count($data['competencies']) : 0,
            isset($data['competencies']) ? array_sum(array_column($data['competencies'], 'proficiency_achieved')) : 0,
            isset($data['competencies']) ? array_sum(array_column($data['competencies'], 'evidence_count')) : 0,
            isset($data['competencies']) ? max(array_column($data['competencies'], 'date_achieved')) : 0,
            // Badges
            isset($data['badges']) ? count($data['badges']) : 0,
            isset($data['certificates']) ? count($data['certificates']) : 0,
            0, // Time to certificate would need calculation
            // Behavioral
            isset($data['behavioral']['deadline_adherence']) ? $data['behavioral']['deadline_adherence'] : 0,
            isset($data['behavioral']['learning_pace']['avg_pace_hours']) ? $data['behavioral']['learning_pace']['avg_pace_hours'] : 0,
            isset($data['behavioral']['academic_integrity']['avg_similarity']) ? $data['behavioral']['academic_integrity']['avg_similarity'] : 0,
            // TA Evaluation
            isset($data['ta_evaluation']) ? array_sum(array_column($data['ta_evaluation'], 'avg_ta_rating')) / count($data['ta_evaluation']) : 0,
            isset($data['ta_evaluation']) ? array_sum(array_column($data['ta_evaluation'], 'feedback_count')) : 0
        );
        
        fputcsv($output, $row);
    }
    
    fclose($output);
}
