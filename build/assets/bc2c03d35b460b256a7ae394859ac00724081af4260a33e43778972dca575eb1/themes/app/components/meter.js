// The Meter's script (#315). Tessera imports it when a page holds an element
// naming it in `data-mount`, calls mount() with the element's
// `data-mount-data`, calls the returned cleanup and unmount() before the
// element goes, and mounts again when the data changes. Each call is logged
// in `globalThis.meterLog`, which the kept browser test reads.

const log = (entry) => {
    globalThis.meterLog = [...(globalThis.meterLog ?? []), entry];
};

export function mount(element, data) {
    const level = Number(data?.level ?? 0);
    const bars = document.createElement('span');
    bars.className = 'meter-bars';

    for (let bar = 0; bar < level; bar += 1) {
        const mark = document.createElement('span');
        mark.className = 'meter-bar';
        mark.textContent = '▮';
        bars.append(mark);
    }

    element.append(bars);
    log(`mount ${level}`);

    return () => {
        bars.remove();
        log('cleanup');
    };
}

export function unmount() {
    log('unmount');
}
