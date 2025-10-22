<?php 


// 1. Quitar el AJAX "Añadir al carrito" en la tienda para productos de categoría membresía
add_filter( 'woocommerce_product_add_to_cart_url', function( $url, $product ) {

    if ( mdd_producto_es_membresia( $product->get_id() ) )  {
        return get_permalink( $product->get_id() ); // manda al detalle
    }
    return $url;

}, 10, 2 );

add_filter( 'woocommerce_loop_add_to_cart_link', function( $button, $product ) {
    
    if ( mdd_producto_es_membresia( $product->get_id() ) )  {    
        //si no esta logueado, no mostrar los prod de memresia
        if ( !is_user_logged_in() )  return '';
        
        $url = get_permalink( $product->get_id() );
        $label = esc_html__( 'Ver detalles', 'mdd' ); 
        return '<a href="'.esc_url($url).'" class="button product_type_simple">'.$label.'</a>';
    }
    return $button;
}, 10, 2 );

//**Si un usuario intenta entrar, mediante url a un detalleproducto de membresia, intentando burlar la denegacion, este lo redirigira a otra pagina
add_action( 'template_redirect', function() {
    
    if ( is_product() ) {
        global $post;
        $product = wc_get_product( $post->ID ); // obtén el objeto seguro

        if ( $product && mdd_producto_es_membresia( $product->get_id() ) ) {
            if ( ! is_user_logged_in() ) {
                // redirigir a login o a donde quieras
                wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
                exit;
            }
        }
    }
});

add_action( 'woocommerce_before_single_product', function() {
    global $product;
    if ( ! $product ) return;

    if ( mdd_producto_es_membresia( $product->get_id() ) )  {
        wc_add_notice(
            '<strong>Importante:</strong> Solo se permite un producto de membresia en el carrito, si usted añade el producto membresia al carrito, todos los demás productos que hay dentro del carrito quedaran afuera. Si no desea continuar puede volver atras.',
            'notice'
        );
    }
});

// 2. Validación al añadir producto al carrito
add_filter( 'woocommerce_add_to_cart_validation', 'mdd_unica_membresia_en_carrito', 10, 3 );

function mdd_unica_membresia_en_carrito( $passed, $product_id, $quantity ) {
    $categorias_del_prod_aniadido = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );

    if ( is_wp_error( $categorias_del_prod_aniadido ) ) {
        return $passed;
    }

    //si quiere añadir un producto ajeno a membresia, con 1 producto membresia ya en carrito entonces denegar
    if ( !in_array( 'membresia', $categorias_del_prod_aniadido, true ) ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $cart_item_categorias = wp_get_post_terms(  $cart_item['product_id'], 'product_cat', array( 'fields' => 'slugs' ) );
            if ( is_array($cart_item_categorias) && in_array('membresia', $cart_item_categorias, true) ) {
                wc_add_notice( 'Ya tienes un producto membresía en el carrito. Añadir otros productos (estando un producto de membresia en carrito) no está permitido', 'error' );
                return false;
            }
        }
    }

    if ( in_array( 'membresia', $categorias_del_prod_aniadido, true ) ) {

        // Solo cantidad 1
        if ( $quantity > 1 ) {
            wc_add_notice( 'Solo se permite 1 membresía en el carrito.', 'error' );
            return false;
        }

        // Ya hay membresía en el carrito
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $cart_item_categorias = wp_get_post_terms(  $cart_item['product_id'], 'product_cat', array( 'fields' => 'slugs' ) );
            if ( is_array($cart_item_categorias) && in_array('membresia', $cart_item_categorias, true) ) {
                wc_add_notice( 'Ya tienes una membresía en el carrito.', 'error' );
                return false;
            }
        }

        // Regla 3: si hay otros productos → vaciar carrito, añadir membresía y redirigir
        if ( WC()->cart->get_cart_contents_count() > 0 ) {
            WC()->cart->empty_cart();
            WC()->cart->add_to_cart( $product_id, 1 );
            wp_safe_redirect( wc_get_cart_url() );
            exit;
        }

    }

    return $passed;
}


//Forzar que todos los productos de categoría 'membresia' se vendan individualmente
add_filter( 'woocommerce_is_sold_individually', function( $sold_individually, $product ) {
    if ( mdd_producto_es_membresia( $product->get_id() ) )  {
        return true;
    }
    return $sold_individually;
}, 10, 2 );

