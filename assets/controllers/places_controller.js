import { Controller } from '@hotwired/stimulus';

let loadScriptPromise = null;

export default class extends Controller {
  static targets = ['address'];
  static values = {
    apiKey: String,
  };

  connect() {
    if (!this.hasAddressTarget) {
      return;
    }

    if (!this.apiKeyValue) {
      console.warn('GOOGLE_PLACES_API_KEY manquante');
      return;
    }

    this.loadScript()
      .then(() => this.mountAutocomplete())
      .catch(() => {
        console.warn('Échec du chargement Google Places.');
      });
  }

  loadScript() {
    if (window.google && window.google.maps && window.google.maps.places) {
      return Promise.resolve();
    }

    if (loadScriptPromise) {
      return loadScriptPromise;
    }

    loadScriptPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.async = true;
      script.defer = true;
      script.src = `https://maps.googleapis.com/maps/api/js?key=${this.apiKeyValue}&libraries=places`;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });

    return loadScriptPromise;
  }

  mountAutocomplete() {
    const input = this.addressTarget;
    if (!input) {
      return;
    }

    const autocomplete = new google.maps.places.Autocomplete(input, {
      types: ['address'],
      componentRestrictions: { country: 'fr' },
    });

    autocomplete.addListener('place_changed', () => {
      const place = autocomplete.getPlace();
      if (!place || !place.address_components) {
        return;
      }

      const components = {};
      place.address_components.forEach((component) => {
        component.types.forEach((type) => {
          components[type] = component;
        });
      });

      const city =
        components.locality?.long_name ||
        components.postal_town?.long_name ||
        components.administrative_area_level_3?.long_name ||
        '';
      const dept     = components.administrative_area_level_2?.long_name || '';
      const deptCode = components.administrative_area_level_2?.short_name || '';
      const postal   = components.postal_code?.long_name || '';
      const lat      = place.geometry?.location?.lat?.() ?? '';
      const lng      = place.geometry?.location?.lng?.() ?? '';

      // Target-based (EasyAdmin & future forms)
      this.setTarget('placeId',        place.place_id);
      this.setTarget('lat',            lat);
      this.setTarget('lng',            lng);
      this.setTarget('postalCode',     postal);
      this.setTarget('cityName',       city);
      this.setTarget('departmentName', dept);
      this.setTarget('departmentCode', deptCode);

      // Name-based fallback (registration form — keeps backward compatibility)
      this.setField('place_id',        place.place_id);
      this.setField('lat',             lat);
      this.setField('lng',             lng);
      this.setField('postal_code',     postal);
      this.setField('city_name',       city);
      this.setField('department_name', dept);
      this.setField('department_code', deptCode);
    });
  }

  /** Set a field by data-places-target attribute. */
  setTarget(target, value) {
    const el = this.element.querySelector(`[data-places-target="${target}"]`);
    if (el) {
      el.value = value ?? '';
    }
  }

  /** Set a field by its name ending (e.g. "form[place_id]"). */
  setField(name, value) {
    const el = this.element.querySelector(`[name$="[${name}]"]`);
    if (el) {
      el.value = value ?? '';
    }
  }
}
