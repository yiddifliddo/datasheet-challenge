<?php

//Block some domains as referer fn
add_action('init', function () {
    $blocked_domains = ['trafficbooster.pro', 'leadsgo.io'];
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

    if( $referer ) {
        foreach ($blocked_domains as $domain) {
            if (stripos($referer, $domain) !== false) {
                wp_die('Access denied.', '403 Forbidden', ['response' => 403]);
            }
        }
    }
});

function cc_mime_types($mimes)
{
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'cc_mime_types');

function i_print($a)
{
    echo '<pre>';
    print_r($a);
    echo '</pre>';
}

global $theme_url, $child_theme_url, $stylesheet_directory, $template_directory;
define('THEME_DNAME', 'icetroamerica');
$stylesheet_directory = get_stylesheet_directory();
$template_directory = get_template_directory();
$theme_url = get_template_directory_uri() . '/';
$child_theme_url = get_stylesheet_directory_uri() . '/';

add_image_size('icetro-251', 251);
add_image_size('icetro-471', 471);
add_image_size('icetro-604', 604);
add_image_size('icetro-800', 800);
add_image_size('icetro-1000', 1000);


function icetroamerica_enqueue_scripts()
{
    global $theme_url, $child_theme_url;
    global $woocommerce;

    wp_register_style('my_jq_ui', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/themes/base/jquery.ui.all.css', __FILE__, false);
    wp_enqueue_style('my_jq_ui');
    wp_enqueue_script('slick-script', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick.min.js', array('jquery'));
    wp_enqueue_style('slick-theme-style', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css');
    wp_enqueue_style('slick-style', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick.min.css');
    wp_enqueue_style('font-Montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');
    wp_enqueue_style('font-awesome-font', $child_theme_url . 'assets/fonts/font-awesome-4.7.0/css/font-awesome.min.css');
    wp_enqueue_script('icetroamerica-scripts', $child_theme_url . 'assets/js/main.js', array('jquery'), '0.0.1', true);

    wp_localize_script(
        'icetroamerica-scripts',
        'i_infos',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'home_url' => home_url(),
        )
    );
}

add_action('wp_enqueue_scripts', 'icetroamerica_enqueue_scripts', 100);

function add_custom_svg_icons()
{
?>
    <svg style="display:none;">
        <symbol id="icon-user-circle-o" viewBox="0 0 28 28">
            <title>user-circle-o</title>
            <path d="M14 0c7.734 0 14 6.266 14 14 0 7.688-6.234 14-14 14-7.75 0-14-6.297-14-14 0-7.734 6.266-14 14-14zM23.672 21.109c1.453-2 2.328-4.453 2.328-7.109 0-6.609-5.391-12-12-12s-12 5.391-12 12c0 2.656 0.875 5.109 2.328 7.109 0.562-2.797 1.922-5.109 4.781-5.109 1.266 1.234 2.984 2 4.891 2s3.625-0.766 4.891-2c2.859 0 4.219 2.312 4.781 5.109zM20 11c0-3.313-2.688-6-6-6s-6 2.688-6 6 2.688 6 6 6 6-2.688 6-6z"></path>
        </symbol>
        <symbol id="icon-search" viewBox="0 0 24 24">
            <title>search</title>
            <path d="M21.7 20.3l-3.7-3.7c1.2-1.5 2-3.5 2-5.6 0-5-4-9-9-9s-9 4-9 9c0 5 4 9 9 9 2.1 0 4.1-0.7 5.6-2l3.7 3.7c0.2 0.2 0.5 0.3 0.7 0.3s0.5-0.1 0.7-0.3c0.4-0.4 0.4-1 0-1.4zM4 11c0-3.9 3.1-7 7-7s7 3.1 7 7c0 1.9-0.8 3.7-2 4.9 0 0 0 0 0 0s0 0 0 0c-1.3 1.3-3 2-4.9 2-4 0.1-7.1-3-7.1-6.9z"></path>
        </symbol>

        <symbol id="icon-facebook" viewBox="0 0 28 28">
            <title>facebook</title>
            <path d="M20.75 1.188v4.125h-2.453c-1.922 0-2.281 0.922-2.281 2.25v2.953h4.578l-0.609 4.625h-3.969v11.859h-4.781v-11.859h-3.984v-4.625h3.984v-3.406c0-3.953 2.422-6.109 5.953-6.109 1.687 0 3.141 0.125 3.563 0.187z"></path>
        </symbol>

        <symbol id="icon-linkedin2" viewBox="0 0 24 24">
            <title>linkedin2</title>
            <path d="M9 9h4.151v2.128h0.059c0.577-1.036 1.991-2.128 4.098-2.128 4.382 0 5.192 2.728 5.192 6.275v7.225h-4.327v-6.405c0-1.528-0.031-3.493-2.251-3.493-2.253 0-2.597 1.664-2.597 3.382v6.516h-4.325v-13.5z"></path>
            <path d="M1.5 9h4.5v13.5h-4.5v-13.5z"></path>
            <path d="M6 5.25c0 1.243-1.007 2.25-2.25 2.25s-2.25-1.007-2.25-2.25c0-1.243 1.007-2.25 2.25-2.25s2.25 1.007 2.25 2.25z"></path>
        </symbol>

        <symbol id="icon-twitter" viewBox="0 0 24 24">
            <title>twitter</title>
            <path d="M24.002 4.556c-0.881 0.394-1.833 0.656-2.827 0.773 1.017-0.609 1.795-1.575 2.166-2.723-0.952 0.562-2.006 0.975-3.127 1.195-0.9-0.956-2.18-1.552-3.595-1.552-2.719 0-4.922 2.203-4.922 4.922 0 0.384 0.042 0.759 0.127 1.12-4.092-0.206-7.72-2.166-10.148-5.147-0.422 0.727-0.666 1.575-0.666 2.475 0 1.706 0.867 3.216 2.189 4.097-0.806-0.023-1.566-0.248-2.231-0.614 0 0.019 0 0.042 0 0.061 0 2.386 1.697 4.378 3.952 4.828-0.412 0.112-0.848 0.173-1.298 0.173-0.319 0-0.623-0.033-0.928-0.089 0.628 1.955 2.447 3.38 4.598 3.422-1.688 1.322-3.806 2.109-6.117 2.109-0.398 0-0.788-0.023-1.177-0.070 2.184 1.402 4.772 2.212 7.552 2.212 9.056 0 14.011-7.505 14.011-14.011 0-0.216-0.005-0.427-0.014-0.637 0.961-0.689 1.795-1.556 2.456-2.545z"></path>
        </symbol>

        <symbol id="icon-document-download" viewBox="0 0 32 32">
            <title>document-download</title>
            <path d="M16 25.050l-3.25-3.25-0.75 0.75 4.5 4.5 4.5-4.5-0.75-0.75-3.25 3.25v-11.050h-1v11.050zM19.5 3h0.5l6 7v18.009c0 1.093-0.894 1.991-1.997 1.991h-15.005c-1.107 0-1.997-0.899-1.997-2.007v-22.985c0-1.109 0.897-2.007 2.003-2.007h10.497zM19 4h-10.004c-0.55 0-0.996 0.455-0.996 0.995v23.009c0 0.55 0.455 0.995 1 0.995h15c0.552 0 1-0.445 1-0.993v-17.007h-4.002c-1.103 0-1.998-0.887-1.998-2.006v-4.994zM20 4.5v4.491c0 0.557 0.451 1.009 0.997 1.009h3.703l-4.7-5.5z"></path>
        </symbol>

        <symbol id="icon-info" viewBox="0 0 24 24">
            <title>info</title>
            <path d="M10.5 7.125c0-0.619 0.506-1.125 1.125-1.125h0.75c0.619 0 1.125 0.506 1.125 1.125v0.75c0 0.619-0.506 1.125-1.125 1.125h-0.75c-0.619 0-1.125-0.506-1.125-1.125v-0.75z"></path>
            <path d="M15 18h-6v-1.5h1.5v-4.5h-1.5v-1.5h4.5v6h1.5z"></path>
            <path d="M12 0c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zM12 21.75c-5.385 0-9.75-4.365-9.75-9.75s4.365-9.75 9.75-9.75 9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75z"></path>
        </symbol>
    </svg>
<?php
}
add_action('wp_head', 'add_custom_svg_icons');

include_once 'lib/custom_post_types.php';
include_once 'lib/shortcodes.php';
require_once 'lib/acf-fields.php';

// Datasheet Email Gate admin settings
require_once get_stylesheet_directory() . '/datasheet-email-gate.php';

// require_once 'sitefinity/listing_image.php';
// require_once 'sitefinity/related_docs.php';
// require_once 'sitefinity/application_image.php';
// require_once 'sitefinity/related_images.php';
// require_once 'sitefinity/application.php';
// require_once 'sitefinity/series.php';
// require_once 'sitefinity/blog_content_images.php';
// require_once 'sitefinity/blog_posts_dates.php';
// require_once 'sitefinity/products_summary.php';

function i_flatsome_search_integration($search_query, $args, $defaults)
{
    $args['post_type'] = array('post', 'page', 'product');
    $args['meta_query'] = array(
        'relation' => 'OR',
        array(
            'key' => 'product_id',
            'value' => $args['s'],
            'compare' => 'LIKE',
        ),
    );

    $query = new WP_Query($args);
    return $query->posts;
}
add_filter('flatsome_ajax_search_function', function () {
    return 'i_flatsome_search_integration';
}, 10, 3);

// function display_weather_banner() {
//     echo '<div style="background-color: #ffcc00; color: #000; text-align: center; padding: 10px; font-size: 16px;">
//             Due to adverse weather conditions in SC, our facilities are temporarily closed. We will reopen as soon as it is safe for our team to return. <br> If you need assistance, please feel free to call or use our <a href="/contact" style="color: #000; text-decoration: underline;">Contact Form</a>. Thank you for your understanding.
//           </div>';
// }
// add_action('wp_head', 'display_weather_banner');
//

add_action('init', function () {
    $role = add_role('forms_manager', 'Forms Manager');

    if ($role) {
        $caps = [
            'gform_full_access',
            'gravityforms_edit_forms',
            'gravityforms_view_entries',
            'gravityforms_edit_entries',
            'gravityforms_delete_entries',
            'gravityforms_export_entries',
            'gravityforms_view_entry_notes',
            'gravityforms_edit_entry_notes',
            'gravityforms_view_settings',
            'gravityforms_edit_settings',
            'gravityforms_view_addons',
            'gravityforms_edit_addons',
            'gravityforms_view_updates',
            'gravityforms_edit_updates',
            'gravityforms_preview_forms',
            'gravityforms_uninstall',
            'gravityforms_create_form',
            'gravityforms_delete_forms',
            'gravityforms_duplicate_forms',
        ];

        foreach ($caps as $cap) {
            $role->add_cap($cap);
        }
    }
});
