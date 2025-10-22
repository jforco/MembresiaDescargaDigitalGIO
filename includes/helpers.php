<?php


function mdd_mostrar_info_membresia_shortcode() {
    ob_start();
    include MDD_PLUGIN_PATH . 'templates/my-membership-info.php';
    return ob_get_clean();
}

add_shortcode( 'mi_membresia', 'mdd_mostrar_info_membresia_shortcode' );

//verificar si un producto pertenece a la categoria especial
/*function mdd_is_excluded_from_membership($product_id) {
    
    // Obtiene todas las categorías del producto.
    $categorias_del_producto = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

    // Si el producto no tiene categorías, no está excluido.
    if ( empty( $categorias_del_producto ) || is_wp_error( $categorias_del_producto ) ) {
        return false;
    }

    // Recorrer cada categoría del producto.
    foreach ( $categorias_del_producto as $categoria_id ) {
        // Obtiene el metadato 'mdd_excluir_membresia' de la categoría.
        $excluido = get_term_meta( $categoria_id, 'mdd_excluir_membresia', true );

        // Si el valor es '1', significa que la categoría está excluida.
        if ( '1' === $excluido ) {
            return true;
        }
    }

    return false;
}*/

function mdd_es_producto_permitido_por_membresia( $producto_id_a_verificar) {
    $user_id = get_current_user_id();

    if ( $user_id === 0 ) {
        return false;
    }

    $membresia_plan_id = get_user_meta( $user_id, '_mdd_producto_id', true );

    if ( empty( $membresia_plan_id ) ) {
        return false;
    }
    
    $categorias_permitidas = get_post_meta( $membresia_plan_id, '_mdd_categorias_permitidas', true );

    if ( empty( $categorias_permitidas ) || ! is_array( $categorias_permitidas ) ) {
        return false;
    }

    $categorias_del_producto = wp_get_post_terms( 
        $producto_id_a_verificar, 
        'product_cat', 
        array( 'fields' => 'ids' ) 
    );
    
    if ( empty( $categorias_del_producto ) || is_wp_error( $categorias_del_producto ) ) {
        return false;
    }

    $categorias_en_comun = array_intersect( (array) $categorias_del_producto, $categorias_permitidas );

    if ( ! empty( $categorias_en_comun ) ) {
        return true;
    }

    return false;
}

//Verifica si la membresía del usuario está activa y no expirada
function mdd_tiene_membresia_activa( $user_id ) {
    $activa = get_user_meta( $user_id, '_mdd_membresia_activa', true);
    $expiracion = get_user_meta( $user_id, '_mdd_membresia_expiracion', true );

    return ( $activa && $expiracion && current_time('timestamp') < $expiracion );
}

//verificar si el usuario ya descargo el producto en el pasado, se basa en la lista de descargados que ya tiene el usuario
function mdd_usuario_ya_descargo_el_producto( $user_id, $product_id ) {
    //obtener lista de sus productos descargados
    $descargados = get_user_meta($user_id, '_mdd_productos_descargados', true);

    if (!is_array($descargados)) {
        $descargados = []; //se incializa como vacio
    }

    //true si el producto ya esta en la lista
    return in_array($product_id, $descargados);
}

//registrar producto descargado
function mdd_registrar_producto_descargado($user_id, $product_id) {
    $descargados = get_user_meta($user_id, '_mdd_productos_descargados', true);

    if ( !is_array($descargados)) {
        $descargados = [];
    }

    if ( !in_array($product_id, $descargados)) {
        $descargados[] = $product_id;
        update_user_meta( $user_id, '_mdd_productos_descargados', $descargados);
    }
}

//Muestra la infot de la  membresía del usuario y la cant de prod descargados
function mdd_mostrar_descargas_usuario() {

    $user_id = get_current_user_id();
    if (!$user_id) {
        echo '<p>Debes iniciar sesion para ver tus descargas</p>';
        return;
    }
    
    if ( !mdd_tiene_membresia_activa($user_id) ) {
        echo '<h5>Aun no tienes una membresia Activa</h5>';
        return;
    }
    
    $membresia_id = get_user_meta( $user_id, '_mdd_producto_id', true );
    $datos_producto = wc_get_product( $membresia_id );

    //Aqui empieza si el usuario paso todas las validaciones

    $fecha_expiracion = get_user_meta( $user_id, '_mdd_membresia_expiracion', true );
    $fecha_imprimible  = wp_date( 'd/m/Y', intval($fecha_expiracion) );
    
    $limite_diario = get_post_meta($membresia_id, '_mdd_descargas_por_dia', true);
    $nombre_membresia = $datos_producto->get_name();

    echo '<p>Tu membresia: <strong>'. $nombre_membresia .'</strong> está activa <br>Limite de descargas diarias: <strong>' . $limite_diario . '</strong><br>Duracion hasta el: <strong>' . $fecha_imprimible. '</strong></p>';

    $product_ids = get_user_meta($user_id, '_mdd_productos_descargados', true);

    if (!is_array($product_ids) || empty($product_ids)) {
        echo '<p>No has descargado productos usando tu membresía aún.</p>';
        return;
    }else {
        echo '<h3>Productos que ya descargaste con tu membresía: ' . count($product_ids ) . '</h3>';
    }

}

//verifica si los dias coinciden (hoy) retona las descargas hechas hoy
function mdd_get_cant_descargas_hechas_hoy( $user_id ) {
    $fecha_hoy = current_time('Y-m-d');
    ////_mdd_descargas_fecha guarda la fecha del último reinicio de contador de descargas
    $fecha_guardada = get_user_meta( $user_id, '_mdd_descargas_fecha', true );
    
    if ( $fecha_hoy !== $fecha_guardada ) {
        return 0;
    }

    return (int) get_user_meta( $user_id, '_mdd_descargas_hechas_hoy', true );
}

//verificada!
function mdd_get_limite_descargas_diaria( $user_id ) {
    $membresia_id = get_user_meta( $user_id, '_mdd_producto_id', true ); //la membresia
    $limite = (int) get_post_meta( $membresia_id, '_mdd_descargas_por_dia', true ); //retorna correctamente las descargas x dia
    return ($limite > 0) ? $limite : 0;
}

function get_productos_descargados_hoy($user_id) {
    $hoy = current_time('Y-m-d');
    $descargas = get_user_meta( $user_id, '_mdd_productos_descargados_' . $hoy, true );
    
    return is_array($descargas) ? $descargas : [];
}

//Verifica si el producto es de categoria membresia (trabaja en el slug), sin importar como lo haya escrito el usuario en el slug
//Ejeplo: Membresia, MEMBRESIAS, Membresias digitales. Si contiene membresia en cualquier forma = True
function mdd_producto_es_membresia( $product_id ) {
    $terms = get_the_terms( $product_id, 'product_cat' );
    if ( empty($terms) || is_wp_error($terms) ) {
        return false;
    }

    foreach ( $terms as $term ) {
        $slug = strtolower( $term->slug ); //a minusculas
        // $name = strtolower( $term->name );

        // Validamos si hay "membresia" en el texto
        if ( strpos( $slug, 'membresia' ) !== false ) 
            return true;
    }
    return false;
}
