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
 * prints the form to edit the gquiz items such moving, deleting and so on
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_gquiz
 */

require_once('../../config.php');
require_once('lib.php');
require_once('edit_form.php');

gquiz_init_gquiz_session();

$id = required_param('id', PARAM_INT);

if (($formdata = data_submitted()) AND !confirm_sesskey()) {
    print_error('invalidsesskey');
}

$do_show = optional_param('do_show', 'edit', PARAM_ALPHA);
$switchitemrequired = optional_param('switchitemrequired', false, PARAM_INT);
$deleteitem = optional_param('deleteitem', false, PARAM_INT);

$current_tab = $do_show;

$url = new moodle_url('/mod/gquiz/edit.php', array('id'=>$id, 'do_show'=>$do_show));

list($course, $cm) = get_course_and_cm_from_cmid($id, 'gquiz');

$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_capability('mod/gquiz:edititems', $context);
$gquiz = $PAGE->activityrecord;
$gquizstructure = new mod_gquiz_structure($gquiz, $cm);

if ($switchitemrequired) {
    require_sesskey();
    $items = $gquizstructure->get_items();
    if (isset($items[$switchitemrequired])) {
        gquiz_switch_item_required($items[$switchitemrequired]);
    }
    redirect($url);
}

if ($deleteitem) {
    require_sesskey();
    $items = $gquizstructure->get_items();
    if (isset($items[$deleteitem])) {
        gquiz_delete_item($deleteitem);
    }
    redirect($url);
}

// Process the create template form.
$cancreatetemplates = has_capability('mod/gquiz:createprivatetemplate', $context) ||
            has_capability('mod/gquiz:createpublictemplate', $context);
$create_template_form = new gquiz_edit_create_template_form(null, array('id' => $id));
if ($data = $create_template_form->get_data()) {
    // Check the capabilities to create templates.
    if (!$cancreatetemplates) {
        print_error('cannotsavetempl', 'gquiz', $url);
    }
    $ispublic = !empty($data->ispublic) ? 1 : 0;
    if (!gquiz_save_as_template($gquiz, $data->templatename, $ispublic)) {
        redirect($url, get_string('saving_failed', 'gquiz'), null, \core\output\notification::NOTIFY_ERROR);
    } else {
        redirect($url, get_string('template_saved', 'gquiz'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

//Get the gquizitems
$lastposition = 0;
$gquizitems = $DB->get_records('gquiz_item', array('gquiz'=>$gquiz->id), 'position');
if (is_array($gquizitems)) {
    $gquizitems = array_values($gquizitems);
    if (count($gquizitems) > 0) {
        $lastitem = $gquizitems[count($gquizitems)-1];
        $lastposition = $lastitem->position;
    } else {
        $lastposition = 0;
    }
}
$lastposition++;


//The use_template-form
$use_template_form = new gquiz_edit_use_template_form('use_templ.php', array('course' => $course, 'id' => $id));

//Print the page header.
$strgquizs = get_string('modulenameplural', 'gquiz');
$strgquiz  = get_string('modulename', 'gquiz');

$PAGE->set_url('/mod/gquiz/edit.php', array('id'=>$cm->id, 'do_show'=>$do_show));
$PAGE->set_heading($course->fullname);
$PAGE->set_title($gquiz->name);

//Adding the javascript module for the items dragdrop.
if (count($gquizitems) > 1) {
    if ($do_show == 'edit') {
        $PAGE->requires->strings_for_js(array(
               'pluginname',
               'move_item',
               'position',
            ), 'gquiz');
        $PAGE->requires->yui_module('moodle-mod_gquiz-dragdrop', 'M.mod_gquiz.init_dragdrop',
                array(array('cmid' => $cm->id)));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($gquiz->name));

/// print the tabs
require('tabs.php');

// Print the main part of the page.

if ($do_show == 'templates') {
    // Print the template-section.
    $use_template_form->display();

    if ($cancreatetemplates) {
        $deleteurl = new moodle_url('/mod/gquiz/delete_template.php', array('id' => $id));
        $create_template_form->display();
        echo '<p><a href="'.$deleteurl->out().'">'.
             get_string('delete_templates', 'gquiz').
             '</a></p>';
    } else {
        echo '&nbsp;';
    }

    if (has_capability('mod/gquiz:edititems', $context)) {
        $urlparams = array('action'=>'exportfile', 'id'=>$id);
        $exporturl = new moodle_url('/mod/gquiz/export.php', $urlparams);
        $importurl = new moodle_url('/mod/gquiz/import.php', array('id'=>$id));
        echo '<p>
            <a href="'.$exporturl->out().'">'.get_string('export_questions', 'gquiz').'</a>/
            <a href="'.$importurl->out().'">'.get_string('import_questions', 'gquiz').'</a>
        </p>';
    }
}

if ($do_show == 'edit') {
    // Print the Item-Edit-section.

    $select = new single_select(new moodle_url('/mod/gquiz/edit_item.php',
            array('cmid' => $id, 'position' => $lastposition, 'sesskey' => sesskey())),
        'typ', gquiz_load_gquiz_items_options());
    $select->label = get_string('add_item', 'mod_gquiz');
    echo $OUTPUT->render($select);


    $form = new mod_gquiz_complete_form(mod_gquiz_complete_form::MODE_EDIT,
            $gquizstructure, 'gquiz_edit_form');
    echo '<div id="gquiz_dragarea">'; // The container for the dragging area.
    $form->display();
    echo '</div>';
}

echo $OUTPUT->footer();
