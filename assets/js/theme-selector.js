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

    // Quand un thème est sélectionné
    themeSelect.on('change', function() {
        var themeSlug = $(this).val();

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
                        $.each(data.styles, function(index, style) {
                            styleSelect.append('<option value="' + style.id + '">' + style.label + '</option>');
                        });
                        stylesContainer.show();
                    }

                    // Ajouter les polices si disponibles
                    if (data.hasFonts && Object.keys(data.fonts).length > 0) {
                        $.each(data.fonts, function(slug, font) {
                            fontSelect.append('<option value="' + font.id + '">' + font.label + '</option>');
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
        // Vous pourriez implémenter un aperçu en direct ici
        // Par exemple, en appliquant temporairement des styles CSS
    });

    // Aperçu en temps réel des polices (optionnel)
    fontSelect.on('change', function() {
        var fontFamily = $(this).find('option:selected').data('family');
        if (fontFamily) {
            $('head').append('<style id="temp-font-preview">body { font-family: ' + fontFamily + ' !important; }</style>');
        } else {
            $('#temp-font-preview').remove();
        }
    });

    // Charger les informations du thème actuel au chargement initial
    if (themeSelect.val()) {
        themeSelect.trigger('change');
    }
});