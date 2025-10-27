<?php
/**
 * Lógica para manejar la cancelación de una membresía desde la tabla WP_List_Table.
 * Esta función ahora se engancha al hook 'admin_init' para asegurar que se ejecuta 
 * lo antes posible en el flujo de administración de WordPress.
 */
function MDD_handle_cancel_membership_action() {
    // 1. Verificar si la acción solicitada es 'mdd_cancelar' Y si estamos en la página correcta.
    if ( ! isset( $_GET['page'] ) || 'mdd-gestionar-membresias' !== $_GET['page'] ) {
        return;
    }

    if ( ! isset( $_GET['action'] ) || 'mdd_cancelar' !== $_GET['action'] ) {
        return;
    }
    
    // Y verificamos que el request method sea GET (como corresponde a un enlace)
    if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
        return;
    }

    // 2. Comprobar permisos del usuario
    // Usamos 'manage_woocommerce' por ser una funcionalidad relacionada con pedidos/productos.
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( __( 'No tienes permisos para realizar esta acción.', 'membresia-descarga-digital' ) );
    }

    // 3. Obtener el ID de usuario
    $user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
    
    if ( $user_id === 0 ) {
        // Redirigir si no se proporcionó un ID de usuario válido
        wp_safe_redirect( admin_url( 'admin.php?page=mdd-gestionar-membresias&error=no_user_id' ) );
        exit;
    }

    // 4. Verificar el Nonce (seguridad)
    $nonce_action = 'mdd_cancelar_membresia_' . $user_id;
    $nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';

    if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
        wp_die( __( 'Enlace de seguridad caducado o inválido. Por favor, inténtalo de nuevo.', 'membresia-descarga-digital' ) );
    }

    // 5. Ejecutar la cancelación
    // Se verifica que la función centralizada exista antes de llamarla.
    $redirect_url = admin_url( 'admin.php?page=mdd-gestionar-membresias' );
    
    if ( function_exists( 'mdd_desactivar_membresia' ) ) {
        // Llama a la función de desactivación simple que maneja la lógica completa.
        mdd_desactivar_membresia( $user_id );
        // 6. Redirigir con mensaje de éxito
        $redirect_url = add_query_arg( 'message', 'cancel_success', $redirect_url );
    } else {
        // La función centralizada no está disponible (problema de inclusión de archivos).
        $redirect_url = add_query_arg( 'error', 'function_not_found', $redirect_url );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_init', 'MDD_handle_cancel_membership_action' );


/**
 * Muestra los mensajes de administración (éxito/error) después de una acción.
 * Se engancha a 'admin_notices'.
 */
function MDD_admin_notices_membership_actions() {
    if ( ! isset( $_GET['page'] ) || 'mdd-gestionar-membresias' !== $_GET['page'] ) {
        return;
    }

    if ( isset( $_GET['message'] ) && 'cancel_success' === $_GET['message'] ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Membresía cancelada correctamente.', 'membresia-descarga-digital' ) . '</p></div>';
    }

    if ( isset( $_GET['error'] ) && 'cancel_fail' === $_GET['error'] ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error al cancelar la membresía. El estado podría no haber cambiado.', 'membresia-descarga-digital' ) . '</p></div>';
    }
    
    if ( isset( $_GET['error'] ) && 'function_not_found' === $_GET['error'] ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error grave: La función de cancelación (mdd_desactivar_membresia) no está disponible. Verifique que el archivo de gestión de cancelación esté incluido correctamente.', 'membresia-descarga-digital' ) . '</p></div>';
    }
}
add_action( 'admin_notices', 'MDD_admin_notices_membership_actions' );
