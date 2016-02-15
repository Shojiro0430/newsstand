<?php

$memcache = new Memcache;
if (!$memcache->connect('127.0.0.1', 11211)) {
    DebugMessage('Cannot connect to memcached!', E_USER_ERROR);
}
$memcache->setCompressThreshold(50 * 1024);

function MCGetHouse($house, $key = 'ts')
{
    global $memcache;

    static $houseKeys = array();
    if ($key == 'ts') {
        if (isset($houseKeys[$house])) {
            return $houseKeys[$house];
        }
        $houseKeys[$house] = $memcache->get('h' . $house . '_ts');
        if ($houseKeys[$house] === false) {
            $houseKeys[$house] = 1;
            MCSetHouse($house, $key, $houseKeys[$house]); // so we don't query a billion times on this key
            $altDb = DBConnect(true);
            if ($altDb) {
                $stmt = $altDb->prepare('SELECT max(unix_timestamp(updated)) FROM tblSnapshot WHERE house = ?');
                $stmt->bind_param('i', $house);
                $stmt->execute();
                $stmt->bind_result($houseKeys[$house]);
                $gotHouse = $stmt->fetch() === true;
                $stmt->close();

                if ($gotHouse) {
                    MCSetHouse($house, $key, $houseKeys[$house]);
                } else {
                    $houseKeys[$house] = 1;
                }

                $altDb->close();
            }
        }
        return $houseKeys[$house];
    }

    return MCGet('h' . $house . '_' . MCGetHouse($house) . '_' . $key);
}

function MCSetHouse($house, $key, $val, $expire = 10800)
{
    global $memcache;

    $prefix = '';
    if ($key != 'ts') {
        $prefix = MCGetHouse($house) . '_';
    }

    $fullKey = 'h' . $house . '_' . $prefix . $key;
    return $memcache->set($fullKey, $val, 0, $expire);
}

function MCGet($key)
{
    global $memcache;

    //if (isset($_GET['refresh']))
    //    return false;

    return $memcache->get($key);
}

function MCSet($key, $val, $expire = 10800)
{
    global $memcache;

    return $memcache->set($key, $val, 0, $expire);
}

function MCAdd($key, $val, $expire = 10800)
{
    global $memcache;

    return $memcache->add($key, $val, 0, $expire);
}

function MCDelete($key)
{
    global $memcache;

    //if (isset($_GET['refresh']))
    //    return false;

    return $memcache->delete($key);
}

$MCHousesLocked = [];
function MCHouseLock($house, $waitSeconds = 30)
{
    global $MCHousesLocked;
    static $registeredShutdown = false;

    if (isset($MCHousesLocked[$house])) {
        if (PHP_SAPI == 'cli') {
            DebugMessage("Already have house lock for $house");
        }
        return true;
    }

    $giveUpAt = microtime(true) + $waitSeconds;
    $me = [
        'pid' => getmypid(),
        'script' => $_SERVER["SCRIPT_FILENAME"],
        'when' => time()
    ];
    do {
        if (MCAdd('mchouselock_'.$house, $me, 30*60)) {
            if (PHP_SAPI == 'cli') {
                DebugMessage("Obtained house lock for $house");
            }
            $MCHousesLocked[$house] = true;
            if (!$registeredShutdown) {
                $registeredShutdown = true;
                register_shutdown_function('MCHouseUnlock');
            }
            return true;
        }
        usleep(500000);
    } while ($giveUpAt > microtime(true));

    $currentLock = MCGet('mchouselock_'.$house);
    DebugMessage("Could not get house lock for $house, owned by ".$currentLock['pid'].' '.$currentLock['script'].' '.TimeDiff($currentLock['when']));

    return false;
}

function MCHouseUnlock($house = null)
{
    global $MCHousesLocked;

    if (is_null($house)) {
        $locked = array_keys($MCHousesLocked);
        foreach ($locked as $house) {
            MCHouseUnlock($house);
        }
    } else {
        if (PHP_SAPI == 'cli') {
            DebugMessage("Releasing house lock for $house");
        }
        MCDelete('mchouselock_'.$house);
        unset($MCHousesLocked[$house]);
    }
}

