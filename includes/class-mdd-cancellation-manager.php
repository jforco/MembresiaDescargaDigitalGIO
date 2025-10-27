<?php
//Para desactivar membresías cuando: un pedido es cancelado o es reembolsado

if ( !defined('ABSPATH') ) {
  exit; 
}

//se ejecuta cuando el estado del pedido cambia a cancelled
add_action( 'woocommerce_order_status_cancelled', 'mdd_desactivar_membresia_por_cancelacion' );
//se ejecuta cuando el estado del pedido cambia a refunded
add_action( 'woocommerce_order_status_refunded', 'mdd_desactivar_membresia_por_cancelacion' );


//Desactivar la membresía del usuario cuando se cancela un pedido 
function mdd_desactivar_membresia_por_cancelacion( $order_id ) { //id del pedido cancelado

    $order = wc_get_order( $order_id );
    if ( !$order ) return; //si no existe el pedido entoncs return 

    $user_id = $order->get_user_id();
    if ( !$user_id )  return; // Salir si no hay usuario

    $contiene_membresia = false;
    $productos_membresia = array(); // Guardar los IDs de productos de membresía

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();

        $duracion_meses = (int) get_post_meta( $product_id, '_mdd_duracion_membresia', true );
 
        if ( $duracion_meses > 0 ) {
            $contiene_membresia = true;
            $productos_membresia[] = $product_id;
        }
    }

    if ( !$contiene_membresia ) { //si no hay productos de membresia no hacer nada
        return;
    }

    mdd_revertir_membresia_usuario( $user_id, $productos_membresia, $order_id );
}

/**
 * Función principal y simple para desactivar una membresía activa y limpiar metadatos.
 * Esta función es ideal para ser llamada directamente desde el Admin (sin contexto de Orden).
 *
 * @param int $user_id ID del usuario.
 * @param string $reason Razón para el log (e.g., 'Pedido Cancelado', 'Admin Cancelación').
 */
function mdd_desactivar_membresia( $user_id, $reason = 'Acción Administrativa Manual' ) {
    
    // 1. Obtener datos actuales de la membresía para el log
    $membresia_activa = get_user_meta( $user_id, '_mdd_membresia_activa', true );
    if ( !$membresia_activa ) {
        error_log( sprintf( '[MDD Desactivación] Usuario %d: Intento de desactivación, pero la membresía ya estaba inactiva. Razón: %s', $user_id, $reason ) );
        return; 
    }

    // Desactivar la membresía (método: poner fecha de expiración en el pasado)
    $ayer = current_time( 'timestamp' ) - (24 * 60 * 60); // Fecha de ayer (24 horas en segundos)
    
    // 2. Actualizar metadatos del usuario
    update_user_meta( $user_id, '_mdd_membresia_activa', false );
    update_user_meta( $user_id, '_mdd_membresia_expiracion', $ayer );
    
    // 3. Limpiar historial de descargas del usuario
    delete_user_meta( $user_id, '_mdd_productos_descargados' );
    delete_user_meta( $user_id, '_mdd_descargas_hechas_hoy' );
    delete_user_meta( $user_id, '_mdd_descargas_fecha' );

    // 4. Log de la acción
    error_log( sprintf( '[MDD Desactivación] Membresía desactivada para usuario %d. Razón: %s', $user_id, $reason ) );

    // 5. Hook personalizado para extender funcionalidad
    do_action( 'mdd_membresia_desactivada', $user_id, $reason );
}


/**
 * Revierte la membresía del usuario, desactivándola y limpiando su historial
 * ESTA FUNCIÓN AHORA SOLO SE USA PARA EL CONTEXTO DE CANCELACIÓN/REEMBOLSO DE PEDIDOS
 *
 * @param int $user_id ID del usuario
 * @param array $productos_membresia Array con IDs de productos de membresía del pedido
 * @param int $order_id ID del pedido cancelado o reembolsado
 */
function mdd_revertir_membresia_usuario( $user_id, $productos_membresia, $order_id ) {
    
    // 1. Obtener datos actuales de la membresía
    $membresia_activa = get_user_meta( $user_id, '_mdd_membresia_activa', true );
    $producto_membresia_actual = get_user_meta( $user_id, '_mdd_producto_id', true );

    if ( !$membresia_activa ) return; 
    
    // 2. Verificar si la membresía actual corresponde a alguno de los productos cancelados
    // Esta verificación es CRUCIAL en el contexto de un pedido, para no desactivar 
    // una membresía comprada en un pedido A si el pedido B (que no la contiene) es cancelado.
    if ( !in_array( $producto_membresia_actual, $productos_membresia ) ) {
        return; 
    }

    // 3. Llamar a la función de desactivación simple y pasar el contexto al log
    $reason = sprintf( 'Cancelación del Pedido #%d', $order_id );
    mdd_desactivar_membresia( $user_id, $reason );

    // No se necesita log ni limpieza de metadatos aquí, lo hace mdd_desactivar_membresia
    // No se necesita el do_action, lo hace mdd_desactivar_membresia
}
