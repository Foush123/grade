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
 * The gradebook grader report
 *
 * @package   gradereport_grader
 * @copyright 2007 Moodle Pty Ltd (http://moodle.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/user/renderer.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/grade/report/grader/lib.php');

// This report may require a lot of memory and time on large courses.
raise_memory_limit(MEMORY_HUGE);
set_time_limit(120);

$courseid      = required_param('id', PARAM_INT);        // course id
$page          = optional_param('page', 0, PARAM_INT);   // active page
$edit          = optional_param('edit', -1, PARAM_BOOL); // sticky editting mode

$sortitemid    = optional_param('sortitemid', 0, PARAM_ALPHANUMEXT);
$sort          = optional_param('sort', '', PARAM_ALPHA);
$action        = optional_param('action', 0, PARAM_ALPHAEXT);
$move          = optional_param('move', 0, PARAM_INT);
$type          = optional_param('type', 0, PARAM_ALPHA);
$target        = optional_param('target', 0, PARAM_ALPHANUM);
$toggle        = optional_param('toggle', null, PARAM_INT);
$toggle_type   = optional_param('toggle_type', 0, PARAM_ALPHANUM);

$graderreportsifirst  = optional_param('sifirst', null, PARAM_NOTAGS);
$graderreportsilast   = optional_param('silast', null, PARAM_NOTAGS);

$studentsperpage = optional_param('perpage', null, PARAM_INT);
$baseurl = new moodle_url('/grade/report/grader/index.php', ['id' => $courseid]);

$PAGE->set_url(new moodle_url('/grade/report/grader/index.php', array('id'=>$courseid)));
$PAGE->set_pagelayout('report');
$PAGE->requires->js_call_amd('gradereport_grader/stickycolspan', 'init');
$PAGE->requires->js_call_amd('gradereport_grader/user', 'init', [$baseurl->out(false)]);
$PAGE->requires->js_call_amd('gradereport_grader/feedback_modal', 'init');
$PAGE->requires->js_call_amd('core_grades/gradebooksetup_forms', 'init');

// basic access checks
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    throw new \moodle_exception('invalidcourseid');
}

// Conditionally add the group JS if we have groups enabled.
if ($course->groupmode) {
    $PAGE->requires->js_call_amd('core_course/actionbar/group', 'init', [$baseurl->out(false)]);
}

require_login($course);
$context = context_course::instance($course->id);

// The report object is recreated each time, save search information to SESSION object for future use.
if (isset($graderreportsifirst)) {
    $SESSION->gradereport["filterfirstname-{$context->id}"] = $graderreportsifirst;
}
if (isset($graderreportsilast)) {
    $SESSION->gradereport["filtersurname-{$context->id}"] = $graderreportsilast;
}

if (isset($studentsperpage) && $studentsperpage >= 0) {
    set_user_preference('grade_report_studentsperpage', $studentsperpage);
}

require_capability('gradereport/grader:view', $context);
require_capability('moodle/grade:viewall', $context);

// CSV export (runs after context is available).
$export = optional_param('export', '', PARAM_ALPHA);
if ($export === 'csv') {
    $gprtmp = new grade_plugin_return([
        'type' => 'report',
        'plugin' => 'grader',
        'course' => $course,
        'page' => 0
    ]);
    $reporttmp = new grade_report_grader($courseid, $gprtmp, $context, 0, null, 'ASC');
    $reporttmp->load_users(true);

    $users = $reporttmp->get_users_list();
    if (empty($users)) {
        // Stream only headers when no users.
        $filename = 'grader_export_' . $courseid . '_' . time() . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            get_string('fullname'),
            get_string('email'),
            get_string('csv_loginscount', 'gradereport_grader'),
            get_string('csv_activedays', 'gradereport_grader'),
            get_string('csv_coursecompletion', 'gradereport_grader'),
            get_string('csv_modulesunlocked', 'gradereport_grader'),
            get_string('lastcourseaccess', 'gradereport_grader'),
            get_string('lastlogin', 'gradereport_grader'),
            get_string('activitiescompleted', 'gradereport_grader'),
            get_string('csv_overduecount', 'gradereport_grader'),
            get_string('csv_quiz_firstacc', 'gradereport_grader'),
            get_string('csv_quiz_bestpct', 'gradereport_grader'),
            get_string('csv_quiz_attempts', 'gradereport_grader'),
            get_string('csv_quiz_attemptsratio', 'gradereport_grader'),
            get_string('csv_quiz_avgtime', 'gradereport_grader'),
            get_string('csv_assign_avgpct', 'gradereport_grader'),
            get_string('csv_assign_ontimepct', 'gradereport_grader'),
            get_string('csv_assign_resub', 'gradereport_grader'),
            get_string('csv_assign_feedbackrich', 'gradereport_grader'),
            get_string('csv_forum_posts', 'gradereport_grader'),
            get_string('csv_forum_replies', 'gradereport_grader'),
            get_string('csv_badges', 'gradereport_grader'),
            get_string('csv_competencies', 'gradereport_grader'),
            get_string('csv_competency_proficiency', 'gradereport_grader'),
            get_string('csv_competency_achieveddate', 'gradereport_grader'),
            get_string('csv_competency_lastupdated', 'gradereport_grader'),
            // Enhanced Analytics Fields
            'H5P Interactions',
            'Video Completion %',
            'SCORM Score',
            'Live Sessions Attended',
            'Punctuality %',
            'Polls Answered',
            'Hands Raised',
            'Forum Response Latency (min)',
            'Instructor Engagement',
            'Peer Rating',
            'Attendance %',
            'Late Count',
            'Absence Count',
            'Attendance Streak',
            'Competency Evidence Count',
            'Badges Earned Count',
            'Certificate Achieved',
            'Deadline Adherence %',
            'Learning Pace (hours)',
            'Academic Integrity %',
            'TA Rating %',
            'TA Notes Count'
        ]);
        fclose($out);
        exit;
    }
    $userids = array_keys($users);

    // Pre-compute aggregates for all users in bulk.
    $now = time();

    // Logins count (site-wide).
    list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'ul0');
    $logins = [];
    if ($DB->get_manager()->table_exists('logstore_standard_log')) {
        $sql = "SELECT userid, COUNT(1) cnt
                  FROM {logstore_standard_log}
                 WHERE userid $usql AND (action = 'loggedin' OR eventname = :evname)
              GROUP BY userid";
        $logins = $DB->get_records_sql($sql, $uparams + ['evname' => '\\core\\event\\user_loggedin']);
    }

    // Active learning days (distinct days with course activity logs).
    $activedays = [];
    if ($DB->get_manager()->table_exists('logstore_standard_log')) {
        $sql = "SELECT userid, COUNT(DISTINCT FLOOR(timecreated/86400)) cnt
                  FROM {logstore_standard_log}
                 WHERE userid $usql AND courseid = :courseid
              GROUP BY userid";
        $activedays = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
    }

    // Activities completed percentage.
    // Total trackable modules in course.
    $sql = "SELECT COUNT(1) cnt
              FROM {course_modules} cm
             WHERE cm.course = :courseid AND cm.completion > 0";
    $totaltrackable = (int)($DB->get_field_sql($sql, ['courseid' => $courseid]) ?: 0);
    $completedpct = [];
    if ($totaltrackable > 0) {
        $sql = "SELECT cmc.userid, COUNT(1) cnt
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid AND cm.completion > 0 AND cmc.userid $usql AND cmc.completionstate = 1
              GROUP BY cmc.userid";
        $completed = $DB->get_records_sql($sql, $uparams + ['courseid' => $courseid]);
        foreach ($userids as $uid) {
            $c = isset($completed[$uid]) ? (int)$completed[$uid]->cnt : 0;
            $completedpct[$uid] = $totaltrackable ? round(($c / $totaltrackable) * 100, 1) : 0;
        }
    }

    // Course completion percent (0 or 100 based on core completion record).
    $coursecompletion = array_fill_keys($userids, 0);
    if (!empty($course->enablecompletion)) {
        list($usqlcc, $uparamscc) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'ucc');
        $ccs = $DB->get_records_sql("SELECT userid, timecompleted FROM {course_completions} WHERE course = :cid AND userid $usqlcc",
            ['cid' => $courseid] + $uparamscc);
        foreach ($ccs as $row) {
            $coursecompletion[(int)$row->userid] = !empty($row->timecompleted) ? 100 : 0;
        }
    }

    // Modules unlocked (visible & trackable count) — same for all users.
    $modulesunlocked = (int)$DB->get_field_sql(
        "SELECT COUNT(1) FROM {course_modules} WHERE course = :courseid AND visible = 1 AND completion > 0",
        ['courseid' => $courseid]
    );

    // Overdue activities count: assign and quiz not completed and due date passed.
    $overdue = array_fill_keys($userids, 0);
    // Assign due dates.
    $sql = "SELECT cm.id cmid, a.duedate
              FROM {assign} a
              JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course
              JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
             WHERE a.course = :courseid AND a.duedate > 0";
    $assigndue = $DB->get_records_sql($sql, ['courseid' => $courseid]);
    if (!empty($assigndue)) {
        list($cmsql, $cmparams) = $DB->get_in_or_equal(array_keys($assigndue), SQL_PARAMS_NAMED, 'cmA');
        $sql = "SELECT cmc.userid, cmc.coursemoduleid cmid, cmc.completionstate
                  FROM {course_modules_completion} cmc
                 WHERE cmc.coursemoduleid $cmsql AND cmc.userid $usql";
        $cmcomp = $DB->get_records_sql($sql, $cmparams + $uparams);
        foreach ($assigndue as $cmid => $rec) {
            if ($rec->duedate < $now) {
                foreach ($userids as $uid) {
                    $key = $uid . '-' . $cmid;
                }
            }
        }
        // Increment if past due and not completed.
        foreach ($userids as $uid) {
            foreach ($assigndue as $cmid => $rec) {
                if ($rec->duedate < $now) {
                    $found = false;
                    foreach ($cmcomp as $row) {
                        if ((int)$row->userid === (int)$uid && (int)$row->cmid === (int)$cmid && (int)$row->completionstate === 1) {
                            $found = true; break;
                        }
                    }
                    if (!$found) { $overdue[$uid]++; }
                }
            }
        }
    }
    // Quiz timeclose overdue.
    $sql = "SELECT cm.id cmid, q.timeclose
              FROM {quiz} q
              JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = q.course
              JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
             WHERE q.course = :courseid AND q.timeclose > 0";
    $quizdue = $DB->get_records_sql($sql, ['courseid' => $courseid]);
    if (!empty($quizdue)) {
        list($cmsql, $cmparams) = $DB->get_in_or_equal(array_keys($quizdue), SQL_PARAMS_NAMED, 'cmQ');
        $sql = "SELECT cmc.userid, cmc.coursemoduleid cmid, cmc.completionstate
                  FROM {course_modules_completion} cmc
                 WHERE cmc.coursemoduleid $cmsql AND cmc.userid $usql";
        $cmcomp = $DB->get_records_sql($sql, $cmparams + $uparams);
        foreach ($userids as $uid) {
            foreach ($quizdue as $cmid => $rec) {
                if ($rec->timeclose < $now) {
                    $found = false;
                    foreach ($cmcomp as $row) {
                        if ((int)$row->userid === (int)$uid && (int)$row->cmid === (int)$cmid && (int)$row->completionstate === 1) {
                            $found = true; break;
                        }
                    }
                    if (!$found) { $overdue[$uid]++; }
                }
            }
        }
    }

    // Quizzes aggregates (best final grade %, attempts, average time seconds).
    $quizstats = [];
    $quizzes = [];
    if ($DB->get_manager()->table_exists('quiz')) {
        $sql = "SELECT q.id quizid, q.course, q.sumgrades, q.grade FROM {quiz} q WHERE q.course = :courseid";
        $quizzes = $DB->get_records_sql($sql, ['courseid' => $courseid]);
    }
    if (!empty($quizzes) && $DB->get_manager()->table_exists('quiz_grades') && $DB->get_manager()->table_exists('quiz_attempts')) {
        list($usqll, $uparamsl) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uq');
        $quizids = array_keys($quizzes);
        list($qsql, $qparams) = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'qid');
        // Best final grade per user per quiz from quiz_grades.
        $grades = $DB->get_records_sql("SELECT userid, quiz, grade FROM {quiz_grades} WHERE userid $usqll AND quiz $qsql", $uparamsl + $qparams);
        // Attempts count and avg time.
        $attempts = $DB->get_records_sql("SELECT userid, quiz, COUNT(1) cnt, AVG(NULLIF(timefinish - timestart,0)) avgdur FROM {quiz_attempts} WHERE userid $usqll AND quiz $qsql AND state='finished' GROUP BY userid, quiz", $uparamsl + $qparams);
        // First attempt accuracy per quiz (attempt=1 finished).
        $firstacc = $DB->get_records_sql("SELECT userid, quiz, AVG(NULLIF(sumgrades,0)) avg_sumgrades FROM {quiz_attempts} WHERE userid $usqll AND quiz $qsql AND state='finished' AND attempt = 1 GROUP BY userid, quiz", $uparamsl + $qparams);
        // Allowed attempts per quiz (0 = unlimited).
        $allowed = [];
        foreach ($quizzes as $qid => $q) { $allowed[$qid] = (int)$q->attempts; }
        foreach ($userids as $uid) {
            $bestpct = 0; $attemptsused = 0; $avgtime = 0; $quizcount = 0; $firstaccpct = 0; $ratioacc = 0; $ratioct = 0;
            foreach ($quizzes as $qid => $q) {
                $quizcount++;
                $gkey = $uid . '-' . $qid;
                // Best grade percent for this quiz.
                foreach ($grades as $gr) {
                    if ((int)$gr->userid === (int)$uid && (int)$gr->quiz === (int)$qid) {
                        if ($q->sumgrades > 0 && $q->grade > 0) {
                            $bestpct += round(($gr->grade / $q->grade) * 100, 1);
                        }
                        break;
                    }
                }
                foreach ($attempts as $at) {
                    if ((int)$at->userid === (int)$uid && (int)$at->quiz === (int)$qid) {
                        $attemptsused += (int)$at->cnt;
                        $avgtime += (int)$at->avgdur;
                        // Attempts used / allowed ratio (only for limited quizzes).
                        $allow = $allowed[$qid] ?? 0;
                        if ($allow > 0) { $ratioacc += min((int)$at->cnt, $allow) / $allow; $ratioct++; }
                        break;
                    }
                }
                foreach ($firstacc as $fa) {
                    if ((int)$fa->userid === (int)$uid && (int)$fa->quiz === (int)$qid) {
                        if ($q->sumgrades > 0) {
                            $firstaccpct += round((($fa->avg_sumgrades ?? 0) / $q->sumgrades) * 100, 1);
                        }
                        break;
                    }
                }
            }
            if ($quizcount > 0) {
                $bestpct = round($bestpct / $quizcount, 1);
                $avgtime = round($avgtime / $quizcount, 0);
                $firstaccpct = round($firstaccpct / $quizcount, 1);
            }
            $attemptsratio = $ratioct ? round(($ratioacc / $ratioct) * 100, 1) : 0;
            $quizstats[$uid] = (object)[
                'bestpct' => $bestpct,
                'attempts' => $attemptsused,
                'avgtime' => $avgtime,
                'firstacc' => $firstaccpct,
                'attemptsratio' => $attemptsratio
            ];
        }
    }

    // Assignments aggregates (avg grade %, on-time rate %, resubmission count).
    $assignstats = [];
    $assignitems = [];
    $sql = "SELECT gi.id giid, gi.grademax, gi.iteminstance assignid
              FROM {grade_items} gi
             WHERE gi.courseid = :courseid AND gi.itemtype = 'mod' AND gi.itemmodule = 'assign'";
    $assignitems = $DB->get_records_sql($sql, ['courseid' => $courseid]);
    if (!empty($assignitems) && $DB->get_manager()->table_exists('grade_grades') &&
        $DB->get_manager()->table_exists('assign_submission') && $DB->get_manager()->table_exists('assign')) {
        $giids = array_keys($assignitems);
        list($gisql, $giparams) = $DB->get_in_or_equal($giids, SQL_PARAMS_NAMED, 'gi');
        // Grades percent.
        $grades = $DB->get_records_sql("SELECT userid, itemid, finalgrade FROM {grade_grades} WHERE userid $usql AND itemid $gisql", $uparams + $giparams);
        // Submissions.
        $assignids = array_map(function($r){return $r->assignid;}, $assignitems);
        list($asql, $aparams) = $DB->get_in_or_equal($assignids, SQL_PARAMS_NAMED, 'as');
        $subs = $DB->get_records_sql("SELECT userid, assignment, COUNT(1) cnt, SUM(CASE WHEN attemptnumber>0 THEN 1 ELSE 0 END) resub FROM {assign_submission} WHERE userid $usql AND assignment $asql GROUP BY userid, assignment", $uparams + $aparams);
        $assignrecords = $DB->get_records_sql("SELECT id, duedate FROM {assign} WHERE id $asql", $aparams);
        foreach ($userids as $uid) {
            $sumgradepct = 0; $gradecount = 0; $ontime = 0; $subcount = 0; $resub = 0;
            foreach ($assignitems as $giid => $gi) {
                foreach ($grades as $gr) {
                    if ((int)$gr->userid === (int)$uid && (int)$gr->itemid === (int)$giid && $gi->grademax > 0) {
                        $sumgradepct += round(($gr->finalgrade / $gi->grademax) * 100, 1);
                        $gradecount++;
                    }
                }
            }
            foreach ($subs as $s) {
                if ((int)$s->userid === (int)$uid) {
                    $subcount += (int)$s->cnt;
                    $resub += (int)$s->resub;
                }
            }
            // On-time: count latest submissions before duedate.
            $ontimecount = 0; $eligible = 0;
            $latest = $DB->get_records_sql("SELECT s.userid, s.assignment, MAX(s.timecreated) t
                                              FROM {assign_submission} s
                                             WHERE s.userid $usql AND s.assignment $asql
                                          GROUP BY s.userid, s.assignment", $uparams + $aparams);
            foreach ($latest as $ls) {
                if ((int)$ls->userid === (int)$uid) {
                    $eligible++;
                    $due = $assignrecords[$ls->assignment]->duedate ?? 0;
                    if ($due <= 0 || $ls->t <= $due) { $ontimecount++; }
                }
            }
            $avggradepct = $gradecount ? round($sumgradepct / $gradecount, 1) : 0;
            $ontimerate = $eligible ? round(($ontimecount / $eligible) * 100, 1) : 0;
            $assignstats[$uid] = (object)['avggradepct' => $avggradepct, 'ontime' => $ontimerate, 'resub' => $resub];
        }
    }

    // Forum posts and replies counts.
    $forumposts = [];
    if ($DB->get_manager()->table_exists('forum_posts') && $DB->get_manager()->table_exists('forum_discussions') && $DB->get_manager()->table_exists('forum')) {
        $forumposts = $DB->get_records_sql("SELECT p.userid, SUM(CASE WHEN p.parent=0 THEN 1 ELSE 0 END) posts, SUM(CASE WHEN p.parent<>0 THEN 1 ELSE 0 END) replies
                                             FROM {forum_posts} p
                                             JOIN {forum_discussions} d ON d.id = p.discussion
                                             JOIN {forum} f ON f.id = d.forum AND f.course = :courseid
                                            WHERE p.userid $usql
                                         GROUP BY p.userid", $uparams + ['courseid' => $courseid]);
    }

    // Badges count for course.
    $badges = [];
    if ($DB->get_manager()->table_exists('badge_issued')) {
        $badges = $DB->get_records_sql("SELECT bi.userid, COUNT(1) cnt
                                          FROM {badge_issued} bi
                                          JOIN {badge} b ON b.id = bi.badgeid AND b.courseid = :courseid
                                         WHERE bi.userid $usql
                                      GROUP BY bi.userid", $uparams + ['courseid' => $courseid]);
    }

    // Competencies achieved count for course.
    $competencies = [];
    if ($DB->get_manager()->table_exists('competency_usercompcourse')) {
        // Use timemodified for both achieved and lastupdated to avoid version-specific fields.
        $competencies = $DB->get_records_sql("SELECT userid, COUNT(1) cnt, MAX(timemodified) achieved, MAX(timemodified) lastupdated
                                                FROM {competency_usercompcourse}
                                               WHERE courseid = :courseid AND userid $usql AND proficiency = 1
                                            GROUP BY userid", $uparams + ['courseid' => $courseid]);
    }

    // Enhanced Analytics Data Collection - Integrated directly into grader report
    $analytics_data = gradereport_grader_get_analytics_data($courseid, $userids);

    // Feedback richness (assign): presence of any comments feedback.
    $feedbackrich = array_fill_keys($userids, 'N');
    if ($DB->get_manager()->table_exists('assignfeedback_comments') && $DB->get_manager()->table_exists('assign_grades') && $DB->get_manager()->table_exists('assign')) {
        $sql = "SELECT ag.userid, COUNT(af.id) cnt
                  FROM {assignfeedback_comments} af
                  JOIN {assign_grades} ag ON ag.id = af.grade
                  JOIN {assign} a ON a.id = ag.assignment AND a.course = :courseid
                 WHERE ag.userid $usql
              GROUP BY ag.userid";
        $fb = $DB->get_records_sql($sql, ['courseid' => $courseid] + $uparams);
        foreach ($fb as $row) { $feedbackrich[(int)$row->userid] = ((int)$row->cnt > 0) ? 'Y' : 'N'; }
    }

    $filename = 'grader_export_' . $courseid . '_' . time() . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        get_string('fullname'),
        get_string('email'),
        get_string('csv_loginscount', 'gradereport_grader'),
        get_string('csv_activedays', 'gradereport_grader'),
        get_string('csv_coursecompletion', 'gradereport_grader'),
        get_string('csv_modulesunlocked', 'gradereport_grader'),
        get_string('lastcourseaccess', 'gradereport_grader'),
        get_string('lastlogin', 'gradereport_grader'),
        get_string('activitiescompleted', 'gradereport_grader'),
        get_string('csv_overduecount', 'gradereport_grader'),
        get_string('csv_quiz_firstacc', 'gradereport_grader'),
        get_string('csv_quiz_bestpct', 'gradereport_grader'),
        get_string('csv_quiz_attempts', 'gradereport_grader'),
        get_string('csv_quiz_attemptsratio', 'gradereport_grader'),
        get_string('csv_quiz_avgtime', 'gradereport_grader'),
        get_string('csv_assign_avgpct', 'gradereport_grader'),
        get_string('csv_assign_ontimepct', 'gradereport_grader'),
        get_string('csv_assign_resub', 'gradereport_grader'),
        get_string('csv_assign_feedbackrich', 'gradereport_grader'),
        get_string('csv_forum_posts', 'gradereport_grader'),
        get_string('csv_forum_replies', 'gradereport_grader'),
        get_string('csv_badges', 'gradereport_grader'),
        get_string('csv_competencies', 'gradereport_grader'),
        get_string('csv_competency_proficiency', 'gradereport_grader'),
        get_string('csv_competency_achieveddate', 'gradereport_grader'),
        get_string('csv_competency_lastupdated', 'gradereport_grader'),
        // Enhanced Analytics Fields
        'H5P Interactions',
        'Video Completion %',
        'SCORM Score',
        'Live Sessions Attended',
        'Punctuality %',
        'Polls Answered',
        'Hands Raised',
        'Forum Response Latency (min)',
        'Instructor Engagement',
        'Peer Rating',
        'Attendance %',
        'Late Count',
        'Absence Count',
        'Attendance Streak',
        'Competency Evidence Count',
        'Badges Earned Count',
        'Certificate Achieved',
        'Deadline Adherence %',
        'Learning Pace (hours)',
        'Academic Integrity %',
        'TA Rating %',
        'TA Notes Count'
    ]);

    foreach ($users as $u) {
        $uid = (int)$u->id;
        $fullname = fullname($u);
        $email = $u->email ?? '';
        $lc = isset($logins[$uid]) ? (int)$logins[$uid]->cnt : 0;
        $ad = isset($activedays[$uid]) ? (int)$activedays[$uid]->cnt : 0;
        $lca = empty($u->courselastaccess) ? '' : userdate($u->courselastaccess, get_string('strftimedatetimeshort'));
        $ll = empty($u->lastlogin) ? '' : userdate($u->lastlogin, get_string('strftimedatetimeshort'));
        $acpct = isset($completedpct[$uid]) ? $completedpct[$uid] : 0;
        $od = isset($overdue[$uid]) ? $overdue[$uid] : 0;
        $q = $quizstats[$uid] ?? (object)['bestpct' => 0, 'attempts' => 0, 'avgtime' => 0, 'firstacc' => 0, 'attemptsratio' => 0];
        $a = $assignstats[$uid] ?? (object)['avggradepct' => 0, 'ontime' => 0, 'resub' => 0];
        $fp = $forumposts[$uid]->posts ?? 0;
        $fr = $forumposts[$uid]->replies ?? 0;
        $bd = $badges[$uid]->cnt ?? 0;
        $cp = $competencies[$uid]->cnt ?? 0;
        $cpprof = $cp > 0 ? 'Y' : 'N';
        $cdate = isset($competencies[$uid]->achieved) && $competencies[$uid]->achieved ? userdate($competencies[$uid]->achieved, get_string('strftimedatetimeshort')) : '';
        $clu = isset($competencies[$uid]->lastupdated) && $competencies[$uid]->lastupdated ? userdate($competencies[$uid]->lastupdated, get_string('strftimedatetimeshort')) : '';
        $frich = $feedbackrich[$uid] ?? 'N';
        
        // Enhanced Analytics Data
        $analytics = $analytics_data[$uid] ?? [];
        
        // H5P Interactions
        $h5p_interactions = 0;
        if (isset($analytics['interactive_content']['h5p'])) {
            foreach ($analytics['interactive_content']['h5p'] as $h5p) {
                $h5p_interactions += $h5p['interaction_count'] ?? 0;
            }
        }
        
        // Video Completion %
        $video_completion = 0;
        if (isset($analytics['interactive_content']['video'])) {
            $total_completion = 0;
            $video_count = 0;
            foreach ($analytics['interactive_content']['video'] as $video) {
                $total_completion += $video['completion_rate'] ?? 0;
                $video_count++;
            }
            $video_completion = $video_count > 0 ? round($total_completion / $video_count, 2) : 0;
        }
        
        // SCORM Score
        $scorm_score = 0;
        if (isset($analytics['interactive_content']['scorm'])) {
            $total_score = 0;
            $scorm_count = 0;
            foreach ($analytics['interactive_content']['scorm'] as $scorm) {
                $total_score += $scorm['avg_score'] ?? 0;
                $scorm_count++;
            }
            $scorm_score = $scorm_count > 0 ? round($total_score / $scorm_count, 2) : 0;
        }
        
        // Live Sessions Data
        $sessions_attended = 0;
        $punctuality_rate = 0;
        $polls_answered = 0;
        $hands_raised = 0;
        if (isset($analytics['live_sessions'])) {
            foreach ($analytics['live_sessions'] as $session_type) {
                foreach ($session_type as $session) {
                    $sessions_attended += $session['sessions_attended'] ?? 0;
                    $punctuality_rate += $session['punctuality_rate'] ?? 0;
                    $polls_answered += $session['polls_answered'] ?? 0;
                    $hands_raised += $session['hands_raised'] ?? 0;
                }
            }
        }
        
        // Forum Data
        $response_latency = 0;
        $instructor_engagement = 0;
        $peer_rating = 0;
        if (isset($analytics['forums'])) {
            $total_latency = 0;
            $forum_count = 0;
            foreach ($analytics['forums'] as $forum) {
                $total_latency += $forum['avg_response_latency'] ?? 0;
                $instructor_engagement += $forum['instructor_replies'] ?? 0;
                $peer_rating += $forum['avg_peer_rating'] ?? 0;
                $forum_count++;
            }
            $response_latency = $forum_count > 0 ? round($total_latency / $forum_count, 2) : 0;
        }
        
        // Attendance Data
        $attendance_rate = 0;
        $late_count = 0;
        $absence_count = 0;
        $attendance_streak = 0;
        if (isset($analytics['attendance'])) {
            $total_attendance = 0;
            $attendance_modules = 0;
            foreach ($analytics['attendance'] as $attendance) {
                $total_attendance += $attendance['attendance_rate'] ?? 0;
                $late_count += $attendance['late_count'] ?? 0;
                $absence_count += $attendance['absence_count'] ?? 0;
                $attendance_streak += $attendance['attendance_streak'] ?? 0;
                $attendance_modules++;
            }
            $attendance_rate = $attendance_modules > 0 ? round($total_attendance / $attendance_modules, 2) : 0;
        }
        
        // Competency Evidence Count
        $competency_evidence = 0;
        if (isset($analytics['competencies'])) {
            foreach ($analytics['competencies'] as $competency) {
                $competency_evidence += $competency['evidence_count'] ?? 0;
            }
        }
        
        // Badges Earned Count
        $badges_earned = isset($analytics['badges']) ? count($analytics['badges']) : 0;
        
        // Certificate Achieved
        $certificate_achieved = isset($analytics['certificates']) ? count($analytics['certificates']) : 0;
        
        // Behavioral Data
        $deadline_adherence = $analytics['behavioral']['deadline_adherence'] ?? 0;
        $learning_pace = $analytics['behavioral']['learning_pace']['avg_pace_hours'] ?? 0;
        $academic_integrity = $analytics['behavioral']['academic_integrity']['avg_similarity'] ?? 0;
        
        // TA Evaluation Data
        $ta_rating = 0;
        $ta_notes = 0;
        if (isset($analytics['ta_evaluation'])) {
            $total_ta_rating = 0;
            $ta_count = 0;
            foreach ($analytics['ta_evaluation'] as $ta_eval) {
                $total_ta_rating += $ta_eval['avg_ta_rating'] ?? 0;
                $ta_notes += $ta_eval['feedback_count'] ?? 0;
                $ta_count++;
            }
            $ta_rating = $ta_count > 0 ? round($total_ta_rating / $ta_count, 2) : 0;
        }
        
        fputcsv($out, [
            $fullname,
            $email,
            $lc,
            $ad,
            $coursecompletion[$uid] ?? 0,
            $modulesunlocked,
            $lca,
            $ll,
            $acpct,
            $od,
            $q->firstacc,
            $q->bestpct,
            $q->attempts,
            $q->attemptsratio,
            $q->avgtime,
            $a->avggradepct,
            $a->ontime,
            $a->resub,
            $frich,
            $fp,
            $fr,
            $bd,
            $cp,
            $cpprof,
            $cdate,
            $clu,
            // Enhanced Analytics Fields
            $h5p_interactions,
            $video_completion,
            $scorm_score,
            $sessions_attended,
            $punctuality_rate,
            $polls_answered,
            $hands_raised,
            $response_latency,
            $instructor_engagement,
            $peer_rating,
            $attendance_rate,
            $late_count,
            $absence_count,
            $attendance_streak,
            $competency_evidence,
            $badges_earned,
            $certificate_achieved,
            $deadline_adherence,
            $learning_pace,
            $academic_integrity,
            $ta_rating,
            $ta_notes
        ]);
    }
    fclose($out);
    exit;
}

// return tracking object
$gpr = new grade_plugin_return(
    array(
        'type' => 'report',
        'plugin' => 'grader',
        'course' => $course,
        'page' => $page
    )
);

// last selected report session tracking
if (!isset($USER->grade_last_report)) {
    $USER->grade_last_report = array();
}
$USER->grade_last_report[$course->id] = 'grader';

// Build editing on/off buttons.
$buttons = '';

$PAGE->set_other_editing_capability('moodle/grade:edit');
if ($PAGE->user_allowed_editing() && !$PAGE->theme->haseditswitch) {
    if ($edit != - 1) {
        $USER->editing = $edit;
    }

    // Page params for the turn editing on button.
    $options = $gpr->get_options();
    $buttons = $OUTPUT->edit_button(new moodle_url($PAGE->url, $options), 'get');
}

$gradeserror = array();

// Handle toggle change request
if (!is_null($toggle) && !empty($toggle_type)) {
    set_user_preferences(array('grade_report_show'.$toggle_type => $toggle));
}

// Perform actions
if (!empty($target) && !empty($action) && confirm_sesskey()) {
    grade_report_grader::do_process_action($target, $action, $courseid);
}

$reportname = get_string('pluginname', 'gradereport_grader');

// Do this check just before printing the grade header (and only do it once).
grade_regrade_final_grades_if_required($course);

//Initialise the grader report object that produces the table
//the class grade_report_grader_ajax was removed as part of MDL-21562
if ($sort && strcasecmp($sort, 'desc') !== 0) {
    $sort = 'asc';
}
// We have lots of hardcoded 'ASC' and 'DESC' strings in grade/report/grader.lib :(. So we need to uppercase the sort.
$sort = strtoupper($sort);

$report = new grade_report_grader($courseid, $gpr, $context, $page, $sortitemid, $sort);

// We call this a little later since we need some info from the grader report.
$PAGE->requires->js_call_amd('gradereport_grader/collapse', 'init', [
    'userID' => $USER->id,
    'courseID' => $courseid,
    'defaultSort' => $report->get_default_sortable()
]);

$numusers = $report->get_numusers(true, true);

$actionbar = new \gradereport_grader\output\action_bar($context, $report, $numusers);
// Export button above the table.
$exporturl = new moodle_url('/grade/report/grader/index.php', ['id' => $courseid, 'export' => 'csv']);
$exportbutton = html_writer::link($exporturl, get_string('exportcsv', 'gradereport_grader'), ['class' => 'btn btn-secondary']);
print_grade_page_head($COURSE->id, 'report', 'grader', false, false, $buttons . html_writer::div($exportbutton, 'ms-2 d-inline-block'), true,
    null, null, null, $actionbar);

// make sure separate group does not prevent view
if ($report->currentgroup == -2) {
    echo $OUTPUT->heading(get_string("notingroup"));
    echo $OUTPUT->footer();
    exit;
}

$warnings = [];
$isediting = has_capability('moodle/grade:edit', $context) && isset($USER->editing) && $USER->editing;
if ($isediting && ($data = data_submitted()) && confirm_sesskey()) {
    // Processing posted grades here.
    $warnings = $report->process_data($data);
}

// Final grades MUST be loaded after the processing.
$report->load_users();
$report->load_final_grades();

//show warnings if any
foreach ($warnings as $warning) {
    echo $OUTPUT->notification($warning);
}

$displayaverages = true;
if ($numusers == 0) {
    $displayaverages = false;
}

$reporthtml = $report->get_grade_table($displayaverages);

$studentsperpage = $report->get_students_per_page();

// Print per-page dropdown.
$pagingoptions = grade_report_grader::PAGINATION_OPTIONS;
if ($studentsperpage) {
    $pagingoptions[] = $studentsperpage; // To make sure the current preference is within the options.
}
$pagingoptions = array_unique($pagingoptions);
sort($pagingoptions);
$pagingoptions = array_combine($pagingoptions, $pagingoptions);
$maxusers = $report->get_max_students_per_page();
if ($numusers > $maxusers) {
    $pagingoptions['0'] = $maxusers;
} else {
    $pagingoptions['0'] = get_string('all');
}

$perpagedata = [
    'baseurl' => (new moodle_url('/grade/report/grader/index.php', ['id' => s($courseid), 'report' => 'grader']))->out(false),
    'options' => []
];
foreach ($pagingoptions as $key => $name) {
    $perpagedata['options'][] = [
        'name' => $name,
        'value' => $key,
        'selected' => $key == $studentsperpage,
    ];
}

$footercontent = html_writer::div(
    $OUTPUT->render_from_template('gradereport_grader/perpage', $perpagedata)
    , 'col-auto'
);

// The number of students per page is always limited even if it is claimed to be unlimited.
$studentsperpage = $studentsperpage ?: $maxusers;
$footercontent .= html_writer::div(
    $OUTPUT->paging_bar($numusers, $report->page, $studentsperpage, $report->pbarurl),
    'col'
);

// print submit button
if (!empty($USER->editing) && $report->get_pref('quickgrading')) {
    echo '<form action="index.php" enctype="application/x-www-form-urlencoded" method="post" id="gradereport_grader">'; // Enforce compatibility with our max_input_vars hack.
    echo '<div>';
    echo '<input type="hidden" value="'.s($courseid).'" name="id" />';
    echo '<input type="hidden" value="'.sesskey().'" name="sesskey" />';
    echo '<input type="hidden" value="'.time().'" name="timepageload" />';
    echo '<input type="hidden" value="grader" name="report"/>';
    echo '<input type="hidden" value="'.$page.'" name="page"/>';
    echo $gpr->get_form_fields();
    echo $reporthtml;

    $footercontent .= html_writer::div(
        '<input type="submit" id="gradersubmit" class="btn btn-primary" value="'.s(get_string('savechanges')).'" />',
        'col-auto'
    );

    $stickyfooter = new core\output\sticky_footer($footercontent);
    echo $OUTPUT->render($stickyfooter);

    echo '</div></form>';
} else {
    echo $reporthtml;

    $stickyfooter = new core\output\sticky_footer($footercontent);
    echo $OUTPUT->render($stickyfooter);
}

$event = \gradereport_grader\event\grade_report_viewed::create(
    array(
        'context' => $context,
        'courseid' => $courseid,
    )
);
$event->trigger();

echo $OUTPUT->footer();
