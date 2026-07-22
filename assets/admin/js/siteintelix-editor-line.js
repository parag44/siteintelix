( function() {
	'use strict';

	var config = 'undefined' !== typeof siteintelixEditorLine ? siteintelixEditorLine : ( window.siteintelixEditorLine || {} );
	var targetLine = Math.max( 1, parseInt( config.line, 10 ) || 1 );
	var attempts = 0;
	var maximumAttempts = 40;

	/**
	 * Navigate a WordPress CodeMirror editor to the requested line.
	 *
	 * @param {Object} editor CodeMirror instance.
	 * @return {void}
	 */
	function navigateCodeMirror( editor ) {
		var line = Math.min( targetLine - 1, Math.max( 0, editor.lineCount() - 1 ) );
		var position = { line: line, ch: 0 };
		var coordinates;
		var scroll;

		editor.setCursor( position );
		editor.scrollIntoView( position, 140 );

		coordinates = editor.charCoords( position, 'local' );
		scroll = editor.getScrollInfo();
		editor.scrollTo( null, Math.max( 0, coordinates.top - ( scroll.clientHeight / 2 ) ) );

		editor.addLineClass( line, 'background', 'siteintelix-editor-target-line' );
		editor.focus();

		window.setTimeout( function() {
			editor.removeLineClass( line, 'background', 'siteintelix-editor-target-line' );
		}, 4000 );
	}

	/**
	 * Navigate the plain textarea fallback to the requested line.
	 *
	 * @param {HTMLTextAreaElement} textarea WordPress file editor textarea.
	 * @return {void}
	 */
	function navigateTextarea( textarea ) {
		var lines = textarea.value.split( '\n' );
		var clampedLine = Math.min( targetLine, Math.max( 1, lines.length ) );
		var offset = lines.slice( 0, clampedLine - 1 ).join( '\n' ).length;
		var lineLength = lines[ clampedLine - 1 ] ? lines[ clampedLine - 1 ].length : 0;

		if ( clampedLine > 1 ) {
			offset++;
		}

		textarea.focus();
		textarea.selectionStart = Math.min( offset, textarea.value.length );
		textarea.selectionEnd = Math.min( offset + lineLength, textarea.value.length );
		textarea.scrollTop = Math.max(
			0,
			( textarea.scrollHeight * ( clampedLine / Math.max( 1, lines.length ) ) ) - ( textarea.clientHeight / 2 )
		);
		textarea.classList.add( 'siteintelix-editor-target-textarea' );

		window.setTimeout( function() {
			textarea.classList.remove( 'siteintelix-editor-target-textarea' );
		}, 4000 );
	}

	/**
	 * Wait for WordPress to initialize its editor, then navigate.
	 *
	 * @return {void}
	 */
	function focusLine() {
		var component = window.wp && window.wp.themePluginEditor;
		var editor = component && component.instance && component.instance.codemirror;
		var textarea = document.getElementById( 'newcontent' );

		if ( editor ) {
			navigateCodeMirror( editor );
			return;
		}

		attempts++;

		if ( textarea && attempts >= 10 && ! document.querySelector( '.CodeMirror' ) ) {
			navigateTextarea( textarea );
			return;
		}

		if ( attempts <= maximumAttempts ) {
			window.setTimeout( focusLine, 100 );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', focusLine );
	} else {
		window.setTimeout( focusLine, 0 );
	}
}() );
