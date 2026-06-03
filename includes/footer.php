<?php 
// includes/footer.php | SysInversiones CH Computer
?>
    </div>
    
    <footer class="main-footer sys-footer">
        <div class="float-right d-none d-sm-inline" style="display:flex;align-items:center;gap:6px;">
            <img src="/sysinversioneschcomputer/Logo/logo.jpg"
                 alt="SysInversiones CH Computer"
                 style="height:22px;width:22px;object-fit:cover;border-radius:4px;vertical-align:middle;">
            <strong>SysInversiones CH Computer</strong> v1.0
        </div>
        <strong>Copyright &copy; <?= date('Y') ?>.</strong> Todos los derechos reservados.
    </footer>

</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<?php if (!empty($extra_js)): ?>
<?= $extra_js ?>
<?php endif; ?>

<script>
// ── TEMA GLOBAL (dark/light) ─────────────────────────────────────────────────
(function(){
    var saved = localStorage.getItem('sys_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
})();

// ── FIX SCROLL JUMP GLOBAL ──────────────────────────────────────────────────
function fixScrollJump() {
    document.body.style.setProperty('padding-right', '0px', 'important');
}
$(document).on('show.bs.modal shown.bs.modal hide.bs.modal hidden.bs.modal', fixScrollJump);
(function () {
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            if (m.type === 'attributes' && m.attributeName === 'style') {
                var pr = document.body.style.paddingRight;
                if (pr && pr !== '0px') {
                    document.body.style.setProperty('padding-right', '0px', 'important');
                }
            }
            if (m.type === 'attributes' && m.attributeName === 'class') {
                fixScrollJump();
            }
        });
    });
    observer.observe(document.body, { attributes: true, attributeFilter: ['style', 'class'] });
})();
// ────────────────────────────────────────────────────────────────────────────
</script>

</body>
</html>
