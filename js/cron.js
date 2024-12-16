jQuery(document).ready(function ($) {
    $('#create_cron').on('click', function () {
        const button = $(this).prop('disabled', true).text('Création en cours...');

        console.log($('#end_export_time').val());

        const data = {
            action: 'create_cron_task',
            form_id: $('#current_form_id').val(),
            email: $('input[name="_gform_setting_Email"]').val(),
            export_interval: $('#export_interval').val(),
            export_time: $('#start_export_time').val(),
            end_export_time : $('#end_export_time').val(),
            fields: $('input[type="hidden"][name^="_gform_setting_field_"]')
                .filter((_, el) => $(el).val() === "1")
                .map((_, el) => $(el).attr('name').replace('_gform_setting_field_', ''))
                .get(),
            _wpnonce: CreateCron.nonce,
        };

        if (!data.fields.length) {
            alert('Veuillez sélectionner au moins un champ à exporter.');
            button.prop('disabled', false).text('Créer la tâche d\'export');
            return;
        }

        $.post(ajaxurl, data)
            .done(response => alert(response.success ? response.data : 'Erreur : ' + response.data))
            .fail(() => alert('Une erreur s\'est produite.'))
            .always(() => button.prop('disabled', false).text('Créer la tâche d\'export'));
    });
});