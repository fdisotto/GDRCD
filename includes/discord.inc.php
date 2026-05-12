<?php
declare(strict_types=1);

/**
 * Discord bridge helper.
 *
 * Espone le funzioni di integrazione (outgoing webhook + lettura config)
 * usate da:
 *  - ref_header.inc.php          (relay dei messaggi chat outgoing)
 *  - api/discord-inbound.inc.php (endpoint inbound da bot Discord)
 *  - pages/gestione/discord.inc.php (admin UI)
 *
 * Storage della configurazione:
 *  I default vivono in config.inc.php ($PARAMETERS['integrations']['discord']),
 *  ma i valori effettivi sono sovrascrivibili in DB tramite la tabella
 *  config_settings (chiavi `discord_*`) cosi' che il webmaster non debba
 *  toccare il file PHP per attivare il bridge.
 *
 *  Nessun valore viene mai stampato in chiaro nell'admin UI: il webhook
 *  URL e' mascherato e il token incoming viene rivelato solo al momento
 *  della rotazione (one-shot).
 *
 * Outgoing failure policy:
 *  Le chiamate al webhook NON devono mai bloccare il flusso di gioco.
 *  Timeout brevi (5s), errori loggati come warning, nessuna eccezione
 *  propagata.
 */

if (!defined('GDRCD_DISCORD_INC')) {
    define('GDRCD_DISCORD_INC', 1);

    /**
     * Restituisce la config effettiva del bridge Discord, fondendo i default
     * di config.inc.php con eventuali override salvati in config_settings.
     *
     * @return array{
     *   enabled: bool,
     *   webhook_url: string,
     *   incoming_token: string,
     *   bridge_room_id: int,
     *   bot_name: string,
     *   relay_types: array<int,string>
     * }
     */
    function gdrcd_discord_config(): array
    {
        global $PARAMETERS;

        $defaults = isset($PARAMETERS['integrations']['discord']) && is_array($PARAMETERS['integrations']['discord'])
            ? $PARAMETERS['integrations']['discord']
            : array();

        $default_enabled        = !empty($defaults['enabled']);
        $default_webhook        = isset($defaults['webhook_url']) ? (string)$defaults['webhook_url'] : '';
        $default_token          = isset($defaults['incoming_token']) ? (string)$defaults['incoming_token'] : '';
        $default_bridge_room_id = isset($defaults['bridge_room_id']) ? (int)$defaults['bridge_room_id'] : 0;
        $default_bot_name       = isset($defaults['bot_name']) && $defaults['bot_name'] !== ''
            ? (string)$defaults['bot_name']
            : 'Discord';
        $default_relay_types    = isset($defaults['relay_types']) && is_array($defaults['relay_types'])
            ? array_values(array_filter(array_map('strval', $defaults['relay_types'])))
            : array('P', 'A', 'M');

        // Override DB (graceful fallback ai default se la tabella non esiste).
        $enabled        = (bool)gdrcd_config_get('discord_enabled', $default_enabled);
        $webhook_url    = (string)gdrcd_config_get('discord_webhook_url', $default_webhook);
        $incoming_token = (string)gdrcd_config_get('discord_incoming_token', $default_token);
        $bridge_room_id = (int)gdrcd_config_get('discord_bridge_room_id', $default_bridge_room_id);
        $bot_name       = (string)gdrcd_config_get('discord_bot_name', $default_bot_name);
        $relay_types_raw = (string)gdrcd_config_get('discord_relay_types', implode(',', $default_relay_types));

        $relay_types = array();
        foreach (explode(',', $relay_types_raw) as $rt) {
            $rt = trim($rt);
            if ($rt !== '') {
                $relay_types[] = strtoupper($rt);
            }
        }
        if (empty($relay_types)) {
            $relay_types = $default_relay_types;
        }

        if ($bot_name === '') {
            $bot_name = 'Discord';
        }

        return array(
            'enabled'        => $enabled,
            'webhook_url'    => $webhook_url,
            'incoming_token' => $incoming_token,
            'bridge_room_id' => $bridge_room_id,
            'bot_name'       => $bot_name,
            'relay_types'    => $relay_types,
        );
    }

    /**
     * Maschera un webhook URL per la presentazione in admin UI.
     * Mantiene host + ultimi 4 caratteri del path, censura il resto.
     */
    function gdrcd_discord_mask_webhook(string $url): string
    {
        $url = (string)$url;
        if ($url === '') {
            return '';
        }
        $parts = @parse_url($url);
        if (!$parts || empty($parts['host'])) {
            // Fallback dumb-mask: tieni i primi/ultimi 6.
            $len = strlen($url);
            if ($len <= 12) {
                return str_repeat('*', $len);
            }
            return substr($url, 0, 6) . str_repeat('*', $len - 12) . substr($url, -6);
        }
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host   = $parts['host'];
        $path   = isset($parts['path']) ? $parts['path'] : '';
        $tail   = $path !== '' ? substr($path, -4) : '';
        return $scheme . $host . '/****' . $tail;
    }

    /**
     * Invia un messaggio chat verso Discord via webhook.
     *
     * Non blocca il flusso: timeout 5s, errori logati ma silenziati.
     *
     * @param string $tipo      Tipo del messaggio chat (P/A/M/...).
     * @param string $mittente  Nome del personaggio mittente.
     * @param string $testo     Testo del messaggio (raw).
     * @param string $stanza    Nome leggibile della stanza/luogo.
     * @return bool true se l'invio e' andato a buon fine (HTTP 204).
     */
    function gdrcd_discord_relay(string $tipo, string $mittente, string $testo, string $stanza): bool
    {
        $cfg = gdrcd_discord_config();
        if (empty($cfg['enabled']) || $cfg['webhook_url'] === '') {
            return false;
        }
        $tipo = strtoupper((string)$tipo);
        if (!in_array($tipo, $cfg['relay_types'], true)) {
            return false;
        }
        if (!function_exists('curl_init')) {
            if (function_exists('gdrcd_log_warning')) {
                gdrcd_log_warning('Discord relay skipped: curl extension missing');
            }
            return false;
        }

        $mittente = (string)$mittente;
        $testo    = (string)$testo;
        $stanza   = (string)$stanza;

        // Discord limits: username <= 80, content <= 2000.
        if ($mittente === '') {
            $mittente = 'GDRCD';
        }
        if (strlen($mittente) > 80) {
            $mittente = substr($mittente, 0, 80);
        }

        $prefix = '[' . ($stanza !== '' ? $stanza : '?') . '] ';
        $maxLen = 2000;
        if (strlen($prefix . $testo) > $maxLen) {
            $testo = substr($testo, 0, max(0, $maxLen - strlen($prefix) - 1)) . "\u{2026}";
        }

        $payload = array(
            'username' => $mittente,
            'content'  => $prefix . $testo,
            // Evita ping involontari da menzioni nel testo del gioco.
            'allowed_mentions' => array('parse' => array()),
        );

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            if (function_exists('gdrcd_log_warning')) {
                gdrcd_log_warning('Discord relay payload encode failed');
            }
            return false;
        }

        $ch = curl_init($cfg['webhook_url']);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_USERAGENT      => 'GDRCD-Discord-Bridge/1.0',
        ));
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code !== 204) {
            if (function_exists('gdrcd_log_warning')) {
                gdrcd_log_warning('Discord webhook failed', array(
                    'http_code' => $code,
                    'curl_err'  => $err,
                    'resp'      => is_string($resp) ? substr($resp, 0, 500) : '',
                ));
            }
            return false;
        }
        return true;
    }

    /**
     * Genera un nuovo token segreto per autenticare il bot Discord
     * sull'endpoint inbound. Hex 64 chars (32 byte random).
     *
     * @return string
     */
    function gdrcd_discord_generate_token(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            // Fallback non-CSPRNG: meglio che bloccare l'admin.
            return bin2hex(pack('Nn', mt_rand(), mt_rand()) . pack('Nn', mt_rand(), mt_rand())
                . pack('Nn', mt_rand(), mt_rand()) . pack('Nn', mt_rand(), mt_rand()));
        }
    }

    /**
     * Confronta in modo timing-safe due token.
     */
    function gdrcd_discord_token_equals(string $a, string $b): bool
    {
        $a = (string)$a;
        $b = (string)$b;
        if ($a === '' || $b === '') {
            return false;
        }
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        if (strlen($a) !== strlen($b)) {
            return false;
        }
        $diff = 0;
        for ($i = 0, $n = strlen($a); $i < $n; $i++) {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $diff === 0;
    }
}
