<?php
/**
 * Plugin Name: Afiliate plugin
 * Description: This plugin allows to get external products using API. Plugin gets products without saving them in database (only in cache for 8 hours).
 * Version: 1.0.1
 * Author: Kamil Krygier
 * Author URI: https://www.linkedin.com/in/kamil-krygier-132940166
 * Text Domain: afiliate-plugin
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * 
 * PLACE BELOW VARIABLES INSIDE wp-config.php
 * PUT YOUR KEYS INSTEAD THOSE PLACEHOLDERS ALSO REMEMBER TO UNCOMMENT
 * REMEMBER TO PLACE IT BEFORE LINE "That's all, stop editing! Happy publishing."
 * 
 * 
 * // Afiliate Plugin
 * // Your sensitive data
 * $consumer_key = 'your_consumer_key';
 * $consumer_secret = 'your_consumer_secret';
 *
 * // Your encryption key and method
 * $encryption_key = 'your_random_string';
 * $encryption_method = 'AES-256-CBC';
 *
 * // Encrypt the data
 * $encrypted_consumer_key = openssl_encrypt($consumer_key, $encryption_method, $encryption_key);
 * $encrypted_consumer_secret = openssl_encrypt($consumer_secret, $encryption_method, $encryption_key);
 *
 * // Store the encrypted data in wp-config.php
 * // Definicja limitu zapytań na sesję
 * define('API_CALL_DOMAIN_URL', 'https://yourdomain.com');
 * define('API_CALL_LIMIT', 10);
 * define('ENCRYPTED_CONSUMER_KEY', $encrypted_consumer_key);
 * define('ENCRYPTED_CONSUMER_SECRET', $encrypted_consumer_secret);
 * define('ENCRYPTION_KEY', $encryption_key);
 * define('ENCRYPTION_METHOD', $encryption_method);
 * 
 * 
*/



/**
 * 
 * SHORTCODE EXAMPLE
 * [AFILIATE_PRODUCTS stock_status=instock category=143 columns=4 orderby=popularity order=desc attribute=pa_kamien attribute_term=218 per_page=8 min_price=100 max_price=1500 sku=S-190-1 include=30889 exclude=54528,54515]
 * 
 * POSSIBLE ARGUMENTS:
 * stock_status - string - Limit result set to products with specified stock status. Options: instock, outofstock and onbackorder.
 * category - string - Limit result set to products assigned a specific category ID.
*/

// Function calls API WooCommerce
function call_woocommerce_api($stock_status, $category, $orderby, $order, $attribute, $attribute_term, $per_page, $min_price, $max_price, $sku, $include, $exclude, $status) {

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Checking API calls limit
    if (!isset($_SESSION['api_call_count'])) {
        $_SESSION['api_call_count'] = 0;
    } else if ($_SESSION['api_call_count'] >= API_CALL_LIMIT) {
        return false;
    }

    // Prepare an array of arguments to process
    $args = compact('stock_status', 'category', 'orderby', 'order', 'attribute', 'attribute_term', 'per_page', 'min_price', 'max_price', 'sku', 'include', 'exclude', 'status');
    
    // Filter the args array to remove empty elements
    $args = array_filter($args, function($value) { return $value !== ''; });

    // Check if cache doesn't contain those products
    $cache_key = "api_cache_" . md5(implode('_', $args));
    $cached_data = get_transient($cache_key);

    if ($cached_data !== false) {
        if(empty($cached_data)) {
            delete_transient($cache_key);
        } else {
            return $cached_data;
        }
    }

    // API Settings
    $api_url = API_CALL_DOMAIN_URL . '/wp-json/wc/v3/products';
    
    // Decrypt the data
    $consumer_key = openssl_decrypt(ENCRYPTED_CONSUMER_KEY, ENCRYPTION_METHOD, ENCRYPTION_KEY);
    $consumer_secret = openssl_decrypt(ENCRYPTED_CONSUMER_SECRET, ENCRYPTION_METHOD, ENCRYPTION_KEY);
    
    // Append non-empty parameters to the API URL
    foreach ($args as $key => $value) {

        // Get the first key of the array
        if ($key == array_key_first($args)) {
            // This is the first key of the array
            $api_url .= '?' . urlencode($key) . '=' . urlencode($value);
        } else {
            $api_url .= '&' . urlencode($key) . '=' . urlencode($value);
        }

    }

    
    // Init cURL session
    $ch = curl_init();
    
    // cURL serrings
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_USERPWD, $consumer_key . ":" . $consumer_secret);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    // API call
    $response = curl_exec($ch);
    
    // Checking for errors
    if(curl_errno($ch)) {
        $error_msg = curl_error($ch);
        error_log("Error while calling WooCommerce API: $error_msg");
        return false;
    }

    // Close cURL session
    curl_close($ch);

    // Decoding the answer
    $response_data = json_decode($response, true);

    // Response cacheing
    set_transient($cache_key, $response_data, 60 * 60 * 8); // Cache for 8 hours

    // API calls count increment
    $_SESSION['api_call_count']++;
    
    return $response_data;
}

// Register shortcode
function register_products_shortcode($atts) {

    if(!is_admin()){

        // Default values for shortcode
        $atts = shortcode_atts(array(
            'stock_status' => 'instock',
            'category' => '143',
            'columns' => 4,
            'orderby' => 'popularity',
            'order' => 'desc',
            'attribute' => '',
            'attribute_term' => '',
            'per_page' => 8,
            'min_price' => '',
            'max_price' => '',
            'sku' => '',
            'include' => '',
            'exclude' => '',

        ), $atts, 'AFILIATE_PRODUCTS');
        
        // API call
        $products = call_woocommerce_api(   $atts['stock_status'], 
                                            $atts['category'], 
                                            $atts['orderby'], 
                                            $atts['order'], 
                                            $atts['attribute'], 
                                            $atts['attribute_term'], 
                                            $atts['per_page'], 
                                            $atts['min_price'], 
                                            $atts['max_price'], 
                                            $atts['sku'], 
                                            $atts['include'], 
                                            $atts['exclude'],
                                            'publish');
        
        // Check if API call returned any data
        if(empty($products)) {
            return "<p style='text-align: center;'>" . __('No results found.', 'default') . "</p>";
        }

        // Building HTML output
        $output = '<ul class="six_products_wrapper">';
        foreach ($products as $product) {
            
            $sale_price = '';

            // Set prices variables
            if($product['type'] == 'variable'){
                $regular_price = $product['baselinker_variations'][0]['regular_price'];
                if(isset($product['baselinker_variations'][0]['sale_price']) && $product['baselinker_variations'][0]['sale_price'] !== "") $sale_price = $product['baselinker_variations'][0]['sale_price'];
            } else if($product['type'] == 'simple'){
                if(isset($product['regular_price']) && $product['regular_price'] !== "") $regular_price = $product['regular_price'];
                    else $regular_price = $product['price'];
                if(isset($product['sale_price']) && $product['sale_price'] !== "") $sale_price = $product['sale_price'];
            }

            $parsed_url = parse_url(get_site_url());
            $page_type = "";
            if(is_singular('post')) $page_type = "POST";
            else if(is_singular('page')) $page_type = "PAGE";
            else if(is_singular('product')) $page_type = "PRODUCT"; 
            else if(is_singular('attachment')) $page_type = "ATTACHMENT"; 
            else if(is_singular('revision')) $page_type = "REVISION"; 
            else if(is_singular('menu')) $page_type = "ATTACHMENT"; 
            else $page_type = "OTHER";

            // Build the product
            $output .= '<li class="six_product">';
            $output .= '<a href="' . $product['permalink'] . '?utm_source='. sanitize_title($parsed_url['host']) .'&utm_medium='. $page_type .'">';
            $output .= '<div class="six_image_wrapper"><img src="' . $product['images'][0]['src'] . '" alt="' . $product['name'] . '">';

            if($sale_price) $output .= '<div class="six_sale_badge"><span>SALE!</span></div>';
            else if($regular_price >= 200) $output .= '<div class="six_sale_badge"><span>DARMOWA DOSTAWA</span></div>';

            $output .= '</div>';
            $output .= '<p>' . $product['name'] . '</p>';
            if($sale_price) $output .= '<p class="six_price"><del>' . $regular_price . 'zł</del> <ins>' . $sale_price . 'zł</ins></p>';
            else $output .= '<p class="six_price"><ins>' . $regular_price . 'zł</ins></p>';
            $output .= '</a>';
            $output .= '</li>';

        }
        $output .= '</ul>';

        $columns_1140 = 3;
        if($atts['columns'] > 4) $columns_1140 = ceil($atts['columns'] / 2);

        $output .= '<style> .six_products_wrapper{display: grid;grid-template-columns: repeat('.$atts['columns'].', 1fr);column-gap: .5em;row-gap: .5em;grid-auto-rows: max-content;list-style: none;width: 100%;overflow: hidden;padding: 0;max-width: 1440px !important;}.six_products_wrapper > .six_product{width: 100%;}.six_products_wrapper > .six_product > a{display: block;padding: 15px;touch-action: manipulation;cursor: pointer;text-decoration: initial;color: #090909;transition: ease-in-out .2s;}.six_products_wrapper > .six_product > a:hover{color: #262626;transition: ease-in-out .2s;}.six_products_wrapper > .six_product > a .six_image_wrapper{background: #f6f6f6;background: -webkit-radial-gradient(circle, #f6f6f6 0%, #f1f1f4 50%, #e7e7e9 100%);background: radial-gradient(circle, #f6f6f6 0%, #f1f1f4 50%, #e7e7e9 100%);margin-bottom: 15px;aspect-ratio: 3/4;object-fit: cover;display: flex;align-items: center;justify-content: center;margin-bottom: 15px;position: relative;}.six_products_wrapper > .six_product > a .six_image_wrapper .six_sale_badge{position: absolute;bottom: 0;left: 0;right: 0;width: 100%;display: flex;align-items: center;background-color: #BFA4DB;text-align: center;}.six_products_wrapper > .six_product > a .six_image_wrapper .six_sale_badge > span{color: #FFFFFF;text-transform: uppercase;margin: 0 auto;font-size: 15px;padding: 0 3px;}.six_products_wrapper > .six_product > a p{line-height: 1.1em;text-align: left;font-size: 15px;color: #090909;letter-spacing: .02em;margin-top: 0;margin-bottom: 10px;}.six_products_wrapper > .six_product img{max-width: 100%;height: auto;}.six_products_wrapper > .six_product .six_price{text-align: left;color: #888888;font-size: 14px;line-height: 18px;}@media screen and (max-width: 1140px){.six_products_wrapper{grid-template-columns: repeat('.$columns_1140 .', 1fr);}}@media screen and (max-width: 767px){.six_products_wrapper{grid-template-columns: repeat(2, 1fr);}}@media screen and (max-width: 445px){.six_products_wrapper > .six_product > a .six_image_wrapper .six_sale_badge > span{font-size: 11px;}}</style>';

        return $output;
    }
    return;
}
add_shortcode('AFILIATE_PRODUCTS', 'register_products_shortcode');
