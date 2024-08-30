define(['jquery', 'core/log', 'mod_learninggraph/renderGraph'], function($, log, renderGraph) {
    class GraphService {
        _requestGraphData(data, container) {
            $.ajax({
                url: M.cfg.wwwroot + '/mod/learninggraph/get_graph.php',
                type: 'GET',
                data: data,
                success: (response) => {
                    renderGraph.renderGraph(container, response);
                },
                error: (error) => {
                    log.info(error);
                    log.error('Failed to fetch graph data for ' + JSON.stringify(data));
                }
            });
        }

        getModuleGraph(courseid, cmid, source) {
            log.debug('View graph for module ' + cmid + ' with source ' + source);
            this._requestGraphData({
                'courseid': courseid,
                'source': source, //instancebasegraph or instanceaggregatedgraph
                'cmid': cmid
            }, 'module-graph-container');
        }

        getQuestionGraph(courseid, cmid, questionId) {
            log.debug('View graph for question ' + questionId);
            this._requestGraphData({
                'courseid': courseid,
                'cmid': cmid,
                'source': 'question',
                'questionid': questionId
            }, 'question-graph-container');
        }

        getStudentGraph(courseid, cmid, userid) {
            log.debug('View graph for user ' + userid + ' in module ' + cmid);
            this._requestGraphData({
                'courseid': courseid,
                'cmid': cmid,
                'source': 'studentgraph',
                'userid': userid
            },  'user-graph-container');
        }
        getLessonPlanGraph(courseid, cmid) {
            log.debug('View graph for lesson plan ' + cmid);
            this._requestGraphData({
                'courseid': courseid,
                'cmid': cmid,
                'source': 'lessonplangraph',
            },  'lessonplan-graph-container');
        }
    }

    const graphService = new GraphService();
    return {
        getModuleGraph: graphService.getModuleGraph.bind(graphService),
        getQuestionGraph: graphService.getQuestionGraph.bind(graphService),
        getStudentGraph: graphService.getStudentGraph.bind(graphService),
        getLessonPlanGraph: graphService.getLessonPlanGraph.bind(graphService)
    };
});