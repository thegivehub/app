(function () {
  class LocationBlock extends HTMLElement {
  	constructor(props) {
  		super(props);
      // this.render();
    }

    connectedCallback() {
      this.render();
    }
    
    static get observedAttributes() {
      return ['location-country', 'location-region', 'location-coordinates'];
    }
    attributeChangedCallback(name, oldValue, newValue) {
      if (name === 'location-country') {
        this.country = newValue;
      } else if (name === 'location-region') {
        this.region = newValue;
      } else if (name === 'location-coordinates') {
        this.coordinates = newValue;
      }
    }
    // List of UN countries
    // This list is based on the ISO 3166-1 alpha-2 codes
    // and includes all member states of the United Nations
    // as well as some observer states and other territories
    // The list is sorted alphabetically by country name
    // You can find the full list of countries and their codes
    // at https://www.unece.org/fileadmin/DAM/cefact/codesfortrade/CountryCodes/ISO3166-1_alpha-2_country_codes.txt
    // or https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes
    // Note: This list may not be up to date with the latest changes
    // in country codes or names. Please verify with the official sources
    // if you need the most accurate and up-to-date information.
    // This list is not exhaustive and may not include all countries
    // or territories. Please verify with the official sources
    // if you need the most accurate and up-to-date information.

    init() {
      const unCountries = [
        { code: "AF", name: "Afghanistan" },
        { code: "AL", name: "Albania" },
        { code: "DZ", name: "Algeria" },
        { code: "AD", name: "Andorra" },
        { code: "AO", name: "Angola" },
        { code: "AG", name: "Antigua and Barbuda" },
        { code: "AR", name: "Argentina" },
        { code: "AM", name: "Armenia" },
        { code: "AU", name: "Australia" },
        { code: "AT", name: "Austria" },
        { code: "AZ", name: "Azerbaijan" },
        { code: "BS", name: "Bahamas" },
        { code: "BH", name: "Bahrain" },
        { code: "BD", name: "Bangladesh" },
        { code: "BB", name: "Barbados" },
        { code: "BY", name: "Belarus" },
        { code: "BE", name: "Belgium" },
        { code: "BZ", name: "Belize" },
        { code: "BJ", name: "Benin" },
        { code: "BT", name: "Bhutan" },
        { code: "BO", name: "Bolivia" },
        { code: "BA", name: "Bosnia and Herzegovina" },
        { code: "BW", name: "Botswana" },
        { code: "BR", name: "Brazil" },
        { code: "BN", name: "Brunei Darussalam" },
        { code: "BG", name: "Bulgaria" },
        { code: "BF", name: "Burkina Faso" },
        { code: "BI", name: "Burundi" },
        { code: "CV", name: "Cabo Verde" },
        { code: "KH", name: "Cambodia" },
        { code: "CM", name: "Cameroon" },
        { code: "CA", name: "Canada" },
        { code: "CF", name: "Central African Republic" },
        { code: "TD", name: "Chad" },
        { code: "CL", name: "Chile" },
        { code: "CN", name: "China" },
        { code: "CO", name: "Colombia" },
        { code: "KM", name: "Comoros" },
        { code: "CG", name: "Congo" },
        { code: "CD", name: "Congo, Democratic Republic of the" },
        { code: "CR", name: "Costa Rica" },
        { code: "CI", name: "Côte d'Ivoire" },
        { code: "HR", name: "Croatia" },
        { code: "CU", name: "Cuba" },
        { code: "CY", name: "Cyprus" },
        { code: "CZ", name: "Czechia" },
        { code: "DK", name: "Denmark" },
        { code: "DJ", name: "Djibouti" },
        { code: "DM", name: "Dominica" },
        { code: "DO", name: "Dominican Republic" },
        { code: "EC", name: "Ecuador" },
        { code: "EG", name: "Egypt" },
        { code: "SV", name: "El Salvador" },
        { code: "GQ", name: "Equatorial Guinea" },
        { code: "ER", name: "Eritrea" },
        { code: "EE", name: "Estonia" },
        { code: "SZ", name: "Eswatini" },
        { code: "ET", name: "Ethiopia" },
        { code: "FJ", name: "Fiji" },
        { code: "FI", name: "Finland" },
        { code: "FR", name: "France" },
        { code: "GA", name: "Gabon" },
        { code: "GM", name: "Gambia" },
        { code: "GE", name: "Georgia" },
        { code: "DE", name: "Germany" },
        { code: "GH", name: "Ghana" },
        { code: "GR", name: "Greece" },
        { code: "GD", name: "Grenada" },
        { code: "GT", name: "Guatemala" },
        { code: "GN", name: "Guinea" },
        { code: "GW", name: "Guinea-Bissau" },
        { code: "GY", name: "Guyana" },
        { code: "HT", name: "Haiti" },
        { code: "HN", name: "Honduras" },
        { code: "HU", name: "Hungary" },
        { code: "IS", name: "Iceland" },
        { code: "IN", name: "India" },
        { code: "ID", name: "Indonesia" },
        { code: "IR", name: "Iran" },
        { code: "IQ", name: "Iraq" },
        { code: "IE", name: "Ireland" },
        { code: "IL", name: "Israel" },
        { code: "IT", name: "Italy" },
        { code: "JM", name: "Jamaica" },
        { code: "JP", name: "Japan" },
        { code: "JO", name: "Jordan" },
        { code: "KZ", name: "Kazakhstan" },
        { code: "KE", name: "Kenya" },
        { code: "KI", name: "Kiribati" },
        { code: "KP", name: "Korea, Democratic People's Republic of" },
        { code: "KR", name: "Korea, Republic of" },
        { code: "KW", name: "Kuwait" },
        { code: "KG", name: "Kyrgyzstan" },
        { code: "LA", name: "Lao People's Democratic Republic" },
        { code: "LV", name: "Latvia" },
        { code: "LB", name: "Lebanon" },
        { code: "LS", name: "Lesotho" },
        { code: "LR", name: "Liberia" },
        { code: "LY", name: "Libya" },
        { code: "LI", name: "Liechtenstein" },
        { code: "LT", name: "Lithuania" },
        { code: "LU", name: "Luxembourg" },
        { code: "MG", name: "Madagascar" },
        { code: "MW", name: "Malawi" },
        { code: "MY", name: "Malaysia" },
        { code: "MV", name: "Maldives" },
        { code: "ML", name: "Mali" },
        { code: "MT", name: "Malta" },
        { code: "MH", name: "Marshall Islands" },
        { code: "MR", name: "Mauritania" },
        { code: "MU", name: "Mauritius" },
        { code: "MX", name: "Mexico" },
        { code: "FM", name: "Micronesia, Federated States of" },
        { code: "MD", name: "Moldova" },
        { code: "MC", name: "Monaco" },
        { code: "MN", name: "Mongolia" },
        { code: "ME", name: "Montenegro" },
        { code: "MA", name: "Morocco" },
        { code: "MZ", name: "Mozambique" },
        { code: "MM", name: "Myanmar" },
        { code: "NA", name: "Namibia" },
        { code: "NR", name: "Nauru" },
        { code: "NP", name: "Nepal" },
        { code: "NL", name: "Netherlands" },
        { code: "NZ", name: "New Zealand" },
        { code: "NI", name: "Nicaragua" },
        { code: "NE", name: "Niger" },
        { code: "NG", name: "Nigeria" },
        { code: "MK", name: "North Macedonia" },
        { code: "NO", name: "Norway" },
        { code: "OM", name: "Oman" },
        { code: "PK", name: "Pakistan" },
        { code: "PW", name: "Palau" },
        { code: "PA", name: "Panama" },
        { code: "PG", name: "Papua New Guinea" },
        { code: "PY", name: "Paraguay" },
        { code: "PE", name: "Peru" },
        { code: "PH", name: "Philippines" },
        { code: "PL", name: "Poland" },
        { code: "PT", name: "Portugal" },
        { code: "QA", name: "Qatar" },
        { code: "RO", name: "Romania" },
        { code: "RU", name: "Russian Federation" },
        { code: "RW", name: "Rwanda" },
        { code: "KN", name: "Saint Kitts and Nevis" },
        { code: "LC", name: "Saint Lucia" },
        { code: "VC", name: "Saint Vincent and the Grenadines" },
        { code: "WS", name: "Samoa" },
        { code: "SM", name: "San Marino" },
        { code: "ST", name: "Sao Tome and Principe" },
        { code: "SA", name: "Saudi Arabia" },
        { code: "SN", name: "Senegal" },
        { code: "RS", name: "Serbia" },
        { code: "SC", name: "Seychelles" },
        { code: "SL", name: "Sierra Leone" },
        { code: "SG", name: "Singapore" },
        { code: "SK", name: "Slovakia" },
        { code: "SI", name: "Slovenia" },
        { code: "SB", name: "Solomon Islands" },
        { code: "SO", name: "Somalia" },
        { code: "ZA", name: "South Africa" },
        { code: "SS", name: "South Sudan" },
        { code: "ES", name: "Spain" },
        { code: "LK", name: "Sri Lanka" },
        { code: "SD", name: "Sudan" },
        { code: "SR", name: "Suriname" },
        { code: "SE", name: "Sweden" },
        { code: "CH", name: "Switzerland" },
        { code: "SY", name: "Syrian Arab Republic" },
        { code: "TJ", name: "Tajikistan" },
        { code: "TZ", name: "Tanzania" },
        { code: "TH", name: "Thailand" },
        { code: "TL", name: "Timor-Leste" },
        { code: "TG", name: "Togo" },
        { code: "TO", name: "Tonga" },
        { code: "TT", name: "Trinidad and Tobago" },
        { code: "TN", name: "Tunisia" },
        { code: "TR", name: "Türkiye" },
        { code: "TM", name: "Turkmenistan" },
        { code: "TV", name: "Tuvalu" },
        { code: "UG", name: "Uganda" },
        { code: "UA", name: "Ukraine" },
        { code: "AE", name: "United Arab Emirates" },
        { code: "GB", name: "United Kingdom" },
        { code: "US", name: "United States of America" },
        { code: "UY", name: "Uruguay" },
        { code: "UZ", name: "Uzbekistan" },
        { code: "VU", name: "Vanuatu" },
        { code: "VE", name: "Venezuela" },
        { code: "VN", name: "Viet Nam" },
        { code: "YE", name: "Yemen" },
        { code: "ZM", name: "Zambia" },
        { code: "ZW", name: "Zimbabwe" }
      ];
      unCountries.sort((a, b) => a.name.localeCompare(b.name));
      const selectElement = document.getElementById('location-country');
      unCountries.forEach(country => {
        const option = document.createElement('option');
        option.value = country.code;
        option.textContent = country.name;
        selectElement.appendChild(option);
      });
    }
    getCurrentLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(position => {
                const lat = Math.round(position.coords.latitude * 1000)/1000;
                const lon = Math.round(position.coords.longitude * 1000)/1000;
                fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`)
                    .then(response => response.json())
                    .then(data => {
                      console.log(data);
                        if (data && data.address && data.address.country_code) {
                            const countryCode = data.address.country_code.toUpperCase();
                            const countrySelect = document.getElementById('location-country');
                            countrySelect.value = countryCode; // Set the selected country
                        }
                        if (data && data.display_name) {
                            document.getElementById('location-region').value = data.display_name.substring(0,data.display_name.lastIndexOf(',')); // Set the region if available
                        }
                        // Optionally, you can also set the coordinates
                        document.getElementById('location-latitude').value = lat;
                        document.getElementById('location-longitude').value = lon;
                    })
                    .catch(error => console.error('Error fetching location:', error));
            }, error => {
                console.error('Error getting location:', error);
            });
        } else {
            alert("Geolocation is not supported by this browser.");
        }
    }
              
    returnString() {
      let newDiv = document.createElement('div');
      let newDivHTML = `
      <div class="location-block">
        <div class="form-group">
          <div>
            <span style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0.5rem;float: right">
              <button type="button" id="useCurrentLocation" style="padding: 0.5rem; font-size: 0.875rem; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer;">
                Use Current Location
              </button>
            </span>
            <h2>Location</h2>
          </div>
          <label for="location-country">Country</label>
           <select name="location-country" id="location-country">
              <option value="">-- Select a country --</option>
          </select>

          <!-- <input type="text" id="location-country" placeholder="e.g., United States"> -->
          <div class="help-text">Enter the country where this campaign is based</div>
        </div>

        <div class="form-group">
          <label for="location-region">Region/City</label>
          <input type="text" id="location-region" placeholder="e.g., New York">
          <div class="help-text">Enter the state, province, or city for this campaign</div>
        </div>

        <div class="form-group">
          <label for="location-coordinates">Coordinates (Optional)</label>
          <div style="display: flex; gap: 10px;">
            <input type="number" id="location-latitude" placeholder="Latitude" step="0.001">
            <input type="number" id="location-longitude" placeholder="Longitude" step="0.001">
          </div>
          <div class="help-text">Optional: Enter precise coordinates for campaign map</div>
        </div>
      </div>`;
      newDiv.innerHTML = newDivHTML;
      return newDiv;
    }

  	render() {
      console.log('rendered');
      document.querySelector('#location-block').appendChild(this.returnString());
      this.init();
      const useCurrentLocationButton = document.getElementById('useCurrentLocation');
      if (useCurrentLocationButton) {
        useCurrentLocationButton.addEventListener('click', () => {
          this.getCurrentLocation();
        });
      }
      const countrySelect = document.getElementById('location-country');
      if (countrySelect) {
        countrySelect.addEventListener('change', () => {
          this.setAttribute('location-country', countrySelect.value);
        });
      }
      const regionInput = document.getElementById('location-region');
      if (regionInput) {
        regionInput.addEventListener('input', () => {
          this.setAttribute('location-region', regionInput.value);
        });
      }
  	}
  }

	window.customElements.define('location-block', LocationBlock);
})();
