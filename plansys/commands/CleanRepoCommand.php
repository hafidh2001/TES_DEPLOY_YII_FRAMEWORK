<?php

/**
 * CleanRepoCommand — bersihkan file repo yang berstatus temp (upload tapi
 * belum pernah direferensikan baris apa pun) yang sudah lewat umurnya.
 *
 * Pemakaian:
 *   php yiic.php cleanRepo --interactive=0
 *   php yiic.php cleanRepo --ageHours=24
 *
 * Jalankan via cron/jadwal (default hapus temp umur > 24 jam).
 */
class CleanRepoCommand extends CConsoleCommand {

    public function actionIndex($ageHours = 24) {
        $n = Repo::cleanupTemporary((int) $ageHours * 3600);
        echo "CleanRepo: " . $n . " file temp (umur > " . (int) $ageHours . " jam) dibersihkan\n";
    }

}