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
 * @package    mod_gquiz
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_gquiz_activity_task
 */

/**
 * Structure step to restore one gquiz activity
 */
class restore_gquiz_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('gquiz', '/activity/gquiz');
        $paths[] = new restore_path_element('gquiz_item', '/activity/gquiz/items/item');
        if ($userinfo) {
            $paths[] = new restore_path_element('gquiz_completed', '/activity/gquiz/completeds/completed');
            $paths[] = new restore_path_element('gquiz_value', '/activity/gquiz/completeds/completed/values/value');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_gquiz($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        // insert the gquiz record
        $newitemid = $DB->insert_record('gquiz', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_gquiz_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->gquiz = $this->get_new_parentid('gquiz');

        $newitemid = $DB->insert_record('gquiz_item', $data);
        $this->set_mapping('gquiz_item', $oldid, $newitemid, true); // Can have files
    }

    protected function process_gquiz_completed($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->gquiz = $this->get_new_parentid('gquiz');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($this->task->is_samesite() && !empty($data->courseid)) {
            $data->courseid = $data->courseid;
        } else if ($this->get_courseid() == SITEID) {
            $data->courseid = SITEID;
        } else {
            $data->courseid = 0;
        }

        $newitemid = $DB->insert_record('gquiz_completed', $data);
        $this->set_mapping('gquiz_completed', $oldid, $newitemid);
    }

    protected function process_gquiz_value($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->completed = $this->get_new_parentid('gquiz_completed');
        $data->item = $this->get_mappingid('gquiz_item', $data->item);
        if ($this->task->is_samesite() && !empty($data->course_id)) {
            $data->course_id = $data->course_id;
        } else if ($this->get_courseid() == SITEID) {
            $data->course_id = SITEID;
        } else {
            $data->course_id = 0;
        }

        $newitemid = $DB->insert_record('gquiz_value', $data);
        $this->set_mapping('gquiz_value', $oldid, $newitemid);
    }

    protected function after_execute() {
        global $DB;
        // Add gquiz related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_gquiz', 'intro', null);
        $this->add_related_files('mod_gquiz', 'page_after_submit', null);
        $this->add_related_files('mod_gquiz', 'item', 'gquiz_item');

        // Once all items are restored we can set their dependency.
        if ($records = $DB->get_records('gquiz_item', array('gquiz' => $this->task->get_activityid()))) {
            foreach ($records as $record) {
                // Get new id for dependitem if present. This will also reset dependitem if not found.
                $record->dependitem = $this->get_mappingid('gquiz_item', $record->dependitem);
                $DB->update_record('gquiz_item', $record);
            }
        }
    }
}
