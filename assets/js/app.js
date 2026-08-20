// Dependent Department -> Program dropdown (used on applications/create.php, edit.php, imports, etc.)
document.addEventListener('DOMContentLoaded', function () {
    var deptSelect = document.getElementById('department_id');
    var progSelect = document.getElementById('program_id');
    if (!deptSelect || !progSelect) return;

    deptSelect.addEventListener('change', function () {
        var deptId = this.value;
        progSelect.innerHTML = '<option value="">Loading...</option>';
        if (!deptId) {
            progSelect.innerHTML = '<option value="">-- Select Program --</option>';
            return;
        }
        fetch(window.BASE_URL + 'ajax/get_programs.php?department_id=' + encodeURIComponent(deptId))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var html = '<option value="">-- Select Program --</option>';
                data.forEach(function (p) {
                    html += '<option value="' + p.id + '">' + p.program_name + '</option>';
                });
                progSelect.innerHTML = html;
            })
            .catch(function () {
                progSelect.innerHTML = '<option value="">Failed to load programs</option>';
            });
    });
});

// Auto-dismiss alerts after 5s
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert-dismissible').forEach(function (alertEl) {
        setTimeout(function () {
            var alert = bootstrap.Alert.getOrCreateInstance(alertEl);
            alert.close();
        }, 5000);
    });
});
