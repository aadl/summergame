function checkForSchool(element_to_check) {
  styleObj = document.getElementById("school-details-div").style;
  if (element_to_check.value == 'adult')
    styleObj.display='none';
  else
    styleObj.display='block';
}

const beforeUnloadHandler = (event) => {
  // Recommended
  event.preventDefault();

  // Included for legacy support, e.g. Chrome/Edge < 119
  event.returnValue = true;
};

function checkForDirty() {
  const form = document.getElementById("summergame-player-form");
  form.addEventListener('change', (event) => {
    const element = event.target;
    console.log('Element changed:', element.name, 'Value:', element.value);
    window.addEventListener("beforeunload", beforeUnloadHandler);
  });
}

function resetDirty() {
  window.removeEventListener("beforeunload", beforeUnloadHandler);
}

window.onload = function() {
  checkForSchool(document.getElementById("edit-agegroup"));
  checkForDirty();
}
