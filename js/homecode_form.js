(function ($, Drupal) {
  var lookupBtn = document.getElementById('edit-lookup-address');
  lookupBtn.addEventListener('click', function() {
    geocode_address(document.getElementById('edit-street').value.trim(), document.getElementById('edit-zip').value.trim());
  }, false);

  // Build marker layers
  var homecodeLayerGroup = new L.layerGroup();

  var myMap = L.map('mapid', {
      center: [42.2781734, -83.74570792114082],
      zoom: 13,
      layers: [homecodeLayerGroup]
  });

  var redIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(myMap);

  myMap.on('click', onMapClick);

  function geocode_address(street, zip) {
    if (street.search(/[\d].* .+/) == -1) {
      setMapError("*Please enter street number and street name");
    }
    else if (zip == '') {
      setMapError("*Please enter zip code");
    }
    else {
      // Pull data from geocode service
      $.ajax({
        type: 'GET',
        url: '/summergame/geocode/' + street + ' ' + zip,
        dataType: 'json',
        success: function (data) {
          if (data.status == 'OK') {
            // parse address components
            var result = data.results[0];
            var formatted_address = result.formatted_address.replace(', USA', '');
            formatted_address = formatted_address.replace(', ', '<br>');
            var pos = formatted_address.lastIndexOf(' ');
            formatted_address = formatted_address.substring(0,pos) + '<br>' + formatted_address.substring(pos+1)

            document.querySelector('[data-drupal-selector="edit-formatted"]').value = formatted_address;
            document.querySelector('[data-drupal-selector="edit-lat"]').value = result.geometry.location.lat;
            document.querySelector('[data-drupal-selector="edit-lon"]').value = result.geometry.location.lng;

            for (var i = 0; i < result.address_components.length; i++) {
              for (var j = 0; j < result.address_components[i].types.length; j++) {
                if (result.address_components[i].types[j] == "route") {
                  document.querySelector('[data-drupal-selector="edit-route"]').value = result.address_components[i].short_name;
                }
              }
            }

            // Make map visible
            document.getElementById("map-error").className = "visually-hidden";
            document.getElementById("map-wrapper").className = "";

            // Clear existing markers, center map and zoom in
            homecodeLayerGroup.clearLayers();
            myMap.invalidateSize();
            myMap.setView([result.geometry.location.lat, result.geometry.location.lng], 20);
            L.marker([result.geometry.location.lat, result.geometry.location.lng], {icon: redIcon}).bindPopup(formatted_address).addTo(homecodeLayerGroup);

            // Show actions section
            showActions();
          }
          else {
            setMapError("*Error looking up address, please try again");
          }
        }
      });
    }
  }

  function setMapError(message) {
    // Hide map and actions
    document.getElementById("map-wrapper").className = "visually-hidden";
    document.getElementById("homecode-form-actions").className = "visually-hidden";

    // show error message
    document.getElementById("map-error").innerHTML = message;
    document.getElementById("map-error").className = "";
  }

  function onMapClick(e) {
    // Update form elements with clicked location
    document.querySelector('[data-drupal-selector="edit-lat"]').value = e.latlng.lat;
    document.querySelector('[data-drupal-selector="edit-lon"]').value = e.latlng.lng;

    // Clear existing markers
    homecodeLayerGroup.clearLayers();

    // Center map and zoom in
    myMap.setView(e.latlng, 20);
    var formatted_address = document.querySelector('[data-drupal-selector="edit-formatted"]').value;
    L.marker(e.latlng, {icon: redIcon}).bindPopup(formatted_address).addTo(homecodeLayerGroup);
  }

})(jQuery, Drupal);

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

function showActions() {
  document.getElementById("homecode-form-actions").className = "sg-form-actions";
}

window.onload = checkCodeType;
