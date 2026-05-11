<div class="space-y-5">
    <header>
        <h2 class="gdrcd-h2 mb-1"><?= $MESSAGE['homepage']['main_content']['welcome'] ?></h2>
        <p class="gdrcd-muted">Versione <span class="gdrcd-badge-accent ml-1"><?= $PARAMETERS['info']['GDRCD'] ?></span></p>
    </header>

    <div class="gdrcd-prose space-y-4">
        <p>
            <strong>GDRCD</strong> è un software CMS (Content Management System) per la realizzazione di browser game di genere "gioco di ruolo play by chat",
            realizzato nella sua versione da un progetto di <strong>Romeo Gentile</strong> nel 2004 e mantenuto nel tempo da diversi autori che ne hanno permesso la sua diffusione.
        </p>
        <p>
            Stai utilizzando la versione <strong><?= $PARAMETERS['info']['GDRCD'] ?></strong> di GDRCD, sviluppata dal
            <a class="gdrcd-link" href="https://github.com/orgs/GDRCD/people" target="_blank" rel="noopener">Team di GDRCD</a>
            sulla base del lavoro fatto da <strong>Salvatore (Blancks) Rotondo</strong> e <strong>breaker</strong> con <strong>GDRCD 5.1</strong>.
        </p>
        <p>
            È possibile consultare i cambiamenti che vengono effettuati di versione in versione nei
            <a class="gdrcd-link" href="https://github.com/GDRCD/GDRCD/releases" target="_blank" rel="noopener">changelog ufficiali</a> del software.
        </p>

        <div class="gdrcd-alert-warning">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
            <div>
                La versione 5.6 <strong>non è retrocompatibile</strong> con le versioni inferiori alla 5.5.
                Dalla 5.5 è stato introdotto il <strong>rilevamento delle modifiche progressive al database</strong> con aggiornamento automatico,
                rendendo le versioni superiori alla 5.5 compatibili con quelle successive.
            </div>
        </div>

        <p>
            Trovi le istruzioni nel <a class="gdrcd-link" href="ISTRUZIONI.txt">manuale</a> allegato.
            Prima di iniziare, leggi la <a class="gdrcd-link" href="license.md">licenza d'uso</a>.
        </p>
        <p>
            Vuoi contribuire? Visita il <a class="gdrcd-link" href="http://www.gdr-online.com/readforum.asp?id=137092">topic dedicato</a> su
            <a class="gdrcd-link" href="https://gdr-online.com/" target="_blank" rel="noopener">GdR-Online.com</a>,
            consulta la <a class="gdrcd-link" href="https://github.com/GDRCD/GDRCD/wiki">Wiki su GitHub</a> o iscriviti al
            <a class="gdrcd-link" href="https://discord.gg/DJWaXPrQPQ">Server Discord ufficiale</a>.
            <em>Ogni aiuto è prezioso.</em>
        </p>

        <p class="text-gdrcd-accent font-display text-lg">Buon divertimento!</p>
    </div>
</div>
