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
        $this->process_actions();
        
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

    private function process_actions() {
        if ( ! isset( $_POST['dt_webform_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dt_webform_nonce'] ) ), 'dt_webform_action' ) ) {
            return;
        }

        $action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
        
        switch ( $action ) {
            case 'create_form':
                $this->handle_create_form();
                break;
            case 'update_form':
                $this->handle_update_form();
                break;
            case 'delete_form':
                $this->handle_delete_form();
                break;
        }
    }

    private function handle_create_form() {
        $form_data = dt_recursive_sanitize_array( $_POST );
        $manager = DT_Webform_Form_Manager::instance();
        
        $result = $manager->create_form_from_admin( $form_data );
        
        if ( is_wp_error( $result ) ) {
            dt_write_log( 'Webform creation failed: ' . $result->get_error_message() );
            $this->add_notice( $result->get_error_message(), 'error' );
        } else {
            $this->add_notice( __( 'Form created successfully!', 'dt-webform' ), 'success' );
            // Redirect to edit page
            wp_redirect( admin_url( 'admin.php?page=disciple_tools_webform&tab=forms&action=edit&form_id=' . $result ) );
            exit;
        }
    }

    private function handle_update_form() {
        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $form_data = dt_recursive_sanitize_array( $_POST );
        $manager = DT_Webform_Form_Manager::instance();
        
        $result = $manager->update_form_from_admin( $form_id, $form_data );
        
        if ( is_wp_error( $result ) ) {
            $this->add_notice( $result->get_error_message(), 'error' );
        } else {
            $this->add_notice( __( 'Form updated successfully!', 'dt-webform' ), 'success' );
        }
    }

    private function handle_delete_form() {
        $form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        
        if ( DT_Webform_Core::delete_form( $form_id ) ) {
            $this->add_notice( __( 'Form deleted successfully!', 'dt-webform' ), 'success' );
        } else {
            $this->add_notice( __( 'Failed to delete form.', 'dt-webform' ), 'error' );
        }
    }

    private function add_notice( $message, $type = 'info' ) {
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }

    private function list_forms_page() {
        $forms = DT_Webform_Form_Manager::instance()->get_forms_for_admin();
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Webforms', 'dt-webform' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=disciple_tools_webform&tab=forms&action=new' ) ); ?>" class="page-title-action">
                    <?php esc_html_e( 'Add New', 'dt-webform' ); ?>
                </a>
            </h1>
            
            <?php if ( empty( $forms ) ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'No forms found. Create your first form to get started.', 'dt-webform' ); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Title', 'dt-webform' ); ?></th>
                            <th><?php esc_html_e( 'Post Type', 'dt-webform' ); ?></th>
                            <th><?php esc_html_e( 'Fields', 'dt-webform' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'dt-webform' ); ?></th>
                            <th><?php esc_html_e( 'Created', 'dt-webform' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'dt-webform' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $forms as $form ) : ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=disciple_tools_webform&tab=forms&action=edit&form_id=' . $form['id'] ) ); ?>">
                                            <?php echo esc_html( $form['title'] ); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $form['post_type'] ) ) ); ?></td>
                                <td><?php echo esc_html( $form['field_count'] ); ?></td>
                                <td>
                                    <span class="<?php echo $form['is_active'] ? 'text-success' : 'text-muted'; ?>">
                                        <?php echo $form['is_active'] ? esc_html__( 'Active', 'dt-webform' ) : esc_html__( 'Inactive', 'dt-webform' ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( mysql2date( 'M j, Y', $form['created_date'] ) ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=disciple_tools_webform&tab=forms&action=edit&form_id=' . $form['id'] ) ); ?>" class="button button-small">
                                        <?php esc_html_e( 'Edit', 'dt-webform' ); ?>
                                    </a>
                                    <a href="<?php echo esc_url( $form['form_url'] ); ?>" class="button button-small" target="_blank">
                                        <?php esc_html_e( 'View', 'dt-webform' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private function new_form_page() {
        $post_types = DT_Webform_Core::get_available_post_types();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Create New Form', 'dt-webform' ); ?></h1>
            
            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=disciple_tools_webform&tab=forms' ) ); ?>">
                <?php wp_nonce_field( 'dt_webform_action', 'dt_webform_nonce' ); ?>
                <input type="hidden" name="action" value="create_form">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="title"><?php esc_html_e( 'Form Title', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="title" name="title" class="regular-text" required>
                            <p class="description"><?php esc_html_e( 'Enter a descriptive title for your form.', 'dt-webform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="post_type"><?php esc_html_e( 'Target Post Type', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <select id="post_type" name="post_type" required>
                                <option value=""><?php esc_html_e( 'Select a post type...', 'dt-webform' ); ?></option>
                                <?php foreach ( $post_types as $type => $config ) : ?>
                                    <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $config['label'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Select the type of record this form will create.', 'dt-webform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="is_active"><?php esc_html_e( 'Active', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="is_active" name="is_active" value="1">
                            <label for="is_active"><?php esc_html_e( 'Make this form active immediately', 'dt-webform' ); ?></label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e( 'Create Form', 'dt-webform' ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=disciple_tools_webform&tab=forms' ) ); ?>" class="button">
                        <?php esc_html_e( 'Cancel', 'dt-webform' ); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    private function edit_form_page( $form_id ) {
        $form = DT_Webform_Form_Manager::instance()->get_form_for_admin( $form_id );
        
        if ( ! $form ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Form not found.', 'dt-webform' ) . '</p></div>';
            return;
        }

        $post_types = DT_Webform_Core::get_available_post_types();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( sprintf( __( 'Edit Form: %s', 'dt-webform' ), $form['title'] ) ); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field( 'dt_webform_action', 'dt_webform_nonce' ); ?>
                <input type="hidden" name="action" value="update_form">
                <input type="hidden" name="form_id" value="<?php echo esc_attr( $form['id'] ); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="title"><?php esc_html_e( 'Form Title', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="title" name="title" class="regular-text" value="<?php echo esc_attr( $form['title'] ); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="post_type"><?php esc_html_e( 'Target Post Type', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <select id="post_type" name="post_type" required>
                                <?php foreach ( $post_types as $type => $config ) : ?>
                                    <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $form['post_type'], $type ); ?>>
                                        <?php echo esc_html( $config['label'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="is_active"><?php esc_html_e( 'Active', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="is_active" name="is_active" value="1" <?php checked( $form['is_active'] ); ?>>
                            <label for="is_active"><?php esc_html_e( 'Form is active and accepting submissions', 'dt-webform' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="form_url"><?php esc_html_e( 'Form URL', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <input type="url" id="form_url" class="regular-text" value="<?php echo esc_url( $form['form_url'] ); ?>" readonly>
                            <p class="description"><?php esc_html_e( 'Direct link to your form.', 'dt-webform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="embed_code"><?php esc_html_e( 'Embed Code', 'dt-webform' ); ?></label>
                        </th>
                        <td>
                            <textarea id="embed_code" class="large-text code" rows="3" readonly><?php echo esc_textarea( $form['embed_code'] ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Copy this code to embed the form on external websites.', 'dt-webform' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e( 'Update Form', 'dt-webform' ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=disciple_tools_webform&tab=forms' ) ); ?>" class="button">
                        <?php esc_html_e( 'Back to Forms', 'dt-webform' ); ?>
                    </a>
                </p>
            </form>
            
            <div class="postbox" style="margin-top: 20px;">
                <h3 class="hndle"><?php esc_html_e( 'Danger Zone', 'dt-webform' ); ?></h3>
                <div class="inside">
                    <form method="post" style="margin: 0;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this form? This action cannot be undone.', 'dt-webform' ); ?>')">
                        <?php wp_nonce_field( 'dt_webform_action', 'dt_webform_nonce' ); ?>
                        <input type="hidden" name="action" value="delete_form">
                        <input type="hidden" name="form_id" value="<?php echo esc_attr( $form['id'] ); ?>">
                        <p><?php esc_html_e( 'Delete this form permanently. This action cannot be undone.', 'dt-webform' ); ?></p>
                        <input type="submit" class="button button-link-delete" value="<?php esc_attr_e( 'Delete Form', 'dt-webform' ); ?>">
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    private function view_form_page( $form_id ) {
        // For Phase 1, redirect to the public form
        $form = DT_Webform_Core::get_form( $form_id );
        if ( $form ) {
            wp_redirect( DT_Webform_Form_Manager::instance()->get_form_url( $form_id ) );
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

