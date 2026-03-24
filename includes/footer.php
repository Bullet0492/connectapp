</div><!-- end main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
document.querySelectorAll('.sidebar-nav a, .sidebar-logout a').forEach(function(a) {
    a.addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('open');
    });
});

// Auto-hoofdletter: eerste letter kapitaal bij invoer
var geenHoofdletter = ['postcode', 'telefoon', 'email', 'wachtwoord', 'url', 'gebruikersnaam', 'intra_id'];
document.addEventListener('input', function(e) {
    var el = e.target;
    if ((el.tagName === 'INPUT' && el.type === 'text') || el.tagName === 'TEXTAREA') {
        var name = el.name || '';
        if (geenHoofdletter.indexOf(name) !== -1) return;
        if (el.id && el.id.endsWith('_zoek')) return;
        var val = el.value;
        if (val.length > 0 && val[0] !== val[0].toUpperCase()) {
            var pos = el.selectionStart;
            el.value = val[0].toUpperCase() + val.slice(1);
            el.setSelectionRange(pos, pos);
        }
    }
});

// Bootstrap formuliervalidatie
document.querySelectorAll('form.needs-validation').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
</script>
</body>
</html>
