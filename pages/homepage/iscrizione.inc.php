<?php
/**
 * Iscrizione (4 fasi)
 *  fase 0 → disclaimer
 *  fase 1 → form dati
 *  fase 2 → validazione + summary
 *  fase 3 → insert + welcome
 */

$fase = (int)gdrcd_filter('get', $_POST['fase'] ?? 0);

/** Restituisce gli hidden con i dati già inseriti per ripostare il form. */
$render_hidden = function () {
    $fields = ['email','nome','cognome','genere','razza','car0','car1','car2','car3','car4','car5'];
    $out = '';
    foreach ($fields as $f) {
        $val = $_POST[$f] ?? '';
        $val = in_array($f, ['razza','car0','car1','car2','car3','car4','car5'], true)
            ? gdrcd_filter('num', $val)
            : gdrcd_filter('out', $val);
        $out .= '<input type="hidden" name="' . $f . '" value="' . $val . '"/>' . "\n";
    }
    return $out;
};

/** Render dello stepper. $current = 0..3. */
$render_stepper = function (int $current) {
    $steps = [
        0 => 'Condizioni',
        1 => 'Dati',
        2 => 'Riepilogo',
        3 => 'Conferma',
    ];
    $html = '<ol class="gdrcd-stepper" aria-label="Fasi iscrizione">';
    $i = 0;
    foreach ($steps as $idx => $label) {
        $state = $idx < $current ? 'is-done' : ($idx === $current ? 'is-active' : '');
        $html .= '<li class="gdrcd-stepper-item ' . $state . '">'
              .  '<span class="gdrcd-stepper-circle">' . ($idx + 1) . '</span>'
              .  '<span class="gdrcd-stepper-label">' . $label . '</span>'
              .  '</li>';
        if ($idx !== array_key_last($steps)) {
            $html .= '<li class="gdrcd-stepper-divider" aria-hidden="true"></li>';
        }
        $i++;
    }
    $html .= '</ol>';
    return $html;
};

/** Validazione comune fasi 2 e 3. Ritorna array errori. */
$validate = function () use ($PARAMETERS, $MESSAGE) {
    $errors = [];

    $email = gdrcd_filter_email($_POST['email'] ?? '');
    if ($email === '' || !strpos($email, '@') || !strpos($email, '.')) {
        $errors[] = gdrcd_filter('out', $MESSAGE['register']['error']['email_needed']);
    } else {
        $result = gdrcd_query("SELECT email FROM personaggio", 'result');
        foreach ($result as $pg) {
            if (gdrcd_password_check($email, $pg['email'])) {
                $errors[] = gdrcd_filter('out', $MESSAGE['register']['error']['email_taken']);
                break;
            }
        }
    }

    $name = $_POST['nome'] ?? '';
    $regex = '#[^\p{L}\s]#u';
    if (empty(gdrcd_safe_name($name)) || preg_match($regex, $name)) {
        $errors[] = gdrcd_filter('out', $MESSAGE['register']['error']['invalid_name']);
    } else {
        $result = gdrcd_query("SELECT nome FROM personaggio WHERE nome='" . gdrcd_safe_name($name) . "' LIMIT 1", 'result');
        if (gdrcd_query($result, 'num_rows') > 0) {
            gdrcd_query($result, 'free');
            $errors[] = gdrcd_filter('out', $MESSAGE['register']['error']['name_taken']);
        }
    }

    $sum = 0;
    for ($i = 0; $i < 6; $i++) {
        $sum += gdrcd_filter('num', $_POST["car$i"] ?? 0);
    }
    if ($sum !== (int)$PARAMETERS['settings']['cars_sum']) {
        $errors[] = gdrcd_filter('out', $MESSAGE['register']['fields']['stats_info'] . ' ' . $PARAMETERS['settings']['cars_sum']);
    }

    $race_id = gdrcd_filter('num', $_POST['razza'] ?? 0);
    $race_data = gdrcd_query("SELECT iscrizione FROM razza WHERE id_razza='{$race_id}' LIMIT 1");
    if (!gdrcd_filter('num', $race_data['iscrizione'] ?? 0)) {
        $errors[] = "Razza non disponibile all'iscrizione.";
    }

    return $errors;
};

$action = htmlspecialchars($_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'], ENT_QUOTES);
?>

<div class="space-y-6">
    <header class="space-y-2">
        <h1 class="gdrcd-h1"><?= gdrcd_filter('out', $MESSAGE['register']['page_name']) ?></h1>
        <p class="gdrcd-muted">Crea il tuo personaggio in pochi passaggi.</p>
    </header>

    <?= $render_stepper($fase) ?>

    <?php if ($fase === 0): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-body space-y-5">
                <div class="gdrcd-alert-info">
                    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="space-y-2">
                        <p><?= gdrcd_filter('out', $MESSAGE['register']['disclaimer']) ?></p>
                        <p><?= gdrcd_filter('out', $MESSAGE['register']['rules_read']) ?></p>
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2">
                    <form action="index.php" method="post">
                        <button type="submit" class="gdrcd-btn-ghost">
                            <?= gdrcd_filter('out', $MESSAGE['register']['forms']['refuse']) ?>
                        </button>
                    </form>
                    <form action="<?= $action ?>" method="post">
                        <input type="hidden" name="fase" value="1"/>
                        <button type="submit" class="gdrcd-btn-primary">
                            <?= gdrcd_filter('out', $MESSAGE['register']['forms']['accept']) ?>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7-7 7M3 12h18"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </section>

    <?php elseif ($fase === 1): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-body">
                <form action="<?= $action ?>" method="post" class="space-y-5">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="gdrcd-label" for="iscr_email">
                                <?= gdrcd_filter('out', $MESSAGE['register']['fields']['email']) ?>
                            </label>
                            <input class="gdrcd-input" type="email" id="iscr_email" name="email"
                                   value="<?= gdrcd_filter('email', $_POST['email'] ?? '') ?>" required/>
                            <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['register']['fields']['email_info']) ?></p>
                        </div>

                        <div>
                            <label class="gdrcd-label" for="iscr_genere">
                                <?= gdrcd_filter('out', $MESSAGE['register']['fields']['gender']) ?>
                            </label>
                            <select class="gdrcd-select" id="iscr_genere" name="genere">
                                <option value="m" <?= (gdrcd_filter('get', $_POST['genere'] ?? '') === 'm') ? 'selected' : '' ?>>
                                    <?= gdrcd_filter('out', $MESSAGE['register']['fields']['gender_m']) ?>
                                </option>
                                <option value="f" <?= (gdrcd_filter('get', $_POST['genere'] ?? '') === 'f') ? 'selected' : '' ?>>
                                    <?= gdrcd_filter('out', $MESSAGE['register']['fields']['gender_f']) ?>
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="gdrcd-label" for="iscr_nome">
                                <?= gdrcd_filter('out', $MESSAGE['register']['fields']['name']) ?>
                            </label>
                            <input class="gdrcd-input" type="text" id="iscr_nome" name="nome"
                                   value="<?= gdrcd_filter('out', $_POST['nome'] ?? '') ?>" required/>
                            <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['register']['fields']['name_info']) ?></p>
                        </div>

                        <div>
                            <label class="gdrcd-label" for="iscr_cognome">
                                <?= gdrcd_filter('out', $MESSAGE['register']['fields']['lastname']) ?>
                            </label>
                            <input class="gdrcd-input" type="text" id="iscr_cognome" name="cognome"
                                   value="<?= gdrcd_filter('out', $_POST['cognome'] ?? '') ?>"/>
                            <p class="gdrcd-help"><?= gdrcd_filter('out', $MESSAGE['register']['fields']['name_info']) ?></p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="gdrcd-label" for="iscr_razza">
                                <?= gdrcd_filter('out', $PARAMETERS['names']['race']['sing'] . ' ' . $MESSAGE['register']['fields']['race']) ?>
                            </label>
                            <select class="gdrcd-select" id="iscr_razza" name="razza">
                                <?php
                                $result = gdrcd_query("SELECT id_razza, nome_razza FROM razza WHERE iscrizione=1 ORDER BY nome_razza", 'result');
                                while ($row = gdrcd_query($result, 'fetch')):
                                    $sel = (gdrcd_filter('get', $_POST['razza'] ?? '') == $row['id_razza']) ? 'selected' : '';
                                    ?>
                                    <option value="<?= $row['id_razza'] ?>" <?= $sel ?>>
                                        <?= gdrcd_filter('out', $row['nome_razza']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <?php if ($PARAMETERS['mode']['racialinfo'] === 'ON'): ?>
                                <p class="gdrcd-help">
                                    <a class="gdrcd-link" href="index.php?page=user_razze" target="_blank" rel="noopener">
                                        <?= gdrcd_filter('out', $MESSAGE['register']['fields']['race_info']) ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <label class="gdrcd-label">
                            <?= gdrcd_filter('out', $MESSAGE['register']['fields']['stats']) ?>
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <div>
                                    <label class="block text-xs font-medium text-gdrcd-muted mb-1" for="iscr_car<?= $i ?>">
                                        <?= gdrcd_filter('out', $PARAMETERS['names']['stats']['car'.$i]) ?>
                                    </label>
                                    <select class="gdrcd-select" id="iscr_car<?= $i ?>" name="car<?= $i ?>">
                                        <?php for ($v = 1; $v <= $PARAMETERS['settings']['initial_cars_cap']; $v++):
                                            $sel = (gdrcd_filter('num', $_POST['car'.$i] ?? 0) == $v) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $v ?>" <?= $sel ?>><?= $v ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <p class="gdrcd-help">
                            <?= gdrcd_filter('out', $MESSAGE['register']['fields']['stats_info'] . ' ' . $PARAMETERS['settings']['cars_sum']) ?>
                        </p>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                        <a href="index.php" class="gdrcd-btn-ghost">
                            <?= gdrcd_filter('out', $MESSAGE['register']['forms']['abort']) ?>
                        </a>
                        <button type="submit" class="gdrcd-btn-primary">
                            <?= gdrcd_filter('out', $MESSAGE['register']['forms']['next']) ?>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7-7 7M3 12h18"/></svg>
                        </button>
                        <input type="hidden" name="fase" value="2"/>
                    </div>
                </form>
            </div>
        </section>

    <?php elseif ($fase === 2):
        $errors = $validate();
        ?>
        <?php if (!empty($errors)): ?>
            <div class="gdrcd-alert-error">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <ul class="space-y-1 list-disc list-inside">
                    <?php foreach ($errors as $err): ?>
                        <li><?= $err ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <form action="<?= $action ?>" method="post">
                <?= $render_hidden() ?>
                <input type="hidden" name="fase" value="1"/>
                <button type="submit" class="gdrcd-btn-secondary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <?= gdrcd_filter('out', $MESSAGE['register']['forms']['try_again']) ?>
                </button>
            </form>
        <?php else:
            $r_gen = ($_POST['genere'] === 'm') ? 'm' : 'f';
            $razza = gdrcd_query("SELECT sing_" . gdrcd_filter('in', $r_gen) . " AS nome_razza FROM razza WHERE id_razza = " . (0 + gdrcd_filter('num', $_POST['razza'])) . " LIMIT 1");
            ?>
            <section class="gdrcd-card">
                <div class="gdrcd-card-header">
                    <h2 class="gdrcd-h3"><?= gdrcd_filter('out', $MESSAGE['register']['summary']) ?></h2>
                </div>
                <div class="gdrcd-card-body">
                    <table class="gdrcd-stat-list">
                        <tbody>
                            <tr><td><?= gdrcd_filter('out', $MESSAGE['register']['fields']['email']) ?></td>
                                <td><?= gdrcd_filter('out', $_POST['email']) ?></td></tr>
                            <tr><td><?= gdrcd_filter('out', $MESSAGE['register']['fields']['name']) ?></td>
                                <td><?= gdrcd_filter('out', $_POST['nome']) ?></td></tr>
                            <tr><td><?= gdrcd_filter('out', $MESSAGE['register']['fields']['lastname']) ?></td>
                                <td><?= gdrcd_filter('out', $_POST['cognome']) ?></td></tr>
                            <tr><td><?= gdrcd_filter('out', $MESSAGE['register']['fields']['gender']) ?></td>
                                <td><?= $_POST['genere'] === 'm'
                                    ? gdrcd_filter('out', $MESSAGE['register']['fields']['gender_m'])
                                    : gdrcd_filter('out', $MESSAGE['register']['fields']['gender_f']) ?></td></tr>
                            <tr><td><?= gdrcd_filter('out', $PARAMETERS['names']['race']['sing']) ?></td>
                                <td><?= gdrcd_filter('out', $razza['nome_razza']) ?></td></tr>
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <tr><td><?= gdrcd_filter('out', $PARAMETERS['names']['stats']['car'.$i]) ?></td>
                                    <td><?= gdrcd_filter('num', $_POST['car'.$i]) ?></td></tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 md:px-8 py-4 border-t border-gdrcd-border flex flex-col-reverse sm:flex-row gap-3 sm:justify-end">
                    <form action="<?= $action ?>" method="post">
                        <?= $render_hidden() ?>
                        <input type="hidden" name="fase" value="1"/>
                        <button type="submit" class="gdrcd-btn-ghost">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            <?= gdrcd_filter('out', $MESSAGE['register']['forms']['back']) ?>
                        </button>
                    </form>
                    <form action="<?= $action ?>" method="post">
                        <?= $render_hidden() ?>
                        <input type="hidden" name="fase" value="3"/>
                        <button type="submit" class="gdrcd-btn-primary">
                            <?= gdrcd_filter('out', $MESSAGE['register']['forms']['ok']) ?>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

    <?php elseif ($fase === 3):
        $errors = $validate();
        ?>
        <?php if (!empty($errors)): ?>
            <div class="gdrcd-alert-error">
                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <ul class="space-y-1 list-disc list-inside">
                    <?php foreach ($errors as $err): ?><li><?= $err ?></li><?php endforeach; ?>
                </ul>
            </div>
            <form action="<?= $action ?>" method="post">
                <?= $render_hidden() ?>
                <input type="hidden" name="fase" value="1"/>
                <button type="submit" class="gdrcd-btn-secondary">
                    <?= gdrcd_filter('out', $MESSAGE['register']['forms']['try_again']) ?>
                </button>
            </form>
        <?php else:
            // Insert PG
            $lastpasschange_field = "";
            $lastpasschange_value = "";
            if ($PARAMETERS['mode']['alert_password_change'] === 'ON'
                && $PARAMETERS['settings']['alert_password_change']['alert_from_signup'] === 'OFF') {
                $lastpasschange_field = ", ultimo_cambiopass";
                $lastpasschange_value = ", NOW()";
            }

            $email = strtolower(gdrcd_filter_email($_POST['email']));
            $pass = gdrcd_genera_pass();

            gdrcd_query(
                "INSERT INTO personaggio (nome, cognome, pass, data_iscrizione, email, sesso, id_razza,"
                . " car0, car1, car2, car3, car4, car5, salute, salute_max, soldi, esperienza"
                . " $lastpasschange_field) VALUES ("
                . "'" . gdrcd_safe_name($_POST['nome']) . "',"
                . "'" . gdrcd_safe_name($_POST['cognome']) . "',"
                . "'" . gdrcd_password_hash($pass) . "', NOW(),"
                . "'" . gdrcd_encript($email) . "',"
                . "'" . gdrcd_filter('in', $_POST['genere']) . "',"
                . gdrcd_filter('num', $_POST['razza']) . ","
                . gdrcd_filter('num', $_POST['car0']) . ","
                . gdrcd_filter('num', $_POST['car1']) . ","
                . gdrcd_filter('num', $_POST['car2']) . ","
                . gdrcd_filter('num', $_POST['car3']) . ","
                . gdrcd_filter('num', $_POST['car4']) . ","
                . gdrcd_filter('num', $_POST['car5']) . ","
                . gdrcd_filter('num', $PARAMETERS['settings']['max_hp']) . ","
                . gdrcd_filter('num', $PARAMETERS['settings']['max_hp']) . ","
                . gdrcd_filter('num', $PARAMETERS['settings']['first_money']) . ","
                . gdrcd_filter('num', $PARAMETERS['settings']['first_px'])
                . " $lastpasschange_value)"
            );

            $send_email = ($PARAMETERS['mode']['emailconfirmation'] === 'ON');

            if ($send_email) {
                $text = $MESSAGE['register']['welcome']['message'][0] . ' ' . $PARAMETERS['info']['site_name']
                    . "\n\n " . $MESSAGE['register']['welcome']['message'][1]
                    . "\n     " . $MESSAGE['register']['welcome']['message'][2]
                    . "\n\n    " . $MESSAGE['register']['welcome']['message']['user'] . ' ' . gdrcd_safe_name($_POST['nome'])
                    . "\n" . $MESSAGE['register']['welcome']['message']['pass'] . ' ' . $pass
                    . "\n\n    " . $PARAMETERS['info']['webmaster_name'];

                $subject = $PARAMETERS['info']['site_name'] . ' - Registrazione di '
                    . gdrcd_safe_name($_POST['nome']) . ' ' . gdrcd_safe_name($_POST['cognome']);

                mail($email, $subject, $text, 'From: ' . gdrcd_filter('out', $PARAMETERS['info']['webmaster_email']));
            }

            gdrcd_query(
                "INSERT INTO messaggi (mittente, destinatario, spedito, testo) VALUES ("
                . "'" . gdrcd_filter('out', $PARAMETERS['info']['webmaster_name']) . "',"
                . "'" . gdrcd_safe_name($_POST['nome']) . "', NOW(),"
                . "'" . gdrcd_filter('out', $MESSAGE['register']['welcome']['message'][4]) . "')"
            );
            ?>
            <section class="gdrcd-card">
                <div class="gdrcd-card-body text-center space-y-4 py-8">
                    <span class="gdrcd-icon-circle bg-gdrcd-success-soft text-gdrcd-success border-green-200">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <h2 class="gdrcd-h2"><?= gdrcd_filter('out', $MESSAGE['register']['welcome']['message']['ok']) ?></h2>

                    <p class="gdrcd-prose max-w-xl mx-auto">
                        <?= gdrcd_filter('out', $MESSAGE['register']['welcome']['message'][0]) ?>
                        <strong><?= gdrcd_filter('out', $PARAMETERS['info']['site_name']) ?></strong>
                        <?= gdrcd_filter('out', $MESSAGE['register']['welcome']['message'][1]) ?>
                    </p>

                    <?php if ($send_email): ?>
                        <p class="gdrcd-muted">
                            <?= gdrcd_filter('out', $MESSAGE['register']['welcome']['message'][3]) ?>
                            <strong class="text-gdrcd-text"><?= $email ?></strong>
                        </p>
                    <?php else: ?>
                        <p class="gdrcd-prose"><?= gdrcd_filter('out', $MESSAGE['register']['welcome']['message'][2]) ?></p>
                        <div class="inline-block text-left bg-gdrcd-panel-alt border border-gdrcd-border rounded-gdrcd px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gdrcd-muted mb-1">Credenziali</div>
                            <div class="text-sm">
                                <span class="text-gdrcd-muted"><?= gdrcd_filter('out', $MESSAGE['register']['welcome']['message']['user']) ?></span>
                                <strong class="ml-1"><?= gdrcd_safe_name($_POST['nome']) ?></strong>
                            </div>
                            <div class="text-sm">
                                <span class="text-gdrcd-muted"><?= gdrcd_filter('out', $MESSAGE['register']['welcome']['message']['pass']) ?></span>
                                <strong class="ml-1 font-mono"><?= $pass ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="pt-4">
                        <a href="index.php" class="gdrcd-btn-primary">
                            <?= gdrcd_filter('out', $MESSAGE['register']['welcome']['back'] . ' ' . strtolower($PARAMETERS['info']['homepage_name'])) ?>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7-7 7M3 12h18"/></svg>
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
