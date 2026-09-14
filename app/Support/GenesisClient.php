<?php

namespace App\Support;

use RuntimeException;

/**
 * Client für die RESTful/JSON-Schnittstelle der Regionaldatenbank
 * Deutschland (GENESIS, regionalstatistik.de). Zugangsdaten müssen laut
 * Doku als eigene HTTP-Header übertragen werden, nicht als Body-Parameter
 * — undokumentiert offensichtlich, aber ohne das antwortet der Dienst mit
 * HTTP 200 und stillem Fallback auf den Gastzugang (kein Fehler!), bzw.
 * bei echten Datenabfragen mit "Code":15 ("Zugangsdaten nicht erkannt").
 */
final class GenesisClient
{
    private const BASE_URL = 'https://www.regionalstatistik.de/genesisws/rest/2020/';

    public function __construct(private readonly string $apiToken)
    {
    }

    /** @param array<string, string|int> $params */
    public function call(string $method, array $params = []): array
    {
        $ch = curl_init(self::BASE_URL . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params + ['language' => 'de']),
            CURLOPT_HTTPHEADER => ['username: ' . $this->apiToken],
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new RuntimeException("GENESIS-Aufruf '$method' fehlgeschlagen (HTTP $httpCode). $err");
        }

        $data = json_decode($response, true);
        if (!is_array($data) || ($data['Status']['Code'] ?? 0) !== 0) {
            $message = $data['Status']['Content'] ?? $data['Content'] ?? $response;
            throw new RuntimeException("GENESIS-Aufruf '$method' meldet Fehler: $message");
        }

        return $data;
    }

    /**
     * Lädt eine data/table-Tabelle für alle Kreise und gibt sie als
     * Zeilen-Array zurück — jede Zeile ist ein 0-indexiertes Array der
     * Rohspalten im "datencsv"-Format (Jahr, AGS, Kreisname, ... weitere
     * Merkmale, dann die Wertspalten). Kopf- und Fußzeilen (Metadaten,
     * Quellenangaben) sind bereits herausgefiltert.
     *
     * @return array<int, array<int, string>>
     */
    public function fetchTableAllKreise(string $tableName, int $startYear): array
    {
        $data = $this->call('data/table', [
            'name' => $tableName,
            'area' => 'all',
            'regionalvariable' => 'KREISE',
            'regionalkey' => '*',
            'startyear' => $startYear,
        ]);

        $csv = $data['Object']['Content'] ?? '';
        $lines = explode("\n", $csv);

        $rows = [];
        foreach ($lines as $line) {
            if ($line === '__________' || $line === '') {
                break; // Ende der Datenzeilen, danach folgen nur noch Fußnoten
            }
            $cols = str_getcsv($line, ';');
            // Datenzeilen beginnen mit einer 4-stelligen Jahreszahl, alle
            // Kopfzeilen (Titel, Merkmalsbeschriftungen) nicht.
            if (!preg_match('/^\d{4}$/', $cols[0] ?? '')) {
                continue;
            }
            $rows[] = array_map(static fn (?string $v): string => trim((string) $v), $cols);
        }

        return $rows;
    }
}
