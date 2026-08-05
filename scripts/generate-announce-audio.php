<?php

/**
 * One-off generator: macOS `say` + `afconvert` → public/audio/announce/number-{1..99}.wav
 * Run: php scripts/generate-announce-audio.php
 *
 * Uses Karen (clear announcement tone). Avoid Samantha — same engine as browser TTS and sounds hollow.
 */

$ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
$tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

function announceWords(int $n, array $ones, array $tens): string
{
    if ($n < 20) {
        return $ones[$n];
    }

    if ($n < 100) {
        $t = intdiv($n, 10);
        $o = $n % 10;

        return $o ? $tens[$t].' '.$ones[$o] : $tens[$t];
    }

    return (string) $n;
}

$outDir = dirname(__DIR__).'/public/audio/announce';
if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

$voice = 'Karen';
$rate = 205;

for ($i = 1; $i <= 99; $i++) {
    // Short PA-style phrase — crisp, not whispery.
    $phrase = 'Number '.announceWords($i, $ones, $tens);
    $aiff = "/tmp/announce-{$i}.aiff";
    $wav = "{$outDir}/number-{$i}.wav";

    $say = 'say -v '.escapeshellarg($voice).' -r '.$rate.' -o '.escapeshellarg($aiff).' '.escapeshellarg($phrase);
    passthru($say, $code);
    if ($code !== 0) {
        fwrite(STDERR, "say failed for {$i}\n");
        exit(1);
    }

    $convert = 'afconvert -f WAVE -d LEI16 '.escapeshellarg($aiff).' '.escapeshellarg($wav);
    passthru($convert, $code2);
    @unlink($aiff);

    if ($code2 !== 0 || ! is_file($wav)) {
        fwrite(STDERR, "afconvert failed for {$i}\n");
        exit(1);
    }

    if ($i % 20 === 0) {
        echo "Generated through {$i}\n";
    }
}

echo "Done: 99 clips ({$voice} @ {$rate}) in {$outDir}\n";
