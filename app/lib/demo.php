<?php
// Demo portal: a fully populated school for showing the product.
// Load or reset it from /super ("Load demo portal") or: php database/seed_demo.php

const DEMO_SLUG = 'demo';
const DEMO_LOGINS = [
    ['owner', 'Demo Admin', 'admin@demo.com', 'Demo@1234'],
    ['staff', 'Front Desk', 'staff@demo.com', 'Staff@1234'],
];

function demo_tenant(): ?array
{
    try {
        return tenant_by_slug(DEMO_SLUG);
    } catch (Throwable $e) {
        return null;
    }
}

/** Deletes any existing demo portal and builds a fresh one with ~6 weeks of activity. */
function seed_demo_tenant(): array
{
    mt_srand(20261008);
    // Keep the time zone the presenter chose in Branding & settings when resetting
    $old = row('SELECT * FROM tenants WHERE slug = ?', [DEMO_SLUG]);
    $setting = $old ? (string) $old['timezone'] : ''; // keep the presenter's choice; new demos are Automatic
    $tz = valid_tz($setting) ? $setting : (device_tz() ?? ( (valid_tz($old['device_timezone'] ?? '') ? $old['device_timezone'] : 'America/New_York')));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('DELETE FROM tenants WHERE slug = ?', [DEMO_SLUG]);
        $tid = insert('tenants', [
            'name' => 'Bright Future Academy', 'slug' => DEMO_SLUG, 'plan' => 'school', 'status' => 'active',
            'primary_color' => '#6C5CE7', 'accent_color' => '#00B894', 'timezone' => $setting,
            'welcome_title' => 'Welcome to Bright Future Academy!',
            'welcome_text' => 'Tap below to sign in, sign out or pick up materials.',
            'allow_past_days' => 7, 'report_emails' => 'admin@demo.com',
        ]);
        seed_tiles($tid, 'school');
        q("UPDATE kiosk_tiles SET active = 1 WHERE tenant_id = ? AND type = 'visitor'", [$tid]); // show visitors in the demo
        foreach (DEMO_LOGINS as [$role, $name, $email, $pass]) {
            insert('users', ['tenant_id' => $tid, 'name' => $name, 'email' => $email, 'role' => $role, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT)]);
        }
        $materials = ['Homework folder', 'Library books', 'Uniform', 'Lunch box', 'Art project', 'Report card', 'Permission slip'];
        foreach ($materials as $i => $m) {
            insert('materials', ['tenant_id' => $tid, 'name' => $m, 'sort_order' => $i]);
        }

        // Students with their own parents / guardians
        $families = [
            ['Ava', 'Johnson', 'Grade 2', ['Michael Johnson' => 'Father', 'Sarah Johnson' => 'Mother']],
            ['Liam', 'Patel', 'Grade 3', ['Raj Patel' => 'Father', 'Priya Patel' => 'Mother']],
            ['Mia', 'Garcia', 'Grade 1', ['Carlos Garcia' => 'Father', 'Elena Garcia' => 'Mother', 'Rosa Garcia' => 'Grandmother']],
            ['Noah', 'Kim', 'Grade 4', ['Daniel Kim' => 'Father', 'Grace Kim' => 'Mother']],
            ['Emma', 'Williams', 'Grade 2', ['Laura Williams' => 'Mother']],
            ['Ethan', 'Brown', 'Grade 5', ['James Brown' => 'Father', 'Olivia Brown' => 'Mother']],
            ['Sophia', 'Nguyen', 'KG', ['Minh Nguyen' => 'Father', 'Lan Nguyen' => 'Mother']],
            ['Lucas', 'Martin', 'Grade 1', ['Paul Martin' => 'Father', 'Anna Martin' => 'Mother']],
            ['Isabella', 'Rossi', 'Grade 3', ['Marco Rossi' => 'Father', 'Giulia Rossi' => 'Mother']],
            ['Aiden', 'Okafor', 'Grade 4', ['Chidi Okafor' => 'Father', 'Ngozi Okafor' => 'Mother']],
            ['Zara', 'Ahmed', 'Grade 2', ['Omar Ahmed' => 'Father', 'Fatima Ahmed' => 'Mother']],
            ['Oliver', 'Smith', 'KG', ['David Smith' => 'Father', 'Emily Smith' => 'Mother', 'Tom Smith' => 'Uncle']],
            ['Amelia', 'Chen', 'Grade 5', ['Wei Chen' => 'Father', 'Li Chen' => 'Mother']],
            ['Mateo', 'Hernandez', 'Grade 3', ['Luis Hernandez' => 'Father', 'Sofia Hernandez' => 'Mother']],
            ['Harper', 'Davis', 'Grade 1', ['Chris Davis' => 'Father', 'Megan Davis' => 'Mother']],
            ['Elijah', 'Wilson', 'Grade 4', ['Karen Wilson' => 'Mother']],
            ['Aria', 'Singh', 'KG', ['Arjun Singh' => 'Father', 'Simran Singh' => 'Mother']],
            ['Benjamin', 'Lopez', 'Grade 2', ['Jose Lopez' => 'Father', 'Maria Lopez' => 'Mother']],
            ['Chloe', 'Taylor', 'Grade 5', ['Mark Taylor' => 'Father', 'Jessica Taylor' => 'Mother']],
            ['Yusuf', 'Khan', 'Grade 3', ['Imran Khan' => 'Father', 'Ayesha Khan' => 'Mother']],
            ['Lily', 'Anderson', 'Grade 1', ['Ryan Anderson' => 'Father', 'Kate Anderson' => 'Mother']],
            ['Henry', 'Thomas', 'Grade 4', ['Peter Thomas' => 'Father', 'Susan Thomas' => 'Grandmother']],
            ['Maya', 'Jackson', 'Grade 2', ['Andre Jackson' => 'Father', 'Tasha Jackson' => 'Mother']],
            ['Leo', 'White', 'KG', ['Ben White' => 'Father', 'Claire White' => 'Mother']],
            ['Nora', 'Harris', 'Grade 3', ['Sam Harris' => 'Father', 'Julia Harris' => 'Mother']],
            ['Kai', 'Tanaka', 'Grade 5', ['Hiro Tanaka' => 'Father', 'Yuki Tanaka' => 'Mother']],
            ['Ella', 'Clark', 'Grade 1', ['Laura Clark' => 'Mother', 'George Clark' => 'Grandfather']],
            ['Samuel', 'Lewis', 'Grade 4', ['Adam Lewis' => 'Father', 'Nina Lewis' => 'Mother']],
            ['Layla', 'Hassan', 'Grade 2', ['Ali Hassan' => 'Father', 'Mariam Hassan' => 'Mother']],
            ['Jack', 'Walker', 'Grade 3', ['Tim Walker' => 'Father', 'Amy Walker' => 'Mother']],
            ['Ruby', 'Young', 'KG', ['Jason Young' => 'Father', 'Hannah Young' => 'Mother']],
            ['Daniel', 'Silva', 'Grade 5', ['Pedro Silva' => 'Father', 'Ana Silva' => 'Mother']],
        ];
        $students = [];
        foreach ($families as $i => [$fn, $ln, $grade, $gs]) {
            $sid = insert('students', ['tenant_id' => $tid, 'student_code' => sprintf('BF%03d', $i + 1), 'first_name' => $fn, 'last_name' => $ln, 'grade' => $grade]);
            $gList = [];
            foreach ($gs as $gName => $rel) {
                $gid = insert('guardians', ['tenant_id' => $tid, 'student_id' => $sid, 'name' => $gName, 'relationship' => $rel, 'phone' => encrypt_pii(sprintf('555-%04d', mt_rand(1000, 9999)))]);
                $gList[] = [$gid, $gName];
            }
            $students[] = ['id' => $sid, 'name' => "$fn $ln", 'guardians' => $gList];
        }

        $teachers = [];
        foreach ([['Hannah', 'Lee'], ['Robert', 'Miller'], ['Aisha', 'Khan'], ['Carlos', 'Mendes'], ['Emily', 'Foster'], ['David', 'Park'], ['Grace', 'Okoye'], ['Sarah', 'Bennett']] as $i => [$fn, $ln]) {
            $id = insert('teachers', ['tenant_id' => $tid, 'employee_code' => sprintf('T%02d', $i + 1), 'first_name' => $fn, 'last_name' => $ln, 'email' => encrypt_pii(strtolower($fn) . '@brightfuture.example')]);
            $teachers[] = ['id' => $id, 'name' => "$fn $ln"];
        }

        // Activity: every school day for the last 6 weeks, plus this morning.
        $now = new DateTime('now', new DateTimeZone($tz));
        $nowMin = (int) $now->format('G') * 60 + (int) $now->format('i');
        $att = $pdo->prepare('INSERT INTO attendance (tenant_id, person_type, person_id, person_name, guardian_id, guardian_name, kind, event_date, event_time, materials) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $t = fn(int $m) => sprintf('%02d:%02d:00', intdiv($m, 60), $m % 60);
        for ($back = 42; $back >= 0; $back--) {
            $day = (clone $now)->modify("-$back days");
            $isToday = $back === 0;
            if (!$isToday && (int) $day->format('N') >= 6) {
                continue; // weekends closed
            }
            $date = $day->format('Y-m-d');
            // On a demo "today" before school starts, still show a busy morning.
            $cap = $isToday ? max($nowMin - 2, 9 * 60) : 24 * 60;
            foreach ($teachers as $tc) {
                if (mt_rand(1, 100) > 95) {
                    continue;
                }
                $in = mt_rand(7 * 60, 7 * 60 + 50);
                if ($in > $cap) {
                    continue;
                }
                $att->execute([$tid, 'teacher', $tc['id'], $tc['name'], null, null, 'sign_in', $date, $t($in), null]);
                $out = mt_rand(15 * 60 + 30, 17 * 60 + 15);
                if ($out <= $cap && !$isToday) {
                    $att->execute([$tid, 'teacher', $tc['id'], $tc['name'], null, null, 'sign_out', $date, $t($out), null]);
                }
            }
            foreach ($students as $st) {
                if (mt_rand(1, 100) > ($isToday ? 85 : 92)) {
                    continue;
                }
                $in = mt_rand(7 * 60 + 25, 8 * 60 + 45);
                if ($in > $cap) {
                    continue;
                }
                $g = $st['guardians'][array_rand($st['guardians'])];
                $att->execute([$tid, 'student', $st['id'], $st['name'], $g[0], $g[1], 'sign_in', $date, $t($in), null]);
                $out = mt_rand(14 * 60 + 45, 17 * 60 + 30);
                $forgot = mt_rand(1, 100) <= 2;
                if (!$forgot && $out <= $cap && (!$isToday || $nowMin > 15 * 60)) {
                    $g = $st['guardians'][array_rand($st['guardians'])];
                    $att->execute([$tid, 'student', $st['id'], $st['name'], $g[0], $g[1], 'sign_out', $date, $t($out), null]);
                }
            }
            for ($p = 0, $n = mt_rand(1, 4); $p < $n; $p++) {
                $st = $students[array_rand($students)];
                $g = $st['guardians'][array_rand($st['guardians'])];
                $items = array_rand(array_flip($materials), mt_rand(1, 2));
                $at = mt_rand(8 * 60, 17 * 60);
                if ($at > $cap) {
                    $at = mt_rand(7 * 60 + 30, min($cap, 9 * 60));
                }
                $att->execute([$tid, 'student', $st['id'], $st['name'], $g[0], $g[1], 'material_pickup', $date, $t($at), implode(', ', (array) $items)]);
            }
        }
        // A few visitors today, so the Visitor tile and sheet have something to show
        $vt = (int) val("SELECT id FROM kiosk_tiles WHERE tenant_id = ? AND type = 'visitor'", [$tid]);
        foreach ([['Jordan Lee', 'BrightPath Books', 'Ms. Lee', 8 * 60 + 40, 9 * 60 + 25], ['Alex Rivera', 'City Fire Dept', 'Office', 10 * 60 + 5, null]] as [$vn, $vc, $vh, $vin, $vout]) {
            $today = $now->format('Y-m-d');
            $vid = insert('attendance', ['tenant_id' => $tid, 'person_type' => 'visitor', 'tile_id' => $vt, 'person_id' => 0, 'person_name' => $vn,
                'guardian_name' => $vc, 'kind' => 'sign_in', 'event_date' => $today, 'event_time' => $t($vin), 'note' => 'Visiting: ' . $vh]);
            q('UPDATE attendance SET person_id = id WHERE id = ?', [$vid]);
            if ($vout) {
                insert('attendance', ['tenant_id' => $tid, 'person_type' => 'visitor', 'tile_id' => $vt, 'person_id' => $vid, 'person_name' => $vn,
                    'guardian_name' => $vc, 'kind' => 'sign_out', 'event_date' => $today, 'event_time' => $t($vout), 'note' => 'Visiting: ' . $vh]);
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return ['tenant_id' => $tid, 'students' => count($students), 'teachers' => count($teachers), 'events' => (int) val('SELECT COUNT(*) FROM attendance WHERE tenant_id = ?', [$tid])];
}
