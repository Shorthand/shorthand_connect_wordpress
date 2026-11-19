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
	let parent = e.closest( 'li.story' );
	parent.classList.add( "selected" );

	// A post can represent either one standalone Story or one complete Project.
	document.getElementById( 'shorthand-project-id' ).value = '';
	document.querySelectorAll( '.project-card[aria-pressed="true"]' ).forEach( function (card) {
		card.setAttribute( 'aria-pressed', 'false' );
		card.closest( 'li.project' ).classList.remove( 'selected' );
	} );

	// Reset the title.
	document.getElementById( "title" ).value = document.querySelector( "li.story.selected .title" ).innerText;
	document.getElementById( "title-prompt-text" ).classList.add( 'screen-reader-text' );

	// Reset the description (abstr act).
	const abstract = document.getElementById( "abstract" );
	if ( abstract ) {
		abstract.value = document.querySelector( "li.story.selected .description" ).value;
	}
}

document.getElementById('stories-list').addEventListener('change', function (event) {
	if (event.target.classList.contains('story_id')) {
		selectStoryRadio(event.target);
	}
});
