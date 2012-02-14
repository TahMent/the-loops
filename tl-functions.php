<?php

/**
 * Helper functions
 * 
 * @package The_Loops
 * @since 0.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Returns a WP_Query based on a loop details.
 *
 * @package The_Loops
 * @since 0.1
 *
 * @param int $id Loop ID.
 * @param string|array $query URL query string or array.
 * @return WP_Query
 */
function tl_query( $id, $query = '' ) {
	global $the_loops, $loop_id;

	$loop_id = $id;
	if ( empty ( $loop_id ) )
		return;

	$content = get_post_meta( $loop_id, 'tl_loop_content', true );

	// post type
	$args = array(
		'post_type' => (array) $content['post_type']
	);

	// author
	$authors_logins = _tl_csv_to_array( $content['authors'] );
	if ( $authors_logins ) {
		$replacements = 1;

		$authors_ids = array();

		foreach ( $authors_logins as $author_login ) {
			$exclude_author = false;

			if ( strpos( $author_login, '-' ) === 0 ) {
				$exclude_author = true;
				$author_login = str_replace( '-', '', $author_login, $replacements );
			}

			$author_id = get_user_by( 'login', $author_login )->ID;

			if ( $exclude_author )
				$authors_ids[] = "-$author_id";
			else
				$authors_ids[] = $author_id;
		}

		if ( $authors_ids )
			$authors_ids = implode( ',', $authors_ids );

		$args['author'] = $authors_ids;
	}

	// taxonomy
	if ( ! empty( $content['taxonomies'] ) ) {
		$tax_query = array();

		foreach ( $content['taxonomies'] as $taxonomy ) {
			if ( empty( $taxonomy['terms'] ) )
				continue;

			$terms = _tl_csv_to_array( $taxonomy['terms'] );

			$tax_query[] = array(
				'taxonomy'         => $taxonomy['taxonomy'],
				'field'            => 'slug',
				'terms'            => $terms,
				'include_children' => empty( $taxonomy['include_children'] ) ? false : true,
				'operator'         => empty( $taxonomy['exclude'] ) ? 'IN' : 'NOT IN'
			);
		}

		if ( $tax_query ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query'] = $tax_query;
		}
	}

	// offset
	$args['offset'] = absint( $content['offset'] );

	// order and orderby
	$args['order'] = $content['order'];

	if ( in_array( $content['orderby'], array( 'meta_value', 'meta_value_num' ) ) ) {
		$content['meta_key'] = trim ( $content['meta_key'] );

		if ( ! empty( $content['meta_key'] ) ) {
			$args['meta_key'] = $content['meta_key'];
			$args['orderby']  = $content['orderby'];
		}
	} else {
		$args['orderby'] = $content['orderby'];
	}

	// custom field
	if ( ! empty( $content['custom_fields'] ) ) {
		$meta_query = array();

		foreach ( $content['custom_fields'] as $custom_field ) {
			if ( empty( $custom_field['key'] ) )
				continue;

			$values = _tl_csv_to_array( $custom_field['values'], "\t" );

			if ( in_array( $custom_field['compare'], array( 'LIKE', 'NOT LIKE' ) ) )
				$values = $values[0];

			$meta_query[] = array(
				'key'     => trim( $custom_field['key'] ),
				'value'   => $values,
				'compare' => $custom_field['compare'],
				'type'    => $custom_field['type']
			);
		}

		if ( $meta_query ) {
			$meta_query['relation'] = 'AND';
			$args['meta_query'] = $meta_query;
		}
	}

	// pagination
	$posts_per_page = (int) $content['posts_per_page'];
	if ( empty( $posts_per_page ) ) {
		$args['nopaging'] = true;
	} else {
		$args['posts_per_page'] = $posts_per_page;
	}

	if ( empty( $content['pagination'] ) ) {
		$args['paged'] = 1;
	} else {
		$args['paged'] = max( 1, get_query_var( 'paged' ) );
	}

	$args = wp_parse_args( $query, $args );

	add_filter( 'posts_where', array( $the_loops, 'filter_where' ) );
	$query = new WP_Query( $args );
	remove_filter( 'posts_where', array( $the_loops, 'filter_where' ) );

	return $query;
}

/**
 * Convert a string of comma-separated values to an array
 *
 * @package The_Loops
 * @since 0.2
 * @params string $string String of comma-separated values
 * @return array Values
 */
function _tl_csv_to_array( $string, $delimiter = ',' ) {
	if ( ! $string )
		return;

	$array = explode( $delimiter, $string );
	return array_map( 'trim', $array );
}

/**
 * Wrapper function for get_posts to get the loops.
 *
 * @package The_Loops
 * @since 0.1
 */
function tl_get_loops( $args = array() ) {
	$defaults = array(
		'post_type' => 'tl_loop',
		'nopaging'  => true
	);

	$args = wp_parse_args( $args, $defaults );

	return get_posts( $args );
}

/**
 * Setup globals before displaying the loop
 *
 * @package The_Loops
 * @since 0.1
 */
function tl_setup_globals( $loop_id, $args, $context ) {
	global $wp_query, $orig_query, $tl_loop_id, $tl_context;

	$tl_loop_id  = $loop_id;
	$tl_context  = $context;

	$tl_query = tl_query( $loop_id, $args );
	$orig_query = clone $wp_query;
	$wp_query  = clone $tl_query;
}

/**
 * Clear globals after displaying the loop
 *
 * @package The_Loops
 * @since 0.3
 */
function tl_clear_globals() {
	global $wp_query, $orig_query, $tl_loop_id, $tl_context;

	$wp_query = clone $orig_query;
	wp_reset_query();

	unset( $orig_query, $tl_loop_id, $tl_context );
}

/**
 * Display a loop
 *
 * @package The_Loops
 * @since 0.1
 *
 * @param int $loop_id Loop ID.
 * @param string $template_name Name of the template to use
 * @param array|string Custom query args
 * @param string Context in which the loop is displayed
 */
function tl_display_loop( $loop_id, $template_name, $args = null, $context = '' ) {
	global $the_loops;

	$loop_templates = tl_get_loop_templates();
	$loop_template = $loop_templates[$template_name];

	tl_setup_globals( $loop_id, $args, $context );

	ob_start();

	include( "{$the_loops->plugin_dir}tl-template-tags.php" );

	tl_locate_template( $loop_template, true );

	$content = ob_get_contents();
	ob_end_clean();

	tl_clear_globals();

	return $content;
}

/**
 * Add the loops shortcode which will render a loop from an id provided as attribute
 *
 * @package The_Loops
 * @since 0.1
 */
function tl_shortcode( $atts ) {
	extract( shortcode_atts( array(
		'id' => 0,
	), $atts ) );

	$post_id = get_the_ID();

	// Exclude current post/page where the shortcode will be displayed
	$args = array(
		'post__not_in' => array( $post_id )
	);
	$details = get_post_meta( $id, 'tl_loop_content', true );

	$content = tl_display_loop( $id, $details['template'], $args, 'shortcode' );

	return $content;
}
add_shortcode( 'the-loop', 'tl_shortcode' );

/**
 * Get the default Loop Templates
 *
 * @package The_Loops
 * @since 0.2
 */
function tl_get_default_loop_templates() {
	global $the_loops;

	$templates_files = scandir( $the_loops->templates_dir );

	foreach ( $templates_files as $template ) {
		if ( ! is_file( $the_loops->templates_dir . $template ) )
			continue;

		// don't allow template files in subdirectories
		if ( false !== strpos( $template, '/' ) )
			continue;

		$data = get_file_data( $the_loops->templates_dir. $template, array( 'name' => 'The Loops Template' ) );

		if ( ! empty( $data['name'] ) )
			$loop_templates[trim( $data['name'] )] = $template;
	}

	return $loop_templates;
}

/**
 * Get the Loop Templates available in the current theme or the default ones
 *
 * @package The_Loops
 * @since 0.2
 */
function tl_get_loop_templates() {
	$themes = get_themes();
	$theme = get_current_theme();
	$templates = $themes[$theme]['Template Files'];
	$loop_templates = tl_get_default_loop_templates();

	if ( is_array( $templates ) ) {
		$base = array( trailingslashit(get_template_directory()), trailingslashit(get_stylesheet_directory()) );

		foreach ( $templates as $template ) {
			$basename = str_replace( $base, '', $template );

			// don't allow template files in subdirectories
			if ( false !== strpos( $basename, '/' ) )
				continue;

			if ( 'functions.php' == $basename )
				continue;

			$data = get_file_data( $template, array( 'name' => 'The Loops Template' ) );

			if ( !empty( $data['name'] ) )
				$loop_templates[trim( $data['name'] )] = $basename;
		}
	}

	return $loop_templates;
}

/**
 * Retrieve the name of the highest priority template file that exists.
 *
 * Searches in the plugin templates dir, then STYLESHEETPATH and TEMPLATEPATH.
 *
 * @package The_Loops
 * @since 0.2
 *
 * @param string|array $template_names Template file(s) to search for, in order.
 * @param bool $load If true the template file will be loaded if it is found.
 * @param bool $require_once Whether to require_once or require. Default true. Has no effect if $load is false.
 * @return string The template filename if one is located.
 */
function tl_locate_template( $template_names, $load = false, $require_once = false ) {
	global $the_loops;

	$located = '';
	foreach ( (array) $template_names as $template_name ) {
		if ( ! $template_name )
			continue;

		if ( file_exists( STYLESHEETPATH . '/' . $template_name ) ) {
			$located = STYLESHEETPATH . '/' . $template_name;
			break;
		} else if ( file_exists( TEMPLATEPATH . '/' . $template_name ) ) {
			$located = TEMPLATEPATH . '/' . $template_name;
			break;
		} else if ( file_exists( $the_loops->templates_dir . $template_name ) ) {
			$located = $the_loops->templates_dir . $template_name;
			break;
		}
	}

	if ( $load && '' != $located )
		load_template( $located, $require_once );

	return $located;
}

