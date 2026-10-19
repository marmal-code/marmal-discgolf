<?php
declare(strict_types=1);
// Trénink: aktivní plán, položky na konkrétní den, záznamy (splněno / vynecháno + důvod), import plánu z textu.

const TR_KINDS = ['putting' => 'Putting', 'gym' => 'Posilovna', 'field' => 'Venku', 'other' => 'Ostatní'];
const TR_REASONS = ['nemoc' => 'Nemoc', 'zraneni' => 'Zranění', 'jine' => 'Jiný důvod'];
const TR_DAYS = [1 => 'po', 2 => 'út', 3 => 'st', 4 => 'čt', 5 => 'pá', 6 => 'so', 7 => 'ne'];
const TR_DAYS_LONG = [1 => 'Pondělí', 2 => 'Úterý', 3 => 'Středa', 4 => 'Čtvrtek', 5 => 'Pátek', 6 => 'Sobota', 7 => 'Neděle'];

function tr_plan(): ?array { return db()->query('SELECT * FROM training_plans WHERE active = 1 ORDER BY id DESC LIMIT 1')->fetch() ?: null; }

/** Položky plánu pro konkrétní den + případný záznam. */
function tr_day(string $date): array {
    $dow = (int)date('N', strtotime($date));
    $q = db()->prepare('SELECT i.*, p.name AS phase_name, l.id AS log_id, l.status, l.reason, l.note AS log_note, l.src AS log_src
        FROM training_items i
        JOIN training_phases p ON p.id = i.phase_id
        JOIN training_plans pl ON pl.id = p.plan_id AND pl.active = 1
        LEFT JOIN training_log l ON l.item_id = i.id AND l.tdate = ?
        WHERE FIND_IN_SET(?, i.days) AND (p.start_date IS NULL OR p.start_date <= ?) AND (p.end_date IS NULL OR p.end_date >= ?)
        ORDER BY i.sort, i.id');
    $q->execute([$date, (string)$dow, $date, $date]);
    return $q->fetchAll();
}

/** Vlastní tréninky mimo plán za den. */
function tr_extra(string $date): array {
    $q = db()->prepare('SELECT * FROM training_log WHERE tdate = ? AND item_id IS NULL ORDER BY id'); $q->execute([$date]); return $q->fetchAll();
}

function tr_set(string $date, int $itemId, string $status, ?string $reason, ?string $note): void {
    $itemId = (int)db()->query('SELECT id FROM training_items WHERE id = ' . $itemId)->fetchColumn();
    if (!$itemId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return;
    if ($status === 'none') { db()->prepare('DELETE FROM training_log WHERE tdate = ? AND item_id = ?')->execute([$date, $itemId]); return; }
    if (!in_array($status, ['done', 'skip'], true)) return;
    if ($status === 'skip' && !isset(TR_REASONS[(string)$reason])) $reason = 'jine';
    db()->prepare('INSERT INTO training_log (tdate, item_id, status, reason, note, created) VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), reason = VALUES(reason), note = VALUES(note), src = NULL')
        ->execute([$date, $itemId, $status, $status === 'skip' ? $reason : null, $note !== null && $note !== '' ? mb_substr($note, 0, 300) : null, now()]);
}

/** Souhrn dne/období: plán, splněno, vynecháno podle důvodu, bez záznamu (jen minulé dny). */
function tr_summary(string $from, string $to): array {
    $s = ['planned' => 0, 'done' => 0, 'skip' => 0, 'none' => 0, 'reasons' => array_fill_keys(array_keys(TR_REASONS), 0), 'extra' => 0];
    $today = date('Y-m-d');
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
        $d = date('Y-m-d', $t); if ($d > $today) break;
        foreach (tr_day($d) as $it) {
            $s['planned']++;
            if ($it['status'] === 'done') $s['done']++;
            elseif ($it['status'] === 'skip') { $s['skip']++; $s['reasons'][$it['reason'] ?: 'jine']++; }
            elseif ($d < $today) $s['none']++;
        }
    }
    $q = db()->prepare('SELECT COUNT(*) FROM training_log WHERE item_id IS NULL AND status = "done" AND tdate BETWEEN ? AND ?'); $q->execute([$from, $to]); $s['extra'] = (int)$q->fetchColumn();
    return $s;
}

function tr_week_start(string $date): string { return date('Y-m-d', strtotime($date) - ((int)date('N', strtotime($date)) - 1) * 86400); }
function tr_cz_date(string $d): string { return date('j. n.', strtotime($d)); }

/* ---------- import plánu z textu ---------- */

function tr_parse_days(string $s): ?string {
    $s = mb_strtolower(trim($s));
    if ($s === '' || preg_match('/^(denně|každý den|kazdy den|daily|vše|vse)$/u', $s)) return '1,2,3,4,5,6,7';
    $map = ['po' => 1, 'út' => 2, 'ut' => 2, 'st' => 3, 'čt' => 4, 'ct' => 4, 'pá' => 5, 'pa' => 5, 'so' => 6, 'ne' => 7];
    $out = [];
    foreach (preg_split('/\s*[,;\/ ]\s*/u', $s, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
        if (preg_match('/^(\p{L}{2})\s*[-–]\s*(\p{L}{2})$/u', $tok, $m) && isset($map[$m[1]], $map[$m[2]])) { for ($d = $map[$m[1]]; $d <= $map[$m[2]]; $d++) $out[$d] = 1; continue; }
        if (!isset($map[$tok])) return null;
        $out[$map[$tok]] = 1;
    }
    ksort($out); return $out ? implode(',', array_keys($out)) : null;
}

function tr_guess_kind(string $t): string {
    $t = mb_strtolower($t);
    if (preg_match('/putt/u', $t)) return 'putting';
    if (preg_match('/posil|gym|kettle|medicin|silov/u', $t)) return 'gym';
    if (preg_match('/venku|hřišt|hrist|kola|turnaj|disk|hod|tělocvič|telocvic|vytrval/u', $t)) return 'field';
    return 'other';
}

function tr_date(string $s): ?string {
    $s = trim($s);
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    if (preg_match('/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})$/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    return null;
}

/**
 * Formát:
 *   # Název plánu
 *   ## Název fáze | od | do
 *   Popis fáze (volitelně, běžný řádek)
 *   - dny | název | minuty | popis      (dny: denně, po-pá, út,pá …)
 * Vrací ['name','phases'=>[...]] nebo ['errors'=>[...]].
 */
function tr_parse_plan(string $text): array {
    $plan = ['name' => 'Tréninkový plán', 'phases' => []]; $err = []; $cur = null;
    foreach (preg_split('/\R/u', trim($text)) as $n => $line) {
        $line = trim($line); $ln = $n + 1;
        if ($line === '') continue;
        if (preg_match('/^#\s+(.+)$/u', $line, $m)) { $plan['name'] = mb_substr(trim($m[1]), 0, 150); continue; }
        if (preg_match('/^##\s+(.+)$/u', $line, $m)) {
            $p = array_map('trim', explode('|', $m[1]));
            $from = isset($p[1]) && $p[1] !== '' ? tr_date($p[1]) : null; $to = isset($p[2]) && $p[2] !== '' ? tr_date($p[2]) : null;
            if ((isset($p[1]) && $p[1] !== '' && !$from) || (isset($p[2]) && $p[2] !== '' && !$to)) $err[] = "Řádek $ln: datum fáze zapiš jako 2026-11-03 nebo 3. 11. 2026.";
            $plan['phases'][] = ['name' => mb_substr($p[0], 0, 150), 'start' => $from, 'end' => $to, 'descr' => '', 'items' => []]; $cur = count($plan['phases']) - 1; continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/u', $line, $m)) {
            if ($cur === null) { $plan['phases'][] = ['name' => 'Plán', 'start' => null, 'end' => null, 'descr' => '', 'items' => []]; $cur = 0; }
            $p = array_map('trim', explode('|', $m[1]));
            if (count($p) < 2) { $err[] = "Řádek $ln: položku zapiš jako „dny | název | minuty | popis“."; continue; }
            $days = tr_parse_days($p[0]);
            if ($days === null) { $err[] = "Řádek $ln: nerozumím dnům „{$p[0]}“ (použij denně, po-pá, út,pá…)."; continue; }
            $min = isset($p[2]) && preg_match('/\d+/', $p[2], $mm) ? (int)$mm[0] : null;
            $plan['phases'][$cur]['items'][] = ['days' => $days, 'title' => mb_substr($p[1], 0, 200), 'minutes' => $min, 'kind' => tr_guess_kind($p[1]), 'details' => isset($p[3]) ? implode(' | ', array_slice($p, 3)) : ''];
            continue;
        }
        if ($cur !== null) $plan['phases'][$cur]['descr'] .= ($plan['phases'][$cur]['descr'] ? "\n" : '') . $line;
        else $err[] = "Řádek $ln: nejdřív zapiš fázi (## Název) nebo položku (- dny | název).";
    }
    if (!$plan['phases']) $err[] = 'V textu nejsou žádné fáze ani položky.';
    foreach ($plan['phases'] as $i => $ph) if (!$ph['items']) $err[] = 'Fáze „' . $ph['name'] . '“ nemá žádné položky.';
    return $err ? ['errors' => $err] : $plan;
}

function tr_save_plan(array $p, bool $replace): int {
    if ($replace) db()->exec('UPDATE training_plans SET active = 0');
    db()->prepare('INSERT INTO training_plans (name, active, created) VALUES (?,1,?)')->execute([$p['name'], now()]); $pid = (int)db()->lastInsertId();
    $ph = db()->prepare('INSERT INTO training_phases (plan_id, name, start_date, end_date, descr, sort) VALUES (?,?,?,?,?,?)');
    $it = db()->prepare('INSERT INTO training_items (phase_id, days, title, minutes, kind, details, sort) VALUES (?,?,?,?,?,?,?)');
    foreach ($p['phases'] as $i => $f) {
        $ph->execute([$pid, $f['name'], $f['start'], $f['end'], $f['descr'] ?: null, $i]); $fid = (int)db()->lastInsertId();
        foreach ($f['items'] as $j => $x) $it->execute([$fid, $x['days'], $x['title'], $x['minutes'], $x['kind'], $x['details'] ?: null, $j]);
    }
    return $pid;
}

function tr_days_label(string $days): string {
    if ($days === '1,2,3,4,5,6,7') return 'denně';
    return implode(', ', array_map(fn($d) => TR_DAYS[(int)$d] ?? '?', explode(',', $days)));
}
