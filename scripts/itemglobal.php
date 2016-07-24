<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');

RunMeNTimes(1);
CatchKill();

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

if (APIMaintenance()) {
    DebugMessage('API Maintenance in progress, not updating global items!', E_USER_NOTICE);
    exit;
}

$db->query('set session transaction isolation level read uncommitted');

if (isset($argv[1]) && ($argv[1] == 'jsononly')) {
    UpdateGlobalDataJson();
    DebugMessage('Done! Started ' . TimeDiff($startTime));
    exit;
}

DebugMessage('Starting..');

$stmt = $db->prepare('SELECT DISTINCT item, bonusset FROM tblItemSummary');
$stmt->execute();
$result = $stmt->get_result();
$items = DBMapArray($result, null);
$stmt->close();

heartbeat();
if ($caughtKill) {
    exit;
}

$regions = ['US','EU'];

$sql = <<<END
SELECT price
FROM tblItemSummary ih, tblRealm r
WHERE item = ?
and bonusset = ?
and r.region = ?
and r.canonical is not null
and ih.house = r.house
ORDER BY 1
END;

DebugMessage('Updating items..');
$itemsCount = count($items);
$now = date('Y-m-d H:i:s');
for ($z = 0; $z < $itemsCount; $z++) {
    $item = $items[$z]['item'];
    $bonusSet = $items[$z]['bonusset'];

    if ($caughtKill) {
        break;
    }

    if (heartbeat()) {
        DebugMessage("Processing item $z/$itemsCount (" . round($z / $itemsCount * 100) . '%)');
    }

    foreach ($regions as $region) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('iis', $item, $bonusSet, $region);
        $stmt->execute();
        $prices = [];
        $price = null;
        $stmt->bind_result($price);
        while ($stmt->fetch()) {
            $prices[] = $price;
        }
        $stmt->close();

        $cnt = count($prices);

        if ($cnt == 0) {
            continue;
        }

        $mean = 0;
        for ($x = 0; $x < $cnt; $x++) {
            $mean += $prices[$x] / $cnt;
        }
        $mean = round($mean);

        $stdDev = 0;
        for ($x = 0; $x < $cnt; $x++) {
            $stdDev += pow($prices[$x] - $mean, 2) / $cnt;
        }
        $stdDev = round(sqrt($stdDev));

        if ($cnt % 2 == 1) {
            $median = $prices[(int)floor($cnt / 2)];
        } else {
            $median = round(($prices[(int)floor($cnt / 2) - 1] + $prices[(int)floor($cnt / 2)]) / 2);
        }

        $stmt = $db->prepare('INSERT INTO tblItemGlobalWorking (`region`, `when`, `item`, `bonusset`, `median`, `mean`, `stddev`) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssiiiii', $region, $now, $item, $bonusSet, $median, $mean, $stdDev);
        $stmt->execute();
        $stmt->close();
    }
}

if (!$caughtKill) {
    heartbeat();
    DebugMessage("Deleting old working rows");
    $db->query('DELETE FROM tblItemGlobalWorking WHERE `when` < timestampadd(DAY, -1, now())');
}

if (!$caughtKill) {
    heartbeat();
    DebugMessage("Updating tblItemGlobal rows");
    $db->query('REPLACE INTO tblItemGlobal (SELECT `item`, `bonusset`, `region`, avg(`median`), avg(`mean`), avg(`stddev`) FROM tblItemGlobalWorking GROUP BY `item`, `bonusset`, `region`)');
}

UpdateGlobalDataJson();

DebugMessage('Done! Started ' . TimeDiff($startTime));

function UpdateGlobalDataJson()
{
    global $caughtKill, $db;
    if ($caughtKill) {
        return;
    }

    heartbeat();
    DebugMessage("Updating global data json");

    $stmt = $db->prepare('SELECT item, avg(median) FROM tblItemGlobal where bonusset=0');
    $stmt->execute();
    $result = $stmt->get_result();
    $prices = DBMapArray($result, null);
    $stmt->close();

    $json = [];
    foreach ($prices as $priceRow) {
        $json[$priceRow['item']] = $priceRow['median'];
    }
    file_put_contents(__DIR__ . '/../public/globalprices.json', json_encode($json, JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT), LOCK_EX);
}