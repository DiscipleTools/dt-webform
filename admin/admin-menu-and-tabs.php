<?php
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

/**
 * Class Disciple_Tools_Webform_Menu
 */
class Disciple_Tools_Webform_Menu {

    public $token = 'disciple_tools_webform';
    public $page_title = 'DT Webform';

    private static $_instance = null;

    /**
     * Disciple_Tools_Webform_Menu Instance
     *
     * Ensures only one instance of Disciple_Tools_Webform_Menu is loaded or can be loaded.
     *
     * @since 0.1.0
     * @static
     * @return Disciple_Tools_Webform_Menu instance
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()


    /**
     * Constructor function.
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {

        add_action( 'admin_menu', array( $this, 'register_menu' ) );

        $this->page_title = __( 'DT Webform', 'dt-webform' );
    } // End __construct()


    /**
     * Loads the subnav page
     * @since 0.1
     */
    public function register_menu() {
        $this->page_title = __( 'DT Webform', 'dt-webform' );

        add_submenu_page( 'dt_extensions', $this->page_title, $this->page_title, 'manage_dt', $this->token, [ $this, 'content' ] );
    }

    /**
     * Menu stub. Replaced when Disciple.Tools Theme fully loads.
     */
    public function extensions_menu() {}

    /**
     * Builds page contents
     * @since 0.1
     */
    public function content() {

        if ( !current_user_can( 'manage_dt' ) ) { // manage dt is a permission that is specific to Disciple.Tools and allows admins, strategists and dispatchers into the wp-admin
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        if ( isset( $_GET['tab'] ) ) {
            $tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
        } else {
            $tab = 'general';
        }

        $link = 'admin.php?page='.$this->token.'&tab=';

        ?>
        <div class="wrap">
            <h2><?php echo esc_html( $this->page_title ) ?></h2>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_attr( $link ) . 'forms' ?>"
                   class="nav-tab <?php echo esc_html( ( $tab == 'forms' || !isset( $tab ) ) ? 'nav-tab-active' : '' ); ?>">Forms</a>
                <a href="<?php echo esc_attr( $link ) . 'settings' ?>" class="nav-tab <?php echo esc_html( ( $tab == 'settings' ) ? 'nav-tab-active' : '' ); ?>">Settings</a>
            </h2>

            <?php
            switch ( $tab ) {
                case 'forms':
                default:
                    $object = new Disciple_Tools_Webform_Tab_Forms();
                    $object->content();
                    break;
                case 'settings':
                    $object = new Disciple_Tools_Webform_Tab_Settings();
                    $object->content();
                    break;
            }
            ?>

        </div><!-- End wrap -->

        <?php
    }
}
Disciple_Tools_Webform_Menu::instance();

/**
 * Class Disciple_Tools_Webform_Tab_Forms
 */
class Disciple_Tools_Webform_Tab_Forms {
    public function content() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
        $form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        
        switch ( $action ) {
            case 'new':
                $this->new_form_page();
                break;
            case 'edit':
                $this->edit_form_page( $form_id );
                break;
            case 'view':
                $this->view_form_page( $form_id );
                break;
            default:
                $this->list_forms_page();
                break;
        }
    }

    private function list_forms_page() {
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Webforms', 'dt-webform' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=disciple_tools_webform&tab=forms&action=new' ) ); ?>" class="page-title-action">
                    <?php esc_html_e( 'Add New', 'dt-webform' ); ?>
                </a>
            </h1>
            
            <div id="dt-webform-forms-list">
                <div class="dt-webform-loading">
                    <p><?php esc_html_e( 'Loading forms...', 'dt-webform' ); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Embed Code Modal -->
        <div id="dt-webform-embed-modal" class="dt-webform-modal" style="display: none;">
            <div class="dt-webform-modal-content">
                <div class="dt-webform-modal-header">
                    <h3><?php esc_html_e( 'Embed Code', 'dt-webform' ); ?></h3>
                    <button type="button" class="dt-webform-modal-close">&times;</button>
                </div>
                <div class="dt-webform-modal-body">
                    <div class="dt-webform-embed-option">
                        <h4><?php esc_html_e( 'Embed Code (iframe)', 'dt-webform' ); ?></h4>
                        <p class="description"><?php esc_html_e( 'Copy and paste this code into your website HTML to embed the form.', 'dt-webform' ); ?></p>
                        <div class="dt-webform-code-container">
                            <textarea id="modal-embed-code" class="dt-webform-code-textarea" readonly></textarea>
                            <button type="button" class="button dt-webform-copy-btn" data-target="modal-embed-code">
                                <?php esc_html_e( 'Copy Code', 'dt-webform' ); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="dt-webform-embed-option">
                        <h4><?php esc_html_e( 'Direct Link', 'dt-webform' ); ?></h4>
                        <p class="description"><?php esc_html_e( 'Share this link directly or use it in your own iframe.', 'dt-webform' ); ?></p>
                        <div class="dt-webform-code-container">
                            <input type="text" id="modal-direct-url" class="dt-webform-url-input" readonly>
                            <button type="button" class="button dt-webform-copy-btn" data-target="modal-direct-url">
                                <?php esc_html_e( 'Copy URL', 'dt-webform' ); ?>
                            </button>
                            <a href="#" id="modal-preview-link" target="_blank" class="button">
                                <?php esc_html_e( 'Preview', 'dt-webform' ); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Load forms via REST API
            loadFormsList();
            
            function loadFormsList() {
                $.ajax({
                    url: '<?php echo esc_url( rest_url( 'dt-webform/v1/forms' ) ); ?>',
                    method: 'GET',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce( 'wp_rest' ); ?>');
                    },
                    success: function(forms) {
                        renderFormsList(forms);
                    },
                    error: function(xhr, status, error) {
                        $('#dt-webform-forms-list').html('<div class="notice notice-error"><p>Failed to load forms: ' + error + '</p></div>');
                    }
                });
            }
            
            function renderFormsList(forms) {
                let html = '';
                
                if (forms.length === 0) {
                    html = '<div class="notice notice-info"><p><?php esc_html_e( 'No forms found. Create your first form to get started.', 'dt-webform' ); ?></p></div>';
                } else {
                    html = '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr>';
                    html += '<th><?php esc_html_e( 'Title', 'dt-webform' ); ?></th>';
                    html += '<th><?php esc_html_e( 'Post Type', 'dt-webform' ); ?></th>';
                    html += '<th><?php esc_html_e( 'Fields', 'dt-webform' ); ?></th>';
                    html += '<th><?php esc_html_e( 'Status', 'dt-webform' ); ?></th>';
                    html += '<th><?php esc_html_e( 'Created', 'dt-webform' ); ?></th>';
                    html += '<th><?php esc_html_e( 'Actions', 'dt-webform' ); ?></th>';
                    html += '</tr></thead><tbody>';
                    
                    forms.forEach(function(form) {
                        const fieldCount = form.fields ? form.fields.length : 0;
                        const statusClass = form.is_active ? 'text-success' : 'text-muted';
                        const statusText = form.is_active ? '<?php esc_html_e( 'Active', 'dt-webform' ); ?>' : '<?php esc_html_e( 'Inactive', 'dt-webform' ); ?>';
                        const createdDate = new Date(form.created_date).toLocaleDateString();
                        
                        html += '<tr>';
                        html += '<td><strong><a href="admin.php?page=disciple_tools_webform&tab=forms&action=edit&form_id=' + form.id + '">' + form.title + '</a></strong></td>';
                        html += '<td>' + form.post_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</td>';
                        html += '<td>' + fieldCount + '</td>';
                        html += '<td><span class="' + statusClass + '">' + statusText + '</span></td>';
                        html += '<td>' + createdDate + '</td>';
                        html += '<td>';
                        html += '<a href="admin.php?page=disciple_tools_webform&tab=forms&action=edit&form_id=' + form.id + '" class="button button-small"><?php esc_html_e( 'Edit', 'dt-webform' ); ?></a> ';
                        html += '<button class="button button-small show-embed-code" data-form-id="' + form.id + '" data-form-title="' + form.title + '"><?php esc_html_e( 'Embed Code', 'dt-webform' ); ?></button> ';
                        html += '<button class="button button-small delete-form" data-form-id="' + form.id + '"><?php esc_html_e( 'Delete', 'dt-webform' ); ?></button>';
                        html += '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                }
                
                $('#dt-webform-forms-list').html(html);
            }
            
            // Handle delete form
            $(document).on('click', '.delete-form', function(e) {
                e.preventDefault();
                if (!confirm('<?php esc_html_e( 'Are you sure you want to delete this form?', 'dt-webform' ); ?>')) {
                    return;
                }
                
                const formId = $(this).data('form-id');
                const $button = $(this);
                
                $button.prop('disabled', true).text('<?php esc_html_e( 'Deleting...', 'dt-webform' ); ?>');
                
                $.ajax({
                    url: '<?php echo esc_url( rest_url( 'dt-webform/v1/forms/' ) ); ?>' + formId,
                    method: 'DELETE',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce( 'wp_rest' ); ?>');
                    },
                    success: function() {
                        loadFormsList(); // Reload the list
                    },
                    error: function(xhr, status, error) {
                        alert('Failed to delete form: ' + error);
                        $button.prop('disabled', false).text('<?php esc_html_e( 'Delete', 'dt-webform' ); ?>');
                    }
                });
            });
            
            // Handle show embed code
            $(document).on('click', '.show-embed-code', function(e) {
                e.preventDefault();
                const formId = $(this).data('form-id');
                const formTitle = $(this).data('form-title');
                
                showEmbedCodeModal(formId, formTitle);
            });
            
            // Close modal handlers
            $(document).on('click', '.dt-webform-modal-close, .dt-webform-modal', function(e) {
                if (e.target === this) {
                    $('.dt-webform-modal').hide();
                }
            });
            
            // Copy to clipboard functionality
            $(document).on('click', '.dt-webform-copy-btn', function(e) {
                e.preventDefault();
                const targetId = $(this).data('target');
                const $target = $('#' + targetId);
                
                if ($target.length) {
                    $target.select();
                    $target[0].setSelectionRange(0, 99999); // For mobile devices
                    
                    try {
                        document.execCommand('copy');
                        $(this).text('<?php esc_html_e( 'Copied!', 'dt-webform' ); ?>').addClass('copied');
                        
                        // Reset button text after 2 seconds
                        const $btn = $(this);
                        setTimeout(function() {
                            $btn.text(targetId.includes('url') ? '<?php esc_html_e( 'Copy URL', 'dt-webform' ); ?>' : '<?php esc_html_e( 'Copy Code', 'dt-webform' ); ?>').removeClass('copied');
                        }, 2000);
                    } catch (err) {
                        console.error('Failed to copy text: ', err);
                        alert('<?php esc_html_e( 'Failed to copy to clipboard. Please select and copy manually.', 'dt-webform' ); ?>');
                    }
                }
            });
            
            function showEmbedCodeModal(formId, formTitle) {
                const siteUrl = '<?php echo esc_js( home_url() ); ?>';
                const formUrl = siteUrl + '/webform/' + formId;
                const embedCode = '<iframe src="' + formUrl + '" width="100%" height="600" frameborder="0" scrolling="auto"></iframe>';
                
                // Update modal content
                $('#dt-webform-embed-modal h3').text('<?php esc_html_e( 'Embed Code for:', 'dt-webform' ); ?> ' + formTitle);
                $('#modal-embed-code').val(embedCode);
                $('#modal-direct-url').val(formUrl);
                $('#modal-preview-link').attr('href', formUrl);
                
                // Show modal
                $('#dt-webform-embed-modal').show();
            }
        });
        </script>
        <?php
    }

    private function new_form_page() {
        $form_builder = DT_Webform_Form_Builder::instance();
        $form_builder->render_form_builder( 0, [] );
    }

    private function edit_form_page( $form_id ) {
        // Get form data from the REST API or directly from the database
        $form = DT_Webform_Core::get_form( $form_id );
        
        if ( ! $form ) {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'Form not found.', 'dt-webform' ); ?></p>
            </div>
            <?php
            return;
        }
        
        // Render the form builder directly with the form data
        $form_builder = DT_Webform_Form_Builder::instance();
        $form_builder->render_form_builder( $form_id, $form );
    }

    private function view_form_page( $form_id ) {
        // For Phase 1, redirect to the public form
        $form = DT_Webform_Core::get_form( $form_id );
        if ( $form ) {
            // Construct the form URL - this would need to be implemented based on your URL structure
            $form_url = site_url( 'dt-webform/' . $form_id );
            wp_redirect( $form_url );
            exit;
        }
    }
}


/**
 * Class Disciple_Tools_Webform_Tab_Settings
 */
class Disciple_Tools_Webform_Tab_Settings {
    public function content() {
        $this->process_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Webform Settings', 'dt-webform' ); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field( 'dt_webform_settings', 'dt_webform_settings_nonce' ); ?>
                <input type="hidden" name="action" value="save_settings">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="default_success_message"><?php esc_html_e( 'Default Success Message', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <textarea id="default_success_message" name="default_success_message" class="large-text" rows="3"><?php echo esc_textarea( get_option( 'dt_webform_default_success_message', __( 'Thank you for your submission!', 'dt-webform' ) ) ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Default success message for new forms.', 'dt-webform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="enable_form_analytics"><?php esc_html_e( 'Form Analytics', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_form_analytics" name="enable_form_analytics" value="1" <?php checked( get_option( 'dt_webform_enable_analytics', false ) ); ?>>
                            <label for="enable_form_analytics"><?php esc_html_e( 'Enable form view and submission tracking', 'dt-webform' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Track form views and submissions for analytics (will be implemented in future phases).', 'dt-webform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="form_cache_duration"><?php esc_html_e( 'Cache Duration', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <select id="form_cache_duration" name="form_cache_duration">
                                <option value="0" <?php selected( get_option( 'dt_webform_cache_duration', 3600 ), 0 ); ?>><?php esc_html_e( 'No caching', 'dt-webform' ); ?></option>
                                <option value="300" <?php selected( get_option( 'dt_webform_cache_duration', 3600 ), 300 ); ?>><?php esc_html_e( '5 minutes', 'dt-webform' ); ?></option>
                                <option value="1800" <?php selected( get_option( 'dt_webform_cache_duration', 3600 ), 1800 ); ?>><?php esc_html_e( '30 minutes', 'dt-webform' ); ?></option>
                                <option value="3600" <?php selected( get_option( 'dt_webform_cache_duration', 3600 ), 3600 ); ?>><?php esc_html_e( '1 hour', 'dt-webform' ); ?></option>
                                <option value="21600" <?php selected( get_option( 'dt_webform_cache_duration', 3600 ), 21600 ); ?>><?php esc_html_e( '6 hours', 'dt-webform' ); ?></option>
                                <option value="86400" <?php selected( get_option( 'dt_webform_cache_duration', 3600 ), 86400 ); ?>><?php esc_html_e( '24 hours', 'dt-webform' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'How long to cache form configurations for better performance.', 'dt-webform' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'dt-webform' ); ?>">
                </p>
            </form>
            
            <div class="postbox" style="margin-top: 30px;">
                <h3 class="hndle"><?php esc_html_e( 'System Information', 'dt-webform' ); ?></h3>
                <div class="inside">
                    <table class="widefat striped">
                        <tbody>
                            <tr>
                                <td><strong><?php esc_html_e( 'Plugin Version', 'dt-webform' ); ?></strong></td>
                                <td><?php echo esc_html( get_option( 'dt_webform_version', '0.1' ) ); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Total Forms', 'dt-webform' ); ?></strong></td>
                                <td><?php echo esc_html( count( DT_Webform_Core::get_forms() ) ); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Active Forms', 'dt-webform' ); ?></strong></td>
                                <td>
                                    <?php 
                                    $active_forms = array_filter( DT_Webform_Core::get_forms(), function( $form ) {
                                        return $form['is_active'];
                                    });
                                    echo esc_html( count( $active_forms ) );
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Available Post Types', 'dt-webform' ); ?></strong></td>
                                <td><?php echo esc_html( implode( ', ', array_keys( DT_Webform_Core::get_available_post_types() ) ) ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function process_settings() {
        if ( ! isset( $_POST['dt_webform_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dt_webform_settings_nonce'] ) ), 'dt_webform_settings' ) ) {
            return;
        }

        if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'save_settings' ) {
            return;
        }

        $post_vars = dt_recursive_sanitize_array( $_POST );

        // Save settings
        if ( isset( $post_vars['default_success_message'] ) ) {
            update_option( 'dt_webform_default_success_message', sanitize_textarea_field( $post_vars['default_success_message'] ) );
        }

        if ( isset( $post_vars['enable_form_analytics'] ) ) {
            update_option( 'dt_webform_enable_analytics', true );
        } else {
            update_option( 'dt_webform_enable_analytics', false );
        }

        if ( isset( $post_vars['form_cache_duration'] ) ) {
            update_option( 'dt_webform_cache_duration', absint( $post_vars['form_cache_duration'] ) );
        }

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully!', 'dt-webform' ) . '</p></div>';
    }
}

