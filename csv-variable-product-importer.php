<?php

/**
 * Plugin Name: CSV Variable Product Importer
 * Plugin URI: https://github.com/varun761/csv-variable-product-importer
 * Description: An extension to import variable products with variants in woocommerce.
 * Version: 1.0.0
 * Author: Varun
 * Author URI: https://github.com/varun761
 * Text Domain: csv-variable-product-importer
 * Requires at least: 6.2
 * Requires PHP: 7.2
 *
 * @package WooCommerce
 */

$activated_plugins = get_option('active_plugins', array());
/**
 * Check WooCommerce exists
 */
if (!in_array('woocommerce/woocommerce.php', $activated_plugins)) {
    return;
}

/*
 * Plugin Directory
 */
if (!defined('CVPI_PLUGIN_DIR_PATH')) {
    define('CVPI_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
}
if (!defined('CVPI_PLUGIN_DIR_URL')) {
    define('CVPI_PLUGIN_DIR_URL', plugin_dir_url(__FILE__));
}

class CSV_Variable_Product_Importer
{
    protected $admin_page_baseurl = 'admin.php?page=csv-variable-product-importer';

    public function __construct()
    {
        if (is_admin()) {
            register_uninstall_hook(__FILE__, array($this, 'uninstall'));
        }
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_post_woocommerce_product_importer', array($this, 'handleFileUploadParse'));
    }

    public function admin_enqueue_scripts()
    {
        wp_register_style('wp_product_importer_css', plugin_dir_url(__FILE__) . '/assets/css/admin_product_importer.css', false, '1.0');
        wp_enqueue_style('wp_product_importer_css');
    }

    public function add_menu_page()
    {
        add_submenu_page('woocommerce', 'Product Import', 'Product Import', 'manage_options', 'csv-variable-product-importer', array($this, 'product_importer_admin_callback'), 1);
    }

    public function product_importer_admin_callback()
    {
        include_once('views/import_form.php');
    }

    public function check_value_exists($arr, $key)
    {
        return is_array($arr) && count($arr) > 0 && array_key_exists($key, $arr) && null !== $arr[$key] && $arr[$key] !== '';
    }

    public function handleFileUploadParse()
    {
        if (isset($_POST['wp_nonce']) && wp_verify_nonce($_POST['wp_nonce'], 'woocommerce_product_importer')) {
            $override = array('test_form' => false);
            $file = $_FILES['file'];
            $file_type = $_FILES['file']['type'];
            if ($file_type === 'text/csv') {
                $file_data = wp_handle_upload($file, $override);
                $row = 1;
                $parsed_data = [];
                $columns = [];
                if (!array_key_exists('error', $file_data)) {
                    // parse csv
                    $handle = fopen($file_data['file'], "r");
                    while (($data = fgetcsv($handle, $_FILES['file']['size'], "\t")) !== FALSE) {
                        $parsed_row = [];
                        $attributes = [];
                        $row_data = explode(',', $data[0]);
                        if ($row === 1) {
                            $columns = array_map(function ($elem) {
                                return str_replace('?', '', str_replace(' ', '_', strtolower($elem)));
                            }, $row_data);
                        } else {
                            foreach ($row_data as $key => $value) {
                                $key_name = $columns[$key];
                                if (!str_contains($key_name, 'attribute')) {
                                    $parsed_row[$key_name] = $value;
                                } else {
                                    $key_array = explode('_', $key_name);
                                    $attributes[$key_array[1] - 1][$key_array[2]] = $value;
                                }
                            }
                            $parsed_row['attributes'] = array_filter($attributes, function ($elem) {
                                return $elem['name'] !== '' && $elem['value(s)'] !== '';
                            });
                            array_push($parsed_data, $parsed_row);
                        }


                        $row++;
                    }
                    $parent_product = [];
                    if (count($parsed_data) > 0) {
                        foreach ($parsed_data as $product) {
                            if ($product['type'] === 'variable') {
                                $wc_product = new WC_Product_Variable();
                                $wc_product->set_name($product['name']);
                                $wc_product->set_slug(implode('-', explode(' ', strtolower($product['name']))));
                                $wc_product->set_manage_stock(true);
                                $wc_product->set_stock_status('instock');
                                $wc_product->set_stock_quantity((float) $product['stock']);
                                $wc_product->set_backorders($product['backorders_allowed']);
                                $wc_product->set_sold_individually($product['sold_individually']);
                                if (count($product['attributes']) > 0) {
                                    $product_attributes = [];
                                    foreach ($product['attributes'] as $attributes) {
                                        $wc_attributes = new WC_Product_Attribute();
                                        $wc_attributes->set_name($attributes['name']);
                                        $attributes_arr = explode('| ', str_replace("'", "", $attributes['value(s)']));
                                        $wc_attributes->set_options($attributes_arr);
                                        $wc_attributes->set_visible($attributes['visible']);
                                        $wc_attributes->set_variation(true);
                                        $product_attributes[] = $wc_attributes;
                                    }
                                    $wc_product->set_attributes($product_attributes);
                                }
                                array_push($parent_product, $wc_product->save());
                            } else if ($product['type'] === 'variation') {
                                if (count($parent_product) > 0 && array_key_exists('parent', $product) && null !== $product['parent'] && $product['parent'] !== '') {
                                    try {
                                        $parent_id = $parent_product[$product['parent'] - 1];
                                        $variation = new WC_Product_Variation();
                                        $variation->set_parent_id($parent_id);
                                        if ($this->check_value_exists($product, 'regular_price')) {
                                            $variation->set_regular_price((float) $product['regular_price']);
                                        }

                                        if ($this->check_value_exists($product, 'sale_price')) {
                                            $variation->set_sale_price((float) $product['sale_price']);
                                        }

                                        if ($this->check_value_exists($product, 'name')) {
                                            $variation->set_name($product['name']);
                                        }

                                        if ($this->check_value_exists($product, 'sku')) {
                                            $variation->set_sku($product['sku']);
                                        }

                                        if ($this->check_value_exists($product, 'tax_status')) {
                                            $variation->set_tax_status($product['tax_status']);
                                        }

                                        if ($this->check_value_exists($product, 'tax_class')) {
                                            $variation->set_tax_class($product['tax_class']);
                                        }

                                        if ($this->check_value_exists($product, 'attributes')) {
                                            if (count($product['attributes']) > 0) {
                                                $attr = [];
                                                foreach ($product['attributes'] as $value) {
                                                    $attr[strtolower($value['name'])] = $value['value(s)'];
                                                }
                                                $variation->set_attributes($attr);
                                            }
                                        }

                                        $variation->save();
                                    } catch(Exception $e) {
                                        echo 'Message: ' .$e->getMessage();
                                    }
                                }
                            }
                        }
                    }
                    wp_delete_file($file_data['file']);
                } else {
                    return wp_redirect(admin_url($this->admin_page_baseurl . '&status=error&msg=file_not_found'));
                }
            } else {
                return wp_redirect(admin_url($this->admin_page_baseurl . '&status=error&msg=file_not_found'));
            }
        } else {
            return wp_redirect(admin_url($this->admin_page_baseurl . '&status=error&msg=nonce_mismatched'));
        }
        return wp_redirect(admin_url($this->admin_page_baseurl . '&status=success'));
    }
}
$GLOBALS['woocommerce-brand'] = new CSV_Variable_Product_Importer();
