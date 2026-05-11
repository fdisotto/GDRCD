<?php
if (!gdrcd_controllo_permessi($PARAMETERS['administration']['maintenance']['access_level'])) {
    return;
}

/*Eseguo l'aggiornamento*/
gdrcd_query("DELETE FROM clgpersonaggiooggetto WHERE nome IN (SELECT nome FROM personaggio WHERE permessi = -1)");
gdrcd_query("OPTIMIZE TABLE clgpersonaggiooggetto");

gdrcd_query("DELETE FROM clgpersonaggioabilita WHERE nome IN (SELECT nome FROM personaggio WHERE permessi = -1)");
gdrcd_query("OPTIMIZE TABLE clgpersonaggioabilita");

gdrcd_query("DELETE FROM clgpersonaggiomostrine WHERE nome IN (SELECT nome FROM personaggio WHERE permessi = -1)");
gdrcd_query("OPTIMIZE TABLE clgpersonaggiomostrine");

gdrcd_query("DELETE FROM clgpersonaggioruolo WHERE personaggio IN (SELECT nome AS personaggio FROM personaggio WHERE permessi = -1)");
gdrcd_query("OPTIMIZE TABLE clgpersonaggioruolo");

gdrcd_query("DELETE FROM messaggi WHERE mittente IN (SELECT nome AS personaggio FROM personaggio WHERE permessi = -1)");
gdrcd_query("DELETE FROM messaggi WHERE destinatario IN (SELECT nome AS personaggio FROM personaggio WHERE permessi = -1)");
gdrcd_query("DELETE FROM backmessaggi WHERE mittente IN (SELECT nome AS personaggio FROM personaggio WHERE permessi = -1)");
gdrcd_query("DELETE FROM backmessaggi WHERE destinatario IN (SELECT nome AS personaggio FROM personaggio WHERE permessi = -1)");
gdrcd_query("OPTIMIZE TABLE messaggi");

gdrcd_query("DELETE FROM araldo_letto WHERE nome IN (SELECT nome AS personaggio FROM personaggio WHERE permessi = -1)");
gdrcd_query("OPTIMIZE TABLE araldo_letto");

gdrcd_query("UPDATE chat SET mittente = 'Cancellato' WHERE mittente IN (SELECT nome AS personaggio FROM personaggio WHERE permessi = -1)");
gdrcd_query("UPDATE chat SET destinatario = 'Cancellato' WHERE destinatario IN (SELECT nome AS personaggio FROM personaggio WHERE permessi = -1)");
gdrcd_query("OPTIMIZE TABLE chat");

gdrcd_query("UPDATE log SET nome_interessato = 'Cancellato' WHERE nome_interessato IN (SELECT nome AS personaggio FROM personaggio WHERE permessi = -1)");
gdrcd_query("OPTIMIZE TABLE log");

gdrcd_query("UPDATE messaggioaraldo SET autore = 'Cancellato' WHERE autore IN (SELECT nome AS personaggio FROM personaggio WHERE permessi = -1)");
gdrcd_query("OPTIMIZE TABLE messaggiaraldo");

gdrcd_query("DELETE FROM personaggio WHERE permessi = -1");
gdrcd_query("OPTIMIZE TABLE personaggio");
?>
<div class="gdrcd-alert-success">
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    <div>
        <strong>Personaggi cancellati rimossi.</strong>
        <span class="text-gdrcd-muted">·</span>
        <?= gdrcd_filter('out', $MESSAGE['warning']['modified']) ?>
    </div>
</div>