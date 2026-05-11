<?php /* Widget sidebar — frame presenti (iframe auto-refresh). */ ?>
<div class="gdrcd-widget-title flex items-center gap-2 -m-4 mb-3 px-4 py-2">
    <svg class="w-4 h-4 text-gdrcd-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87a4 4 0 100-8 4 4 0 000 8zm0-8a4 4 0 100-8 4 4 0 000 8z"/>
    </svg>
    <span>Presenti</span>
</div>

<iframe src="pages/presenti.inc.php?ref=60"
        title="Elenco personaggi presenti online"
        class="w-full border-0 bg-transparent block"
        style="height:32rem;"
        allowtransparency="true">
    <p><?= gdrcd_filter_out($MESSAGE['errors']['can_t_load_frame']) ?></p>
</iframe>
