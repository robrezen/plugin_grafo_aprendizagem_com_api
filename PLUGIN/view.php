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
 * Prints an instance of mod_learninggraph.
 *
 * @package     mod_learninggraph
 * @copyright   2023 Robson Rezende <robson.rezen@usp.br>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\context\user;

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/mod_form.php');
require_once($CFG->libdir . '/questionlib.php');
require_once(__DIR__.'/classes/questions.php');
require_once(__DIR__.'/classes/api_manager.php');

// Course module id.
$cmid = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('learninggraph', $cmid, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$moduleinstance = $DB->get_record('learninggraph', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cmid);
require_capability('mod/learninggraph:view', $context);

$is_teacher = has_capability('moodle/course:manageactivities', $context);

if ($is_teacher) {
    $manager = new question_manager();
    $api_manager = new api_manager();

    if (optional_param('send_lesson_plan', 0, PARAM_INT)) {
        $response = $api_manager->send_lessonplan_to_api($cm->instance);
        if ($response['status'] == 'success') {
            redirect(new moodle_url('/mod/learninggraph/view.php', ['id' => $cmid]), get_string('api_request_sent', 'mod_learninggraph'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect(new moodle_url('/mod/learninggraph/view.php', ['id' => $cmid]), $response['message'], null, \core\output\notification::NOTIFY_ERROR);
    }

    if (optional_param('insert_new_questions', 0, PARAM_INT) == 1) {
        $manager->insert_new_questions($course->id);
        redirect(new moodle_url('/mod/learninggraph/view.php', ['id' => $cmid]), get_string('questions_inserted_successfully', 'mod_learninggraph'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$PAGE->set_url(new moodle_url('/mod/learninggraph/view.php', array('id' => $cmid)));
$PAGE->set_title(get_string('lesson_plan', 'mod_learninggraph'));
$PAGE->set_heading(format_string($course->fullname));

// Print the page header
echo $OUTPUT->header();

$templateData = new stdClass();
$templateData->graph_label = get_string('graph', 'mod_learninggraph');

if ($is_teacher) {
    $templateData->autosending = $moduleinstance->autosending;
    $templateData->send_to_api_label = get_string('send_to_api', 'mod_learninggraph');
    $templateData->cancel_sending_data_label = get_string('cancelesendingdata', 'mod_learninggraph');
    $templateData->insert_new_questions_label = get_string('insert_new_questions', 'mod_learninggraph');
    $templateData->questions_label = get_string('questions', 'mod_learninggraph');
    $templateData->select = get_string('select', 'mod_learninggraph');
    $templateData->graphstatus = get_string('graphstatus', 'mod_learninggraph');
    $templateData->questiontext = get_string('questiontext', 'mod_learninggraph');
    $templateData->graphs = get_string('graphs', 'mod_learninggraph');
    $templateData->generatequestion = get_string('generatequestion', 'mod_learninggraph');
    $templateData->id = $cmid;

    echo $OUTPUT->render_from_template('mod_learninggraph/view', $templateData);

    $PAGE->requires->js_call_amd('mod_learninggraph/moduleGraph', 'init', array($course->id, $cm->instance));
    $PAGE->requires->js_call_amd('mod_learninggraph/questions', 'init', array($course->id, $cm->instance));
    $PAGE->requires->js_call_amd('mod_learninggraph/lessonPlan', 'init', array($course->id, $cmid));
    $PAGE->requires->js_call_amd('mod_learninggraph/menuTeacher', 'init', array('teacher'));
} else {
    echo $OUTPUT->render_from_template('mod_learninggraph/view_student', $templateData);

    $PAGE->requires->js_call_amd('mod_learninggraph/studentGraph', 'init', array($course->id, $cmid, $USER->id));
    $PAGE->requires->js_call_amd('mod_learninggraph/menuStudent', 'init', array('student'));
}

echo $OUTPUT->footer();
