<?php

chdir(__DIR__);

$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');
require_once('../incl/subscription.incl.php');

RunMeNTimes(2);
CatchKill();

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/watch/');
define('MAX_BONUSES', 6); // is a count, 1 through N
define('ARRAY_INDEX_BUYOUT', 1);
define('ARRAY_INDEX_QUANTITY', 0);
define('ARRAY_INDEX_AUCTIONS', 1);
define('ARRAY_INDEX_MARKETPRICE', 2);
define('ARRAY_INDEX_ALLBREEDS', -1);

ini_set('memory_limit', '384M');

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

if (APIMaintenance()) {
    DebugMessage('API Maintenance in progress, not parsing snapshots!', E_USER_NOTICE);
    exit;
}

$stmt = $db->prepare('SELECT house, region FROM tblRealm GROUP BY house');
$stmt->execute();
$result = $stmt->get_result();
$houseRegionCache = DBMapArray($result);
$stmt->close();

$stmt = $db->prepare('SELECT id FROM tblDBCItem WHERE `class` in (2,4) AND `auctionable` = 1');
$stmt->execute();
$result = $stmt->get_result();
$auctionExtraItemsCache = DBMapArray($result);
$stmt->close();

$stmt = $db->prepare('SELECT id FROM tblDBCItemBonus WHERE `flags` & 1');
$stmt->execute();
$result = $stmt->get_result();
$bonusSetMemberCache = array_keys(DBMapArray($result));
$stmt->close();

$maxPacketSize = 0;
$stmt = $db->prepare('show variables like \'max_allowed_packet\'');
$stmt->execute();
$stmt->bind_result($nonsense, $maxPacketSize);
$stmt->fetch();
$stmt->close();
unset($nonsense);

$loopStart = time();
$toSleep = 0;
while ((!$caughtKill) && (time() < ($loopStart + 60 * 30))) {
    heartbeat();
    sleep(min($toSleep, 10));
    if ($caughtKill || APIMaintenance()) {
        break;
    }
    ob_start();
    $toSleep = NextDataFile();
    ob_end_flush();
    if ($toSleep === false) {
        break;
    }
}
DebugMessage('Done! Started ' . TimeDiff($startTime));

function NextDataFile()
{
    $dir = scandir(substr(SNAPSHOT_PATH, 0, -1), SCANDIR_SORT_ASCENDING);
    $lockFail = false;
    $gotFile = false;
    foreach ($dir as $fileName) {
        if (preg_match('/^(\d+)-(\d+)\.json$/', $fileName, $res)) {
            if (($handle = fopen(SNAPSHOT_PATH . $fileName, 'rb')) === false) {
                continue;
            }

            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                $lockFail = true;
                fclose($handle);
                continue;
            }

            if (feof($handle)) {
                fclose($handle);
                unlink(SNAPSHOT_PATH . $fileName);
                continue;
            }

            $snapshot = intval($res[1], 10);
            $house = intval($res[2], 10);

            /*
            if (!MCHouseLock($house, 3)) {
                $lockFail = true;
                continue;
            }
            */

            $gotFile = $fileName;
            break;
        }
    }
    unset($dir);

    if (!$gotFile) {
        return $lockFail ? 3 : 10;
    }

    DebugMessage(
        "House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " data file from " . TimeDiff(
            $snapshot, array(
                'parts'     => 2,
                'precision' => 'second'
            )
        )
    );
    $json = json_decode(fread($handle, filesize(SNAPSHOT_PATH . $fileName)), true);

    ftruncate($handle, 0);
    fclose($handle);
    unlink(SNAPSHOT_PATH . $fileName);

    if (json_last_error() != JSON_ERROR_NONE) {
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " $snapshot data file corrupted! " . json_last_error_msg(), E_USER_WARNING);
        //MCHouseUnlock($house);
        return 0;
    }

    ParseAuctionData($house, $snapshot, $json);
    //MCHouseUnlock($house);
    return 0;
}

function ParseAuctionData($house, $snapshot, &$json)
{
    global $houseRegionCache;
    global $auctionExtraItemsCache;

    $snapshotString = Date('Y-m-d H:i:s', $snapshot);
    $startTimer = microtime(true);

    $region = $houseRegionCache[$house]['region'];

    $jsonAuctions = [];
    if (isset($json['auctions']['auctions'])) {
        $jsonAuctions =& $json['auctions']['auctions'];
    } elseif (isset($json['auctions']) && (count($json['auctions']) > 5)) {
        $jsonAuctions =& $json['auctions'];
    }

    $itemBuyouts = [];
    $itemBids = [];
    $petBuyouts = [];
    $petBids = [];
    $emptyItemInfo = [ARRAY_INDEX_QUANTITY => 0, ARRAY_INDEX_AUCTIONS => []];

    if ($jsonAuctions) {
        $auctionCount = count($jsonAuctions);
        DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " parsing $auctionCount auctions");

        for ($x = 0; $x < $auctionCount; $x++) {
            $auction =& $jsonAuctions[$x];

            if (isset($auction['petBreedId'])) {
                $auction['petBreedId'] = (($auction['petBreedId'] - 3) % 10) + 3; // squash gender
            }
            $hasBuyout = ($auction['buyout'] != 0);

            if (isset($auction['petSpeciesId'])) {
                if ($hasBuyout) {
                    $aucList = &$petBuyouts;
                } else {
                    $aucList = &$petBids;
                }
                if (!isset($aucList[$auction['petSpeciesId']][ARRAY_INDEX_ALLBREEDS])) {
                    $aucList[$auction['petSpeciesId']][ARRAY_INDEX_ALLBREEDS] = $emptyItemInfo;
                }
                if ($hasBuyout) {
                    AuctionListInsert($aucList[$auction['petSpeciesId']][ARRAY_INDEX_ALLBREEDS][ARRAY_INDEX_AUCTIONS], $auction['quantity'], $auction['buyout']);
                }
                $aucList[$auction['petSpeciesId']][ARRAY_INDEX_ALLBREEDS][ARRAY_INDEX_QUANTITY] += $auction['quantity'];

                if (!isset($aucList[$auction['petSpeciesId']][$auction['petBreedId']])) {
                    $aucList[$auction['petSpeciesId']][$auction['petBreedId']] = $emptyItemInfo;
                }
                if ($hasBuyout) {
                    AuctionListInsert($aucList[$auction['petSpeciesId']][$auction['petBreedId']][ARRAY_INDEX_AUCTIONS], $auction['quantity'], $auction['buyout']);
                }
                $aucList[$auction['petSpeciesId']][$auction['petBreedId']][ARRAY_INDEX_QUANTITY] += $auction['quantity'];
            } else {
                if ($hasBuyout) {
                    $aucList = &$itemBuyouts;
                } else {
                    $aucList = &$itemBids;
                }
                $bonusSet = 0;
                if (isset($auctionExtraItemsCache[$auction['item']]) && isset($auction['bonusLists'])) {
                    $bonusSet = GetBonusSet($auction['bonusLists']);
                }
                $itemInfoKey = $auction['item'] . ":$bonusSet";
                if (!isset($aucList[$itemInfoKey])) {
                    $aucList[$itemInfoKey] = $emptyItemInfo;
                }

                if ($hasBuyout) {
                    AuctionListInsert($aucList[$itemInfoKey][ARRAY_INDEX_AUCTIONS], $auction['quantity'], $auction['buyout']);
                }
                $aucList[$itemInfoKey][ARRAY_INDEX_QUANTITY] += $auction['quantity'];
            }
        }
    }
    unset($json, $jsonAuctions);

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " found ".count($itemBuyouts)." distinct items, ".count($petBuyouts)." species");

    $watchesSet = []; $watchesSetCount = 0;
    $watchesUnset = []; $watchesUnsetCount = 0;
    $updateObserved = [];
    $watchesCount = 0;
    $sql = <<<'EOF'
select uw.`user`, uw.seq, uw.item, uw.bonusset, uw.species, uw.breed, uw.direction, uw.quantity, uw.price,
    if(uw.observed is null, 0, 1) isset, if(uw.reported > uw.observed, 1, 0) wasreported
from tblUserWatch uw
join tblUser u on uw.user = u.id
where uw.deleted is null
and ((uw.region = ? and uw.observed is null) or uw.house = ?)
and (uw.observed is null or uw.observed < ?)
and (u.paiduntil > now() or u.lastseen > timestampadd(day, ?, now()))
order by uw.item, uw.species
EOF;

    $ourDb = DBConnect(true);
    $ourDb->query('set transaction isolation level read uncommitted, read only');
    $ourDb->begin_transaction();

    $stmt = $ourDb->prepare($sql);
    $freeDays = -1 * SUBSCRIPTION_WATCH_FREE_LAST_LOGIN_DAYS;
    $stmt->bind_param('sisi', $region, $house, $snapshotString, $freeDays);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $watchSatisfied = false;
        $watchesCount++;
        if ($row['item']) {
            $itemInfoKey = $row['item'] . ':' . ($row['bonusset'] ?: 0);
            if (!isset($itemBuyouts[$itemInfoKey])) {
                // none of this item for sale
                if (is_null($row['quantity'])) {
                    // market price notification, without quantity it does not change
                    continue;
                }
                if (is_null($row['price']) && $row['direction'] == 'Over' && $row['quantity'] == 0 && isset($itemBids[$itemInfoKey])) {
                    // when no quantity avail for buyout, and we're looking for any quantity over X, check bids too
                    $watchSatisfied = $itemBids[$itemInfoKey][ARRAY_INDEX_QUANTITY];
                } else {
                    $watchSatisfied = WatchSatisfied($emptyItemInfo, $row['direction'], $row['quantity'], $row['price']);
                }
            } else {
                $watchSatisfied = WatchSatisfied($itemBuyouts[$itemInfoKey], $row['direction'], $row['quantity'], $row['price']);
            }
        } else if ($row['species']) {
            $breed = $row['breed'] ?: ARRAY_INDEX_ALLBREEDS;
            if (!isset($petBuyouts[$row['species']][$breed])) {
                // none of this pet for sale
                if (is_null($row['quantity'])) {
                    // market price notification, without quantity it does not change
                    continue;
                }
                if (is_null($row['price']) && $row['direction'] == 'Over' && $row['quantity'] == 0 && isset($petBids[$row['species']][$breed])) {
                    // when no quantity avail for buyout, and we're looking for any quantity over X, check bids too
                    $watchSatisfied = $petBids[$row['species']][$breed][ARRAY_INDEX_QUANTITY];
                } else {
                    $watchSatisfied = WatchSatisfied($emptyItemInfo, $row['direction'], $row['quantity'], $row['price']);
                }
            } else {
                $watchSatisfied = WatchSatisfied($petBuyouts[$row['species']][$breed], $row['direction'], $row['quantity'], $row['price']);
            }
        }
        if ($watchSatisfied === false) {
            if ($row['isset']) {
                $watchesUnset[$row['user']][] = $row['seq'];
                $watchesUnsetCount++;
            }
        } elseif (!$row['wasreported']) {
            $watchesSet[$row['user']][$row['seq']] = $watchSatisfied;
            $watchesSetCount++;
            if (!$row['isset']) {
                $updateObserved[$row['user']] = true;
            }
        }
    }
    $result->close();
    $stmt->close();

    $ourDb->commit(); // end read-uncommitted transaction
    unset($itemBuyouts, $itemBids, $petBuyouts, $petBids);

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " reviewed $watchesCount watches, $watchesSetCount set, $watchesUnsetCount unset");

    $queryCount = 0;
    $sql = 'update tblUserWatch set house = if(region is null, house, null), currently = null, observed = null where user = %d and seq in (%s)';
    foreach ($watchesUnset as $user => $allSeqs) {
        $chunks = array_chunk($allSeqs, 200);
        foreach ($chunks as $seqs) {
            DBQueryWithError($ourDb, sprintf($sql, $user, implode(',', $seqs)));
            $queryCount++;
        }
    }

    $sql = 'update tblUserWatch set house = ?, currently = ?, observed = ifnull(observed, ?) where user = ? and seq = ?';
    if (count($watchesSet)) {
        $stmt = $ourDb->prepare($sql);
        $boundCurrently = $boundUser = $boundSeq = null;
        $stmt->bind_param('issii', $house, $boundCurrently, $snapshotString, $boundUser, $boundSeq);
        foreach ($watchesSet as $user => $allSeqs) {
            foreach ($allSeqs as $seq => $currently) {
                $boundCurrently = $currently;
                $boundUser = $user;
                $boundSeq = $seq;
                $stmt->execute();
                $queryCount++;
            }
        }
        $stmt->close();
    }

    $observedUsers = array_chunk(array_keys($updateObserved), 200);
    $sql = 'update tblUser set watchesobserved = \'%1$s\' where id in (%2$s) and ifnull(watchesobserved, \'2000-01-01\') < \'%1$s\'';
    foreach ($observedUsers as $users) {
        DBQueryWithError($ourDb, sprintf($sql, $snapshotString, implode(',', $users)));
        $queryCount++;
    }

    $ourDb->close();

    DebugMessage("House " . str_pad($house, 5, ' ', STR_PAD_LEFT) . " finished with $queryCount update queries in " . round(microtime(true) - $startTimer, 2) . " sec");
}

function WatchSatisfied(&$itemAuctions, $direction, $quantity, $price) {
    if (is_null($price)) {
        // qty notification
        if (   (($direction == 'Over') && ($itemAuctions[ARRAY_INDEX_QUANTITY] > $quantity))
            || (($direction != 'Over') && ($itemAuctions[ARRAY_INDEX_QUANTITY] < $quantity))) {
            return $itemAuctions[ARRAY_INDEX_QUANTITY];
        }
    } else {
        $curPrice = GetMarketPrice($itemAuctions, $quantity);
        if (is_null($curPrice)) {
            // fewer than $quantity are available
            return false;
        }
        if (   (($direction == 'Over') && ($curPrice > $price))
            || (($direction != 'Over') && ($curPrice < $price))) {
            return $curPrice;
        }
    }
    return false;
}

function AuctionListInsert(&$list, $qty, $buyout) {
    $pricePer = $buyout;
    if ($qty != 1) {
        $pricePer =0| $buyout/$qty;
        $auction = [ARRAY_INDEX_QUANTITY => $qty, ARRAY_INDEX_BUYOUT => $buyout];
    } else {
        $auction = $pricePer;
    }

    $iMax = count($list) - 1;
    if ($iMax == -1) {
        $list[] = $auction;
        return;
    }
    $iMin = 0;
    $iMid = 0;

    while ($iMin <= $iMax) {
        $iMid = (0|(($iMax - $iMin) / 2)) + $iMin;
        $midPrice = $list[$iMid];
        $midQty = 1;
        if (is_array($midPrice)) {
            $midQty = $midPrice[ARRAY_INDEX_QUANTITY];
            $midPrice = 0| $midPrice[ARRAY_INDEX_BUYOUT]/$midPrice[ARRAY_INDEX_QUANTITY];
        }
        if ($midPrice == $pricePer) {
            if ($midQty == $qty) {
                break;
            }
            if ($midQty < $qty) {
                $iMin = ++$iMid;
            } else {
                $iMax = $iMid - 1;
            }
        }
        if ($midPrice < $pricePer) {
            $iMin = ++$iMid;
        } else {
            $iMax = $iMid - 1;
        }
    }

    array_splice($list, $iMid, 0, [$auction]);
}

function GetBonusSet($bonusList)
{
    global $bonusSetMemberCache, $db;
    static $bonusSetCache = [];

    $bonuses = [];
    for ($y = 0; $y < count($bonusList); $y++) {
        if (isset($bonusList[$y]['bonusListId'])) {
            $bonuses[] = intval($bonusList[$y]['bonusListId'],10);
        }
    }
    $bonuses = array_intersect(array_unique($bonuses, SORT_NUMERIC), $bonusSetMemberCache);
    sort($bonuses, SORT_NUMERIC);

    if (count($bonuses) == 0) {
        return 0;
    }

    // check local static cache
    $bonusesKey = implode(':', $bonuses);
    if (isset($bonusSetCache[$bonusesKey])) {
        return $bonusSetCache[$bonusesKey];
    }

    // not in cache, check db
    $stmt = $db->prepare('SELECT `set`, GROUP_CONCAT(`bonus` ORDER BY 1 SEPARATOR \':\') `bonus` from tblBonusSet GROUP BY `set`');
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bonusSetCache[$row['bonus']] = $row['set'];
    }
    $result->close();
    $stmt->close();

    // check updated local cache now that we're synced with db
    if (isset($bonusSetCache[$bonusesKey])) {
        return $bonusSetCache[$bonusesKey];
    }

    // still don't have it, make a new one
    if (!DBQueryWithError($db, 'lock tables tblBonusSet write')) {
        return 0;
    };
    $newSet = 0;

    $stmt = $db->prepare('select ifnull(max(`set`),0)+1 from tblBonusSet');
    $stmt->execute();
    $stmt->bind_result($newSet);
    $stmt->fetch();
    $stmt->close();

    if ($newSet) {
        $sql = 'insert into tblBonusSet (`set`, `bonus`) VALUES ';
        for ($x = 0; $x < count($bonuses); $x++) {
            $sql .= ($x > 0 ? ',' : '') . "($newSet,{$bonuses[$x]})";
        }
        if (!DBQueryWithError($db, $sql)) {
            $newSet = 0;
        }
    }
    DBQueryWithError($db, 'unlock tables');

    if ($newSet) {
        $bonusSetCache[$bonusesKey] = $newSet;
    }

    return $newSet;
}

function GetMarketPrice(&$info, $inBuyCount = 0)
{
    if (!$inBuyCount) {
        if (isset($info[ARRAY_INDEX_MARKETPRICE])) {
            return $info[ARRAY_INDEX_MARKETPRICE];
        }
        if ($info[ARRAY_INDEX_QUANTITY] == 0) {
            return null;
        }
    } elseif ($info[ARRAY_INDEX_QUANTITY] < $inBuyCount) {
        return null;
    }

    $gq = 0;
    $gp = 0;
    $x = 0;
    $buyCount = $inBuyCount ?: ceil($info[ARRAY_INDEX_QUANTITY] * 0.15);
    while ($gq < $buyCount) {
        $auc = $info[ARRAY_INDEX_AUCTIONS][$x++];
        if (is_array($auc)) {
            $gq += $auc[ARRAY_INDEX_QUANTITY];
            $gp += $auc[ARRAY_INDEX_BUYOUT];
        } else {
            $gq++;
            $gp += $auc;
        }
    }
    if (!$inBuyCount) {
        return $info[ARRAY_INDEX_MARKETPRICE] = ceil($gp / $gq);
    }
    return $gp; // returns full price to buy $gq
}

function DBQueryWithError(&$db, $sql)
{
    $queryOk = $db->query($sql);
    if (!$queryOk) {
        DebugMessage("SQL error: " . $db->errno . ' ' . $db->error . " - " . substr(preg_replace('/[\r\n]/', ' ', $sql), 0, 500), E_USER_WARNING);
    }

    return $queryOk;
}