<?php


if ( !defined('ABSPATH') ) {
    exit;
}

function mdd_anadir_campos_datos_membresia() {
    echo '<div class="options_group">';

    //campo para Duracion de membresia
    woocommerce_wp_text_input( array(
        'id'    => '_mdd_duracion_membresia',
        'label' => __( 'Duracion de la membresia (meses)', 'membresia-descarga-digital'),
        'desc_tip' => true,
        'type'  => 'number',
        'custom_attributes' => array(
            'min' => '1',
            'step' => '1'
        )
    ));

    //campo para Numero de descargas x dia
    woocommerce_wp_text_input( array(
        'id'    => '_mdd_descargas_por_dia',
        'label' => __('Descargas por Dia', 'membresia-descarga-digital'),
        'desc_tip'  => true,
        'description' => __( 'Cantidad maxima de archivos que el usuario puede descargar por dia', 'membresia-descarga-digital' ),
        'type'  =>  'number',
        'custom_attributes' => array(
            'min' => '1',
            'step' => '1'
        )
    ));

    //campo para Membresias permitidas
    $categorias = get_terms( 'product_cat', array( 'hide_empty' => false ) );
    $opciones_categorias = array();
    
    foreach ( $categorias as $cat ) {
        $opciones_categorias[ $cat->term_id ] = $cat->name;
    }

    global $thepostid;
    $categorias_permitidas = get_post_meta( $thepostid, '_mdd_categorias_permitidas', true );
    if ( ! is_array( $categorias_permitidas ) ) {
        $categorias_permitidas = array();
    }

    // 3. Renderizar el campo de selección múltiple
    woocommerce_wp_select( array(
        'id'            => '_mdd_categorias_permitidas', 
        'label'         => __( 'Categorías incluidas en la Membresía', 'membresia-descarga-digital' ),
        'options'       => $opciones_categorias,
        'description'   => __( 'Selecciona las categorías de productos a las que esta membresía otorga acceso.', 'membresia-descarga-digital' ),
        'custom_attributes' => array(
            'multiple' => 'multiple',
            'style'    => 'height: 200px;',
        ),
        'value'         => $categorias_permitidas,
    ));

    echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'mdd_anadir_campos_datos_membresia' );


//Guarda el valor de los campos personalizados
function mdd_guardar_campos_membresia( $post_id ) {
    if ( isset( $_POST['_mdd_duracion_membresia']) ) {
        $duracion = intval( $_POST['_mdd_duracion_membresia']);
        update_post_meta( $post_id, '_mdd_duracion_membresia', $duracion );
    }

    if ( isset( $_POST['_mdd_descargas_por_dia'] ) ) {
        $limite = intval( $_POST['_mdd_descargas_por_dia'] );
        update_post_meta( $post_id, '_mdd_descargas_por_dia', $limite );
    }

    if ( isset( $_POST['_mdd_categorias_permitidas'] ) ) {
        $categorias = array_map( 'intval', (array) $_POST['_mdd_categorias_permitidas'] );
        update_post_meta( $post_id, '_mdd_categorias_permitidas', $categorias );
    } else {
        // Si no se selecciona nada, asegúrate de guardar un array vacío para borrar el valor.
        update_post_meta( $post_id, '_mdd_categorias_permitidas', array() );
    }
}
add_action( 'woocommerce_process_product_meta', 'mdd_guardar_campos_membresia' );

//categoria especial
/*function mdd_anadir_campo_nueva_categoria() {
    ?>
    <div class="form-field">
        <label for="mdd-excluir-membresia"><?php _e( 'Excluir de la Membresía', 'membresia-descarga-digital' ); ?></label>
        <input type="checkbox" name="mdd-excluir-membresia" id="mdd-excluir-membresia" value="1" />
        <p class="description"><?php _e( 'Marcar esta opción para excluir todos los productos de esta categoría de las descargas por membresía.', 'membresia-descarga-digital' ); ?></p>
    </div>
    <?php
}
add_action( 'product_cat_add_form_fields', 'mdd_anadir_campo_nueva_categoria', 10, 2 );*/

/**
 * Añade el campo de checkbox 'Excluir de la Membresía' al formulario de edición de categoría.
 */
/*function mdd_anadir_campo_editar_categoria( $term ) {
    $excluido = get_term_meta( $term->term_id, 'mdd_excluir_membresia', true );
    ?>
    <tr class="form-field">
        <th scope="row"><label for="mdd-excluir-membresia"><?php _e( 'Excluir de la Membresía', 'membresia-descarga-digital' ); ?></label></th>
        <td>
            <input type="checkbox" name="mdd-excluir-membresia" id="mdd-excluir-membresia" value="1" <?php checked( $excluido, '1' ); ?> />
            <p class="description"><?php _e( 'Marcar esta opción para excluir todos los productos de esta categoría de las descargas por membresía.', 'membresia-descarga-digital' ); ?></p>
        </td>
    </tr>
    <?php
}
add_action( 'product_cat_edit_form_fields', 'mdd_anadir_campo_editar_categoria', 10, 2 );*/


/*function mdd_guardar_campo_categoria( $term_id ) {
    if ( isset( $_POST['mdd-excluir-membresia'] ) && '1' === $_POST['mdd-excluir-membresia'] ) {
        update_term_meta( $term_id, 'mdd_excluir_membresia', '1' );
    } else {
        update_term_meta( $term_id, 'mdd_excluir_membresia', '0' ); 
    }
}
add_action( 'edited_product_cat', 'mdd_guardar_campo_categoria', 10, 2 );
add_action( 'create_product_cat', 'mdd_guardar_campo_categoria', 10, 2 );*/


// Agregar "Membresía" junto a Virtual/Descargable en la cabecera de Datos del producto
add_filter( 'product_type_options', function( $options ) {
    global $post;
    $es_membresia = get_post_meta($post->ID, '_es_membresia', true);
    $options['mdd_membresia'] = array(
        'id'            => '_es_membresia',
        'wrapper_class' => 'show_if_simple show_if_variable', // muéstralo en tipos comunes
        'label'         => __( 'Membresía', 'mdd' ),
        'description'   => __( 'Marcar si este producto es una membresía.', 'mdd' ),
        'default'       => ($es_membresia === 'yes') ? 'yes' : 'no',
    );
    return $options; // importantísimo
}, 10 );

//Guardar el valor y asignar categoría
add_action( 'woocommerce_admin_process_product_object', function( $product ) {
    $es_membresia = isset( $_POST['_es_membresia'] ) ? 'yes' : 'no';
    $product->update_meta_data( '_es_membresia', $es_membresia );

    if ( $es_membresia === 'yes' ) {
        // forzar a virtual
        $product->set_virtual( true );

        // asegura que exista la categoría, por si el usuario se le olvida crear la categoria Membresia
        // if ( ! term_exists( 'membresia', 'product_cat' ) ) {
        //     wp_insert_term( 'Membresía', 'product_cat', array( 'slug' => 'membresia' ) );
        // }
        wp_set_object_terms( $product->get_id(), 'membresia', 'product_cat', true );
    } else {
        //remueve la categoria si el checkbox es desmarcado
        wp_remove_object_terms($product->get_id(), 'membresia', 'product_cat');
    }
});

//Ocultar pestañas cuando sea membresía (en carga inicial):
add_filter( 'woocommerce_product_data_tabs', function( $tabs ) {
    global $post;
    $es_membresia = get_post_meta( $post->ID, '_es_membresia', true );
    if ( $es_membresia === 'yes' ) {
        unset( $tabs['linked_product'] );   // Productos relacionados
        unset( $tabs['attribute'] );        // Atributos
        unset( $tabs['advanced'] );         // Avanzado
        unset( $tabs['cartflows'] );        // CartFlows (si existe)
        unset( $tabs['direct_checkout'] );  // Direct Checkout (si existe)
    }
    return $tabs;
}, 20 );

//Ocultar/mostrar al vuelo (marcas el checkbox y se ocultan sin recargar):
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'product' ) : ?>
        <script>
        jQuery(function($){
            $('#_es_membresia').on('change', function(){
                if( $(this).is(':checked') ){
                    $('#_virtual').prop('checked', true).trigger('change');
                }
            });

            function toggleMembresiaFields() {
                if( $('#_es_membresia').is(':checked') ) {
                    $('#_mdd_duracion_membresia').closest('.form-field').show();
                    $('#_mdd_descargas_por_dia').closest('.form-field').show();
                } else {
                    $('#_mdd_duracion_membresia').closest('.form-field').hide();
                    $('#_mdd_descargas_por_dia').closest('.form-field').hide();
                }
            }

             // al cargar la página
            toggleMembresiaFields();

            // cuando cambie el check de membresía
            $('#_es_membresia').on('change', function(){
                toggleMembresiaFields();
            });

            function toggleMembresiaTabs() {
                if ( $('#_es_membresia').is(':checked') ) {
                    $('#woocommerce-product-data .wc-tabs li.attribute_tab').hide();
                    $('#woocommerce-product-data .wc-tabs li.linked_product_tab').hide();
                    $('#woocommerce-product-data .wc-tabs li.advanced_tab').hide();
                    $('#woocommerce-product-data .wc-tabs li.cartflows_tab').hide();
                    $('#woocommerce-product-data .wc-tabs li.direct_checkout_tab').hide();
                } else {
                    $('#woocommerce-product-data .wc-tabs li.attribute_tab').show();
                    $('#woocommerce-product-data .wc-tabs li.linked_product_tab').show();
                    $('#woocommerce-product-data .wc-tabs li.advanced_tab').show();
                    $('#woocommerce-product-data .wc-tabs li.cartflows_tab').show();
                    $('#woocommerce-product-data .wc-tabs li.direct_checkout_tab').show();
                }
            }
            toggleMembresiaTabs();
            $(document).on('change', '#_es_membresia', toggleMembresiaTabs);
        });
        </script>
    <?php endif;
});

