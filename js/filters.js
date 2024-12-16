jQuery(document).ready(function($) {
    // Bouton pour ajouter une nouvelle ligne de condition
    const $addFilterBtn = $('#add_filter_row');
    // Conteneur des conditions logiques
    const $conditionalLogicContainer = $('#conditional_logic_container');

    // ID du formulaire courant
    var formId = $('#current_form_id').val();

    // Récupérer les choix de champs depuis l'attribut 'data-field-choices' et les convertir en un tableau
    const rawFieldChoices = $conditionalLogicContainer.data('field-choices');
    const fieldChoices = typeof rawFieldChoices === 'string' ? JSON.parse(rawFieldChoices) : rawFieldChoices;

    // Fonction pour recalculer les indices des lignes (utile après ajout ou suppression)
    function recalculateIndices() {
        $conditionalLogicContainer.children('.condition-row').each(function(index) {
            $(this).find('.conditional-field').attr('name', `conditional_logic[${index}][field]`);
            $(this).find('.conditional-operator').attr('name', `conditional_logic[${index}][operator]`);
            $(this).find('.conditional-value').attr('name', `conditional_logic[${index}][value]`);
        });
    }

    // Fonction pour ajouter une nouvelle condition
    $addFilterBtn.on('click', function () {
        // Calcul de l'indice basé sur le nombre actuel de lignes
        const index = $conditionalLogicContainer.children('.condition-row').length;

        // Génération du champ "Field"
        let fieldDropdown = `
            <select name="conditional_logic[${index}][field]" class="conditional-field">
                ${fieldChoices.map(choice => `<option value="${choice.value}">${choice.label}</option>`).join('')}
                <option value="date_created">Date</option>
            </select>
        `;

        // Génération du champ "Operator"
        const operatorDropdown = `
            <select name="conditional_logic[${index}][operator]" class="conditional-operator">
                <option value="is">est</option>
                <option value="is_not">n'est pas</option>
                <option value="greater_than">plus grand que</option>
                <option value="less_than">plus petit que</option>
                <option value="contains">contient</option>
            </select>
        `;

        // Génération du champ "Value"
        const valueInput = `<input type="text" name="conditional_logic[${index}][value]" class="conditional-value" placeholder="Valeur" />`;

        // Boutons d'ajout et de suppression
        const addButton = '<button type="button" class="add-condition">+</button>';
        const deleteButton = '<button type="button" class="delete-condition">-</button>';

        // Ajout d'une nouvelle condition
        $conditionalLogicContainer.append(`
            <div class="condition-row" data-id="${index}">
                ${fieldDropdown}
                ${operatorDropdown}
                ${valueInput}
                ${addButton}
                ${deleteButton}
            </div>
        `);

        // Mise à jour des indices
        recalculateIndices();

        updateAddButtonVisibility();
    });

    // Suppression d'une condition (avec suppression côté serveur via AJAX)
    $conditionalLogicContainer.on('click', '.delete-condition', function() {
        const $row = $(this).closest('.condition-row');
        $row.remove();
        recalculateIndices();
        updateAddButtonVisibility();
    });


    // Vérifier le nombre de conditions et mettre à jour l'état du bouton
    function updateAddButtonVisibility() {
        // Compter le nombre de lignes "condition-row"
        const index = $conditionalLogicContainer.children('.condition-row').length;

        // Si des conditions existent (au moins une ligne)
        if (index > 0) {
            // Cachez le bouton "ajouter" s'il y a des lignes
            $addFilterBtn.hide();
        } else {
            // Sinon, affichez le bouton "ajouter"
            $addFilterBtn.show();
        }
    }

    // Clonage d'une condition (ajout d'une ligne similaire)
    $conditionalLogicContainer.on('click', '.add-condition', function() {
        // Clone la ligne actuelle et réinitialise les valeurs
        const $row = $(this).closest('.condition-row').clone();
        $row.find('input, select').val(''); // Réinitialiser les valeurs des champs
        $conditionalLogicContainer.append($row);
        // Recalcule les indices après l'ajout
        recalculateIndices();
    });

    // Fonction pour charger les conditions existantes via AJAX
    function loadExistingConditions(formId) {
        $.ajax({
            url: ajaxurl, // URL de l'action AJAX
            method: 'POST',
            data: {
                action: 'get_filters',
                form_id: formId
            },
            success: function(response) {
                if (response.success) {
                    populateExistingConditions(response.data.conditional_logic);
                    updateAddButtonVisibility();
                } else {
                    console.log('Erreur :', response);
                }
            },
            error: function(xhr, status, error) {
                console.log('Erreur AJAX:', xhr, status, error);
            }
        });
    }

    // Fonction pour afficher les conditions existantes dans le conteneur
    function populateExistingConditions(conditions) {
        if (conditions && Array.isArray(conditions)) {
            // Parcourt chaque condition existante
            conditions.forEach((condition, index) => {
                // Génère le champ "Field" avec sélection de la valeur actuelle
                const fieldDropdown = `
                    <select name="conditional_logic[${index}][field]" class="conditional-field">
                        ${fieldChoices.map(choice => 
                            `<option value="${choice.value}" ${choice.value == condition.field ? 'selected' : ''}>${choice.label}</option>`
                        ).join('')}
                        <option value="date_created" ${condition.field === 'date_created' ? 'selected' : ''}>Date</option>
                    </select>
                `;

                // Génère le champ "Operator" avec sélection de l'opérateur actuel
                const operatorDropdown = `
                    <select name="conditional_logic[${index}][operator]" class="conditional-operator">
                        <option value="is" ${condition.operator == 'is' ? 'selected' : ''}>est</option>
                        <option value="is_not" ${condition.operator == 'is_not' ? 'selected' : ''}>n'est pas</option>
                        <option value="greater_than" ${condition.operator == 'greater_than' ? 'selected' : ''}>plus grand que</option>
                        <option value="less_than" ${condition.operator == 'less_than' ? 'selected' : ''}>plus petit que</option>
                        <option value="contains" ${condition.operator == 'contains' ? 'selected' : ''}>contient</option>
                    </select>
                `;

                // Génère le champ "Value" avec la valeur actuelle
                const valueInput = `
                    <input type="text" name="conditional_logic[${index}][value]" class="conditional-value" value="${condition.value}" placeholder="Valeur" />
                `;

                // Boutons d'ajout et de suppression
                const addButton = '<button type="button" class="add-condition">+</button>';
                const deleteButton = '<button type="button" class="delete-condition">-</button>';

                // Ajout de la ligne au conteneur
                $conditionalLogicContainer.append(`
                    <div class="condition-row" data-id="${index}">
                        ${fieldDropdown}
                        ${operatorDropdown}
                        ${valueInput}
                        ${addButton}
                        ${deleteButton}
                    </div>
                `);
            });
            // Recalcule les indices après l'ajout
            recalculateIndices();
        } else {
            // Si aucune condition n'existe, afficher un message ou laisser vide
            $conditionalLogicContainer.append('<p>Aucune condition trouvée.</p>');
        }
    }

    // Charger les conditions existantes au démarrage
    loadExistingConditions(formId);

    $conditionalLogicContainer.on('input', '.conditional-value', function () {
        const $row = $(this).closest('.condition-row');
        const selectedField = $row.find('.conditional-field').val();
        const inputValue = $(this).val();
    
        if (selectedField === 'date_created') {
            const resolvedDate = resolveDateExpression(inputValue);
            $(this).val(resolvedDate); // Met à jour le champ avec la date résolue
        }
    });

    // Fonction pour résoudre une expression de date
    function resolveDateExpression(expression, timezone = 'Pacific/Noumea') {
        const today = new Date();
        const now = new Date(today.toLocaleString('en-US', { timeZone: timezone })); // Convertir la date au fuseau horaire de Nouméa

        switch (expression.toLowerCase()) {
            case 'aujourd\'hui':
                return formatDate(now); // Retourne la date d'aujourd'hui en format "DD/MM/YYYY"
            case 'hier':
                const yesterday = new Date(now);
                yesterday.setDate(now.getDate() - 1);
                return formatDate(yesterday); // Retourne la date d'hier en format "DD/MM/YYYY"
            default:
                return expression; // Retourne l'expression brute si non reconnue
        }
    }

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${day}/${month}/${year}`; // Format "DD/MM/YYYY" pour les comparaisons
    }
});