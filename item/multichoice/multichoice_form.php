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

require_once($CFG->dirroot.'/mod/gquiz/item/gquiz_item_form_class.php');

class gquiz_multichoice_form extends gquiz_item_form {
    protected $type = "multichoice";

    public function definition() {
        $item = $this->_customdata['item'];
        $common = $this->_customdata['common'];
        $positionlist = $this->_customdata['positionlist'];
        $position = $this->_customdata['position'];

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string($this->type, 'gquiz'));

        $mform->addElement('advcheckbox', 'required', get_string('required', 'gquiz'), '' , null , array(0, 1));

        $mform->addElement('text',
                            'name',
                            get_string('item_name', 'gquiz'),
                            array('size' => gquiz_ITEM_NAME_TEXTBOX_SIZE,
                                  'maxlength' => 255));

        $mform->addElement('text',
                            'label',
                            get_string('item_label', 'gquiz'),
                            array('size' => gquiz_ITEM_LABEL_TEXTBOX_SIZE,
                                  'maxlength' => 255));

       
        /*  $mform->addElement('select',
                            'subtype',
                            get_string('multichoicetype', 'gquiz').'&nbsp;',
                            array('r'=>get_string('radio', 'gquiz'),
                                  'c'=>get_string('check', 'gquiz'),
                                  'd'=>get_string('dropdown', 'gquiz')));
        */
        $mform->addElement('select',
                            'horizontal',
                            get_string('adjustment', 'gquiz').'&nbsp;',
                            array(0 => get_string('vertical', 'gquiz'),
                                  1 => get_string('horizontal', 'gquiz')));
        $mform->hideIf('horizontal', 'subtype', 'eq', 'd');

      /*  $mform->addElement('selectyesno',
                           'hidenoselect',
                           get_string('hide_no_select_option', 'gquiz'));
        */
        

        $mform->addElement('selectyesno',
                           'ignoreempty',
                           get_string('do_not_analyse_empty_submits', 'gquiz'));
        
        $mform->addElement('textarea', 'values', get_string('multichoice_values', 'gquiz'),
            'wrap="virtual" rows="10" cols="65"');

        $mform->addElement('static', 'hint', '', get_string('use_one_line_for_each_value', 'gquiz'));
        parent::gradeable($mform,"Enter Line Of the Correct Answer");
        parent::definition();
        $this->set_data($item);

    }

    public function set_data($item) {
        $info = $this->_customdata['info'];

        $item->horizontal = $info->horizontal;

        $item->subtype = $info->subtype;

        $itemvalues = str_replace(gquiz_MULTICHOICE_LINE_SEP, "\n", $info->presentation);
        $itemvalues = str_replace("\n\n", "\n", $itemvalues);
        $item->values = $itemvalues;

        return parent::set_data($item);
    }

    public function get_data() {
        if (!$item = parent::get_data()) {
            return false;
        }

        $presentation = str_replace("\n", gquiz_MULTICHOICE_LINE_SEP, trim($item->values));
        if (!isset($item->subtype)) {
            $subtype = 'r';
        } else {
            $subtype = substr($item->subtype, 0, 1);
        }
        if (isset($item->horizontal) AND $item->horizontal == 1 AND $subtype != 'd') {
            $presentation .= gquiz_MULTICHOICE_ADJUST_SEP.'1';
        }
        if (!isset($item->hidenoselect)) {
            $item->hidenoselect = 1;
        }
        if (!isset($item->ignoreempty)) {
            $item->ignoreempty = 0;
        }

        $item->presentation = $subtype.gquiz_MULTICHOICE_TYPE_SEP.$presentation;
        return $item;
    }
}
