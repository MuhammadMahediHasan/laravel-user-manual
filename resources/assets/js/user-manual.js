(function () {
    function toggleSidebar() {
        var sidebar = document.getElementById('user-manual-sidebar');
        var overlay = document.getElementById('user-manual-sidebar-overlay');

        if (!sidebar || !overlay) {
            return;
        }

        var isOpen = sidebar.classList.contains('user-manual__sidebar--open');

        sidebar.classList.toggle('user-manual__sidebar--open', !isOpen);
        overlay.classList.toggle('user-manual__overlay--visible', !isOpen);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-user-manual-toggle="sidebar"]').forEach(function (element) {
            element.addEventListener('click', toggleSidebar);
        });
    });
})();
