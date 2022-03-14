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
 * prints the tabbed bar
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_gquiz
 */
defined('MOODLE_INTERNAL') OR die('not allowed');

$tabs = array();
$row  = array();
$inactive = array();
$activated = array();

//some pages deliver the cmid instead the id
if (isset($cmid) AND intval($cmid) AND $cmid > 0) {
    $usedid = $cmid;
} else {
    $usedid = $id;
}

$context = context_module::instance($usedid);

$courseid = optional_param('courseid', false, PARAM_INT);
// $current_tab = $SESSION->gquiz->current_tab;
if (!isset($current_tab)) {
    $current_tab = '';
}

$viewurl = new moodle_url('/mod/gquiz/view.php', array('id' => $usedid));
$row[] = new tabobject('view', $viewurl->out(), get_string('overview', 'gquiz'));
$urlparams = ['id' => $usedid];
if ($gquiz->course == SITEID && $courseid) {
    $urlparams['courseid'] = $courseid;
}

if (has_capability('mod/gquiz:edititems', $context)) {
    $editurl = new moodle_url('/mod/gquiz/edit.php', $urlparams + ['do_show' => 'edit']);
    $row[] = new tabobject('edit', $editurl->out(), get_string('edit_items', 'gquiz'));

    $templateurl = new moodle_url('/mod/gquiz/edit.php', $urlparams + ['do_show' => 'templates']);
    $row[] = new tabobject('templates', $templateurl->out(), get_string('templates', 'gquiz'));
}

if ($gquiz->course == SITEID && has_capability('mod/gquiz:mapcourse', $context)) {
    $mapurl = new moodle_url('/mod/gquiz/mapcourse.php', $urlparams);
    $row[] = new tabobject('mapcourse', $mapurl->out(), get_string('mappedcourses', 'gquiz'));
}

if (has_capability('mod/gquiz:viewreports', $context)) {
    if ($gquiz->course == SITEID) {
        $analysisurl = new moodle_url('/mod/gquiz/analysis_course.php', $urlparams);
    } else {
        $analysisurl = new moodle_url('/mod/gquiz/analysis.php', $urlparams);
    }
    $row[] = new tabobject('analysis', $analysisurl->out(), get_string('analysis', 'gquiz'));

    $reporturl = new moodle_url('/mod/gquiz/show_entries.php', $urlparams);
    $row[] = new tabobject('showentries',
                            $reporturl->out(),
                            get_string('show_entries', 'gquiz'));

    if ($gquiz->anonymous == gquiz_ANONYMOUS_NO AND $gquiz->course != SITEID) {
        $nonrespondenturl = new moodle_url('/mod/gquiz/show_nonrespondents.php', $urlparams);
        $row[] = new tabobject('nonrespondents',
                                $nonrespondenturl->out(),
                                get_string('show_nonrespondents', 'gquiz'));
    }
}

if (count($row) > 1) {
    $tabs[] = $row;

    print_tabs($tabs, $current_tab, $inactive, $activated);
}

