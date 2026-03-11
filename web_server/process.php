<?php

header('Content-Type: application/json');

require_once 'config.php';
require_once 'connect_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $date = $_POST['date'] ?? '';
    $raw_targets = $_POST['targets'] ?? [];

    if (empty($date) || empty($raw_targets))
    {
        echo json_encode(["error" => "Something went wrong!"]);
        exit;
    }


    $requestedTargets = [];
    foreach ($raw_targets as $target) {
        if (!isset($target['enabled'])) continue;
        $name = $target['name'] ?? '';
        $loc = $target['type'] ?? '';
        if (empty($name) || empty($loc)) continue;
        $requestedTargets[] = [
            'name' => $name,
            'location' => $loc,
            'is_interstellar' => ($loc === 'solar') ? 0 : 1,
        ];
    }

    if (empty($requestedTargets)) {
        echo json_encode(["error" => "No targets selected!"]);
        exit;
    }

    $cachedResults = [];
    $toCompute = [];

    $vpdo = $pdo;

    // Update the table name to match the main database structure
    $selectSql = 'SELECT start_time, end_time FROM visibility WHERE date = :date AND target_name = :name AND is_interstellar = :is_interstellar ORDER BY start_time ASC';
    $selectStmt = $vpdo->prepare($selectSql);

    // Update the insert statement to use the main database
    $insertSql = 'INSERT INTO visibility(date, target_name, is_interstellar, start_time, end_time) VALUES (:date, :name, :is_interstellar, :start_time, :end_time)';
    $insertStmt = $vpdo->prepare($insertSql);

    if ($vpdo) {
        foreach ($requestedTargets as $t) {
            $selectStmt->bindValue(':date', $date);
            $selectStmt->bindValue(':name', $t['name']);
            $selectStmt->bindValue(':is_interstellar', $t['is_interstellar'], PDO::PARAM_INT);
            $selectStmt->execute();
            $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                // Build windows array
                $windows = [];
                foreach ($rows as $r) {
                    $windows[] = ['start' => $r['start_time'], 'end' => $r['end_time']];
                }
                $cachedResults[$t['name'] . ':' . $t['location']] = [
                    'name' => $t['name'],
                    'location' => $t['location'],
                    'windows' => $windows
                ];
            } else {
                $toCompute[] = $t;
            }
        }
    } else {
        // No visibility DB available, compute everything
        $toCompute = $requestedTargets;
    }

    $computedResults = [];
    if (!empty($toCompute)) {
        // Build command-line flags for gen_obs_json.py: -s name for solar, -i name for interstellar
        $targetArgs = '';
        foreach ($toCompute as $ct) {
            $nameEsc = escapeshellarg($ct['name']);
            if ($ct['is_interstellar'] === 0) {
                $targetArgs .= ' -s ' . $nameEsc;
            } else {
                $targetArgs .= ' -i ' . $nameEsc;
            }
        }

        $safe_date = escapeshellarg($date);
        $command = "$gen_obs_json_py $safe_date $targetArgs 2>&1";
        exec($command, $output_array, $return_code);

        if (count($output_array) != 1 || $return_code != 0)
        {
            file_put_contents($process_log, print_r(implode("\r\n", $output_array)."\r\n", true));
        }

        $json_string = '';
        foreach ($output_array as $line)
        {
            if (strpos($line, '{"date":') === 0)
                {
                $json_string = $line;
                break;
            }
        }

        $result = [];
        if (!empty($json_string)) {
            $result = json_decode($json_string, true);
        }

        if (!empty($result) && isset($result['targets']) && is_array($result['targets'])) {
            // Store computed windows into visibility DB for future reuse
            if ($vpdo) {
                $insertSql = 'INSERT INTO visibility(date, target_name, is_interstellar, start_time, end_time) VALUES (:date, :name, :is_interstellar, :start_time, :end_time)';
                $insertStmt = $vpdo->prepare($insertSql);
            }

            foreach ($result['targets'] as $t) {
                // normalize windows
                if (isset($t['windows']) && is_array($t['windows'])) {
                    // save each window row
                    if ($vpdo) {
                        foreach ($t['windows'] as $w) {
                            if (!isset($w['start']) || !isset($w['end'])) continue;
                            $insertStmt->bindValue(':date', $date);
                            $insertStmt->bindValue(':name', $t['name']);
                            $insertStmt->bindValue(':is_interstellar', ($t['location'] === 'solar') ? 0 : 1, PDO::PARAM_INT);
                            $insertStmt->bindValue(':start_time', $w['start']);
                            $insertStmt->bindValue(':end_time', $w['end']);
                                try {
                                    $insertStmt->execute();
                                } catch (Throwable $e) {
                                    // Log insert error for debugging
                                    file_put_contents($process_log, "Visibility insert failed for {$t['name']} ({$t['location']}): " . $e->getMessage() . "\n", FILE_APPEND);
                                }
                        }
                    }
                }

                $computedResults[$t['name'] . ':' . $t['location']] = $t;
            }
        }
    }

    // Merge cachedResults and computedResults to preserve original order
    $finalOutputTargets = [];
    foreach ($requestedTargets as $t) {
        $key = $t['name'] . ':' . $t['location'];
        if (isset($cachedResults[$key])) {
            $finalOutputTargets[] = $cachedResults[$key];
        } elseif (isset($computedResults[$key])) {
            $finalOutputTargets[] = $computedResults[$key];
        } else {
            // No info available (e.g., target not found or no visibility)
            $finalOutputTargets[] = [
                'name' => $t['name'],
                'location' => $t['location'],
                'windows' => 'No visibility'
            ];
        }
    }

    // Return JSON similar to the python script
    echo json_encode(['date' => $date, 'targets' => $finalOutputTargets]);
}
else
{
    echo json_encode(["error" => "Invalid request method"]);
}

?>