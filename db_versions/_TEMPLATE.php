<?php
/**
 * =============================================================================
 *  TEMPLATE — NON E' UNA MIGRAZIONE REALE
 * =============================================================================
 *
 * Questo file e' uno scheletro da copiare quando si crea una nuova migrazione.
 *
 * E' preceduto da "_" perche':
 *  - resti facilmente ignorabile a colpo d'occhio nella lista
 *  - NON venga caricato come migrazione dall'engine (il nome non comincia
 *    con un id numerico nel formato YYYYMMDDHH).
 *
 * Per creare una nuova migrazione:
 *
 *  1. Copia questo file in db_versions/YYYYMMDDHH_NomeClasse.php
 *     - YYYYMMDDHH = anno/mese/giorno/ora UTC corrente (es: 2026051114)
 *     - NomeClasse = PascalCase, deve coincidere col nome della classe
 *
 *  2. Rinomina la classe "MigrationTemplate" in NomeClasse (deve essere
 *     identico al nome del file dopo il prefisso numerico).
 *
 *  3. Implementa up() con le modifiche allo schema/dati.
 *
 *  4. Implementa down() con le modifiche inverse (opzionale ma fortemente
 *     consigliato per poter eseguire un rollback).
 *
 *  5. NON modificare $migration_id: viene dedotto automaticamente dal
 *     prefisso del nome del file.
 *
 *  6. (Solo per nuove installazioni) aggiungi una riga corrispondente in
 *     gdrcd_db.sql, nell'INSERT della tabella `_gdrcd_db_versions`.
 *
 * Vedi db_versions/README.md per un esempio completo.
 * =============================================================================
 */

// Guardia: se questo file venisse incluso per errore, NON definisce una classe
// utile e l'engine lo ignora (il prefisso "_TEMPLATE" non e' un id numerico).
if (false) {

class MigrationTemplate extends DbMigration
{
    /**
     * Applica la migrazione.
     *
     * Esempi di operazioni tipiche:
     *   gdrcd_query("CREATE TABLE IF NOT EXISTS ...");
     *   gdrcd_query("ALTER TABLE ... ADD COLUMN ...");
     *   gdrcd_query("UPDATE ... SET ... WHERE ...");
     *
     * Attenzione: le istruzioni DDL (CREATE/ALTER TABLE) provocano un
     * commit implicito in MySQL/MariaDB, quindi il rollback automatico
     * dell'engine ha effetti limitati su quel tipo di operazioni.
     */
    public function up()
    {
        gdrcd_query("-- TODO: implementare la migrazione");
    }

    /**
     * Annulla la migrazione (rollback).
     * Lasciare vuoto o lanciare una eccezione se l'operazione non e'
     * reversibile (es: DROP TABLE di una tabella con dati).
     */
    public function down()
    {
        gdrcd_query("-- TODO: implementare il rollback");
    }
}

}
