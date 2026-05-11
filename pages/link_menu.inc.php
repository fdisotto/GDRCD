<?php
/**
 * Widget sidebar: menu link + select mappa "vai a".
 */

$mkey = !empty($params['menu_key']) ? $params['menu_key'] : 'menu';
$theme = $PARAMETERS['themes']['current_theme'];
$show_gotomap = ($PARAMETERS['mode']['gotomap_list'] ?? 'OFF') === 'ON' && empty($params['no_gotomap_list']);

$gotomap_list = [];
if ($show_gotomap) {
    $result = gdrcd_query(
        "SELECT mappa_click.id_click, mappa_click.nome, mappa.id, mappa.nome AS nome_chat,
                mappa.chat, mappa.pagina, mappa.id_mappa_collegata
         FROM mappa_click
         LEFT JOIN mappa ON mappa.id_mappa = mappa_click.id_click",
        'result'
    );
    if (gdrcd_query($result, 'num_rows') > 0) {
        while ($row = gdrcd_query($result, 'fetch')) {
            $gotomap_list[$row['nome'] . '|@|' . $row['id_click']][$row['id']] = [
                'nome'            => $row['nome_chat'],
                'chat'            => $row['chat'],
                'pagina'          => $row['pagina'],
                'mappa_collegata' => $row['id_mappa_collegata'],
            ];
        }
        gdrcd_query($result, 'free');
    }
}

$menu_title = !empty($PARAMETERS['names']['gamemenu'][$mkey])
    ? gdrcd_filter('out', $PARAMETERS['names']['gamemenu'][$mkey])
    : '';
$hovers = [];
?>

<?php if ($menu_title !== ''): ?>
    <div class="gdrcd-widget-title flex items-center gap-2 -m-4 mb-3 px-4 py-2">
        <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
        <span><?= $menu_title ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($gotomap_list)): ?>
    <label class="block">
        <span class="text-[11px] uppercase tracking-wide text-gdrcd-text-soft font-display flex items-center gap-1 mb-1">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.553 2.776A1 1 0 0021 18.882V8.118a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
            Vai a
        </span>
        <select id="gotomap" class="gdrcd-select w-full text-sm" onchange="self.location.href=this.value;">
            <?php foreach ($gotomap_list as $infoMap => $infoLocation):
                $splitInfoMap = explode('|@|', $infoMap);
                $is_sel_map = ($_SESSION['mappa'] == $splitInfoMap[1] && $_SESSION['luogo'] == -1);
            ?>
                <option value="main.php?page=mappaclick&map_id=<?= htmlspecialchars($splitInfoMap[1]) ?>"<?= $is_sel_map ? ' selected' : '' ?>>
                    <?= htmlspecialchars($splitInfoMap[0]) ?>
                </option>
                <?php if (is_array($infoLocation)):
                    foreach ($infoLocation as $idLoc => $infoLoc):
                        if (empty($infoLoc['nome'])) continue;
                        if ($infoLoc['chat'] != 0) {
                            $valueLoc = 'dir=' . $idLoc . '&map_id=' . $splitInfoMap[1];
                        } elseif ($infoLoc['mappa_collegata'] != 0) {
                            $valueLoc = 'page=mappaclick&map_id=' . $infoLoc['mappa_collegata'];
                        } else {
                            $valueLoc = 'page=' . $infoLoc['pagina'];
                        }
                        $is_sel = ($_SESSION['luogo'] == $idLoc && $_SESSION['luogo'] != -1);
                ?>
                    <option value="main.php?<?= htmlspecialchars($valueLoc) ?>"<?= $is_sel ? ' selected' : '' ?>>
                        &raquo; <?= htmlspecialchars($infoLoc['nome']) ?>
                    </option>
                <?php endforeach; endif;
            endforeach; ?>
        </select>
    </label>
<?php endif; ?>

<?php if (!empty($PARAMETERS[$mkey])): ?>
    <div class="grid grid-cols-3 gap-2 pt-1 <?= !empty($params['class']) ? htmlspecialchars($params['class']) : '' ?>">
        <?php foreach ($PARAMETERS[$mkey] as $key => $link_menu):
            if (empty($link_menu['url'])) continue;
            $id_attr = 'link_' . $mkey . '_' . $key;
            $title   = !empty($link_menu['text']) ? gdrcd_filter('out', $link_menu['text']) : '';
            $extra_attrs = '';
            foreach ($link_menu as $k => $v) {
                if (!in_array($k, ['text', 'image_file', 'url', 'image_file_onclick', 'sprite', 'class', 'style'], true)) {
                    $extra_attrs .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars($v) . '"';
                }
            }

            if (!empty($link_menu['image_file_onclick'])) {
                $hovers[$id_attr] = [
                    'normal' => '/themes/' . $theme . '/imgs/' . $mkey . '/' . $link_menu['image_file'],
                    'hover'  => '/themes/' . $theme . '/imgs/' . $mkey . '/' . $link_menu['image_file_onclick'],
                ];
            }
        ?>
            <a href="<?= htmlspecialchars($link_menu['url']) ?>"
               id="<?= htmlspecialchars($id_attr) ?>"
               title="<?= $title ?>"
               class="link_menu group flex flex-col items-center gap-1 p-2 rounded-md border border-gdrcd-border bg-gdrcd-panel-alt hover:border-gdrcd-accent hover:bg-gdrcd-accent-soft transition text-center"
               <?= $extra_attrs ?>>
                <?php if (!empty($link_menu['image_file']) && empty($link_menu['sprite'])): ?>
                    <img src="/themes/<?= htmlspecialchars($theme) ?>/imgs/<?= htmlspecialchars($mkey) ?>/<?= htmlspecialchars($link_menu['image_file']) ?>"
                         alt="<?= $title ?>" class="w-7 h-7 object-contain"/>
                <?php elseif (!empty($link_menu['sprite'])): ?>
                    <span class="inline-block w-7 h-7 bg-no-repeat bg-center bg-contain sprite"
                          style="background-image:url(themes/<?= htmlspecialchars($theme) ?>/imgs/<?= htmlspecialchars($mkey) ?>/<?= htmlspecialchars($link_menu['image_file']) ?>)"></span>
                <?php endif; ?>
                <span class="text-[10px] text-gdrcd-text-soft group-hover:text-gdrcd-accent leading-tight">
                    <?= $title ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($hovers)): ?>
        <script>
        (function() {
            var hovers = <?= json_encode($hovers) ?>;
            document.querySelectorAll('.link_menu').forEach(function(a) {
                var id = a.id;
                if (!hovers[id]) return;
                var img = a.querySelector('img');
                if (!img) return;
                a.addEventListener('mouseenter', function() { img.src = hovers[id].hover; });
                a.addEventListener('mouseleave', function() { img.src = hovers[id].normal; });
            });
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>
