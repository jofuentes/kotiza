// Utilidades globales de la interfaz
function toggleTheme() {
    const dark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', dark ? 'dark' : 'light');
}

// Token CSRF para peticiones fetch
window.csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
