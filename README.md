Close and Zero Attempts Report
=======================================

Allows an instructor to easily close attempts- including the ability to close all overdue exams in two clicks. Also allows instructors to create "null" or "zero" attempts for users who did not attempt the quiz- which will allow them to review a quiz as though they had submitted an empty quiz.

Authored by Kyle Temkin, working for Binghamton University <http://www.binghamton.edu>

Warning
------------------------
This report has been minimally tested with newer versions of Moodle. It's based off of old Moodle core code, and thus required a /lot/ of cleanup! 

Forthcoming Features
-------------------------
Will include a cron script which can be used to automatically zero non-submitted quiz attempts after the due date has passed.

Installation Instruction
-------------------------

To install Moodle 2.1+ using git, execute the following commands in the root of your Moodle install:

    git clone git://github.com/bumoodle/moodle-report_closezero.git mod/quiz/report/closezero
    echo 'mod/quiz/report/closezero' >> .git/info/exclude

Or, extract the following zip in your_moodle_root/mod/quiz/report/closezero:

    https://github.com/bumoodle/moodle-report_closezero/zipball/master

