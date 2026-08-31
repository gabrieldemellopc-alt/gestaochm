import './bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const iconAliases = {
    'arrow-right-left': 'arrow-left-right', 'bar-chart-3': 'bar-chart',
    'building-2': 'buildings', 'calendar-days': 'calendar-week',
    'check-circle-2': 'check-circle', 'circle-alert': 'exclamation-circle',
    'circle-check': 'check-circle', 'circle-check-big': 'check-circle',
    'circle-dot': 'circle', 'clock-3': 'clock', 'edit-3': 'pencil',
    'external-link': 'box-arrow-up-right', 'file-text': 'file-earmark-text',
    'fuel': 'fuel-pump', 'gauge': 'speedometer2', 'history': 'clock-history',
    'map-pin': 'geo-alt', 'plus': 'plus-lg', 'refresh-cw': 'arrow-clockwise',
    'rotate-ccw': 'arrow-counterclockwise', 'save': 'floppy',
    'search-x': 'search', 'settings-2': 'sliders', 'trash-2': 'trash',
    'triangle-alert': 'exclamation-triangle', 'user-round': 'person',
    'users': 'people', 'wallet': 'wallet2', 'wrench': 'wrench-adjustable',
    'x': 'x-lg',
};

window.chmIconClass = (name) => 'bi bi-' + (iconAliases[name] || name || 'question-circle');

Alpine.start();
