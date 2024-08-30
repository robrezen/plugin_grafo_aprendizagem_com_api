define(['jquery', 'core/log', 'mod_learninggraph/getGraph'], function($, log, getGraph) {
    return {
        init: (couserid, instance) => {
            loadTable();

            function attachSendEventListeners() {
                document.querySelectorAll('.send-question-btn:not([disabled])').forEach(button => {
                    button.addEventListener('click', function() {
                        const questionId = this.getAttribute('data-id');
                        sendQuestionToAPI(questionId);
                    });
                });

                document.querySelectorAll('.view-graph-btn:not([disabled])').forEach(button => {
                    button.addEventListener('click', function() {
                        const questionId = this.getAttribute('data-id');
                        viewQuestionGraph(questionId);
                    });
                });
            }

            function sendQuestionToAPI(questionIds) {
                log.debug('Send questions to api.');
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/learninggraph/send_data_to_api.php',
                    type: 'GET',
                    data: {
                        'questionids': questionIds,
                        'instance': instance,
                    },
                    success: (response) => {
                        log.debug(response);
                        loadTable();
                        startPollingForStatusUpdate(questionIds)
                    },
                    error: () => {
                        alert('Failed to send questions to API.');
                        loadTable();
                    }
                });
            }

            function startPollingForStatusUpdate(questionIds) {
                const pollInterval = 5000; 
                const maxAttempts = 12;
                let attempts = 0;

                const poll = () => {
                    $.ajax({
                        url: M.cfg.wwwroot + '/mod/learninggraph/check_data_returned.php',
                        type: 'GET',
                        data: {
                            'questionids': questionIds
                        },
                        success: (res) => {
                            log.debug(res);
                            const response = JSON.parse(res);
                            let allProcessed = true;

                            response.status.forEach(status => {
                                if (status !== 'processed') {
                                    allProcessed = false;
                                }
                            });

                            if (allProcessed || attempts >= maxAttempts) {
                                loadTable();
                            } else {
                                attempts++;
                                setTimeout(poll, pollInterval);
                            }
                        },
                        error: (error) => {
                            log.error('Failed to check question status', error);
                        }
                    });
                };

                poll();
            }

            function sendSelectedQuestionsToAPI() {
                const selectedQuestionCheckboxes = document.querySelectorAll('input[name="selected_questions[]"]:checked');
                const selectedQuestionIds = Array.from(selectedQuestionCheckboxes).map(checkbox => checkbox.value);
            
                if (selectedQuestionIds.length === 0) {
                    alert('Please select at least one question.');
                    return;
                }
            
                const questionIds = selectedQuestionIds.join(',');
                sendQuestionToAPI(questionIds);
            }

            function sendAllQuestionsToAPI() {
                const allQuestionId = Array.from(document.querySelectorAll('input[name="selected_questions[]"]')).map(checkbox => checkbox.value);
            
                if (allQuestionId.length === 0) {
                    alert('Nothing to send. Insert questions first.');
                    return;
                }

                const questionIds = allQuestionId.join(',');            
                sendQuestionToAPI(questionIds);
            }

            function loadTable() {
                log.debug('Fetch questions for course module.');
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/learninggraph/fetch_questions.php',
                    type: 'GET',
                    data: {
                        'courseid': couserid
                    },
                    success:(questions) => {
                        const table = document.getElementById('questionsTable');
                        $('#questionsTable tr:not(:first)').remove();

                        Object.values(questions).forEach((question) => {
                            const row = table.insertRow();
                            const btnDisabled = question.graphstatus === 'processing' ? 'disabled' : '';
                            row.innerHTML = `
                                <td><input type="checkbox" name="selected_questions[]" value="${question.id}"></td>
                                <td>${question.id}</td>
                                <td>${question.graphstatus}</td>
                                <td>${question.questiontext.substring(0, 50)}</td>
                                <td>
                                    <button class="btn btn-primary view-graph-btn" data-id="${question.id}" data-text="${question.questiontext}" ${btnDisabled}>
                                        <span class="material-symbols-outlined">linked_services</span>
                                    </button>
                                </td>
                                <td>
                                    <button class="btn btn-primary send-question-btn" data-id="${question.id}" data-text="${question.questiontext}" ${btnDisabled}>Send to API</button>
                                </td>
                            `;
                        });
                        document.getElementById('send-selected-btn').addEventListener('click', () => {
                            sendSelectedQuestionsToAPI();
                        });
                        document.getElementById('send-all-btn').addEventListener('click', () => {
                            sendAllQuestionsToAPI();
                        });
                        attachSendEventListeners();
                    },
                    error: (error) => {
                        log.debug(error);
                        alert('Failed to fetch questions.');
                    }
                });
            }

            function viewQuestionGraph(questionId) {
                log.debug('View graph for question ' + questionId);
                var modal = document.createElement('div');
                modal.setAttribute('id', 'graphModal');
                modal.setAttribute('class', 'modal');
                modal.innerHTML = `
                    <div class="modal-content">
                        <button class="close-modal">&times;</button>
                        <div id="question-graph-container" style="width: 100%; height: 90%;"></div>
                    </div>
                `;
                document.body.appendChild(modal);

                var closeModal = modal.getElementsByClassName('close-modal')[0];
                closeModal.onclick = () => {
                    modal.style.display = 'none';
                    modal.remove();
                };

                window.onclick = (event) => {
                    if (event.target == modal) {
                        modal.style.display = 'none';
                        modal.remove();
                    }
                };
                modal.style.display = 'block';
                getGraph.getQuestionGraph(couserid, instance, questionId);
            }
        }
    };
});
