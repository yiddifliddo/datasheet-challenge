<?php
/**
 * Datasheet Email Gate — Admin Settings
 *
 * Add this file to your theme's functions.php with:
 *   require_once get_template_directory() . '/datasheet-email-gate.php';
 *
 * Creates a settings page under Settings > Datasheet Email Gate
 * where you can select the Gravity Form used to gate document downloads,
 * configure the hidden field ID for the document URL, and set the email field ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Datasheet_Email_Gate_Settings {

    const OPTION_GROUP = 'datasheet_email_gate';
    const PAGE_SLUG    = 'datasheet-email-gate';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add the settings page under the Settings menu.
     */
    public function add_settings_page() {
        add_options_page(
            'Datasheet Email Gate',
            'Datasheet Email Gate',
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register all settings fields.
     */
    public function register_settings() {
        register_setting( self::OPTION_GROUP, 'deg_gravity_form_id', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ) );

        register_setting( self::OPTION_GROUP, 'deg_hidden_field_id', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 2,
        ) );

        register_setting( self::OPTION_GROUP, 'deg_email_field_id', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 1,
        ) );

        register_setting( self::OPTION_GROUP, 'deg_modal_heading', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Enter your email to receive this document',
        ) );

        register_setting( self::OPTION_GROUP, 'deg_enable_gate', array(
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ) );

        add_settings_section(
            'deg_main_section',
            'Configuration',
            array( $this, 'render_section_description' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'deg_enable_gate',
            'Enable Email Gate',
            array( $this, 'render_enable_gate_field' ),
            self::PAGE_SLUG,
            'deg_main_section'
        );

        add_settings_field(
            'deg_gravity_form_id',
            'Gravity Form',
            array( $this, 'render_form_select_field' ),
            self::PAGE_SLUG,
            'deg_main_section'
        );

        add_settings_field(
            'deg_hidden_field_id',
            'Hidden Field ID (Document URL)',
            array( $this, 'render_hidden_field_id' ),
            self::PAGE_SLUG,
            'deg_main_section'
        );

        add_settings_field(
            'deg_email_field_id',
            'Email Field ID',
            array( $this, 'render_email_field_id' ),
            self::PAGE_SLUG,
            'deg_main_section'
        );

        add_settings_field(
            'deg_modal_heading',
            'Modal Heading',
            array( $this, 'render_modal_heading_field' ),
            self::PAGE_SLUG,
            'deg_main_section'
        );
    }

    public function render_section_description() {
        echo '<p>Select the Gravity Form to use for gating document downloads. ';
        echo 'The form should contain an <strong>Email field</strong> and a <strong>Hidden field</strong> ';
        echo 'that will receive the document URL automatically.</p>';
        echo '<p>If your form uses <strong>double opt-in</strong>, the confirmation email will be sent ';
        echo 'by Gravity Forms before delivering the document link.</p>';
    }

    /**
     * Render the enable/disable toggle.
     */
    public function render_enable_gate_field() {
        $enabled = get_option( 'deg_enable_gate', true );
        echo '<label>';
        echo '<input type="checkbox" name="deg_enable_gate" value="1" ' . checked( $enabled, true, false ) . '>';
        echo ' Require email before document download';
        echo '</label>';
        echo '<p class="description">When disabled, documents will download directly without an email prompt.</p>';
    }

    /**
     * Render a dropdown of all Gravity Forms.
     */
    public function render_form_select_field() {
        $selected_id = get_option( 'deg_gravity_form_id', 0 );

        if ( ! class_exists( 'GFAPI' ) ) {
            echo '<p style="color:#d63638;"><strong>Gravity Forms is not active.</strong> Please install and activate Gravity Forms.</p>';
            return;
        }

        $forms = GFAPI::get_forms();

        if ( empty( $forms ) ) {
            echo '<p>No Gravity Forms found. <a href="' . esc_url( admin_url( 'admin.php?page=gf_new_form' ) ) . '">Create one</a>.</p>';
            return;
        }

        echo '<select name="deg_gravity_form_id">';
        echo '<option value="0">— Select a form —</option>';
        foreach ( $forms as $form ) {
            printf(
                '<option value="%d" %s>%s (ID: %d)</option>',
                absint( $form['id'] ),
                selected( $selected_id, $form['id'], false ),
                esc_html( $form['title'] ),
                absint( $form['id'] )
            );
        }
        echo '</select>';
        echo '<p class="description">Choose the form with an email field and a hidden field for the document URL. ';
        echo 'Enable double opt-in in the form\'s settings if needed.</p>';
    }

    /**
     * Render hidden field ID input.
     */
    public function render_hidden_field_id() {
        $value = get_option( 'deg_hidden_field_id', 2 );
        printf(
            '<input type="number" name="deg_hidden_field_id" value="%d" min="1" class="small-text">',
            absint( $value )
        );
        echo '<p class="description">The Gravity Forms field ID of the <strong>Hidden</strong> field that will store the document URL. ';
        echo 'Find this in the form editor under the field\'s Advanced tab.</p>';
    }

    /**
     * Render email field ID input.
     */
    public function render_email_field_id() {
        $value = get_option( 'deg_email_field_id', 1 );
        printf(
            '<input type="number" name="deg_email_field_id" value="%d" min="1" class="small-text">',
            absint( $value )
        );
        echo '<p class="description">The Gravity Forms field ID of the <strong>Email</strong> field.</p>';
    }

    /**
     * Render modal heading input.
     */
    public function render_modal_heading_field() {
        $value = get_option( 'deg_modal_heading', 'Enter your email to receive this document' );
        printf(
            '<input type="text" name="deg_modal_heading" value="%s" class="regular-text">',
            esc_attr( $value )
        );
        echo '<p class="description">The heading text displayed in the email gate popup.</p>';
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Datasheet Email Gate Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

new Datasheet_Email_Gate_Settings();

/**
 * Helper: get the configured form ID.
 */
function deg_get_form_id() {
    return absint( get_option( 'deg_gravity_form_id', 0 ) );
}

/**
 * Helper: check if the email gate is enabled.
 */
function deg_is_enabled() {
    return (bool) get_option( 'deg_enable_gate', true ) && deg_get_form_id() > 0;
}

/**
 * Helper: get the hidden field input name.
 */
function deg_get_hidden_field_name() {
    $id = absint( get_option( 'deg_hidden_field_id', 2 ) );
    return 'input_' . $id;
}
