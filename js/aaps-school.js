(function ($, Drupal, drupalSettings) {

  console.log(drupalSettings);

  var code_box = document.querySelector('.aaps-school-code-box');

  code_box.style.width = 100*drupalSettings.data.w + "%";
  code_box.style.height = 100*drupalSettings.data.h + "%";
  code_box.style.left = 100*drupalSettings.data.x + "%";
  code_box.style.top = 100*drupalSettings.data.y + "%";


})(jQuery, Drupal, drupalSettings);