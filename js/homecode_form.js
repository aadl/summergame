function checkCodeType() {
  element_to_check = document.getElementById('edit-type');
  if (element_to_check.value == 'lawn') {
    document.getElementById("homecode-form-details").className = "";
    document.getElementById("lawn-elements").className = "";
    document.getElementById("library-elements").className = "visually-hidden";
  }
  else if (element_to_check.value == 'library') {
    document.getElementById("homecode-form-details").className = "";
    document.getElementById("lawn-elements").className = "visually-hidden";
    document.getElementById("library-elements").className = "";
  }
  else {
    document.getElementById("homecode-form-details").className = "visually-hidden";
    document.getElementById("lawn-elements").className = "";
    document.getElementById("library-elements").className = "";
  }
}

window.onload = checkCodeType;
