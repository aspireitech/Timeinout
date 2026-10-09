<div class="topbar"><div><h1>Import from a spreadsheet</h1><div class="muted">Save your Excel or Google Sheet as <b>CSV</b>, then upload it here. Re-uploading is safe — existing people are updated, not duplicated.</div></div></div>
<div class="grid c2">
  <div class="card">
    <h3><span class="avatar" style="width:34px;height:34px"><?= icon('users', 18) ?></span> <?= e(term('a2')) ?><?= uses_contacts() ? ' &amp; ' . e(strtolower(term('c2'))) : '' ?></h3>
    <p class="muted small">One row per person. Put each contact in <code>guardian1_…</code>, <code>guardian2_…</code> columns — or repeat the student on several rows with <code>guardian_name</code>, <code>relationship</code>, <code>phone</code>.</p>
    <div class="table-wrap" style="margin-bottom:14px"><table class="table small">
      <tr><th>student_id</th><th>first_name</th><th>last_name</th><th>grade</th><th>guardian1_name</th><th>guardian1_relationship</th><th>guardian2_name</th><th>…</th></tr>
      <tr><td>S101</td><td>Olivia</td><td>Martin</td><td>Grade 1</td><td>Paul Martin</td><td>Father</td><td>Anna Martin</td><td>…</td></tr>
    </table></div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="type" value="students">
      <div class="field"><input type="file" name="file" accept=".csv,text/csv" required></div>
      <div class="row"><button class="btn btn-primary"><?= icon('upload', 16) ?> Upload students</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/import/sample-students.csv')) ?>"><?= icon('download', 16) ?> Sample file</a></div>
    </form>
  </div>
  <div class="card">
    <h3><span class="avatar teacher" style="width:34px;height:34px"><?= icon('teacher', 18) ?></span> <?= e(term('b2')) ?></h3>
    <p class="muted small">One row per person. A single <code>name</code> column also works.</p>
    <div class="table-wrap" style="margin-bottom:14px"><table class="table small">
      <tr><th>employee_id</th><th>first_name</th><th>last_name</th><th>email</th><th>phone</th></tr>
      <tr><td>T101</td><td>Grace</td><td>Hopper</td><td>grace@example.com</td><td>555-0301</td></tr>
    </table></div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="type" value="teachers">
      <div class="field"><input type="file" name="file" accept=".csv,text/csv" required></div>
      <div class="row"><button class="btn btn-accent"><?= icon('upload', 16) ?> Upload teachers</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/import/sample-teachers.csv')) ?>"><?= icon('download', 16) ?> Sample file</a></div>
    </form>
  </div>
</div>
