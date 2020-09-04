(function ($, Drupal) {
  // Build marker layers
  var availableLayerGroup = new L.layerGroup();
  var redeemedLayerGroup = new L.layerGroup();

  var myMap = L.map('mapid', {
      center: [42.2781734, -83.74570792114082],
      zoom: 13,
      layers: [availableLayerGroup, redeemedLayerGroup]
  });

  var redIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });

  var orangeIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-orange.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });

  var yellowIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-gold.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });

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
  }).addTo(myMap);

  // Load marker data from json source
  $.ajax({
    type: 'GET',
    url: '/summergame/homecodes/markerdata',
    dataType: 'json',
    success: function (data) {
      var redeemedData = false;

      // Loop through data and create markers
      $.each(data, function(index, element) {
/* NO REDEEMED DATA IN OFFSEASON
        if (element.redeemed) {
          redeemedData = true;
          L.marker([element.lat, element.lon], {icon: redIcon}).bindPopup(element.homecode).addTo(redeemedLayerGroup);
        }
        else {
          L.marker([element.lat, element.lon]).bindPopup(element.homecode).addTo(availableLayerGroup);
        }
*/
        if (element.num_redemptions >= 300) {
          L.marker([element.lat, element.lon], {icon: redIcon}).bindPopup(element.homecode).addTo(availableLayerGroup);
        }
        else if (element.num_redemptions >= 200) {
          L.marker([element.lat, element.lon], {icon: orangeIcon}).bindPopup(element.homecode).addTo(availableLayerGroup);
        }
        else if (element.num_redemptions >= 100) {
          L.marker([element.lat, element.lon], {icon: yellowIcon}).bindPopup(element.homecode).addTo(availableLayerGroup);
        }
        else if (element.num_redemptions >= 50) {
          L.marker([element.lat, element.lon], {icon: greenIcon}).bindPopup(element.homecode).addTo(availableLayerGroup);
        }
        else {
          L.marker([element.lat, element.lon]).bindPopup(element.homecode).addTo(availableLayerGroup);
        }
      });

      // Only show layer control if redeemed data has been returned
      if (redeemedData) {
        // Add layers to map
        var overlayMaps = {
            "Available": availableLayerGroup,
            "Redeemed": redeemedLayerGroup
        };

        L.control.layers(null, overlayMaps, {collapsed:false}).addTo(myMap);

        $(".leaflet-control-layers-overlays").prepend("<label>Show Codes:</label>");
      }
      else {
        availableLayerGroup.addTo(myMap);
      }
    }
  });
})(jQuery, Drupal);
