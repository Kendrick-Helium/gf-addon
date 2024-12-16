<?php

class GF_Export_Csv extends GFAddOn {

    // Fonction de génération du CSV et envoi par email
	private function prepare_csv_data( $form, $entries, $fields ) {
		// Préparer les en-têtes CSV
		$csv_data = [];
		$headers = array_map( fn( $field_id ) => mb_convert_encoding( GFAPI::get_field( $form['id'], $field_id )->label, 'UTF-8', 'auto' ), $fields );
		$csv_data[] = $headers;
	
		// Ajouter les données des entrées
		foreach ( $entries as $entry ) {
			$row = array_map( fn( $field_id ) => mb_convert_encoding( $entry[ $field_id ] ?? '', 'UTF-8', 'auto' ), $fields );
			$csv_data[] = $row;
		}
	
		return $csv_data;
	}
	
	private function write_csv_file( $file_path, $csv_data ) {
		// Créer le fichier CSV avec un BOM UTF-8
		$file = fopen( $file_path, 'w' );
		fputs( $file, "\xEF\xBB\xBF" ); // UTF-8 BOM
		foreach ( $csv_data as $line ) {
			fputcsv( $file, $line );
		}
		fclose( $file );
	}
	
	private function send_email_with_attachment( $file_path, $form_name, $current_date, $emails ) {
		// Décoder les caractères spéciaux pour le sujet de l'email
		$subject = 'Export CSV formulaire ' . utf8_encode($form_name) . ' - ' . $current_date;
		$message = 'Veuillez trouver l\'export des entrées en pièce jointe.';
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
	
		// Diviser les emails en un tableau
		$email_list = array_map( 'trim', explode( ',', $emails ) ); // Supprime les espaces autour des emails
	
		// Préparer les pièces jointes
		$attachments = array( $file_path );
	
		// Envoyer l'email à chaque destinataire
		foreach ( $email_list as $email ) {
			wp_mail( $email, $subject, $message, $headers, $attachments );
		}
	
		return true; // Retourner `true` si tout s'est bien passé
	}



	public function generate_csv_and_send_email( $form_id, $fields, $email ) {
		// Récupérer le formulaire
		$form = GFAPI::get_form( $form_id );
		if ( is_wp_error( $form ) ) {
			return false;
		}
	
		$conditions = $form['conditional_logic'] ?? [];

		// Construire les critères de recherche
		$search_criteria = [
			'status'        => 'active', // Exemple : filtrer uniquement les entrées actives
			'field_filters' => $this->build_field_filters_from_conditions( $conditions ), // Appliquer les filtres ici
		];
	
		// Pagination
		$paging = [ 'offset' => 0, 'page_size' => 1000 ];
	
		// Récupérer les entrées
		$entries = GFAPI::get_entries( $form_id, $search_criteria, null, $paging );
	
		if ( is_wp_error( $entries ) || empty( $entries ) ) {
			return false;
		}
	
		// Récupérer le nom du formulaire et la date actuelle
		$form_name = sanitize_title_with_dashes( $form['title'] );
		$current_date = date( 'd-m-Y' );
	
		// Nom du fichier CSV
		$csv_filename = 'Export_' . $form_name . '_' . $current_date . '.csv';
	
		// Préparer les données CSV
		$csv_data = $this->prepare_csv_data( $form, $entries, $fields );
	
		// Chemin temporaire pour le fichier CSV
		$file_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $csv_filename;
	
		// Écrire les données CSV dans un fichier
		$this->write_csv_file( $file_path, $csv_data );
	
		// Envoyer l'email avec le fichier CSV en pièce jointe
		$mail_sent = $this->send_email_with_attachment( $file_path, $form_name, $current_date, $email );
	
		// Supprimer le fichier temporaire
		unlink( $file_path );
	
		return $mail_sent;
	}

	private function build_field_filters_from_conditions($conditions) {
		$field_filters = [];
		
		// Récupérer la date de la veille (si besoin)
		$yesterday = $this->get_yesterday_date(); // Récupère la date d'hier au format Y-m-d
		
		foreach ($conditions as $condition) {
			if (isset($condition['field'], $condition['operator'], $condition['value'])) {
				$key = $condition['field'];
				$value = $condition['value'];
				$operator = $this->map_operator_to_gf($condition['operator']);
		
				// Vérifier si le champ est "date_created"
				if ($key === 'date_created') {
					// Si la valeur est 'hier', remplacer par la date d'hier
					if (strtolower($value) === 'hier') {
						$value = $yesterday; // Utilise la date d'hier
					} else {
						// Convertir les valeurs en timestamps si nécessaire
						$timestamp = strtotime($value);
						if ($timestamp) {
							$value = date('Y-m-d', $timestamp); // Formater en "YYYY-MM-DD"
						}
					}
				}
	
				$field_filters[] = [
					'key'      => $key,
					'value'    => $value,
					'operator' => $operator,
				];
			}
		}
	
		return $field_filters;
	}
	
	// Fonction pour obtenir la date d'hier au format Y-m-d
	private function get_yesterday_date($timezone = 'Pacific/Noumea') {
		$now = new DateTime('now', new DateTimeZone($timezone));
		$yesterday = new DateTime('yesterday', new DateTimeZone($timezone));
		return $yesterday->format('Y-m-d');
	}
	
	// Mapper les opérateurs de votre interface utilisateur aux opérateurs Gravity Forms
	private function map_operator_to_gf($operator) {
		// Mappage des opérateurs utilisés dans votre logique conditionnelle vers ceux que GFAPI attend
		switch ($operator) {
			case 'is':
				return 'is';
			case 'is_not':
				return 'isnot';
			case 'greater_than':
				return 'greaterthan';
			case 'less_than':
				return 'lessthan';
			case 'contains':
				return 'like';
			default:
				return 'is';
		}
	}

    // Exporter manuellement le CSV
	public function manual_export_csv() {
		if ( isset( $_POST['formid'] ) && isset( $_POST['email'] ) && isset( $_POST['fields'] ) ) {
			$form_id = sanitize_text_field( $_POST['formid'] );
			$email = sanitize_text_field( $_POST['email'] ); // Toujours une chaîne, même avec plusieurs emails
			$fields = $_POST['fields'];
	
			$result = $this->generate_csv_and_send_email( $form_id, $fields, $email );
	
			if ( $result ) {
				wp_send_json_success( 'Exportation envoyée par email.' );
			} else {
				wp_send_json_error( 'Une erreur est survenue.' );
			}
		} else {
			wp_send_json_error( 'Paramètres invalides.' );
		}
	}
}