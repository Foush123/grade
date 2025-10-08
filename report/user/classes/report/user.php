<?php
namespace gradereport_user\report;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/tablelib.php');

class user {

    protected $course;
    protected $gpr;
    protected $context;

    public function __construct($course, $gpr, $context) {
        $this->course = $course;
        $this->gpr = $gpr;
        $this->context = $context;
    }

    /**
     * Print the user grades table
     */
    public function print_table() {
        global $DB, $OUTPUT;

        // Get all users enrolled in the course
        $users = get_enrolled_users($this->context, '', 0, 'u.id, u.firstname, u.lastname, u.email');

        $table = new \flexible_table('gradereport-user-table');

        $table->define_columns(['fullname', 'email', 'grade']);
        $table->define_headers([get_string('fullname'), get_string('email'), get_string('grade')]);

        $table->define_baseurl(new \moodle_url('/gradereport/user/view.php', ['id' => $this->course->id]));
        $table->setup();

        foreach ($users as $user) {
            // Get grade for user in this course
            $grades = grade_get_course_grades($this->course->id, $user->id);

            $gradeval = '-';
            if (!empty($grades) && isset($grades->grades[$user->id])) {
                $gradeval = $grades->grades[$user->id]->str_long_grade;
            }

            $table->add_data([
                fullname($user),
                $user->email,
                $gradeval
            ]);
        }

        $table->print_html();
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
}
