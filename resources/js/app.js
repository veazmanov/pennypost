import './bootstrap';

import Alpine from 'alpinejs';
import compose from './compose';

window.Alpine = Alpine;

Alpine.data('compose', compose);

Alpine.start();
