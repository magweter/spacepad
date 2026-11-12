// Opens the upgrade dialog from anywhere in the portal; `feature` picks what it shows first.
export function openUpgrade(feature = 'displays') {
    window.dispatchEvent(new CustomEvent('open-upgrade', { detail: { feature } }));
}
