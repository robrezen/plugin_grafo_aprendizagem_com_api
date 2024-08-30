<?php
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot.'/mod/learninggraph/lib.php');
require_once($CFG->dirroot.'/mod/learninggraph/classes/api_manager.php');

class mod_learninggraph_external extends external_api {

    public static function ws_data_received_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'Data id', VALUE_REQUIRED),
                'data' => new external_value(PARAM_RAW, 'The data received from the external API', VALUE_REQUIRED),
                'destination' => new external_value(PARAM_TEXT, 'The destination of the data', VALUE_DEFAULT),
                'userid' => new external_value(PARAM_INT, 'The user ID', VALUE_DEFAULT),
                'questionid' => new external_value(PARAM_INT, 'The question ID', VALUE_DEFAULT)
            )
        );
    }

    public static function ws_data_received($id, $data, $destination = 'lessonplan', $userid = null, $questionid = null) {
        $params = self::validate_parameters(self::ws_data_received_parameters(),
                            array('id' => $id, 'data' => $data, 'destination' => $destination, 'userid' => $userid, 'questionid' => $questionid));

        $graph = convert_response_to_graph($params['data']);
        $api_manager = new api_manager();
    
        try {
            switch ($params['destination']) {
                case 'lessonplangraph':
                    $conditions = array('id' => $params['id']);
                    learninggraph_save_graph($conditions, $graph, 'learninggraph_lessonplans');

                    $api_manager->process_base_and_aggregated_graphs($params['id'], $params['data']);
                    break;
                case 'studentgraph':
                    $conditions = array('userid' => $params['userid'], 'cmid' => $params['id']);
                    learninggraph_save_graph($conditions, $graph, 'learninggraph_student_graphs');
                    $cm = get_coursemodule_from_id('learninggraph', $params['id'], false, MUST_EXIST);

                    $aggregatedgraph = get_graph(array('id' => $cm->instance), 'learninggraph', 'aggregatedgraph');
                    $api_manager->merge_graph(convert_graph_to_response($aggregatedgraph), array($params['data']), $cm->instance, 'aggregategraph', false);
                    break;
                case 'basegraph':
                    $conditions = array('id' => $params['id']);
                    learninggraph_save_graph($conditions, $graph, 'learninggraph');
                    break;
                case 'aggregategraph':
                    $conditions = array('id' => $params['id']);
                    learninggraph_save_graph($conditions, $graph, 'learninggraph', True);
                    break;
                case 'questiongraph':
                    $conditions = array('questionid' => $params['questionid']);
                    learninggraph_save_graph($conditions, $graph, 'learninggraph_quest_graphs');
                    learninggraph_update_questions_status([$params['questionid']], LEARNINGGRAPH_STATUS_PROCESSED);

                    $api_manager->process_base_and_aggregated_graphs($params['id'], $params['data']);
                    break;
                default:
                    throw new Exception('Invalid destination');
            }

            if ($userid) {
                $user = \core_user::get_user($userid);
                if ($user) {
                    $eventdata = new \core\message\message();
                    $eventdata->component         = 'mod_learninggraph';
                    $eventdata->name              = 'notification';
                    $eventdata->userfrom          = \core_user::get_noreply_user();
                    $eventdata->userto            = $user;
                    $eventdata->subject           = 'Data Received';
                    $eventdata->fullmessage       = 'Webhook data processed successfully';
                    $eventdata->fullmessageformat = FORMAT_PLAIN;
                    $eventdata->fullmessagehtml   = '<p>Webhook data processed successfully</p>';
                    $eventdata->smallmessage      = 'Webhook data processed';
                    $eventdata->notification      = 1;
                    message_send($eventdata);
                }
            }
            $message = $destination . ' data processed successfully';
            $success = true;
        } catch (Exception $e) {
            $message = 'Error in ws_data_received: ' . $e->getMessage();
            $success = false;
            switch ($params['destination']) {
                case 'questiongraph':
                    learninggraph_update_questions_status([$params['questionid']], 'failed');
                    break;
            }
        }
        return array('success' => $success, 'message' => $message);
    }

    public static function ws_data_received_returns() {
        return new external_function_parameters(
            array(
                'success' => new external_value(PARAM_BOOL, 'Whether the processing was successful'),
                'message' => new external_value(PARAM_TEXT, 'Message describing the result')
            )
        );
    }
}
