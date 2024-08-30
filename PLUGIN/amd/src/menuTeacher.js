define(['jquery', 'core/log'], function($, log) {
    return {
        init: () => {
            const openAllCheckbox = document.getElementById('open-all');
            const openLessonCheckbox = document.getElementById('open-lesson');
            const openGraphCheckbox = document.getElementById('open-graph');
            const openQuestionsCheckbox = document.getElementById('open-questions');
            const accordions = {
                'lesson': 'collapseLesson',
                'graph': 'collapseGraph',
                'questions': 'collapseQuestions'
            };

            function loadSettings() {
                const settings = JSON.parse(localStorage.getItem('accordionSettings')) || {};
                openAllCheckbox.checked = settings.openAll || false;
                openLessonCheckbox.checked = settings.lesson || false;
                openGraphCheckbox.checked = settings.graph || false;
                openQuestionsCheckbox.checked = settings.questions || false;
                
                if (openAllCheckbox.checked) {
                    openAllAccordions();
                } else {
                    for (let key in accordions) {
                        if (settings[key]) {
                            document.getElementById(accordions[key]).classList.add('show');
                        }
                    }
                }
            }

            function saveSettings() {
                const settings = {
                    openAll: openAllCheckbox.checked,
                    lesson: openLessonCheckbox.checked,
                    graph: openGraphCheckbox.checked,
                    questions: openQuestionsCheckbox.checked
                };
                localStorage.setItem('accordionSettings', JSON.stringify(settings));
            }

            function openAllAccordions() {
                for (let key in accordions) {
                    document.getElementById(accordions[key]).classList.add('show');
                }
            }

            function closeAllAccordions() {
                for (let key in accordions) {
                    document.getElementById(accordions[key]).classList.remove('show');
                }
            }

            openAllCheckbox.addEventListener('change', () => {
                if (openAllCheckbox.checked) {
                    openLessonCheckbox.checked = true;
                    openGraphCheckbox.checked = true;
                    openQuestionsCheckbox.checked = true;
                    openAllAccordions();
                } else {
                    openLessonCheckbox.checked = true;
                    openGraphCheckbox.checked = true;
                    openQuestionsCheckbox.checked = true;
                    closeAllAccordions();
                }
                saveSettings();
            });

            openLessonCheckbox.addEventListener('change', () => {
                log.info(openLessonCheckbox.checked);
                if (!openLessonCheckbox.checked) {
                    openAllCheckbox.checked = false;
                }
                document.getElementById(accordions.lesson).classList.toggle('show', openLessonCheckbox.checked);
                saveSettings();
            });

            openGraphCheckbox.addEventListener('change', () => {
                log.info(openLessonCheckbox.checked);
                if (!openGraphCheckbox.checked) {
                    openAllCheckbox.checked = false;
                }
                document.getElementById(accordions.graph).classList.toggle('show', openGraphCheckbox.checked);
                saveSettings();
            });

            openQuestionsCheckbox.addEventListener('change', () => {
                log.info(openLessonCheckbox.checked);
                if (!openQuestionsCheckbox.checked) {
                    openAllCheckbox.checked = false;
                }
                document.getElementById(accordions.questions).classList.toggle('show', openQuestionsCheckbox.checked);
                saveSettings();
            });

            loadSettings();
        }
    };
});