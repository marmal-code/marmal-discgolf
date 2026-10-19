<?php
declare(strict_types=1);
// Výchozí tréninkový plán do března 2027 (z profilu a plánu vytvořeného s Claude). Dá se upravit nebo nahradit importem.
return function (PDO $pdo): void {
    if ((int)$pdo->query('SELECT COUNT(*) FROM training_plans')->fetchColumn() > 0) return;
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO training_plans (name, note, active, created) VALUES (?,?,1,?)')->execute(['Plán do března 2027', 'Čtyři fáze od návratu do prvního turnaje sezóny. Rámec: posilovna 2× týdně, putting každé ráno, 1–2 tréninky venku.', $now]);
    $plan = (int)$pdo->lastInsertId();
    $ph = $pdo->prepare('INSERT INTO training_phases (plan_id, name, start_date, end_date, descr, sort) VALUES (?,?,?,?,?,?)');
    $it = $pdo->prepare('INSERT INTO training_items (phase_id, days, title, minutes, kind, details, sort) VALUES (?,?,?,?,?,?,?)');
    $D = '1,2,3,4,5,6,7';
    $data = [
        ['0. Návrat', '2026-10-19', '2026-11-02', 'Lehký návrat. Před posilovnou a rotací ověřit u lékaře, co už smíš.', [
            [$D, 'Chůze a lehká mobilita', 20, 'other', 'Jen chůze a lehká mobilita, žádná posilovna.'],
            [$D, 'Putting z krátké vzdálenosti', 15, 'putting', 'Do cca 6 m, bez agresivní rotace, bez straddle a spin puttu, dokud to lékař nepotvrdí.'],
        ]],
        ['1. Základ', '2026-11-03', '2026-12-31', 'Síla a základ techniky, putting každé ráno.', [
            ['2,5', 'Posilovna', 60, 'gym', 'Nohy, zadek, core (anti-rotace), záda, pak postupně rotace.'],
            [$D, 'Ranní putting', 20, 'putting', '5 min zahřátí z krátké vzdálenosti, 10 min tlakové hry, 3–5 min s mokrým diskem.'],
            ['6', 'Trénink venku / v tělocvičně', 60, 'field', 'Disk a rutina před hodem.'],
        ]],
        ['2. Výbušnost a vytrvalost', '2027-01-01', '2027-02-28', 'Výbušnost, rotace a udržení techniky ve druhém kole dne.', [
            ['2,5', 'Posilovna – výbušnost a rotace', 60, 'gym', 'Hody medicinbalem, kettlebell swing, rotační cviky.'],
            [$D, 'Ranní putting', 20, 'putting', 'Delší vzdálenosti a lag z C2.'],
            ['6', 'Vytrvalostní trénink venku', 90, 'field', 'Simulace dvou kol za den, sleduj, kdy ti klesá technika.'],
            ['7', 'Videorozbor backhandu', 15, 'other', 'Natoč backhand a zkontroluj hitch.'],
        ]],
        ['3. Ostření', '2027-03-01', '2027-03-31', 'Turnajová příprava: warm-up z omezeného prostoru, protokol pro vítr a mokro, pevná rutina.', [
            ['2', 'Posilovna – udržení', 45, 'gym', 'Lehčí udržovací trénink.'],
            [$D, 'Ranní putting', 15, 'putting', 'Pevná rutina, tlakové hry.'],
            ['6', 'Tréninkový turnajový den', 120, 'field', 'Kola jako na turnaji včetně warm-upu z omezeného prostoru.'],
            ['3', 'Protokol pro vítr a mokro', 30, 'field', 'Pevné pořadí: ručník, disk, hod. Nacvičit i v dešti.'],
        ]],
    ];
    foreach ($data as $i => [$name, $from, $to, $descr, $items]) {
        $ph->execute([$plan, $name, $from, $to, $descr, $i]); $pid = (int)$pdo->lastInsertId();
        foreach ($items as $j => [$days, $title, $min, $kind, $det]) $it->execute([$pid, $days, $title, $min, $kind, $det, $j]);
    }
};
