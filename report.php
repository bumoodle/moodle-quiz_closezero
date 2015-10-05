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
 * This file defines the quiz overview report class.
 *
 * @package    quiz
 * @subpackage overview
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/quiz/report/overview/report.php');
require_once($CFG->dirroot.'/mod/quiz/report/closezero/closezero_settings_form.php');
require_once($CFG->dirroot.'/mod/quiz/report/closezero/closezero_table.php');


/**
 * Report which assists in closing and zeroing of attempts.
 *
 * @author     Kyle Temkin <ktemkin@binghamton.edu>, modified from Moodle Core Code
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_closezero_report extends quiz_overview_report 
{


    const ATTEMPTS_OVERDUE = 0;
    const ATTEMPTS_OVERDUE_NO_OVERRIDE = 1;
    const ATTEMPTS_OPEN = 2;


    public function display($quiz, $cm, $course) 
    {
        global $CFG, $COURSE, $DB, $OUTPUT;

        //determine the current context for this report
        $this->context = get_context_instance(CONTEXT_MODULE, $cm->id);

        //determine if the user wants to download the current report
        $download = optional_param('download', '', PARAM_ALPHA);

        //get all students relevant to the current quiz
        list($currentgroup, $students, $groupstudents, $allowed) = $this->load_relevant_students($cm, $course);

        //initialize an array of options for the given page (?)
        $pageoptions = array();
        $pageoptions['id'] = $cm->id;
        $pageoptions['mode'] = 'closezero';

        $reporturl = new moodle_url('/mod/quiz/report.php', $pageoptions);

        //get the "filter" which is used to determine which quizzes affect the final grade
        $qmsubselect = quiz_report_qm_filter_select($quiz);

        //create a new settings form, which allows us to specify which attempts are shown
        $mform = new mod_quiz_report_closezero_settings($reporturl, array('qmsubselect' => $qmsubselect, 'quiz' => $quiz, 'currentgroup' => $currentgroup, 'context' => $this->context));

        //attempt to get any data submitted with this report
        $fromform = $mform->get_data();

        //if we recieved data from the size/selection form
        if ($fromform) 
        {

            //then we haven't hit the "close all" button
            $zerononattempts = false;

            $attemptsmode = $fromform->attemptsmode;

            $regradefilter = !empty($fromform->regradefilter);

            $pagesize = $fromform->pagesize;

        }
        //otherwise, get the parameters directly from $_POST 
        else 
        {
            $zerononattempts  = optional_param('zerononattempts', 0, PARAM_BOOL);
            $attemptsmode = optional_param('attemptsmode', null, PARAM_INT);
            $regradefilter = optional_param('regradefilter', 0, PARAM_INT);
            $detailedmarks = get_user_preferences('quiz_report_overview_detailedmarks', 1);
            $pagesize = get_user_preferences('quiz_report_pagesize', 0);
        }


        $displayoptions = array();
        $displayoptions['attemptsmode'] = $attemptsmode;
        $displayoptions['regradefilter'] = $regradefilter;

        $mform->set_data($displayoptions + array('pagesize' => $pagesize));

        // We only want to show the checkbox to delete attempts
        // if the user has permissions and if the report mode is showing attempts.
        $includecheckboxes = has_any_capability(array('mod/quiz:regrade', 'mod/quiz:deleteattempts'), $this->context); 


        //get information regarding the current course- moodle has a local variable $course (which denotes the course active in the report?)
        //and a global variable $COURSE (which denotes the course which this report was displayed from?)
        $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
        $displaycoursecontext = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
        $displaycourseshortname = format_string($COURSE->shortname, true, array('context' => $displaycoursecontext));

        //load all questions in the quiz
        $questions = quiz_report_get_significant_questions($quiz);

        //create a new report table, which will list all of the relevant attempts
        $options = new quiz_overview_options('closezero', $quiz, $cm, $course);
        $options->checkboxcolumn = $includecheckboxes;
        $table = new quiz_closezero_table($quiz, $this->context, $qmsubselect, $options, $groupstudents, $students, $questions, $reporturl);

        //determine the filename which will be used, in case this quiz is downloading
        $filename = quiz_report_download_filename(get_string('overviewfilename', 'quiz_overview'), $courseshortname, $quiz->name);

        //process the core actions (close and/or zero)
        if (empty($currentgroup) || $groupstudents) 
        {
            //TODO: replace me

            if (optional_param('delete', 0, PARAM_BOOL) && confirm_sesskey()) 
            {
                if ($attemptids = optional_param_array('attemptid', array(), PARAM_INT)) 
                {
                    require_capability('mod/quiz:deleteattempts', $this->context);
                    $this->delete_selected_attempts($quiz, $cm, $attemptids, $allowed);
                    redirect($reporturl->out(false, $displayoptions));
                }

            } 
            else if (optional_param('closeselected', 0, PARAM_BOOL) && confirm_sesskey()) 
            {
                if ($attemptids = optional_param_array('attemptid', array(), PARAM_INT)) 
                {
                    require_capability('mod/quiz:regrade', $this->context);
                    $this->close_attempts($quiz, $cm, $course, $attemptids);
                    redirect($reporturl->out(false, $displayoptions));
                }
            }
        }

        if ($zerononattempts && confirm_sesskey()) 
        {
            require_capability('mod/quiz:grade', $this->context);
            $this->create_attempts_for_nonattempters($quiz, $cm, $course, $students);
            redirect($reporturl->out(false, $displayoptions), '', 5);

        } 

        $this->print_header_and_tabs($cm, $course, $quiz, 'overview');

        // Print information on the number of existing attempts
        if (!$table->is_downloading()) 
        {
            //determine the total number of attempts so far
            $num_attempts_str = quiz_num_attempt_summary($quiz, $cm, true, $currentgroup);

            //if there are attempts, print them
            if ($num_attempts_str) 
            {
                echo '<div class="quizattemptcounts">' . $num_attempts_str . '</div>';
            }
        }

        //determine if the quiz contains any questions
        $have_questions = quiz_has_questions($quiz->id);
        
        //handle the cases in which the quiz is empty, or there are no relevant students
        if (!$have_questions)
            echo quiz_no_questions_message($quiz, $cm, $this->context);
        else if (!$students)
            echo $OUTPUT->notification(get_string('nostudentsyet'));
        else if ($currentgroup && !$groupstudents)
            echo $OUTPUT->notification(get_string('nostudentsingroup'));
       

        //display the form
        $mform->display();

        //determine if we have students
        $have_students = $students && (!$currentgroup || $groupstudents);
       
        //if we have questions, and we have students, begin the table 
        if ($have_questions && ($have_students || ($attemptsmode == QUIZ_REPORT_ATTEMPTS_ALL))) 
        {
       
            /**
             * From the old CloseAttempt report.
             */    

            // Construct the SQL
            $fields = $DB->sql_concat('u.id', '\'#\'', 'COALESCE(quiza.attempt, 0)').' AS concattedid, ';
            
            if ($qmsubselect) 
            {
                $fields .=
                    "(CASE " .
                    "   WHEN $qmsubselect THEN 1" .
                    "   ELSE 0 " .
                    "END) AS gradedattempt, ";
            }

            $fields .='quiza.uniqueid AS attemptuniqueid,
                    quiza.id AS attempt,
                    u.id AS userid,
                    u.idnumber,
                    u.firstname,
                    u.lastname,
                    u.picture,
                    u.imagealt,
                    u.email,
                    u.institution,
                    u.department,
                    quiza.sumgrades,
                    quiza.timefinish,
                    quiza.preview,
                    quiza.timestart,                    
                    Coalesce(qo.timeclose, q.timeclose) as overclose,
                    CASE WHEN quiza.timefinish = 0 THEN null
                         WHEN quiza.timefinish > quiza.timestart THEN quiza.timefinish - quiza.timestart
                         ELSE 0 END AS duration';
                         
            // To explain that last bit, in MySQL, quiza.timestart and quiza.timefinish
            // are unsigned. Since MySQL 5.5.5, when they introduced strict mode,
            // subtracting a larger unsigned int from a smaller one gave an error.
            // Therefore, we avoid doing that. timefinish can be non-zero and less
            // than timestart when you have two load-balanced servers with very
            // badly synchronised clocks, and a student does a really quick attempt.

            // This part is the same for all cases - join users and quiz_attempts tables
            $from = '{user} u ';
            
            $from .= 'LEFT JOIN {quiz_attempts} quiza ON quiza.userid = u.id AND quiza.quiz = :quizid';
            $from .= ' LEFT JOIN {quiz} q ON q.id = quiza.quiz ';
            $from .= ' LEFT JOIN {quiz_overrides} qo ON q.id = qo.quiz AND quiza.userid = qo.userid ';
            
            $params = array('quizid' => $quiz->id);

            //get the current time, according to Moodle, for due date comparisons
            $now = time();

            switch ($attemptsmode) 
            {

                case self::ATTEMPTS_OVERDUE:
                    
                    list($allowed_usql, $allowed_params) = $DB->get_in_or_equal($allowed, SQL_PARAMS_NAMED, 'u');
                    $params += $allowed_params;
                    
                    $where = "u.id $allowed_usql AND quiza.timefinish = 0 AND quiza.preview != 1 AND quiza.id IS NOT NULL AND Coalesce(qo.timeclose, q.timeclose) < $now AND q.timeclose != 0";
                    break;
                    
                case self::ATTEMPTS_OVERDUE_NO_OVERRIDE:
                    
                    
                    list($allowed_usql, $allowed_params) = $DB->get_in_or_equal($allowed, SQL_PARAMS_NAMED, 'u');
                    $params += $allowed_params;
                    
                    //show _open_ attempts which are overdue, but do not compensate for overrides
                    $where = "u.id $allowed_usql AND quiza.timefinish = 0 AND quiza.preview != 1 AND quiza.id IS NOT NULL AND q.timeclose < $now AND q.timeclose != 0";
                    break;
                    
                default:
                case self::ATTEMPTS_OPEN:

                    list($allowed_usql, $allowed_params) = $DB->get_in_or_equal($allowed, SQL_PARAMS_NAMED, 'u');
                    $params += $allowed_params;
                    
                    //show only _open_ attempts 
                    $where = "u.id $allowed_usql AND quiza.timefinish = 0 AND quiza.preview != 1 AND quiza.id IS NOT NULL";
                    break;
                
                
            }

            /**
             * End QuizAttempt code
             */

        $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);
        $table->set_sql($fields, $from, $where, $params);


 
        if (has_capability('mod/quiz:regrade', $this->context)) {
            $regradesneeded = $this->count_question_attempts_needing_regrade(
                    $quiz, $groupstudents);
            if ($currentgroup) {
                $a= new stdClass();
                $a->groupname = groups_get_group_name($currentgroup);
                $a->coursestudents = get_string('participants');
                $a->countregradeneeded = $regradesneeded;
                $regradealldrydolabel =
                        get_string('regradealldrydogroup', 'quiz_overview', $a);
                $regradealldrylabel =
                        get_string('regradealldrygroup', 'quiz_overview', $a);
                $regradealllabel =
                        get_string('regradeallgroup', 'quiz_overview', $a);
            } else {
                $regradealldrydolabel =
                        get_string('regradealldrydo', 'quiz_overview', $regradesneeded);
                $regradealldrylabel =
                        get_string('regradealldry', 'quiz_overview');
                $regradealllabel =
                        get_string('regradeall', 'quiz_overview');
            }


            $displayurl = new moodle_url($reporturl,
                    $displayoptions + array('sesskey' => sesskey()));
            echo '<div class="mdl-align">';
            echo '<form action="'.$displayurl->out_omit_querystring().'">';
            echo '<div>';
            echo html_writer::input_hidden_params($displayurl);
            echo '<input type="submit" name="zerononattempts" value="' . get_string('zeroallnon', 'quiz_closezero') . '"/>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
        }



            // Print information on the grading method
            if ($strattempthighlight = quiz_report_highlighting_grading_method( $quiz, $qmsubselect, false)) {
                echo '<div class="quizattemptcounts">' . $strattempthighlight . '</div>';
            }
        }

        // Define table columns
        $columns = array();
        $headers = array();

        //if we're not downloading the table, and the user has permission, display the checkboxes for "close/zero attempt"
        if (!$table->is_downloading() && $includecheckboxes) 
        {
            $columns[] = 'checkbox';
            $headers[] = null;
        }

        //add a column which displays the name of the given user
        $this->add_user_columns($table, $columns, $headers);

        //and add a column which displays the time the attempt was started, finished, and the time taken
        $this->add_time_columns($columns, $headers);


        $options = new quiz_overview_options('closezero', $quiz, $cm, $course);
        $this->set_up_table_columns( $table, $columns, $headers, $reporturl, $options, false);
        $table->set_attribute('class', 'generaltable generalbox grades');

        $table->out($pagesize, true);
        

        return true;
    }

    protected function create_empty_attempt($quiz, $cm, $course, $userid)
    {
        global $DB;

        //get the current context
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        //if the an attempt exists for the given user, bail out
        if($DB->record_exists('quiz_attempts', array('quiz' => $quiz->id, 'userid' => $userid)))
            return;

        //if the user is a preview user, bail out
        if(has_capability('mod/quiz:preview', $context, $userid))
            return;
        
        //create a new quiz object
        $quizobj = quiz::create($quiz->id, $userid);   

        //create a new QUBA
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        //and create a new attempt for the current user
        $attempt = quiz_create_attempt($quizobj->get_quiz(), 0, false, time(), false);
        $attempt->userid = $userid;

        //load all questions in the quiz
        $quizobj->preload_questions();
        $quizobj->load_questions();

        //prepare questions for use
        $idstoslots = array();
        $questionsinuse = array_keys($quizobj->get_questions());

        //add each question in the quiz to the stub attempt
        foreach ($quizobj->get_questions() as $i => $questiondata) 
        {
            //if the question type is "random", pick a random question
            if ($questiondata->qtype != 'random') 
            {
                //if the quiz has shuffle answers off, then keep maintain the settings for a random question
                if (!$quizobj->get_quiz()->shuffleanswers)
                    $questiondata->options->shuffleanswers = false;

                //create a random question from the question bank
                $question = question_bank::make_question($questiondata);
            } 
            //otherwise, instantiate the question
            else 
            {
                $question = question_bank::get_qtype('random')->choose_other_question( $questiondata, $questionsinuse, $quizobj->get_quiz()->shuffleanswers);
                
                if (is_null($question)) 
                {
                    throw new moodle_exception('notenoughrandomquestions', 'quiz', $quizobj->view_url(), $questiondata);
                }
            }

            //add the question to the quiz, and mark it as "in use", so we don't accidentally add it on a subsequnet random attempt
            $idstoslots[$i] = $quba->add_question($question, $questiondata->maxmark);
            $questionsinuse[] = $question->id;
        }

        //start all of the given questions
        $quba->start_all_questions(new question_variant_pseudorandom_no_repeats_strategy(0), time());

        //create a new layout object
        $newlayout = array();

        //and build it from the attempt
        foreach (explode(',', $attempt->layout) as $qid) 
        {
            if ($qid != 0) 
                $newlayout[] = $idstoslots[$qid];
            else 
                $newlayout[] = 0;
        }
        $attempt->layout = implode(',', $newlayout);

        //finally, save the new attempt
        $transaction = $DB->start_delegated_transaction();
        question_engine::save_questions_usage_by_activity($quba);
        $attempt->uniqueid = $quba->get_id();
        $attempt->id = $DB->insert_record('quiz_attempts', $attempt);

        // Trigger event
        $eventdata = new stdClass();
        $eventdata->component = 'mod_quiz';
        $eventdata->attemptid = $attempt->id;
        $eventdata->timestart = $attempt->timestart;
        $eventdata->userid    = $attempt->userid;
        $eventdata->quizid    = $quizobj->get_quizid();
        $eventdata->cmid      = $quizobj->get_cmid();
        $eventdata->courseid  = $quizobj->get_courseid();
        events_trigger('quiz_attempt_started', $eventdata);

        $transaction->allow_commit();



        //then, immediately close it
        $this->close_attempt($quiz, $cm, $course, $attempt);
    } 


    protected function zero_grade($quiz, $userid, $overwrite = false)
    {
        global $DB;

        //scale the "zeroed" grade to the correct minimum value
        $final_zero = quiz_rescale_grade(0, $quiz, false); 

        //attempt to get any existing grade for the quiz
        $existing = $DB->get_record('quiz_grades', array('quiz' => $quiz->id, 'userid' => $userid)); 

        print_object($existing);

        //if we have an existing grade, and overwrite is on, update the record
        if($existing && $overwrite)
        {
            //zero out the grade
            $existing->grade = $final_zero;
            $existing->timemodified = time();

            //and store the modified result
            $DB->update_record('quiz_grades', $existing);
        }
        //otherwise, if no existing grade exists, insert a zero
        elseif(!$existing)
        {
            //create a new grade record
            $grade = new stdClass;
            $grade->quiz = $quiz->id;
            $grade->userid = $userid;
            $grade->grade = $final_zero;
            $grade->timemodified = time();

            //and insert it into the database
            $DB->insert_record('quiz_grades', $grade);
        }

        //finally, update the grades for the given user
        quiz_update_grades($quiz, $userid);
    }

    /**
     *  Closes all or a selection of attempts.
     */
    protected function close_attempts($quiz, $cm, $course, $attemptids = array())
    {
        global $DB;

        //start off with a vanilla "where" clause
        $where = "quiz = ? AND preview = 0";
        $params = array($quiz->id);

        //if attemptids were specified, limit the scope of the query to only the given attempts
        if ($attemptids) 
        {
            //get a single SQL condition that encompases all of the attempt IDs
            list($asql, $aparams) = $DB->get_in_or_equal($attemptids);

            //then add it to SQL query
            $where .= " AND id $asql";
            $params = array_merge($params, $aparams);
        }

        //query the database for any matching quiz attempts
        $attempts = $DB->get_records_select('quiz_attempts', $where, $params);
        
        //if no quiz attempts matched, quit
        if (!$attempts) 
            return;

        //for each attempt to be closed
        foreach ($attempts as $attempt) 
        {
            //reset the PHP execution timeout
            set_time_limit(30);

            //and close the attempt
            $this->close_attempt($quiz, $cm, $course, $attempt);
        }
    }

    /**
     * Closes a single quiz attempt, as though the user had submitted it.
     */ 
    protected function close_attempt($quiz, $cm, $course, $attempt)
    {
        global $DB;

        //set the "finished time" to now
        $timefinish = time();
        
        //get the QUBA object which represents the core of the quiz
        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        //finish all questions in the QUBA
        $quba->finish_all_questions();

        //and save the QUBA
        question_engine::save_questions_usage_by_activity($quba);

        //wrap the attempt data in a quiz_attempt object, and ask it to finish
        $attempt_object = new quiz_attempt($attempt, $quiz, $cm, $course, true);
        $attempt_object->process_finish($timefinish, true);

        //finally, save the grade
        quiz_save_best_grade($quiz, $attempt->userid);
    }

    protected function create_attempts_for_nonattempters($quiz, $cm, $course, $students)
    {
        global $DB;

        //run the zero-grade algorithm, with override set to FALSE
        foreach($students as $student)
            $this->create_empty_attempt($quiz, $cm, $course, $student);
    }

}
