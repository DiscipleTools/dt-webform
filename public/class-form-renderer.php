<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Form Renderer class for public form display
 */
class DT_Webform_Form_Renderer {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_scripts' ] );
        add_shortcode( 'dt_webform', [ $this, 'render_form_shortcode' ] );
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_action( 'template_redirect', [ $this, 'handle_webform_request' ] );
    }

    /**
     * Enqueue public scripts and styles
     */
    public function enqueue_public_scripts() {
        // Don't enqueue for standalone webform pages (they handle their own assets)
        $is_webform_page = get_query_var( 'dt_webform_id' );
        if ( $is_webform_page ) {
            return;
        }
        
        // Only enqueue on pages that contain shortcodes
        $has_shortcode = is_singular() && has_shortcode( get_post()->post_content, 'dt_webform' );
        
        if ( $has_shortcode ) {
            wp_enqueue_script(
                'dt-webform-public',
                plugin_dir_url( __FILE__ ) . 'assets/js/form-public.js',
                [ 'jquery' ],
                '1.0.0',
                true
            );

            wp_enqueue_style(
                'dt-webform-public',
                plugin_dir_url( __FILE__ ) . 'assets/css/form-public.css',
                [],
                '1.0.0'
            );

            // Localize script
            wp_localize_script( 'dt-webform-public', 'dtWebformPublic', [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'rest_url' => rest_url( 'dt-public/v1/webform/' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'strings' => [
                    'required' => __( 'This field is required.', 'dt-webform' ),
                    'submitting' => __( 'Submitting...', 'dt-webform' ),
                    'submit' => __( 'Submit', 'dt-webform' ),
                    'error' => __( 'An error occurred. Please try again.', 'dt-webform' ),
                    'success' => __( 'Thank you for your submission!', 'dt-webform' ),
                ]
            ] );
        }
    }

    /**
     * Render form via shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Form HTML
     */
    public function render_form_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'id' => 0,
            'title' => 'true',
            'description' => 'true',
        ], $atts, 'dt_webform' );

        $form_id = intval( $atts['id'] );
        if ( ! $form_id ) {
            return '<p class="dt-webform-error">' . __( 'Error: No form ID specified.', 'dt-webform' ) . '</p>';
        }

        return $this->render_form( $form_id, $atts );
    }

    /**
     * Add rewrite rules for webform URLs
     */
    public function add_rewrite_rules() {
        add_rewrite_rule( '^webform/([0-9]+)/?$', 'index.php?dt_webform_id=$matches[1]', 'top' );
        add_rewrite_tag( '%dt_webform_id%', '([0-9]+)' );
    }

    /**
     * Handle webform requests
     */
    public function handle_webform_request() {
        $form_id = get_query_var( 'dt_webform_id' );
        
        if ( $form_id ) {
            $form_id = intval( $form_id );
            if ( $form_id > 0 ) {
                $this->render_standalone_form( $form_id );
                exit;
            }
        }
    }

    /**
     * Render standalone form page
     *
     * @param int $form_id Form ID
     */
    private function render_standalone_form( $form_id ) {
        $form = DT_Webform_Core::get_form( $form_id );
        
        if ( ! $form || ! $form['is_active'] ) {
            wp_die( __( 'Form not found or is not active.', 'dt-webform' ), __( 'Form Error', 'dt-webform' ), [ 'response' => 404 ] );
        }

        // Get form HTML
        $form_html = $this->render_form( $form_id );

        // Get plugin URL for assets
        $plugin_url = plugin_dir_url( __FILE__ );

        // Render minimal page with only webform assets
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html( $form['settings']['title'] ?: $form['title'] ); ?></title>
            
            <!-- Only load webform CSS -->
            <link rel="stylesheet" href="<?php echo esc_url( $plugin_url . 'assets/css/form-public.css' ); ?>?v=1.0.0" type="text/css" media="all">
            
            <!-- Only load necessary JS -->
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="<?php echo esc_url( $plugin_url . 'assets/js/form-public.js' ); ?>?v=1.0.0"></script>
            
            <!-- Localize script data -->
            <script>
                window.dtWebformPublic = {
                    ajaxurl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
                    rest_url: '<?php echo esc_url( rest_url( 'dt-public/v1/webform/' ) ); ?>',
                    nonce: '<?php echo wp_create_nonce( 'wp_rest' ); ?>',
                    strings: {
                        required: '<?php echo esc_js( __( 'This field is required.', 'dt-webform' ) ); ?>',
                        submitting: '<?php echo esc_js( __( 'Submitting...', 'dt-webform' ) ); ?>',
                        submit: '<?php echo esc_js( __( 'Submit', 'dt-webform' ) ); ?>',
                        error: '<?php echo esc_js( __( 'An error occurred. Please try again.', 'dt-webform' ) ); ?>',
                        success: '<?php echo esc_js( __( 'Thank you for your submission!', 'dt-webform' ) ); ?>'
                    }
                };
            </script>
        </head>
        <body class="dt-webform-standalone">
            <div class="dt-webform-container">
                <?php echo $form_html; ?>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Render form HTML
     *
     * @param int $form_id Form ID
     * @param array $options Rendering options
     * @return string Form HTML
     */
    public function render_form( $form_id, $options = [] ) {
        $form = DT_Webform_Core::get_form( $form_id );
        
        if ( ! $form ) {
            return '<p class="dt-webform-error">' . __( 'Form not found.', 'dt-webform' ) . '</p>';
        }

        if ( ! $form['is_active'] ) {
            return '<p class="dt-webform-error">' . __( 'This form is currently inactive.', 'dt-webform' ) . '</p>';
        }

        $show_title = ( $options['title'] ?? 'true' ) === 'true';
        $show_description = ( $options['description'] ?? 'true' ) === 'true';

        ob_start();
        ?>
        <div class="dt-webform" data-form-id="<?php echo esc_attr( $form_id ); ?>">
            <?php if ( $show_title && ! empty( $form['settings']['title'] ) ) : ?>
                <h2 class="dt-webform-title"><?php echo esc_html( $form['settings']['title'] ); ?></h2>
            <?php endif; ?>

            <?php if ( $show_description && ! empty( $form['settings']['description'] ) ) : ?>
                <div class="dt-webform-description">
                    <?php echo wp_kses_post( wpautop( $form['settings']['description'] ) ); ?>
                </div>
            <?php endif; ?>

            <form class="dt-webform-form" method="post" novalidate>
                <?php wp_nonce_field( 'dt_webform_submit_' . $form_id, 'dt_webform_nonce' ); ?>
                <input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">

                <div class="dt-webform-fields">
                    <?php foreach ( $form['fields'] as $field ) : ?>
                        <?php echo $this->render_field( $field ); ?>
                    <?php endforeach; ?>
                </div>

                <div class="dt-webform-submit-container">
                    <button type="submit" class="dt-webform-submit-btn">
                        <?php esc_html_e( 'Submit', 'dt-webform' ); ?>
                    </button>
                </div>

                <div class="dt-webform-messages" style="display: none;">
                    <div class="dt-webform-message dt-webform-success" style="display: none;"></div>
                    <div class="dt-webform-message dt-webform-error" style="display: none;"></div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render individual form field
     *
     * @param array $field Field configuration
     * @return string Field HTML
     */
    private function render_field( $field ) {
        $field_id = 'dt_webform_' . sanitize_key( $field['key'] );
        $field_name = $field['key'];
        $required = ! empty( $field['required'] );
        $required_attr = $required ? 'required' : '';
        $required_mark = $required ? ' <span class="dt-webform-required">*</span>' : '';

        ob_start();
        ?>
        <div class="dt-webform-field dt-webform-field-<?php echo esc_attr( $field['type'] ); ?>" data-field-key="<?php echo esc_attr( $field['key'] ); ?>">
            <label for="<?php echo esc_attr( $field_id ); ?>" class="dt-webform-field-label">
                <?php echo esc_html( $field['label'] ); ?><?php echo $required_mark; ?>
            </label>

            <?php
            switch ( $field['type'] ) {
                case 'text':
                case 'email':
                case 'tel':
                case 'url':
                case 'number':
                case 'date':
                    echo $this->render_input_field( $field, $field_id, $required_attr );
                    break;

                case 'textarea':
                    echo $this->render_textarea_field( $field, $field_id, $required_attr );
                    break;

                case 'select':
                    echo $this->render_select_field( $field, $field_id, $required_attr );
                    break;

                case 'radio':
                    echo $this->render_radio_field( $field, $required_attr );
                    break;

                case 'checkbox':
                    echo $this->render_checkbox_field( $field, $field_id, $required_attr );
                    break;

                case 'multi_select':
                    echo $this->render_multi_select_field( $field, $required_attr );
                    break;

                default:
                    echo $this->render_input_field( $field, $field_id, $required_attr );
                    break;
            }
            ?>

            <?php if ( ! empty( $field['description'] ) ) : ?>
                <div class="dt-webform-field-description">
                    <?php echo esc_html( $field['description'] ); ?>
                </div>
            <?php endif; ?>

            <div class="dt-webform-field-error" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render input field (text, email, etc.)
     */
    private function render_input_field( $field, $field_id, $required_attr ) {
        $placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';
        
        return sprintf(
            '<input type="%s" id="%s" name="%s" class="dt-webform-input" placeholder="%s" %s>',
            esc_attr( $field['type'] ),
            esc_attr( $field_id ),
            esc_attr( $field['key'] ),
            esc_attr( $placeholder ),
            $required_attr
        );
    }

    /**
     * Render textarea field
     */
    private function render_textarea_field( $field, $field_id, $required_attr ) {
        $placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';
        
        return sprintf(
            '<textarea id="%s" name="%s" class="dt-webform-textarea" placeholder="%s" rows="4" %s></textarea>',
            esc_attr( $field_id ),
            esc_attr( $field['key'] ),
            esc_attr( $placeholder ),
            $required_attr
        );
    }

    /**
     * Render select field
     */
    private function render_select_field( $field, $field_id, $required_attr ) {
        $html = sprintf(
            '<select id="%s" name="%s" class="dt-webform-select" %s>',
            esc_attr( $field_id ),
            esc_attr( $field['key'] ),
            $required_attr
        );

        $html .= '<option value="">' . __( 'Select an option...', 'dt-webform' ) . '</option>';

        if ( ! empty( $field['options'] ) ) {
            foreach ( $field['options'] as $option ) {
                $value = is_array( $option ) ? $option['value'] : $option;
                $label = is_array( $option ) ? $option['label'] : $option;
                $html .= sprintf(
                    '<option value="%s">%s</option>',
                    esc_attr( $value ),
                    esc_html( $label )
                );
            }
        }

        $html .= '</select>';
        return $html;
    }

    /**
     * Render radio field
     */
    private function render_radio_field( $field, $required_attr ) {
        $html = '<div class="dt-webform-radio-group">';

        if ( ! empty( $field['options'] ) ) {
            foreach ( $field['options'] as $index => $option ) {
                $value = is_array( $option ) ? $option['value'] : $option;
                $label = is_array( $option ) ? $option['label'] : $option;
                $option_id = 'dt_webform_' . sanitize_key( $field['key'] ) . '_' . $index;

                $html .= sprintf(
                    '<label class="dt-webform-radio-label">
                        <input type="radio" id="%s" name="%s" value="%s" class="dt-webform-radio" %s>
                        <span class="dt-webform-radio-text">%s</span>
                    </label>',
                    esc_attr( $option_id ),
                    esc_attr( $field['key'] ),
                    esc_attr( $value ),
                    $required_attr,
                    esc_html( $label )
                );
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render checkbox field
     */
    private function render_checkbox_field( $field, $field_id, $required_attr ) {
        $label_text = $field['label'] ?? __( 'Check this box', 'dt-webform' );
        
        return sprintf(
            '<label class="dt-webform-checkbox-label">
                <input type="checkbox" id="%s" name="%s" value="1" class="dt-webform-checkbox" %s>
                <span class="dt-webform-checkbox-text">%s</span>
            </label>',
            esc_attr( $field_id ),
            esc_attr( $field['key'] ),
            $required_attr,
            esc_html( $label_text )
        );
    }

    /**
     * Render multi-select field
     */
    private function render_multi_select_field( $field, $required_attr ) {
        $html = '<div class="dt-webform-multi-select">';

        if ( ! empty( $field['options'] ) ) {
            foreach ( $field['options'] as $index => $option ) {
                $value = is_array( $option ) ? $option['value'] : $option;
                $label = is_array( $option ) ? $option['label'] : $option;
                $option_id = 'dt_webform_' . sanitize_key( $field['key'] ) . '_' . $index;

                $html .= sprintf(
                    '<label class="dt-webform-checkbox-label">
                        <input type="checkbox" id="%s" name="%s[]" value="%s" class="dt-webform-checkbox" %s>
                        <span class="dt-webform-checkbox-text">%s</span>
                    </label>',
                    esc_attr( $option_id ),
                    esc_attr( $field['key'] ),
                    esc_attr( $value ),
                    $required_attr,
                    esc_html( $label )
                );
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Get form URL for embedding/sharing
     *
     * @param int $form_id Form ID
     * @return string Form URL
     */
    public function get_form_url( $form_id ) {
        return home_url( "/webform/{$form_id}" );
    }

    /**
     * Get form embed code
     *
     * @param int $form_id Form ID
     * @param array $options Embed options
     * @return string Embed code
     */
    public function get_form_embed_code( $form_id, $options = [] ) {
        $form_url = $this->get_form_url( $form_id );
        $width = $options['width'] ?? '100%';
        $height = $options['height'] ?? '600px';

        return sprintf(
            '<iframe src="%s" width="%s" height="%s" frameborder="0" scrolling="auto"></iframe>',
            esc_url( $form_url ),
            esc_attr( $width ),
            esc_attr( $height )
        );
    }
}

// Initialize the form renderer
DT_Webform_Form_Renderer::instance(); 