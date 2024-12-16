jQuery(document).ready(function($) {

    // Initialisation de datetimepicker
    var $start_date_cron = $('#start_export_time');
    var $end_date_cron = $('#end_export_time');

    $start_date_cron.datetimepicker({
        datepicker: true,
        format: 'd/m/Y H:i',
        step: 5, 
    });

    $end_date_cron.datetimepicker({
        datepicker: true,
        format: 'd/m/Y'
    });



    // SEND AJAX MAIL
    $('#manual_export_button').on('click', function() {
        var formId = $('#current_form_id').val(); // Récupérer l'ID du formulaire
        var email = $('input[name="_gform_setting_Email"]').val(); // Email pour l'export

        // Parcourir tous les champs cachés et vérifier leur valeur
        var selectedFields = [];
        $('input[type="hidden"][name^="_gform_setting_field_"]').each(function() {
            // Vérifier si la valeur de l'input est égale à 1
            if ($(this).val() === "1") {
                var fieldId = $(this).attr('name').replace('_gform_setting_field_', ''); // Récupérer l'ID du champ à partir du nom
                selectedFields.push(fieldId); // Ajouter l'ID du champ au tableau
            }
        });

        if (selectedFields.length === 0) {
            alert('Veuillez sélectionner au moins un champ à exporter.');
            return;
        }

        // Effectuer la requête AJAX pour lancer l'exportation
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'manual_export_csv',
                formid: formId,
                email: email,
                fields: selectedFields
            },
            success: function(response) {
                alert('Exportation envoyée avec succès.');
            },
            error: function() {
                alert('Erreur lors de l\'exportation.');
            }
        });
    });
});