(function () {
  window.checkForSchool = function(element_to_check) {
    styleObj = document.getElementById("school-details-div").style;
    if (element_to_check.value == 'adult')
      styleObj.display='none';
    else
      styleObj.display='block';
  }

  window.addEventListener('load', function () {
    checkForSchool(document.getElementById("edit-agegroup"));
    if (!window.location.pathname.replace(/\/$/, '').endsWith('/edit')) {
      return;
    }
    const beforeUnloadHandler = (event) => {
      event.preventDefault();
      event.returnValue = true;
    };

    const form = document.getElementById("summergame-player-form");
    form.addEventListener('change', () => {
      window.addEventListener("beforeunload", beforeUnloadHandler);
    });
    form.addEventListener('submit', () => {
      window.removeEventListener("beforeunload", beforeUnloadHandler);
    });
  });

})();
