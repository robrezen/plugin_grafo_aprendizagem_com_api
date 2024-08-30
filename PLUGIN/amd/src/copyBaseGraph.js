define(['jquery', 'core/str', 'core/notification', 'core/log', 'core/modal_factory'], function($, str, notification, log, ModalFactory) {
    return {
        init: () =>{
            log.debug('copyBaseGraph init');
            $('#id_copybasegraphbtn').on('click', (e) => {
                
                e.preventDefault();
                log.info(e)
                log.info('copybasegraphbtn clicked');
                const userid = $('#id_selecteduser').val();
                if (userid) {
                    str.get_string('confirm_copy_base_graph', 'mod_learninggraph').then((confirmstr) => {
                        if (confirm(confirmstr)) {
                            window.location.href = M.cfg.wwwroot + '/mod/learninggraph/copy_base_graph.php?cmid=' + M.cfg.contextInstanceId + '&userid=' + userid;
                        }
                    });
                } else {
                    notification.alert(str.get_string('error'), str.get_string('nouserselected', 'mod_learninggraph'), 'error');
                }
            });

            var existingGraphsError = document.querySelector('[data-error=existing_graphs]');
            log.debug(existingGraphsError);
            if (existingGraphsError) {
                var existingGraphs = JSON.parse(existingGraphsError.innerText);

                str.get_strings([
                    {key: 'existing_graphs_title', component: 'mod_learninggraph'},
                    {key: 'existing_graphs_message', component: 'mod_learninggraph'},
                    {key: 'yes', component: 'moodle'},
                    {key: 'no', component: 'moodle'}
                ]).done(function(strings) {
                    ModalFactory.create({
                        type: ModalFactory.types.SAVE_CANCEL,
                        title: strings[0],
                        body: strings[1],
                        preShowCallback: function(modal) {
                            // Set the existing graphs data in the modal
                            modal.getRoot().find('.modal-body').html(strings[1] + '<br>' + existingGraphs.join(', '));
                        }
                    }).done(function(modal) {
                        modal.getRoot().on(ModalEvents.save, function() {
                            // Lógica para usar as questões para criar o grafo base
                            document.querySelector('input[name=\"use_existing_graphs\"]').value = 1;
                            modal.hide();
                            // Submeter o formulário
                            document.querySelector('form').submit();
                        });

                        modal.getRoot().on(ModalEvents.cancel, function() {
                            modal.hide();
                        });

                        modal.show();
                    });
                });
            }
        }
    };
});