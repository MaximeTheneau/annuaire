import { Controller } from '@hotwired/stimulus';

let loadScriptPromise = null;

export default class extends Controller {
  static values = {
    markers: Array,
    apiKey: String,
    center: Object,
    zoom: Number,
  };

  connect() {
    if (!this.apiKeyValue) {
      console.warn('GOOGLE_PLACES_API_KEY manquante');
      return;
    }

    this.loadScript()
      .then(() => this.initializeMap())
      .catch(() => {
        console.warn('Échec du chargement de la carte.');
      });
  }

  loadScript() {
    if (window.google && window.google.maps) {
      return Promise.resolve();
    }

    if (loadScriptPromise) {
      return loadScriptPromise;
    }

    loadScriptPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${this.apiKeyValue}`;
      script.async = true;
      script.defer = true;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });

    return loadScriptPromise;
  }

  initializeMap() {
    const markers = this.markersValue || [];

    const defaultCenter = { lat: 46.2276, lng: 2.2137 };
    const center = this.hasCenterValue
      ? this.centerValue
      : markers.length
        ? { lat: Number(markers[0].lat), lng: Number(markers[0].lng) }
        : defaultCenter;

    const zoom = this.hasZoomValue
      ? this.zoomValue
      : markers.length > 1
        ? 6
        : 12;

    const map = new google.maps.Map(this.element, {
      center,
      zoom,
      mapTypeControl: false,
      streetViewControl: false,
    });

    if (!markers.length) {
      return;
    }

    const bounds = new google.maps.LatLngBounds();

    markers.forEach((marker) => {
      const position = { lat: Number(marker.lat), lng: Number(marker.lng) };
      const pin = new google.maps.Marker({
        position,
        map,
        title: marker.name || '',
      });
      bounds.extend(position);

      if (marker.name) {
        const content = marker.url
          ? `<a href="${marker.url}">${marker.name}</a>`
          : marker.name;
        const info = new google.maps.InfoWindow({ content });
        pin.addListener('click', () => {
          info.open({ anchor: pin, map });
        });
      }
    });

    if (markers.length > 1) {
      map.fitBounds(bounds);
    }
  }
}
