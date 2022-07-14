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

  // Load heatmap data from json source
  $.ajax({
    type: 'GET',
    url: '/summergame/map/data/' + drupalSettings.hc_game_term,
    dataType: 'json',
    success: function (data) {
      var heatmap = new L.TileLayer.HeatCanvas({},{'step':0.5, 'degree':HeatCanvas.LINEAR, 'opacity':0.7});
      heatmap.onRenderingStart(function(){
        document.getElementById("status").innerHTML = 'rendering';
      });
      heatmap.onRenderingEnd(function(){
        document.getElementById("status").innerHTML = '';
      });

      // Loop through data and create markers
      $.each(data, function(index, element) {
        heatmap.pushData(element.lat, element.lon, element.value);
      });
      myMap.addLayer(heatmap);
      alert('FOO');
    }
  });

  // Add Branch Locations
  L.marker([42.278355032204445, -83.74590413038366]).bindPopup('<strong>Downtown Library</strong><br>343 South Fifth Ave.<br>Building Codes<br>Library Code Stop').addTo(availableLayerGroup);
  L.marker([42.24387568322788, -83.71805381691777]).bindPopup('<strong>Malletts Creek Library</strong><br>3090 East Eisenhower Parkway<br>Building Codes<br>Library Code Stop').addTo(availableLayerGroup);
  L.marker([42.25271695126512, -83.77811950157411]).bindPopup('<strong>Pittsfield Library</strong><br>2359 Oak Valley Dr.<br>Building Codes<br>Library Code Stop').addTo(availableLayerGroup);
  L.marker([42.30838433760029, -83.71416680157276]).bindPopup('<strong>Traverwood Library</strong><br>3333 Traverwood Dr.<br>Building Codes<br>Library Code Stop').addTo(availableLayerGroup);
  L.marker([42.27866255504599, -83.78305954390173]).bindPopup('<strong>Westgate Library</strong><br>2503 Jackson Ave.<br>Building Codes<br>Library Code Stop').addTo(availableLayerGroup);

  // Load marker data from json source
  $.ajax({
    type: 'GET',
    url: '/summergame/homecodes/markerdata/' + drupalSettings.hc_game_term,
    dataType: 'json',
    success: function (data) {
      var redeemedData = false;

      // Loop through data and create markers
      $.each(data, function(index, element) {
        if (drupalSettings.hc_points_enabled) {
          if (element.redeemed) {
            redeemedData = true;
            L.marker([element.lat, element.lon], {icon: redIcon}).bindPopup(element.homecode).addTo(redeemedLayerGroup);
          }
          else {
            if (element.code_id) {
              // Add report link to homecode text
              element.homecode += '<br>[ <a href="/summergame/homecodes/report/' + element.code_id + '">Can\'t find it?</a> ]';
            }
            L.marker([element.lat, element.lon]).bindPopup(element.homecode).addTo(availableLayerGroup);
          }
        }
        else {
          // Offseason, show number of redemptions
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
