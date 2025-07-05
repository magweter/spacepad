import axios from 'axios';
import htmx from 'htmx.org';

window.axios = axios;
window.htmx = htmx;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
