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
 * prints the form so the user can fill out the gquiz
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_gquiz
 */

require_once("../../config.php");
require_once("lib.php");

gquiz_init_gquiz_session();

$id = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', null, PARAM_INT);
$gopage = optional_param('gopage', 0, PARAM_INT);
$gopreviouspage = optional_param('gopreviouspage', null, PARAM_RAW);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'gquiz');
$gquiz = $DB->get_record("gquiz", array("id" => $cm->instance), '*', MUST_EXIST);

$urlparams = array('id' => $cm->id, 'gopage' => $gopage, 'courseid' => $courseid);
$PAGE->set_url('/mod/gquiz/complete.php', $urlparams);

require_course_login($course, true, $cm);
$PAGE->set_activity_record($gquiz);

$context = context_module::instance($cm->id);
$gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $courseid);

$courseid = $gquizcompletion->get_courseid();

// Check whether the gquiz is mapped to the given courseid.
if (!has_capability('mod/gquiz:edititems', $context) &&
        !$gquizcompletion->check_course_is_mapped()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('cannotaccess', 'mod_gquiz'));
    echo $OUTPUT->footer();
    exit;
}

//check whether the given courseid exists
if ($courseid AND $courseid != SITEID) {
    require_course_login(get_course($courseid)); // This overwrites the object $COURSE .
}

if (!$gquizcompletion->can_complete()) {
    print_error('error');
}

$PAGE->navbar->add(get_string('gquiz:complete', 'gquiz'));
$PAGE->set_heading($course->fullname);
$PAGE->set_title($gquiz->name);
$PAGE->set_pagelayout('incourse');

// Check if the gquiz is open (timeopen, timeclose).
if (!$gquizcompletion->is_open()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($gquiz->name));
    echo $OUTPUT->box_start('generalbox boxaligncenter');
    echo $OUTPUT->notification(get_string('gquiz_is_not_open', 'gquiz'));
    echo $OUTPUT->continue_button(course_get_url($courseid ?: $gquiz->course));
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

// Mark activity viewed for completion-tracking.
if (isloggedin() && !isguestuser()) {
    $gquizcompletion->set_module_viewed();
}

// Check if user is prevented from re-submission.
$cansubmit = $gquizcompletion->can_submit();

// Initialise the form processing gquiz completion.
if (!$gquizcompletion->is_empty() && $cansubmit) {
    // Process the page via the form.
    $urltogo = $gquizcompletion->process_page($gopage, $gopreviouspage);

    if ($urltogo !== null) {
        redirect($urltogo);
    }
}

// Print the page header.
$strgquizs = get_string("modulenameplural", "gquiz");
$strgquiz  = get_string("modulename", "gquiz");

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($gquiz->name));

if ($gquizcompletion->is_empty()) {
    \core\notification::error(get_string('no_items_available_yet', 'gquiz'));
} else if ($cansubmit) {
    if ($gquizcompletion->just_completed()) {
        // Display information after the submit.
        if ($gquiz->page_after_submit) {
            echo $OUTPUT->box($gquizcompletion->page_after_submit(),
                    'generalbox boxaligncenter');
        }
        if ($gquizcompletion->can_view_analysis()) {
            echo '<p align="center">';
            $analysisurl = new moodle_url('/mod/gquiz/analysis.php', array('id' => $cm->id, 'courseid' => $courseid));
            echo html_writer::link($analysisurl, get_string('completed_gquizs', 'gquiz'));
            echo '</p>';
        }

        if ($gquiz->site_after_submit) {
            $url = gquiz_encode_target_url($gquiz->site_after_submit);
        } else {
            $url = course_get_url($courseid ?: $course->id);
        }
        echo $OUTPUT->continue_button($url);
    } else {
        // Display the form with the questions.
        echo $gquizcompletion->render_items();
    }
} else {
    echo $OUTPUT->box_start('generalbox boxaligncenter');
    echo $OUTPUT->notification(get_string('this_gquiz_is_already_submitted', 'gquiz'));
    echo $OUTPUT->continue_button(course_get_url($courseid ?: $course->id));
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
