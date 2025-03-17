/**
 * JavaScript pour le sélecteur de thème
 */
jQuery(document).ready(function($) {
    var themeSelect = $('#theme-select');
    var styleSelect = $('#style-select');
    var fontSelect = $('#font-select');
    var stylesContainer = $('#theme-styles-container');
    var fontsContainer = $('#theme-fonts-container');
    var themePreview = $('#theme-preview');
    var toggleButton = $('#theme-selector-toggle');
    var popup = $('#theme-selector-popup');
    var closeButton = $('#theme-selector-close');
    
    // Fonctions pour gérer le stockage des sélections
    function saveSelection(key, value) {
        try {
            localStorage.setItem('theme_selector_' + key, value);
        } catch (e) {
            console.error('Erreur lors de la sauvegarde de la sélection:', e);
        }
    }
    
    function getSelection(key, defaultValue) {
        try {
            var value = localStorage.getItem('theme_selector_' + key);
            return value !== null ? value : defaultValue;
        } catch (e) {
            console.error('Erreur lors de la récupération de la sélection:', e);
            return defaultValue;
        }
    }
    
    // Gestion de l'ouverture/fermeture de la popup
    toggleButton.on('click', function() {
        popup.addClass('active');
        // Sauvegarder l'état de la popup
        saveSelection('popup_open', 'true');
    });
    
    closeButton.on('click', function() {
        popup.removeClass('active');
        // Sauvegarder l'état de la popup
        saveSelection('popup_open', 'false');
    });
    
    // Fermer la popup en cliquant en dehors (optionnel)
    $(document).on('click', function(event) {
        if (!$(event.target).closest('#theme-selector-popup, #theme-selector-toggle').length) {
            popup.removeClass('active');
            saveSelection('popup_open', 'false');
        }
    });
    
    // Rétablir l'état de la popup au chargement
    if (getSelection('popup_open', 'false') === 'true') {
        popup.addClass('active');
    }

    // Quand un thème est sélectionné
    themeSelect.on('change', function() {
        var themeSlug = $(this).val();
        
        // Sauvegarder la sélection du thème
        saveSelection('theme', themeSlug);

        // Réinitialiser les autres sélecteurs
        styleSelect.html('<option value="">' + themeSelectorData.defaultStyleText + '</option>');
        fontSelect.html('<option value="">' + themeSelectorData.defaultFontText + '</option>');
        stylesContainer.hide();
        fontsContainer.hide();
        themePreview.empty();

        if (!themeSlug) {
            return;
        }

        // Afficher un indicateur de chargement
        themePreview.html('<div class="loading-indicator">Chargement des informations du thème...</div>');

        // Appel AJAX pour récupérer les styles et polices
        $.ajax({
            url: themeSelectorData.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_theme_styles',
                nonce: themeSelectorData.nonce,
                theme: themeSlug
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;

                    // Mettre à jour l'aperçu du thème
                    var previewHtml = '<div class="theme-info">';

                    if (data.theme.screenshot) {
                        previewHtml += '<div class="theme-screenshot"><img src="' + data.theme.screenshot + '" alt="' + data.theme.name + '" /></div>';
                    }

                    previewHtml += '<h4>' + data.theme.name + '</h4>';
                    previewHtml += '<p>' + data.theme.description + '</p>';
                    previewHtml += '</div>';

                    themePreview.html(previewHtml);

                    // Ajouter les styles si disponibles
                    if (data.hasStyles && data.styles.length > 0) {
                        var savedStyle = getSelection('style', '');
                        $.each(data.styles, function(index, style) {
                            var selected = (savedStyle === style.id) ? ' selected' : '';
                            styleSelect.append('<option value="' + style.id + '"' + selected + '>' + style.label + '</option>');
                        });
                        stylesContainer.show();
                    }

                    // Ajouter les polices si disponibles
                    if (data.hasFonts && Object.keys(data.fonts).length > 0) {
                        var savedFont = getSelection('font', '');
                        $.each(data.fonts, function(slug, font) {
                            var selected = (savedFont === font.id) ? ' selected' : '';
                            fontSelect.append('<option value="' + font.id + '"' + selected + '>' + font.label + '</option>');
                        });
                        fontsContainer.show();
                    }
                } else {
                    themePreview.html('<div class="error-message">Erreur: ' + response.data + '</div>');
                }
            },
            error: function() {
                themePreview.html('<div class="error-message">Une erreur est survenue lors de la communication avec le serveur.</div>');
            }
        });
    });

    // Aperçu en temps réel des styles (optionnel)
    styleSelect.on('change', function() {
        // Sauvegarder la sélection du style
        saveSelection('style', $(this).val());
        
        // Vous pourriez implémenter un aperçu en direct ici
        // Par exemple, en appliquant temporairement des styles CSS
    });

    // Aperçu en temps réel des polices (optionnel)
    fontSelect.on('change', function() {
        // Sauvegarder la sélection de la police
        saveSelection('font', $(this).val());
        
        var fontFamily = $(this).find('option:selected').data('family');
        if (fontFamily) {
            $('head').append('<style id="temp-font-preview">body { font-family: ' + fontFamily + ' !important; }</style>');
        } else {
            $('#temp-font-preview').remove();
        }
    });

    // Restaurer les sélections précédentes
    var savedTheme = getSelection('theme', '');
    if (savedTheme) {
        // Sélectionner le thème sauvegardé
        themeSelect.val(savedTheme);
    }
    
    // Charger les informations du thème actuel au chargement initial
    if (themeSelect.val()) {
        themeSelect.trigger('change');
    }
});