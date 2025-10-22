<?php


//Añadir nueva seccion en la pagina de "mi cuenta"
//Registrar la nueva sección "mis-descargas-membresia"
function mdd_registrar_endpoint_membresia() {
    add_rewrite_endpoint('mis-descargas-membresia', EP_ROOT | EP_PAGES);
}
add_action('init', 'mdd_registrar_endpoint_membresia');


add_filter( 'woocommerce_get_price_html', 'mdd_mostrar_precio_personalizado', 10, 2 );
function mdd_mostrar_precio_personalizado( $precio_html, $product ) {
    if ( !is_user_logged_in() ) return $precio_html;

    $user_id = get_current_user_id();
    if ( !mdd_tiene_membresia_activa($user_id)) return $precio_html;

    $descargas_usadas = mdd_get_cant_descargas_hechas_hoy($user_id);
    $product_id = $product->get_id();

    $limite = mdd_get_limite_descargas_diaria( $user_id );

    if ( $descargas_usadas < $limite && $product->is_downloadable() ) {
        //primero ver si es permitido
        if (mdd_es_producto_permitido_por_membresia($product_id)) {
            return '<span class="price">Gratis con membresia</span>';
        }
    }

    return $precio_html;
}

add_action( 'woocommerce_single_product_summary', 'mdd_mostrar_boton_descarga_directa', 35 );

function mdd_mostrar_boton_descarga_directa() {
    //si esta logueado
    if ( !is_user_logged_in() ) return;

    global $product;
    //si el producto es descargable
    if ( !$product->is_downloadable() ) return;

    $user_id = get_current_user_id();
    //si aun tiene la membresia activa
    if ( !mdd_tiene_membresia_activa( $user_id ) ) return;

    //verifica si los dias coinciden (hoy) retona las descargas hechas hoy
    $usados = mdd_get_cant_descargas_hechas_hoy($user_id);
    $product_id = $product->get_id();
    
    $ya_descargo = mdd_usuario_ya_descargo_el_producto($user_id, $product_id); //si ya lo tiene
    $usara_credito = !$ya_descargo; 
    $creditos_diario = mdd_get_limite_descargas_diaria($user_id);
    $creditos_disponibles = $creditos_diario - $usados;
    $creditos_despues_descarga = max(0, $creditos_disponibles - ($usara_credito ? 1 : 0 ));
    if ( $creditos_disponibles <= 0 && !$ya_descargo) {
        // echo '<p><strong>Has alcanzado tu limite diario de descargas.</strong></p>';
        echo '<div style="border-left: 4px solid #cc0000; padding: 10px; background-color: #fff0f0; color: #cc0000; margin-bottom: 20px;">
            <strong>Limite:</strong> Has alcanzado tu limite diario de descargas. Ya no tiene creditos por el dia de hoy
        </div>';
        return;
    }

    if ( !mdd_es_producto_permitido_por_membresia($product_id)) {
        return;
    }

    //boton de descarga
    $files = $product->get_downloads();
    if ( !empty($files) ) {
        echo '<div style="background:#ffe0b2;padding:15px;border-radius:8px;margin-top:20px;">';
         echo '<p>Descarga este producto usando <strong>' . ($usara_credito ? '1' : '0' ) . ' crédito</strong></p>';
        echo '<p><strong>Tus créditos disponibles: </strong>' . $creditos_disponibles . '</p>';
        echo '<p><strong>Créditos despues de esta descarga: </strong>' . $creditos_despues_descarga . '</p>';
        foreach ($files as $file) {
            $download_url = add_query_arg( 'mdd_descargar', $product_id, site_url() );
            echo '<a class="button alt" style="margin-top:5px;" href="' . esc_url($download_url) . '">';
            echo 'Descargar';
            echo '</a>';         
        }   
        echo '</div>';
    }
}

add_action( 'woocommerce_single_product_summary', function() {
    global $product;

    if (!mdd_es_producto_permitido_por_membresia($product->get_id()) && is_user_logged_in()) {
        echo '<div style="border-left: 4px solid #cc0000; padding: 10px; background-color: #fff0f0; color: #cc0000; margin-bottom: 20px;">
            <strong>Importante:</strong> Este producto no esta disponible para descarga con membresia
        </div>';
    }

}, 20);

// Añadir el item al menú de "Mi cuenta"
add_filter( 'woocommerce_account_menu_items', function($items) {
    $items['mis-descargas-membresia'] = 'Membresias activas';
    return $items;
});

//Mostrar el contenido en la seccion
add_action( 'woocommerce_account_mis-descargas-membresia_endpoint', 'mdd_mostrar_descargas_usuario');
