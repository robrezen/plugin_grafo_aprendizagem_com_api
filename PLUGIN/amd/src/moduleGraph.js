define(['jquery', 'core/log', 'mod_learninggraph/getGraph'], function($, log, getGraph) {
    return {
        init: (courseid, cmid) => {
            log.info('View graph for module ' + cmid);
            
            initializeToggleButtons(courseid, cmid);

            function toggleButtonStyles(selectedButton, otherButton) {
                selectedButton.classList.remove('btn-secondary');
                selectedButton.classList.add('btn-primary');
                otherButton.classList.remove('btn-primary');
                otherButton.classList.add('btn-secondary');
            }

            function initializeToggleButtons(courseid, cmid) {
                const baseGraphBtn = document.getElementById('basegraph-btn');
                const aggregateGraphBtn = document.getElementById('aggregategraph-btn');

                // Check local storage for user's previous selection
                const savedGraphType = localStorage.getItem('selectedGraphType') || 'instancebasegraph';

                switch (savedGraphType) {
                    case 'instancebasegraph':
                        toggleButtonStyles(baseGraphBtn, aggregateGraphBtn);
                        getGraph.getModuleGraph(courseid, cmid, 'instancebasegraph');
                        break;
                    default:
                        toggleButtonStyles(baseGraphBtn, aggregateGraphBtn);
                        getGraph.getModuleGraph(courseid, cmid, 'instanceaggregatedgraph');
                        break;
                }

                baseGraphBtn.addEventListener('click', () => {
                    toggleButtonStyles(baseGraphBtn, aggregateGraphBtn);
                    getGraph.getModuleGraph(courseid, cmid, 'instancebasegraph');
                    localStorage.setItem('selectedGraphType', 'instancebasegraph');
                });

                aggregateGraphBtn.addEventListener('click', () => {
                    toggleButtonStyles(aggregateGraphBtn, baseGraphBtn);
                    getGraph.getModuleGraph(courseid, cmid, 'instanceaggregatedgraph');
                    localStorage.setItem('selectedGraphType', 'instanceaggregatedgraph');
                });
            }
        }
    };
});
