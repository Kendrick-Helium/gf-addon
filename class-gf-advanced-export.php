<?php

// Empêcher l'accès direct
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Inclure le framework de base de Gravity Forms.
GFForms::include_addon_framework();

class GFAdvancedExport extends GFAddOn {

	protected $_version = GF_ADVANCED_EXPORT_VERSION;
	protected $_min_gravityforms_version = '2.5';
	protected $_slug = 'gf-advanced-export';
	protected $_path = __FILE__;
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Advanced Export Add-On';
	protected $_short_title = 'Export Avancé';

	private static $_instance = null;

    private $export_csv;
	private $cron_handler;

	// Singleton pour récupérer l'instance unique de l'add-on
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFAdvancedExport();
		}
		return self::$_instance;
	}

	public static function includes() {

		// Inclure le fichier cron
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-gf-cron.php' );

		// Inclure le fichier d'export csv
		require_once( plugin_dir_path( __FILE__ ) . 'includes/class-gf-export-csv.php' );

	}

	public function __construct() {
        parent::__construct();

		// Charger les fichiers nécessaires
		$this->includes();
        
        // Initialisation du gestionnaire de Cron
        if ( class_exists( 'GF_Cron_Handler' ) ) {
            $this->cron_handler = new GF_Cron_Handler();
        }

        // Initialisation du gestionnaire d'export CSV
        if ( class_exists( 'GF_Export_Csv' ) ) {
            $this->export_csv = new GF_Export_Csv();
            add_action( 'wp_ajax_manual_export_csv', [ $this->export_csv, 'manual_export_csv' ] );
        }
    }

	// Initialisation de l'add-on
	public function init() {
		parent::init();

		add_filter( 'gform_form_settings', [ $this, 'form_settings_fields' ], 10, 2 );

		// Localiser le script cron-js après son enregistrement
		add_action( 'admin_enqueue_scripts', function() {
			// Localiser le script 'cron-js' après son enregistrement
			wp_localize_script( 'cron-js', 'CreateCron', [
				'nonce'    => wp_create_nonce('create_cron_task_nonce'),
			]);
		}, 11 );


		add_filter('gform_pre_submission_filter', function($form) {
			// Vérifier si des données conditionnelles ont été envoyées
			if (isset($_POST['conditional_logic'])) {
				// Enregistrer les conditions dans les paramètres du formulaire
				$form['conditional_logic'] = $_POST['conditional_logic'];
			}
			return $form;
		});

		// Action pour récupérer les conditions depuis la base de données
		add_action('wp_ajax_get_filters', [ $this, 'get_filters' ]);
	}

	// Paramètres globaux de l'add-on
	public function plugin_settings_fields() {
		return [
			[ 
				'title'  => 'Paramètres Globaux',
				'fields' => [
					[
						'name'    => 'default_email',
						'label'   => 'Email par défaut',
						'type'    => 'text',
						'class'   => 'medium',
						'tooltip' => 'Adresse email où envoyer les exports CSV par défaut.',
					],
				],
			],
		];
	}

	public function save_form_settings($form, $settings) {
		// Vérifier si des conditions ont été envoyées via POST
		if (isset($_POST['conditional_logic']) && is_array($_POST['conditional_logic'])) {

			// On récupère les conditions envoyées
			$conditions = $_POST['conditional_logic'];

			// Valider et/ou filtrer les conditions si nécessaire
			$validated_conditions = [];
			foreach ($conditions as $condition) {
				if (isset($condition['field'], $condition['operator'], $condition['value'])) {
					// Valider les données ici si nécessaire (par exemple, éviter les valeurs vides)
					if (!empty($condition['field']) && !empty($condition['operator']) && !empty($condition['value'])) {
						$validated_conditions[] = [
							'field' => sanitize_text_field($condition['field']),
							'operator' => sanitize_text_field($condition['operator']),
							'value' => sanitize_text_field($condition['value']),
						];
					}
				}
			}

			// Mettre à jour la structure des conditions dans le formulaire
			$form['conditional_logic'] = $validated_conditions;
		} else {
			$form['conditional_logic'] = [];
		}

		// Appeler la méthode parent pour sauvegarder les autres paramètres du formulaire
		return parent::save_form_settings($form, $settings);
	}
	
	function get_filters() {
		// Vérifier si l'utilisateur a les autorisations nécessaires
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
	
		// Obtenez l'ID du formulaire
		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;

		// Récupérer les données du formulaire à partir de la base de données
		if ( $form_id ) {
			$form = GFAPI::get_form( $form_id );

			if ( isset( $form['conditional_logic'] ) ) {

				// Si des conditions existent, renvoyez-les
				wp_send_json_success( array( 'conditional_logic' => $form['conditional_logic'] ) );
			} else {
				wp_send_json_error( array( 'message' => 'No conditional logic found' ) );
			}
		} else {
			wp_send_json_error( array( 'message' => 'Invalid form ID' ) );
		}
	}
	
	// Paramètres spécifiques au formulaire
	public function form_settings_fields( $form ) {
		// Récupérer les champs du formulaire pour les afficher dans les cases à cocher
		$choices = $this->get_form_fields_choices( $form['id'] );

		// Créer le tableau de choix pour les champs conditionnels
		$field_choices = [];

		foreach ($choices as $choice) {
			$field_choices[] = [
				'value' => $choice['value'],  // La valeur du champ
				'label' => $choice['label']   // L'étiquette du champ
			];
		}

		$field_choices_json = json_encode($field_choices); // Convertir le tableau PHP en JSON

		return array(
			array(
				'title'  => esc_html__( 'Export Avancé', 'advancedexport' ),
				'fields' => array(
					array(
						'name'  => 'hidden_form_id',
						'type'  => 'html',
						'html'  => '<input type="hidden" id="current_form_id" value="' . esc_attr( $form['id'] ) . '">',
					),
					array(
						'name'    => 'Email',
						'label'   => 'Email',
						'type'    => 'text',
						'class'   => 'medium',
						'tooltip' => 'Renseignez le/les email(s) où seront envoyés les exports. Séparez par une virgule.',
					),
					array(
						'name'    => 'export_interval',
						'label'   => 'Fréquence d\'Export',
						'type'    => 'select',
						'id'      => 'export_interval', // Ajouté pour le ciblage dans JS
						'choices' => array(
							array( 'label' => 'Toutes les heures', 'value' => 'hourly' ),
							array( 'label' => 'Tous les jours', 'value' => 'daily' ),
							array( 'label' => 'Toutes les semaines', 'value' => 'weekly' ),
							// array( 'label' => 'Toutes les deux semaines', 'value' => 'biweekly' ),
							// array( 'label' => 'Tous les mois', 'value' => 'monthly' ),
						),
						'default_value' => rgar( $form, 'export_interval' ),
					),
					array(
						'name'    => 'start_export_time',
						'label'   => 'Date début d\'Export',
						'type'    => 'text',
						'class'   => 'time-picker',
						'id'      => 'start_export_time',
						'tooltip' => 'Choisissez le jour et l\'heure du début de l\'export',
						'default_value' => rgar( $form, 'export_time' ),
					),
					array(
						'name'    => 'end_export_time',
						'label'   => 'Date fin d\'Export',
						'type'    => 'text',
						'class'   => 'time-picker',
						'id'      => 'end_export_time',
						'tooltip' => 'Choisissez le jour de fin de l\'export.',
						'default_value' => rgar( $form, 'export_time' ),
					),
					array(
						'name'    => 'fields_to_export',
						'label'   => 'Champs à Exporter',
						'type'    => 'checkbox',
						'tooltip' => 'Sélectionnez les champs à exporter.',
						'choices' => $choices,
					),
					array(
						'name'    => 'conditional_logic',
						'class'	  => 'choices',
						'label'   => 'Filtre d\'export',
						'tooltip' => 'Filtrez les champs (Ex : Date est hier. Ce filtre exportera toutes les entrées de la veille dynamiquement)',
						'type'    => 'html',
						'html'    => '<button class="button" id="add_filter_row" type="button">Ajouter une condition</button>
                                  	  <div id="conditional_logic_container" data-field-choices=\'' . $field_choices_json . '\'></div>',
					),
					array(
						'name'  => 'create_cron',
						'label' => '',
						'type'  => 'html',
						'html'  => '<button class="button" id="create_cron" type="button">Créer la tâche d\'export</button>',
					),
					array(
						'name'  => 'manual_export_button',
						'label' => '',
						'type'  => 'html',
						'html'  => '<button class="button" id="manual_export_button" type="button">Exporter Manuellement</button>',
					),
				),
			),
		);
	}
	
	// Récupérer les champs du formulaire pour les utiliser dans les choix des cases à cocher
	private function get_form_fields_choices( $form_id ) {
		$form = GFAPI::get_form( $form_id );
		$choices = [];

		if ( is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				$choices[] = array(
					'label' => esc_html( $field['label'] ),
					'name'  => 'field_' . $field['id'],
					'value' => $field['id'],
				);
			}
		}

		return $choices;
	}
	
	// Charger les scripts spécifiques à l'administration Gravity Forms
	public function scripts() {
		if ( ! GFForms::is_gravity_page() ) {
			return parent::scripts();
		}

		$scripts = array();

		$scripts[] = array(
			'handle'  => 'custom-datetimepicker',  
			'src'     => plugin_dir_url( __FILE__ ) . "/js/jquery.datetimepicker.full.min.js",
			'deps'    => array( 'jquery' ),
			'enqueue' => array(
				array(
					'admin_page' => 'form_settings',
					'tab'         => $this->_slug,
				),
			),
			'in_footer' => true,
		);

		$scripts[] = array(
			'handle'  => 'custom-js',
			'src'     => plugin_dir_url( __FILE__ ) . "/js/script.js",
			'deps'    => array( 'jquery' ),
			'enqueue' => array(
				array(
					'admin_page' => 'form_settings',
					'tab'         => $this->_slug,
				),
			),
			'in_footer' => true,
		);

		$scripts[] = array(
			'handle'  => 'filters-js',
			'src'     => plugin_dir_url( __FILE__ ) . "/js/filters.js",
			'deps'    => array( 'jquery' ),
			'enqueue' => array(
				array(
					'admin_page' => 'form_settings',
					'tab'         => $this->_slug,
				),
			),
			'in_footer' => true,
		);

		$scripts[] = array(
			'handle'  => 'cron-js',
			'src'     => plugin_dir_url( __FILE__ ) . "/js/cron.js",
			'deps'    => array( 'jquery' ),
			'enqueue' => array(
				array(
					'admin_page' => 'form_settings',
					'tab'         => $this->_slug,
				),
			),
			'in_footer' => true,
		);

		// Enregistrez et localisez le script après l'ajout du script cron
		add_action( 'admin_enqueue_scripts', function () {
			wp_localize_script( 'cron-js', 'CreateCron', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('create_cron_nonce'),
			]);
		}, 10, 0 );

		return $scripts;
	}

	// Charger les styles spécifiques à l'administration Gravity Forms
	public function styles() {
		// Charger uniquement sur les pages Gravity Forms
		if ( ! GFForms::is_gravity_page() ) {
			return parent::styles();
		}

		// Déclaration des styles
		$styles[] = [
			'handle'  => 'custom-datetimepicker-style',
			'src'     => plugin_dir_url( __FILE__ ) . "/css/jquery.datetimepicker.css", // Chemin vers votre fichier CSS
			'enqueue' => array(
				array(
					'admin_page' => 'form_settings',  // Spécifier les pages où le style sera chargé
					'tab'         => $this->_slug,
				),
			),
		];

		$styles[] = [
			'handle'  => 'custom-style',
			'src'     => plugin_dir_url( __FILE__ ) . "/css/style.css", // Chemin vers votre fichier CSS
			'enqueue' => array(
				array(
					'admin_page' => 'form_settings',  // Spécifier les pages où le style sera chargé
					'tab'         => $this->_slug,
				),
			),
		];

		// Retourner tous les styles ajoutés
		return array_merge( parent::styles(), $styles );
	}
}

GFAdvancedExport::get_instance();