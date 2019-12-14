<?php

chdir(__DIR__);
$startTime = time();

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/battlenet.incl.php');

define('SNAPSHOT_PATH', '/var/newsstand/snapshots/');
define('EARLY_CHECK_SECONDS', 120);
define('MINIMUM_INTERVAL_SECONDS', 1220);
define('DATA_FILE_CURLOPTS', [
    CURLOPT_LOW_SPEED_LIMIT => 50*1024,
    CURLOPT_LOW_SPEED_TIME => 5,
    CURLOPT_TIMEOUT => 20,
]);

$regions = ['US','EU','CN','TW','KR'];

if (!isset($argv[1]) || !in_array($argv[1], $regions)) {
    DebugMessage('Need region '.implode(', ', $regions), E_USER_ERROR);
}

if (!DBConnect()) {
    DebugMessage('Cannot connect to db!', E_USER_ERROR);
}

$region = $argv[1];
$runNTimes = 1;
if (isset($argv[2])) {
    $runNTimes = intval($argv[2], 10);
    if ($runNTimes <= 0) {
        $runNTimes = 1;
    }
}

RunMeNTimes($runNTimes);
CatchKill();

$loopStart = time();
$toSleep = 0;
while ((!CatchKill()) && (time() < ($loopStart + 60 * 30))) {
    heartbeat();
    sleep(min($toSleep, 30));
    if (CatchKill()) {
        break;
    }
    ob_start();
    $toSleep = FetchSnapshot();
    ob_end_flush();
    if ($toSleep === false) {
        break;
    }
}
DebugMessage('Done! Started ' . TimeDiff($startTime));

function FetchSnapshot()
{
    global $db, $region;

    $lockName = "fetchsnapshot_$region";

    $stmt = $db->prepare('select get_lock(?, 30)');
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $lockSuccess = null;
    $stmt->bind_result($lockSuccess);
    if (!$stmt->fetch()) {
        $lockSuccess = null;
    }
    $stmt->close();
    if ($lockSuccess != '1') {
        DebugMessage("Could not get mysql lock for $lockName.");
        return 30;
    }
    $earlyCheckSeconds = EARLY_CHECK_SECONDS;

    $nextRealmSql = <<<ENDSQL
    select r.house, min(r.canonical), count(*) c, ifnull(hc.nextcheck, s.nextcheck) upd, 
    s.lastupdate, if(s.lastupdate < timestampadd(hour, -36, now()), 0, ifnull(s_id.maxid, 0)) maxid, s.mindelta, 
    hc.lastchecksuccessresult
    from tblRealm r
    left join (
        select deltas.house, timestampadd(second, least(ifnull(min(delta)-$earlyCheckSeconds, 45*60), 150*60), max(deltas.updated)) nextcheck, max(deltas.updated) lastupdate, least(min(delta), 150*60) mindelta
        from (
            select sn.updated,
            if(@prevhouse = sn.house and sn.updated > timestampadd(hour, -72, now()), unix_timestamp(sn.updated) - @prevdate, null) delta,
            @prevdate := unix_timestamp(sn.updated) updated_ts,
            @prevhouse := sn.house house
            from (select @prevhouse := null, @prevdate := null) setup, tblSnapshot sn
            order by sn.house, sn.updated) deltas
        group by deltas.house
        ) s on s.house = r.house
    left join tblHouseCheck hc on hc.house = r.house
    left join tblSnapshot s_id on s_id.house = s.house and s_id.updated = s.lastupdate
    where r.region = ?
    and r.house is not null
    and r.canonical is not null
    group by r.house
    order by ifnull(upd, '2000-01-01') asc, c desc, r.house asc
    limit 1
ENDSQL;

    $house = $slug = $realmCount = $nextDate = $lastDate = $maxId = $minDelta = $lastSuccessJson = null;

    $stmt = $db->prepare($nextRealmSql);
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $stmt->bind_result($house, $slug, $realmCount, $nextDate, $lastDate, $maxId, $minDelta, $lastSuccessJson);
    $gotRealm = $stmt->fetch() === true;
    $stmt->close();

    if (!$gotRealm) {
        DebugMessage("No $region realms to fetch!");
        ReleaseDBLock($lockName);
        return 30;
    }

    if (strtotime($nextDate) > time() && (strtotime($nextDate) < (time() + 3.5 * 60 * 60))) {
        $delay = strtotime($nextDate) - time();
        DebugMessage("No $region realms ready yet, waiting ".SecondsOrMinutes($delay).".");
        ReleaseDBLock($lockName);
        return $delay;
    }

    SetHouseNextCheck($house, time() + 600, null);
    ReleaseDBLock($lockName);

    DebugMessage("$region $slug fetch for house $house to update $realmCount realms, due since " . (is_null($nextDate) ? 'unknown' : (SecondsOrMinutes(time() - strtotime($nextDate)).' ago')));

    $requestInfo = GetBattleNetURL($region, "wow/auction/data/$slug");

    $outHeaders = [];
    $dta = [];
    $json = $requestInfo ? \Newsstand\HTTP::Get($requestInfo[0], $requestInfo[1], $outHeaders) : false;
    if (($json === false) && isset($outHeaders['body'])) {
        // happens if server returns non-200 code, but we'll want that json anyway
        $json = $outHeaders['body'];
    }
    if ($json !== false) {
        $dta = json_decode($json, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            $dta = [];
        }
    }
    if (!isset($dta['files']) && !is_null($lastSuccessJson)) {
        // no files in current status json, probably "internal server error"
        // check the headers on our last known good data url
        $lastGoodDta = json_decode($lastSuccessJson, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            DebugMessage("$region $slug invalid JSON for last successful json\n" . $lastSuccessJson, E_USER_WARNING);
        } elseif (!isset($lastGoodDta['files'])) {
            DebugMessage("$region $slug no files in the last success json?!", E_USER_WARNING);
        } else {
            usort($lastGoodDta['files'], 'AuctionFileSort');
            $fileInfo = end($lastGoodDta['files']);
            $oldModified = ceil(intval($fileInfo['lastModified'], 10) / 1000);
            DebugMessage("$region $slug returned no files. Checking headers on URL from " . date('Y-m-d H:i:s', $oldModified));

            $headers = \Newsstand\HTTP::Head(preg_replace('/^http:/', 'https:', $fileInfo['url']));
            if (isset($headers['Last-Modified'])) {
                $newModified = strtotime($headers['Last-Modified']);
                $fileInfo['lastModified'] = $newModified * 1000;
                $dta['files'] = [$fileInfo];

                if (abs($oldModified - $newModified) < 10) {
                    DebugMessage("$region $slug data file has unchanged last modified date from last successful parse.");
                } else {
                    DebugMessage("$region $slug data file modified " . date('Y-m-d H:i:s', $newModified) . ".");
                }
            } else {
                DebugMessage("$region $slug data file failed fetching last modified date via HEAD method.");
            }
        }
    }
    if (!isset($dta['files'])) {
        $delay = GetCheckDelay(strtotime($lastDate));
        DebugMessage("$region $slug returned no files. Waiting ".SecondsOrMinutes($delay).".", E_USER_WARNING);
        SetHouseNextCheck($house, time() + $delay, $json);
        \Newsstand\HTTP::AbandonConnections();
        return 0;
    }

    usort($dta['files'], 'AuctionFileSort');
    $fileInfo = end($dta['files']);

    $modified = ceil(intval($fileInfo['lastModified'], 10) / 1000);
    $lastDateUnix = is_null($lastDate) ? ($modified - 1) : strtotime($lastDate);
    $delay = 0;
    if (!is_null($minDelta) && ($modified <= $lastDateUnix)) {
        if (($lastDateUnix + $minDelta) > time()) {
            // we checked for an earlier-than-expected snapshot, didn't see one
            $delay = ($lastDateUnix + $minDelta) - time() + 8; // next check will be 8 seconds after expected update
        } else if (($lastDateUnix + $minDelta + 45) > time()) {
            // this is the first check after we expected a new snapshot, but didn't see one.
            // don't trust api, assume data file URL won't change, and check last-modified time on data file
            $headers = \Newsstand\HTTP::Head(preg_replace('/^http:/', 'https:', $fileInfo['url']));
            if (isset($headers['Last-Modified'])) {
                $newModified = strtotime($headers['Last-Modified']);
                if ($newModified > $modified) {
                    DebugMessage("$region $slug data file indicates last modified $newModified ".date('H:i:s', $newModified).", ignoring API result.");
                    $modified = $newModified;
                } else if ($newModified == $modified) {
                    DebugMessage("$region $slug data file has last modified date matching API result.");
                } else {
                    DebugMessage("$region $slug data file has last modified date earlier than API result: $newModified ".date('H:i:s', $newModified).".");
                }
            } else {
                DebugMessage("$region $slug data file failed fetching last modified date via HEAD method.");
            }
        }
    }
    if ($modified <= $lastDateUnix) {
        if ($delay <= 0) {
            $delay = GetCheckDelay($modified);
        }
        DebugMessage("$region $slug still not updated since $modified ".date('H:i:s', $modified)." (" . SecondsOrMinutes(time() - $modified) . " ago). Waiting ".SecondsOrMinutes($delay).".");
        SetHouseNextCheck($house, time() + $delay, $json);

        return 0;
    }

    DebugMessage("$region $slug updated $modified ".date('H:i:s', $modified)." (" . SecondsOrMinutes(time() - $modified) . " ago), fetching auction data file");
    $dlStart = microtime(true);
    $data = \Newsstand\HTTP::Get(preg_replace('/^http:/', 'https:', $fileInfo['url']), [], $outHeaders, DATA_FILE_CURLOPTS);
    $dlDuration = microtime(true) - $dlStart;
    if (!$data || (substr($data, -4) != "]\r\n}")) {
        \Newsstand\HTTP::AbandonConnections();
        if (!$data) {
            DebugMessage("$region $slug data file empty. Waiting 5 seconds and trying again.");
            sleep(5);
        } else {
            DebugMessage("$region $slug data file malformed. Waiting 10 seconds and trying again.");
            sleep(10);
        }
        $dlStart = microtime(true);
        $data = \Newsstand\HTTP::Get($fileInfo['url'] . (parse_url($fileInfo['url'], PHP_URL_QUERY) ? '&' : '?') . 'please', [], $outHeaders, DATA_FILE_CURLOPTS);
        $dlDuration = microtime(true) - $dlStart;
    }
    if (!$data) {
        DebugMessage("$region $slug data file empty. Will try again in 30 seconds.");
        SetHouseNextCheck($house, time() + 30, $json);
        \Newsstand\HTTP::AbandonConnections();

        return 10;
    }
    if (substr($data, -4) != "]\r\n}") {
        $delay = GetCheckDelay($modified);
        DebugMessage("$region $slug data file still probably malformed. Waiting ".SecondsOrMinutes($delay).".");
        SetHouseNextCheck($house, time() + $delay, $json);

        return 0;
    }

    $xferBytes = isset($outHeaders['X-Original-Content-Length']) ? $outHeaders['X-Original-Content-Length'] : strlen($data);
    DebugMessage("$region $slug data file " . strlen($data) . " bytes" . ($xferBytes != strlen($data) ? (' (transfer length ' . $xferBytes . ', ' . round($xferBytes / strlen($data) * 100, 1) . '%)') : '') . ", " . round($dlDuration, 2) . "sec, " . round($xferBytes / 1000 / $dlDuration) . "KBps");
    if ($xferBytes >= strlen($data) && strlen($data) > 65536) {
        DebugMessage('No compression? ' . print_r($outHeaders, true));
    }

    if ($xferBytes / 1000 / $dlDuration < 200 && in_array($region, ['US','EU'])) {
        DebugMessage("Speed under 200KBps, closing persistent connections");
        \Newsstand\HTTP::AbandonConnections();
    }

    $successJson = json_encode($dta); // will include any updates from using lastSuccessJson

    $nextCheck = null;
    if ($modified - $lastDateUnix <= MINIMUM_INTERVAL_SECONDS) {
        $nextCheck = date('Y-m-d H:i:s', $modified + MINIMUM_INTERVAL_SECONDS);
        DebugMessage(sprintf('%s %s update interval was %d seconds (<= %d), forcing next check at %s', $region, $slug, $modified - $lastDateUnix, MINIMUM_INTERVAL_SECONDS, date('H:i:s', $modified + MINIMUM_INTERVAL_SECONDS)));
    }

    $stmt = $db->prepare('INSERT INTO tblHouseCheck (house, nextcheck, lastcheck, lastcheckresult, lastchecksuccess, lastchecksuccessresult) VALUES (?, ?, now(), ?, now(), ?) ON DUPLICATE KEY UPDATE nextcheck=values(nextcheck), lastcheck=values(lastcheck), lastcheckresult=values(lastcheckresult), lastchecksuccess=values(lastchecksuccess), lastchecksuccessresult=values(lastchecksuccessresult)');
    $stmt->bind_param('isss', $house, $nextCheck, $json, $successJson);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare('INSERT INTO tblSnapshot (house, updated) VALUES (?, from_unixtime(?))');
    $stmt->bind_param('ii', $house, $modified);
    $stmt->execute();
    $stmt->close();

    MCSet('housecheck_'.$house, time(), 0);

    $fileName = "$modified-" . str_pad($house, 5, '0', STR_PAD_LEFT) . ".json";
    $data = gzencode($data);
    file_put_contents(SNAPSHOT_PATH . $fileName, $data, LOCK_EX);
    link(SNAPSHOT_PATH . $fileName, SNAPSHOT_PATH . 'parse/' . $fileName);
    if (in_array($region, ['US','EU'])) {
        link(SNAPSHOT_PATH . $fileName, SNAPSHOT_PATH . 'watch/' . $fileName);
    }
    unlink(SNAPSHOT_PATH . $fileName);

    return 0;
}

function ReleaseDBLock($lockName) {
    global $db;

    $stmt = $db->prepare('do release_lock(?)');
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $stmt->close();
}

function SecondsOrMinutes($sec) {
    if ($sec >= 180) {
        return round($sec/60,1).' minutes';
    }
    return "$sec seconds";
}

function GetCheckDelay($modified)
{
    $now = time();

    $delayMinutes = 0.5;
    if ($modified < ($now - 4200)) { // over 70 minutes ago
        $delayMinutes = 2;
    }
    if ($modified < ($now - 10800)) { // over 3 hours ago
        $delayMinutes = 5;
    }
    if ($modified < ($now - 21600)) { // over 6 hours ago
        $delayMinutes = 15;
    }

    return $delayMinutes * 60;
}

function SetHouseNextCheck($house, $nextCheck, $json)
{
    global $db;

    $stmt = $db->prepare('INSERT INTO tblHouseCheck (house, nextcheck, lastcheck, lastcheckresult) VALUES (?, from_unixtime(?), now(), ?) ON DUPLICATE KEY UPDATE nextcheck=values(nextcheck), lastcheck=values(lastcheck), lastcheckresult=values(lastcheckresult)');
    $stmt->bind_param('iis', $house, $nextCheck, $json);
    $stmt->execute();
    $stmt->close();

    MCSet('housecheck_'.$house, time(), 0);
}

function AuctionFileSort($a, $b)
{
    $am = intval($a['lastModified'], 10) / 1000;
    $bm = intval($b['lastModified'], 10) / 1000;
    return $am - $bm;
}
