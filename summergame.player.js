function checkForSchool(element_to_check) {
  styleObj = document.getElementById("school-details-div").style;
  if (element_to_check.value == 'adult')
    styleObj.display='none';
  else
    styleObj.display='block';
}

var _isDirty = false;

function checkForDirty() {
  $(":input").change(function(){
    _isDirty = true;
  });
}

function displayDirty() {
  if (_isDirty) {
    return "You have unsaved changes that will be lost.";
  }
}

function resetDirty() {
  _isDirty = false;
}

window.onload = checkForDirty;
window.onbeforeunload = displayDirty;
