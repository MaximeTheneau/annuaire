import { startStimulusApp } from '@symfony/stimulus-bundle';
import CookieController from './controllers/cookie_controller.js';
import MapController from './controllers/map_controller.js';
import PlacesController from './controllers/places_controller.js';

const app = startStimulusApp();
app.register('cookie', CookieController);
app.register('map', MapController);
app.register('places', PlacesController);
