// resources/js/Globals/helpers.js
export function registerGlobalHelpers(app) {
  const yearLevel = (year_level) => {
    if(year_level == 0){ return 'N/A (No Level)'}
    if (!year_level) return '-'
    switch (parseInt(year_level)) {
      case 0: return 'N/A (No Level)'
      case 1: return 'First Year'
      case 2: return 'Second Year'
      case 3: return 'Third Year'
      case 4: return 'Fourth Year'
      default: return '-'
    }
  }

  const address = (data) => {
    var _address = ""

    _address = (data.address1 || '') + " " + (data.barangay || '') + " " + (data.municipality || '') + (data.zipcode ? ", " + data.zipcode : "") + (data.province ? ", " + data.province : "")

    return _address;
  }

  const addressPB = (data) => {
    var _address = ""

    _address = (data.pb_street || '') + " " + (data.pb_house_number || '') + " " + (data.pb_barangay || '') + " " + (data.pb_municipality || '') + (data.pb_zipcode ? ", " + data.pb_zipcode : "") + (data.pb_province ? ", " + data.pb_province : "")

    return _address;
  }

  const fullName = (data) => {
    var _full_name = ""

    _full_name = (data.last_name || '') + ", " + (data.first_name || '') + " " + (data.middle_name ? " "+data.middle_name[0]+"." : "") + (data.name_extension ? " "+data.name_extension : "")

    return capitalizeWords(_full_name);
  }

  function capitalizeWords(sentence) {
    return sentence
      .split(' ')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
      .join(' ');
  }

  function disasterName(id) {

     var index = disaster_types
        .map((x) => {
          return x.id
        })
        .indexOf(id)
    
    if(index <0){
      return '';
    }

    return disaster_types[index].name;
  }

  function disasterUnit(id) {

     var index = disaster_types
        .map((x) => {
          return x.id
        })
        .indexOf(id)
    
    if(index <0){
      return '';
    }

    return disaster_types[index].unit;
  }

  const disaster_types = [
  // 🌪️ Hydro-Meteorological Hazards
  { "id": 1,  "name": "Typhoon", "parameter": "Wind Speed", "unit": "km/h" },
  { "id": 2,  "name": "Tropical Depression", "parameter": "Wind Speed", "unit": "km/h" },
  { "id": 3,  "name": "Flood", "parameter": "Water Level / Flow Rate", "unit": "m / m³/s" },
  { "id": 4,  "name": "Storm Surge", "parameter": "Wave Height", "unit": "m" },
  { "id": 5,  "name": "Heavy Rainfall", "parameter": "Rainfall Amount", "unit": "mm/hr" },
  { "id": 6,  "name": "Drought", "parameter": "Rainfall Deficit / Duration", "unit": "%" },
  { "id": 7,  "name": "Thunderstorm", "parameter": "Rainfall Intensity", "unit": "mm/hr" },
  { "id": 8,  "name": "Tornado", "parameter": "Wind Speed", "unit": "km/h" },
  { "id": 9,  "name": "El Niño Phenomenon", "parameter": "Sea Surface Temperature Anomaly", "unit": "°C" },
  { "id": 10, "name": "La Niña Phenomenon", "parameter": "Sea Surface Temperature Anomaly", "unit": "°C" },

  // 🌋 Geologic Hazards
  { "id": 11, "name": "Earthquake", "parameter": "Magnitude / Intensity", "unit": "Mw / PEIS" },
  { "id": 12, "name": "Tsunami", "parameter": "Wave Height", "unit": "m" },
  { "id": 13, "name": "Volcanic Eruption", "parameter": "Volcanic Explosivity Index", "unit": "VEI" },
  { "id": 14, "name": "Landslide", "parameter": "Volume of Displaced Material", "unit": "m³" },
  { "id": 15, "name": "Ground Subsidence", "parameter": "Vertical Displacement", "unit": "cm/year" },
  { "id": 16, "name": "Liquefaction", "parameter": "Soil Saturation / Settlement", "unit": "%" },

  // 🦠 Biological Hazards
  { "id": 17, "name": "Epidemic", "parameter": "Infection Rate", "unit": "cases/day" },
  { "id": 18, "name": "Pandemic", "parameter": "Infection Rate", "unit": "cases/day" },
  { "id": 19, "name": "Animal Infestation", "parameter": "Population Density", "unit": "animals/ha" },
  { "id": 20, "name": "Pest Infestation", "parameter": "Crop Damage", "unit": "%" },
  { "id": 21, "name": "Plant Disease Outbreak", "parameter": "Infection Rate", "unit": "plants/ha" },

  // 🔥 Environmental Hazards
  { "id": 22, "name": "Forest Fire", "parameter": "Burned Area", "unit": "ha" },
  { "id": 23, "name": "Grass Fire", "parameter": "Burned Area", "unit": "ha" },
  { "id": 24, "name": "Algal Bloom", "parameter": "Cell Density", "unit": "cells/mL" },
  { "id": 25, "name": "Fish Kill", "parameter": "Dissolved Oxygen", "unit": "mg/L" },
  { "id": 26, "name": "Water Pollution Event", "parameter": "Contaminant Concentration", "unit": "mg/L" }
];

  // ✅ Attach both to globalThis and Vue app

  globalThis.disasterName = disasterName
  app.config.globalProperties.$disasterName = disasterName

  globalThis.disasterUnit = disasterUnit
  app.config.globalProperties.$disasterUnit = disasterUnit

  globalThis.disaster_types = disaster_types
  app.config.globalProperties.$disaster_types = disaster_types

  globalThis.addressPB = addressPB
  app.config.globalProperties.$addressPB = addressPB


  globalThis.fullName = fullName
  app.config.globalProperties.$fullName = fullName

  globalThis.yearLevel = yearLevel
  app.config.globalProperties.$yearLevel = yearLevel

  globalThis.address = address
  app.config.globalProperties.$address = address

  globalThis.capitalizeWords = capitalizeWords
  app.config.globalProperties.$capitalizeWords = capitalizeWords
}
