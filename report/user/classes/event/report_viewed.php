<?php
namespace gradereport_user\event;

defined('MOODLE_INTERNAL') || die();

class report_viewed extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'course';
    }

    public static function get_name() {
        return get_string('eventgradereportviewed', 'gradereport_user');
    }

    public function get_description() {
        return "The user viewed the user grade report for course {$this->objectid}.";
    }
}
	
