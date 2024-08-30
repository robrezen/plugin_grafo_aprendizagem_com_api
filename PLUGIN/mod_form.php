<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The main mod_learninggraph configuration form.
 *
 * @package     mod_learninggraph
 * @copyright   2023 Robson Rezende <robson.rezen@usp.br>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\progress\none;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/learninggraph/lib.php');

/**
 * Module instance settings form.
 *
 * @package     mod_learninggraph
 * @copyright   2023 Robson Rezende <robson.rezen@usp.br>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_learninggraph_mod_form extends moodleform_mod {
    public function definition() {
        $cm = $this->current;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Element to allow text input in the editor
        $mform->addElement('editor', 'lesson_plaintext', get_string('lesson_plan_text', 'mod_learninggraph'));

        // Add a hidden field for introformat with a default value of 1 (HTML format)
        $mform->addElement('hidden', 'lesson_format', 1);
        $mform->setType('lesson_format', PARAM_INT);

        // Add a checkbox for autosending
        $mform->addElement('advcheckbox', 'autosending', get_string('autosending', 'mod_learninggraph'));
        $mform->setDefault('autosending', 0);
        $mform->addHelpButton('autosending', 'autosending', 'mod_learninggraph');
        $mform->setType('autosending', PARAM_INT);
        $mform->addElement('hidden', 'use_existing_graphs', 0);
        $mform->setType('use_existing_graphs', PARAM_INT);

        // Add standard elements.
        $this->standard_coursemodule_elements();

        // Add standard buttons.
        if ($cm->instance) {
            $this->add_copy_base_graph_elements($mform, $cm->course);
            $this->add_action_buttons();
        } else {
            $this->add_action_buttons(true, get_string('savelessonplan', 'mod_learninggraph'));
        }

    }

    private function add_copy_base_graph_elements($mform, $courseid) {
        global $DB;

        $mform->addElement('header', 'copybasegraphheader', get_string('copybasegraph', 'mod_learninggraph'));

        $users = get_enrolled_users(context_course::instance($courseid));
        $useroptions = [];
        foreach ($users as $user) {
            $useroptions[$user->id] = fullname($user);
        }

        $mform->addElement('select', 'selecteduser', get_string('selectuser', 'mod_learninggraph'), $useroptions);
        $mform->setType('selecteduser', PARAM_INT);

        $mform->addElement('button', 'copybasegraphbtn', get_string('copybasegraph', 'mod_learninggraph'));
    }

    public function validation($data, $files) {
        $errors = [];

        // Sanitize and validate the lesson_plaintext field
        $lesson_plaintext = $data['lesson_plaintext']['text'];
        if (empty($lesson_plaintext)) {
            $errors['lesson_plaintext'] = get_string('lesson_plan_required', 'mod_learninggraph');
        }
        return $errors;
    }

    public function data_preprocessing(&$defaultvalues) : void {
        $cm = $this->current;
        $existing_plan = get_existing_plan_text($cm->instance);
        if ($existing_plan) {
            $lesson_plaintext = isset($existing_plan->plan_text) ? $existing_plan->plan_text : '';
            $lesson_format = isset($existing_plan->plan_format) ? $existing_plan->plan_format : FORMAT_PLAIN;
            $defaultvalues['lesson_plaintext']['text'] = $lesson_plaintext;
            $defaultvalues['lesson_format'] = $lesson_format;
        } else {
            $defaultvalues['lesson_plaintext']['text'] = '';
        }
        $defaultvalues['autosending'] = learninggraph_get_autosending($cm->instance);
    }

    public function definition_after_data() {
        global $PAGE;
        $PAGE->requires->js_call_amd('mod_learninggraph/copyBaseGraph', 'init');
    }

    public function post_process_data($data) {
        global $PAGE; 
        $cm = $this->current;   
        $existing_graphs = get_not_empty_graphs($cm->course);
        print_r($existing_graphs);

        if (!empty($existing_graphs)) {
            $PAGE->requires->js_amd_inline("
                require(['core/modal_factory', 'core/str', 'core/modal_events'], function(ModalFactory, str, ModalEvents) {
                    str.get_strings([
                        {key: 'existing_graphs_title', component: 'mod_learninggraph'},
                        {key: 'existing_graphs_message', component: 'mod_learninggraph'},
                        {key: 'yes', component: 'moodle'},
                        {key: 'no', component: 'moodle'}
                    ]).done(function(strings) {
                        ModalFactory.create({
                            type: ModalFactory.types.SAVE_CANCEL,
                            title: strings[0],
                            body: strings[1] + '<br>' + '".json_encode($existing_graphs)."',
                            buttons: [
                                {
                                    text: strings[2],
                                    class: 'btn-primary',
                                    click: function() {
                                        // Definir o valor do campo oculto para usar os grafos existentes
                                        document.querySelector('input[name=\"use_existing_graphs\"]').value = 1;
                                        modal.hide();
                                        // Submeter o formulário
                                        document.querySelector('form').submit();
                                    }
                                },
                                {
                                    text: strings[3],
                                    click: function() {
                                        modal.hide();
                                    }
                                }
                            ]
                        }).done(function(modal) {
                            modal.getRoot().on(ModalEvents.save, function() {
                                // Definir o valor do campo oculto para usar os grafos existentes
                                document.querySelector('input[name=\"use_existing_graphs\"]').value = 1;
                                modal.hide();
                                // Submeter o formulário
                                document.querySelector('form').submit();
                            });

                            modal.getRoot().on(ModalEvents.cancel, function() {
                                modal.hide();
                            });

                            modal.show();
                        });
                    });
                });
            ");
        }
        $data->test;

        return $data;
    }
}