(function ($, Drupal, drupalSettings) {

  console.log(drupalSettings);

  var schoolsLayerGroup = new L.layerGroup();

  var schoolMap = L.map('aaps-map', {
      center: [42.2781734, -83.74570792114082],
      zoom: 12,
      layers: [schoolsLayerGroup]
  });

  var blueIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(schoolMap);

  for(let i = 0 ; i < drupalSettings.data.length;i++){

    var bIcon = blueIcon;
    L.marker([drupalSettings.data[i].latitude, drupalSettings.data[i].longitude], {icon: bIcon}).bindPopup(`<b><a href="/summergame/aaps/school/${drupalSettings.data[i].school_image}">${drupalSettings.data[i].label}</a></b>`, {autoClose: false}).addTo(schoolsLayerGroup).openPopup();

  }

  schoolMap.panTo([42.2781734, -83.74570792114082]);

})(jQuery, Drupal, drupalSettings);