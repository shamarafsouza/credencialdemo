<?php
declare(strict_types=1);

/**
 * Regras de validade por CID para PCD (exemplo).
 * Ajuste conforme as regras reais do município.
 */
function validadePorCID(string $cid): int {
    $cid = strtoupper(trim($cid));
    // Exemplos que dão 5 anos; demais 2 anos
    $cincoAnos = ['H54.0','H54','H54.4','Q05','G80','M62'];
    foreach ($cincoAnos as $c) {
        if (strpos($cid, $c) === 0) return 5;
    }
    return 2;
}
