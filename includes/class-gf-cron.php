<?php

class GF_Cron_Handler extends GFAddOn {

    public static function includes() {
        // Inclure le fichier d'export CSV
        require_once( plugin_dir_path( __FILE__ ) . 'class-gf-export-csv.php' );
    }

    public function __construct() {
        parent::__construct();
        // Charger les fichiers nécessaires
        $this->includes();

        // Ajouter l'action pour gérer l'appel AJAX
        add_action('wp_ajax_create_cron_task', [$this, 'handle_create_cron_task']);

        add_action('gf_advanced_export', [$this, 'execute_cron_export'], 10, 1);
    }

    public function handle_create_cron_task() {
        // Vérification des permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.', 403);
        }

        // Validation et nettoyage des données
        $clean_data = $this->validate_cron_data( $_POST );

        // Planification de la tâche
        $this->schedule_export_task( $clean_data );

        wp_send_json_success('Tâche Cron créée avec succès. Date de fin : ' . date('d/m/Y', $clean_data['end_timestamp']));
    }

    // Fonction pour valider et nettoyer les données envoyées par AJAX
    private function validate_cron_data( $data ) {

        $form_id            = intval($data['form_id'] ?? 0);
        // $hook_cron_name     = sanitize_text_field($data['cron_name'] ?? '');
        $email              = sanitize_email($data['email'] ?? '');
        $fields             = array_map('sanitize_text_field', $data['fields'] ?? []);
        $interval           = sanitize_text_field($data['export_interval'] ?? '');
        $start_date_cron    = sanitize_text_field($data['export_time'] ?? '');
        $end_date_cron      = sanitize_text_field($data['end_export_time'] ?? '');
    
        // Définir le fuseau horaire de Nouméa (UTC+11)
        $timezone_noumea = new DateTimeZone('Pacific/Noumea');
    
        // Convertir les dates en objets DateTime avec le fuseau horaire de Nouméa
        $start_date = DateTime::createFromFormat('d/m/Y H:i', $start_date_cron, $timezone_noumea);
        error_log(print_r($start_date, true));
        error_log(print_r($start_date_cron, true));
        
        $end_date   = DateTime::createFromFormat('d/m/Y', $end_date_cron, $timezone_noumea);
    
        // Vérifier si la date de début et de fin sont valides
        if (!$start_date || !$end_date) {
            wp_send_json_error('Les dates sont invalides.');
        }
    
        // Convertir en timestamps
        $start_timestamp = $start_date->getTimestamp();
        $end_timestamp   = $end_date->setTime(8, 00, 00)->getTimestamp(); // Fixer l'heure de fin à 08:00:00 en Nouméa
    
        // Valider les dates
        if ($end_timestamp <= $start_timestamp) {
            wp_send_json_error('La date de fin doit être supérieure à la date de début.');
        }
    
        // Valider les autres champs
        if (!$form_id || !$email || empty($fields) || !$interval) {
            wp_send_json_error('Données manquantes ou invalides.');
        }
    
        return compact('form_id', 'email', 'fields', 'interval', 'start_timestamp', 'end_timestamp');
    }

    // Fonction pour planifier la tâche Cron avec une date de fin
    private function schedule_export_task($data) {

        // $hook = 'gf_advanced_' . $data['hook_cron_name'];
        $hook = 'gf_advanced_export';

        $args = [
            'form_id' => $data['form_id'],
            'email' => $data['email'],
            'fields' => $data['fields'],
            'end_date_cron' => $data['end_timestamp'],
        ];
    
        // Supprimer les tâches existantes pour ce formulaire
        if (wp_next_scheduled($hook)) {
            wp_clear_scheduled_hook($hook);
        }
    
        // Planifier le cron seulement si la date de fin n'est pas dépassée
        $current_time = time();
        if ($current_time < $data['end_timestamp']) {
            wp_schedule_event($data['start_timestamp'], $data['interval'], $hook, $args);
        } else {
            error_log('La date de fin est dépassée. Le cron n\'a pas été planifié.');
        }
    }
    

    
    // Fonction pour exécuter l'export lorsque le cron est déclenché
    public function execute_cron_export( $args ) {
        // Récupérer le nom du hook et récupérer les arguments spécifiques au cron
        $hook_name = 'gf_advanced_export';
        
        // Récupérer les arguments spécifiques au cron
        $cron_args = $this->get_cron_task_args($hook_name);
        
        
        // Vérifier la date de fin
        if (!empty($cron_args['end_date_cron']) && time() >= $cron_args['end_date_cron']) {     
            // Supprimer le cron si la date de fin est atteinte
            wp_clear_scheduled_hook($hook_name, $args);
            error_log('Le cron "' . $hook_name . '" a été désactivé : la date de fin a été atteinte.');
            return;
        }
    
        // Créer une instance de l'export CSV et lancer l'export
        $export = new GF_Export_Csv();
        $export->generate_csv_and_send_email($cron_args['form_id'], $cron_args['fields'], $cron_args['email']);
    }



    function get_cron_task_args( $hook_name ) {
        // Récupérer toutes les tâches cron
        $cron_jobs = _get_cron_array();

        // Parcourir les tâches cron
        foreach ($cron_jobs as $timestamp => $hooks) {
            foreach ($hooks as $hook => $details) {
                // Rechercher le hook spécifique
                if ($hook === $hook_name) {
                    foreach ($details as $key => $data) {
                        // Vérifiez si des arguments sont définis
                        if (!empty($data['args'])) {
                            return $data['args'];
                        }
                    }
                }
            }
        }
    
        // Aucun argument trouvé
        error_log('Aucun argument trouvé pour le hook : ' . $hook_name);
        return null;
    }
}