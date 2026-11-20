// ScaffoldGauge's script, made by bin/aic make:component --script (#315).
// Tessera imports it on a page whose markup holds the component, calls
// mount() with the element and its data-mount-data, mounts again when the
// data changes, and calls the returned cleanup and unmount() before the
// element goes.

export function mount(element, data) {
    const shown = document.createElement('pre');
    shown.textContent = JSON.stringify(data);
    element.append(shown);

    return () => {
        shown.remove();
    };
}

export function unmount() {
}
