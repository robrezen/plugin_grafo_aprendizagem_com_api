define(['jquery', 'core/log', 'mod_learninggraph/getGraph'], function($, log, getGraph) {
    return {
        init: (courseid, cmid, userid) => {
            log.info('View graph for student ' + userid + ' in module ' + cmid);
            getGraph.getStudentGraph(courseid, cmid, userid);
        }
    };
});