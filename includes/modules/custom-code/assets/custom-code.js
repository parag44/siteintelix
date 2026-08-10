(function () {
	'use strict';
	var textarea = document.getElementById('sitx-code-editor');
	if (textarea && window.wp && wp.codeEditor && window.siteintelixCustomCode && siteintelixCustomCode.editorSettings) {
		wp.codeEditor.initialize(textarea, siteintelixCustomCode.editorSettings);
	}
}());
