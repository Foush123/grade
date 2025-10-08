<?php
namespace gradereport_user\report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->libdir.'/tablelib.php');

class user {

    protected $course; // stdClass course
    protected $gpr;
    protected $context;
    protected $userid = null; // Optional focus user id
    protected $viewasuser = false;

    // External API expects these properties.
    public $maxdepth = 1;
    public $tabledata = [];
    public $gradeitemsdata = [];
    public $user; // stdClass focused user (when set)

    /**
     * Constructor compatible with usages in index.php and external API.
     * Accepts either a course id (int) or a course record (stdClass) as first arg.
     */
    public function __construct($courseorid, $gpr, $context, $userid = null, $viewasuser = false) {
        global $DB, $USER;
        $this->gpr = $gpr;
        $this->context = $context;
        $this->userid = $userid;
        $this->viewasuser = (bool)$viewasuser;

        // Resolve course.
        if (is_object($courseorid)) {
            $this->course = $courseorid;
        } else {
            $this->course = $DB->get_record('course', ['id' => (int)$courseorid], '*', MUST_EXIST);
        }

        // Resolve focus user when provided.
        if (!empty($this->userid)) {
            $this->user = $DB->get_record('user', ['id' => $this->userid, 'deleted' => 0], '*', MUST_EXIST);
        } else {
            $this->user = $USER;
        }
    }

    /**
     * Build internal table data. Returns true if data exists.
     */
    public function fill_table() {
        global $CFG;

        // Prepare simple table rows: [fullname, email, grade string].
        $rows = [];

        if (!empty($this->user->id)) {
            $rows[] = $this->build_row_for_user($this->user->id);
        } else {
            $enrolled = \get_enrolled_users($this->context, '', 0, 'u.id');
            foreach ($enrolled as $u) {
                $rows[] = $this->build_row_for_user($u->id);
            }
        }

        // Filter out any null rows.
        $this->tabledata = array_values(array_filter($rows));
        $this->gradeitemsdata = []; // Not used by our minimal implementation.

        // Keep a conservative maxdepth.
        $this->maxdepth = 1;

        return !empty($this->tabledata);
    }

    /**
     * Return HTML table. If $return is true, returns string; otherwise echoes.
     */
    public function print_table($return = false) {
        global $OUTPUT;

        // Ensure data is prepared.
        if (empty($this->tabledata)) {
            $this->fill_table();
        }

        $table = new \flexible_table('gradereport-user-table');
        $table->define_columns(['fullname', 'email', 'grade']);
        $table->define_headers([\get_string('fullname'), \get_string('email'), \get_string('grade')]);
        $table->define_baseurl(new \moodle_url('/grade/report/user/index.php', ['id' => $this->course->id]));
        $table->setup();

        foreach ($this->tabledata as $row) {
            $table->add_data($row);
        }

        ob_start();
        $table->print_html();
        $html = ob_get_clean();

        if ($return) {
            return $html;
        }
        echo $html;
        return '';
    }

    /**
     * Trigger the report viewed event
     */
    public function viewed() {
        \gradereport_user\event\report_viewed::create([
            'context' => $this->context,
            'objectid' => $this->course->id
        ])->trigger();
    }

    protected function build_row_for_user(int $userid): ?array {
        $user = \core_user::get_user($userid, 'id, firstname, lastname, email', MUST_EXIST);
        $grades = \grade_get_course_grades($this->course->id, $user->id);
        $gradeval = '-';
        if (!empty($grades) && isset($grades->grades[$user->id])) {
            $gradeval = $grades->grades[$user->id]->str_long_grade;
        }
        return [\fullname($user), $user->email, $gradeval];
    }
}
