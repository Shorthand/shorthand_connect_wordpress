<?php

function shand_abstract_template( $post_id, $abstract_html, $data ) {
	$abstract_html = '<p>' . $abstract_html . '</p>';

	$abstract_html .= '<a href="' . get_permalink( $post_id ) . '">View the story</a>';

	$abstract_html .= '<div style="display:none;">' . $data . '</div>';
	return $abstract_html;
}
