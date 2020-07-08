(function ($, Drupal) {
  var mymap = L.map("mapid").setView([42.2781734, -83.74570792114082], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(mymap);

  // Load marker data from json source
  $.ajax({
    type: 'GET',
    url: '/summergame/homecodes/markerdata',
    dataType: 'json',
    success: function (data) {
        $.each(data, function(index, element) {
          L.marker([element.lat, element.lon]).bindPopup(element.homecode).addTo(mymap);
        });
    }
  });
})(jQuery, Drupal);
