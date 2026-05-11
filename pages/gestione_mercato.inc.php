<?php
/**
 * Gestione mercato (main.php?page=gestione_mercato)
 * CRUD oggetti + assegnazione a mercato o PG.
 */

if ($_SESSION['permessi'] < MODERATOR) {
    echo '<div class="gdrcd-alert-error">'
       . '<svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>'
       . '<div>' . gdrcd_filter('out', $MESSAGE['error']['not_allowed']) . '</div>'
       . '</div>';
    return;
}

$lbl     = $MESSAGE['interface']['administration']['items'];
$op      = $_POST['op'] ?? null;
$loaded_item = null;
$alerts  = [];

$slots = [
    INVENTARIO => $lbl['fit_in']['inventory'],
    ZAINO      => $lbl['fit_in']['bag'],
    MANODX     => $lbl['fit_in']['hand_dx'],
    MANOSX     => $lbl['fit_in']['hand_sx'],
    TORSO      => $lbl['fit_in']['chest'],
    GAMBE      => $lbl['fit_in']['legs'],
    PIEDI      => $lbl['fit_in']['feet'],
    TESTA      => $lbl['fit_in']['head'],
    ANELLO    => $lbl['fit_in']['ring'],
    COLLO      => $lbl['fit_in']['neck'],
];

/* ============================================================
 * Handler
 * ============================================================ */
if ($op === 'load') {
    $loaded_item = gdrcd_query("SELECT * FROM oggetto WHERE id_oggetto = " . gdrcd_filter('num', $_POST['load_item']));
} elseif ($op === 'update') {
    if (isset($_POST['modifica'])) {
        gdrcd_query(
            "UPDATE oggetto SET
                tipo = " . gdrcd_filter('num', $_POST['tipo_oggetto']) . ",
                nome = '" . gdrcd_filter('in', $_POST['nome_oggetto']) . "',
                urlimg = '" . gdrcd_filter('in', $_POST['img_oggetto']) . "',
                descrizione = '" . gdrcd_filter('in', $_POST['descrizione_oggetto']) . "',
                costo = " . gdrcd_filter('num', $_POST['costo_oggetto']) . ",
                ubicabile = " . gdrcd_filter('num', $_POST['fit_in']) . ",
                attacco = " . gdrcd_filter('num', $_POST['attacco_oggetto']) . ",
                difesa = " . gdrcd_filter('num', $_POST['difesa_oggetto']) . ",
                cariche = " . gdrcd_filter('num', $_POST['cariche_oggetto']) . ",
                bonus_car0 = " . gdrcd_filter('num', $_POST['car0_oggetto']) . ",
                bonus_car1 = " . gdrcd_filter('num', $_POST['car1_oggetto']) . ",
                bonus_car2 = " . gdrcd_filter('num', $_POST['car2_oggetto']) . ",
                bonus_car3 = " . gdrcd_filter('num', $_POST['car3_oggetto']) . ",
                bonus_car4 = " . gdrcd_filter('num', $_POST['car4_oggetto']) . ",
                bonus_car5 = " . gdrcd_filter('num', $_POST['car5_oggetto']) . "
             WHERE id_oggetto = " . gdrcd_filter('num', $_POST['id_oggetto'])
        );
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
    } elseif (isset($_POST['elimina'])) {
        $id   = gdrcd_filter('num', $_POST['id_oggetto']);
        $rec  = gdrcd_query("SELECT costo FROM oggetto WHERE id_oggetto = " . $id . " LIMIT 1");
        $refound = gdrcd_query("SELECT nome FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id, 'result');
        while ($r = gdrcd_query($refound, 'fetch')) {
            gdrcd_query("UPDATE personaggio SET soldi = soldi + " . gdrcd_filter('num', $rec['costo']) . " WHERE nome = '" . gdrcd_filter_in($r['nome']) . "'");
        }
        gdrcd_query($refound, 'free');
        gdrcd_query("DELETE FROM oggetto WHERE id_oggetto = " . $id . " LIMIT 1");
        gdrcd_query("DELETE FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id);
        $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
    }
} elseif ($op === 'insert') {
    $immagine = empty($_POST['img_oggetto']) ? 'standard_oggetto.png' : gdrcd_filter('in', $_POST['img_oggetto']);
    gdrcd_query(
        "INSERT INTO oggetto (tipo, nome, urlimg, descrizione, costo, ubicabile, attacco, difesa, cariche,
                              bonus_car0, bonus_car1, bonus_car2, bonus_car3, bonus_car4, bonus_car5,
                              creatore, data_inserimento) VALUES ("
        . gdrcd_filter('num', $_POST['tipo_oggetto']) . ","
        . "'" . gdrcd_filter('in', $_POST['nome_oggetto']) . "',"
        . "'" . $immagine . "',"
        . "'" . gdrcd_filter('in', $_POST['descrizione_oggetto']) . "',"
        . gdrcd_filter('num', $_POST['costo_oggetto']) . ","
        . gdrcd_filter('num', $_POST['fit_in']) . ","
        . gdrcd_filter('num', $_POST['attacco_oggetto']) . ","
        . gdrcd_filter('num', $_POST['difesa_oggetto']) . ","
        . gdrcd_filter('num', $_POST['cariche_oggetto']) . ","
        . gdrcd_filter('num', $_POST['car0_oggetto']) . ","
        . gdrcd_filter('num', $_POST['car1_oggetto']) . ","
        . gdrcd_filter('num', $_POST['car2_oggetto']) . ","
        . gdrcd_filter('num', $_POST['car3_oggetto']) . ","
        . gdrcd_filter('num', $_POST['car4_oggetto']) . ","
        . gdrcd_filter('num', $_POST['car5_oggetto']) . ","
        . "'" . gdrcd_filter('in', $_SESSION['login']) . "', NOW())"
    );
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['inserted'])];
} elseif ($op === 'assign' && (int)gdrcd_filter('num', $_POST['num_oggetti'] ?? 0) > 0) {
    $id_obj  = gdrcd_filter('num', $_POST['id_oggetto']);
    $num     = gdrcd_filter('num', $_POST['num_oggetti']);
    $target  = $_POST['give_item'] ?? '';
    $cariche = gdrcd_filter('num', $_POST['cariche_oggetto'] ?? 0);

    if ($target === 'mercato') {
        $check = gdrcd_query("SELECT id_oggetto FROM mercato WHERE id_oggetto = " . $id_obj, 'result');
        if (gdrcd_query($check, 'num_rows') > 0) {
            gdrcd_query($check, 'free');
            gdrcd_query("UPDATE mercato SET numero = " . $num . " WHERE id_oggetto = " . $id_obj);
        } else {
            gdrcd_query("INSERT INTO mercato (id_oggetto, numero) VALUES (" . $id_obj . ", " . $num . ")");
        }
    } else {
        $target_in = gdrcd_filter('in', $target);
        $check = gdrcd_query("SELECT id_oggetto FROM clgpersonaggiooggetto WHERE id_oggetto = " . $id_obj . " AND nome = '" . $target_in . "'", 'result');
        if (gdrcd_query($check, 'num_rows') > 0) {
            gdrcd_query($check, 'free');
            gdrcd_query("UPDATE clgpersonaggiooggetto SET numero = numero + " . $num . " WHERE id_oggetto = " . $id_obj . " AND nome = '" . $target_in . "'");
        } else {
            gdrcd_query("INSERT INTO clgpersonaggiooggetto (nome, id_oggetto, cariche, numero) VALUES ("
                . "'" . $target_in . "', " . $id_obj . ", " . $cariche . ", " . $num . ")");
        }
    }
    $alerts[] = ['success', gdrcd_filter('out', $MESSAGE['warning']['modified'])];
}

/* Default valori se nessun oggetto caricato */
$item = $loaded_item ?? [
    'id_oggetto' => 0, 'tipo' => 0, 'nome' => '', 'urlimg' => '', 'descrizione' => '',
    'costo' => 0, 'ubicabile' => INVENTARIO, 'attacco' => 0, 'difesa' => 0, 'cariche' => 0,
    'bonus_car0' => 0, 'bonus_car1' => 0, 'bonus_car2' => 0, 'bonus_car3' => 0,
    'bonus_car4' => 0, 'bonus_car5' => 0,
];
$is_edit = ($loaded_item !== null);
?>

<div class="space-y-6">

    <header class="space-y-2">
        <h2 class="gdrcd-h1"><?= gdrcd_filter('out', $MESSAGE['interface']['administration']['items']['load_item'] ?? 'Gestione mercato') ?></h2>
        <p class="gdrcd-muted">Gestione oggetti del gioco e loro assegnazione al mercato o ai personaggi.</p>
    </header>

    <?php foreach ($alerts as [$kind, $msg]): ?>
        <div class="gdrcd-alert-<?= $kind ?>">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <div><?= $msg ?></div>
        </div>
    <?php endforeach; ?>

    <!-- ============================================================
         Card: caricamento oggetto esistente
         ============================================================ -->
    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $lbl['load_item']) ?></h3>
        </div>
        <div class="gdrcd-card-body">
            <form action="main.php?page=gestione_mercato" method="post" class="flex flex-wrap items-end gap-3">
                <?= gdrcd_csrf_field() ?>
                <div class="flex-1 min-w-[16rem]">
                    <label class="gdrcd-label" for="gm_load">Oggetto</label>
                    <select class="gdrcd-select" id="gm_load" name="load_item">
                        <?php $list = gdrcd_query("SELECT id_oggetto, nome FROM oggetto ORDER BY nome", 'result');
                        while ($o = gdrcd_query($list, 'fetch')):
                            $sel = ((int)$o['id_oggetto'] === (int)$item['id_oggetto']) ? 'selected' : '';
                            ?>
                            <option value="<?= (int)$o['id_oggetto'] ?>" <?= $sel ?>>
                                <?= gdrcd_filter('out', $o['nome']) ?>
                            </option>
                        <?php endwhile;
                        gdrcd_query($list, 'free');
                        ?>
                    </select>
                </div>
                <input type="hidden" name="op" value="load"/>
                <button type="submit" class="gdrcd-btn-secondary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Carica
                </button>
            </form>
        </div>
    </section>

    <!-- ============================================================
         Card: dettagli oggetto (insert o update)
         ============================================================ -->
    <section class="gdrcd-card">
        <div class="gdrcd-card-header">
            <h3 class="gdrcd-h3"><?= $is_edit ? 'Modifica oggetto' : 'Nuovo oggetto' ?></h3>
            <?php if ($is_edit): ?>
                <p class="gdrcd-muted text-xs">ID #<?= (int)$item['id_oggetto'] ?></p>
            <?php endif; ?>
        </div>
        <div class="gdrcd-card-body">
            <form action="main.php?page=gestione_mercato" method="post" class="space-y-5">
                <?= gdrcd_csrf_field() ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="gdrcd-label" for="om_tipo"><?= gdrcd_filter('out', $lbl['item_type']) ?></label>
                        <select class="gdrcd-select" id="om_tipo" name="tipo_oggetto">
                            <?php $tipi = gdrcd_query("SELECT * FROM codtipooggetto ORDER BY descrizione", 'result');
                            while ($t = gdrcd_query($tipi, 'fetch')):
                                $sel = ((int)$item['tipo'] === (int)$t['cod_tipo']) ? 'selected' : '';
                                ?>
                                <option value="<?= (int)$t['cod_tipo'] ?>" <?= $sel ?>>
                                    <?= gdrcd_filter('out', $t['descrizione']) ?>
                                </option>
                            <?php endwhile;
                            gdrcd_query($tipi, 'free');
                            ?>
                        </select>
                        <p class="gdrcd-help">
                            <a class="gdrcd-link" href="main.php?page=gestione_tipi&types=items">
                                <?= gdrcd_filter('out', $lbl['link']['menage_types']) ?>
                            </a>
                        </p>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="om_nome"><?= gdrcd_filter('out', $lbl['item_name']) ?></label>
                        <input class="gdrcd-input" type="text" id="om_nome" name="nome_oggetto"
                               value="<?= gdrcd_filter('out', $item['nome']) ?>" required/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="om_img"><?= gdrcd_filter('out', $lbl['item_image']) ?></label>
                        <input class="gdrcd-input" type="text" id="om_img" name="img_oggetto"
                               value="<?= gdrcd_filter('out', $item['urlimg']) ?>"/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="om_slot"><?= gdrcd_filter('out', $lbl['item_fit_in']) ?></label>
                        <select class="gdrcd-select" id="om_slot" name="fit_in">
                            <?php foreach ($slots as $val => $label):
                                $sel = ((int)$item['ubicabile'] === $val) ? 'selected' : '';
                                ?>
                                <option value="<?= $val ?>" <?= $sel ?>>
                                    <?= gdrcd_filter('out', $label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="gdrcd-label" for="om_descr"><?= gdrcd_filter('out', $lbl['item_info']) ?></label>
                    <textarea class="gdrcd-textarea" id="om_descr" name="descrizione_oggetto" rows="4"><?= gdrcd_filter('out', $item['descrizione']) ?></textarea>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="gdrcd-label" for="om_costo"><?= gdrcd_filter('out', $lbl['item_price']) ?></label>
                        <input class="gdrcd-input" type="number" id="om_costo" name="costo_oggetto"
                               value="<?= (int)$item['costo'] ?>"/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="om_atk"><?= gdrcd_filter('out', $lbl['item_bonus_offensive']) ?></label>
                        <input class="gdrcd-input" type="number" id="om_atk" name="attacco_oggetto"
                               value="<?= (int)$item['attacco'] ?>"/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="om_def"><?= gdrcd_filter('out', $lbl['item_bonus_defensive']) ?></label>
                        <input class="gdrcd-input" type="number" id="om_def" name="difesa_oggetto"
                               value="<?= (int)$item['difesa'] ?>"/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="om_chg"><?= gdrcd_filter('out', $lbl['item_charges']) ?></label>
                        <input class="gdrcd-input" type="number" id="om_chg" name="cariche_oggetto"
                               value="<?= (int)$item['cariche'] ?>"/>
                    </div>
                </div>

                <div>
                    <div class="gdrcd-eyebrow mb-2"><?= gdrcd_filter('out', $lbl['item_bonus']) ?></div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <div>
                                <label class="block text-xs font-medium text-gdrcd-muted mb-1" for="om_car<?= $i ?>">
                                    <?= gdrcd_filter('out', $PARAMETERS['names']['stats']['car'.$i]) ?>
                                </label>
                                <input class="gdrcd-input" type="number" id="om_car<?= $i ?>" name="car<?= $i ?>_oggetto"
                                       value="<?= (int)$item['bonus_car'.$i] ?>"/>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-3 sm:justify-end pt-2 border-t border-gdrcd-border">
                    <?php if ($is_edit): ?>
                        <a href="main.php?page=gestione_mercato" class="gdrcd-btn-ghost">
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['cancel']) ?>
                        </a>
                        <input type="hidden" name="op" value="update"/>
                        <input type="hidden" name="id_oggetto" value="<?= (int)$item['id_oggetto'] ?>"/>
                        <button type="submit" name="elimina" value="1" class="gdrcd-btn-danger"
                                onclick="return confirm('Eliminare questo oggetto e rimborsare i possessori?');">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['delete']) ?>
                        </button>
                        <button type="submit" name="modifica" value="1" class="gdrcd-btn-primary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="op" value="insert"/>
                        <button type="submit" name="modifica" value="1" class="gdrcd-btn-primary">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <?= gdrcd_filter('out', $MESSAGE['interface']['forms']['submit']) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <!-- ============================================================
         Card: assegna oggetto (solo se loaded)
         ============================================================ -->
    <?php if ($is_edit): ?>
        <section class="gdrcd-card">
            <div class="gdrcd-card-header">
                <h3 class="gdrcd-h3"><?= gdrcd_filter('out', $lbl['give_item']) ?></h3>
            </div>
            <div class="gdrcd-card-body">
                <form action="main.php?page=gestione_mercato" method="post" class="grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-3 items-end">
                    <?= gdrcd_csrf_field() ?>
                    <div>
                        <label class="gdrcd-label" for="om_num"><?= gdrcd_filter('out', $lbl['number_item']) ?></label>
                        <input class="gdrcd-input" type="number" id="om_num" name="num_oggetti" value="1" min="1" required/>
                    </div>
                    <div>
                        <label class="gdrcd-label" for="om_dest"><?= gdrcd_filter('out', $lbl['destination_item']) ?></label>
                        <select class="gdrcd-select" id="om_dest" name="give_item">
                            <option value="mercato"><?= gdrcd_filter('out', $PARAMETERS['names']['market_name']) ?></option>
                            <optgroup label="Personaggi">
                                <?php $chars = gdrcd_query("SELECT nome FROM personaggio ORDER BY nome", 'result');
                                while ($c = gdrcd_query($chars, 'fetch')): ?>
                                    <option value="<?= gdrcd_filter('out', $c['nome']) ?>"><?= gdrcd_filter('out', $c['nome']) ?></option>
                                <?php endwhile;
                                gdrcd_query($chars, 'free');
                                ?>
                            </optgroup>
                        </select>
                    </div>
                    <div>
                        <input type="hidden" name="id_oggetto" value="<?= (int)$item['id_oggetto'] ?>"/>
                        <input type="hidden" name="cariche_oggetto" value="<?= (int)$item['cariche'] ?>"/>
                        <input type="hidden" name="op" value="assign"/>
                        <button type="submit" class="gdrcd-btn-primary w-full">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7-7 7M3 12h18"/></svg>
                            Assegna
                        </button>
                    </div>
                </form>
            </div>
        </section>
    <?php endif; ?>

</div>
