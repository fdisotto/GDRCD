<?php

/**
 * Crea la tabella `legal_pages` per memorizzare i testi delle pagine
 * legali (privacy policy, termini di servizio) editabili da admin.
 *
 * I contenuti sono renderizzati lato server con `gdrcd_bbcoder()` e sono
 * mostrati nelle pagine pubbliche:
 *   - main.php?page=privacy_policy
 *   - main.php?page=tos
 *
 * Editing dal pannello di gestione (SUPERUSER):
 *   - main.php?page=gestione/legal
 */
class GDRCDLegalPages extends DbMigration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        gdrcd_query("
            CREATE TABLE IF NOT EXISTS legal_pages (
                slug       VARCHAR(64) NOT NULL,
                title      VARCHAR(255) NOT NULL,
                body       MEDIUMTEXT NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by VARCHAR(50) NULL,
                PRIMARY KEY (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $rows = [
            [
                'privacy_policy',
                'Informativa sulla privacy',
                "[b]Informativa sulla privacy[/b]\n\n"
                . "La presente informativa descrive le modalita' con cui vengono trattati i dati personali "
                . "degli utenti che utilizzano questo sito di gioco di ruolo.\n\n"
                . "[b]1. Titolare del trattamento[/b]\n"
                . "Il titolare del trattamento e' il webmaster del sito, contattabile all'indirizzo "
                . "email indicato nella homepage del gioco.\n\n"
                . "[b]2. Dati raccolti[/b]\n"
                . "Vengono raccolti e trattati i seguenti dati:\n"
                . "- Indirizzo email fornito in fase di registrazione, utilizzato per le comunicazioni di servizio "
                . "(recupero password, notifiche amministrative).\n"
                . "- Indirizzo IP di accesso, registrato a fini di sicurezza (rate-limit dei tentativi di login, "
                . "tracciamento abusi, log tecnici).\n"
                . "- Dati di attivita' di gioco (messaggi in chat, missive private, post sui forum, modifiche "
                . "alla scheda del personaggio) connessi alla normale funzione del sito.\n"
                . "- Cookie tecnici necessari al mantenimento della sessione di login.\n\n"
                . "[b]3. Finalita' del trattamento[/b]\n"
                . "I dati sono trattati esclusivamente per:\n"
                . "- Erogazione del servizio di gioco.\n"
                . "- Sicurezza del sito e prevenzione di abusi.\n"
                . "- Adempimento di obblighi di legge.\n\n"
                . "[b]4. Conservazione dei dati[/b]\n"
                . "I dati di gioco sono conservati per tutta la durata dell'account. I log tecnici (tentativi "
                . "di login, log di chat) sono conservati per il tempo strettamente necessario alle finalita' "
                . "di sicurezza, e comunque non oltre 12 mesi salvo diversa necessita' legale.\n\n"
                . "[b]5. Diritti dell'interessato (GDPR)[/b]\n"
                . "Ai sensi del Regolamento (UE) 2016/679 (GDPR) l'utente ha diritto di:\n"
                . "- Accedere ai propri dati personali.\n"
                . "- Richiederne la rettifica o la cancellazione.\n"
                . "- Limitare il trattamento o opporvisi.\n"
                . "- Richiedere la portabilita' dei dati.\n"
                . "- Proporre reclamo all'autorita' di controllo competente (Garante per la protezione dei "
                . "dati personali).\n\n"
                . "[b]6. Contatti[/b]\n"
                . "Per esercitare i propri diritti o richiedere ulteriori informazioni e' possibile contattare "
                . "il webmaster all'indirizzo email indicato in homepage.\n\n"
                . "[i]Il presente testo costituisce un modello di base e deve essere personalizzato dal "
                . "gestore del sito in base alle specifiche modalita' di trattamento adottate.[/i]",
            ],
            [
                'tos',
                'Termini di servizio',
                "[b]Termini di servizio[/b]\n\n"
                . "L'utilizzo di questo sito di gioco di ruolo e' subordinato all'accettazione dei seguenti "
                . "termini di servizio. La registrazione di un account costituisce piena accettazione delle "
                . "presenti condizioni.\n\n"
                . "[b]1. Oggetto del servizio[/b]\n"
                . "Il sito offre un ambiente di gioco di ruolo testuale a scopo ricreativo. La partecipazione "
                . "e' gratuita e volontaria.\n\n"
                . "[b]2. Regole d'uso del sito[/b]\n"
                . "L'utente si impegna a:\n"
                . "- Fornire dati di registrazione veritieri (in particolare un indirizzo email valido).\n"
                . "- Utilizzare il sito in conformita' alla legge italiana e al regolamento di gioco pubblicato.\n"
                . "- Non condividere le proprie credenziali con terzi e non utilizzare account di altri utenti.\n"
                . "- Rispettare gli altri giocatori e lo staff.\n\n"
                . "[b]3. Comportamento in gioco[/b]\n"
                . "All'interno della chat, dei forum e di tutti gli spazi interattivi del sito e' richiesto "
                . "un comportamento corretto, coerente con l'ambientazione e rispettoso degli altri partecipanti. "
                . "Il regolamento di gioco specifico (consultabile dal menu utente) integra le presenti regole.\n\n"
                . "[b]4. Divieti[/b]\n"
                . "E' espressamente vietato:\n"
                . "- Pubblicare contenuti illegali, diffamatori, offensivi, discriminatori, osceni o lesivi "
                . "dei diritti di terzi.\n"
                . "- Utilizzare il sito per attivita' di spam, phishing, distribuzione di malware o qualunque "
                . "altra attivita' illecita.\n"
                . "- Tentare di compromettere la sicurezza del sito, accedere ad aree riservate o sfruttare "
                . "bug del software.\n"
                . "- Utilizzare bot, script automatici o strumenti che alterino il normale funzionamento del gioco.\n\n"
                . "[b]5. Sospensione e cancellazione dell'account[/b]\n"
                . "Lo staff si riserva il diritto, a propria insindacabile discrezione, di:\n"
                . "- Sospendere temporaneamente l'account in caso di violazione delle presenti regole o del "
                . "regolamento di gioco.\n"
                . "- Cancellare definitivamente l'account in caso di violazioni gravi o reiterate.\n"
                . "L'utente puo' in ogni momento richiedere la cancellazione del proprio account dal menu "
                . "utente o tramite richiesta scritta al webmaster.\n\n"
                . "[b]6. Limitazione di responsabilita'[/b]\n"
                . "Il sito e' fornito \"cosi' com'e'\", senza alcuna garanzia di disponibilita' continua o di "
                . "conservazione dei dati di gioco. I gestori non rispondono di interruzioni del servizio "
                . "ne' di eventuali perdite di dati di gioco.\n\n"
                . "[b]7. Modifiche ai termini[/b]\n"
                . "I presenti termini possono essere aggiornati in qualunque momento. L'utente e' tenuto a "
                . "consultarli periodicamente. La data dell'ultimo aggiornamento e' indicata in calce alla pagina.\n\n"
                . "[b]8. Legge applicabile e foro competente[/b]\n"
                . "I presenti termini sono regolati dalla legge italiana. Per ogni controversia derivante "
                . "dall'utilizzo del sito e' competente in via esclusiva il foro del luogo di residenza del "
                . "gestore del sito, salvo diversa disposizione di legge inderogabile.\n\n"
                . "[i]Il presente testo costituisce un modello di base e deve essere personalizzato dal "
                . "gestore del sito.[/i]",
            ],
        ];

        foreach ($rows as $r) {
            $slug  = gdrcd_filter('in', $r[0]);
            $title = gdrcd_filter('in', $r[1]);
            $body  = gdrcd_filter('in', $r[2]);
            gdrcd_query(
                "INSERT IGNORE INTO legal_pages (slug, title, body) VALUES ("
                . "'" . $slug . "', '" . $title . "', '" . $body . "')"
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        gdrcd_query("DROP TABLE IF EXISTS legal_pages");
    }
}
