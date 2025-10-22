<?php


if ( !defined('ABSPATH') ) {
    exit;
}

//Hook: cuando una orden se marca como completada
add_action( 'woocommerce_order_status_completed', 'mdd_activar_membresia_usuario' );

//Activar una membresía para el usuario cuando la compra
function mdd_activar_membresia_usuario( $order_id ) {

    $order = wc_get_order( $order_id );
    if ( !$order ) return;

    $user_id = $order->get_user_id();
    if ( !$user_id ) return;

    foreach ($order->get_items() as $item) {

        $product_id = $item->get_product_id();
        $duracion_meses = (int) get_post_meta( $product_id, '_mdd_duracion_membresia', true );
        
        if ( $duracion_meses <= 0 ) continue;

        //obtener la expiración actual
        $expiracion_actual = (int) get_user_meta( $user_id, '_mdd_membresia_expiracion', true );
        //calcular desde cuándo sumar: si la membresía está activa, sumar desde la expiración actual; si no, desde hoy
        $ahora = current_time( 'timestamp' );
        $desde = ($expiracion_actual && ($expiracion_actual > $ahora)) ? $expiracion_actual : $ahora;

        //sumar duración
        $nueva_expiracion = strtotime("+$duracion_meses months", $desde);
        
        //guardar datos en user_meta
        update_user_meta( $user_id, '_mdd_membresia_activa', true );
        update_user_meta( $user_id, '_mdd_membresia_inicio', $ahora );
        update_user_meta( $user_id, '_mdd_membresia_expiracion', $nueva_expiracion );
        update_user_meta( $user_id, '_mdd_duracion_membresia', $duracion_meses );
        update_user_meta( $user_id, '_mdd_producto_id', $product_id );

        break; //solo procesamos uno
    } 
}



