(function ($, Drupal) {
  var mymap = L.map("mapid").setView([42.2781734, -83.74570792114082], 13);

  var greenIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });

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
          if (element.redeemed) {
            L.marker([element.lat, element.lon], {icon: greenIcon}).bindPopup(element.homecode).addTo(mymap);
          }
          else {
            L.marker([element.lat, element.lon]).bindPopup(element.homecode).addTo(mymap);
          }
        });
    }
  });
})(jQuery, Drupal);
