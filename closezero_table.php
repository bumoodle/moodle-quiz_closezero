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
 * This file defines the quiz grades table.
 *
 * @package    quiz
 * @subpackage overview
 * @copyright  2008 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/quiz/report/overview/overview_table.php');


/**
 * This is a table subclass for displaying the quiz grades report.
 *
 * @copyright  2008 Jamie Pratt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_closezero_table extends quiz_overview_table 
{

    public function __construct($quiz, $context, $qmsubselect, quiz_overview_options $options, $groupstudents, $students, $questions, $reporturl) 
    {
        parent::__construct($quiz, $context, $qmsubselect, $options, $groupstudents, $students, $questions, $reporturl);
        $this->detailedmarks = array();
    }


    protected function submit_buttons() {

        //TODO: add own capability instead of using delete
        if (has_capability('mod/quiz:deleteattempts', $this->context)) {
            echo '<input type="submit" name="closeselected" value="' .  get_string('closeselected', 'quiz_closezero') . '"/>';
            echo '<input type="submit" name="closeselectedandzero" value="' .  get_string('closeselectedandzero', 'quiz_closezero') . '"/>';
        }
    }

 
    /**
     * @param string $colname the name of the column.
     * @param object $attempt the row of data - see the SQL in display() in
     * mod/quiz/report/overview/report.php to see what fields are present,
     * and what they are called.
     * @return string the contents of the cell.
     */
    public function other_cols($colname, $attempt) 
    {
        return '';
    }


    protected function requires_latest_steps_loaded() 
    {
        return false;
    }

    /**
     * Don't handle regrading from this report.
     */ 
    protected function get_regraded_questions() 
    {
        return array();
    }
}
