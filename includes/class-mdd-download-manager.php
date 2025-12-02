<?php

//Control de descargas para usuarios con membresia

if ( !defined('ABSPATH') ) {
    exit;
}

/**
 * Intercepta la solicitud de descarga y procesa la lógica según el tipo de producto.
 * Hooked to 'init'.
 */
function mdd_interceptar_descarga_producto() {

    $user_id = get_current_user_id();
    if (!$user_id)  wp_die( 'Debes iniciar sesion.'); 
        
    $producto_id = intval($_GET['mdd_descargar']);
    if (!$producto_id)  wp_die( 'Producto no válido.');

    if ( function_exists('mdd_es_producto_permitido_por_membresia') 
        && !mdd_es_producto_permitido_por_membresia($producto_id)) 
    {
        wp_die( 'Este producto no está disponible para descargar mediante membresia');
    }
    
    if ( !mdd_tiene_membresia_activa($user_id) )    wp_die( 'Tu membresia no está activa');
    
    $limite = mdd_get_limite_descargas_diaria($user_id); 
    $usados = mdd_get_cant_descargas_hechas_hoy($user_id);
    $ya_descargo = mdd_usuario_ya_descargo_el_producto($user_id, $producto_id);
    
    if (!$ya_descargo && $usados >= $limite) {
        wp_die( 'Has alcanzado tu limite de descargas por hoy.');
    }

    //Registrar descarga si nunca lo descargo y hay credito
    if (!$ya_descargo) {
        
        $fecha_hoy = current_time('Y-m-d');
        $fecha_guardada = get_user_meta( $user_id, '_mdd_descargas_fecha', true );
        
        if ( $fecha_guardada !== $fecha_hoy ) {
            $usados = 0;
        }
        
        $usados++;
        update_user_meta( $user_id, '_mdd_descargas_fecha', $fecha_hoy);
        update_user_meta( $user_id, '_mdd_descargas_hechas_hoy', $usados);
        
        mdd_registrar_producto_descargado($user_id, $producto_id);
    }

    // Obtener el objeto producto
    $producto = wc_get_product($producto_id);
    
    // 1. Intentar obtener archivos nativos de WooCommerce
    $files = $producto->get_downloads();

    if (!empty($files)) {
        $file = array_shift($files);
        $file_path = $file['file'];

        $relative_path = str_replace( site_url('/'), ABSPATH, $file_path );
        $real_path = realpath( $relative_path );

        if ( !$real_path || !file_exists($real_path) ) {
            wp_die('El archivo no existe en el servidor.');
        }

        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($real_path) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($real_path));
        flush();
        readfile($real_path);

        exit;
    }

    // 2. Lógica para URL de Descarga Externa
    $external_url = $producto->get_meta( '_mdd_url_descarga_externa' );
    
    if ( ! empty( $external_url ) ) {
        wp_redirect( $external_url );
        exit;
    }

    wp_die('No hay archivos disponibles.');
}

add_action( 'init', 'mdd_iniciar_interceptor_descargas' );

function mdd_iniciar_interceptor_descargas() {
    if ( isset($_GET['mdd_descargar']) ) {
        mdd_interceptar_descarga_producto();
    }
}


// --- LÓGICA DE VISUALIZACIÓN EN MI CUENTA (Historial) ---
add_filter( 'woocommerce_customer_get_downloadable_products', 'mdd_inject_external_downloads_my_account', 10, 1 );

function mdd_inject_external_downloads_my_account( $downloads ) {
    if ( ! is_user_logged_in() ) {
        return $downloads;
    }

    $orders = wc_get_orders( array(
        'customer' => get_current_user_id(),
        'limit'    => -1,
        'status'   => array( 'completed', 'processing' ),
    ) );

    foreach ( $orders as $order ) {
        foreach ( $order->get_items() as $item ) {
            if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) continue;
            
            $product = $item->get_product();
            if ( ! $product ) continue;

            if ( $product->get_downloads() ) continue;

            $external_url = $product->get_meta( '_mdd_url_descarga_externa' );
            if ( ! empty( $external_url ) ) {
                $downloads[] = array(
                    'download_url' => $external_url,
                    'download_name' => $product->get_name(),
                    'product_name' => $product->get_name(),
                    'product_url'  => $product->get_permalink(),
                    'downloads_remaining' => '∞',
                    'access_expires' => null,
                    'file' => array( 'file' => $external_url, 'name' => $product->get_name() )
                );
            }
        }
    }
    return $downloads;
}

/**
 * ESTRATEGIA NUCLEAR: CSS + REMOVE ACTION
 * 1. Ocultamos visualmente la tabla nativa usando CSS específico.
 * 2. Intentamos remover la acción nativa en 'template_redirect'.
 */

// A. CSS para ocultar duplicados
add_action( 'wp_head', 'mdd_force_hide_native_downloads_css' );

function mdd_force_hide_native_downloads_css() {
    if ( is_order_received_page() || is_view_order_page() ) {
        ?>
        <style>
            /* Ocultar cualquier sección de descargas de WC que NO sea la nuestra */
            section.woocommerce-order-downloads:not(.mdd-custom-table) {
                display: none !important;
            }
        </style>
        <?php
    }
}

// B. Remover acción y añadir la nuestra
add_action( 'template_redirect', 'mdd_manage_order_downloads_table_hooks' );

function mdd_manage_order_downloads_table_hooks() {
    // Solo actuamos en las páginas de recibo/orden
    if ( is_order_received_page() || is_view_order_page() ) {
        // Intentamos remover la tabla nativa
        remove_action( 'woocommerce_order_details_before_order_table', 'woocommerce_order_downloads_table', 10 );
        
        // Añadimos nuestra tabla
        add_action( 'woocommerce_order_details_before_order_table', 'mdd_render_custom_order_downloads_table', 10, 1 );
    }
}

function mdd_render_custom_order_downloads_table( $order ) {
    
    if ( ! $order || ! $order->is_paid() ) {
        return;
    }

    $downloads_to_show = array();

    foreach ( $order->get_items() as $item ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) continue;
        
        $product = $item->get_product();
        if ( ! $product ) continue;

        $native_files = $product->get_downloads();
        $external_url = $product->get_meta( '_mdd_url_descarga_externa' );

        // CASO 1: Archivos Nativos
        if ( ! empty( $native_files ) ) {
            $file = reset( $native_files );
            $file_url = $file['file'];
            $file_name = $file['name'];
            
            $downloads_to_show[] = array(
                'name' => $product->get_name() . ' - ' . $file_name,
                'url'  => $file_url,
                'is_external' => false
            );
        }
        // CASO 2: URL Externa
        elseif ( ! empty( $external_url ) ) {
            $downloads_to_show[] = array(
                'name' => $product->get_name(),
                'url'  => $external_url,
                'is_external' => true
            );
        }
    }

    if ( empty( $downloads_to_show ) ) {
        return;
    }

    // AÑADIMOS LA CLASE 'mdd-custom-table' PARA QUE EL CSS LA RESPETE
    ?>
    <section class="woocommerce-order-downloads mdd-custom-table">
        <h2 class="woocommerce-order-downloads__title"><?php esc_html_e( 'Descargas', 'woocommerce' ); ?></h2>
        
        <table class="woocommerce-table woocommerce-table--order-downloads shop_table shop_table_responsive order_details">
            <thead>
                <tr>
                    <th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Producto', 'woocommerce' ); ?></th>
                    <th class="woocommerce-table__product-table download-remaining"><?php esc_html_e( 'Descargas restantes', 'woocommerce' ); ?></th>
                    <th class="woocommerce-table__product-expires download-expires"><?php esc_html_e( 'Caduca', 'woocommerce' ); ?></th>
                    <th class="woocommerce-table__product-download download-file"><span class="nobr"><?php esc_html_e( 'Descargar', 'woocommerce' ); ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $downloads_to_show as $download ) : ?>
                    <tr>
                        <td class="woocommerce-table__product-name product-name" data-title="<?php esc_attr_e( 'Producto', 'woocommerce' ); ?>">
                            <?php echo esc_html( $download['name'] ); ?>
                        </td>
                        <td class="woocommerce-table__product-table download-remaining" data-title="<?php esc_attr_e( 'Descargas restantes', 'woocommerce' ); ?>">
                            &infin;
                        </td>
                        <td class="woocommerce-table__product-expires download-expires" data-title="<?php esc_attr_e( 'Caduca', 'woocommerce' ); ?>">
                            <?php esc_html_e( 'Nunca', 'woocommerce' ); ?>
                        </td>
                        <td class="woocommerce-table__product-download download-file" data-title="<?php esc_attr_e( 'Descargar', 'woocommerce' ); ?>">
                            <a href="<?php echo esc_url( $download['url'] ); ?>" 
                               class="woocommerce-button button" 
                               <?php echo $download['is_external'] ? 'target="_blank"' : ''; ?>>
                                <?php echo esc_html( $download['name'] ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
}