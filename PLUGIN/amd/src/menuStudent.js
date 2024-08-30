define(['jquery', 'core/log'], function($, log) {
    return {
        init: () => {
            const openGraphCheckbox = document.getElementById('open-graph');

            function loadSettings() {
                const settings = JSON.parse(localStorage.getItem('accordionSettings')) || {};
                openGraphCheckbox.checked = settings.graph || false;
                document.getElementById('collapseGraph').classList.toggle('show', openGraphCheckbox.checked);
            }

            function saveSettings() {
                const settings = {
                    graph: openGraphCheckbox.checked,
                };
                localStorage.setItem('accordionSettings', JSON.stringify(settings));
            }

            openGraphCheckbox.addEventListener('change', () => {
                document.getElementById('collapseGraph').classList.toggle('show', openGraphCheckbox.checked);
                saveSettings();
            });

            loadSettings();
        }
    };
});