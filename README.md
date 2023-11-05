# Affiliate Plugin

This plugin allows to get sixsilver.pl products using the API. The plugin gets products without saving them in the database.

## Part I: Usage Manual for Non-WordPress Users

### What does it do

The plugin fetches products from the sixsilver.pl website using their API, and allows you to embed the fetched products into your WordPress site using a shortcode. The fetched product data is not stored in your database (only in cache for 8 hours).

### How shortcode can look

The shortcode to use the plugin looks like this:

```markdown
[SIXSILVER_PRODUCTS stock_status=instock category=143 columns=4 orderby=popularity order=desc attribute=pa_kamien attribute_term=218 per_page=8 min_price=100 max_price=1500 sku=S-190-1 include=30889 exclude=54528,54515]
```

### What attributes can be used in shortcode

The following attributes can be used in the shortcode:

- `stock_status`: The stock status of the product. Possible values: `instock`, `outofstock`, `onbackorder`.
- `category`: The category ID of the product.
- `columns`: The number of columns to display the products in.
- `orderby`: How to order the products. Possible values: `popularity`, `date`, `price`, `relevance`.
- `order`: The direction to order the products. Possible values: `desc`, `asc`.
- `attribute`: The attribute of the product.
- `attribute_term`: The term of the product attribute.
- `per_page`: The number of products to display per page.
- `min_price`: The minimum price of the products.
- `max_price`: The maximum price of the products.
- `sku`: The SKU of the product.
- `include`: The IDs of products to include.
- `exclude`: The IDs of products to exclude.

### What those attributes do

Each attribute filters the products that are fetched from the sixsilver.pl API. For instance, `stock_status=instock` only fetches products that are in stock, while `category=143` only fetches products that belong to category 143.

### What type of values it accepts

All attributes accept string values, except `columns` and `per_page`, which accept integer values.

### Note

Some attributes can contain more than one value, separated by a comma. For instance, `include=30889,54515` includes products with the IDs 30889 and 54515.

## Part II: Technical Documentation

### What does the code do

The code defines a WordPress plugin that fetches product data from the sixsilver.pl API and displays the products on your WordPress site using a shortcode. It first calls the API to fetch the product data, then caches the data for 8 hours to reduce the number of API calls. It also keeps track of the number of API calls made in a session to prevent exceeding the API's limit.

### How to install the plugin

To install the plugin, follow these steps:

1. Download the plugin code and place it in your WordPress plugins directory, which is usually `wp-content/plugins/`.

2. Edit your `wp-config.php` file and add the following code above the line that says "That's all, stop editing! Happy publishing.":

```php
// SIXSILVER Afiliate
// Your sensitive data
$consumer_key = 'your_consumer_key';
$consumer_secret = 'your_consumer_secret';

// Your encryption key and method
$encryption_key = 'your_random_string';
$encryption_method = 'AES-256-CBC';

// Encrypt the data
$encrypted_consumer_key = openssl_encrypt($consumer_key, $encryption_method, $encryption_key);
$encrypted_consumer_secret = openssl_encrypt($consumer_secret, $encryption_method, $encryption_key);

// Store the encrypted data in wp-config.php
define('API_CALL_DOMAIN_URL', 'https://sixsilver.pl');
define('API_CALL_LIMIT', 10);
define('ENCRYPTED_CONSUMER_KEY', $encrypted_consumer_key);
define('ENCRYPTED_CONSUMER_SECRET', $encrypted_consumer_secret);
define('ENCRYPTION_KEY', $encryption_key);
define('ENCRYPTION_METHOD', $encryption_method);
```

Replace `'your_consumer_key'`, `'your_consumer_secret'`, and `'your_random_string'` with your own values.
#### How to get your api keys? 
Check here: [https://woocommerce.com/document/woocommerce-rest-api/](https://woocommerce.com/document/woocommerce-rest-api/)

3. Go to the WordPress admin dashboard, navigate to Plugins, and activate the "SIXSILVER afiliate plugin".
