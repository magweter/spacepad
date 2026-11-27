// Mark an alert as done for this user. The id goes into `doneAlerts` at once, so the popup and the
// dashboard both drop it straight away; the server is told in the background. Not an Inertia visit:
// a visit is cancelled when the next one starts, and a dismissal must not get lost when someone
// clicks on right away.
import { ref } from 'vue';
import { requestJson } from '../Composables/http';
import { toastError } from './Toasts/toasts';

export const doneAlerts = ref([]);

export function dismissAlert(alert) {
    if (!doneAlerts.value.includes(alert.id)) doneAlerts.value = [...doneAlerts.value, alert.id];
    return requestJson('POST', alert.dismissUrl).catch(() => toastError('The message could not be closed for good. It may show again.'));
}
