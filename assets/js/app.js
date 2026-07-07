/**
 * Interacciones básicas de la interfaz.
 * Reemplaza el main.js de la plantilla NiceAdmin: solo se conserva
 * lo que el sistema usa realmente (toggle del sidebar y tooltips).
 */
document.addEventListener('DOMContentLoaded', function () {

    var toggle = document.querySelector('.toggle-sidebar-btn');
    if (toggle) {
        toggle.addEventListener('click', function () {
            document.body.classList.toggle('toggle-sidebar');
        });
    }

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

});
