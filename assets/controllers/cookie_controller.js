import { Controller } from '@hotwired/stimulus';

const MAX_AGE = 60 * 60 * 24 * 365;

const loadValue = (name) => {
  const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
  return match ? decodeURIComponent(match[2]) : null;
};

const getStoredValue = (name) => {
  try {
    const localValue = window.localStorage.getItem(name);
    if (localValue !== null) {
      return localValue;
    }
  } catch (error) {
    // ignore storage errors
  }

  return loadValue(name);
};

const setStoredValue = (name, value) => {
  try {
    window.localStorage.setItem(name, String(value));
  } catch (error) {
    // ignore storage errors
  }
  document.cookie = `${name}=${encodeURIComponent(String(value))}; path=/; max-age=${MAX_AGE}; samesite=lax`;
};

export default class extends Controller {
  static targets = ['choices', 'toggle'];
  static values = {
    googleAnalyticsId: String,
    googleAdsenseClientId: String,
  };

  connect() {
    this.hideChoices();
    this.hide();

    if (this.shouldShow()) {
      this.show();
      this.syncToggles();
      return;
    }

    this.applyConsent();
  }

  accept() {
    setStoredValue('cookiesModal', true);
    setStoredValue('cookiesGoogle', true);
    setStoredValue('cookiesAdsense', true);
    this.applyConsent();
    this.hide();
  }

  refuse() {
    setStoredValue('cookiesModal', true);
    setStoredValue('cookiesGoogle', false);
    setStoredValue('cookiesAdsense', false);
    this.applyConsent();
    this.hide();
  }

  customize() {
    if (this.hasChoicesTarget) {
      this.choicesTarget.classList.add('active');
    }
    this.syncToggles();
  }

  confirm() {
    setStoredValue('cookiesModal', true);
    this.toggleTargets.forEach((toggle) => {
      const key = toggle.dataset.cookieKeyValue;
      if (!key) {
        return;
      }
      setStoredValue(key, toggle.checked);
    });
    this.applyConsent();
    this.hide();
  }

  syncToggles() {
    this.toggleTargets.forEach((toggle) => {
      const key = toggle.dataset.cookieKeyValue;
      if (!key) {
        return;
      }
      const value = getStoredValue(key);
      toggle.checked = value === 'true';
    });
  }

  applyConsent() {
    const googleEnabled = getStoredValue('cookiesGoogle') === 'true';
    const adsenseEnabled = getStoredValue('cookiesAdsense') === 'true';

    if (googleEnabled) {
      this.createGoogleAnalyticsScript();
    } else {
      setStoredValue('cookiesGoogle', false);
      this.removeGoogleAnalyticsScript();
    }

    if (adsenseEnabled) {
      this.createGoogleAdsenseScript();
    } else {
      setStoredValue('cookiesAdsense', false);
      this.removeGoogleAdsenseScript();
    }
  }

  shouldShow() {
    const hasModal = getStoredValue('cookiesModal');
    const hasGoogle = getStoredValue('cookiesGoogle');
    const hasAdsense = getStoredValue('cookiesAdsense');
    return !(hasModal || hasGoogle || hasAdsense);
  }

  show() {
    this.element.classList.add('active');
    if (this.hasChoicesTarget) {
      this.choicesTarget.classList.remove('active');
    }
  }

  hide() {
    this.element.classList.remove('active');
    this.hideChoices();
  }

  hideChoices() {
    if (this.hasChoicesTarget) {
      this.choicesTarget.classList.remove('active');
    }
  }

  createGoogleAnalyticsScript() {
    if (!this.googleAnalyticsIdValue) {
      return;
    }

    if (!document.getElementById('google-analytics-init')) {
      const scriptInit = document.createElement('script');
      scriptInit.async = true;
      scriptInit.src = `https://www.googletagmanager.com/gtag/js?id=${this.googleAnalyticsIdValue}`;
      scriptInit.id = 'google-analytics-init';
      document.head.appendChild(scriptInit);
    }

    const existing = document.getElementById('google-analytics');
    if (existing) {
      existing.remove();
    }

    const consentSettings = {
      ad_storage: 'granted',
      ad_user_data: 'granted',
      ad_personalization: 'granted',
      analytics_storage: 'granted',
    };

    const script = document.createElement('script');
    script.id = 'google-analytics';
    script.textContent = `
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('config', '${this.googleAnalyticsIdValue}', {
        page_path: window.location.pathname
      });
      gtag('consent', "update", ${JSON.stringify(consentSettings)});
      gtag('js', new Date());
    `;
    document.head.appendChild(script);
  }

  removeGoogleAnalyticsScript() {
    const init = document.getElementById('google-analytics-init');
    const inline = document.getElementById('google-analytics');
    if (init) {
      init.remove();
    }
    if (inline) {
      inline.remove();
    }
    if (window.dataLayer) {
      window.dataLayer = [];
    }
    if (window.gtag) {
      window.gtag = null;
    }
  }

  createGoogleAdsenseScript() {
    if (!this.googleAdsenseClientIdValue) {
      return;
    }
    if (document.getElementById('google-adsense')) {
      return;
    }

    const scriptAdsense = document.createElement('script');
    scriptAdsense.async = true;
    scriptAdsense.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${this.googleAdsenseClientIdValue}`;
    scriptAdsense.id = 'google-adsense';
    scriptAdsense.crossOrigin = 'anonymous';
    document.head.appendChild(scriptAdsense);
  }

  removeGoogleAdsenseScript() {
    const adsense = document.getElementById('google-adsense');
    if (adsense) {
      adsense.remove();
    }
  }
}
