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
 * shows an analysed view of a gquiz on the mainsite
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_gquiz
 */

require_once("../../config.php");
require_once("lib.php");

$current_tab = 'analysis';

$id = required_param('id', PARAM_INT);  //the POST dominated the GET
$courseitemfilter = optional_param('courseitemfilter', '0', PARAM_INT);
$courseitemfiltertyp = optional_param('courseitemfiltertyp', '0', PARAM_ALPHANUM);
$courseid = optional_param('courseid', false, PARAM_INT);

$url = new moodle_url('/mod/gquiz/analysis_course.php', array('id'=>$id));
navigation_node::override_active_url($url);
if ($courseid !== false) {
    $url->param('courseid', $courseid);
}
if ($courseitemfilter !== '0') {
    $url->param('courseitemfilter', $courseitemfilter);
}
if ($courseitemfiltertyp !== '0') {
    $url->param('courseitemfiltertyp', $courseitemfiltertyp);
}
$PAGE->set_url($url);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'gquiz');
$context = context_module::instance($cm->id);

require_course_login($course, true, $cm);

$feedback = $PAGE->activityrecord;

if (!($gquiz->publish_stats OR has_capability('mod/gquiz:viewreports', $context))) {
    print_error('error');
}

$gquizstructure = new mod_gquiz_structure($gquiz, $PAGE->cm, $courseid);

// Process course select form.
$courseselectform = new mod_gquiz_course_select_form($url, $gquizstructure);
if ($data = $courseselectform->get_data()) {
    redirect(new moodle_url($url, ['courseid' => $data->courseid]));
}

/// Print the page header
$strgquizs = get_string("modulenameplural", "gquiz");
$strgquiz  = get_string("modulename", "gquiz");

$PAGE->set_heading($course->fullname);
$PAGE->set_title($gquiz->name);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($gquiz->name));

/// print the tabs
require('tabs.php');

//get the groupid
//lstgroupid is the choosen id
$mygroupid = false;

$courseselectform->display();

// Button "Export to excel".
if (has_capability('mod/gquiz:viewreports', $context) && $gquizstructure->get_items()) {
    echo $OUTPUT->container_start('form-buttons');
    $aurl = new moodle_url('/mod/gquiz/analysis_to_excel.php',
        ['sesskey' => sesskey(), 'id' => $id, 'courseid' => (int)$courseid]);
    echo $OUTPUT->single_button($aurl, get_string('export_to_excel', 'gquiz'));
    echo $OUTPUT->container_end();
}

// Show the summary.
$summary = new mod_gquiz\output\summary($gquizstructure);
echo $OUTPUT->render_from_template('mod_gquiz/summary', $summary->export_for_template($OUTPUT));

// Get the items of the gquiz.
$items = $gquizstructure->get_items(true);

if ($courseitemfilter > 0) {
    $sumvalue = 'SUM(' . $DB->sql_cast_char2real('value', true) . ')';
    $sql = "SELECT fv.course_id, c.shortname, $sumvalue AS sumvalue, COUNT(value) as countvalue
            FROM {gquiz_value} fv, {course} c, {gquiz_item} fi
            WHERE fv.course_id = c.id AND fi.id = fv.item AND fi.typ = ? AND fv.item = ?
            GROUP BY course_id, shortname
            ORDER BY sumvalue desc";

    if ($courses = $DB->get_records_sql($sql, array($courseitemfiltertyp, $courseitemfilter))) {
        $item = $DB->get_record('gquiz_item', array('id'=>$courseitemfilter));
        echo '<h4>'.$item->name.'</h4>';
        echo '<div class="clearfix">';
        echo '<table>';
        echo '<tr><th>Course</th><th>Average</th></tr>';

        foreach ($courses as $c) {
            $coursecontext = context_course::instance($c->course_id);
            $shortname = format_string($c->shortname, true, array('context' => $coursecontext));

            echo '<tr>';
            echo '<td>'.$shortname.'</td>';
            echo '<td align="right">';
            echo format_float(($c->sumvalue / $c->countvalue), 2);
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>'.get_string('noresults').'</p>';
    }
    echo '<p><a href="analysis_course.php?id=' . $id . '">';
    echo get_string('back');
    echo '</a></p>';
} else {

    // Print the items in an analysed form.
    foreach ($items as $item) {
        echo '<table class="analysis">';
        $itemobj = gquiz_get_item_class($item->typ);
        $printnr = ($gquiz->autonumbering && $item->itemnr) ? ($item->itemnr . '.') : '';
        $itemobj->print_analysed($item, $printnr, $mygroupid, $gquizstructure->get_courseid());
        if (preg_match('/rated$/i', $item->typ)) {
            $url = new moodle_url('/mod/gquiz/analysis_course.php', array('id' => $id,
                'courseitemfilter' => $item->id, 'courseitemfiltertyp' => $item->typ));
            $anker = html_writer::link($url, get_string('sort_by_course', 'gquiz'));

            echo '<tr><td colspan="2">'.$anker.'</td></tr>';
        }
        echo '</table>';
    }
}

echo $OUTPUT->footer();

