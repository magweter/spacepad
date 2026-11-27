// The types an admin can give an alert: the label on its tag, the colours of the row and of the
// chip in the admin list. Status is colour plus word, so every tone carries its own label.
// `attention` counts it in the overview's "things need your attention".
export const alertTones = {
    info: { label: 'Info', row: 'border-info-border bg-info-bg text-info', chip: 'info', attention: false },
    new: { label: 'New', row: 'border-info-border bg-info-bg text-info', chip: 'info', attention: false },
    maintenance: { label: 'Maintenance', row: 'border-offline-border bg-offline-bg text-ink-2', chip: 'neutral', attention: false },
    attention: { label: 'Attention', row: 'border-checkin bg-checkin-bg text-checkin-text', chip: 'amber', attention: true },
    service_degradation: { label: 'Service degradation', row: 'border-reserved-border bg-reserved-bg text-reserved-text', chip: 'reserved', attention: true },
    action_required: { label: 'Action required', row: 'border-reserved-border bg-reserved-bg text-reserved-text', chip: 'reserved', attention: true },
    resolved: { label: 'Resolved', row: 'border-available-border bg-available-bg text-available-text', chip: 'available', attention: false },
};

export const toneOf = (tone) => alertTones[tone] ?? alertTones.attention;
