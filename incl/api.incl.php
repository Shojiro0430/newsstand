<?php

require_once('memcache.incl.php');

define('API_MAINTENANCE', false);
define('API_VERSION', 2);
define('THROTTLE_PERIOD', 3600); // seconds
define('THROTTLE_MAXHITS', 200);
define('BANLIST_CACHEKEY', 'banlist_cidrs');
define('BANLIST_FILENAME', __DIR__ . '/banlist.txt');

// maintenance mode
if (API_MAINTENANCE && (php_sapi_name() != 'cli')) {
    header('HTTP/1.1 503 Service Unavailable');
    header('Cache-Control: no-cache');
    exit;
}

function json_return($json)
{
    if ($json === false) {
        header('HTTP/1.1 400 Bad Request');
        exit;
    }

    ini_set('zlib.output_compression', 1);

    if (!is_string($json)) {
        $json = json_encode($json, JSON_NUMERIC_CHECK);
    }

    header('Content-type: application/json');
    echo $json;
    exit;
}

function GetSiteRegion()
{
    return (isset($_SERVER['HTTP_HOST']) && (preg_match('/^eu./i', $_SERVER['HTTP_HOST']) > 0)) ? 'EU' : 'US';
}

function GetRealms($region)
{
    global $db;

    $cacheKey = 'realms2_' . $region;

    if ($realms = MCGet($cacheKey)) {
        return $realms;
    }

    DBConnect();

    $stmt = $db->prepare('SELECT r.* FROM tblRealm r WHERE region = ? AND (locale IS NOT NULL OR (locale IS NULL AND exists (SELECT 1 FROM tblSnapshot s WHERE s.house=r.house) AND (SELECT count(*) FROM tblRealm r2 WHERE r2.house=r.house AND r2.id != r.id AND r2.locale IS NOT NULL) = 0))');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $realms = DBMapArray($result);
    $stmt->close();

    MCSet($cacheKey, $realms);

    return $realms;
}

function GetRegion($house)
{
    global $db;

    $house = abs($house);
    if (($tr = MCGet('getregion_' . $house)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = 'SELECT max(region) FROM `tblRealm` WHERE house=?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $house);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    $tr = array_pop($tr);

    MCSet('getregion_' . $house, $tr, 24 * 60 * 60);

    return $tr;
}

function GetHouse($realm)
{
    global $db;

    if (($tr = MCGet('gethouse_' . $realm)) !== false) {
        return $tr;
    }

    DBConnect();

    $sql = 'SELECT house FROM `tblRealm` WHERE id=?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $realm);
    $stmt->execute();
    $result = $stmt->get_result();
    $tr = DBMapArray($result, null);
    $stmt->close();
    $tr = array_pop($tr);

    MCSet('gethouse_' . $realm, $tr);

    return $tr;
}

function BotCheck()
{
    if (IPIsBanned()) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    $c = UserThrottleCount();
    if ($c > THROTTLE_MAXHITS * 2) {
        BanIP();
    } else {
        if ($c > THROTTLE_MAXHITS) {
            header('Expires: 0');
            json_return(array('captcha' => CaptchaDetails()));
        }
    }
}

function BanIP($ip = false)
{
    if ($ip === false) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    if (!$ip) {
        return false;
    }
    $ip = trim(strtoupper($ip));

    if (!IPIsBanned($ip)) {
        file_put_contents(BANLIST_FILENAME, "\n$ip # " . Date('Y-m-d H:i:s'), FILE_APPEND & LOCK_EX);
        MCDelete(BANLIST_CACHEKEY);
        MCDelete(BANLIST_CACHEKEY . '_' . $ip);
    }

    header('HTTP/1.1 429 Too Many Requests');
    exit;
}

function IPIsBanned($ip = false)
{
    if ($ip === false) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    if (!$ip) {
        return false;
    }
    $ip = trim(strtoupper($ip));

    $cacheKey = BANLIST_CACHEKEY . '_' . $ip;
    $result = MCGet($cacheKey);
    if ($result !== false) {
        return $result == 'yes';
    }

    $banList = MCGet(BANLIST_CACHEKEY);
    if ($banList === false) {
        $banList = [];
        if (file_exists(BANLIST_FILENAME)) {
            $fh = fopen(BANLIST_FILENAME, 'r');
            if ($fh) {
                while (($line = fgets($fh, 4096)) !== false) {
                    if (preg_match('/^\s*([\d\.:\/]+)/', $line, $res) > 0) {
                        $banList[] = strtoupper($res[0]);
                    }
                }
            }
            fclose($fh);
        }
        MCSet(BANLIST_CACHEKEY, $banList, 86400);
    }

    $ipv4 = (strpos($ip, ':') === false);
    if ($ipv4) {
        $longIp = ip2long($ip);
    }
    for ($x = 0; $x < count($banList); $x++) {
        if (strpos($banList[$x], '/') !== false) {
            // mask
            if ($ipv4 && (strpos($banList[$x], ':') === false)) {
                // ipv4
                list($subnet, $mask) = explode('/', $banList[$x]);
                if (($longIp & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet)) {
                    $result = true;
                    break;
                }
            } elseif (!$ipv4 && (strpos($banList[$x], ':') !== false)) {
                // TODO ipv6 masks
            }
        } else {
            // single IP
            if ($ip == $banList[$x]) {
                $result = true;
                break;
            }
        }
    }

    MCSet($cacheKey, $result ? 'yes' : 'no');

    return $result;
}

function CaptchaDetails()
{
    global $db;

    $cacheKey = 'captcha_' . $_SERVER['REMOTE_ADDR'];
    if (($details = MCGet($cacheKey)) !== false) {
        return $details['public'];
    }

    DBConnect();

    $races = array(
        'bloodelf' => 10,
        'draenei'  => 11,
        'dwarf'    => 3,
        'gnome'    => 7,
        'goblin'   => 9,
        'human'    => 1,
        'nightelf' => 4,
        'orc'      => 2,
        'tauren'   => 6,
        'troll'    => 8,
        'undead'   => 5,
    );

    $raceExclude = array(
        $races['bloodelf'] => array($races['nightelf']),
        $races['nightelf'] => array($races['bloodelf']),
    );

    $keys = array_keys($races);
    $goodRace = $races[$keys[rand(0, count($keys) - 1)]];

    $howMany = rand(2, 3);

    $sql = 'SELECT * FROM tblCaptcha WHERE race = ? AND helm = 0 ORDER BY rand() LIMIT ?';

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $goodRace, $howMany);
    $stmt->execute();
    $result = $stmt->get_result();
    $goodRows = DBMapArray($result);
    $stmt->close();

    $sql = 'SELECT * FROM tblCaptcha WHERE race NOT IN (%s) ORDER BY rand() LIMIT %d';
    $exclude = array($goodRace);
    if (isset($raceExclude[$goodRace])) {
        $exclude = array_merge($exclude, $raceExclude[$goodRace]);
    }

    $sql = sprintf($sql, implode(',', $exclude), 12 - $howMany);
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $badRows = DBMapArray($result);
    $stmt->close();

    $allRows = array_merge($goodRows, $badRows);
    shuffle($allRows);

    $details = array(
        'answer' => '',
        'public' => array(
            'lookfor' => $goodRace,
            'ids'     => array()
        )
    );

    for ($x = 0; $x < count($allRows); $x++) {
        if (isset($goodRows[$allRows[$x]['id']])) {
            $details['answer'] .= ($x + 1);
        }
        $details['public']['ids'][] = $allRows[$x]['id'];
    };

    MCSet($cacheKey, $details);

    return $details['public'];
}

function UserThrottleCount($reset = false)
{
    static $returned = false;
    if (!$reset && $returned !== false) {
        return $returned;
    }

    global $memcache;

    $k = 'throttle_%s_' . $_SERVER['REMOTE_ADDR'];
    $kTime = sprintf($k, 'time');
    $kCount = sprintf($k, 'count');

    if ($reset) {
        $memcache->delete($kTime);
        return $returned = 0;
    }

    $vals = $memcache->get(array($kTime, $kCount));
    $memcache->set($kTime, time(), false, THROTTLE_PERIOD);
    if (!isset($vals[$kTime]) || !isset($vals[$kCount]) || ($vals[$kTime] < time() - THROTTLE_PERIOD)) {
        $memcache->set($kCount, 1, false, THROTTLE_PERIOD * 2);
        return $returned = 1;
    }
    $memcache->increment($kCount);

    return $returned = ++$vals[$kCount];
}

function HouseETag($house)
{
    $curTag = 'W/"' . MCGetHouse($house) . '"';
    $theirTag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';

    if ($curTag && $curTag == $theirTag) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    header('ETag: ' . $curTag);
}
