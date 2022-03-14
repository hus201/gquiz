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
 * Library of functions and constants for module gquiz
 * includes the main-part of gquiz-functions
 *
 * @package mod_gquiz
 * @copyright Andreas Grabs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/mod/gquiz/classes/markcalculator.php');
// Include forms lib.
require_once($CFG->libdir.'/formslib.php');

define('gquiz_ANONYMOUS_YES', 1);
define('gquiz_ANONYMOUS_NO', 2);
define('gquiz_MIN_ANONYMOUS_COUNT_IN_GROUP', 2);
define('gquiz_DECIMAL', '.');
define('gquiz_THOUSAND', ',');
define('gquiz_RESETFORM_RESET', 'gquiz_reset_data_');
define('gquiz_RESETFORM_DROP', 'gquiz_drop_gquiz_');
define('gquiz_MAX_PIX_LENGTH', '400'); //max. Breite des grafischen Balkens in der Auswertung
define('gquiz_DEFAULT_PAGE_COUNT', 20);

// Event types.
define('gquiz_EVENT_TYPE_OPEN', 'open');
define('gquiz_EVENT_TYPE_CLOSE', 'close');

//require_once(__DIR__ . '/deprecatedlib.php');

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function gquiz_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * this will create a new instance and return the id number
 * of the new instance.
 *
 * @global object
 * @param object $gquiz the object given by mod_gquiz_mod_form
 * @return int
 */
function gquiz_add_instance($gquiz) {
    global $DB;
    
    $gquiz->timemodified = time();
    $gquiz->id = '';

    if (empty($gquiz->site_after_submit)) {
        $gquiz->site_after_submit = '';
    }

    //saving the gquiz in db
    $gquizid = $DB->insert_record("gquiz", $gquiz);
    
    $gquiz->id = $gquizid;
    
    gquiz_set_events($gquiz);

    if (!isset($gquiz->coursemodule)) {
        $cm = get_coursemodule_from_id('gquiz', $gquiz->id);
        $gquiz->coursemodule = $cm->id;
    }
    $context = context_module::instance($gquiz->coursemodule);

    if (!empty($gquiz->completionexpected)) {
        \core_completion\api::update_completion_date_event($gquiz->coursemodule, 'gquiz', $gquiz->id,
                $gquiz->completionexpected);
    }

    $editoroptions = gquiz_get_editor_options();

    // process the custom wysiwyg editor in page_after_submit
    if ($draftitemid = $gquiz->page_after_submit_editor['itemid']) {
        $gquiz->page_after_submit = file_save_draft_area_files($draftitemid, $context->id,
                                                    'mod_gquiz', 'page_after_submit',
                                                    0, $editoroptions,
                                                    $gquiz->page_after_submit_editor['text']);

        $gquiz->page_after_submitformat = $gquiz->page_after_submit_editor['format'];
    }
    $DB->update_record('gquiz', $gquiz);
   
    return $gquizid;
    
}

/**
 * this will update a given instance
 *
 * @global object
 * @param object $gquiz the object given by mod_gquiz_mod_form
 * @return boolean
 */
function gquiz_update_instance($gquiz) {
    global $DB;

    $gquiz->timemodified = time();
    $gquiz->id = $gquiz->instance;

    if (empty($gquiz->site_after_submit)) {
        $gquiz->site_after_submit = '';
    }

    //save the gquiz into the db
    $DB->update_record("gquiz", $gquiz);

    //create or update the new events
    gquiz_set_events($gquiz);
    $completionexpected = (!empty($gquiz->completionexpected)) ? $gquiz->completionexpected : null;
    \core_completion\api::update_completion_date_event($gquiz->coursemodule, 'gquiz', $gquiz->id, $completionexpected);

    $context = context_module::instance($gquiz->coursemodule);

    $editoroptions = gquiz_get_editor_options();

    // process the custom wysiwyg editor in page_after_submit
    if ($draftitemid = $gquiz->page_after_submit_editor['itemid']) {
        $gquiz->page_after_submit = file_save_draft_area_files($draftitemid, $context->id,
                                                    'mod_gquiz', 'page_after_submit',
                                                    0, $editoroptions,
                                                    $gquiz->page_after_submit_editor['text']);

        $gquiz->page_after_submitformat = $gquiz->page_after_submit_editor['format'];
    }
    $DB->update_record('gquiz', $gquiz);

    return true;
}

/**
 * Serves the files included in gquiz items like label. Implements needed access control ;-)
 *
 * There are two situations in general where the files will be sent.
 * 1) filearea = item, 2) filearea = template
 *
 * @package  mod_gquiz
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function gquiz_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($filearea === 'item' or $filearea === 'template') {
        $itemid = (int)array_shift($args);
        //get the item what includes the file
        if (!$item = $DB->get_record('gquiz_item', array('id'=>$itemid))) {
            return false;
        }
        $gquizid = $item->gquiz;
        $templateid = $item->template;
    }

    if ($filearea === 'page_after_submit' or $filearea === 'item') {
        if (! $gquiz = $DB->get_record("gquiz", array("id"=>$cm->instance))) {
            return false;
        }

        $gquizid = $gquiz->id;

        //if the filearea is "item" so we check the permissions like view/complete the gquiz
        $canload = false;
        //first check whether the user has the complete capability
        if (has_capability('mod/gquiz:complete', $context)) {
            $canload = true;
        }

        //now we check whether the user has the view capability
        if (has_capability('mod/gquiz:view', $context)) {
            $canload = true;
        }

        //if the gquiz is on frontpage and anonymous and the fullanonymous is allowed
        //so the file can be loaded too.
        if (isset($CFG->gquiz_allowfullanonymous)
                    AND $CFG->gquiz_allowfullanonymous
                    AND $course->id == SITEID
                    AND $gquiz->anonymous == gquiz_ANONYMOUS_YES ) {
            $canload = true;
        }

        if (!$canload) {
            return false;
        }
    } else if ($filearea === 'template') { //now we check files in templates
        if (!$template = $DB->get_record('gquiz_template', array('id'=>$templateid))) {
            return false;
        }

        //if the file is not public so the capability edititems has to be there
        if (!$template->ispublic) {
            if (!has_capability('mod/gquiz:edititems', $context)) {
                return false;
            }
        } else { //on public templates, at least the user has to be logged in
            if (!isloggedin()) {
                return false;
            }
        }
    } else {
        return false;
    }

    if ($context->contextlevel == CONTEXT_MODULE) {
        if ($filearea !== 'item' and $filearea !== 'page_after_submit') {
            return false;
        }
    }

    if ($context->contextlevel == CONTEXT_COURSE || $context->contextlevel == CONTEXT_SYSTEM) {
        if ($filearea !== 'template') {
            return false;
        }
    }

    $relativepath = implode('/', $args);
    if ($filearea === 'page_after_submit') {
        $fullpath = "/{$context->id}/mod_gquiz/$filearea/$relativepath";
    } else {
        $fullpath = "/{$context->id}/mod_gquiz/$filearea/{$item->id}/$relativepath";
    }

    $fs = get_file_storage();

    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!

    return false;
}

/**
 * this will delete a given instance.
 * all referenced data also will be deleted
 *
 * @global object
 * @param int $id the instanceid of gquiz
 * @return boolean
 */
function gquiz_delete_instance($id) {
    global $DB;

    //get all referenced items
    $gquizitems = $DB->get_records('gquiz_item', array('gquiz'=>$id));

    //deleting all referenced items and values
    if (is_array($gquizitems)) {
        foreach ($gquizitems as $gquizitem) {
            $DB->delete_records("gquiz_value", array("item"=>$gquizitem->id));
            $DB->delete_records("gquiz_valuetmp", array("item"=>$gquizitem->id));
        }
        if ($delitems = $DB->get_records("gquiz_item", array("gquiz"=>$id))) {
            foreach ($delitems as $delitem) {
                gquiz_delete_item($delitem->id, false);
            }
        }
    }

    //deleting the completeds
    $DB->delete_records("gquiz_completed", array("gquiz"=>$id));

    //deleting the unfinished completeds
    $DB->delete_records("gquiz_completedtmp", array("gquiz"=>$id));

    //deleting old events
    $DB->delete_records('event', array('modulename'=>'gquiz', 'instance'=>$id));
    return $DB->delete_records("gquiz", array("id"=>$id));
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param cm_info|stdClass $mod
 * @param stdClass $gquiz
 * @return stdClass
 */
function gquiz_user_outline($course, $user, $mod, $gquiz) {
    global $DB;
    $outline = (object)['info' => '', 'time' => 0];
    if ($gquiz->anonymous != gquiz_ANONYMOUS_NO) {
        // Do not disclose any user info if gquiz is anonymous.
        return $outline;
    }
    $params = array('userid' => $user->id, 'gquiz' => $gquiz->id,
        'anonymous_response' => gquiz_ANONYMOUS_NO);
    $status = null;
    $context = context_module::instance($mod->id);
    if ($completed = $DB->get_record('gquiz_completed', $params)) {
        // User has completed gquiz.
        $outline->info = get_string('completed', 'gquiz');
        $outline->time = $completed->timemodified;
    } else if ($completedtmp = $DB->get_record('gquiz_completedtmp', $params)) {
        // User has started but not completed gquiz.
        $outline->info = get_string('started', 'gquiz');
        $outline->time = $completedtmp->timemodified;
    } else if (has_capability('mod/gquiz:complete', $context, $user)) {
        // User has not started gquiz but has capability to do so.
        $outline->info = get_string('not_started', 'gquiz');
    }

    return $outline;
}

/**
 * Returns all users who has completed a specified gquiz since a given time
 * many thanks to Manolescu Dorel, who contributed these two functions
 *
 * @global object
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param array $activities Passed by reference
 * @param int $index Passed by reference
 * @param int $timemodified Timestamp
 * @param int $courseid
 * @param int $cmid
 * @param int $userid
 * @param int $groupid
 * @return void
 */
function gquiz_get_recent_mod_activity(&$activities, &$index,
                                          $timemodified, $courseid,
                                          $cmid, $userid="", $groupid="") {

    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    $sqlargs = array();

    $userfieldsapi = \core_user\fields::for_userpic();
    $userfields = $userfieldsapi->get_sql('u', false, '', 'useridagain', false)->selects;
    $sql = " SELECT fk . * , fc . * , $userfields
                FROM {gquiz_completed} fc
                    JOIN {gquiz} fk ON fk.id = fc.gquiz
                    JOIN {user} u ON u.id = fc.userid ";

    if ($groupid) {
        $sql .= " JOIN {groups_members} gm ON  gm.userid=u.id ";
    }

    $sql .= " WHERE fc.timemodified > ?
                AND fk.id = ?
                AND fc.anonymous_response = ?";
    $sqlargs[] = $timemodified;
    $sqlargs[] = $cm->instance;
    $sqlargs[] = gquiz_ANONYMOUS_NO;

    if ($userid) {
        $sql .= " AND u.id = ? ";
        $sqlargs[] = $userid;
    }

    if ($groupid) {
        $sql .= " AND gm.groupid = ? ";
        $sqlargs[] = $groupid;
    }

    if (!$gquizitems = $DB->get_records_sql($sql, $sqlargs)) {
        return;
    }

    $cm_context = context_module::instance($cm->id);

    if (!has_capability('mod/gquiz:view', $cm_context)) {
        return;
    }

    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $cm_context);
    $groupmode       = groups_get_activity_groupmode($cm, $course);

    $aname = format_string($cm->name, true);
    foreach ($gquizitems as $gquizitem) {
        if ($gquizitem->userid != $USER->id) {

            if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
                $usersgroups = groups_get_all_groups($course->id,
                                                     $gquizitem->userid,
                                                     $cm->groupingid);
                if (!is_array($usersgroups)) {
                    continue;
                }
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }

        $tmpactivity = new stdClass();

        $tmpactivity->type      = 'gquiz';
        $tmpactivity->cmid      = $cm->id;
        $tmpactivity->name      = $aname;
        $tmpactivity->sectionnum= $cm->sectionnum;
        $tmpactivity->timestamp = $gquizitem->timemodified;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->gquizid = $gquizitem->id;
        $tmpactivity->content->gquizuserid = $gquizitem->userid;

        $tmpactivity->user = user_picture::unalias($gquizitem, null, 'useridagain');
        $tmpactivity->user->fullname = fullname($gquizitem, $viewfullnames);

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * Prints all users who has completed a specified gquiz since a given time
 * many thanks to Manolescu Dorel, who contributed these two functions
 *
 * @global object
 * @param object $activity
 * @param int $courseid
 * @param string $detail
 * @param array $modnames
 * @return void Output is echo'd
 */
function gquiz_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="forum-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid));
    echo "</td><td>";

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo $OUTPUT->image_icon('icon', $modname, $activity->type);
        echo "<a href=\"$CFG->wwwroot/mod/gquiz/view.php?id={$activity->cmid}\">{$activity->name}</a>";
        echo '</div>';
    }

    echo '<div class="title">';
    echo '</div>';

    echo '<div class="user">';
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
         ."{$activity->user->fullname}</a> - ".userdate($activity->timestamp);
    echo '</div>';

    echo "</td></tr></table>";

    return;
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param cm_info|stdClass $mod
 * @param stdClass $gquiz
 */
function gquiz_user_complete($course, $user, $mod, $gquiz) {
    global $DB;
    if ($gquiz->anonymous != gquiz_ANONYMOUS_NO) {
        // Do not disclose any user info if gquiz is anonymous.
        return;
    }
    $params = array('userid' => $user->id, 'gquiz' => $gquiz->id,
        'anonymous_response' => gquiz_ANONYMOUS_NO);
    $url = $status = null;
    $context = context_module::instance($mod->id);
    if ($completed = $DB->get_record('gquiz_completed', $params)) {
        // User has completed gquiz.
        if (has_capability('mod/gquiz:viewreports', $context)) {
            $url = new moodle_url('/mod/gquiz/show_entries.php',
                ['id' => $mod->id, 'userid' => $user->id,
                    'showcompleted' => $completed->id]);
        }
        $status = get_string('completedon', 'gquiz', userdate($completed->timemodified));
    } else if ($completedtmp = $DB->get_record('gquiz_completedtmp', $params)) {
        // User has started but not completed gquiz.
        $status = get_string('startedon', 'gquiz', userdate($completedtmp->timemodified));
    } else if (has_capability('mod/gquiz:complete', $context, $user)) {
        // User has not started gquiz but has capability to do so.
        $status = get_string('not_started', 'gquiz');
    }

    if ($url && $status) {
        echo html_writer::link($url, $status);
    } else if ($status) {
        echo html_writer::div($status);
    }
}

/**
 * @return bool true
 */
function gquiz_cron () {
    return true;
}

/**
 * @deprecated since Moodle 3.8
 */
function gquiz_scale_used() {
    throw new coding_exception('gquiz_scale_used() can not be used anymore. Plugins can implement ' .
        '<modname>_scale_used_anywhere, all implementations of <modname>_scale_used are now ignored');
}

/**
 * Checks if scale is being used by any instance of gquiz
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any assignment
 */
function gquiz_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function gquiz_get_view_actions() {
    return array('view', 'view all');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function gquiz_get_post_actions() {
    return array('submit');
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all responses from the specified gquiz
 * and clean up any related data.
 *
 * @global object
 * @global object
 * @uses gquiz_RESETFORM_RESET
 * @uses gquiz_RESETFORM_DROP
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function gquiz_reset_userdata($data) {
    global $CFG, $DB;

    $resetgquizs = array();
    $dropgquizs = array();
    $status = array();
    $componentstr = get_string('modulenameplural', 'gquiz');

    //get the relevant entries from $data
    foreach ($data as $key => $value) {
        switch(true) {
            case substr($key, 0, strlen(gquiz_RESETFORM_RESET)) == gquiz_RESETFORM_RESET:
                if ($value == 1) {
                    $templist = explode('_', $key);
                    if (isset($templist[3])) {
                        $resetgquizs[] = intval($templist[3]);
                    }
                }
            break;
            case substr($key, 0, strlen(gquiz_RESETFORM_DROP)) == gquiz_RESETFORM_DROP:
                if ($value == 1) {
                    $templist = explode('_', $key);
                    if (isset($templist[3])) {
                        $dropgquizs[] = intval($templist[3]);
                    }
                }
            break;
        }
    }

    //reset the selected gquizs
    foreach ($resetgquizs as $id) {
        $gquiz = $DB->get_record('gquiz', array('id'=>$id));
        gquiz_delete_all_completeds($gquiz);
        $status[] = array('component'=>$componentstr.':'.$gquiz->name,
                        'item'=>get_string('resetting_data', 'gquiz'),
                        'error'=>false);
    }

    // Updating dates - shift may be negative too.
    if ($data->timeshift) {
        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        $shifterror = !shift_course_mod_dates('gquiz', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component' => $componentstr, 'item' => get_string('datechanged'), 'error' => $shifterror);
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @global object
 * @uses gquiz_RESETFORM_RESET
 * @param object $mform form passed by reference
 */
function gquiz_reset_course_form_definition(&$mform) {
    global $COURSE, $DB;

    $mform->addElement('header', 'gquizheader', get_string('modulenameplural', 'gquiz'));

    if (!$gquizs = $DB->get_records('gquiz', array('course'=>$COURSE->id), 'name')) {
        return;
    }

    $mform->addElement('static', 'hint', get_string('resetting_data', 'gquiz'));
    foreach ($gquizs as $gquiz) {
        $mform->addElement('checkbox', gquiz_RESETFORM_RESET.$gquiz->id, $gquiz->name);
    }
}

/**
 * Course reset form defaults.
 *
 * @global object
 * @uses gquiz_RESETFORM_RESET
 * @param object $course
 */
function gquiz_reset_course_form_defaults($course) {
    global $DB;

    $return = array();
    if (!$gquizs = $DB->get_records('gquiz', array('course'=>$course->id), 'name')) {
        return;
    }
    foreach ($gquizs as $gquiz) {
        $return[gquiz_RESETFORM_RESET.$gquiz->id] = true;
    }
    return $return;
}

/**
 * Called by course/reset.php and shows the formdata by coursereset.
 * it prints checkboxes for each gquiz available at the given course
 * there are two checkboxes:
 * 1) delete userdata and keep the gquiz
 * 2) delete userdata and drop the gquiz
 *
 * @global object
 * @uses gquiz_RESETFORM_RESET
 * @uses gquiz_RESETFORM_DROP
 * @param object $course
 * @return void
 */
function gquiz_reset_course_form($course) {
    global $DB, $OUTPUT;

    echo get_string('resetting_gquizs', 'gquiz'); echo ':<br />';
    if (!$gquizs = $DB->get_records('gquiz', array('course'=>$course->id), 'name')) {
        return;
    }

    foreach ($gquizs as $gquiz) {
        echo '<p>';
        echo get_string('name', 'gquiz').': '.$gquiz->name.'<br />';
        echo html_writer::checkbox(gquiz_RESETFORM_RESET.$gquiz->id,
                                1, true,
                                get_string('resetting_data', 'gquiz'));
        echo '<br />';
        echo html_writer::checkbox(gquiz_RESETFORM_DROP.$gquiz->id,
                                1, false,
                                get_string('drop_gquiz', 'gquiz'));
        echo '</p>';
    }
}

/**
 * This gets an array with default options for the editor
 *
 * @return array the options
 */
function gquiz_get_editor_options() {
    return array('maxfiles' => EDITOR_UNLIMITED_FILES,
                'trusttext'=>true);
}

/**
 * This creates new events given as timeopen and closeopen by $gquiz.
 *
 * @global object
 * @param object $gquiz
 * @return void
 */
function gquiz_set_events($gquiz) {
    global $DB, $CFG;

    // Include calendar/lib.php.
    require_once($CFG->dirroot.'/calendar/lib.php');

    // Get CMID if not sent as part of $gquiz.
    if (!isset($gquiz->coursemodule)) {
        $cm = get_coursemodule_from_instance('gquiz', $gquiz->id, $gquiz->course);
        $gquiz->coursemodule = $cm->id;
    }

    // gquiz start calendar events.
    $eventid = $DB->get_field('event', 'id',
            array('modulename' => 'gquiz', 'instance' => $gquiz->id, 'eventtype' => gquiz_EVENT_TYPE_OPEN));

    if (isset($gquiz->timeopen) && $gquiz->timeopen > 0) {
        $event = new stdClass();
        $event->eventtype    = gquiz_EVENT_TYPE_OPEN;
        $event->type         = empty($gquiz->timeclose) ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->name         = get_string('calendarstart', 'gquiz', $gquiz->name);
        $event->description  = format_module_intro('gquiz', $gquiz, $gquiz->coursemodule, false);
        $event->format       = FORMAT_HTML;
        $event->timestart    = $gquiz->timeopen;
        $event->timesort     = $gquiz->timeopen;
        $event->visible      = instance_is_visible('gquiz', $gquiz);
        $event->timeduration = 0;
        if ($eventid) {
            // Calendar event exists so update it.
            $event->id = $eventid;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Event doesn't exist so create one.
            $event->courseid     = $gquiz->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'gquiz';
            $event->instance     = $gquiz->id;
            $event->eventtype    = gquiz_EVENT_TYPE_OPEN;
            calendar_event::create($event, false);
        }
    } else if ($eventid) {
        // Calendar event is on longer needed.
        $calendarevent = calendar_event::load($eventid);
        $calendarevent->delete();
    }

    // gquiz close calendar events.
    $eventid = $DB->get_field('event', 'id',
            array('modulename' => 'gquiz', 'instance' => $gquiz->id, 'eventtype' => gquiz_EVENT_TYPE_CLOSE));

    if (isset($gquiz->timeclose) && $gquiz->timeclose > 0) {
        $event = new stdClass();
        $event->type         = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype    = gquiz_EVENT_TYPE_CLOSE;
        $event->name         = get_string('calendarend', 'gquiz', $gquiz->name);
        $event->description  = format_module_intro('gquiz', $gquiz, $gquiz->coursemodule, false);
        $event->format       = FORMAT_HTML;
        $event->timestart    = $gquiz->timeclose;
        $event->timesort     = $gquiz->timeclose;
        $event->visible      = instance_is_visible('gquiz', $gquiz);
        $event->timeduration = 0;
        if ($eventid) {
            // Calendar event exists so update it.
            $event->id = $eventid;
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            // Event doesn't exist so create one.
            $event->courseid     = $gquiz->course;
            $event->groupid      = 0;
            $event->userid       = 0;
            $event->modulename   = 'gquiz';
            $event->instance     = $gquiz->id;
            calendar_event::create($event, false);
        }
    } else if ($eventid) {
        // Calendar event is on longer needed.
        $calendarevent = calendar_event::load($eventid);
        $calendarevent->delete();
    }
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every gquiz event in the site is checked, else
 * only gquiz events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance gquiz module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function gquiz_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB;

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('gquiz', array('id' => $instance), '*', MUST_EXIST);
        }
        gquiz_set_events($instance);
        return true;
    }

    if ($courseid) {
        if (! $gquizs = $DB->get_records("gquiz", array("course" => $courseid))) {
            return true;
        }
    } else {
        if (! $gquizs = $DB->get_records("gquiz")) {
            return true;
        }
    }

    foreach ($gquizs as $gquiz) {
        gquiz_set_events($gquiz);
    }
    return true;
}

/**
 * this function is called by {@link gquiz_delete_userdata()}
 * it drops the gquiz-instance from the course_module table
 *
 * @global object
 * @param int $id the id from the coursemodule
 * @return boolean
 */
function gquiz_delete_course_module($id) {
    global $DB;

    if (!$cm = $DB->get_record('course_modules', array('id'=>$id))) {
        return true;
    }
    return $DB->delete_records('course_modules', array('id'=>$cm->id));
}



////////////////////////////////////////////////
//functions to handle capabilities
////////////////////////////////////////////////

/**
 * @deprecated since 3.1
 */
function gquiz_get_context() {
    throw new coding_exception('gquiz_get_context() can not be used anymore.');
}

/**
 *  returns true if the current role is faked by switching role feature
 *
 * @global object
 * @return boolean
 */
function gquiz_check_is_switchrole() {
    global $USER;
    if (isset($USER->switchrole) AND
            is_array($USER->switchrole) AND
            count($USER->switchrole) > 0) {

        return true;
    }
    return false;
}

/**
 * count users which have not completed the gquiz
 *
 * @global object
 * @uses CONTEXT_MODULE
 * @param cm_info $cm Course-module object
 * @param int $group single groupid
 * @param string $sort
 * @param int $startpage
 * @param int $pagecount
 * @param bool $includestatus to return if the user started or not the gquiz among the complete user record
 * @return array array of user ids or user objects when $includestatus set to true
 */
function gquiz_get_incomplete_users(cm_info $cm,
                                       $group = false,
                                       $sort = '',
                                       $startpage = false,
                                       $pagecount = false,
                                       $includestatus = false) {

    global $DB;

    $context = context_module::instance($cm->id);

    //first get all user who can complete this gquiz
    $cap = 'mod/gquiz:complete';
    $userfieldsapi = \core_user\fields::for_name();
    $allnames = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
    $fields = 'u.id, ' . $allnames . ', u.picture, u.email, u.imagealt';
    if (!$allusers = get_users_by_capability($context,
                                            $cap,
                                            $fields,
                                            $sort,
                                            '',
                                            '',
                                            $group,
                                            '',
                                            true)) {
        return false;
    }
    // Filter users that are not in the correct group/grouping.
    $info = new \core_availability\info_module($cm);
    $allusersrecords = $info->filter_user_list($allusers);

    $allusers = array_keys($allusersrecords);

    //now get all completeds
    $params = array('gquiz'=>$cm->instance);
    if ($completedusers = $DB->get_records_menu('gquiz_completed', $params, '', 'id, userid')) {
        // Now strike all completedusers from allusers.
        $allusers = array_diff($allusers, $completedusers);
    }

    //for paging I use array_slice()
    if ($startpage !== false AND $pagecount !== false) {
        $allusers = array_slice($allusers, $startpage, $pagecount);
    }

    // Check if we should return the full users objects.
    if ($includestatus) {
        $userrecords = [];
        $startedusers = $DB->get_records_menu('gquiz_completedtmp', ['gquiz' => $cm->instance], '', 'id, userid');
        $startedusers = array_flip($startedusers);
        foreach ($allusers as $userid) {
            $allusersrecords[$userid]->gquizstarted = isset($startedusers[$userid]);
            $userrecords[] = $allusersrecords[$userid];
        }
        return $userrecords;
    } else {    // Return just user ids.
        return $allusers;
    }
}

/**
 * count users which have not completed the gquiz
 *
 * @global object
 * @param object $cm
 * @param int $group single groupid
 * @return int count of userrecords
 */
function gquiz_count_incomplete_users($cm, $group = false) {
    if ($allusers = gquiz_get_incomplete_users($cm, $group)) {
        return count($allusers);
    }
    return 0;
}

/**
 * count users which have completed a gquiz
 *
 * @global object
 * @uses gquiz_ANONYMOUS_NO
 * @param object $cm
 * @param int $group single groupid
 * @return int count of userrecords
 */
function gquiz_count_complete_users($cm, $group = false) {
    global $DB;

    $params = array(gquiz_ANONYMOUS_NO, $cm->instance);

    $fromgroup = '';
    $wheregroup = '';
    if ($group) {
        $fromgroup = ', {groups_members} g';
        $wheregroup = ' AND g.groupid = ? AND g.userid = c.userid';
        $params[] = $group;
    }

    $sql = 'SELECT COUNT(u.id) FROM {user} u, {gquiz_completed} c'.$fromgroup.'
              WHERE anonymous_response = ? AND u.id = c.userid AND c.gquiz = ?
              '.$wheregroup;

    return $DB->count_records_sql($sql, $params);

}

/**
 * get users which have completed a gquiz
 *
 * @global object
 * @uses CONTEXT_MODULE
 * @uses gquiz_ANONYMOUS_NO
 * @param object $cm
 * @param int $group single groupid
 * @param string $where a sql where condition (must end with " AND ")
 * @param array parameters used in $where
 * @param string $sort a table field
 * @param int $startpage
 * @param int $pagecount
 * @return object the userrecords
 */
function gquiz_get_complete_users($cm,
                                     $group = false,
                                     $where = '',
                                     array $params = null,
                                     $sort = '',
                                     $startpage = false,
                                     $pagecount = false) {

    global $DB;

    $context = context_module::instance($cm->id);

    $params = (array)$params;

    $params['anon'] = gquiz_ANONYMOUS_NO;
    $params['instance'] = $cm->instance;

    $fromgroup = '';
    $wheregroup = '';
    if ($group) {
        $fromgroup = ', {groups_members} g';
        $wheregroup = ' AND g.groupid = :group AND g.userid = c.userid';
        $params['group'] = $group;
    }

    if ($sort) {
        $sortsql = ' ORDER BY '.$sort;
    } else {
        $sortsql = '';
    }

    $userfieldsapi = \core_user\fields::for_userpic();
    $ufields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
    $sql = 'SELECT DISTINCT '.$ufields.', c.timemodified as completed_timemodified
            FROM {user} u, {gquiz_completed} c '.$fromgroup.'
            WHERE '.$where.' anonymous_response = :anon
                AND u.id = c.userid
                AND c.gquiz = :instance
              '.$wheregroup.$sortsql;

    if ($startpage === false OR $pagecount === false) {
        $startpage = false;
        $pagecount = false;
    }
    return $DB->get_records_sql($sql, $params, $startpage, $pagecount);
}

/**
 * get users which have the viewreports-capability
 *
 * @uses CONTEXT_MODULE
 * @param int $cmid
 * @param mixed $groups single groupid or array of groupids - group(s) user is in
 * @return object the userrecords
 */
function gquiz_get_viewreports_users($cmid, $groups = false) {

    $context = context_module::instance($cmid);

    //description of the call below:
    //get_users_by_capability($context, $capability, $fields='', $sort='', $limitfrom='',
    //                          $limitnum='', $groups='', $exceptions='', $doanything=true)
    return get_users_by_capability($context,
                            'mod/gquiz:viewreports',
                            '',
                            'lastname',
                            '',
                            '',
                            $groups,
                            '',
                            false);
}

/**
 * get users which have the receivemail-capability
 *
 * @uses CONTEXT_MODULE
 * @param int $cmid
 * @param mixed $groups single groupid or array of groupids - group(s) user is in
 * @return object the userrecords
 */
function gquiz_get_receivemail_users($cmid, $groups = false) {

    $context = context_module::instance($cmid);

    //description of the call below:
    //get_users_by_capability($context, $capability, $fields='', $sort='', $limitfrom='',
    //                          $limitnum='', $groups='', $exceptions='', $doanything=true)
    return get_users_by_capability($context,
                            'mod/gquiz:receivemail',
                            '',
                            'lastname',
                            '',
                            '',
                            $groups,
                            '',
                            false);
}

////////////////////////////////////////////////
//functions to handle the templates
////////////////////////////////////////////////
////////////////////////////////////////////////

/**
 * creates a new template-record.
 *
 * @global object
 * @param int $courseid
 * @param string $name the name of template shown in the templatelist
 * @param int $ispublic 0:privat 1:public
 * @return int the new templateid
 */
function gquiz_create_template($courseid, $name, $ispublic = 0) {
    global $DB;

    $templ = new stdClass();
    $templ->course   = ($ispublic ? 0 : $courseid);
    $templ->name     = $name;
    $templ->ispublic = $ispublic;

    $templid = $DB->insert_record('gquiz_template', $templ);
    return $DB->get_record('gquiz_template', array('id'=>$templid));
}

/**
 * creates new template items.
 * all items will be copied and the attribute gquiz will be set to 0
 * and the attribute template will be set to the new templateid
 *
 * @global object
 * @uses CONTEXT_MODULE
 * @uses CONTEXT_COURSE
 * @param object $gquiz
 * @param string $name the name of template shown in the templatelist
 * @param int $ispublic 0:privat 1:public
 * @return boolean
 */
function gquiz_save_as_template($gquiz, $name, $ispublic = 0) {
    global $DB;
    $fs = get_file_storage();

    if (!$gquizitems = $DB->get_records('gquiz_item', array('gquiz'=>$gquiz->id))) {
        return false;
    }

    if (!$newtempl = gquiz_create_template($gquiz->course, $name, $ispublic)) {
        return false;
    }

    //files in the template_item are in the context of the current course or
    //if the template is public the files are in the system context
    //files in the gquiz_item are in the gquiz_context of the gquiz
    if ($ispublic) {
        $s_context = context_system::instance();
    } else {
        $s_context = context_course::instance($newtempl->course);
    }
    $cm = get_coursemodule_from_instance('gquiz', $gquiz->id);
    $f_context = context_module::instance($cm->id);

    //create items of this new template
    //depend items we are storing temporary in an mapping list array(new id => dependitem)
    //we also store a mapping of all items array(oldid => newid)
    $dependitemsmap = array();
    $itembackup = array();
    foreach ($gquizitems as $item) {

        $t_item = clone($item);

        unset($t_item->id);
        $t_item->gquiz = 0;
        $t_item->template     = $newtempl->id;
        $t_item->id = $DB->insert_record('gquiz_item', $t_item);
        //copy all included files to the gquiz_template filearea
        $itemfiles = $fs->get_area_files($f_context->id,
                                    'mod_gquiz',
                                    'item',
                                    $item->id,
                                    "id",
                                    false);
        if ($itemfiles) {
            foreach ($itemfiles as $ifile) {
                $file_record = new stdClass();
                $file_record->contextid = $s_context->id;
                $file_record->component = 'mod_gquiz';
                $file_record->filearea = 'template';
                $file_record->itemid = $t_item->id;
                $fs->create_file_from_storedfile($file_record, $ifile);
            }
        }

        $itembackup[$item->id] = $t_item->id;
        if ($t_item->dependitem) {
            $dependitemsmap[$t_item->id] = $t_item->dependitem;
        }

    }

    //remapping the dependency
    foreach ($dependitemsmap as $key => $dependitem) {
        $newitem = $DB->get_record('gquiz_item', array('id'=>$key));
        $newitem->dependitem = $itembackup[$newitem->dependitem];
        $DB->update_record('gquiz_item', $newitem);
    }

    return true;
}

/**
 * deletes all gquiz_items related to the given template id
 *
 * @global object
 * @uses CONTEXT_COURSE
 * @param object $template the template
 * @return void
 */
function gquiz_delete_template($template) {
    global $DB;

    //deleting the files from the item is done by gquiz_delete_item
    if ($t_items = $DB->get_records("gquiz_item", array("template"=>$template->id))) {
        foreach ($t_items as $t_item) {
            gquiz_delete_item($t_item->id, false, $template);
        }
    }
    $DB->delete_records("gquiz_template", array("id"=>$template->id));
}

/**
 * creates new gquiz_item-records from template.
 * if $deleteold is set true so the existing items of the given gquiz will be deleted
 * if $deleteold is set false so the new items will be appanded to the old items
 *
 * @global object
 * @uses CONTEXT_COURSE
 * @uses CONTEXT_MODULE
 * @param object $gquiz
 * @param int $templateid
 * @param boolean $deleteold
 */
function gquiz_items_from_template($gquiz, $templateid, $deleteold = false) {
    global $DB, $CFG;

    require_once($CFG->libdir.'/completionlib.php');

    $fs = get_file_storage();

    if (!$template = $DB->get_record('gquiz_template', array('id'=>$templateid))) {
        return false;
    }
    //get all templateitems
    if (!$templitems = $DB->get_records('gquiz_item', array('template'=>$templateid))) {
        return false;
    }

    //files in the template_item are in the context of the current course
    //files in the gquiz_item are in the gquiz_context of the gquiz
    if ($template->ispublic) {
        $s_context = context_system::instance();
    } else {
        $s_context = context_course::instance($gquiz->course);
    }
    $course = $DB->get_record('course', array('id'=>$gquiz->course));
    $cm = get_coursemodule_from_instance('gquiz', $gquiz->id);
    $f_context = context_module::instance($cm->id);

    //if deleteold then delete all old items before
    //get all items
    if ($deleteold) {
        if ($gquizitems = $DB->get_records('gquiz_item', array('gquiz'=>$gquiz->id))) {
            //delete all items of this gquiz
            foreach ($gquizitems as $item) {
                gquiz_delete_item($item->id, false);
            }

            $params = array('gquiz'=>$gquiz->id);
            if ($completeds = $DB->get_records('gquiz_completed', $params)) {
                $completion = new completion_info($course);
                foreach ($completeds as $completed) {
                    $DB->delete_records('gquiz_completed', array('id' => $completed->id));
                    // Update completion state
                    if ($completion->is_enabled($cm) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC &&
                            $gquiz->completionsubmit) {
                        $completion->update_state($cm, COMPLETION_INCOMPLETE, $completed->userid);
                    }
                }
            }
            $DB->delete_records('gquiz_completedtmp', array('gquiz'=>$gquiz->id));
        }
        $positionoffset = 0;
    } else {
        //if the old items are kept the new items will be appended
        //therefor the new position has an offset
        $positionoffset = $DB->count_records('gquiz_item', array('gquiz'=>$gquiz->id));
    }

    //create items of this new template
    //depend items we are storing temporary in an mapping list array(new id => dependitem)
    //we also store a mapping of all items array(oldid => newid)
    $dependitemsmap = array();
    $itembackup = array();
    foreach ($templitems as $t_item) {
        $item = clone($t_item);
        unset($item->id);
        $item->gquiz = $gquiz->id;
        $item->template = 0;
        $item->position = $item->position + $positionoffset;

        $item->id = $DB->insert_record('gquiz_item', $item);

        //moving the files to the new item
        $templatefiles = $fs->get_area_files($s_context->id,
                                        'mod_gquiz',
                                        'template',
                                        $t_item->id,
                                        "id",
                                        false);
        if ($templatefiles) {
            foreach ($templatefiles as $tfile) {
                $file_record = new stdClass();
                $file_record->contextid = $f_context->id;
                $file_record->component = 'mod_gquiz';
                $file_record->filearea = 'item';
                $file_record->itemid = $item->id;
                $fs->create_file_from_storedfile($file_record, $tfile);
            }
        }

        $itembackup[$t_item->id] = $item->id;
        if ($item->dependitem) {
            $dependitemsmap[$item->id] = $item->dependitem;
        }
    }

    //remapping the dependency
    foreach ($dependitemsmap as $key => $dependitem) {
        $newitem = $DB->get_record('gquiz_item', array('id'=>$key));
        $newitem->dependitem = $itembackup[$newitem->dependitem];
        $DB->update_record('gquiz_item', $newitem);
    }
}

/**
 * get the list of available templates.
 * if the $onlyown param is set true so only templates from own course will be served
 * this is important for droping templates
 *
 * @global object
 * @param object $course
 * @param string $onlyownorpublic
 * @return array the template recordsets
 */
function gquiz_get_template_list($course, $onlyownorpublic = '') {
    global $DB, $CFG;

    switch($onlyownorpublic) {
        case '':
            $templates = $DB->get_records_select('gquiz_template',
                                                 'course = ? OR ispublic = 1',
                                                 array($course->id),
                                                 'name');
            break;
        case 'own':
            $templates = $DB->get_records('gquiz_template',
                                          array('course'=>$course->id),
                                          'name');
            break;
        case 'public':
            $templates = $DB->get_records('gquiz_template', array('ispublic'=>1), 'name');
            break;
    }
    return $templates;
}

////////////////////////////////////////////////
//Handling der Items
////////////////////////////////////////////////
////////////////////////////////////////////////

/**
 * load the lib.php from item-plugin-dir and returns the instance of the itemclass
 *
 * @param string $typ
 * @return gquiz_item_base the instance of itemclass
 */
function gquiz_get_item_class($typ) {
    global $CFG;

    //get the class of item-typ
    $itemclass = 'gquiz_item_'.$typ;
    //get the instance of item-class
    if (!class_exists($itemclass)) {
        require_once($CFG->dirroot.'/mod/gquiz/item/'.$typ.'/lib.php');
    }
    return new $itemclass();
}

/**
 * load the available item plugins from given subdirectory of $CFG->dirroot
 * the default is "mod/gquiz/item"
 *
 * @global object
 * @param string $dir the subdir
 * @return array pluginnames as string
 */
function gquiz_load_gquiz_items($dir = 'mod/gquiz/item') {
    global $CFG;
    $names = get_list_of_plugins($dir);
    $ret_names = array();

    foreach ($names as $name) {
        require_once($CFG->dirroot.'/'.$dir.'/'.$name.'/lib.php');
        if (class_exists('gquiz_item_'.$name)) {
            $ret_names[] = $name;
        }
    }
    return $ret_names;
}

/**
 * load the available item plugins to use as dropdown-options
 *
 * @global object
 * @return array pluginnames as string
 */
function gquiz_load_gquiz_items_options() {
    global $CFG;

    $gquiz_options = array("pagebreak" => get_string('add_pagebreak', 'gquiz'));

    if (!$gquiz_names = gquiz_load_gquiz_items('mod/gquiz/item')) {
        return array();
    }

    foreach ($gquiz_names as $fn) {
        $gquiz_options[$fn] = get_string($fn, 'gquiz');
    }
    asort($gquiz_options);
    return $gquiz_options;
}

/**
 * load the available items for the depend item dropdown list shown in the edit_item form
 *
 * @global object
 * @param object $gquiz
 * @param object $item the item of the edit_item form
 * @return array all items except the item $item, labels and pagebreaks
 */
function gquiz_get_depend_candidates_for_item($gquiz, $item) {
    global $DB;
    //all items for dependitem
    $where = "gquiz = ? AND typ != 'pagebreak' AND hasvalue = 1";
    $params = array($gquiz->id);
    if (isset($item->id) AND $item->id) {
        $where .= ' AND id != ?';
        $params[] = $item->id;
    }
    $dependitems = array(0 => get_string('choose'));
    $gquizitems = $DB->get_records_select_menu('gquiz_item',
                                                  $where,
                                                  $params,
                                                  'position',
                                                  'id, label');

    if (!$gquizitems) {
        return $dependitems;
    }
    //adding the choose-option
    foreach ($gquizitems as $key => $val) {
        if (trim(strval($val)) !== '') {
            $dependitems[$key] = format_string($val);
        }
    }
    return $dependitems;
}

/**
 * @deprecated since 3.1
 */
function gquiz_create_item() {
    throw new coding_exception('gquiz_create_item() can not be used anymore.');
}

/**
 * save the changes of a given item.
 *
 * @global object
 * @param object $item
 * @return boolean
 */
function gquiz_update_item($item) {
    global $DB;
    return $DB->update_record("gquiz_item", $item);
}

/**
 * deletes an item and also deletes all related values
 *
 * @global object
 * @uses CONTEXT_MODULE
 * @param int $itemid
 * @param boolean $renumber should the kept items renumbered Yes/No
 * @param object $template if the template is given so the items are bound to it
 * @return void
 */
function gquiz_delete_item($itemid, $renumber = true, $template = false) {
    global $DB;

    $item = $DB->get_record('gquiz_item', array('id'=>$itemid));

    //deleting the files from the item
    $fs = get_file_storage();

    if ($template) {
        if ($template->ispublic) {
            $context = context_system::instance();
        } else {
            $context = context_course::instance($template->course);
        }
        $templatefiles = $fs->get_area_files($context->id,
                                    'mod_gquiz',
                                    'template',
                                    $item->id,
                                    "id",
                                    false);

        if ($templatefiles) {
            $fs->delete_area_files($context->id, 'mod_gquiz', 'template', $item->id);
        }
    } else {
        if (!$cm = get_coursemodule_from_instance('gquiz', $item->gquiz)) {
            return false;
        }
        $context = context_module::instance($cm->id);

        $itemfiles = $fs->get_area_files($context->id,
                                    'mod_gquiz',
                                    'item',
                                    $item->id,
                                    "id", false);

        if ($itemfiles) {
            $fs->delete_area_files($context->id, 'mod_gquiz', 'item', $item->id);
        }
    }

    $DB->delete_records("gquiz_value", array("item"=>$itemid));
    $DB->delete_records("gquiz_valuetmp", array("item"=>$itemid));

    //remove all depends
    $DB->set_field('gquiz_item', 'dependvalue', '', array('dependitem'=>$itemid));
    $DB->set_field('gquiz_item', 'dependitem', 0, array('dependitem'=>$itemid));


    $DB->delete_records("gquiz_item", array("id"=>$itemid));
    // Added by gquiz Start
    $DB->delete_records('gquiz_graded_qustions',array('itemid'=>$itemid));
    mark_calculator::recalcluate_marks(0,$item->gquiz);    
    // Added by gquiz End
    if ($renumber) {
        gquiz_renumber_items($item->gquiz);
    }
}

/**
 * deletes all items of the given gquizid
 *
 * @global object
 * @param int $gquizid
 * @return void
 */
function gquiz_delete_all_items($gquizid) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    if (!$gquiz = $DB->get_record('gquiz', array('id'=>$gquizid))) {
        return false;
    }

    if (!$cm = get_coursemodule_from_instance('gquiz', $gquiz->id)) {
        return false;
    }

    if (!$course = $DB->get_record('course', array('id'=>$gquiz->course))) {
        return false;
    }

    if (!$items = $DB->get_records('gquiz_item', array('gquiz'=>$gquizid))) {
        return;
    }
    foreach ($items as $item) {
        gquiz_delete_item($item->id, false);
    }
    if ($completeds = $DB->get_records('gquiz_completed', array('gquiz'=>$gquiz->id))) {
        $completion = new completion_info($course);
        foreach ($completeds as $completed) {
            $DB->delete_records('gquiz_completed', array('id' => $completed->id));
            // Update completion state
            if ($completion->is_enabled($cm) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC &&
                    $gquiz->completionsubmit) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $completed->userid);
            }
        }
    }

    $DB->delete_records('gquiz_completedtmp', array('gquiz'=>$gquizid));

}

/**
 * this function toggled the item-attribute required (yes/no)
 *
 * @global object
 * @param object $item
 * @return boolean
 */
function gquiz_switch_item_required($item) {
    global $DB, $CFG;

    $itemobj = gquiz_get_item_class($item->typ);

    if ($itemobj->can_switch_require()) {
        $new_require_val = (int)!(bool)$item->required;
        $params = array('id'=>$item->id);
        $DB->set_field('gquiz_item', 'required', $new_require_val, $params);
    }
    return true;
}

/**
 * renumbers all items of the given gquizid
 *
 * @global object
 * @param int $gquizid
 * @return void
 */
function gquiz_renumber_items($gquizid) {
    global $DB;

    $items = $DB->get_records('gquiz_item', array('gquiz'=>$gquizid), 'position');
    $pos = 1;
    if ($items) {
        foreach ($items as $item) {
            $DB->set_field('gquiz_item', 'position', $pos, array('id'=>$item->id));
            $pos++;
        }
    }
}

/**
 * this decreases the position of the given item
 *
 * @global object
 * @param object $item
 * @return bool
 */
function gquiz_moveup_item($item) {
    global $DB;

    if ($item->position == 1) {
        return true;
    }

    $params = array('gquiz'=>$item->gquiz);
    if (!$items = $DB->get_records('gquiz_item', $params, 'position')) {
        return false;
    }

    $itembefore = null;
    foreach ($items as $i) {
        if ($i->id == $item->id) {
            if (is_null($itembefore)) {
                return true;
            }
            $itembefore->position = $item->position;
            $item->position--;
            gquiz_update_item($itembefore);
            gquiz_update_item($item);
            gquiz_renumber_items($item->gquiz);
            return true;
        }
        $itembefore = $i;
    }
    return false;
}

/**
 * this increased the position of the given item
 *
 * @global object
 * @param object $item
 * @return bool
 */
function gquiz_movedown_item($item) {
    global $DB;

    $params = array('gquiz'=>$item->gquiz);
    if (!$items = $DB->get_records('gquiz_item', $params, 'position')) {
        return false;
    }

    $movedownitem = null;
    foreach ($items as $i) {
        if (!is_null($movedownitem) AND $movedownitem->id == $item->id) {
            $movedownitem->position = $i->position;
            $i->position--;
            gquiz_update_item($movedownitem);
            gquiz_update_item($i);
            gquiz_renumber_items($item->gquiz);
            return true;
        }
        $movedownitem = $i;
    }
    return false;
}

/**
 * here the position of the given item will be set to the value in $pos
 *
 * @global object
 * @param object $moveitem
 * @param int $pos
 * @return boolean
 */
function gquiz_move_item($moveitem, $pos) {
    global $DB;

    $params = array('gquiz'=>$moveitem->gquiz);
    if (!$allitems = $DB->get_records('gquiz_item', $params, 'position')) {
        return false;
    }
    if (is_array($allitems)) {
        $index = 1;
        foreach ($allitems as $item) {
            if ($index == $pos) {
                $index++;
            }
            if ($item->id == $moveitem->id) {
                $moveitem->position = $pos;
                gquiz_update_item($moveitem);
                continue;
            }
            $item->position = $index;
            gquiz_update_item($item);
            $index++;
        }
        return true;
    }
    return false;
}

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_print_item_preview() {
    throw new coding_exception('gquiz_print_item_preview() can not be used anymore. '
            . 'Items must implement complete_form_element().');
}

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_print_item_complete() {
    throw new coding_exception('gquiz_print_item_complete() can not be used anymore. '
        . 'Items must implement complete_form_element().');
}

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_print_item_show_value() {
    throw new coding_exception('gquiz_print_item_show_value() can not be used anymore. '
        . 'Items must implement complete_form_element().');
}

/**
 * if the user completes a gquiz and there is a pagebreak so the values are saved temporary.
 * the values are not saved permanently until the user click on save button
 *
 * @global object
 * @param object $gquizcompleted
 * @return object temporary saved completed-record
 */
function gquiz_set_tmp_values($gquizcompleted) {
    global $DB;

    //first we create a completedtmp
    $tmpcpl = new stdClass();
    foreach ($gquizcompleted as $key => $value) {
        $tmpcpl->{$key} = $value;
    }
    unset($tmpcpl->id);
    $tmpcpl->timemodified = time();
    $tmpcpl->id = $DB->insert_record('gquiz_completedtmp', $tmpcpl);
    //get all values of original-completed
    if (!$values = $DB->get_records('gquiz_value', array('completed'=>$gquizcompleted->id))) {
        return;
    }
    foreach ($values as $value) {
        unset($value->id);
        $value->completed = $tmpcpl->id;
        $DB->insert_record('gquiz_valuetmp', $value);
    }
    return $tmpcpl;
}

/**
 * this saves the temporary saved values permanently
 *
 * @global object
 * @param object $gquizcompletedtmp the temporary completed
 * @param object $gquizcompleted the target completed
 * @return int the id of the completed
 */
function gquiz_save_tmp_values($gquizcompletedtmp, $gquizcompleted) {
    global $DB;

    $tmpcplid = $gquizcompletedtmp->id;
    if ($gquizcompleted) {
        //first drop all existing values
        $DB->delete_records('gquiz_value', array('completed'=>$gquizcompleted->id));
        //update the current completed
        $gquizcompleted->timemodified = time();
        $DB->update_record('gquiz_completed', $gquizcompleted);
    } else {
        $gquizcompleted = clone($gquizcompletedtmp);
        $gquizcompleted->id = '';
        $gquizcompleted->timemodified = time();
        $gquizcompleted->id = $DB->insert_record('gquiz_completed', $gquizcompleted);
    }

    $allitems = $DB->get_records('gquiz_item', array('gquiz' => $gquizcompleted->gquiz));

    //save all the new values from gquiz_valuetmp
    //get all values of tmp-completed
    $params = array('completed'=>$gquizcompletedtmp->id);
    $values = $DB->get_records('gquiz_valuetmp', $params);
    foreach ($values as $value) {
        //check if there are depend items
        $item = $DB->get_record('gquiz_item', array('id'=>$value->item));
        if ($item->dependitem > 0 && isset($allitems[$item->dependitem])) {
            $ditem = $allitems[$item->dependitem];
            while ($ditem !== null) {
                $check = gquiz_compare_item_value($tmpcplid,
                                            $ditem,
                                            $item->dependvalue,
                                            true);
                if (!$check) {
                    break;
                }
                if ($ditem->dependitem > 0 && isset($allitems[$ditem->dependitem])) {
                    $item = $ditem;
                    $ditem = $allitems[$ditem->dependitem];
                } else {
                    $ditem = null;
                }
            }

        } else {
            $check = true;
        }
        if ($check) {
            unset($value->id);
            $value->completed = $gquizcompleted->id;
            $DB->insert_record('gquiz_value', $value);
        }
    }
    //drop all the tmpvalues
    $DB->delete_records('gquiz_valuetmp', array('completed'=>$tmpcplid));
    $DB->delete_records('gquiz_completedtmp', array('id'=>$tmpcplid));

    // Trigger event for the delete action we performed.
    $cm = get_coursemodule_from_instance('gquiz', $gquizcompleted->gquiz);
    $event = \mod_gquiz\event\response_submitted::create_from_record($gquizcompleted, $cm);
    $event->trigger();
    return $gquizcompleted->id;

}

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_delete_completedtmp() {
    throw new coding_exception('gquiz_delete_completedtmp() can not be used anymore.');

}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//functions to handle the pagebreaks
////////////////////////////////////////////////

/**
 * this creates a pagebreak.
 * a pagebreak is a special kind of item
 *
 * @global object
 * @param int $gquizid
 * @return mixed false if there already is a pagebreak on last position or the id of the pagebreak-item
 */
function gquiz_create_pagebreak($gquizid) {
    global $DB;

    //check if there already is a pagebreak on the last position
    $lastposition = $DB->count_records('gquiz_item', array('gquiz'=>$gquizid));
    if ($lastposition == gquiz_get_last_break_position($gquizid)) {
        return false;
    }

    $item = new stdClass();
    $item->gquiz = $gquizid;

    $item->template=0;

    $item->name = '';

    $item->presentation = '';
    $item->hasvalue = 0;

    $item->typ = 'pagebreak';
    $item->position = $lastposition + 1;

    $item->required=0;

    return $DB->insert_record('gquiz_item', $item);
}

/**
 * get all positions of pagebreaks in the given gquiz
 *
 * @global object
 * @param int $gquizid
 * @return array all ordered pagebreak positions
 */
function gquiz_get_all_break_positions($gquizid) {
    global $DB;

    $params = array('typ'=>'pagebreak', 'gquiz'=>$gquizid);
    $allbreaks = $DB->get_records_menu('gquiz_item', $params, 'position', 'id, position');
    if (!$allbreaks) {
        return false;
    }
    return array_values($allbreaks);
}

/**
 * get the position of the last pagebreak
 *
 * @param int $gquizid
 * @return int the position of the last pagebreak
 */
function gquiz_get_last_break_position($gquizid) {
    if (!$allbreaks = gquiz_get_all_break_positions($gquizid)) {
        return false;
    }
    return $allbreaks[count($allbreaks) - 1];
}

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_get_page_to_continue() {
    throw new coding_exception('gquiz_get_page_to_continue() can not be used anymore.');
}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//functions to handle the values
////////////////////////////////////////////////

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_clean_input_value() {
    throw new coding_exception('gquiz_clean_input_value() can not be used anymore. '
        . 'Items must implement complete_form_element().');

}

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_save_values() {
    throw new coding_exception('gquiz_save_values() can not be used anymore.');
}

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_save_guest_values() {
    throw new coding_exception('gquiz_save_guest_values() can not be used anymore.');
}

/**
 * get the value from the given item related to the given completed.
 * the value can come as temporary or as permanently value. the deciding is done by $tmp
 *
 * @global object
 * @param int $completeid
 * @param int $itemid
 * @param boolean $tmp
 * @return mixed the value, the type depends on plugin-definition
 */
function gquiz_get_item_value($completedid, $itemid, $tmp = false) {
    global $DB;

    $tmpstr = $tmp ? 'tmp' : '';
    $params = array('completed'=>$completedid, 'item'=>$itemid);
    return $DB->get_field('gquiz_value'.$tmpstr, 'value', $params);
}

/**
 * compares the value of the itemid related to the completedid with the dependvalue.
 * this is used if a depend item is set.
 * the value can come as temporary or as permanently value. the deciding is done by $tmp.
 *
 * @param int $completedid
 * @param stdClass|int $item
 * @param mixed $dependvalue
 * @param bool $tmp
 * @return bool
 */
function gquiz_compare_item_value($completedid, $item, $dependvalue, $tmp = false) {
    global $DB;

    if (is_int($item)) {
        $item = $DB->get_record('gquiz_item', array('id' => $item));
    }

    $dbvalue = gquiz_get_item_value($completedid, $item->id, $tmp);

    $itemobj = gquiz_get_item_class($item->typ);
    return $itemobj->compare_value($item, $dbvalue, $dependvalue); //true or false
}

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_check_values() {
    throw new coding_exception('gquiz_check_values() can not be used anymore. '
        . 'Items must implement complete_form_element().');
}

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_create_values() {
    throw new coding_exception('gquiz_create_values() can not be used anymore.');
}

/**
 * @deprecated since Moodle 3.1
 */
function gquiz_update_values() {
    throw new coding_exception('gquiz_update_values() can not be used anymore.');
}

/**
 * get the values of an item depending on the given groupid.
 * if the gquiz is anonymous so the values are shuffled
 *
 * @global object
 * @global object
 * @param object $item
 * @param int $groupid
 * @param int $courseid
 * @param bool $ignore_empty if this is set true so empty values are not delivered
 * @return array the value-records
 */
function gquiz_get_group_values($item,
                                   $groupid = false,
                                   $courseid = false,
                                   $ignore_empty = false) {

    global $CFG, $DB;

    //if the groupid is given?
    if (intval($groupid) > 0) {
        $params = array();
        if ($ignore_empty) {
            $value = $DB->sql_compare_text('fbv.value');
            $ignore_empty_select = "AND $value != :emptyvalue AND $value != :zerovalue";
            $params += array('emptyvalue' => '', 'zerovalue' => '0');
        } else {
            $ignore_empty_select = "";
        }

        $query = 'SELECT fbv .  *
                    FROM {gquiz_value} fbv, {gquiz_completed} fbc, {groups_members} gm
                   WHERE fbv.item = :itemid
                         AND fbv.completed = fbc.id
                         AND fbc.userid = gm.userid
                         '.$ignore_empty_select.'
                         AND gm.groupid = :groupid
                ORDER BY fbc.timemodified';
        $params += array('itemid' => $item->id, 'groupid' => $groupid);
        $values = $DB->get_records_sql($query, $params);

    } else {
        $params = array();
        if ($ignore_empty) {
            $value = $DB->sql_compare_text('value');
            $ignore_empty_select = "AND $value != :emptyvalue AND $value != :zerovalue";
            $params += array('emptyvalue' => '', 'zerovalue' => '0');
        } else {
            $ignore_empty_select = "";
        }

        if ($courseid) {
            $select = "item = :itemid AND course_id = :courseid ".$ignore_empty_select;
            $params += array('itemid' => $item->id, 'courseid' => $courseid);
            $values = $DB->get_records_select('gquiz_value', $select, $params);
        } else {
            $select = "item = :itemid ".$ignore_empty_select;
            $params += array('itemid' => $item->id);
            $values = $DB->get_records_select('gquiz_value', $select, $params);
        }
    }
    $params = array('id'=>$item->gquiz);
    if ($DB->get_field('gquiz', 'anonymous', $params) == gquiz_ANONYMOUS_YES) {
        if (is_array($values)) {
            shuffle($values);
        }
    }
    return $values;
}

/**
 * check for multiple_submit = false.
 * if the gquiz is global so the courseid must be given
 *
 * @global object
 * @global object
 * @param int $gquizid
 * @param int $courseid
 * @return boolean true if the gquiz already is submitted otherwise false
 */
function gquiz_is_already_submitted($gquizid, $courseid = false) {
    global $USER, $DB;

    if (!isloggedin() || isguestuser()) {
        return false;
    }

    $params = array('userid' => $USER->id, 'gquiz' => $gquizid);
    if ($courseid) {
        $params['courseid'] = $courseid;
    }
    return $DB->record_exists('gquiz_completed', $params);
}

/**
 * @deprecated since Moodle 3.1. Use gquiz_get_current_completed_tmp() or gquiz_get_last_completed.
 */
function gquiz_get_current_completed() {
    throw new coding_exception('gquiz_get_current_completed() can not be used anymore. Please ' .
            'use either gquiz_get_current_completed_tmp() or gquiz_get_last_completed()');
}

/**
 * get the completeds depending on the given groupid.
 *
 * @global object
 * @global object
 * @param object $gquiz
 * @param int $groupid
 * @param int $courseid
 * @return mixed array of found completeds otherwise false
 */
function gquiz_get_completeds_group($gquiz, $groupid = false, $courseid = false) {
    global $CFG, $DB;

    if (intval($groupid) > 0) {
        $query = "SELECT fbc.*
                    FROM {gquiz_completed} fbc, {groups_members} gm
                   WHERE fbc.gquiz = ?
                         AND gm.groupid = ?
                         AND fbc.userid = gm.userid";
        if ($values = $DB->get_records_sql($query, array($gquiz->id, $groupid))) {
            return $values;
        } else {
            return false;
        }
    } else {
        if ($courseid) {
            $query = "SELECT DISTINCT fbc.*
                        FROM {gquiz_completed} fbc, {gquiz_value} fbv
                        WHERE fbc.id = fbv.completed
                            AND fbc.gquiz = ?
                            AND fbv.course_id = ?
                        ORDER BY random_response";
            if ($values = $DB->get_records_sql($query, array($gquiz->id, $courseid))) {
                return $values;
            } else {
                return false;
            }
        } else {
            if ($values = $DB->get_records('gquiz_completed', array('gquiz'=>$gquiz->id))) {
                return $values;
            } else {
                return false;
            }
        }
    }
}

/**
 * get the count of completeds depending on the given groupid.
 *
 * @global object
 * @global object
 * @param object $gquiz
 * @param int $groupid
 * @param int $courseid
 * @return mixed count of completeds or false
 */
function gquiz_get_completeds_group_count($gquiz, $groupid = false, $courseid = false) {
    global $CFG, $DB;

    if ($courseid > 0 AND !$groupid <= 0) {
        $sql = "SELECT id, COUNT(item) AS ci
                  FROM {gquiz_value}
                 WHERE course_id  = ?
              GROUP BY item ORDER BY ci DESC";
        if ($foundrecs = $DB->get_records_sql($sql, array($courseid))) {
            $foundrecs = array_values($foundrecs);
            return $foundrecs[0]->ci;
        }
        return false;
    }
    if ($values = gquiz_get_completeds_group($gquiz, $groupid)) {
        return count($values);
    } else {
        return false;
    }
}

/**
 * deletes all completed-recordsets from a gquiz.
 * all related data such as values also will be deleted
 *
 * @param stdClass|int $gquiz
 * @param stdClass|cm_info $cm
 * @param stdClass $course
 * @return void
 */
function gquiz_delete_all_completeds($gquiz, $cm = null, $course = null) {
    global $DB;

    if (is_int($gquiz)) {
        $gquiz = $DB->get_record('gquiz', array('id' => $gquiz));
    }

    if (!$completeds = $DB->get_records('gquiz_completed', array('gquiz' => $gquiz->id))) {
        return;
    }

    if (!$course && !($course = $DB->get_record('course', array('id' => $gquiz->course)))) {
        return false;
    }

    if (!$cm && !($cm = get_coursemodule_from_instance('gquiz', $gquiz->id))) {
        return false;
    }

    foreach ($completeds as $completed) {
        gquiz_delete_completed($completed, $gquiz, $cm, $course);
    }
}

/**
 * deletes a completed given by completedid.
 * all related data such values or tracking data also will be deleted
 *
 * @param int|stdClass $completed
 * @param stdClass $gquiz
 * @param stdClass|cm_info $cm
 * @param stdClass $course
 * @return boolean
 */
function gquiz_delete_completed($completed, $gquiz = null, $cm = null, $course = null) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    if (!isset($completed->id)) {
        if (!$completed = $DB->get_record('gquiz_completed', array('id' => $completed))) {
            return false;
        }
    }

    if (!$gquiz && !($gquiz = $DB->get_record('gquiz', array('id' => $completed->gquiz)))) {
        return false;
    }

    if (!$course && !($course = $DB->get_record('course', array('id' => $gquiz->course)))) {
        return false;
    }

    if (!$cm && !($cm = get_coursemodule_from_instance('gquiz', $gquiz->id))) {
        return false;
    }

    //first we delete all related values
    $DB->delete_records('gquiz_value', array('completed' => $completed->id));

    // Delete the completed record.
    $return = $DB->delete_records('gquiz_completed', array('id' => $completed->id));

    // Update completion state
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC && $gquiz->completionsubmit) {
        $completion->update_state($cm, COMPLETION_INCOMPLETE, $completed->userid);
    }
    // Trigger event for the delete action we performed.
    $event = \mod_gquiz\event\response_deleted::create_from_record($completed, $cm, $gquiz);
    $event->trigger();

    return $return;
}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//functions to handle sitecourse mapping
////////////////////////////////////////////////

/**
 * @deprecated since 3.1
 */
function gquiz_is_course_in_sitecourse_map() {
    throw new coding_exception('gquiz_is_course_in_sitecourse_map() can not be used anymore.');
}

/**
 * @deprecated since 3.1
 */
function gquiz_is_gquiz_in_sitecourse_map() {
    throw new coding_exception('gquiz_is_gquiz_in_sitecourse_map() can not be used anymore.');
}

/**
 * gets the gquizs from table gquiz_sitecourse_map.
 * this is used to show the global gquizs on the gquiz block
 * all gquizs with the following criteria will be selected:<br />
 *
 * 1) all gquizs which id are listed together with the courseid in sitecoursemap and<br />
 * 2) all gquizs which not are listed in sitecoursemap
 *
 * @global object
 * @param int $courseid
 * @return array the gquiz-records
 */
function gquiz_get_gquizs_from_sitecourse_map($courseid) {
    global $DB;

    //first get all gquizs listed in sitecourse_map with named courseid
    $sql = "SELECT f.id AS id,
                   cm.id AS cmid,
                   f.name AS name,
                   f.timeopen AS timeopen,
                   f.timeclose AS timeclose
            FROM {gquiz} f, {course_modules} cm, {gquiz_sitecourse_map} sm, {modules} m
            WHERE f.id = cm.instance
                   AND f.course = '".SITEID."'
                   AND m.id = cm.module
                   AND m.name = 'gquiz'
                   AND sm.courseid = ?
                   AND sm.gquizid = f.id";

    if (!$gquizs1 = $DB->get_records_sql($sql, array($courseid))) {
        $gquizs1 = array();
    }

    //second get all gquizs not listed in sitecourse_map
    $gquizs2 = array();
    $sql = "SELECT f.id AS id,
                   cm.id AS cmid,
                   f.name AS name,
                   f.timeopen AS timeopen,
                   f.timeclose AS timeclose
            FROM {gquiz} f, {course_modules} cm, {modules} m
            WHERE f.id = cm.instance
                   AND f.course = '".SITEID."'
                   AND m.id = cm.module
                   AND m.name = 'gquiz'";
    if (!$allgquizs = $DB->get_records_sql($sql)) {
        $allgquizs = array();
    }
    foreach ($allgquizs as $a) {
        if (!$DB->record_exists('gquiz_sitecourse_map', array('gquizid'=>$a->id))) {
            $gquizs2[] = $a;
        }
    }

    $gquizs = array_merge($gquizs1, $gquizs2);
    $modinfo = get_fast_modinfo(SITEID);
    return array_filter($gquizs, function($f) use ($modinfo) {
        return ($cm = $modinfo->get_cm($f->cmid)) && $cm->uservisible;
    });

}

/**
 * Gets the courses from table gquiz_sitecourse_map
 *
 * @param int $gquizid
 * @return array the course-records
 */
function gquiz_get_courses_from_sitecourse_map($gquizid) {
    global $DB;

    $sql = "SELECT c.id, c.fullname, c.shortname
              FROM {gquiz_sitecourse_map} f, {course} c
             WHERE c.id = f.courseid
                   AND f.gquizid = ?
          ORDER BY c.fullname";

    return $DB->get_records_sql($sql, array($gquizid));

}

/**
 * Updates the course mapping for the gquiz
 *
 * @param stdClass $gquiz
 * @param array $courses array of course ids
 */
function gquiz_update_sitecourse_map($gquiz, $courses) {
    global $DB;
    if (empty($courses)) {
        $courses = array();
    }
    $currentmapping = $DB->get_fieldset_select('gquiz_sitecourse_map', 'courseid', 'gquizid=?', array($gquiz->id));
    foreach (array_diff($courses, $currentmapping) as $courseid) {
        $DB->insert_record('gquiz_sitecourse_map', array('gquizid' => $gquiz->id, 'courseid' => $courseid));
    }
    foreach (array_diff($currentmapping, $courses) as $courseid) {
        $DB->delete_records('gquiz_sitecourse_map', array('gquizid' => $gquiz->id, 'courseid' => $courseid));
    }
    // TODO MDL-53574 add events.
}

/**
 * @deprecated since 3.1
 */
function gquiz_clean_up_sitecourse_map() {
    throw new coding_exception('gquiz_clean_up_sitecourse_map() can not be used anymore.');
}

////////////////////////////////////////////////
////////////////////////////////////////////////
////////////////////////////////////////////////
//not relatable functions
////////////////////////////////////////////////

/**
 * @deprecated since 3.1
 */
function gquiz_print_numeric_option_list() {
    throw new coding_exception('gquiz_print_numeric_option_list() can not be used anymore.');
}

/**
 * sends an email to the teachers of the course where the given gquiz is placed.
 *
 * @global object
 * @global object
 * @uses gquiz_ANONYMOUS_NO
 * @uses FORMAT_PLAIN
 * @param object $cm the coursemodule-record
 * @param object $gquiz
 * @param object $course
 * @param stdClass|int $user
 * @param stdClass $completed record from gquiz_completed if known
 * @return void
 */
function gquiz_send_email($cm, $gquiz, $course, $user, $completed = null) {
    global $CFG, $DB, $PAGE;

    if ($gquiz->email_notification == 0) {  // No need to do anything
        return;
    }

    if (!is_object($user)) {
        $user = $DB->get_record('user', array('id' => $user));
    }

    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode =  $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }

    if ($groupmode == SEPARATEGROUPS) {
        $groups = $DB->get_records_sql_menu("SELECT g.name, g.id
                                               FROM {groups} g, {groups_members} m
                                              WHERE g.courseid = ?
                                                    AND g.id = m.groupid
                                                    AND m.userid = ?
                                           ORDER BY name ASC", array($course->id, $user->id));
        $groups = array_values($groups);

        $teachers = gquiz_get_receivemail_users($cm->id, $groups);
    } else {
        $teachers = gquiz_get_receivemail_users($cm->id);
    }

    if ($teachers) {

        $strgquizs = get_string('modulenameplural', 'gquiz');
        $strgquiz  = get_string('modulename', 'gquiz');

        if ($gquiz->anonymous == gquiz_ANONYMOUS_NO) {
            $printusername = fullname($user);
        } else {
            $printusername = get_string('anonymous_user', 'gquiz');
        }

        foreach ($teachers as $teacher) {
            $info = new stdClass();
            $info->username = $printusername;
            $info->gquiz = format_string($gquiz->name, true);
            $info->url = $CFG->wwwroot.'/mod/gquiz/show_entries.php?'.
                            'id='.$cm->id.'&'.
                            'userid=' . $user->id;
            if ($completed) {
                $info->url .= '&showcompleted=' . $completed->id;
                if ($gquiz->course == SITEID) {
                    // Course where gquiz was completed (for site gquizs only).
                    $info->url .= '&courseid=' . $completed->courseid;
                }
            }

            $a = array('username' => $info->username, 'gquizname' => $gquiz->name);

            $postsubject = get_string('gquizcompleted', 'gquiz', $a);
            $posttext = gquiz_send_email_text($info, $course);

            if ($teacher->mailformat == 1) {
                $posthtml = gquiz_send_email_html($info, $course, $cm);
            } else {
                $posthtml = '';
            }

            $customdata = [
                'cmid' => $cm->id,
                'instance' => $gquiz->id,
            ];
            if ($gquiz->anonymous == gquiz_ANONYMOUS_NO) {
                $eventdata = new \core\message\message();
                $eventdata->anonymous        = false;
                $eventdata->courseid         = $course->id;
                $eventdata->name             = 'submission';
                $eventdata->component        = 'mod_gquiz';
                $eventdata->userfrom         = $user;
                $eventdata->userto           = $teacher;
                $eventdata->subject          = $postsubject;
                $eventdata->fullmessage      = $posttext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml  = $posthtml;
                $eventdata->smallmessage     = '';
                $eventdata->courseid         = $course->id;
                $eventdata->contexturl       = $info->url;
                $eventdata->contexturlname   = $info->gquiz;
                // User image.
                $userpicture = new user_picture($user);
                $userpicture->size = 1; // Use f1 size.
                $userpicture->includetoken = $teacher->id; // Generate an out-of-session token for the user receiving the message.
                $customdata['notificationiconurl'] = $userpicture->get_url($PAGE)->out(false);
                $eventdata->customdata = $customdata;
                message_send($eventdata);
            } else {
                $eventdata = new \core\message\message();
                $eventdata->anonymous        = true;
                $eventdata->courseid         = $course->id;
                $eventdata->name             = 'submission';
                $eventdata->component        = 'mod_gquiz';
                $eventdata->userfrom         = $teacher;
                $eventdata->userto           = $teacher;
                $eventdata->subject          = $postsubject;
                $eventdata->fullmessage      = $posttext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml  = $posthtml;
                $eventdata->smallmessage     = '';
                $eventdata->courseid         = $course->id;
                $eventdata->contexturl       = $info->url;
                $eventdata->contexturlname   = $info->gquiz;
                // gquiz icon if can be easily reachable.
                $customdata['notificationiconurl'] = ($cm instanceof cm_info) ? $cm->get_icon_url()->out() : '';
                $eventdata->customdata = $customdata;
                message_send($eventdata);
            }
        }
    }
}

/**
 * sends an email to the teachers of the course where the given gquiz is placed.
 *
 * @global object
 * @uses FORMAT_PLAIN
 * @param object $cm the coursemodule-record
 * @param object $gquiz
 * @param object $course
 * @return void
 */
function gquiz_send_email_anonym($cm, $gquiz, $course) {
    global $CFG;

    if ($gquiz->email_notification == 0) { // No need to do anything
        return;
    }

    $teachers = gquiz_get_receivemail_users($cm->id);

    if ($teachers) {

        $strgquizs = get_string('modulenameplural', 'gquiz');
        $strgquiz  = get_string('modulename', 'gquiz');
        $printusername = get_string('anonymous_user', 'gquiz');

        foreach ($teachers as $teacher) {
            $info = new stdClass();
            $info->username = $printusername;
            $info->gquiz = format_string($gquiz->name, true);
            $info->url = $CFG->wwwroot.'/mod/gquiz/show_entries.php?id=' . $cm->id;

            $a = array('username' => $info->username, 'gquizname' => $gquiz->name);

            $postsubject = get_string('gquizcompleted', 'gquiz', $a);
            $posttext = gquiz_send_email_text($info, $course);

            if ($teacher->mailformat == 1) {
                $posthtml = gquiz_send_email_html($info, $course, $cm);
            } else {
                $posthtml = '';
            }

            $eventdata = new \core\message\message();
            $eventdata->anonymous        = true;
            $eventdata->courseid         = $course->id;
            $eventdata->name             = 'submission';
            $eventdata->component        = 'mod_gquiz';
            $eventdata->userfrom         = $teacher;
            $eventdata->userto           = $teacher;
            $eventdata->subject          = $postsubject;
            $eventdata->fullmessage      = $posttext;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml  = $posthtml;
            $eventdata->smallmessage     = '';
            $eventdata->courseid         = $course->id;
            $eventdata->contexturl       = $info->url;
            $eventdata->contexturlname   = $info->gquiz;
            $eventdata->customdata       = [
                'cmid' => $cm->id,
                'instance' => $gquiz->id,
                'notificationiconurl' => ($cm instanceof cm_info) ? $cm->get_icon_url()->out() : '',  // Performance wise.
            ];

            message_send($eventdata);
        }
    }
}

/**
 * send the text-part of the email
 *
 * @param object $info includes some infos about the gquiz you want to send
 * @param object $course
 * @return string the text you want to post
 */
function gquiz_send_email_text($info, $course) {
    $coursecontext = context_course::instance($course->id);
    $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
    $posttext  = $courseshortname.' -> '.get_string('modulenameplural', 'gquiz').' -> '.
                    $info->gquiz."\n";
    $posttext .= '---------------------------------------------------------------------'."\n";
    $posttext .= get_string("emailteachermail", "gquiz", $info)."\n";
    $posttext .= '---------------------------------------------------------------------'."\n";
    return $posttext;
}


/**
 * send the html-part of the email
 *
 * @global object
 * @param object $info includes some infos about the gquiz you want to send
 * @param object $course
 * @return string the text you want to post
 */
function gquiz_send_email_html($info, $course, $cm) {
    global $CFG;
    $coursecontext = context_course::instance($course->id);
    $courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
    $course_url = $CFG->wwwroot.'/course/view.php?id='.$course->id;
    $gquiz_all_url = $CFG->wwwroot.'/mod/gquiz/index.php?id='.$course->id;
    $gquiz_url = $CFG->wwwroot.'/mod/gquiz/view.php?id='.$cm->id;

    $posthtml = '<p><font face="sans-serif">'.
            '<a href="'.$course_url.'">'.$courseshortname.'</a> ->'.
            '<a href="'.$gquiz_all_url.'">'.get_string('modulenameplural', 'gquiz').'</a> ->'.
            '<a href="'.$gquiz_url.'">'.$info->gquiz.'</a></font></p>';
    $posthtml .= '<hr /><font face="sans-serif">';
    $posthtml .= '<p>'.get_string('emailteachermailhtml', 'gquiz', $info).'</p>';
    $posthtml .= '</font><hr />';
    return $posthtml;
}

/**
 * @param string $url
 * @return string
 */
function gquiz_encode_target_url($url) {
    if (strpos($url, '?')) {
        list($part1, $part2) = explode('?', $url, 2); //maximal 2 parts
        return $part1 . '?' . htmlentities($part2);
    } else {
        return $url;
    }
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $gquiznode The node to add module settings to
 */
function gquiz_extend_settings_navigation(settings_navigation $settings,
                                             navigation_node $gquiznode) {

    global $PAGE;

    if (!$context = context_module::instance($PAGE->cm->id, IGNORE_MISSING)) {
        print_error('badcontext');
    }

    if (has_capability('mod/gquiz:edititems', $context)) {
        $questionnode = $gquiznode->add(get_string('questions', 'gquiz'));

        $questionnode->add(get_string('edit_items', 'gquiz'),
                    new moodle_url('/mod/gquiz/edit.php',
                                    array('id' => $PAGE->cm->id,
                                          'do_show' => 'edit')));

        $questionnode->add(get_string('export_questions', 'gquiz'),
                    new moodle_url('/mod/gquiz/export.php',
                                    array('id' => $PAGE->cm->id,
                                          'action' => 'exportfile')));

        $questionnode->add(get_string('import_questions', 'gquiz'),
                    new moodle_url('/mod/gquiz/import.php',
                                    array('id' => $PAGE->cm->id)));

        $questionnode->add(get_string('templates', 'gquiz'),
                    new moodle_url('/mod/gquiz/edit.php',
                                    array('id' => $PAGE->cm->id,
                                          'do_show' => 'templates')));
    }

    if (has_capability('mod/gquiz:mapcourse', $context) && $PAGE->course->id == SITEID) {
        $gquiznode->add(get_string('mappedcourses', 'gquiz'),
                    new moodle_url('/mod/gquiz/mapcourse.php',
                                    array('id' => $PAGE->cm->id)));
    }

    if (has_capability('mod/gquiz:viewreports', $context)) {
        $gquiz = $PAGE->activityrecord;
        if ($gquiz->course == SITEID) {
            $gquiznode->add(get_string('analysis', 'gquiz'),
                    new moodle_url('/mod/gquiz/analysis_course.php',
                                    array('id' => $PAGE->cm->id)));
        } else {
            $gquiznode->add(get_string('analysis', 'gquiz'),
                    new moodle_url('/mod/gquiz/analysis.php',
                                    array('id' => $PAGE->cm->id)));
        }

        $gquiznode->add(get_string('show_entries', 'gquiz'),
                    new moodle_url('/mod/gquiz/show_entries.php',
                                    array('id' => $PAGE->cm->id)));

        if ($gquiz->anonymous == gquiz_ANONYMOUS_NO AND $gquiz->course != SITEID) {
            $gquiznode->add(get_string('show_nonrespondents', 'gquiz'),
                        new moodle_url('/mod/gquiz/show_nonrespondents.php',
                                        array('id' => $PAGE->cm->id)));
        }
    }
}

function gquiz_init_gquiz_session() {
    //initialize the gquiz-Session - not nice at all!!
    global $SESSION;
    if (!empty($SESSION)) {
        if (!isset($SESSION->gquiz) OR !is_object($SESSION->gquiz)) {
            $SESSION->gquiz = new stdClass();
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function gquiz_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-gquiz-*'=>get_string('page-mod-gquiz-x', 'gquiz'));
    return $module_pagetype;
}

/**
 * Move save the items of the given $gquiz in the order of $itemlist.
 * @param string $itemlist a comma separated list with item ids
 * @param stdClass $gquiz
 * @return bool true if success
 */
function gquiz_ajax_saveitemorder($itemlist, $gquiz) {
    global $DB;

    $result = true;
    $position = 0;
    foreach ($itemlist as $itemid) {
        $position++;
        $result = $result && $DB->set_field('gquiz_item',
                                            'position',
                                            $position,
                                            array('id'=>$itemid, 'gquiz'=>$gquiz->id));
    }
    return $result;
}

/**
 * Checks if current user is able to view gquiz on this course.
 *
 * @param stdClass $gquiz
 * @param context_module $context
 * @param int $courseid
 * @return bool
 */
function gquiz_can_view_analysis($gquiz, $context, $courseid = false) {
    if (has_capability('mod/gquiz:viewreports', $context)) {
        return true;
    }

    if (intval($gquiz->publish_stats) != 1 ||
            !has_capability('mod/gquiz:viewanalysepage', $context)) {
        return false;
    }

    if (!isloggedin() || isguestuser()) {
        // There is no tracking for the guests, assume that they can view analysis if condition above is satisfied.
        return $gquiz->course == SITEID;
    }

    return gquiz_is_already_submitted($gquiz->id, $courseid);
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_gquiz_get_fontawesome_icon_map() {
    return [
        'mod_gquiz:required' => 'fa-exclamation-circle',
        'mod_gquiz:notrequired' => 'fa-question-circle-o',
    ];
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.3
 */
function gquiz_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER, $CFG;

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    // Check for new attempts.
    $updates->attemptsfinished = (object) array('updated' => false);
    $updates->attemptsunfinished = (object) array('updated' => false);
    $select = 'gquiz = ? AND userid = ? AND timemodified > ?';
    $params = array($cm->instance, $USER->id, $from);

    $attemptsfinished = $DB->get_records_select('gquiz_completed', $select, $params, '', 'id');
    if (!empty($attemptsfinished)) {
        $updates->attemptsfinished->updated = true;
        $updates->attemptsfinished->itemids = array_keys($attemptsfinished);
    }
    $attemptsunfinished = $DB->get_records_select('gquiz_completedtmp', $select, $params, '', 'id');
    if (!empty($attemptsunfinished)) {
        $updates->attemptsunfinished->updated = true;
        $updates->attemptsunfinished->itemids = array_keys($attemptsunfinished);
    }

    // Now, teachers should see other students updates.
    if (has_capability('mod/gquiz:viewreports', $cm->context)) {
        $select = 'gquiz = ? AND timemodified > ?';
        $params = array($cm->instance, $from);

        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groupusers = array_keys(groups_get_activity_shared_group_members($cm));
            if (empty($groupusers)) {
                return $updates;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($groupusers);
            $select .= ' AND userid ' . $insql;
            $params = array_merge($params, $inparams);
        }

        $updates->userattemptsfinished = (object) array('updated' => false);
        $attemptsfinished = $DB->get_records_select('gquiz_completed', $select, $params, '', 'id');
        if (!empty($attemptsfinished)) {
            $updates->userattemptsfinished->updated = true;
            $updates->userattemptsfinished->itemids = array_keys($attemptsfinished);
        }

        $updates->userattemptsunfinished = (object) array('updated' => false);
        $attemptsunfinished = $DB->get_records_select('gquiz_completedtmp', $select, $params, '', 'id');
        if (!empty($attemptsunfinished)) {
            $updates->userattemptsunfinished->updated = true;
            $updates->userattemptsunfinished->itemids = array_keys($attemptsunfinished);
        }
    }

    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */

function mod_gquiz_core_calendar_provide_event_action(calendar_event $event,
                                                         \core_calendar\action_factory $factory,
                                                         int $userid = 0) {

    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['gquiz'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    $gquizcompletion = new mod_gquiz_completion(null, $cm, 0, false, null, null, $userid);

    if (!empty($cm->customdata['timeclose']) && $cm->customdata['timeclose'] < time()) {
        // gquiz is already closed, do not display it even if it was never submitted.
        return null;
    }

    if (!$gquizcompletion->can_complete()) {
        // The user can't complete the gquiz so there is no action for them.
        return null;
    }

    // The gquiz is actionable if it does not have timeopen or timeopen is in the past.
    $actionable = $gquizcompletion->is_open();

    if ($actionable && $gquizcompletion->is_already_submitted(false)) {
        // There is no need to display anything if the user has already submitted the gquiz.
        return null;
    }

    return $factory->create_instance(
        get_string('answerquestions', 'gquiz'),
        new \moodle_url('/mod/gquiz/view.php', ['id' => $cm->id]),
        1,
        $actionable
    );
}

/**
 * Add a get_coursemodule_info function in case any gquiz type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function gquiz_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionsubmit, timeopen, timeclose, anonymous';
    if (!$gquiz = $DB->get_record('gquiz', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $gquiz->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('gquiz', $gquiz, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionsubmit'] = $gquiz->completionsubmit;
    }
    // Populate some other values that can be used in calendar or on dashboard.
    if ($gquiz->timeopen) {
        $result->customdata['timeopen'] = $gquiz->timeopen;
    }
    if ($gquiz->timeclose) {
        $result->customdata['timeclose'] = $gquiz->timeclose;
    }
    if ($gquiz->anonymous) {
        $result->customdata['anonymous'] = $gquiz->anonymous;
    }

    return $result;
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_gquiz_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionsubmit':
                if (!empty($val)) {
                    $descriptions[] = get_string('completionsubmit', 'gquiz');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The due date must be after the sbumission start date'],
 *     [1506741172, 'The due date must be before the cutoff date']
 * ]
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $instance The module instance to get the range from
 * @return array
 */
function mod_gquiz_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $instance) {
    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == gquiz_EVENT_TYPE_OPEN) {
        // The start time of the open event can't be equal to or after the
        // close time of the choice activity.
        if (!empty($instance->timeclose)) {
            $maxdate = [
                $instance->timeclose,
                get_string('openafterclose', 'gquiz')
            ];
        }
    } else if ($event->eventtype == gquiz_EVENT_TYPE_CLOSE) {
        // The start time of the close event can't be equal to or earlier than the
        // open time of the choice activity.
        if (!empty($instance->timeopen)) {
            $mindate = [
                $instance->timeopen,
                get_string('closebeforeopen', 'gquiz')
            ];
        }
    }

    return [$mindate, $maxdate];
}

/**
 * This function will update the gquiz module according to the
 * event that has been modified.
 *
 * It will set the timeopen or timeclose value of the gquiz instance
 * according to the type of event provided.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $gquiz The module instance to get the range from
 */
function mod_gquiz_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $gquiz) {
    global $CFG, $DB;

    if (empty($event->instance) || $event->modulename != 'gquiz') {
        return;
    }

    if ($event->instance != $gquiz->id) {
        return;
    }

    if (!in_array($event->eventtype, [gquiz_EVENT_TYPE_OPEN, gquiz_EVENT_TYPE_CLOSE])) {
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == gquiz_EVENT_TYPE_OPEN) {
        // If the event is for the gquiz activity opening then we should
        // set the start time of the gquiz activity to be the new start
        // time of the event.
        if ($gquiz->timeopen != $event->timestart) {
            $gquiz->timeopen = $event->timestart;
            $gquiz->timemodified = time();
            $modified = true;
        }
    } else if ($event->eventtype == gquiz_EVENT_TYPE_CLOSE) {
        // If the event is for the gquiz activity closing then we should
        // set the end time of the gquiz activity to be the new start
        // time of the event.
        if ($gquiz->timeclose != $event->timestart) {
            $gquiz->timeclose = $event->timestart;
            $modified = true;
        }
    }

    if ($modified) {
        $gquiz->timemodified = time();
        $DB->update_record('gquiz', $gquiz);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}
