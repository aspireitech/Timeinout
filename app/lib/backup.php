<?php
// SQL dumps of whole databases (used by cron/backup.php and before a portal is removed).

/** Writes one database as SQL (structure + data) into a gzip file. */
function dump_database(PDO $pdo, string $file): int
{
    $gz = gzopen($file, 'wb6');
    gzwrite($gz, "-- ClockInOut backup " . gmdate('Y-m-d H:i:s') . " UTC\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
    $count = 0;
    foreach ($tables as [$table]) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        gzwrite($gz, "DROP TABLE IF EXISTS `$table`;\n$create;\n");
        $st = $pdo->query("SELECT * FROM `$table`", PDO::FETCH_NUM);
        $batch = [];
        while ($r = $st->fetch(PDO::FETCH_NUM)) {
            $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
            if (count($batch) >= 200) {
                gzwrite($gz, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
                $count += count($batch);
                $batch = [];
            }
        }
        if ($batch) {
            gzwrite($gz, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
            $count += count($batch);
        }
        gzwrite($gz, "\n");
    }
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($gz);
    return $count;
}
