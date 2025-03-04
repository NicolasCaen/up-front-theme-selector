<?php
/**
 * Plugin Name: Sélecteur de Thème Front-End
 * Description: Permet aux utilisateurs de changer de thème et de styles via un shortcode.
 * Version: 1.0.0
 * Author: Claude
 * Text Domain: theme-selector
 */

// Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

class Theme_Selector {
    /**
     * Constructeur
     */
    public function __construct() {
        // Enregistrement du shortcode
        add_shortcode('theme_selector', array($this, 'theme_selector_shortcode'));

        // Ajouter les scripts et styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX pour charger les styles d'un thème
        add_action('wp_ajax_get_theme_styles', array($this, 'ajax_get_theme_styles'));
        add_action('wp_ajax_nopriv_get_theme_styles', array($this, 'ajax_get_theme_styles'));

        // Hook pour traiter le formulaire soumis
        add_action('template_redirect', array($this, 'process_theme_form'));

        // Ajouter la fonction pour appliquer le thème sélectionné
        add_action('setup_theme', array($this, 'apply_selected_theme'), 5);
    }

    /**
     * Enregistrer les scripts et styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'theme-selector-css',
            plugin_dir_url(__FILE__) . 'assets/css/theme-selector.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'theme-selector-js',
            plugin_dir_url(__FILE__) . 'assets/js/theme-selector.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script(
            'theme-selector-js',
            'themeSelectorData',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('theme_selector_nonce'),
            )
        );
    }

    /**
     * Shortcode pour afficher le sélecteur de thème
     */
    public function theme_selector_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'title' => __('Sélecteur de thème', 'theme-selector'),
            ),
            $atts,
            'theme_selector'
        );

        $themes = wp_get_themes();
        $current_theme = wp_get_theme();

        // Démarrer le buffer de sortie
        ob_start();
        ?>
        <div class="theme-selector-container">
            <h3><?php echo esc_html($atts['title']); ?></h3>

            <form method="post" action="" id="theme-selector-form">
                <?php wp_nonce_field('theme_selector_action', 'theme_selector_nonce'); ?>

                <div class="theme-selector-field">
                    <label for="theme-select"><?php _e('Choisir un thème :', 'theme-selector'); ?></label>
                    <select name="selected_theme" id="theme-select">
                        <option value=""><?php _e('-- Sélectionner un thème --', 'theme-selector'); ?></option>
                        <?php foreach ($themes as $theme_slug => $theme) : ?>
                            <option value="<?php echo esc_attr($theme_slug); ?>" <?php selected($theme_slug, $current_theme->get_stylesheet()); ?>>
                                <?php echo esc_html($theme->get('Name')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="theme-styles-container" style="display: none;">
                    <div class="theme-selector-field">
                        <label for="style-select"><?php _e('Choisir un style :', 'theme-selector'); ?></label>
                        <select name="selected_style" id="style-select">
                            <option value=""><?php _e('-- Style par défaut --', 'theme-selector'); ?></option>
                            <!-- Les options de style seront chargées ici par AJAX -->
                        </select>
                    </div>
                </div>

                <div id="theme-fonts-container" style="display: none;">
                    <div class="theme-selector-field">
                        <label for="font-select"><?php _e('Choisir une police :', 'theme-selector'); ?></label>
                        <select name="selected_font" id="font-select">
                            <option value=""><?php _e('-- Police par défaut --', 'theme-selector'); ?></option>
                            <!-- Les options de police seront chargées ici par AJAX -->
                        </select>
                    </div>
                </div>

                <div class="theme-selector-preview" id="theme-preview">
                    <!-- Aperçu du thème ici -->
                </div>

                <div class="theme-selector-submit">
                    <button type="submit" name="apply_theme" class="button button-primary">
                        <?php _e('Appliquer', 'theme-selector'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX pour récupérer les styles d'un thème
     */
    public function ajax_get_theme_styles() {
        check_ajax_referer('theme_selector_nonce', 'nonce');

        $theme_slug = isset($_POST['theme']) ? sanitize_text_field($_POST['theme']) : '';

        if (empty($theme_slug)) {
            wp_send_json_error(__('Aucun thème spécifié.', 'theme-selector'));
        }

        $theme = wp_get_theme($theme_slug);
        if (!$theme->exists()) {
            wp_send_json_error(__('Ce thème n\'existe pas.', 'theme-selector'));
        }

        $response = array(
            'styles' => array(),
            'fonts' => array(),
            'hasStyles' => false,
            'hasFonts' => false,
        );

        // Récupérer les styles du thème
        if ($this->theme_has_styles($theme_slug)) {
            $styles = $this->get_theme_styles($theme_slug);
            if (!empty($styles)) {
                $response['hasStyles'] = true;
                $response['styles'] = $styles;
            }
        }

        // Récupérer les polices du thème
        $fonts = $this->get_theme_fonts($theme_slug);
        if (!empty($fonts)) {
            $response['hasFonts'] = true;
            $response['fonts'] = $fonts;
        }

        // Informations sur le thème
        $response['theme'] = array(
            'name' => $theme->get('Name'),
            'description' => $theme->get('Description'),
            'screenshot' => $theme->get_screenshot() ? $theme->get_screenshot() : '',
        );

        wp_send_json_success($response);
    }

    /**
     * Traiter le formulaire soumis
     */
    public function process_theme_form() {
        if (!isset($_POST['apply_theme']) || !isset($_POST['theme_selector_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['theme_selector_nonce'], 'theme_selector_action')) {
            wp_die(__('Sécurité: Nonce invalide.', 'theme-selector'));
        }

        $theme_slug = isset($_POST['selected_theme']) ? sanitize_text_field($_POST['selected_theme']) : '';
        $style_id = isset($_POST['selected_style']) ? sanitize_text_field($_POST['selected_style']) : '';
        $font_id = isset($_POST['selected_font']) ? sanitize_text_field($_POST['selected_font']) : '';

        if (empty($theme_slug)) {
            return;
        }

        // Vérifier si le thème existe
        $theme = wp_get_theme($theme_slug);
        if (!$theme->exists()) {
            return;
        }

        // Stocker les sélections dans des cookies (30 jours)
        setcookie('ts_theme', $theme_slug, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);

        if (!empty($style_id)) {
            setcookie('ts_style', $style_id, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        } else {
            setcookie('ts_style', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN); // Supprimer le cookie
        }

        if (!empty($font_id)) {
            setcookie('ts_font', $font_id, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        } else {
            setcookie('ts_font', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN); // Supprimer le cookie
        }

        // Si l'utilisateur est administrateur, on peut changer le thème
        if (current_user_can('switch_themes')) {
            switch_theme($theme_slug);
        }

        // Rediriger pour appliquer les changements
        wp_redirect(remove_query_arg(array('theme', 'style', 'font')));
        exit;
    }

    /**
     * Appliquer le thème sélectionné
     */
    public function apply_selected_theme() {
        // Ne pas exécuter dans l'admin
        if (is_admin()) {
            return;
        }
    
        // Vérifier si un thème est sélectionné via cookie
        if (!isset($_COOKIE['ts_theme']) || empty($_COOKIE['ts_theme'])) {
            return;
        }
    
        $theme_slug = sanitize_text_field($_COOKIE['ts_theme']);
        $theme = wp_get_theme($theme_slug);
    
        if (!$theme->exists()) {
            return;
        }
    
        // Utiliser un filtre unique au lieu de plusieurs
        add_filter('template', function() use ($theme) {
            return $theme->get_template();
        });
    
        // Charger les styles et polices de manière conditionnelle
        $style_id = isset($_COOKIE['ts_style']) ? sanitize_text_field($_COOKIE['ts_style']) : '';
        $font_id = isset($_COOKIE['ts_font']) ? sanitize_text_field($_COOKIE['ts_font']) : '';
    
        if (!empty($style_id) || !empty($font_id)) {
            add_action('wp_head', function() use ($theme_slug, $style_id, $font_id) {
                if (!empty($style_id)) {
                    $this->apply_style($theme_slug, $style_id);
                }
                if (!empty($font_id)) {
                    $this->apply_font($theme_slug, $font_id);
                }
            }, 999);
        }
    }
    // public function apply_selected_theme() {
    //     // Ne pas exécuter dans l'admin
    //     if (is_admin()) {
    //         return;
    //     }

    //     // Vérifier si un thème est sélectionné via cookie
    //     if (isset($_COOKIE['ts_theme']) && !empty($_COOKIE['ts_theme'])) {
    //         $theme_slug = sanitize_text_field($_COOKIE['ts_theme']);
    //         $theme = wp_get_theme($theme_slug);

    //         if ($theme->exists()) {
    //             // Pour tous les utilisateurs, appliquer le thème via filtres
    //             add_filter('stylesheet', function() use ($theme) {
    //                 return $theme->get_stylesheet();
    //             });

    //             add_filter('template', function() use ($theme) {
    //                 return $theme->get_template();
    //             });

    //             // Définir les chemins du thème
    //             add_filter('theme_root', function() use ($theme) {
    //                 return get_theme_root($theme->get_stylesheet());
    //             });

    //             // Définir les constantes nécessaires
    //             if (!defined('STYLESHEET')) {
    //                 define('STYLESHEET', $theme->get_stylesheet());
    //             }
    //             if (!defined('TEMPLATEPATH')) {
    //                 define('TEMPLATEPATH', $theme->get_theme_root() . '/' . $theme->get_template());
    //             }
    //             if (!defined('STYLESHEETPATH')) {
    //                 define('STYLESHEETPATH', $theme->get_theme_root() . '/' . $theme->get_stylesheet());
    //             }

    //             // Appliquer le style si sélectionné
    //             $style_id = isset($_COOKIE['ts_style']) ? sanitize_text_field($_COOKIE['ts_style']) : '';
    //             $font_id = isset($_COOKIE['ts_font']) ? sanitize_text_field($_COOKIE['ts_font']) : '';

    //             if (!empty($style_id) || !empty($font_id)) {
    //                 add_action('wp_head', function() use ($theme_slug, $style_id, $font_id) {
    //                     // Appliquer le style
    //                     if (!empty($style_id)) {
    //                         $this->apply_style($theme_slug, $style_id);
    //                     }

    //                     // Appliquer la police
    //                     if (!empty($font_id)) {
    //                         $this->apply_font($theme_slug, $font_id);
    //                     }
    //                 }, 999); // Priorité élevée pour s'assurer que ça s'applique après les styles du thème
    //             }
    //         }
    //     }
    // }

    /**
     * Vérifier si un thème supporte les styles globaux
     */
    private function theme_has_styles($theme_slug) {
        // Pour les thèmes FSE
        if (function_exists('wp_is_block_theme')) {
            $theme = wp_get_theme($theme_slug);
            if (wp_is_block_theme($theme)) {
                return true;
            }
        }

        // Vérifier theme.json
        $theme = wp_get_theme($theme_slug);
        $theme_json_file = $theme->get_stylesheet_directory() . '/theme.json';

        if (file_exists($theme_json_file)) {
            $theme_json = json_decode(file_get_contents($theme_json_file), true);
            if (isset($theme_json['styles']) || isset($theme_json['variations'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Récupérer les styles d'un thème
     */
    private function get_theme_styles($theme_slug) {
        $styles = array();
        $theme = wp_get_theme($theme_slug);

        // Pour les thèmes FSE
        if (function_exists('wp_get_global_styles_variations')) {
            $current_theme = wp_get_theme();

            // Temporairement changer de thème pour récupérer les variations
            if ($theme->get_stylesheet() !== $current_theme->get_stylesheet() && current_user_can('switch_themes')) {
                // Sauvegarder le thème actuel
                $original_theme = $current_theme->get_stylesheet();

                // Changer temporairement
                switch_theme($theme_slug);

                // Récupérer les variations
                $variations = wp_get_global_styles_variations();

                // Restaurer le thème original
                switch_theme($original_theme);

                if (!empty($variations)) {
                    foreach ($variations as $index => $variation) {
                        $styles[$index] = array(
                            'id' => 'variation-' . $index,
                            'label' => isset($variation['title']) ? $variation['title'] : sprintf(__('Style %d', 'theme-selector'), $index + 1),
                            'data' => json_encode($variation)
                        );
                    }
                }
            } else {
                // Si c'est déjà le thème actif, récupérer directement
                $variations = wp_get_global_styles_variations();
                if (!empty($variations)) {
                    foreach ($variations as $index => $variation) {
                        $styles[$index] = array(
                            'id' => 'variation-' . $index,
                            'label' => isset($variation['title']) ? $variation['title'] : sprintf(__('Style %d', 'theme-selector'), $index + 1),
                            'data' => json_encode($variation)
                        );
                    }
                }
            }
        }

        // Alternative: parcourir le fichier theme.json
        if (empty($styles)) {
            $theme_json_file = $theme->get_stylesheet_directory() . '/theme.json';
            if (file_exists($theme_json_file)) {
                $theme_json = json_decode(file_get_contents($theme_json_file), true);
                if (isset($theme_json['styles'])) {
                    $styles[0] = array(
                        'id' => 'theme-json-style',
                        'label' => __('Style principal', 'theme-selector'),
                        'data' => json_encode($theme_json['styles'])
                    );
                }
                if (isset($theme_json['variations'])) {
                    foreach ($theme_json['variations'] as $index => $variation) {
                        $styles['json-' . $index] = array(
                            'id' => 'theme-json-variation-' . $index,
                            'label' => isset($variation['title']) ? $variation['title'] : sprintf(__('Variation %d', 'theme-selector'), $index + 1),
                            'data' => json_encode($variation)
                        );
                    }
                }
            }
        }

        return $styles;
    }

    /**
     * Récupérer les polices d'un thème
     */
    private function get_theme_fonts($theme_slug) {
        $cache_key = 'theme_fonts_' . $theme_slug;
        $cached_fonts = wp_cache_get($cache_key);
        
        if (false !== $cached_fonts) {
            return $cached_fonts;
        }
    
        $fonts = [];
        $theme_json_file = wp_get_theme($theme_slug)->get_stylesheet_directory() . '/theme.json';
        
        if (file_exists($theme_json_file)) {
            // Lecture partielle du fichier pour économiser la mémoire
            $theme_json_content = file_get_contents($theme_json_file);
            $theme_json = json_decode($theme_json_content, true);
            
            if (isset($theme_json['settings']['typography']['fontFamilies'])) {
                $font_families = $theme_json['settings']['typography']['fontFamilies'];
                foreach ($font_families as $font) {
                    if (isset($font['name']) && isset($font['slug'])) {
                        $fonts[$font['slug']] = [
                            'id' => $font['slug'],
                            'label' => $font['name'],
                            'family' => $font['fontFamily'] ?? '',
                        ];
                    }
                }
            }
        }
        
        wp_cache_set($cache_key, $fonts, '', 3600);
        
        return $fonts;
    }
    // private function get_theme_fonts($theme_slug) {
    //     $fonts = array();
    //     $theme = wp_get_theme($theme_slug);

    //     // Vérifier theme.json pour les polices
    //     $theme_json_file = $theme->get_stylesheet_directory() . '/theme.json';
    //     if (file_exists($theme_json_file)) {
    //         $theme_json = json_decode(file_get_contents($theme_json_file), true);
    //         if (isset($theme_json['settings']['typography']['fontFamilies'])) {
    //             $font_families = $theme_json['settings']['typography']['fontFamilies'];
    //             foreach ($font_families as $index => $font) {
    //                 if (isset($font['name']) && isset($font['slug'])) {
    //                     $fonts[$font['slug']] = array(
    //                         'id' => $font['slug'],
    //                         'label' => $font['name'],
    //                         'family' => isset($font['fontFamily']) ? $font['fontFamily'] : '',
    //                     );
    //                 }
    //             }
    //         }
    //     }

    //     return $fonts;
    // }

    /**
     * Appliquer un style au thème
     */
    private function apply_style($theme_slug, $style_id) {
        // Pour les thèmes FSE actifs
        if (function_exists('wp_get_global_styles_variations') && get_stylesheet() === $theme_slug) {
            $variations = wp_get_global_styles_variations();
            if (strpos($style_id, 'variation-') === 0) {
                $variation_index = (int) str_replace('variation-', '', $style_id);
                if (isset($variations[$variation_index])) {
                    if (function_exists('wp_update_global_styles_variations')) {
                        wp_update_global_styles_variations($variations[$variation_index]);
                    } else {
                        echo '<style>' . $this->generate_css_from_variation($variations[$variation_index]) . '</style>';
                    }
                }
            }
        } else {
            // Pour les autres cas, essayer de générer du CSS à partir des données stockées
            // Vous pourriez stocker les données de variation dans les options et les récupérer ici
            $theme_json_file = get_theme_file_path($theme_slug . '/theme.json');
            if (file_exists($theme_json_file)) {
                $theme_json = json_decode(file_get_contents($theme_json_file), true);
                if (isset($theme_json['styles'])) {
                    echo '<style>' . $this->generate_css_from_variation($theme_json['styles']) . '</style>';
                }
            }
        }
    }

    /**
     * Appliquer une police au thème
     */
    private function apply_font($theme_slug, $font_id) {
        $fonts = $this->get_theme_fonts($theme_slug);
        if (isset($fonts[$font_id])) {
            echo '<style>
                :root {
                    --font-family-base: ' . esc_attr($fonts[$font_id]['family']) . ';
                }
                body, h1, h2, h3, h4, h5, h6, p, span, div {
                    font-family: var(--font-family-base) !important;
                }
            </style>';
        }
    }

    /**
     * Générer du CSS à partir d'une variation de style
     */
    private function generate_css_from_variation($variation) {
        $css = '';

        // Convertir en tableau si c'est une chaîne JSON
        if (is_string($variation)) {
            $variation = json_decode($variation, true);
        }

        // Générer le CSS pour la couleur de fond
        if (isset($variation['color']['background'])) {
            $css .= 'body { background-color: ' . esc_attr($variation['color']['background']) . ' !important; }';
        }

        // Générer le CSS pour la couleur du texte
        if (isset($variation['color']['text'])) {
            $css .= 'body { color: ' . esc_attr($variation['color']['text']) . ' !important; }';
        }

        // Générer le CSS pour les liens
        if (isset($variation['elements']['link']['color']['text'])) {
            $css .= 'a { color: ' . esc_attr($variation['elements']['link']['color']['text']) . ' !important; }';
        }

        // Générer le CSS pour les titres
        if (isset($variation['elements']['heading']['color']['text'])) {
            $css .= 'h1, h2, h3, h4, h5, h6 { color: ' . esc_attr($variation['elements']['heading']['color']['text']) . ' !important; }';
        }

        return $css;
    }
}

// Initialiser le plugin
$theme_selector = new Theme_Selector();