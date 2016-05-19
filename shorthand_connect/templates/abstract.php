<?php

function abstract_template($post_id, $abstract, $data) {
	$abstract = '<p>'.$abstract.'</p>';

	$abstract .= '<a href="'.get_permalink($post_id).'">View the story</a>';

	$abstract .= '<div style="display:none;">'.$data.'</div>';
	return $abstract;
}

?>