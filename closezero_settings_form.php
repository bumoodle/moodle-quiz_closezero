<?php





require_once "$CFG->libdir/formslib.php";

class mod_quiz_report_closezero_settings extends moodleform 
{

    function definition() {
        global $COURSE;
        $mform    =& $this->_form;
        $mform->addElement('header', 'preferencespage', get_string('preferencespage', 'quiz_overview'));

        if (!$this->_customdata['currentgroup']){
            $studentsstring = get_string('participants');
        } else {
            $a = new stdClass();
            $a->coursestudent = get_string('participants');
            $a->groupname = groups_get_group_name($this->_customdata['currentgroup']);
            if (20 < strlen($a->groupname)){
                $studentsstring = get_string('studentingrouplong', 'quiz_overview', $a);
            } else {
                $studentsstring = get_string('studentingroup', 'quiz_overview', $a);
            }
        }
        $options = array();
        if (!$this->_customdata['currentgroup'])
        {
        //    $options[QUIZ_REPORT_ATTEMPTS_ALL] = get_string('optallattempts','quiz_overview');
        }
        if ($this->_customdata['currentgroup'] || $COURSE->id != SITEID) {
            $options[quiz_closezero_report::ATTEMPTS_OVERDUE] = get_string('alloverdue','quiz_closezero');
            $options[quiz_closezero_report::ATTEMPTS_OVERDUE_NO_OVERRIDE] = get_string('alloverduenooverrides','quiz_closezero');
            $options[quiz_closezero_report::ATTEMPTS_OPEN] = get_string('allopen','quiz_closezero');
            
        }
        $mform->addElement('select', 'attemptsmode', get_string('show', 'quiz_overview'), $options);
        $mform->addElement('header', 'preferencesuser', get_string('preferencesuser', 'quiz_overview'));

        $mform->addElement('text', 'pagesize', get_string('pagesize', 'quiz_overview'));
        $mform->setType('pagesize', PARAM_INT);

        $mform->addElement('submit', 'submitbutton', get_string('preferencessave', 'quiz_overview'));
    }
}
