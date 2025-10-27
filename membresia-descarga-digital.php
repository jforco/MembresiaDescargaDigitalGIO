<?php

/**
 * Plugin Name:       Membresía Descarga Digital - GIO 
 * Plugin URI:        
 * Description:       Gestión de membresías con descargas diarias limitadas en WooCommerce.
 * Version:           1.1.0
 * Author:            Jonathan Forco
 * Author URI:        
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       membresia-descarga-digital
 * Domain Path:       /languages
 */

if ( !defined('ABSPATH') ) {
    exit;
}

//definicion de constantes
define( 'MDD_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MDD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


// Funciones auxiliares
require_once MDD_PLUGIN_PATH . 'includes/helpers.php';

//aqui esta el tema de los campos personalizados al crear/editar productos woocomerce
require_once MDD_PLUGIN_PATH . 'includes/class-mdd-product-fields.php';

//la logica de activación de membresías
require_once MDD_PLUGIN_PATH . 'includes/class-mdd-membresia-manager.php';

//el control de descargas por día
require_once MDD_PLUGIN_PATH . 'includes/class-mdd-download-manager.php';

// Hooks centralizados
require_once MDD_PLUGIN_PATH . 'includes/class-mdd-hooks.php';  

// Para el tema del carrito y sus reglas con el producto membresia
require_once plugin_dir_path( __FILE__ ) . 'includes/class-mdd-cart-rules.php';

// Gestión de cancelación de membresías
require_once MDD_PLUGIN_PATH . 'includes/class-mdd-cancellation-manager.php';

// Gestion administrativa de usuarios
require_once MDD_PLUGIN_PATH . 'includes/class-mdd-miembros-list-table.php';

require_once MDD_PLUGIN_PATH . 'includes/class-mdd-admin.php';

require_once MDD_PLUGIN_PATH . 'includes/class-mdd-membership-actions.php';

// Al activar el plugin, forzar el registro de endpoints personalizados
register_activation_hook(__FILE__, function() {
    // Asegúrate de registrar primero el endpoint
    // add_action('init', 'mdd_registrar_endpoint_membresia', 10);
    flush_rewrite_rules(); // Este es el reinicio seguro
});