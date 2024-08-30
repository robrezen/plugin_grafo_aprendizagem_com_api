define(['jquery', 'core/log', 'mod_learninggraph/getGraph'], function($, log, getGraph) {
    return {
        init: (courseid, cmid) => {
            log.info('View graph for module ' + cmid);
            getGraph.getLessonPlanGraph(courseid, cmid);
        }
    };
});
