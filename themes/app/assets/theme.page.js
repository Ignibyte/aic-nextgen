// The fixture theme's page script (#239, a `*.page.js`): every page loads it after Tessera, tracked and fingerprinted.
// It marks the document, so a browser look can tell that it ran.
document.documentElement.dataset.themeScript = 'loaded';
