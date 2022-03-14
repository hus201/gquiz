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
 * the first page to view the gquiz
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_gquiz
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/gquiz/lib.php');

$id = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', false, PARAM_INT);

$current_tab = 'view';

list($course, $cm) = get_course_and_cm_from_cmid($id, 'gquiz');
require_course_login($course, true, $cm);
$gquiz = $PAGE->activityrecord;

$gquizcompletion = new mod_gquiz_completion($gquiz, $cm, $courseid);

$context = context_module::instance($cm->id);

if ($course->id == SITEID) {
    $PAGE->set_pagelayout('incourse');
}
$PAGE->set_url('/mod/gquiz/view.php', array('id' => $cm->id));
$PAGE->set_title($gquiz->name);
$PAGE->set_heading($course->fullname);

// Check access to the given courseid.
if ($courseid AND $courseid != SITEID) {
    require_course_login(get_course($courseid)); // This overwrites the object $COURSE .
}

// Check whether the gquiz is mapped to the given courseid.
if (!has_capability('mod/gquiz:edititems', $context) &&
        !$gquizcompletion->check_course_is_mapped()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('cannotaccess', 'mod_gquiz'));
    echo $OUTPUT->footer();
    exit;
}

// Trigger module viewed event.
$gquizcompletion->trigger_module_viewed();

/// Print the page header
echo $OUTPUT->header();

/// Print the main part of the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

$previewimg = $OUTPUT->pix_icon('t/preview', get_string('preview'));
$previewlnk = new moodle_url('/mod/gquiz/print.php', array('id' => $id));
if ($courseid) {
    $previewlnk->param('courseid', $courseid);
}
$preview = html_writer::link($previewlnk, $previewimg);

echo $OUTPUT->heading(format_string($gquiz->name) . $preview);

// Render the activity information.
$completiondetails = \core_completion\cm_completion_details::get_instance($cm, $USER->id);
$activitydates = \core\activity_dates::get_dates_for_module($cm, $USER->id);
echo $OUTPUT->activity_information($cm, $completiondetails, $activitydates);

// Print the tabs.
require('tabs.php');

// Show description.
echo $OUTPUT->box_start('generalbox gquiz_description');
$options = (object)array('noclean' => true);
echo format_module_intro('gquiz', $gquiz, $cm->id);
echo $OUTPUT->box_end();

//show some infos to the gquiz
if (has_capability('mod/gquiz:edititems', $context)) {

    echo $OUTPUT->heading(get_string('overview', 'gquiz'), 3);

    //get the groupid
    $groupselect = groups_print_activity_menu($cm, $CFG->wwwroot.'/mod/gquiz/view.php?id='.$cm->id, true);
    $mygroupid = groups_get_activity_group($cm);

    echo $groupselect.'<div class="clearer">&nbsp;</div>';
    $summary = new mod_gquiz\output\summary($gquizcompletion, $mygroupid);
    echo $OUTPUT->render_from_template('mod_gquiz/summary', $summary->export_for_template($OUTPUT));

    if ($pageaftersubmit = $gquizcompletion->page_after_submit()) {
        echo $OUTPUT->heading(get_string("page_after_submit", "gquiz"), 3);
        echo $OUTPUT->box($pageaftersubmit, 'generalbox gquiz_after_submit');
    }
}

if (!has_capability('mod/gquiz:viewreports', $context) &&
        $gquizcompletion->can_view_analysis()) {
    $analysisurl = new moodle_url('/mod/gquiz/analysis.php', array('id' => $id));
    echo '<div class="mdl-align"><a href="'.$analysisurl->out().'">';
    echo get_string('completed_gquizs', 'gquiz').'</a>';
    echo '</div>';
}

if (has_capability('mod/gquiz:mapcourse', $context) && $gquiz->course == SITEID) {
    echo $OUTPUT->box_start('generalbox gquiz_mapped_courses');
    echo $OUTPUT->heading(get_string("mappedcourses", "gquiz"), 3);
    echo '<p>' . get_string('mapcourse_help', 'gquiz') . '</p>';
    $mapurl = new moodle_url('/mod/gquiz/mapcourse.php', array('id' => $id));
    echo '<p class="mdl-align">' . html_writer::link($mapurl, get_string('mapcourses', 'gquiz')) . '</p>';
    echo $OUTPUT->box_end();
}

if ($gquizcompletion->can_complete()) {
    
    echo $OUTPUT->box_start('generalbox boxaligncenter');
    if (!$gquizcompletion->is_open()) {
        // gquiz is not yet open or is already closed.
        echo $OUTPUT->notification(get_string('gquiz_is_not_open', 'gquiz'));
        echo $OUTPUT->continue_button(course_get_url($courseid ?: $course->id));
    } else if ($gquizcompletion->can_submit()) {
        // Display a link to complete gquiz or resume.
        $completeurl = new moodle_url('/mod/gquiz/complete.php',
                ['id' => $id, 'courseid' => $courseid]);
        if ($startpage = $gquizcompletion->get_resume_page()) {
            $completeurl->param('gopage', $startpage);
            $label = get_string('continue_the_form', 'gquiz');
        } else {
            $label = get_string('complete_the_form', 'gquiz');
        }
        echo html_writer::div(html_writer::link($completeurl, $label, array('class' => 'btn btn-secondary')), 'complete-gquiz');
    } else {
        // gquiz was already submitted.
        echo $OUTPUT->notification(get_string('this_gquiz_is_already_submitted', 'gquiz'));
        $OUTPUT->continue_button(course_get_url($courseid ?: $course->id));
    }
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();

