document.addEventListener('DOMContentLoaded', function () {
    const themeSwitch = document.getElementById('themeSwitch');
    if (!themeSwitch) {
        return;
    }

    const rootHtml = document.documentElement;

    // Set initial state of the toggle based on the class on the <html> element
    if (rootHtml.classList.contains('dark-mode')) {
        themeSwitch.checked = true;
    }

    themeSwitch.addEventListener('change', function () {
        rootHtml.classList.toggle('dark-mode', this.checked);
        localStorage.setItem('theme', this.checked ? 'dark' : 'light');
    });
});