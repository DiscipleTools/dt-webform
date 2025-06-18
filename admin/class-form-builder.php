<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Form Builder class for creating and editing webforms
 */
class DT_Webform_Form_Builder {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        $this->handle_ajax_requests();
    }

    /**
     * Enqueue admin scripts and styles for form builder
     */
    public function enqueue_scripts( $hook_suffix ) {
        if ( strpos( $hook_suffix, 'disciple_tools_webform' ) === false ) {
            return;
        }

        wp_enqueue_script( 
            'dt-webform-form-builder', 
            plugin_dir_url( __FILE__ ) . 'assets/js/form-builder.js',
            [ 'jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'wp-util' ],
            '1.0.0',
            true
        );

        wp_enqueue_style( 
            'dt-webform-admin', 
            plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
            [],
            '1.0.0'
        );

        // Get initial DT fields data if we're editing an existing form
        $current_post_type = '';
        $initial_dt_fields = [];
        
        if ( isset( $_GET['form_id'] ) && ! empty( $_GET['form_id'] ) ) {
            $form_id = intval( $_GET['form_id'] );
            $form_data = DT_Webform_Core::get_form( $form_id );
            if ( $form_data && ! empty( $form_data['post_type'] ) ) {
                $current_post_type = $form_data['post_type'];
                $initial_dt_fields = $this->get_dt_fields( $current_post_type );
            }
        }

        // Localize script with data
        wp_localize_script( 'dt-webform-form-builder', 'dtWebformAdmin', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'restUrl' => rest_url(),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'nonce' => wp_create_nonce( 'dt_webform_builder' ),
            'siteUrl' => home_url(),
            'initialPostType' => $current_post_type,
            'initialDTFields' => $initial_dt_fields,
            'strings' => [
                'confirmDelete' => __( 'Are you sure you want to delete this field?', 'dt-webform' ),
                'fieldRequired' => __( 'This field is required.', 'dt-webform' ),
                'saveSuccess' => __( 'Form saved successfully!', 'dt-webform' ),
                'saveError' => __( 'Failed to save form.', 'dt-webform' ),
                'loadingFields' => __( 'Loading fields...', 'dt-webform' ),
                'noFieldsFound' => __( 'No fields found for this post type.', 'dt-webform' ),
            ],
        ] );
    }

    /**
     * Get available DT fields for a specific post type
     *
     * @param string $post_type DT post type
     * @return array Available fields
     */
    public function get_dt_fields( $post_type ) {
        error_log( 'DT Webform: get_dt_fields called with post_type: ' . $post_type );
        
        if ( ! class_exists( 'DT_Posts' ) ) {
            error_log( 'DT Webform: DT_Posts class not available' );
            return [];
        }

        if ( ! method_exists( 'DT_Posts', 'get_post_field_settings' ) ) {
            error_log( 'DT Webform: get_post_field_settings method not available' );
            return [];
        }

        try {
            $field_settings = DT_Posts::get_post_field_settings( $post_type );
            error_log( 'DT Webform: Field settings retrieved: ' . print_r( array_keys( $field_settings ), true ) );
            
            $available_fields = [];

            foreach ( $field_settings as $field_key => $field_config ) {
                // Skip fields that shouldn't be in forms
                if ( $this->should_skip_field( $field_key, $field_config ) ) {
                    continue;
                }

                $available_fields[ $field_key ] = [
                    'key' => $field_key,
                    'label' => $field_config['name'] ?? ucwords( str_replace( '_', ' ', $field_key ) ),
                    'type' => $this->map_dt_field_type( $field_config['type'] ?? 'text' ),
                    'description' => $field_config['description'] ?? '',
                    'required' => $field_config['required'] ?? false,
                    'options' => $this->get_field_options( $field_config ),
                    'validation' => $this->get_field_validation( $field_config ),
                    'is_dt_field' => true,
                ];
            }

            error_log( 'DT Webform: Available fields count: ' . count( $available_fields ) );
            return $available_fields;
        } catch ( Exception $e ) {
            error_log( 'DT Webform: Error getting DT fields - ' . $e->getMessage() );
            return [];
        }
    }

    /**
     * Check if a field should be skipped in forms
     */
    private function should_skip_field( $field_key, $field_config ) {
        // Skip system fields
        $skip_fields = [
            'ID',
            'post_title',
            'post_content', 
            'post_date',
            'post_author',
            'permalink',
            'last_modified',
            'created_date',
            'requires_update',
            'duplicate_data',
            'tags',
            'follow',
            'unfollow',
            'tasks',
            'activity',
        ];

        if ( in_array( $field_key, $skip_fields ) ) {
            return true;
        }

        // Skip fields marked as private or hidden
        if ( isset( $field_config['private'] ) && $field_config['private'] ) {
            return true;
        }

        if ( isset( $field_config['hidden'] ) && $field_config['hidden'] ) {
            return true;
        }

        // Skip very complex field types for Phase 2
        $complex_types = [
            'post_user_meta',
            'connection',
            'tags',
            'user_select',
        ];

        if ( isset( $field_config['type'] ) && in_array( $field_config['type'], $complex_types ) ) {
            return true;
        }

        return false;
    }

    /**
     * Map DT field types to webform field types
     */
    private function map_dt_field_type( $dt_type ) {
        $type_mapping = [
            'text' => 'text',
            'textarea' => 'textarea',
            'number' => 'number',
            'date' => 'date',
            'datetime' => 'datetime-local',
            'boolean' => 'checkbox',
            'key_select' => 'select',
            'multi_select' => 'multi_select',
            'communication_channel' => 'communication_channel',
            'location' => 'location',
            'email' => 'email',
            'phone' => 'tel',
            'url' => 'url',
        ];

        return $type_mapping[ $dt_type ] ?? 'text';
    }

    /**
     * Get field options for select/multi-select fields
     */
    private function get_field_options( $field_config ) {
        if ( ! isset( $field_config['default'] ) || ! is_array( $field_config['default'] ) ) {
            return [];
        }

        $options = [];
        foreach ( $field_config['default'] as $key => $value ) {
            if ( is_array( $value ) && isset( $value['label'] ) ) {
                $options[] = [
                    'value' => $key,
                    'label' => $value['label'],
                ];
            } else {
                $options[] = [
                    'value' => $key,
                    'label' => is_string( $value ) ? $value : $key,
                ];
            }
        }

        return $options;
    }

    /**
     * Get field validation rules
     */
    private function get_field_validation( $field_config ) {
        $validation = [];

        if ( isset( $field_config['required'] ) && $field_config['required'] ) {
            $validation['required'] = true;
        }

        if ( isset( $field_config['type'] ) ) {
            switch ( $field_config['type'] ) {
                case 'email':
                    $validation['email'] = true;
                    break;
                case 'url':
                    $validation['url'] = true;
                    break;
                case 'number':
                    $validation['numeric'] = true;
                    if ( isset( $field_config['min_value'] ) ) {
                        $validation['min'] = $field_config['min_value'];
                    }
                    if ( isset( $field_config['max_value'] ) ) {
                        $validation['max'] = $field_config['max_value'];
                    }
                    break;
            }
        }

        return $validation;
    }

    /**
     * Get custom field types available for forms
     */
    public function get_custom_field_types() {
        return [
            'custom_text' => [
                'label' => __( 'Text Input', 'dt-webform' ),
                'type' => 'text',
                'icon' => 'dashicons-edit',
                'description' => __( 'Single line text input', 'dt-webform' ),
            ],
            'custom_textarea' => [
                'label' => __( 'Textarea', 'dt-webform' ),
                'type' => 'textarea',
                'icon' => 'dashicons-text-page',
                'description' => __( 'Multi-line text input', 'dt-webform' ),
            ],
            'custom_select' => [
                'label' => __( 'Dropdown', 'dt-webform' ),
                'type' => 'select',
                'icon' => 'dashicons-arrow-down-alt2',
                'description' => __( 'Dropdown selection field', 'dt-webform' ),
            ],
            'custom_radio' => [
                'label' => __( 'Radio Buttons', 'dt-webform' ),
                'type' => 'radio',
                'icon' => 'dashicons-marker',
                'description' => __( 'Single choice from multiple options', 'dt-webform' ),
            ],
            'custom_checkbox' => [
                'label' => __( 'Checkbox', 'dt-webform' ),
                'type' => 'checkbox',
                'icon' => 'dashicons-yes-alt',
                'description' => __( 'Single checkbox for yes/no', 'dt-webform' ),
            ],
            'custom_email' => [
                'label' => __( 'Email', 'dt-webform' ),
                'type' => 'email',
                'icon' => 'dashicons-email-alt',
                'description' => __( 'Email address input with validation', 'dt-webform' ),
            ],
            'custom_phone' => [
                'label' => __( 'Phone', 'dt-webform' ),
                'type' => 'tel',
                'icon' => 'dashicons-phone',
                'description' => __( 'Phone number input', 'dt-webform' ),
            ],
            'custom_number' => [
                'label' => __( 'Number', 'dt-webform' ),
                'type' => 'number',
                'icon' => 'dashicons-calculator',
                'description' => __( 'Numeric input field', 'dt-webform' ),
            ],
            'custom_date' => [
                'label' => __( 'Date', 'dt-webform' ),
                'type' => 'date',
                'icon' => 'dashicons-calendar-alt',
                'description' => __( 'Date picker input', 'dt-webform' ),
            ],
        ];
    }

    /**
     * Render the form builder interface
     *
     * @param int $form_id Form ID (0 for new form)
     * @param array $form_data Existing form data
     */
    public function render_form_builder( $form_id = 0, $form_data = [] ) {
        $post_types = DT_Webform_Core::get_available_post_types();
        $current_post_type = $form_data['post_type'] ?? '';
        $dt_fields = $current_post_type ? $this->get_dt_fields( $current_post_type ) : [];
        $custom_field_types = $this->get_custom_field_types();
        $form_fields = $form_data['fields'] ?? [];
        ?>
        <div id="dt-webform-builder" class="dt-webform-builder">
            <div class="dt-webform-builder-header">
                <h2><?php echo $form_id ? __( 'Edit Form', 'dt-webform' ) : __( 'Create New Form', 'dt-webform' ); ?></h2>
            </div>

            <div id="dt-webform-builder-form" class="dt-webform-form-container">
                <!-- Hidden fields for form data -->
                <?php if ( $form_id ) : ?>
                    <input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
                <?php endif; ?>

                <div class="dt-webform-builder-content">
                    <!-- Form Settings Panel -->
                    <div class="dt-webform-settings-panel">
                        <h3><?php esc_html_e( 'Form Settings', 'dt-webform' ); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="form_title"><?php esc_html_e( 'Form Title', 'dt-webform' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="form_title" name="title" class="regular-text" 
                                           value="<?php echo esc_attr( $form_data['title'] ?? '' ); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="form_post_type"><?php esc_html_e( 'Target Post Type', 'dt-webform' ); ?></label>
                                </th>
                                <td>
                                    <select id="form_post_type" name="post_type" required>
                                        <option value=""><?php esc_html_e( 'Select a post type...', 'dt-webform' ); ?></option>
                                        <?php foreach ( $post_types as $type => $config ) : ?>
                                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $current_post_type, $type ); ?>>
                                                <?php echo esc_html( $config['label'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="form_description"><?php esc_html_e( 'Form Description', 'dt-webform' ); ?></label>
                                </th>
                                <td>
                                    <textarea id="form_description" name="settings[description]" class="large-text" rows="3"><?php echo esc_textarea( $form_data['settings']['description'] ?? '' ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'Optional description shown at the top of the form.', 'dt-webform' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="form_success_message"><?php esc_html_e( 'Success Message', 'dt-webform' ); ?></label>
                                </th>
                                <td>
                                    <textarea id="form_success_message" name="settings[success_message]" class="large-text" rows="2"><?php echo esc_textarea( $form_data['settings']['success_message'] ?? __( 'Thank you for your submission!', 'dt-webform' ) ); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="form_is_active"><?php esc_html_e( 'Status', 'dt-webform' ); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="form_is_active" name="is_active" value="1" <?php checked( $form_data['is_active'] ?? false ); ?>>
                                    <label for="form_is_active"><?php esc_html_e( 'Form is active and accepting submissions', 'dt-webform' ); ?></label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Form Builder Interface -->
                    <div class="dt-webform-builder-interface">
                        <div class="dt-webform-builder-sidebar">
                            <!-- Available Fields -->
                            <div class="dt-webform-field-groups">
                                <h4><?php esc_html_e( 'Available Fields', 'dt-webform' ); ?></h4>
                                
                                <!-- DT Fields -->
                                <div class="dt-webform-field-group" id="dt-fields-group">
                                    <h5><?php esc_html_e( 'DT Fields', 'dt-webform' ); ?></h5>
                                    <div class="dt-webform-available-fields" id="dt-available-fields">
                                        <?php if ( empty( $dt_fields ) ) : ?>
                                            <p class="description"><?php esc_html_e( 'Select a post type to see available DT fields.', 'dt-webform' ); ?></p>
                                        <?php else : ?>
                                            <?php foreach ( $dt_fields as $field ) : ?>
                                                <div class="dt-webform-field-item" data-field-type="dt" data-field-key="<?php echo esc_attr( $field['key'] ); ?>">
                                                    <span class="field-icon dashicons dashicons-admin-generic"></span>
                                                    <span class="field-label"><?php echo esc_html( $field['label'] ); ?></span>
                                                    <span class="field-type"><?php echo esc_html( $field['type'] ); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Custom Fields -->
                                <div class="dt-webform-field-group">
                                    <h5><?php esc_html_e( 'Custom Fields', 'dt-webform' ); ?></h5>
                                    <div class="dt-webform-available-fields">
                                        <?php foreach ( $custom_field_types as $field_key => $field_config ) : ?>
                                            <div class="dt-webform-field-item" data-field-type="custom" data-field-key="<?php echo esc_attr( $field_key ); ?>">
                                                <span class="field-icon dashicons <?php echo esc_attr( $field_config['icon'] ); ?>"></span>
                                                <span class="field-label"><?php echo esc_html( $field_config['label'] ); ?></span>
                                                <span class="field-description"><?php echo esc_html( $field_config['description'] ); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Fields Container -->
                        <div class="dt-webform-form-container">
                            <h4><?php esc_html_e( 'Form Fields', 'dt-webform' ); ?></h4>
                            <div class="dt-webform-form-fields" id="dt-webform-form-fields">
                                <?php if ( ! empty( $form_fields ) ) : ?>
                                    <?php foreach ( $form_fields as $field ) : ?>
                                        <?php $this->render_form_field_editor( $field ); ?>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="dt-webform-empty-state">
                                        <p><?php esc_html_e( 'Drag fields from the sidebar to build your form.', 'dt-webform' ); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="dt-webform-builder-actions">
                        <div class="dt-webform-actions-left">
                            <button type="button" class="button" id="dt-webform-preview-btn">
                                <?php esc_html_e( 'Preview Form', 'dt-webform' ); ?>
                            </button>
                        </div>
                        <div class="dt-webform-actions-right">
                            <button type="button" class="button-primary" id="dt-webform-save-btn">
                                <?php echo $form_id ? esc_html__( 'Update Form', 'dt-webform' ) : esc_html__( 'Create Form', 'dt-webform' ); ?>
                            </button>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=disciple_tools_webform&tab=forms' ) ); ?>" class="button">
                                <?php esc_html_e( 'Cancel', 'dt-webform' ); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Embed Code Section (shown after form is saved) -->
                    <?php if ( $form_id ) : ?>
                        <div class="dt-webform-embed-section">
                            <h3><?php esc_html_e( 'Embed This Form', 'dt-webform' ); ?></h3>
                            <p class="description"><?php esc_html_e( 'Use the code below to embed this form on your website or share the direct link.', 'dt-webform' ); ?></p>
                            
                            <div class="dt-webform-embed-options">
                                <div class="dt-webform-embed-option">
                                    <h4><?php esc_html_e( 'Embed Code (iframe)', 'dt-webform' ); ?></h4>
                                    <p class="description"><?php esc_html_e( 'Copy and paste this code into your website HTML to embed the form.', 'dt-webform' ); ?></p>
                                    <div class="dt-webform-code-container">
                                        <textarea id="dt-webform-embed-code" class="dt-webform-code-textarea" readonly><?php echo esc_textarea( $this->get_form_embed_code( $form_id ) ); ?></textarea>
                                        <button type="button" class="button dt-webform-copy-btn" data-target="dt-webform-embed-code">
                                            <?php esc_html_e( 'Copy Code', 'dt-webform' ); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="dt-webform-embed-option">
                                    <h4><?php esc_html_e( 'Direct Link', 'dt-webform' ); ?></h4>
                                    <p class="description"><?php esc_html_e( 'Share this link directly or use it in your own iframe.', 'dt-webform' ); ?></p>
                                    <div class="dt-webform-code-container">
                                        <input type="text" id="dt-webform-direct-url" class="dt-webform-url-input" value="<?php echo esc_url( $this->get_form_url( $form_id ) ); ?>" readonly>
                                        <button type="button" class="button dt-webform-copy-btn" data-target="dt-webform-direct-url">
                                            <?php esc_html_e( 'Copy URL', 'dt-webform' ); ?>
                                        </button>
                                        <a href="<?php echo esc_url( $this->get_form_url( $form_id ) ); ?>" target="_blank" class="button">
                                            <?php esc_html_e( 'Preview', 'dt-webform' ); ?>
                                        </a>
                                    </div>
                                </div>

                                <div class="dt-webform-embed-option">
                                    <h4><?php esc_html_e( 'Customizable Embed Code', 'dt-webform' ); ?></h4>
                                    <p class="description"><?php esc_html_e( 'Customize the iframe dimensions and generate new embed code.', 'dt-webform' ); ?></p>
                                    <div class="dt-webform-embed-customizer">
                                        <div class="dt-webform-embed-controls">
                                            <label for="embed-width"><?php esc_html_e( 'Width:', 'dt-webform' ); ?></label>
                                            <input type="text" id="embed-width" value="100%" class="small-text">
                                            
                                            <label for="embed-height"><?php esc_html_e( 'Height:', 'dt-webform' ); ?></label>
                                            <input type="text" id="embed-height" value="600px" class="small-text">
                                            
                                            <button type="button" class="button" id="generate-custom-embed">
                                                <?php esc_html_e( 'Generate', 'dt-webform' ); ?>
                                            </button>
                                        </div>
                                        <div class="dt-webform-code-container">
                                            <textarea id="dt-webform-custom-embed" class="dt-webform-code-textarea" readonly placeholder="<?php esc_attr_e( 'Click Generate to create custom embed code', 'dt-webform' ); ?>"></textarea>
                                            <button type="button" class="button dt-webform-copy-btn" data-target="dt-webform-custom-embed" style="display: none;">
                                                <?php esc_html_e( 'Copy Code', 'dt-webform' ); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Field Editor Modal -->
            <div id="dt-webform-field-editor-modal" class="dt-webform-modal" style="display: none;">
                <div class="dt-webform-modal-content">
                    <div class="dt-webform-modal-header">
                        <h3><?php esc_html_e( 'Field Settings', 'dt-webform' ); ?></h3>
                        <button type="button" class="dt-webform-modal-close">&times;</button>
                    </div>
                    <div class="dt-webform-modal-body">
                        <!-- Field editor content will be populated by JavaScript -->
                    </div>
                    <div class="dt-webform-modal-footer">
                        <button type="button" class="button-primary" id="dt-webform-save-field">
                            <?php esc_html_e( 'Save Field', 'dt-webform' ); ?>
                        </button>
                        <button type="button" class="button" id="dt-webform-cancel-field">
                            <?php esc_html_e( 'Cancel', 'dt-webform' ); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Form Preview Modal -->
            <div id="dt-webform-preview-modal" class="dt-webform-modal" style="display: none;">
                <div class="dt-webform-modal-content dt-webform-preview-content">
                    <div class="dt-webform-modal-header">
                        <h3><?php esc_html_e( 'Form Preview', 'dt-webform' ); ?></h3>
                        <button type="button" class="dt-webform-modal-close">&times;</button>
                    </div>
                    <div class="dt-webform-modal-body">
                        <div id="dt-webform-preview-container">
                            <!-- Preview content will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/html" id="tmpl-dt-webform-field-editor">
            <!-- Field editor template will be defined in JavaScript -->
        </script>
        <?php
    }

    /**
     * Render a form field in the editor
     */
    private function render_form_field_editor( $field ) {
        $field_id = 'field_' . uniqid();
        ?>
        <div class="dt-webform-field-editor" data-field-id="<?php echo esc_attr( $field_id ); ?>">
            <div class="dt-webform-field-header">
                <div class="dt-webform-field-handle">
                    <span class="dashicons dashicons-menu"></span>
                </div>
                <div class="dt-webform-field-info">
                    <strong><?php echo esc_html( $field['label'] ?? $field['key'] ); ?></strong>
                    <span class="field-type">(<?php echo esc_html( $field['type'] ); ?>)</span>
                    <?php if ( ! empty( $field['required'] ) ) : ?>
                        <span class="field-required">*</span>
                    <?php endif; ?>
                </div>
                <div class="dt-webform-field-actions">
                    <button type="button" class="button button-small dt-webform-edit-field" data-field-id="<?php echo esc_attr( $field_id ); ?>">
                        <?php esc_html_e( 'Edit', 'dt-webform' ); ?>
                    </button>
                    <button type="button" class="button button-small dt-webform-delete-field" data-field-id="<?php echo esc_attr( $field_id ); ?>">
                        <?php esc_html_e( 'Delete', 'dt-webform' ); ?>
                    </button>
                </div>
            </div>
            <div class="dt-webform-field-preview">
                <?php $this->render_field_preview( $field ); ?>
            </div>
            
            <!-- Hidden field data -->
            <script type="application/json" class="dt-webform-field-data">
                <?php echo wp_json_encode( $field ); ?>
            </script>
        </div>
        <?php
    }

    /**
     * Render a preview of the field
     */
    private function render_field_preview( $field ) {
        $field_type = $field['type'] ?? 'text';
        $field_label = $field['label'] ?? $field['key'];
        $field_placeholder = $field['placeholder'] ?? '';
        $field_required = ! empty( $field['required'] );

        echo '<div class="dt-webform-field-preview-content">';
        echo '<label class="dt-webform-preview-label">';
        echo esc_html( $field_label );
        if ( $field_required ) {
            echo ' <span class="required">*</span>';
        }
        echo '</label>';

        switch ( $field_type ) {
            case 'textarea':
                echo '<textarea class="dt-webform-preview-field" placeholder="' . esc_attr( $field_placeholder ) . '" disabled></textarea>';
                break;
            case 'select':
                echo '<select class="dt-webform-preview-field" disabled>';
                echo '<option>' . esc_html__( 'Select an option...', 'dt-webform' ) . '</option>';
                if ( ! empty( $field['options'] ) ) {
                    foreach ( $field['options'] as $option ) {
                        if ( is_array( $option ) ) {
                            echo '<option value="' . esc_attr( $option['value'] ) . '">' . esc_html( $option['label'] ) . '</option>';
                        } else {
                            echo '<option>' . esc_html( $option ) . '</option>';
                        }
                    }
                }
                echo '</select>';
                break;
            case 'checkbox':
                echo '<label><input type="checkbox" disabled> ' . esc_html( $field_label ) . '</label>';
                break;
            case 'radio':
                if ( ! empty( $field['options'] ) ) {
                    foreach ( $field['options'] as $option ) {
                        $label = is_array( $option ) ? $option['label'] : $option;
                        echo '<label><input type="radio" name="preview_radio" disabled> ' . esc_html( $label ) . '</label><br>';
                    }
                }
                break;
            default:
                echo '<input type="' . esc_attr( $field_type ) . '" class="dt-webform-preview-field" placeholder="' . esc_attr( $field_placeholder ) . '" disabled>';
                break;
        }

        if ( ! empty( $field['description'] ) ) {
            echo '<p class="dt-webform-field-description">' . esc_html( $field['description'] ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Process AJAX requests for form builder
     */
    public function handle_ajax_requests() {
        add_action( 'wp_ajax_dt_webform_get_dt_fields', [ $this, 'ajax_get_dt_fields' ] );
        add_action( 'wp_ajax_dt_webform_preview_form', [ $this, 'ajax_preview_form' ] );
        add_action( 'wp_ajax_dt_webform_validate_field', [ $this, 'ajax_validate_field' ] );
    }

    /**
     * AJAX handler to get DT fields for a post type
     */
    public function ajax_get_dt_fields() {
        error_log( 'DT Webform: ajax_get_dt_fields called' );
        error_log( 'DT Webform: POST data: ' . print_r( $_POST, true ) );
        
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dt_webform_builder' ) ) {
            error_log( 'DT Webform: Nonce verification failed' );
            wp_send_json_error( 'Security check failed' );
            return;
        }

        if ( ! current_user_can( 'manage_dt' ) ) {
            error_log( 'DT Webform: Permission check failed' );
            wp_send_json_error( 'Insufficient permissions' );
            return;
        }

        $post_type = sanitize_text_field( $_POST['post_type'] ?? '' );
        error_log( 'DT Webform: Getting fields for post type: ' . $post_type );
        
        $fields = $this->get_dt_fields( $post_type );
        
        error_log( 'DT Webform: Returning fields: ' . print_r( $fields, true ) );
        wp_send_json_success( $fields );
    }

    /**
     * AJAX handler for form preview
     */
    public function ajax_preview_form() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dt_webform_builder' ) ) {
            wp_die( 'Security check failed' );
        }

        if ( ! current_user_can( 'manage_dt' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $form_data = json_decode( stripslashes( $_POST['form_data'] ?? '{}' ), true );
        
        // Generate preview HTML
        $preview_html = $this->generate_form_preview( $form_data );

        wp_send_json_success( [ 'html' => $preview_html ] );
    }

    /**
     * Generate form preview HTML
     */
    private function generate_form_preview( $form_data ) {
        ob_start();
        
        $title = $form_data['title'] ?? __( 'Form Preview', 'dt-webform' );
        $description = $form_data['settings']['description'] ?? '';
        $fields = $form_data['fields'] ?? [];
        
        ?>
        <div class="dt-webform-preview">
            <div class="dt-webform-header">
                <h2><?php echo esc_html( $title ); ?></h2>
                <?php if ( $description ) : ?>
                    <p class="dt-webform-description"><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
            </div>
            
            <form class="dt-webform-form">
                <?php foreach ( $fields as $field ) : ?>
                    <div class="dt-webform-field">
                        <?php $this->render_field_preview( $field ); ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="dt-webform-submit">
                    <button type="submit" class="dt-webform-submit-btn" disabled>
                        <?php esc_html_e( 'Submit', 'dt-webform' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * AJAX handler for field validation
     */
    public function ajax_validate_field() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dt_webform_builder' ) ) {
            wp_die( 'Security check failed' );
        }

        if ( ! current_user_can( 'manage_dt' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $field_data = json_decode( stripslashes( $_POST['field_data'] ?? '{}' ), true );
        $errors = $this->validate_field_configuration( $field_data );

        if ( empty( $errors ) ) {
            wp_send_json_success( [ 'valid' => true ] );
        } else {
            wp_send_json_error( [ 'errors' => $errors ] );
        }
    }

    /**
     * Validate field configuration
     */
    private function validate_field_configuration( $field_data ) {
        $errors = [];

        if ( empty( $field_data['key'] ) ) {
            $errors[] = __( 'Field key is required.', 'dt-webform' );
        }

        if ( empty( $field_data['label'] ) ) {
            $errors[] = __( 'Field label is required.', 'dt-webform' );
        }

        if ( empty( $field_data['type'] ) ) {
            $errors[] = __( 'Field type is required.', 'dt-webform' );
        }

        // Validate field-specific requirements
        if ( in_array( $field_data['type'] ?? '', [ 'select', 'radio', 'multi_select' ] ) ) {
            if ( empty( $field_data['options'] ) || ! is_array( $field_data['options'] ) ) {
                $errors[] = __( 'Options are required for this field type.', 'dt-webform' );
            }
        }

        return $errors;
    }

    /**
     * Get form URL for embedding/sharing
     *
     * @param int $form_id Form ID
     * @return string Form URL
     */
    private function get_form_url( $form_id ) {
        return home_url( "/webform/{$form_id}" );
    }

    /**
     * Get form embed code
     *
     * @param int $form_id Form ID
     * @param array $options Embed options
     * @return string Embed code
     */
    private function get_form_embed_code( $form_id, $options = [] ) {
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

// Initialize the form builder
DT_Webform_Form_Builder::instance();