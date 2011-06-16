function checkForSchool(element_to_check) {
  styleObj = document.getElementById("school-details-div").style;
  if (element_to_check.value == 'adult')
    styleObj.display='none';
  else
    styleObj.display='block';
}
