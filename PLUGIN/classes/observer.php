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
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     mod_learninggraph
 * @category    observer
 * @copyright   2023 Robson Rezende <robson.rezen@usp.br>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/api_manager.php');

class mod_learninggraph_observer {
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;
        $api_manager = new api_manager();

        $attempt = $DB->get_record('quiz_attempts', array('id' => $event->objectid), '*', MUST_EXIST);
        $questions = $DB->get_records('question_attempts', array('questionusageid' => $attempt->uniqueid));
    
        $details = array();
        foreach ($questions as $question) {
            $questiondata = $DB->get_record('question', array('id' => $question->questionid), '*', MUST_EXIST);
            if ($questiondata != null) {
                $grade = $DB->get_record_sql(
                    'SELECT fraction FROM {question_attempt_steps} WHERE questionattemptid = ? ORDER BY id DESC LIMIT 1',
                    array($question->id)
                );
                $conditions = array('questionid' => $question->questionid);
                $graph = get_graph($conditions, 'learninggraph_quest_graphs', 'graphdata');
                if ($graph != null && isset($graph['nodes']) && !empty($graph['nodes']) && isset($graph['edges']) && !empty($graph['edges'])) {
                    $detail = new stdClass();
                    $detail->graph = convert_graph_to_response($graph);
                    $detail->questiontext = strip_tags($questiondata->questiontext);
                    $detail->grade = is_null($grade->fraction) ? 0 : $grade->fraction;
                    $details[] = $detail;
                } else {
                    #to do notify user
                }
            }
        }
        $instances = learninggraph_get_instances($event->courseid);
        if ($instances) {
            foreach ($instances as $instance) {
                $api_manager->update_level_and_merge($details, $instance->cmid, $event->userid, $event);
            }
        }
    }

    public static function insert_new_question(\core\event\question_created $event) {
        $question = $event->get_record_snapshot('question', $event->objectid);
        $manager = new api_manager();
        $context = $event->get_context();
        if (autoupdate_question($context)) {
            if (learninggraph_insert_question($question->id, $event->courseid) == true) {
                $instances = learninggraph_get_instances($event->courseid);
                if ($instances) {
                    foreach ($instances as $instance) {
                        $manager->send_questions_to_api($instance->id, $question->id);
                    }
                }
            }
        }
    }

    public static function update_question(\core\event\question_updated $event) {
        $question = $event->get_record_snapshot('question', $event->objectid);
        $context = $event->get_context();
        if (autoupdate_question($context)) {
            print_r($question);
        }
    }

    public static function delete_question(\core\event\question_deleted $event) {
        $context = $event->get_context();
        if (autoupdate_question($context)) {
            learninggraph_delele_question($event->objectid);
        }
    }

    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {

        $userid = $event->relateduserid;
        $courseid = $event->courseid;

        $instances = learninggraph_get_instances($courseid);
        if ($instances) {
            foreach ($instances as $instance) {
                learninggraph_insert_student_graph($instance, $userid);
            }
        }
    }

    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;

        $userid = $event->relateduserid;
        $courseid = $event->courseid;

        $instances = learninggraph_get_instances($courseid);
        if ($instances) {
            foreach ($instances as $instance) {
                $DB->delete_records('learninggraph_student_graphs', array('cmid' => $instance->id, 'userid' => $userid));
            }
        }
    }
}