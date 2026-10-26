<div class="topbar"><div><h1>Import from a spreadsheet</h1><div class="muted">Upload an Excel file (<b>.xlsx</b>) or a <b>CSV</b> with any columns: the first row must be the headers. Next you will match each column to a field, or keep it as extra info. Re-uploading is safe — existing people are updated, not duplicated.</div></div></div>
<div class="grid c2">
  <div class="card">
    <h3><span class="avatar" style="width:34px;height:34px"><?= icon('users', 18) ?></span> <?= e(term('a2')) ?><?= uses_contacts() ? ' &amp; ' . e(strtolower(term('c2'))) : '' ?></h3>
    <p class="muted small">One row per <?= e(strtolower(term('a1'))) ?>. Columns: <code>student_id</code>, <code>student_first_name</code>, <code>student_last_name</code>, <code>grade</code>,
      <code>father</code>, <code>mother</code> (each with optional <code>_phone</code> / <code>_email</code>), and up to two other people allowed to check in:
      <code>relative1_name</code> + <code>relative1_relationship</code> (e.g. Grandmother, Uncle).
      Headers like <code>Guardian (Father)</code>, <code>Guardian (Mother)</code> and <code>Authorized Relative</code> also work; several relatives go in one cell separated by <code>;</code>,
      optionally with the relationship in brackets: <code>Rose Ng (Grandmother); Tom Ng</code>. Cells with N/A are treated as empty. Re-uploading adds new people and never removes anyone. Extra columns are ignored.</p>
    <div class="table-wrap" style="margin-bottom:14px"><table class="table small">
      <tr><th>student_id</th><th>student_first_name</th><th>student_last_name</th><th>grade</th><th>father</th><th>mother</th><th>relative1_name</th><th>relative1_relationship</th></tr>
      <tr><td>S101</td><td>Olivia</td><td>Martin</td><td>Grade 1</td><td>Paul Martin</td><td>Anna Martin</td><td>Rose Martin</td><td>Grandmother</td></tr>
    </table></div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="type" value="students">
      <div class="field"><input type="file" name="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></div>
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
      <div class="field"><input type="file" name="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></div>
      <div class="row"><button class="btn btn-accent"><?= icon('upload', 16) ?> Upload teachers</button>
        <a class="btn btn-ghost" href="<?= e(url('/admin/import/sample-teachers.csv')) ?>"><?= icon('download', 16) ?> Sample file</a></div>
    </form>
  </div>
</div>
