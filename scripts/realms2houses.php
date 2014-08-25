<?php

chdir(__DIR__);

require_once('../incl/incl.php');
require_once('../incl/heartbeat.incl.php');
require_once('../incl/memcache.incl.php');

RunMeNTimes(1);
CatchKill();

if (!DBConnect())
    DebugMessage('Cannot connect to db!', E_USER_ERROR);

$regions = array('US','EU');

foreach ($regions as $region)
{
    heartbeat();
    if ($caughtKill)
        break;
    $url = sprintf('https://%s.api.battle.net/wow/realm/status', strtolower($region));

    $json = FetchHTTP($url);
    $realms = json_decode($json, true);
    if (json_last_error() != JSON_ERROR_NONE)
    {
        DebugMessage("$url did not return valid JSON");
        continue;
    }

    if (!isset($realms['realms']) || (count($realms['realms']) == 0))
    {
        DebugMessage("$url returned no realms");
        continue;
    }

    $stmt = $db->prepare('select ifnull(max(id), 0) from tblRealm');
    $stmt->execute();
    $stmt->bind_result($nextId);
    $stmt->fetch();
    $stmt->close();
    $nextId++;

    $stmt = $db->prepare('insert into tblRealm (id, region, slug, name, locale) values (?, ?, ?, ?, ?) on duplicate key update name=values(name), locale=values(locale)');
    $realms = $realms['realms'];
    foreach ($realms as $realm)
    {
        $stmt->bind_param('issss', $nextId, $region, $realm['slug'], $realm['name'], $realm['locale']);
        $stmt->execute();
        if ($db->affected_rows > 0)
            $nextId++;
        $stmt->reset();
    }
    $stmt->close();

    $stmt = $db->prepare('select slug, house from tblRealm where region = ?');
    $stmt->bind_param('s', $region);
    $stmt->execute();
    $result = $stmt->get_result();
    $houses = DBMapArray($result);
    $stmt->close();

    $stmt = $db->prepare('select ifnull(max(house),0) from tblRealm');
    $stmt->execute();
    $stmt->bind_result($maxHouse);
    $stmt->fetch();
    $stmt->close();

    $hashes = array();
    $retries = 0;

    usort($realms, function($a,$b){
        $r = strcmp($a['battlegroup'],$b['battlegroup']);
        if ($r != 0) return $r;
        $r = strcmp($a['type'],$b['type']);
        if ($r != 0) return $r;
        $r = strcmp($a['locale'],$b['locale']);
        if ($r != 0) return $r;
        $r = $a['tol-barad']['status'] - $b['tol-barad']['status'];
        if ($r != 0) return $r;
        $r = $a['tol-barad']['controlling-faction'] - $b['tol-barad']['controlling-faction'];
        if ($r != 0) return $r;
        $r = $a['tol-barad']['next'] - $b['tol-barad']['next'];
        return $r;
    });

    $lastTime = 0;
    $lastHash = '';

    foreach ($realms as $r)
    {
        heartbeat();
        if ($caughtKill)
            break 2;

        $n = $r['tol-barad']['next'];
        $thisHash = 'x' . $r['type'] . 'x' . $r['tol-barad']['controlling-faction'] . 'x' . $r['tol-barad']['status'] . $r['battlegroup'] . $r['locale'];
        if (($lastHash != $thisHash) || ($n - $lastTime > 1000))
            $lastTime = $n;

        $lastHash = $thisHash;
        $hash = md5(''.$lastTime.$lastHash);

        $houses[$r['slug']]['hash'] = $hash;
        if (!isset($hashes[$hash]))
            $hashes[$hash] = array();

        $hashes[$hash][] = $r['slug'];
    }

    heartbeat();
    if ($caughtKill)
        break;
    foreach ($hashes as $hash => $slugs)
    {
        $candidates = array();
        foreach ($slugs as $slug)
            if (!is_null($houses[$slug]['house']))
            {
                if (!isset($candidates[$houses[$slug]['house']]))
                    $candidates[$houses[$slug]['house']] = 0;
                $candidates[$houses[$slug]['house']]++;
            }
        if (count($candidates) > 0)
        {
            asort($candidates);
            $curHouse = array_keys($candidates);
            $curHouse = array_pop($curHouse);
        }
        else
            $curHouse = ++$maxHouse;
        $houseKeys = array_keys($houses);
        foreach ($houseKeys as $slug)
        {
            if (in_array($slug, $slugs))
                continue;
            if ($houses[$slug]['house'] == $curHouse)
                $houses[$slug]['house'] = null;
        }
        foreach ($slugs as $slug)
        {
            if ($houses[$slug]['house'] != $curHouse)
            {
                DebugMessage("$region $slug changing from ".(is_null($houses[$slug]['house']) ? 'null' : $houses[$slug]['house'])." to $curHouse");
                $db->real_query(sprintf('update tblRealm set house = %d where region = \'%s\' and slug = \'%s\'', $curHouse, $db->escape_string($region), $db->escape_string($slug)));
                $houses[$slug]['house'] = $curHouse;
            }
        }
    }
    $memcache->delete('realms_'.$region);
}

CleanOldHouses();

DebugMessage('Done!');

function CleanOldHouses()
{
    global $db, $caughtKill;

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblAuction where house not in (select cast(house as signed) * -1 from tblRealm union select cast(house as signed) from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblAuction where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out auctions from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblHouseCheck where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();
    if (count($oldIds))
        $db->real_query(sprintf('delete from tblHouseCheck where house in (%s)', implode(',',$oldIds)));

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblItemHistory where house not in (select cast(house as signed) * -1 from tblRealm union select cast(house as signed) from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblItemHistory where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out item history from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblItemSummary where house not in (select cast(house as signed) * -1 from tblRealm union select cast(house as signed) from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblItemSummary where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out item summary from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

    if ($caughtKill)
        return;

    $sql = 'select distinct house from tblSnapshot where house not in (select distinct house from tblRealm)';
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldIds = DBMapArray($result);
    $stmt->close();

    $sql = 'delete from tblSnapshot where house = %d limit 2000';
    foreach ($oldIds as $oldId)
    {
        if ($caughtKill)
            return;

        DebugMessage('Clearing out snapshots from old house '.$oldId);

        while (!$caughtKill)
        {
            heartbeat();
            $ok = $db->real_query(sprintf($sql, $oldId));
            if (!$ok || $db->affected_rows == 0)
                break;
        }
    }

}