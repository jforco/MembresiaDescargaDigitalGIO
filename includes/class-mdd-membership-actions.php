<?php

add_action( 'admin_init', 'MDD_handle_membership_actions' );

function MDD_handle_membership_actions() {
	// 1. Validar la página y el método de solicitud
	if ( ! isset( $_GET['page'] ) || 'mdd-gestionar-membresias' !== $_GET['page'] ) {
		return;
	}
	
	// El usuario debe tener permisos suficientes para gestionar WooCommerce/Opciones.
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';
	$redirect_url = admin_url( 'admin.php?page=mdd-gestionar-membresias' );

	// ---------------------------------------------------------------------
	// A. Lógica para la CANCELACIÓN (Acción enviada por GET desde un enlace)
	// ---------------------------------------------------------------------
	if ( $action === 'mdd_cancelar' && $_SERVER['REQUEST_METHOD'] === 'GET' ) {
		
		$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		$nonce_action = 'mdd_cancelar_membresia_' . $user_id;
		$nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';
		
		if ( $user_id === 0 ) {
			 $redirect_url = add_query_arg( 'error', 'no_user_id', $redirect_url );
		} elseif ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			// Usamos wp_die() para nonces fallidos en GET, ya que es una acción crítica.
			wp_die( esc_html__( 'Enlace de seguridad caducado o inválido. Por favor, inténtalo de nuevo.', 'membresia-descarga-digital' ) );
		} else {
			// Ejecutar la cancelación.
			$result = mdd_cancelar_membresia( $user_id );
			
			if ( is_wp_error( $result ) ) {
				 $redirect_url = add_query_arg( 'error', 'cancel_fail', $redirect_url );
			} else {
				 $redirect_url = add_query_arg( 'message', 'cancel_success', $redirect_url );
			}
		}
	}

	// ---------------------------------------------------------------------
	// B. Lógica para MODIFICAR FECHA DE EXPIRACIÓN (Acción enviada por POST desde un formulario)
	// Se reestructura ligeramente la lógica de validación para un flujo POST más claro.
	// ---------------------------------------------------------------------
	if ( $action === 'mdd_modificar_expiracion' && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
		
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$new_date = isset( $_POST['mdd_expiracion_date'] ) ? sanitize_text_field( $_POST['mdd_expiracion_date'] ) : '';
		$nonce_action = 'mdd_guardar_expiracion_' . $user_id; // Nonce específico para guardar
		$nonce = isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : '';
		
		// 2. Verificar Nonce primero (seguridad es prioridad)
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_die( esc_html__( 'Token de seguridad inválido. Por favor, inténtalo de nuevo.', 'membresia-descarga-digital' ) );
		}

		// 3. Verificar datos
		if ( $user_id === 0 || empty( $new_date ) ) {
			$redirect_url = add_query_arg( 'error', 'invalid_data', $redirect_url );
		} else {
			// Convertir la fecha YYYY-MM-DD a timestamp de Unix
			$new_timestamp = strtotime( $new_date );

			if ( $new_timestamp === false ) {
				$redirect_url = add_query_arg( 'error', 'invalid_date_format', $redirect_url );
			} else {
				// Ejecutar la actualización.
				$result = mdd_actualizar_fecha_expiracion( $user_id, $new_timestamp );
				
				if ( is_wp_error( $result ) ) {
					$redirect_url = add_query_arg( 'error', 'update_fail', $redirect_url );
				} else {
					$redirect_url = add_query_arg( 'message', 'update_success', $redirect_url );
				}
			}
		}
	}
	
	// Si alguna acción se ejecutó y modificó la URL, redirigimos.
	if ( in_array( $action, ['mdd_cancelar', 'mdd_modificar_expiracion'] ) ) {
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}
}

function mdd_cancelar_membresia( $user_id ) {
	// 1. Marcar como inactiva
	$updated = update_user_meta( $user_id, '_mdd_membresia_activa', '0' );
	
	// 2. Establecer la fecha de expiración al pasado para asegurar la desactivación inmediata.
	$date_updated = mdd_actualizar_fecha_expiracion( $user_id, current_time( 'timestamp' ) - 1 );
	
	if ( $updated || ! is_wp_error( $date_updated ) ) {
		// Retorna TRUE si se actualizó el estado o si la fecha se actualizó correctamente.
		return true; 
	} else {
		return new WP_Error( 'cancel_failed', esc_html__( 'La membresía no pudo ser cancelada, el estado ya era inactivo o no se pudo actualizar la meta data.', 'membresia-descarga-digital' ) );
	}
}


function mdd_actualizar_fecha_expiracion( $user_id, $new_timestamp ) {
	// Aseguramos que el usuario no esté activo si la fecha es en el pasado.
	if ( $new_timestamp < current_time( 'timestamp' ) ) {
		update_user_meta( $user_id, '_mdd_membresia_activa', '0' );
	} else {
		 // Si la fecha es futura, asegurar que la membresía esté activa.
		update_user_meta( $user_id, '_mdd_membresia_activa', '1' );
	}

	$updated = update_user_meta( $user_id, '_mdd_membresia_expiracion', $new_timestamp );
	
	if ( $updated ) {
		return true;
	} else {
		return new WP_Error( 'update_failed', esc_html__( 'La fecha de expiración no pudo ser actualizada o es el mismo valor.', 'membresia-descarga-digital' ) );
	}
}


add_action( 'admin_notices', 'MDD_admin_notices_membership_actions' );

function MDD_admin_notices_membership_actions() {
	if ( ! isset( $_GET['page'] ) || 'mdd-gestionar-membresias' !== $_GET['page'] ) {
		return;
	}

	// --- Mensajes de Cancelación (Originales) ---
	if ( isset( $_GET['message'] ) && 'cancel_success' === $_GET['message'] ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Membresía cancelada correctamente.', 'membresia-descarga-digital' ) . '</p></div>';
	}
	if ( isset( $_GET['error'] ) && 'cancel_fail' === $_GET['error'] ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error al cancelar la membresía. El estado podría no haber cambiado.', 'membresia-descarga-digital' ) . '</p></div>';
	}

	// --- Mensajes de Modificación de Expiración (NUEVOS) ---
	if ( isset( $_GET['message'] ) && 'update_success' === $_GET['message'] ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Fecha de expiración actualizada correctamente.', 'membresia-descarga-digital' ) . '</p></div>';
	}
	if ( isset( $_GET['error'] ) && 'update_fail' === $_GET['error'] ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error al actualizar la fecha de expiración.', 'membresia-descarga-digital' ) . '</p></div>';
	}
	if ( isset( $_GET['error'] ) && 'invalid_date_format' === $_GET['error'] ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error: El formato de fecha proporcionado no es válido.', 'membresia-descarga-digital' ) . '</p></div>';
	}

	// --- Mensajes Generales ---
	if ( isset( $_GET['error'] ) && 'no_user_id' === $_GET['error'] ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error: No se proporcionó un ID de usuario válido.', 'membresia-descarga-digital' ) . '</p></div>';
	}
	if ( isset( $_GET['error'] ) && 'invalid_data' === $_GET['error'] ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error: Datos de formulario incompletos o inválidos.', 'membresia-descarga-digital' ) . '</p></div>';
	}
}