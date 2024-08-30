<?php

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'mod_learninggraph_ws_data_received' => array(
        'classname'   => 'mod_learninggraph_external',
        'methodname'  => 'ws_data_received',
        'classpath'   => 'mod/learninggraph/externallib.php',
        'description' => 'Process data received from a webhook.',
        'type'        => 'write',
        'ajax'        => true,
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile')
    ),
);

$services = array(
    'Learning Graph Services' => array(
        'functions' => array(
            'mod_learninggraph_ws_data_received'
        ),
        'restrictedusers' =>1,
        'enabled' => 1,
        'shortname' =>'learninggraph_service'
    )
);