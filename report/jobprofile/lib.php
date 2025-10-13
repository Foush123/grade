<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/report/lib.php');

/**
 * Basic report class to integrate with grade_report APIs if needed later.
 * Currently not used for rendering, which is done directly in index.php.
 */
class grade_report_jobprofile extends grade_report {
    public function process_action($target, $action) {
    }
    public function process_data($data) {
    }
}



