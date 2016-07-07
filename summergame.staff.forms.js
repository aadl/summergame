$(document).ready(function() {
	var bibField = document.getElementById('edit-tag-bib');
	$('#summergame-code-edit-form input:radio').change(function() {
		if ($(this).val() == 'tag_bib_yes') {
			bibField.required = true;
			bibField.disabled = false;
			document.getElementById('edit-everlasting').checked = true;
		}
		else {
			bibField.required = false;
			bibField.disabled = true;
		}
	});
});