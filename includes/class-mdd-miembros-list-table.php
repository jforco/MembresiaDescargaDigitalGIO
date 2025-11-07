<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class MDD_Miembros_List_Table extends WP_List_Table {

    // **NOTA:** Aquí vendrá todo el código para definir columnas, datos y filtros.
    
    /**
     * Definición de las columnas de la tabla.
     */
    public function get_columns() {
        $columns = array(
            'usuario'          => __( 'Usuario', 'membresia-descarga-digital' ), // Columna principal para acciones
            'user_email'       => __( 'Email', 'membresia-descarga-digital' ),
            'estado'           => __( 'Estado', 'membresia-descarga-digital' ),
            'plan_nombre'      => __( 'Nombre del Plan', 'membresia-descarga-digital' ),
            'fecha_inicio'     => __( 'Inicio', 'membresia-descarga-digital' ),
            'fecha_expiracion' => __( 'Expiración', 'membresia-descarga-digital' ),
            'descargas_total'  => __( 'Descargas Totales', 'membresia-descarga-digital' ),
            'acciones'         => __( 'Acciones', 'membresia-descarga-digital' ),
        );
        return $columns;
    }
    
    protected function column_usuario( $item ) {
        // Mostrar el nombre del usuario y un enlace a su perfil
        return sprintf( '<strong><a href="%s">%s</a></strong>',
            esc_url( get_edit_user_link( $item['id'] ) ),
            esc_html( $item['display_name'] )
        );
    }

    protected function column_acciones( $item ) {
        // Enlaces base, apuntando a la misma página administrativa (admin.php?page=mdd-gestionar-membresias)
        $base_url = admin_url('admin.php?page=mdd-gestionar-membresias');
        
        // Enlace/Botón de MODIFICAR MEMBRESÍA
        // Esto sigue apuntando a la acción 'mdd_editar_expiracion' que renderiza el formulario de edición.
        $edit_url = add_query_arg( 
            array( 
                'action' => 'mdd_editar_membresia', 
                'user' => $item['id'] 
            ), 
            $base_url 
        );
        
        $edit_button = sprintf( 
            // Usamos button-primary para el CTA principal
            '<a href="%s" class="button button-primary">%s</a>', 
            esc_url( $edit_url ), 
            __( 'Modificar', 'membresia-descarga-digital' ) // Texto unificado
        );

        // Devolvemos solo el botón de edición
        return $edit_button;
    }

    protected function column_default( $item, $column_name ) {
        if ( isset( $item[ $column_name ] ) ) {
            return $item[ $column_name ];
        }
        return '—'; 
    }


    public function get_sortable_columns() {
        $sortable_columns = array(
            // El formato es 'slug_de_columna' => array('campo_a_ordenar', 'asc/desc')
            'usuario'          => array( 'display_name', false ),
            'email'            => array( 'user_email', false ),
            'fecha_inicio'     => array( 'mdd_membresia_inicio', false ),
            'fecha_expiracion' => array( 'mdd_membresia_expiracion', false ),
        );
        return $sortable_columns;
    }

    /**
     * Prepara los elementos (filas) para que se muestren en la tabla.
     */
    public function prepare_items() {
        global $wpdb;
        $per_page = 20; // Número de elementos por página
        
        // 1. Obtener los parámetros de ordenamiento y paginación
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'user_registered';
        $order = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
        $paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $offset = ( $paged - 1 ) * $per_page;
        
        $current_time = current_time('timestamp');
        $where_clauses = " WHERE 1=1 "; // Inicializamos la cláusula WHERE

        // 2. Aplicar Búsqueda (s) por Usuario o Email
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        if ( ! empty( $search ) ) {
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $where_clauses .= $wpdb->prepare( " AND (u.display_name LIKE %s OR u.user_email LIKE %s) ", $search_like, $search_like );
        }

        // 3. Aplicar Filtros Desplegables

        // Filtro por PLAN (mdd_filter_plan)
        $plan_filter = isset( $_GET['mdd_filter_plan'] ) ? absint( $_GET['mdd_filter_plan'] ) : 0;
        if ( $plan_filter > 0 ) {
            $where_clauses .= $wpdb->prepare( " AND um_plan.meta_value = %s ", $plan_filter );
        }

        // Filtro por ESTADO (mdd_filter_estado)
        $state_filter = isset( $_GET['mdd_filter_estado'] ) ? sanitize_text_field( $_GET['mdd_filter_estado'] ) : '';
        if ( ! empty( $state_filter ) ) {
            switch ( $state_filter ) {
                case 'active':
                    // Activa: Bandera '1' Y expiración en el futuro
                    $where_clauses .= $wpdb->prepare( " AND um_active.meta_value = '1' AND um_expiracion.meta_value > %d ", $current_time );
                    break;
                case 'inactive':
                    // Inactiva: Bandera '0' (o cualquier otro estado que no sea activo/expirado)
                    $where_clauses .= " AND um_active.meta_value = '0' ";
                    break;
            }
        }
        
        // Filtro por EXPIRACIÓN (mdd_filter_expiracion)
        $expiration_filter = isset( $_GET['mdd_filter_expiracion'] ) ? sanitize_text_field( $_GET['mdd_filter_expiracion'] ) : '';
        if ( ! empty( $expiration_filter ) ) {
            $end_of_period = 0;
            if ( 'this_week' === $expiration_filter ) {
                $end_of_period = strtotime( '+7 days' ); 
            } elseif ( 'this_month' === $expiration_filter ) {
                // Sumamos el offset para incluir todo el último día del mes
                $end_of_period = strtotime( '+30 days' ); 
            }
            
            // La fecha debe estar en el futuro, pero antes del final del periodo
            if ( $end_of_period > 0 ) {
                $where_clauses .= $wpdb->prepare( " AND um_expiracion.meta_value > %d AND um_expiracion.meta_value <= %d ", $current_time, $end_of_period );
            }
        }
        
        // 4. Definir la consulta base (termina en JOINs)
        $sql = "
            SELECT 
                u.ID, 
                u.display_name, 
                u.user_email,
                -- Metadatos del usuario. Usamos aliases claros
                MAX(CASE WHEN um_active.meta_key = '_mdd_membresia_activa' THEN um_active.meta_value ELSE NULL END) AS mdd_membresia_activa,
                MAX(CASE WHEN um_plan.meta_key = '_mdd_producto_id' THEN um_plan.meta_value ELSE NULL END) AS mdd_producto_id,
                MAX(CASE WHEN um_inicio.meta_key = '_mdd_membresia_inicio' THEN um_inicio.meta_value ELSE NULL END) AS mdd_membresia_inicio,
                MAX(CASE WHEN um_expiracion.meta_key = '_mdd_membresia_expiracion' THEN um_expiracion.meta_value ELSE NULL END) AS mdd_membresia_expiracion,
                MAX(CASE WHEN um_descargas.meta_key = '_mdd_productos_descargados' THEN um_descargas.meta_value ELSE NULL END) AS mdd_productos_descargados
            FROM 
                {$wpdb->users} AS u
            INNER JOIN 
                {$wpdb->usermeta} AS um_active ON (u.ID = um_active.user_id AND um_active.meta_key = '_mdd_membresia_activa')
            LEFT JOIN 
                {$wpdb->usermeta} AS um_plan ON (u.ID = um_plan.user_id AND um_plan.meta_key = '_mdd_producto_id')
            LEFT JOIN 
                {$wpdb->usermeta} AS um_inicio ON (u.ID = um_inicio.user_id AND um_inicio.meta_key = '_mdd_membresia_inicio')
            LEFT JOIN 
                {$wpdb->usermeta} AS um_expiracion ON (u.ID = um_expiracion.user_id AND um_expiracion.meta_key = '_mdd_membresia_expiracion')
            LEFT JOIN 
                {$wpdb->usermeta} AS um_descargas ON (u.ID = um_descargas.user_id AND um_descargas.meta_key = '_mdd_productos_descargados')
        ";

        // AÑADIR TODAS LAS CLÁUSULAS WHERE AQUÍ
        $sql .= $where_clauses;
        
        // 5. Aplicar GROUP BY (esto debe ir después de todas las condiciones WHERE)
        $sql .= " GROUP BY u.ID ";
        
        // 6. Aplicar Ordenamiento
        if ( in_array( $orderby, array( 'display_name', 'user_email', 'mdd_membresia_inicio', 'mdd_membresia_expiracion' ) ) ) {
            $sql .= " ORDER BY {$orderby} " . ( strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC' );
        }
        
        // 7. Aplicar Paginación
        $sql_limit = $sql . " LIMIT %d OFFSET %d";

        // Obtener el total de resultados para la paginación (usando $sql antes del LIMIT/OFFSET)
        $total_items = count( $wpdb->get_results( $sql, ARRAY_A ) );
        
        // Obtener los datos paginados
        $data = $wpdb->get_results( $wpdb->prepare( $sql_limit, $per_page, $offset ), ARRAY_A );

        // 8. Mapear y Renderizar Datos
        $this->items = array_map( array( $this, 'mdd_format_item_data' ), $data );
        
        // 9. Configurar la paginación
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ) );
        
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
    }

    /**
     * Formatea y decodifica los datos de cada fila antes de renderizar.
     */
    protected function mdd_format_item_data( $item ) {
        // Renombramos la ID para que sea más fácil de usar
        $item['id'] = $item['ID'];
        
        // Decodificar el array de productos descargados (que fue guardado serializado)
        $descargados_array = maybe_unserialize( $item['mdd_productos_descargados'] );
        $item['descargas_total'] = is_array( $descargados_array ) ? count( $descargados_array ) : 0;
        
        // Obtener el nombre del plan (producto)
        $item['plan_nombre'] = get_the_title( $item['mdd_producto_id'] );
        
        // Determinar el Estado (Activo/Inactivo)
        $item['estado'] = $this->mdd_get_membership_status( $item );
        
        // Formatear fechas
        $item['fecha_inicio'] = ! empty( $item['mdd_membresia_inicio'] ) ? date_i18n( get_option( 'date_format' ), $item['mdd_membresia_inicio'] ) : '—';
        
        // --- Lógica de Coloración para la Fecha de Expiración ---
        
        // 1. Obtener el timestamp de expiración del array $item
        $timestamp_expiracion = ! empty( $item['mdd_membresia_expiracion'] ) ? $item['mdd_membresia_expiracion'] : 0;
        
        // 2. Formatear la fecha
        $fecha_expiracion_formateada = ! empty( $timestamp_expiracion ) ? date_i18n( get_option( 'date_format' ), $timestamp_expiracion ) : '—';
        
        // 3. Inicializar el campo de visualización con la fecha simple
        $item['fecha_expiracion'] = $fecha_expiracion_formateada;
        
        if ( $timestamp_expiracion > 0 ) {
            $now = current_time( 'timestamp' );
            // 7 días desde ahora
            $seven_days_from_now = strtotime( '+7 days', $now );
            
            $color = '';
            $title = '';
            
            if ( $timestamp_expiracion < $now ) {
                // ROJO: Expirada (Fecha menor que hoy)
                $color = 'red';
                $title = esc_attr__( 'Expirada', 'membresia-descarga-digital' );
            } elseif ( $timestamp_expiracion <= $seven_days_from_now ) {
                // NARANJA: Expira en menos de 7 días (Alerta)
                $color = '#FF8C00'; 
                $title = esc_attr__( 'Próximo a expirar', 'membresia-descarga-digital' );
            }
            
            // Si se determinó un color, sobrescribir el campo 'fecha_expiracion' con el HTML
            if ( ! empty( $color ) ) {
                $item['fecha_expiracion'] = sprintf(
                    '<strong style="color:%s;" title="%s">%s</strong>',
                    $color,
                    $title,
                    $fecha_expiracion_formateada
                );
            }
        }

        $item['user_email'] = $item['user_email'];

        return $item;
    }

    /**
     * Determina el estado de la membresía para la columna 'estado'.
     */
    protected function mdd_get_membership_status( $item ) {
        $activa = $item['mdd_membresia_activa'];
        $tiempo_actual = current_time('timestamp');

        if ( $activa === '1') {
            return '<span style="color: green; font-weight: bold;">' . __( 'Activa', 'membresia-descarga-digital' ) . '</span>';
        } 
        
        else {
             return '<span style="color: #ff9800; font-weight: bold;">' . __( 'Inactiva', 'membresia-descarga-digital' ) . '</span>';
        }
        
    }

    protected function mdd_get_membership_products() {
        // Usamos '_mdd_duracion_membresia' como identificador: solo productos con duración > 0.
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1, // Obtener todos
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids', // Solo necesitamos los IDs para eficiencia
            'meta_query'     => array(
                array(
                    'key'     => '_mdd_duracion_membresia', // CLAVE DEL CAMPO DE DURACIÓN
                    'value'   => 0,
                    'compare' => '>', // Solo planes con duración mayor a cero
                    'type'    => 'NUMERIC', // Importante para la comparación numérica
                ),
            ),
        );

        $query = new WP_Query( $args );
        $product_ids = $query->posts;

        $products = array();
        if ( ! empty( $product_ids ) ) {
            foreach ( $product_ids as $id ) {
                $products[ $id ] = get_the_title( $id );
            }
        }
        return $products;
    }

    public function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        // Obtener los valores actuales de los filtros
        $current_plan_filter = isset( $_GET['mdd_filter_plan'] ) ? absint( $_GET['mdd_filter_plan'] ) : 0;
        $current_state_filter = isset( $_GET['mdd_filter_estado'] ) ? sanitize_text_field( $_GET['mdd_filter_estado'] ) : '';
        $current_expiration_filter = isset( $_GET['mdd_filter_expiracion'] ) ? sanitize_text_field( $_GET['mdd_filter_expiracion'] ) : '';

        // --- Filtro por PLAN DE MEMBRESÍA (Producto) ---
        // Utilizamos el método auxiliar mdd_get_membership_products para obtener SÓLO los planes válidos.
        $membership_plans = $this->mdd_get_membership_products();
        
        echo '<div class="alignleft actions">';

        if ( ! empty( $membership_plans ) ) {
            echo '<label for="mdd_filter_plan" class="screen-reader-text">' . esc_html__( 'Filtrar por plan', 'membresia-descarga-digital' ) . '</label>';
            echo '<select name="mdd_filter_plan" id="mdd_filter_plan">';
            echo '<option value="0">' . esc_html__( 'Mostrar todos los planes', 'membresia-descarga-digital' ) . '</option>';
            
            foreach ( $membership_plans as $plan_id => $plan_title ) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr( $plan_id ),
                    selected( $plan_id, $current_plan_filter, false ),
                    esc_html( $plan_title )
                );
            }
            echo '</select>';
        }

        // --- Filtro por ESTADO DE MEMBRESÍA ---
        $estados = array(
            'active' => __( 'Solo Activas', 'membresia-descarga-digital' ),
            'inactive' => __( 'Solo Inactivas', 'membresia-descarga-digital' ),
        );
        echo '<label for="mdd_filter_estado" class="screen-reader-text">' . esc_html__( 'Filtrar por estado', 'membresia-descarga-digital' ) . '</label>';
        echo '<select name="mdd_filter_estado" id="mdd_filter_estado">';
        echo '<option value="">' . esc_html__( 'Mostrar todos los estados', 'membresia-descarga-digital' ) . '</option>';
        foreach ( $estados as $key => $label ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $key ),
                selected( $key, $current_state_filter, false ),
                esc_html( $label )
            );
        }
        echo '</select>';

        // --- Filtro por EXPIRACIÓN (Próximos Vencimientos) ---
        $expiraciones = array(
            'this_week' => __( 'Expira los próximos 7 dias', 'membresia-descarga-digital' ),
            'this_month' => __( 'Expira los próximos 30 dias', 'membresia-descarga-digital' ),
        );
        echo '<label for="mdd_filter_expiracion" class="screen-reader-text">' . esc_html__( 'Filtrar por expiración', 'membresia-descarga-digital' ) . '</label>';
        echo '<select name="mdd_filter_expiracion" id="mdd_filter_expiracion">';
        echo '<option value="">' . esc_html__( 'Mostrar todas las expiraciones', 'membresia-descarga-digital' ) . '</option>';
        foreach ( $expiraciones as $key => $label ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $key ),
                selected( $key, $current_expiration_filter, false ),
                esc_html( $label )
            );
        }
        echo '</select>';

        // El botón de enviar debe ser 'Aplicar' para que los filtros funcionen
        submit_button( __( 'Filtrar', 'membresia-descarga-digital' ), 'secondary', 'action', false );
        
        echo '</div>'; // Cierra alignleft actions
    }
}