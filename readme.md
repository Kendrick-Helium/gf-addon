# Add-on pour Gravity Forms : Exportation Planifiée avec Filtrage

Cet add-on permet de planifier des exportations de données de formulaires Gravity Forms. Vous pouvez configurer des exports récurrents (par heure, jour, semaine, mois) et filtrer les entrées en fonction de critères avancés (par exemple, récupérer des entrées avec une date relative comme "hier").

## Fonctionnalités

- **Planification des exports** : Planifiez des exportations de manière récurrente (par heure, jour, semaine, mois).
- **Filtrage avancé** : Filtrez les entrées selon des critères spécifiques (par exemple, récupérer les entrées de "hier" ou de "aujourd'hui").
- **Champ `date_created`** : Utilisez la date de création des entrées dans vos filtres, avec des expressions comme "hier" ou "aujourd'hui".
- **Fuseau horaire de Nouméa** : Les calculs de dates comme "hier" sont effectués selon le fuseau horaire de Nouméa (`Pacific/Noumea`).

## Prérequis

- WordPress 5.0 ou version supérieure
- Gravity Forms 2.5 ou version supérieure

## Installation

1. Téléchargez l'add-on et placez-le dans le dossier `wp-content/plugins/`.
2. Activez le plugin depuis le tableau de bord WordPress sous **Extensions**.
3. Une fois activé, allez dans **Forms** -> **Paramètres** pour configurer la planification des exports.

## Configuration

### Ajouter une tâche d'exportation planifiée

1. Accédez à l'onglet **Paramètres** du formulaire Gravity Forms que vous souhaitez configurer.
2. Sous **Exportations planifiées**, vous verrez un formulaire pour ajouter des tâches d'exportation.
3. Vous pouvez définir les critères de planification, comme l'exportation quotidienne, hebdomadaire, ou mensuelle.
4. Sélectionnez les champs à exporter.
5. Configurez les filtres pour exporter uniquement certaines entrées (par exemple, celles créées "hier").

### Champs de filtre

Dans le formulaire d'ajout de condition, vous pouvez utiliser les options suivantes :

- **Field** : Sélectionnez un champ à filtrer (par exemple, `date_created` pour la date de création des entrées).
- **Operator** : Choisissez un opérateur de filtre (par exemple, `est`, `plus grand que`, etc.).
- **Value** : Entrez une valeur. Vous pouvez utiliser des expressions de date comme :
    - **aujourd'hui** : Pour récupérer les entrées créées aujourd'hui.
    - **hier** : Pour récupérer les entrées créées hier.
  
### Exemple de filtre

- **Field** : `date_created`
- **Operator** : `est`
- **Value** : `hier`

Cela permettra de récupérer toutes les entrées créées le jour précédent.

## Fonctionnement du Cron

Lors du lancement d'un export via le cron, si un champ `date_created` utilise l'expression `hier`, la date sera automatiquement mise à jour pour correspondre à la veille au moment de l'exécution du cron.

### Exemple de configuration d'exportation

L'export sera automatiquement généré en fonction de la planification et des filtres que vous avez définis dans le formulaire.

### Paramètres du Cron

Le cron s'exécutera selon la fréquence que vous avez configurée (par exemple, chaque jour, à minuit, pour un export quotidien).

## Exemple de Code PHP pour les Filtres

Voici comment les filtres sont construits avant d'être envoyés pour l'exportation :

```php
private function build_field_filters_from_conditions($conditions) {
    $field_filters = [];
    
    // Récupérer la date d'hier (au fuseau horaire de Nouméa)
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