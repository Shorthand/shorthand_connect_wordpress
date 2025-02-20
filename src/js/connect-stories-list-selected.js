/**
 * Stories selection processing.
 *
 * @package Shorthand Connect
 */

function selectStoryRadio(e) {
	// Remove selected class and add new one.
	let selected = document.querySelector( "li.story.selected" );
	if (selected) {
		selected.classList.remove( "selected" );
	}
	let parent = e.parentElement.parentElement;
	parent.classList.add( "selected" );

	// Reset the title.
	document.getElementById( "title" ).value = document.querySelector( "li.story.selected .title" ).innerText;
	document.getElementById( "title-prompt-text" ).classList.add( 'screen-reader-text' );

	// Reset the description (abstr act).
	document.getElementById( "abstract" ).value = document.querySelector( "li.story.selected .description" ).value;
}
