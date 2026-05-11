<?php

/*
 * Procedura di recupero Password
 */
$feedback = '';
$feedback_type = null;

if (!empty($_POST['email'])) {
    $result = gdrcd_query("SELECT nome, email FROM personaggio", 'result');

    while ($row = gdrcd_query($result, 'assoc')) {
        if (gdrcd_password_check($_POST['email'], $row['email'])) {
            $pass = gdrcd_genera_pass();
            $hasReset = gdrcd_query("UPDATE personaggio SET pass = '" . gdrcd_filter('in', gdrcd_password_hash($pass)) . "' WHERE nome = '" . gdrcd_filter('in', $row['nome']) . "' LIMIT 1");

            if ($hasReset) {
                $subject = gdrcd_filter('out', $MESSAGE['register']['forms']['mail']['sub'] . ' ' . $PARAMETERS['info']['site_name']);
                $text = gdrcd_filter('out', $MESSAGE['register']['forms']['mail']['text'] . ': ' . $pass);

                $hasEmailSent = mail($_POST['email'], $subject, $text, 'From: ' . $PARAMETERS['info']['webmaster_email']);
                if ($hasEmailSent) {
                    $feedback = gdrcd_filter('out', $MESSAGE['warning']['modified']);
                    $feedback_type = 'success';
                }
            } else {
                $feedback = gdrcd_filter('out', $MESSAGE['warning']['cant_do']);
                $feedback_type = 'error';
            }
        }
    }
}

?>
<form action="index.php" method="post" class="space-y-3">
    <div>
        <label class="gdrcd-label" for="passrecovery"><?= $MESSAGE['homepage']['forms']['email'] ?></label>
        <input class="gdrcd-input" type="email" id="passrecovery" name="email" required/>
    </div>
    <?php if ($feedback !== ''): ?>
        <div class="<?= $feedback_type === 'success' ? 'gdrcd-alert-success' : 'gdrcd-alert-error' ?> text-xs">
            <?= $feedback ?>
        </div>
    <?php endif; ?>
    <button type="submit" class="gdrcd-btn-secondary w-full">
        <?= $MESSAGE['homepage']['forms']['new_pass'] ?>
    </button>
</form>
