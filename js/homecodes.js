(function ($, Drupal) {
  // Build marker layers
  var badgeLayerGroup = new L.layerGroup();
  var branchLayerGroup = new L.layerGroup();
  var bizcodeLayerGroup = new L.layerGroup();
  // var heatmapLayerGroup = new L.layerGroup();
  var homecodeLayerGroup = new L.layerGroup();
  var homecodeLayerGroupA = new L.layerGroup();
  var homecodeLayerGroupB = new L.layerGroup();
  var homecodeLayerGroupC = new L.layerGroup();
  var homecodeLayerGroupD = new L.layerGroup();
  var homecodeLayerGroupE = new L.layerGroup();

  var myMap = L.map('mapid', {
      center: [42.2781734, -83.74570792114082],
      zoom: 13,
      layers: [badgeLayerGroup, branchLayerGroup, bizcodeLayerGroup, homecodeLayerGroup, homecodeLayerGroupA, homecodeLayerGroupB, homecodeLayerGroupC, homecodeLayerGroupD, homecodeLayerGroupE]
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

  var blueIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });

  var purpleIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-violet.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });

  var greyIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-grey.png',
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
/*
      var heatRadius = 0.001;
      if (element = document.querySelector('#heatRadius')) {
        heatRadius = element.innerHTML;
      }

      var heatCfg = {
        // radius should be small ONLY if scaleRadius is true (or small radius is intended)
        // if scaleRadius is false it will be the constant radius used in pixels
        "radius": heatRadius,
        "maxOpacity": .5,
        // scales the radius based on map zoom
        "scaleRadius": true,
        // if set to false the heatmap uses the global maximum for colorization
        // if activated: uses the data maximum within the current map boundaries
        //   (there will always be a red spot with useLocalExtremas true)
        "useLocalExtrema": false,
        // which field name in your data represents the latitude - default "lat"
        latField: 'lat',
        // which field name in your data represents the longitude - default "lng"
        lngField: 'lon',
        // which field name in your data represents the data value - default "value"
        valueField: 'count'
      };
      var heatmapLayer = new HeatmapOverlay(heatCfg).addTo(heatmapLayerGroup);
      heatmapLayer.setData(data.heatmap);

      // Create Legend
      var legendCanvas = document.createElement('canvas');
      legendCanvas.width = 200;
      legendCanvas.height = 10;
      var gradientImg = document.querySelector('#gradient');
      var legendCtx = legendCanvas.getContext('2d');
      var gradientCfg = {};

      gradientCfg = heatmapLayer._heatmap._config.defaultGradient;
      var gradient = legendCtx.createLinearGradient(0, 0, 200, 1);
      for (var key in gradientCfg) {
        gradient.addColorStop(key, gradientCfg[key]);
      }
      legendCtx.fillStyle = gradient;
      legendCtx.fillRect(0, 0, 200, 10);
      gradientImg.src = legendCanvas.toDataURL();
*/
      // Add Badges
      $.each(data.badges, function(index, element) {
        // create new icon image based on badge image
        var badgeIcon = new L.Icon({
          iconUrl: element.image,
          iconSize: [64, 64],
        });
        L.marker([element.lat, element.lon], {icon: badgeIcon}).bindPopup(element.popup).addTo(badgeLayerGroup);
      });

      // Add Bizcodes
      $.each(data.bizcodes, function(index, element) {
        L.marker([element.lat, element.lon], {icon: redIcon}).bindPopup(element.bizcode).addTo(bizcodeLayerGroup);
      });

      // Loop through homecode data and create markers
      $.each(data.homecodes, function(index, element) {
        if (drupalSettings.hc_points_enabled) {
          var reported = false;
          if (element.reports && Object.keys(element.reports).length >= drupalSettings.hc_report_threshold) {
            element.homecode = 'REPORTED as hard to find<br>' + element.homecode;
            reported = true;
          }
          if (element.code_id) {
            // Add report link to homecode text
            element.homecode += '<br>[ <a href="/summergame/homecodes/report/' + element.code_id + '">Can\'t find it?</a> ]';
          }

          // Determine layer group
          if (element.layerGroup == 'A') {
            var aIcon = (reported ? greyIcon : redIcon);
            L.marker([element.lat, element.lon], {icon: aIcon}).bindPopup(element.homecode).addTo(homecodeLayerGroupA);
          }
          else if (element.layerGroup == 'B') {
            var bIcon = (reported ? greyIcon : orangeIcon);
            L.marker([element.lat, element.lon], {icon: bIcon}).bindPopup(element.homecode).addTo(homecodeLayerGroupB);
          }
          else if (element.layerGroup == 'C') {
            var cIcon = (reported ? greyIcon : yellowIcon);
            L.marker([element.lat, element.lon], {icon: cIcon}).bindPopup(element.homecode).addTo(homecodeLayerGroupC);
          }
          else if (element.layerGroup == 'D') {
            var dIcon = (reported ? greyIcon : greenIcon);
            L.marker([element.lat, element.lon], {icon: dIcon}).bindPopup(element.homecode).addTo(homecodeLayerGroupD);
          }
          else {
            // default to LayerGroupE
            var eIcon = (reported ? greyIcon : blueIcon);
            L.marker([element.lat, element.lon], {icon: eIcon}).bindPopup(element.homecode).addTo(homecodeLayerGroupE);
          }
        }
        else {
          // Offseason, show number of redemptions
          if (element.num_redemptions >= 300) {
            L.marker([element.lat, element.lon], {icon: redIcon}).bindPopup(element.homecode).addTo(homecodeLayerGroup);
          }
          else if (element.num_redemptions >= 200) {
            L.marker([element.lat, element.lon], {icon: orangeIcon}).bindPopup(element.homecode).addTo(homecodeLayerGroup);
          }
          else if (element.num_redemptions >= 100) {
            L.marker([element.lat, element.lon], {icon: yellowIcon}).bindPopup(element.homecode).addTo(homecodeLayerGroup);
          }
          else if (element.num_redemptions >= 50) {
            L.marker([element.lat, element.lon], {icon: greenIcon}).bindPopup(element.homecode).addTo(homecodeLayerGroup);
          }
          else {
            L.marker([element.lat, element.lon]).bindPopup(element.homecode).addTo(homecodeLayerGroup);
          }
        }
      });
    }
  });

  // Add Branch Locations
  L.marker([42.278355032204445, -83.74590413038366], {icon: purpleIcon}).bindPopup('<strong>Downtown Library</strong><br>343 South Fifth Ave.<br>Building Codes<br>Library Code Stop').addTo(branchLayerGroup);
  L.marker([42.24387568322788, -83.71805381691777], {icon: purpleIcon}).bindPopup('<strong>Malletts Creek Library</strong><br>3090 East Eisenhower Parkway<br>Building Codes<br>Library Code Stop').addTo(branchLayerGroup);
  L.marker([42.25271695126512, -83.77811950157411], {icon: purpleIcon}).bindPopup('<strong>Pittsfield Library</strong><br>2359 Oak Valley Dr.<br>Building Codes<br>Library Code Stop').addTo(branchLayerGroup);
  L.marker([42.30838433760029, -83.71416680157276], {icon: purpleIcon}).bindPopup('<strong>Traverwood Library</strong><br>3333 Traverwood Dr.<br>Building Codes<br>Library Code Stop').addTo(branchLayerGroup);
  L.marker([42.27866255504599, -83.78305954390173], {icon: purpleIcon}).bindPopup('<strong>Westgate Library</strong><br>2503 Jackson Ave.<br>Building Codes<br>Library Code Stop').addTo(branchLayerGroup);

  // Set up layer labels

  // Add layers to map
  var overlayMaps = {
    "Branches <img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-violet.png\" height=\"15px\">": branchLayerGroup,
    "Business Codes <img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png\" height=\"15px\">": bizcodeLayerGroup,
    "Lawn Codes": homecodeLayerGroup,
    "< 3 days old <img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png\" height=\"15px\">": homecodeLayerGroupA,
    "< 7 days old <img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-orange.png\" height=\"15px\">": homecodeLayerGroupB,
    "< 2 weeks old <img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png\" height=\"15px\">": homecodeLayerGroupC,
    "< 3 weeks old <img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png\" height=\"15px\">": homecodeLayerGroupD,
    "> 3 weeks old <img src=\"https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png\" height=\"15px\">": homecodeLayerGroupE,
    "Badge Starting Points": badgeLayerGroup,
  };
  L.control.layers(null, overlayMaps, {collapsed:false}).addTo(myMap);

  myMap.on("overlayremove", function(e){
    if (e.name == 'Lawn Codes') {
      setTimeout(function(){myMap.removeLayer(homecodeLayerGroupA)}, 10);
      setTimeout(function(){myMap.removeLayer(homecodeLayerGroupB)}, 10);
      setTimeout(function(){myMap.removeLayer(homecodeLayerGroupC)}, 10);
      setTimeout(function(){myMap.removeLayer(homecodeLayerGroupD)}, 10);
      setTimeout(function(){myMap.removeLayer(homecodeLayerGroupE)}, 10);
    }
  });

  myMap.on("overlayadd", function(e){
    if (e.name == 'Lawn Codes') {
      setTimeout(function(){myMap.addLayer(homecodeLayerGroupA)}, 10);
      setTimeout(function(){myMap.addLayer(homecodeLayerGroupB)}, 10);
      setTimeout(function(){myMap.addLayer(homecodeLayerGroupC)}, 10);
      setTimeout(function(){myMap.addLayer(homecodeLayerGroupD)}, 10);
      setTimeout(function(){myMap.addLayer(homecodeLayerGroupE)}, 10);
    }
  });
  /*
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
*/
})(jQuery, Drupal);
